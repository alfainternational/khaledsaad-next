<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Workspace\Models\Workspace;
use App\Events\SubscriptionActivated;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Settle a pending order from a confirmed PayPal capture.
 * Idempotent: the same capture (or an already-paid order) is a no-op and returns false.
 * Validates against the order snapshot, never the current plan price (plans may be edited later).
 */
class FulfillOrder
{
    public function __construct(private readonly CreditLedger $ledger) {}

    /**
     * @param  array{id:string,amount_minor:int,currency:string}  $capture
     * @param  array  $payload  raw provider payload stored for audit
     *
     * @throws InvalidArgumentException when the order is unknown or the capture does not match it
     */
    public function handle(string $orderPublicId, string $paypalOrderId, array $capture, array $payload, string $source): bool
    {
        $activated = null;
        $settled = DB::transaction(function () use ($orderPublicId, $paypalOrderId, $capture, $payload, $source, &$activated): bool {
            $order = DB::table('orders')->where('public_id', $orderPublicId)->lockForUpdate()->first();
            if (! $order) {
                throw new InvalidArgumentException('الطلب غير معروف.');
            }
            if ($order->status === 'paid') {
                return false;
            }
            if (! in_array($order->status, ['pending', 'approved'], true)) {
                throw new InvalidArgumentException('الطلب غير قابل للتسوية في حالته الحالية.');
            }
            if ($order->provider_checkout_id !== null && $order->provider_checkout_id !== $paypalOrderId) {
                throw new InvalidArgumentException('معرّف PayPal لا يطابق الطلب.');
            }
            if ((int) $order->amount !== (int) $capture['amount_minor'] || strtoupper((string) $order->currency) !== strtoupper($capture['currency'])) {
                throw new InvalidArgumentException('قيمة الدفعة أو عملتها لا تطابق الطلب المسجل.');
            }

            $plan = DB::table('plans')->where('id', $order->plan_id)->lockForUpdate()->first();
            if (! $plan) {
                throw new InvalidArgumentException('الخطة المرتبطة بالطلب غير موجودة.');
            }
            $workspace = Workspace::query()->lockForUpdate()->findOrFail($order->workspace_id);

            $inserted = DB::table('payments')->insertOrIgnore([
                'workspace_id' => $workspace->id,
                'order_id' => $order->id,
                'plan_id' => $plan->id,
                'provider' => 'paypal',
                'provider_payment_id' => $capture['id'],
                'provider_price_id' => $paypalOrderId,
                'plan' => $plan->slug,
                'amount' => (int) $capture['amount_minor'],
                'currency' => strtoupper($capture['currency']),
                'status' => 'succeeded',
                'payload' => json_encode(['source' => $source] + $payload, JSON_UNESCAPED_UNICODE),
                'paid_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($inserted === 0) {
                return false;
            }

            DB::table('orders')->where('id', $order->id)->update([
                'status' => 'paid', 'provider_checkout_id' => $paypalOrderId, 'updated_at' => now(),
            ]);
            $subscription = DB::table('subscriptions')->where('workspace_id', $workspace->id)->first();
            $periodStart = $subscription?->ends_at && Carbon::parse($subscription->ends_at)->isFuture()
                ? Carbon::parse($subscription->ends_at) : now();
            DB::table('subscriptions')->updateOrInsert(
                ['workspace_id' => $workspace->id],
                ['plan_id' => $plan->id, 'plan' => $plan->slug, 'status' => 'active', 'provider' => 'paypal', 'provider_id' => $capture['id'], 'ends_at' => $periodStart->copy()->addMonthNoOverflow(), 'updated_at' => now(), 'created_at' => $subscription?->created_at ?? now()]
            );
            if ((int) $plan->credits > 0) {
                $this->ledger->record($workspace, 'payment:paypal:'.$capture['id'], 'grant', (int) $plan->credits, 'رصيد دفعة مؤكدة عبر PayPal', ['order' => $orderPublicId]);
            }
            $activated = [$workspace, (string) $plan->slug];

            return true;
        });
        // After commit: earned-but-unissued certificates are released by the plan gate (blueprint §4.13).
        if ($settled && $activated !== null) {
            SubscriptionActivated::dispatch($activated[0], $activated[1]);
        }

        return $settled;
    }
}
