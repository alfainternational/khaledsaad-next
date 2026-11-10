<?php

namespace App\Http\Controllers\App;

use App\Domain\Assessment\Services\Catalog;
use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Insights\Services\EventRecorder;
use App\Domain\Lab\Ai\AiContext;
use App\Domain\Lab\Ai\UsageRecorder;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Services\AssetVault;
use App\Domain\Lab\Services\BrandReader;
use App\Domain\Lab\Services\GeneratorEngine;
use App\Domain\Workspace\Models\Project;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * اقرأ موقعك (spec 2026-11-09 §5، الموجة 1) — خطوتان مفصولتان عمداً.
 *
 * `store` reads the page and SAVES NOTHING to the project. `apply` writes the fields the member ticked. The split is
 * the whole design: the project profile is their own words and the hub all nine generators prefill from, so a crawl
 * proposing into it is help, and a crawl overwriting it is the platform talking over the person it serves.
 *
 * The read is charged only when a model was actually used, and the credit is reserved before the call and released
 * if it fails — the same ledger discipline as every other AI path here.
 */
class BrandProfileController extends Controller
{
    public function store(
        Request $request,
        Tool $tool,
        BrandReader $reader,
        CreditLedger $ledger,
        AssetVault $vault,
        GeneratorEngine $engine,
        UsageRecorder $usage,
        EventRecorder $events,
    ): RedirectResponse {
        abort_unless($tool->is_published && $tool->isReader(), 404);
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'url' => ['required', 'string', 'max:2000'],
        ]);
        $project = Project::query()->findOrFail($data['project_id']);
        $workspace = $this->memberWorkspace($request, $project->workspace_id);
        abort_unless(in_array($workspace->pivot->role, ['owner', 'editor'], true), 403);

        $aiEnabled = $reader->aiEnabled();
        $cost = $aiEnabled ? (int) $tool->credit_cost : 0;
        if ($aiEnabled) {
            try {
                $usage->assertWithinQuota($workspace);
            } catch (DomainException $exception) {
                return back()->withErrors(['reader' => $exception->getMessage()])->withInput();
            }
        }

        $publicId = (string) Str::uuid();
        try {
            if ($cost > 0) {
                $ledger->reserve($workspace, "reader:{$publicId}:reserve", $cost, 'حجز قراءة الموقع');
            }
        } catch (DomainException $exception) {
            return back()->withErrors(['reader' => $exception->getMessage()])->withInput();
        }

        try {
            $result = $reader->read($data['url'], new AiContext(
                workspace: $workspace,
                userId: $request->user()->id,
                purpose: 'reader:'.$tool->slug,
                operationKey: "reader:{$publicId}:ai",
                // The page is public, but what the model returns describes the member's business, so it is handled
                // under the same rule as their project data: never a provider that trains on what it is sent.
                sensitive: true,
                cacheable: false,
            ));
        } catch (DomainException $exception) {
            $this->release($ledger, $workspace, $publicId, $cost);

            return back()->withErrors(['reader' => $exception->getMessage()])->withInput();
        } catch (Throwable $exception) {
            $this->release($ledger, $workspace, $publicId, $cost);
            report($exception);

            return back()->withErrors(['reader' => 'تعذّرت قراءة الصفحة. أعد المحاولة أو جرّب صفحة أخرى من الموقع نفسه.'])->withInput();
        }

        // Charged only for a read a model actually took part in; a base extraction is free, like every base output here.
        $charged = $result['used_ai'] ? $cost : 0;
        if ($cost > 0) {
            $ledger->record($workspace, "reader:{$publicId}:release", 'release', $cost, 'تحويل حجز القراءة');
            if ($charged > 0) {
                $ledger->record($workspace, "reader:{$publicId}:capture", 'capture', -$charged, 'قراءة موقع '.$result['facts']['host']);
            }
        }

        $proposal = $result['proposal'];
        $markdown = $engine->renderTemplate((string) ($tool->output_template), $proposal + [
            'host' => $result['facts']['host'],
            'url' => $result['facts']['url'],
            'sector_label' => Catalog::label(Catalog::SECTORS, $proposal['sector'] ?? null, '— لم تقله الصفحة'),
            'channel_label' => Catalog::label(Catalog::CHANNELS, $proposal['channel'] ?? null, '— لم تظهر قناة'),
            'evidence' => $this->evidence($result['facts']),
        ]);

        $output = $vault->save(
            $request->user(), $project, $tool,
            'ملف العلامة — '.Str::limit($proposal['brand'] ?: $result['facts']['host'], 50, '…'),
            (string) $tool->output_type, $markdown,
            ['url' => $result['facts']['url']] + array_intersect_key($proposal, array_flip(BrandReader::FIELDS)),
            null, null, null,
            ['source' => 'reader', 'used_ai' => $result['used_ai'], 'via' => $result['facts']['via'], 'host' => $result['facts']['host']],
        );

        session(['lab.project_id' => $project->id, 'lab.reader.'.$tool->slug => [
            'markdown' => $markdown, 'proposal' => $proposal, 'facts' => $result['facts'], 'used_ai' => $result['used_ai'],
            'notice' => $result['notice'], 'credits' => $charged, 'output_id' => $output->id, 'at' => now()->toDateTimeString(),
        ]]);
        $events->record($request, 'reader.used', ['tool_id' => $tool->id, 'meta' => ['host' => $result['facts']['host'], 'used_ai' => $result['used_ai'], 'credits' => $charged]]);
        $events->record($request, 'asset.saved', ['tool_id' => $tool->id, 'meta' => ['version' => $output->version, 'reader' => true]]);

        return redirect()->to(route('tools.reader', ['project' => $project->id]).'#reader-result')
            ->with('status', $charged > 0
                ? 'قُرئ الموقع وحُفظ ملف العلامة كإصدار v'.$output->version.' ('.$charged.' رصيد). راجعه ثم طبّقه على مشروعك.'
                : 'قُرئ الموقع وحُفظ الاستخلاص القاعدي كإصدار v'.$output->version.' بلا خصم رصيد. راجعه ثم طبّقه على مشروعك.');
    }

    /** The second step: only the fields the member ticked, and only the four the project profile actually holds. */
    public function apply(Request $request, Tool $tool, Project $project, EventRecorder $events): RedirectResponse
    {
        abort_unless($tool->is_published && $tool->isReader(), 404);
        $workspace = $this->memberWorkspace($request, $project->workspace_id);
        abort_unless(in_array($workspace->pivot->role, ['owner', 'editor'], true), 403);

        $data = $request->validate([
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => [Rule::in(['offer', 'audience', 'sector', 'channel'])],
            'offer' => ['nullable', 'string', 'max:300'],
            'audience' => ['nullable', 'string', 'max:300'],
            'sector' => ['nullable', Rule::in(array_keys(Catalog::SECTORS))],
            'channel' => ['nullable', Rule::in(array_keys(Catalog::CHANNELS))],
        ]);

        $profile = $project->profile ?? [];
        $applied = [];
        foreach ($data['fields'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value === '') {
                continue;
            }
            $profile[$field] = $value;
            $applied[] = $field;
        }
        if ($applied === []) {
            return back()->withErrors(['reader' => 'لم تختر حقلاً فيه قيمة. الحقول الفارغة لا تُطبَّق.']);
        }

        $project->update(['profile' => array_filter($profile, fn ($v) => $v !== null && $v !== '')]);
        $events->record($request, 'reader.applied', ['tool_id' => $tool->id, 'meta' => ['fields' => $applied]]);

        return back()->with('status', 'طُبِّقت '.count($applied).' حقول على ملف المشروع؛ ستُعبَّأ المولّدات منها تلقائياً.');
    }

    private function release(CreditLedger $ledger, $workspace, string $publicId, int $cost): void
    {
        if ($cost > 0) {
            $ledger->record($workspace, "reader:{$publicId}:release", 'release', $cost, 'فك حجز قراءة لم تكتمل');
        }
    }

    /** What the page itself said, quoted back, so the member can check the proposal against the source. */
    private function evidence(array $facts): string
    {
        $lines = [];
        if (trim((string) $facts['title']) !== '') {
            $lines[] = '- **عنوان الصفحة**: '.$facts['title'];
        }
        if (trim((string) $facts['lead']) !== '') {
            $lines[] = '- **أول فقرة**: '.Str::limit($facts['lead'], 300, '…');
        }
        if ($facts['headings'] !== []) {
            $lines[] = '- **العناوين**: '.implode(' · ', array_slice($facts['headings'], 0, 8));
        }
        if ($facts['prices'] !== []) {
            $lines[] = '- **أسعار معلنة**: '.implode('، ', $facts['prices']);
        }
        if ($facts['channels'] !== []) {
            $lines[] = '- **قنوات ظاهرة**: '.implode('، ', array_map(fn ($c) => Catalog::label(Catalog::CHANNELS, $c, $c), $facts['channels']));
        }
        $lines[] = '- **حجم النصّ المقروء**: '.number_format((int) $facts['words']).' كلمة'.($facts['via'] === 'reader' ? ' (عبر قارئ الصفحات)' : ' (من الصفحة مباشرة)');

        return implode("\n", $lines);
    }
}
