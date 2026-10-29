<?php

namespace App\Http\Controllers\Site;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Insights\Services\EventRecorder;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Lab\Services\Calculators;
use App\Domain\Lab\Services\CampaignSimulator;
use App\Domain\Lab\Services\ChainResolver;
use App\Domain\Lab\Services\ExperimentPicker;
use App\Domain\Lab\Services\GeneratorEngine;
use App\Domain\Lab\Services\LabCatalog;
use App\Domain\Lab\Services\TextDiff;
use App\Domain\Workspace\Models\Project;
use App\Http\Controllers\Controller;
use App\Support\Viewer;
use Illuminate\Http\Request;

/**
 * المختبر — /tools (§4.6), صفحة الأداة — /tools/{tool} (§4.7), الحاسبات (§4.8) والمحاكي، وصفحة الأصل المشترك /a/{token}.
 * Visitors generate, check and copy; saving, AI enhancement and sharing need an account.
 */
class LabController extends Controller
{
    public function __construct(
        private readonly GeneratorEngine $engine,
        private readonly ChainResolver $chain,
        private readonly ExperimentPicker $experiments,
        private readonly EventRecorder $events,
        private readonly Viewer $viewer,
    ) {}

    public function index(Request $request)
    {
        $tools = Tool::query()->where('is_published', true)->orderBy('position')->get();
        $category = (string) $request->query('cat', '');

        return view('site.tools.index', [
            'generators' => $tools->where('kind', 'generator')->values(), 'calculators' => $tools->where('kind', 'calculator')->values(), 'simulator' => $tools->firstWhere('kind', 'simulator'),
            'categories' => Tool::CATEGORIES, 'category' => in_array($category, Tool::CATEGORIES, true) ? $category : '',
        ]);
    }

    public function show(Request $request, Tool $tool)
    {
        abort_unless($tool->is_published, 404);
        if ($tool->kind === 'calculator') {
            return redirect()->route('tools.calculator', $tool->slug);
        }
        if ($tool->kind === 'simulator') {
            return redirect()->route('tools.simulator');
        }
        $user = $this->member($request);
        $projects = $user ? $this->projects($request) : collect();
        $project = $this->currentProject($request, $projects);
        $sessionAssets = (array) session('lab.assets', []);
        $assets = $this->chain->assets($project, $user ? [] : $sessionAssets);
        $prefill = $this->chain->prefill($tool, $project, $assets);
        $result = session('lab.result.'.$tool->slug);
        $input = $result['input'] ?? [];
        if ($request->query('example') === '1') {
            $input = $tool->example_input ?? [];
            $result = null;
        } elseif ($request->query('fresh') === '1') {
            $input = [];
            $result = null;
        }
        $fields = [];
        foreach ($tool->fields() as $field) {
            $key = $field['key'];
            $value = $input[$key] ?? ($prefill[$key]['value'] ?? ($field['default'] ?? ''));
            $fields[] = $field + ['value' => $value, 'prefilled' => ! isset($input[$key]) && isset($prefill[$key]), 'source_label' => $prefill[$key]['label'] ?? null, 'options_map' => LabCatalog::options($field)];
        }
        $chain = $this->chain->chain($tool, $project, $assets);
        $saved = $user && $project ? ToolOutput::query()->where('project_id', $project->id)->where('output_type', $tool->output_type)->orderByDesc('version')->get() : collect();
        $latest = $saved->first();
        $diff = $latest && $saved->count() > 1 ? TextDiff::lines((string) $saved[1]->body_markdown, (string) $latest->body_markdown) : null;
        $pendingDiff = $result && $latest && $latest->body_markdown !== $result['markdown'] ? TextDiff::lines((string) $latest->body_markdown, $result['markdown']) : null;
        $examples = ToolOutput::query()->where('tool_id', $tool->id)->where('payload->illustrative', true)->latest('id')->limit(3)->get();
        $relatedLesson = Lesson::query()->whereNotNull('published_at')->when($tool->course_slug, fn ($q) => $q->whereHas('course', fn ($c) => $c->where('slug', $tool->course_slug)))->with('course')->orderBy('position')->first()
            ?? Lesson::query()->whereNotNull('published_at')->with('course')->orderBy('position')->first();
        $relatedArticle = ContentItem::published()->where('topic', 'like', '%'.$tool->category.'%')->latest('published_at')->first() ?? ContentItem::published()->latest('published_at')->first();
        $this->events->record($request, 'visit.tool', ['tool_id' => $tool->id]);

        return view('site.tools.show', [
            'tool' => $tool, 'fields' => $fields, 'chain' => $chain, 'result' => $result, 'member' => (bool) $user, 'projects' => $projects, 'project' => $project,
            'saved' => $saved, 'latest' => $latest, 'diff' => $diff, 'pendingDiff' => $pendingDiff, 'examples' => $examples,
            'relatedLesson' => $relatedLesson, 'relatedArticle' => $relatedArticle, 'aiEnabled' => filled(config('services.openai.key')),
            'isSaved' => $result && $latest && $latest->body_markdown === $result['markdown'],
            'checks' => \App\Domain\Lab\Services\QualityChecker::CHECKS,
        ]);
    }

    /** Generate the base asset (no AI, no account): the result lives in the session and feeds the next tool in the chain. */
    public function generate(Request $request, Tool $tool)
    {
        abort_unless($tool->is_published && $tool->kind === 'generator', 404);
        $user = $this->member($request);
        $projects = $user ? $this->projects($request) : collect();
        if ($user && $request->filled('project_id') && $projects->firstWhere('id', (int) $request->input('project_id'))) {
            session(['lab.project_id' => (int) $request->input('project_id')]);
        }
        $version = $this->experiments->versionFor($tool, $request);
        $generated = $this->engine->generate($tool, $request->all(), $version);
        if ($generated['missing'] !== []) {
            session(['lab.result.'.$tool->slug => ['input' => $generated['input']] + ['markdown' => null]]);

            return redirect()->to(route('tools.show', $tool).'#inputs')->withErrors(['lab' => 'أكمل الحقول: '.implode('، ', $generated['missing'])]);
        }
        $result = ['markdown' => $generated['markdown'], 'title' => $generated['title'], 'input' => $generated['input'], 'quality' => $generated['quality'], 'version_id' => $version?->id, 'enhanced' => false, 'notice' => null, 'at' => now()->toDateTimeString()];
        session(['lab.result.'.$tool->slug => $result]);
        $assets = (array) session('lab.assets', []);
        $assets[$tool->output_type] = ['input' => $generated['input'], 'title' => $generated['title'], 'slug' => $tool->slug, 'markdown' => $generated['markdown'], 'at' => $result['at']];
        session(['lab.assets' => $assets]);
        $this->experiments->expose($tool, $request);
        $this->events->record($request, 'tool.generated', ['tool_id' => $tool->id, 'meta' => ['version_id' => $version?->id, 'verdict' => $generated['quality']['verdict'], 'prefilled' => array_keys(array_filter($request->all(), fn ($v, $k) => str_starts_with($k, '_src_'), ARRAY_FILTER_USE_BOTH))]]);

        return redirect()->to(route('tools.show', $tool).'#asset')->with('status', 'أُنشئ أصلك بلا ذكاء اصطناعي. افحص البنود الستة ثم احفظه أو تابع إلى الخطوة التالية في السلسلة.');
    }

    public function calculator(Request $request, string $calc)
    {
        abort_unless(isset(LabCatalog::CALCULATORS[$calc]), 404);
        $tool = Tool::query()->where('slug', $calc)->where('is_published', true)->firstOrFail();
        $input = [];
        foreach ($tool->fields() as $field) {
            $input[$field['key']] = $request->query($field['key'], $field['default'] ?? null);
        }
        if ($request->query->count() > 0) {
            $this->events->record($request, 'calculator.used', ['tool_id' => $tool->id]);
        }

        return view('site.tools.calculator', ['tool' => $tool, 'input' => $input, 'result' => Calculators::compute($calc, $input), 'calculators' => Tool::query()->where('kind', 'calculator')->where('is_published', true)->orderBy('position')->get()]);
    }

    public function simulator(Request $request)
    {
        $tool = Tool::query()->where('kind', 'simulator')->where('is_published', true)->firstOrFail();
        $input = [];
        foreach ($tool->fields() as $field) {
            $input[$field['key']] = $request->query($field['key'], $field['default'] ?? null);
        }
        if ($request->query->count() > 0) {
            $this->events->record($request, 'simulator.used', ['tool_id' => $tool->id]);
        }

        return view('site.tools.simulator', ['tool' => $tool, 'input' => $input, 'result' => CampaignSimulator::simulate($input), 'benchmarks' => CampaignSimulator::BENCHMARKS]);
    }

    /** Public read-only page of a shared asset (no account, noindex). 404 once the owner revokes the link. */
    public function shared(string $token)
    {
        $output = ToolOutput::query()->where('share_token', $token)->with(['tool', 'project'])->firstOrFail();

        return response()->view('site.assets.shared', ['output' => $output])->header('X-Robots-Tag', 'noindex');
    }

    private function member(Request $request)
    {
        return $this->viewer->isMember() && ! $this->viewer->isBeginner() ? $request->user() : null;
    }

    private function projects(Request $request)
    {
        $ids = $request->user()->workspaces()->pluck('workspaces.id');

        return Project::query()->whereIn('workspace_id', $ids)->orderBy('id')->get();
    }

    private function currentProject(Request $request, $projects): ?Project
    {
        if ($projects->isEmpty()) {
            return null;
        }
        $id = (int) ($request->query('project', session('lab.project_id', 0)));

        return $projects->firstWhere('id', $id) ?? $projects->first();
    }
}
