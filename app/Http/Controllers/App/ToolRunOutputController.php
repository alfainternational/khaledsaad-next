<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ToolRunOutputController extends Controller
{
    public function destroy(Request $request, int $toolRun): RedirectResponse
    {
        $run = DB::table('tool_runs')->where('id', $toolRun)->first();
        abort_unless($run, 404);
        $workspace = $this->memberWorkspace($request, $run->workspace_id);
        abort_unless(in_array($workspace->pivot->role, ['owner', 'editor'], true), 403);
        abort_unless($run->status !== 'running' && (int) $run->reserved_credits === 0, 409);
        DB::table('tool_runs')->where('id', $run->id)->delete();

        return back()->with('status', 'تم حذف المخرج المحفوظ.');
    }
}
