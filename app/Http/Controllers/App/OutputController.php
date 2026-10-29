<?php

namespace App\Http\Controllers\App;

use App\Domain\Insights\Services\EventRecorder;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Lab\Services\AssetVault;
use App\Domain\Lab\Services\ChainResolver;
use App\Domain\Lab\Services\ExperimentPicker;
use App\Domain\Lab\Services\LabCatalog;
use App\Domain\Lab\Services\QualityChecker;
use App\Domain\Lab\Services\TextDiff;
use App\Domain\Workspace\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * أصولي — /app/outputs (blueprint §5.3), the asset page with versions, saving a generated asset into a project,
 * and the read-only share link (create / revoke).
 */
class OutputController extends Controller
{
    public function __construct(private readonly AssetVault $vault, private readonly ChainResolver $chain, private readonly ExperimentPicker $experiments, private readonly EventRecorder $events) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $projects = Project::query()->whereIn('workspace_id', $user->workspaces()->pluck('workspaces.id'))->orderBy('id')->get();
        $type = (string) $request->query('type', '');
        $projectId = (int) $request->query('project', 0);
        $outputs = ToolOutput::query()->where('user_id', $user->id)->with(['tool', 'lesson', 'project'])
            ->when($type !== '', fn ($q) => $q->where('output_type', $type))
            ->when($projectId > 0, fn ($q) => $q->where('project_id', $projectId))
            ->orderByDesc('created_at')->get();
        $types = ToolOutput::query()->where('user_id', $user->id)->distinct()->pluck('output_type')->filter()->mapWithKeys(fn ($t) => [$t => LabCatalog::label($t)]);

        return view('app.outputs.index', compact('outputs', 'projects', 'type', 'projectId', 'types'));
    }

    public function show(Request $request, ToolOutput $output)
    {
        $this->own($request, $output);
        $output->load(['tool', 'lesson.course', 'project']);
        $versions = $output->lineage();
        $previous = $versions->first(fn ($v) => $v->version < $output->version);
        $diff = $previous ? TextDiff::lines((string) $previous->body_markdown, (string) $output->body_markdown) : null;

        return view('app.outputs.show', ['output' => $output, 'versions' => $versions, 'diff' => $diff, 'checks' => QualityChecker::CHECKS]);
    }

    /** Save the asset generated on the tool page (session) as a new version in the chosen project. */
    public function store(Request $request, Tool $tool)
    {
        abort_unless($tool->is_published && $tool->kind === 'generator', 404);
        $data = $request->validate(['project_id' => ['required', 'integer'], 'title' => ['required', 'string', 'max:160'], 'markdown' => ['required', 'string', 'max:60000']]);
        $project = Project::query()->findOrFail($data['project_id']);
        $workspace = $this->memberWorkspace($request, $project->workspace_id);
        abort_unless(in_array($workspace->pivot->role, ['owner', 'editor'], true), 403);
        $result = session('lab.result.'.$tool->slug, []);
        $markdown = str_replace("\r\n", "\n", $data['markdown']);
        $version = isset($result['version_id']) ? $tool->versions()->whereKey($result['version_id'])->first() : $tool->activeVersion();
        $output = $this->vault->save($request->user(), $project, $tool, $data['title'], (string) $tool->output_type, $markdown, $result['input'] ?? [], $result['quality'] ?? null, null, $version, ['source' => 'tool', 'enhanced' => (bool) ($result['enhanced'] ?? false)]);
        session(['lab.project_id' => $project->id]);
        $this->experiments->convert($tool, $request);
        $this->events->record($request, 'asset.saved', ['tool_id' => $tool->id, 'meta' => ['version' => $output->version, 'version_id' => $version?->id]]);
        $plan = $this->chain->plan($project, $this->chain->assets($project, []));
        $assembly = $this->vault->assemblePlan($project, $plan);
        $status = 'حُفظ الأصل في مشروعك «'.$project->name.'» كإصدار v'.$output->version.'.';
        if ($assembly) {
            $status .= ' اكتملت السلسلة: خطة مشروعك مجمّعة الآن في صفحة واحدة.';
        }

        return redirect()->to(route('tools.show', [$tool, 'project' => $project->id]).'#asset')->with('status', $status);
    }

    public function destroy(Request $request, ToolOutput $output)
    {
        $this->own($request, $output);
        $output->delete();

        return redirect()->route('app.outputs.index')->with('status', 'حُذف الأصل «'.$output->title.'» (v'.$output->version.').');
    }

    public function share(Request $request, ToolOutput $output)
    {
        $this->own($request, $output);
        $this->vault->share($output);
        $this->events->record($request, 'asset.shared', ['tool_id' => $output->tool_id]);

        return back()->with('status', 'أُنشئ رابط القراءة. كل من يملك الرابط يقرأ الأصل بلا تسجيل حتى تلغيه.')->with('share_url', route('assets.shared', $output->share_token));
    }

    public function unshare(Request $request, ToolOutput $output)
    {
        $this->own($request, $output);
        $this->vault->unshare($output);

        return back()->with('status', 'أُلغي رابط المشاركة؛ لم يعد الأصل متاحاً للقراءة العامة.');
    }

    private function own(Request $request, ToolOutput $output): void
    {
        abort_unless($output->user_id === $request->user()->id, 403);
    }
}
