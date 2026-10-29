<?php

namespace App\Http\Controllers\Site;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\ResourceTemplate;
use App\Domain\Insights\Services\EventRecorder;
use App\Domain\Lab\Services\AssetVault;
use App\Domain\Workspace\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['topic' => ['nullable', 'string', 'max:100'], 'format' => ['nullable', 'in:markdown,pdf,csv']]);
        $resources = ResourceTemplate::query()->where('is_published', true)->with(['lesson.course', 'tool'])
            ->when($filters['topic'] ?? null, fn ($q, $topic) => $q->whereHas('lesson.course', fn ($c) => $c->where('slug', $topic)))
            // Markdown and PDF are representations of every template; table export needs fields.
            ->when(($filters['format'] ?? '') === 'csv', fn ($q) => $q->whereJsonLength('template', '>', 0))
            ->orderBy('name')->paginate(12)->withQueryString();
        $topics = Course::query()->where('is_published', true)->orderBy('position')->get();

        return view('site.resources', compact('resources', 'topics', 'filters'));
    }

    public function show(Request $request, ResourceTemplate $template)
    {
        abort_unless($template->is_published, 404);
        $template->load(['lesson.course', 'tool']);
        $projects = $request->user() ? Project::query()->whereIn('workspace_id', $request->user()->workspaces()->wherePivotIn('role', ['owner', 'editor'])->pluck('workspaces.id'))->orderBy('name')->get() : collect();

        return view('site.resources.show', ['template' => $template, 'markdown' => $this->body($template), 'projects' => $projects]);
    }

    public function download(Request $request, ResourceTemplate $template, EventRecorder $events)
    {
        abort_unless($template->is_published, 404);
        $data = $request->validate(['format' => ['nullable', 'in:markdown,pdf,csv']]);
        $format = $data['format'] ?? 'markdown';
        $body = $this->body($template);
        $template->increment('downloads_count');
        $events->record($request, 'template.downloaded', ['lesson_id' => $template->lesson_id, 'meta' => ['template' => $template->slug, 'format' => $format]]);
        if ($format === 'pdf') {
            return response()->view('site.assets.print', ['title' => $template->name, 'markdown' => $body])->header('X-Robots-Tag', 'noindex');
        }
        if ($format === 'csv') {
            return response()->streamDownload(function () use ($template) {
                $stream = fopen('php://output', 'w');
                fwrite($stream, "\xEF\xBB\xBF");
                fputcsv($stream, ['الحقل', 'القيمة'], ',', '"', '');
                foreach ($template->template ?? [] as $key => $field) {
                    $label = is_array($field) ? ($field['label'] ?? $field['key'] ?? $key) : $field;
                    $label = (string) $label;
                    if (preg_match('/^[\s]*[=+@\-]/u', $label)) {
                        $label = "'".$label;
                    }
                    fputcsv($stream, [$label, ''], ',', '"', '');
                }
                fclose($stream);
            }, $template->slug.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response($body, 200, ['Content-Type' => 'text/markdown; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$template->slug.'.md"']);
    }

    public function save(Request $request, ResourceTemplate $template, AssetVault $vault)
    {
        abort_unless($template->is_published, 404);
        $data = $request->validate(['project_id' => ['required', 'integer']]);
        $project = Project::query()->findOrFail($data['project_id']);
        $workspace = $this->memberWorkspace($request, $project->workspace_id);
        abort_unless(in_array($workspace->pivot->role, ['owner', 'editor'], true), 403);
        // A blank template is not evidence of a completed lesson.
        $output = $vault->save($request->user(), $project, $template->tool, $template->name, 'resource_'.$template->id, $this->body($template), [], null, null, null, ['source' => 'resource', 'template_id' => $template->id]);

        return redirect()->route('app.outputs.show', $output)->with('status', 'حُفظ القالب في مشروعك. افتح الدرس المرتبط لتطبيقه.');
    }

    private function body(ResourceTemplate $template): string
    {
        if ($template->body_markdown) {
            return $template->body_markdown;
        }
        $lines = ['# '.$template->name, '', $template->description, ''];
        foreach ($template->template ?? [] as $key => $field) {
            $label = is_array($field) ? ($field['label'] ?? $field['key'] ?? $key) : $field;
            $lines[] = '## '.$label."\n\n________________\n";
        }

        return implode("\n", $lines);
    }
}
