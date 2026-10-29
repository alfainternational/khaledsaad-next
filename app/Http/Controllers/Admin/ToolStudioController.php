<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Lab\Models\Experiment;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolVersion;
use App\Domain\Lab\Services\GeneratorEngine;
use App\Domain\Lab\Services\LabCatalog;
use App\Domain\Lab\Services\QualityChecker;
use App\Domain\Lab\Services\StudioMetrics;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * استوديو الأداة — /admin/tools و /admin/tools/{tool} (blueprint §6.4): field schema, output template, template versions with A/B,
 * a test run with sample input, and the first quality metric: save rate (saved ÷ generated) per tool and per version. Every change is audited.
 */
class ToolStudioController extends Controller
{
    private const ROLES = ['editor', 'admin', 'super_admin'];

    public function __construct(private readonly StudioMetrics $metrics, private readonly GeneratorEngine $engine) {}

    public function index(Request $request)
    {
        $this->authorizeEditor($request);
        $tools = Tool::query()->where('slug', '!=', 'lesson-lab')->orderBy('kind')->orderBy('position')->get();
        $rates = $this->metrics->saveRates();
        $experiments = Experiment::query()->where('subject_type', 'tool')->where('status', 'running')->get()->keyBy('subject_id');

        return view('admin.tools.index', compact('tools', 'rates', 'experiments'));
    }

    public function show(Request $request, Tool $tool)
    {
        $this->authorizeEditor($request);
        $tool->load('versions');
        $experiment = Experiment::query()->where('subject_type', 'tool')->where('subject_id', $tool->id)->where('status', 'running')->with('variants')->first();
        $sample = session('studio.sample.'.$tool->id) ?? $tool->example_input ?? [];
        $test = session('studio.test.'.$tool->id);

        return view('admin.tools.show', [
            'tool' => $tool, 'versions' => $tool->versions, 'experiment' => $experiment, 'sample' => $sample, 'test' => $test,
            'rate' => $this->metrics->saveRates()[$tool->id] ?? ['generated' => 0, 'saved' => 0, 'rate' => null],
            'versionRates' => $this->metrics->versionRates($tool), 'experimentRates' => $experiment ? $this->metrics->experimentRates($experiment) : [],
            'checks' => QualityChecker::CHECKS, 'categories' => Tool::CATEGORIES, 'chainTypes' => LabCatalog::CHAIN + LabCatalog::LESSON_ASSETS + ['profile' => 'ملف المشروع'],
            'audit' => DB::table('audit_logs')->where('subject_type', 'tool')->where('subject_id', (string) $tool->id)->orderByDesc('id')->limit(12)->get(),
        ]);
    }

    /** Schema, template, quality rules, chain and publication of the tool itself (the live definition the public page uses). */
    public function update(Request $request, Tool $tool)
    {
        $this->authorizeEditor($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'description' => ['required', 'string', 'max:600'], 'tagline' => ['nullable', 'string', 'max:300'],
            'category' => ['required', Rule::in(Tool::CATEGORIES)], 'credit_cost' => ['required', 'integer', 'min:0', 'max:50'], 'is_published' => ['nullable', 'boolean'],
            'input_schema' => ['nullable', 'string', 'max:20000'], 'output_template' => ['nullable', 'string', 'max:30000'],
            'required_rules' => ['nullable', 'array'], 'required_rules.*' => [Rule::in(array_keys(QualityChecker::CHECKS))],
            'advisory_rules' => ['nullable', 'array'], 'advisory_rules.*' => [Rule::in(array_keys(QualityChecker::CHECKS))],
            'feeds_from' => ['nullable', 'array'], 'feeds_from.*' => ['string', 'max:60'], 'feeds_into' => ['nullable', 'array'], 'feeds_into.*' => ['string', 'max:60'],
        ]);
        $schema = $tool->input_schema;
        if ($tool->kind === 'generator' && filled($data['input_schema'] ?? null)) {
            $decoded = json_decode($data['input_schema'], true);
            if (! is_array($decoded) || array_filter($decoded, fn ($f) => ! is_array($f) || empty($f['key']) || empty($f['label'])) !== []) {
                return back()->withErrors(['input_schema' => 'مخطط الحقول يجب أن يكون JSON لقائمة حقول، كل حقل بـ key وlabel.'])->withInput();
            }
            $schema = array_values($decoded);
        }
        $before = ['schema' => hash('sha256', json_encode($tool->input_schema)), 'template' => hash('sha256', (string) $tool->output_template)];
        $tool->update([
            'name' => $data['name'], 'description' => $data['description'], 'tagline' => $data['tagline'] ?? null, 'category' => $data['category'], 'credit_cost' => $data['credit_cost'],
            'is_published' => (bool) ($data['is_published'] ?? false), 'input_schema' => $schema,
            'output_template' => $tool->kind === 'generator' ? ($data['output_template'] ?? $tool->output_template) : null,
            'quality_rules' => $tool->kind === 'generator' ? ['required' => array_values($data['required_rules'] ?? []), 'advisory' => array_values(array_diff($data['advisory_rules'] ?? [], $data['required_rules'] ?? []))] : [],
            'feeds_from' => array_values($data['feeds_from'] ?? []), 'feeds_into' => array_values($data['feeds_into'] ?? []),
        ]);
        $this->audit($request, 'tool.updated', $tool, ['before' => $before, 'after' => ['schema' => hash('sha256', json_encode($tool->input_schema)), 'template' => hash('sha256', (string) $tool->output_template)], 'published' => $tool->is_published]);

        return back()->with('status', 'حُفظت الأداة. التغيير في المخطط والقالب يظهر فوراً على الصفحة العامة.');
    }

    /** A new template version (copy of the current definition, editable label and AI instructions). Activation is explicit. */
    public function storeVersion(Request $request, Tool $tool)
    {
        $this->authorizeEditor($request);
        abort_unless($tool->kind === 'generator', 404);
        $data = $request->validate(['label' => ['required', 'string', 'max:120'], 'output_template' => ['required', 'string', 'max:30000'], 'ai_instructions' => ['nullable', 'string', 'max:2000'], 'activate' => ['nullable', 'boolean']]);
        $version = $tool->versions()->create([
            'version' => ((int) $tool->versions()->max('version')) + 1, 'label' => $data['label'], 'input_schema' => $tool->input_schema, 'output_schema' => ['markdown', 'quality'],
            'output_template' => $data['output_template'], 'ai_instructions' => $data['ai_instructions'] ?? null, 'prompt_key' => 'marketing-ar-v1',
            'config_hash' => hash('sha256', json_encode([$tool->input_schema, $data['output_template']], JSON_UNESCAPED_UNICODE)), 'is_active' => false, 'created_by' => $request->user()->id,
        ]);
        if ($data['activate'] ?? false) {
            $this->activate($tool, $version);
        }
        $this->audit($request, 'tool.version_created', $tool, ['version' => $version->version, 'activated' => (bool) ($data['activate'] ?? false)]);

        return back()->with('status', 'أُنشئ الإصدار v'.$version->version.($data['activate'] ?? false ? ' وفُعّل.' : '. فعّله عندما تريد أن يولّد منه المتعلمون.'));
    }

    public function activateVersion(Request $request, Tool $tool, ToolVersion $version)
    {
        $this->authorizeEditor($request);
        abort_unless($version->tool_id === $tool->id, 404);
        $this->activate($tool, $version);
        $tool->update(['output_template' => $version->output_template ?: $tool->output_template]);
        $this->audit($request, 'tool.version_activated', $tool, ['version' => $version->version]);

        return back()->with('status', 'الإصدار v'.$version->version.' هو الفعّال الآن.');
    }

    /** Test inside the studio with sample input: renders the asset and the six checks without touching learners' data. */
    public function test(Request $request, Tool $tool)
    {
        $this->authorizeEditor($request);
        abort_unless($tool->kind === 'generator', 404);
        $versionId = (int) $request->input('version_id', 0);
        $version = $versionId > 0 ? $tool->versions()->whereKey($versionId)->first() : $tool->activeVersion();
        $generated = $this->engine->generate($tool, $request->except(['_token', 'version_id']), $version);
        session(['studio.sample.'.$tool->id => $generated['input'], 'studio.test.'.$tool->id => ['markdown' => $generated['markdown'], 'quality' => $generated['quality'], 'missing' => $generated['missing'], 'version' => $version?->version, 'at' => now()->toDateTimeString()]]);

        return redirect()->to(route('admin.tools.show', $tool).'#test')->with('status', $generated['missing'] === [] ? 'نُفّذ الاختبار على الإصدار v'.($version?->version ?? '—').'.' : 'نُفّذ الاختبار مع حقول ناقصة: '.implode('، ', $generated['missing']));
    }

    /** Start an A/B between two versions (one experiment per tool at a time). */
    public function startExperiment(Request $request, Tool $tool)
    {
        $this->authorizeEditor($request);
        abort_unless($tool->kind === 'generator', 404);
        $data = $request->validate(['variant_a' => ['required', 'integer', 'different:variant_b'], 'variant_b' => ['required', 'integer'], 'weight_a' => ['required', 'integer', 'min:10', 'max:90'], 'name' => ['nullable', 'string', 'max:120']]);
        $ids = $tool->versions()->whereIn('id', [$data['variant_a'], $data['variant_b']])->pluck('id');
        abort_unless($ids->count() === 2, 422);
        if (Experiment::query()->where('subject_type', 'tool')->where('subject_id', $tool->id)->where('status', 'running')->exists()) {
            return back()->withErrors(['experiment' => 'توجد تجربة قيد التشغيل على هذه الأداة؛ أنهِها أولاً (تجربة واحدة في كل مرة).']);
        }
        $experiment = Experiment::query()->create(['name' => $data['name'] ?: 'A/B قالب '.$tool->name, 'subject_type' => 'tool', 'subject_id' => $tool->id, 'metric' => 'save_rate', 'status' => 'running', 'created_by' => $request->user()->id, 'started_at' => now()]);
        $experiment->variants()->createMany([
            ['label' => 'A', 'tool_version_id' => $data['variant_a'], 'weight' => $data['weight_a']],
            ['label' => 'B', 'tool_version_id' => $data['variant_b'], 'weight' => 100 - $data['weight_a']],
        ]);
        $this->audit($request, 'tool.experiment_started', $tool, ['experiment_id' => $experiment->id, 'variants' => [$data['variant_a'], $data['variant_b']], 'weight_a' => $data['weight_a']]);

        return back()->with('status', 'بدأت تجربة A/B: '.$data['weight_a'].'٪ من التوليدات على A و'.(100 - $data['weight_a']).'٪ على B. معدل الحفظ لكل منهما يظهر هنا.');
    }

    public function endExperiment(Request $request, Tool $tool, Experiment $experiment)
    {
        $this->authorizeEditor($request);
        abort_unless($experiment->subject_type === 'tool' && (int) $experiment->subject_id === $tool->id, 404);
        $data = $request->validate(['winner' => ['nullable', 'integer']]);
        $winner = isset($data['winner']) ? $experiment->variants()->whereKey($data['winner'])->first() : null;
        $experiment->update(['status' => 'ended', 'ended_at' => now(), 'winner_variant_id' => $winner?->id]);
        if ($winner && $winner->tool_version_id) {
            $version = $tool->versions()->whereKey($winner->tool_version_id)->first();
            if ($version) {
                $this->activate($tool, $version);
                $tool->update(['output_template' => $version->output_template ?: $tool->output_template]);
            }
        }
        $this->audit($request, 'tool.experiment_ended', $tool, ['experiment_id' => $experiment->id, 'winner_variant_id' => $winner?->id]);

        return back()->with('status', $winner ? 'انتهت التجربة واعتُمد الإصدار الفائز ('.$winner->label.').' : 'انتهت التجربة بلا اعتماد؛ الإصدار الفعّال كما هو.');
    }

    private function activate(Tool $tool, ToolVersion $version): void
    {
        $tool->versions()->update(['is_active' => false]);
        $version->update(['is_active' => true]);
    }

    private function authorizeEditor(Request $request): void
    {
        abort_unless(in_array($request->user()?->platform_role, self::ROLES, true), 403);
    }

    private function audit(Request $request, string $action, Tool $tool, array $context): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id, 'action' => $action, 'subject_type' => 'tool', 'subject_id' => (string) $tool->id,
            'context' => json_encode($context, JSON_UNESCAPED_UNICODE), 'request_id' => $request->attributes->get('request_id'), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
