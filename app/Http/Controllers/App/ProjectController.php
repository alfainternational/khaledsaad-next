<?php

namespace App\Http\Controllers\App;

use App\Domain\Workspace\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function show(Request $request, Project $project)
    {
        $allowed = $request->user()->workspaces()->whereKey($project->workspace_id)->exists();
        abort_unless($allowed, 403);

        return view('app.projects.show', compact('project'));
    }
}
