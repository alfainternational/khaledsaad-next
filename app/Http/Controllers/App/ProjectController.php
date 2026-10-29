<?php

namespace App\Http\Controllers\App;

use App\Domain\Assessment\Services\Catalog;
use App\Domain\Lab\Models\PlanAssembly;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Lab\Services\AssetVault;
use App\Domain\Lab\Services\ChainResolver;
use App\Domain\Workspace\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * مشروعي — /app/projects/{project} (blueprint §5.4): the project profile that pre-fills every tool, the project's assets,
 * notes, and the assembled one-page plan when the chain is complete.
 */
class ProjectController extends Controller
{
    public function __construct(private readonly ChainResolver $chain, private readonly AssetVault $vault) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['workspace_id' => ['required', 'integer'], 'name' => ['required', 'string', 'max:120']]);
        $workspace = $this->memberWorkspace($request, $data['workspace_id']);
        $this->authorizeMutation($workspace->pivot->role);
        $base = Str::slug($data['name']) ?: 'project';
        $slug = $base;
        if ($workspace->projects()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(5));
        }
        $project = $workspace->projects()->create(['name' => $data['name'], 'slug' => $slug]);

        return redirect()->route('app.projects.show', $project)->with('status', 'تم إنشاء المشروع.');
    }

    public function show(Request $request, Project $project)
    {
        $workspace = $this->memberWorkspace($request, $project->workspace_id);
        $outputs = ToolOutput::query()->where('project_id', $project->id)->with(['tool', 'lesson'])->orderByDesc('created_at')->get();
        $plan = $this->chain->plan($project, $this->chain->assets($project, []));
        $assembly = PlanAssembly::query()->where('project_id', $project->id)->first();

        return view('app.projects.show', [
            'project' => $project, 'workspace' => $workspace, 'outputs' => $outputs, 'plan' => $plan, 'assembly' => $assembly,
            'sectors' => Catalog::SECTORS, 'channels' => Catalog::CHANNELS, 'goals' => Catalog::GOALS, 'profile' => $project->profile ?? [],
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $workspace = $this->memberWorkspace($request, $project->workspace_id);
        $this->authorizeMutation($workspace->pivot->role);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'offer' => ['nullable', 'string', 'max:300'], 'sector' => ['nullable', Rule::in(array_keys(Catalog::SECTORS))], 'audience' => ['nullable', 'string', 'max:300'],
            'goal' => ['nullable', Rule::in(array_keys(Catalog::GOALS))], 'goal_number' => ['nullable', 'integer', 'min:0', 'max:1000000'], 'channel' => ['nullable', Rule::in(array_keys(Catalog::CHANNELS))],
            'budget' => ['nullable', 'integer', 'min:0', 'max:100000000'], 'city' => ['nullable', 'string', 'max:80'], 'notes' => ['nullable', 'string', 'max:4000'],
        ]);
        $profile = $project->profile ?? [];
        foreach (['offer', 'sector', 'audience', 'goal', 'goal_number', 'channel', 'budget', 'city', 'notes'] as $key) {
            if (array_key_exists($key, $data)) {
                $profile[$key] = $data[$key] === null || $data[$key] === '' ? null : $data[$key];
            }
        }
        $project->update(['name' => $data['name'], 'profile' => array_filter($profile, fn ($v) => $v !== null)]);

        return back()->with('status', 'حُدّث ملف المشروع؛ ستُعبَّأ نماذج الأدوات منه تلقائياً.');
    }

    /** The assembled one-page plan (blueprint §4.7 item 5). */
    public function plan(Request $request, Project $project)
    {
        $this->memberWorkspace($request, $project->workspace_id);
        $plan = $this->chain->plan($project, $this->chain->assets($project, []));
        $assembly = $this->vault->assemblePlan($project, $plan) ?? PlanAssembly::query()->where('project_id', $project->id)->first();

        return view('app.projects.plan', ['project' => $project, 'plan' => $plan, 'assembly' => $assembly, 'markdown' => $this->vault->planMarkdown($project)]);
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $workspace = $this->memberWorkspace($request, $project->workspace_id);
        abort_unless($workspace->pivot->role === 'owner', 403);
        $project->delete();

        return redirect('/dashboard')->with('status', 'تم حذف المشروع.');
    }

    private function authorizeMutation(string $role): void
    {
        abort_unless(in_array($role, ['owner', 'editor'], true), 403);
    }
}
