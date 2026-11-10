<?php

namespace App\Domain\Lab\Ai;

use App\Domain\Workspace\Models\Workspace;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes the AI usage trail and enforces the monthly workspace quota on top of it.
 *
 * The quota is checked BEFORE credits are reserved, so a run refused for allowance never touches the ledger.
 */
class UsageRecorder
{
    public function record(AiContext $context, string $provider, string $model, int $inputTokens, int $outputTokens, bool $cached = false): void
    {
        DB::table('ai_usages')->insertOrIgnore([
            'workspace_id' => $context->workspace?->id,
            'user_id' => $context->userId,
            'run_id' => $context->runId,
            'operation_key' => ($context->operationKey ?? (string) Str::uuid()).($cached ? ':cached' : ':'.$provider),
            'provider' => $provider,
            'model' => $model,
            'capability' => 'text',
            'purpose' => Str::limit($context->purpose, 120, ''),
            'input_tokens' => max(0, $inputTokens),
            'output_tokens' => max(0, $outputTokens),
            'cost_micro_usd' => 0,
            'cached' => $cached,
            'sensitive' => $context->sensitive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{requests: int, tokens: int} since the first of the current month */
    public function monthlyTotals(Workspace $workspace): array
    {
        $row = DB::table('ai_usages')
            ->where('workspace_id', $workspace->id)
            ->where('cached', false)
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('COUNT(*) as requests, COALESCE(SUM(input_tokens + output_tokens), 0) as tokens')
            ->first();

        return ['requests' => (int) ($row->requests ?? 0), 'tokens' => (int) ($row->tokens ?? 0)];
    }

    /**
     * @param  int  $planned  how many calls this run intends to make — a panel asks for its whole size, so a run that
     *                        cannot fit in what is left of the month is refused whole instead of stopping half-spoken.
     *
     * @throws DomainException when this workspace has spent its month
     */
    public function assertWithinQuota(?Workspace $workspace, int $planned = 1): void
    {
        if (! $workspace) {
            return;
        }

        $requestLimit = (int) config('ai.quota.workspace_monthly_requests', 0);
        $tokenLimit = (int) config('ai.quota.workspace_monthly_tokens', 0);
        if ($requestLimit < 1 && $tokenLimit < 1) {
            return;
        }

        $planned = max(1, $planned);
        $totals = $this->monthlyTotals($workspace);
        if ($requestLimit > 0 && $totals['requests'] + $planned > $requestLimit) {
            // With planned = 1 this is the old «already at the limit» check, unchanged.
            throw new DomainException($planned > 1
                ? 'ما بقي من حصة الذكاء الاصطناعي لهذا الشهر لا يكفي تشغيلاً بهذا الحجم. قلّل عدد الشخصيات أو الجولات، أو انتظر تجديد الحد مع بداية الشهر. لم يُخصم أي رصيد.'
                : 'بلغت مساحة العمل حدّ تشغيلات الذكاء الاصطناعي لهذا الشهر. لم يُخصم أي رصيد، والحد يتجدد مع بداية الشهر.');
        }
        if ($tokenLimit > 0 && $totals['tokens'] >= $tokenLimit) {
            throw new DomainException('بلغت مساحة العمل حدّ المعالجة لهذا الشهر. لم يُخصم أي رصيد، والحد يتجدد مع بداية الشهر.');
        }
    }
}
