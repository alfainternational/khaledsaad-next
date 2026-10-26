<?php

namespace App\Http\Controllers\App;

use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Services\AiGateway;
use App\Domain\Lab\Services\CampaignBriefBuilder;
use App\Domain\Workspace\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ToolRunController extends Controller
{
    public function store(Request $request, Tool $tool, CampaignBriefBuilder $builder, AiGateway $ai, CreditLedger $ledger): RedirectResponse
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'integer'],
            'project_id' => ['required', 'integer'],
            'goal' => ['required', 'string', 'max:1000'],
            'audience' => ['required', 'string', 'max:1000'],
            'offer' => ['required', 'string', 'max:1000'],
        ]);
        $workspace = Workspace::query()->findOrFail($data['workspace_id']);
        abort_unless($request->user()->workspaces()->whereKey($workspace->id)->exists(), 403);
        $project = Project::query()->where('workspace_id', $workspace->id)->findOrFail($data['project_id']);
        if ($tool->credit_cost > 0 && $ledger->balance($workspace) < $tool->credit_cost) {
            return back()->withErrors(['tool' => 'الرصيد غير كافٍ لتشغيل هذه الأداة.'])->withInput();
        }

        $publicId = (string) Str::uuid();
        $runId = DB::table('tool_runs')->insertGetId([
            'workspace_id' => $workspace->id, 'project_id' => $project->id, 'tool_id' => $tool->id,
            'user_id' => $request->user()->id, 'public_id' => $publicId, 'status' => 'running',
            'input_payload' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'output_payload' => null, 'reserved_credits' => $tool->credit_cost, 'used_credits' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($tool->credit_cost > 0) {
            $ledger->record($workspace, "run:{$publicId}:reserve", 'reserve', -$tool->credit_cost, 'حجز تشغيل '.$tool->name, ['run_id' => $runId]);
        }

        try {
            $output = $tool->kind === 'deterministic'
                ? $builder->build($data)
                : $ai->generate("الهدف: {$data['goal']}\nالجمهور: {$data['audience']}\nالعرض: {$data['offer']}\nأنشئ نتيجة عملية لأداة: {$tool->name}.");

            if ($tool->credit_cost > 0) {
                $ledger->record($workspace, "run:{$publicId}:release", 'release', $tool->credit_cost, 'تحويل الحجز إلى استخدام');
                $ledger->record($workspace, "run:{$publicId}:capture", 'capture', -$tool->credit_cost, 'تشغيل '.$tool->name, ['run_id' => $runId]);
            }
            DB::table('tool_runs')->where('id', $runId)->update([
                'status' => 'completed', 'output_payload' => json_encode($output, JSON_UNESCAPED_UNICODE),
                'reserved_credits' => 0, 'used_credits' => $tool->credit_cost, 'updated_at' => now(),
            ]);
        } catch (DomainException $exception) {
            if ($tool->credit_cost > 0) {
                $ledger->record($workspace, "run:{$publicId}:release", 'release', $tool->credit_cost, 'فك حجز تشغيل فاشل');
            }
            DB::table('tool_runs')->where('id', $runId)->update([
                'status' => 'failed', 'reserved_credits' => 0, 'error_code' => 'provider_unavailable', 'updated_at' => now(),
            ]);

            return back()->withErrors(['tool' => $exception->getMessage()])->withInput();
        }

        return back()->with('tool_output', $output);
    }
}
