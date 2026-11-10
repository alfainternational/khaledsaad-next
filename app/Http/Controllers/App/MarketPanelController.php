<?php

namespace App\Http\Controllers\App;

use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Insights\Services\EventRecorder;
use App\Domain\Lab\Ai\AiContext;
use App\Domain\Lab\Ai\UsageRecorder;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Services\AiGateway;
use App\Domain\Lab\Services\AssetVault;
use App\Domain\Lab\Services\ExperimentPicker;
use App\Domain\Lab\Services\SyntheticMarket;
use App\Domain\Workspace\Models\Project;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * تشغيل لوحة السوق المحاكى (plan 2026-11-09 البند 3). Same ledger discipline as «حسّن بالذكاء الاصطناعي» — reserve
 * before the first call, capture on success, release on failure — with one difference that matters: a panel is many
 * calls, so it reserves the planned maximum and captures only the voice-rounds that actually answered. A half-spoken
 * panel is still useful, and charging the full price for it would not be honest.
 *
 * The run record keeps what reproducing it needs (seed, voices, rounds, provider, model, tool version), which is what
 * «حتمية» means for a language model: the record is reproducible even though the model is not.
 */
class MarketPanelController extends Controller
{
    public function store(Request $request, Tool $tool, SyntheticMarket $market, AiGateway $ai, CreditLedger $ledger, AssetVault $vault, ExperimentPicker $experiments, EventRecorder $events, UsageRecorder $usage): RedirectResponse
    {
        abort_unless($tool->is_published && $tool->isPanel(), 404);
        $data = $request->validate(['project_id' => ['required', 'integer']]);
        $project = Project::query()->findOrFail($data['project_id']);
        $workspace = $this->memberWorkspace($request, $project->workspace_id);
        abort_unless(in_array($workspace->pivot->role, ['owner', 'editor'], true), 403);

        $version = $experiments->versionFor($tool, $request);
        $plan = $market->plan($tool, $request->all());
        if ($plan['missing'] !== []) {
            return back()->withErrors(['panel' => 'أكمل الحقول: '.implode('، ', $plan['missing'])])->withInput();
        }
        if (! $ai->enabled()) {
            return back()->withErrors(['panel' => 'اللوحة تحتاج مزوّد ذكاء اصطناعي مفعّلاً. لا يوجد مخرج قاعدي بلا نموذج، ولم يُخصم أي رصيد.'])->withInput();
        }

        // The monthly allowance is checked before the ledger is touched, exactly as in the tool run path — but for the
        // whole panel: a run that cannot fit in what is left of the month is refused instead of stopping half-spoken.
        try {
            $usage->assertWithinQuota($workspace, $plan['calls']);
        } catch (DomainException $exception) {
            return back()->withErrors(['panel' => $exception->getMessage()])->withInput();
        }

        $publicId = (string) Str::uuid();
        $reserved = $plan['credits'];
        try {
            $runId = DB::transaction(function () use ($workspace, $project, $tool, $version, $request, $plan, $publicId, $ledger, $reserved): int {
                if ($reserved > 0) {
                    $ledger->reserve($workspace, "panel:{$publicId}:reserve", $reserved, 'حجز لوحة السوق المحاكى ('.$plan['calls'].' صوت/جولة)');
                }

                return DB::table('tool_runs')->insertGetId([
                    'workspace_id' => $workspace->id, 'project_id' => $project->id, 'tool_id' => $tool->id, 'tool_version_id' => $version?->id,
                    'user_id' => $request->user()->id, 'public_id' => $publicId, 'status' => 'running',
                    'input_payload' => json_encode([
                        'input' => $plan['input'], 'panel' => ['voices' => $plan['voices'], 'rounds' => $plan['rounds'], 'calls' => $plan['calls'], 'seed' => $plan['seed']],
                    ], JSON_UNESCAPED_UNICODE),
                    'output_payload' => null, 'reserved_credits' => $reserved, 'used_credits' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }, 3);
        } catch (DomainException $exception) {
            return back()->withErrors(['panel' => $exception->getMessage()])->withInput();
        }

        try {
            $result = $market->run($tool, $plan, $version, new AiContext(
                workspace: $workspace,
                userId: $request->user()->id,
                runId: $runId,
                purpose: 'panel:'.$tool->slug,
                operationKey: "panel:{$publicId}",
            ));
            $used = min($reserved, $result['credits']);
            if ($reserved > 0) {
                $ledger->record($workspace, "panel:{$publicId}:release", 'release', $reserved, 'تحويل حجز اللوحة إلى استخدام');
                if ($used > 0) {
                    $ledger->record($workspace, "panel:{$publicId}:capture", 'capture', -$used, 'لوحة السوق المحاكى', ['run_id' => $runId, 'answered' => $result['answered']]);
                }
            }
            DB::table('tool_runs')->where('id', $runId)->update([
                'status' => 'completed',
                'output_payload' => json_encode([
                    'text' => $result['markdown'], 'counts' => $result['counts'], 'decision' => $result['decision'],
                    'answered' => $result['answered'], 'partial' => $result['partial'], 'provider' => $result['provider'], 'model' => $result['model'], 'cached' => $result['cached'],
                ], JSON_UNESCAPED_UNICODE),
                'reserved_credits' => 0, 'used_credits' => $used, 'updated_at' => now(),
            ]);
            $output = $vault->save($request->user(), $project, $tool, $result['title'], (string) $tool->output_type, $result['markdown'], $plan['input'], null, null, $version, [
                'source' => 'panel', 'run_id' => $runId, 'seed' => $plan['seed'], 'counts' => $result['counts'], 'provider' => $result['provider'], 'model' => $result['model'],
            ]);
        } catch (Throwable $exception) {
            DB::transaction(function () use ($reserved, $ledger, $workspace, $publicId, $runId): void {
                if ($reserved > 0) {
                    $ledger->record($workspace, "panel:{$publicId}:release", 'release', $reserved, 'فك حجز لوحة لم تكتمل');
                }
                DB::table('tool_runs')->where('id', $runId)->update(['status' => 'failed', 'reserved_credits' => 0, 'error_code' => 'provider_unavailable', 'updated_at' => now()]);
            });
            report($exception);
            $message = $exception instanceof DomainException ? $exception->getMessage() : 'تعذر تشغيل اللوحة. تم استرجاع الرصيد المحجوز بالكامل ويمكن إعادة المحاولة.';

            return back()->withErrors(['panel' => $message])->withInput();
        }

        session([
            'lab.project_id' => $project->id,
            'lab.panel.'.$tool->slug => [
                'markdown' => $result['markdown'], 'title' => $result['title'], 'input' => $plan['input'], 'voices' => $result['voices'],
                'counts' => $result['counts'], 'decision' => $result['decision'], 'rounds' => $plan['rounds'], 'seed' => $plan['seed'],
                'credits' => $used, 'answered' => $result['answered'], 'partial' => $result['partial'], 'cached' => $result['cached'],
                'clamped' => $plan['clamped'], 'version_id' => $version?->id, 'at' => now()->toDateTimeString(),
            ],
        ]);
        $experiments->convert($tool, $request);
        $events->record($request, 'panel.used', ['tool_id' => $tool->id, 'meta' => ['voices' => count($plan['voices']), 'rounds' => $plan['rounds'], 'credits' => $used, 'version_id' => $version?->id]]);
        $events->record($request, 'asset.saved', ['tool_id' => $tool->id, 'meta' => ['version' => $output->version, 'panel' => true]]);

        $status = 'اكتملت اللوحة وحُفظت كإصدار v'.$output->version.' ('.$used.' رصيد).';
        if ($result['partial']) {
            $status = 'توقّفت اللوحة قبل اكتمال كل الأصوات؛ حُفظ ما تحدّث منها كإصدار v'.$output->version.' وخُصم '.$used.' رصيد فقط.';
        }

        return redirect()->to(route('tools.panel', ['project' => $project->id]).'#panel-result')->with('status', $status);
    }
}
