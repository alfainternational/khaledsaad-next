<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, CreditLedger $ledger): JsonResponse
    {
        $secret = (string) config('services.payments.webhook_secret');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless($secret !== '' && hash_equals($expected, (string) $request->header('X-Platform-Signature')), 401);

        $payload = $request->json()->all();
        validator($payload, [
            'event_id' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:100'],
            'workspace_id' => ['required', 'integer'],
        ])->validate();

        DB::transaction(function () use ($payload, $ledger): void {
            $inserted = DB::table('webhook_events')->insertOrIgnore([
                'provider' => 'configured', 'event_id' => $payload['event_id'], 'type' => $payload['type'],
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'processed_at' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($inserted === 0) {
                return;
            }

            $workspace = Workspace::query()->lockForUpdate()->findOrFail($payload['workspace_id']);
            if ($payload['type'] === 'payment.succeeded') {
                DB::table('subscriptions')->updateOrInsert(
                    ['workspace_id' => $workspace->id],
                    ['plan' => $payload['plan'] ?? 'pro', 'status' => 'active', 'provider' => 'configured', 'updated_at' => now(), 'created_at' => now()]
                );
                if ((int) ($payload['credits'] ?? 0) > 0) {
                    $ledger->record($workspace, 'payment:'.$payload['event_id'], 'grant', (int) $payload['credits'], 'رصيد دفعة مؤكدة');
                }
            }
            DB::table('webhook_events')->where('event_id', $payload['event_id'])->update(['processed_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['received' => true]);
    }
}
