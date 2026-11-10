<?php

namespace App\Http\Controllers\App;

use App\Domain\Insights\Services\EventRecorder;
use App\Domain\Lab\Models\Media;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Services\AssetVault;
use App\Domain\Lab\Services\ExperimentPicker;
use App\Domain\Lab\Services\GeneratorEngine;
use App\Domain\Lab\Services\MediaLibrary;
use App\Domain\Lab\Services\VisualStudio;
use App\Domain\Workspace\Models\Project;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * تشغيل استوديو الأصول البصرية (plan 2026-11-09 البند 4). No model is called, so no credit is reserved and no quota
 * is touched — this is the one member-facing tool whose output costs the platform nothing but CPU.
 *
 * What it does cost is trust with untrusted bytes. An upload is validated on its decoded header, never on the name
 * or the client's content type; the stored path is a uuid, never the member's file name; and re-encoding through GD
 * drops EXIF, which is how a product photo stops carrying the GPS coordinates of someone's home.
 */
class VisualStudioController extends Controller
{
    public function store(
        Request $request,
        Tool $tool,
        VisualStudio $studio,
        MediaLibrary $library,
        GeneratorEngine $engine,
        AssetVault $vault,
        ExperimentPicker $experiments,
        EventRecorder $events,
    ): RedirectResponse {
        abort_unless($tool->is_published && $tool->isStudio(), 404);

        $limits = (array) config('studio.limits');
        $maxKilobytes = (int) ceil($limits['max_bytes'] / 1024);
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            // `image` makes Laravel verify the decoded type, not the extension the client claims.
            'source' => ['required', 'file', 'image', 'mimetypes:'.implode(',', (array) $limits['mimes']), 'max:'.$maxKilobytes],
            'logo' => ['nullable', 'file', 'image', 'mimetypes:image/png,image/webp,image/jpeg', 'max:2048'],
            'headline' => ['nullable', 'string', 'max:300'],
            'templates' => ['required', 'array', 'min:1', 'max:'.(int) $limits['max_templates']],
            'templates.*' => [Rule::in(array_keys($studio->templates()))],
        ]);

        $project = Project::query()->findOrFail($data['project_id']);
        $workspace = $this->memberWorkspace($request, $project->workspace_id);
        abort_unless(in_array($workspace->pivot->role, ['owner', 'editor'], true), 403);

        $version = $experiments->versionFor($tool, $request);
        $headline = trim((string) ($data['headline'] ?? ''));

        try {
            $assets = $studio->compose(
                (string) file_get_contents($request->file('source')->getRealPath()),
                $data['templates'],
                $headline,
                $request->hasFile('logo') ? (string) file_get_contents($request->file('logo')->getRealPath()) : null,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['studio' => $exception->getMessage()])->withInput();
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['studio' => 'تعذّرت معالجة الصورة. جرّب صورة أخرى أو صيغة JPG.'])->withInput();
        }

        /** @var array<int, Media> $stored */
        $stored = [];
        $rows = [];
        foreach ($assets as $asset) {
            $media = $library->store(
                $asset['bytes'],
                $asset['extension'],
                $asset['mime'],
                $project,
                $request->user(),
                'visual-asset',
                public: false,
                attributes: ['width' => $asset['width'], 'height' => $asset['height'], 'original_name' => $asset['key'].'.'.$asset['extension']],
            );
            $stored[] = $media;
            $rows[] = '| '.$asset['label'].' | '.$asset['width'].'×'.$asset['height'].' | ['.$asset['key'].'.'.$asset['extension'].']('.route('media.show', $media).') |';
        }

        $title = $headline !== '' ? Str::limit($headline, 60, '…') : 'أصول بصرية — '.$project->name;
        $markdown = $engine->renderTemplate((string) ($version?->output_template ?: $tool->output_template), [
            'headline_or_project' => $headline !== '' ? $headline : $project->name,
            'assets_table' => implode("\n", $rows),
        ]);

        $output = $vault->save($request->user(), $project, $tool, $title, (string) $tool->output_type, $markdown, [
            'headline' => $headline, 'templates' => $data['templates'],
        ], null, null, $version, ['source' => 'studio', 'media_ids' => collect($stored)->pluck('id')->all()]);

        // Attach after the asset exists: until now every file was an orphan with a 90-day clock on it.
        foreach ($stored as $media) {
            $library->attach($media, $output);
        }

        session(['lab.project_id' => $project->id, 'lab.studio.'.$tool->slug => [
            'markdown' => $markdown, 'title' => $title, 'headline' => $headline, 'templates' => $data['templates'],
            'media' => collect($stored)->map(fn (Media $m) => ['id' => $m->id, 'url' => route('media.show', $m), 'label' => $m->original_name, 'width' => $m->width, 'height' => $m->height, 'size' => $m->humanSize()])->all(),
            'output_id' => $output->id, 'at' => now()->toDateTimeString(),
        ]]);
        $experiments->convert($tool, $request);
        $events->record($request, 'studio.used', ['tool_id' => $tool->id, 'meta' => ['assets' => count($stored), 'headline' => $headline !== '']]);
        $events->record($request, 'asset.saved', ['tool_id' => $tool->id, 'meta' => ['version' => $output->version, 'studio' => true]]);

        return redirect()->to(route('tools.studio', ['project' => $project->id]).'#studio-result')
            ->with('status', 'جُهّزت '.count($stored).' أصول وحُفظت كإصدار v'.$output->version.' بلا خصم رصيد.');
    }
}
