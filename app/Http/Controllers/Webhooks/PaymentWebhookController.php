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
            'order_id' => ['required_if:type,payment.succeeded', 'string', 'max:255'],
            'payment_id' => ['required_if:type,payment.succeeded', 'string', 'max:255'],
            'price_id' => ['required_if:type,payment.succeeded', 'string', 'max:255'],
            'amount' => ['required_if:type,payment.succeeded', 'integer', 'min:1'],
            'currency' => ['required_if:type,payment.succeeded', 'string', 'size:3'],
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
                [$order, $plan] = $this->resolveEntitlement($workspace, $payload);
                $paymentInserted = DB::table('payments')->insertOrIgnore([
                    'workspace_id' => $workspace->id,
                    'order_id' => $order->id,
                    'plan_id' => $plan->id,
                    'provider' => 'configured',
                    'provider_payment_id' => $payload['payment_id'],
                    'provider_price_id' => $payload['price_id'],
                    'plan' => $plan->slug,
                    'amount' => $payload['amount'],
                    'currency' => strtoupper($payload['currency']),
                    'status' => 'succeeded',
                    'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    'paid_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($paymentInserted === 0) {
                    DB::table('webhook_events')->where('event_id', $payload['event_id'])->update(['processed_at' => now(), 'updated_at' => now()]);

                    return;
                }
                DB::table('orders')->where('id', $order->id)->update(['status' => 'paid', 'updated_at' => now()]);
                DB::table('subscriptions')->updateOrInsert(
                    ['workspace_id' => $workspace->id],
                    ['plan_id' => $plan->id, 'plan' => $plan->slug, 'status' => 'active', 'provider' => 'configured', 'provider_id' => $payload['payment_id'], 'updated_at' => now(), 'created_at' => now()]
                );
                if ((int) $plan->credits > 0) {
                    $ledger->record($workspace, 'payment:'.$payload['event_id'], 'grant', (int) $plan->credits, 'رصيد دفعة مؤكدة');
                }
            }
            DB::table('webhook_events')->where('event_id', $payload['event_id'])->update(['processed_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['received' => true]);
    }

    private function resolveEntitlement(Workspace $workspace, array $payload): array
    {
        $order = DB::table('orders')
            ->where('public_id', $payload['order_id'])
            ->where('workspace_id', $workspace->id)
            ->lockForUpdate()
            ->first();
        abort_unless($order && $order->status === 'pending', 422, 'الطلب غير صالح أو تمت تسويته مسبقاً.');
        $plan = DB::table('plans')->where('id', $order->plan_id)->where('is_active', true)->lockForUpdate()->first();
        abort_unless($plan, 422, 'الخطة غير متاحة.');

        if ($plan->provider_price_id !== $payload['price_id']
            || (int) $order->amount !== (int) $payload['amount']
            || (int) $plan->price !== (int) $payload['amount']
            || strtoupper((string) $order->currency) !== strtoupper($payload['currency'])
            || strtoupper((string) $plan->currency) !== strtoupper($payload['currency'])) {
            abort(422, 'قيمة الدفعة أو عملتها لا تطابق الطلب والخطة المسجلين.');
        }

        return [$order, $plan];
    }
}
