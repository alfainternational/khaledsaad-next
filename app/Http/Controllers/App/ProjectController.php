<?php

namespace App\Http\Controllers\App;

use App\Domain\Workspace\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProjectController extends Controller
{
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

    public function show(Request $request, Project $project): View
    {
        $workspace = $this->memberWorkspace($request, $project->workspace_id);
        $runs = DB::table('tool_runs')
            ->join('tools', 'tools.id', '=', 'tool_runs.tool_id')
            ->where('tool_runs.project_id', $project->id)
            ->where('tool_runs.status', 'completed')
            ->latest('tool_runs.created_at')
            ->select('tool_runs.*', 'tools.name as tool_name')
            ->get()
            ->map(function ($run) {
                $run->output = json_decode($run->output_payload, true) ?: [];

                return $run;
            });

        return view('app.projects.show', compact('project', 'workspace', 'runs'));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $workspace = $this->memberWorkspace($request, $project->workspace_id);
        $this->authorizeMutation($workspace->pivot->role);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $project->update($data);

        return back()->with('status', 'تم تحديث المشروع.');
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
