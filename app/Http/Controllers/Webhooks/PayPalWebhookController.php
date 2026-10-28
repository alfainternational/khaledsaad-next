<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Commerce\Services\FulfillOrder;
use App\Domain\Commerce\Services\PayPalClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Receives PayPal webhook events. Signature is verified against PayPal's API; every verified
 * event is persisted once (event id is unique) and answered with 200 so PayPal does not retry.
 */
class PayPalWebhookController extends Controller
{
    public function __invoke(Request $request, PayPalClient $paypal, FulfillOrder $fulfill): JsonResponse
    {
        abort_unless($paypal->isConfigured(), 503, 'PayPal غير مهيأ.');

        $raw = $request->getContent();
        $headers = [];
        foreach (['paypal-auth-algo', 'paypal-cert-url', 'paypal-transmission-id', 'paypal-transmission-sig', 'paypal-transmission-time'] as $name) {
            $headers[$name] = $request->header($name);
        }
        abort_unless($paypal->verifyWebhook($headers, $raw), 401, 'توقيع PayPal غير صالح.');

        $event = json_decode($raw, true);
        abort_unless(is_array($event) && filled($event['id'] ?? null) && filled($event['event_type'] ?? null), 400);

        $inserted = DB::table('webhook_events')->insertOrIgnore([
            'provider' => 'paypal', 'event_id' => $event['id'], 'type' => $event['event_type'],
            'payload' => json_encode($event, JSON_UNESCAPED_UNICODE), 'processed_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($inserted === 0) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        $note = null;
        try {
            $note = $this->process($event, $paypal, $fulfill);
        } catch (InvalidArgumentException|RequestException $e) {
            $note = $e->getMessage();
            Log::warning('paypal.webhook.rejected', ['event' => $event['id'], 'type' => $event['event_type'], 'reason' => $note]);
        }

        DB::table('webhook_events')->where('event_id', $event['id'])->update([
            'processed_at' => now(),
            'payload' => json_encode($note ? ['_note' => $note] + $event : $event, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        return response()->json(['received' => true]);
    }

    /** Returns a note for events that were acknowledged but not fulfilled; null when fulfilled or irrelevant. */
    private function process(array $event, PayPalClient $paypal, FulfillOrder $fulfill): ?string
    {
        $resource = $event['resource'] ?? [];

        return match ($event['event_type']) {
            // Capture confirmed (normal path, also covers captures done on the return URL).
            'PAYMENT.CAPTURE.COMPLETED' => $this->fulfillCapture($resource, $event, $fulfill),
            // Buyer approved but never came back to the site: capture server-side.
            'CHECKOUT.ORDER.APPROVED' => $this->captureApproved($resource, $event, $paypal, $fulfill),
            default => null,
        };
    }

    private function fulfillCapture(array $resource, array $event, FulfillOrder $fulfill): ?string
    {
        if (($resource['status'] ?? null) !== 'COMPLETED') {
            return 'capture status is not COMPLETED';
        }
        $orderPublicId = (string) ($resource['custom_id'] ?? '');
        $paypalOrderId = (string) ($resource['supplementary_data']['related_ids']['order_id'] ?? '');
        if ($orderPublicId === '' || $paypalOrderId === '') {
            return 'capture is missing custom_id or related order id';
        }

        $capture = [
            'id' => (string) $resource['id'],
            'amount_minor' => PayPalClient::toMinor((string) ($resource['amount']['value'] ?? '0')),
            'currency' => strtoupper((string) ($resource['amount']['currency_code'] ?? '')),
        ];
        $fulfill->handle($orderPublicId, $paypalOrderId, $capture, $event, 'webhook');

        return null;
    }

    private function captureApproved(array $resource, array $event, PayPalClient $paypal, FulfillOrder $fulfill): ?string
    {
        $paypalOrderId = (string) ($resource['id'] ?? '');
        if ($paypalOrderId === '') {
            return 'approved order is missing id';
        }
        $order = DB::table('orders')->where('provider_checkout_id', $paypalOrderId)->first();
        if (! $order || $order->status === 'paid') {
            return $order ? null : 'approved order is not known locally';
        }

        $captured = $paypal->captureOrder($paypalOrderId);
        $capture = PayPalClient::completedCapture($captured);
        if (! $capture) {
            return 'capture did not complete';
        }
        $fulfill->handle($order->public_id, $paypalOrderId, $capture, $captured, 'webhook-capture');

        return null;
    }
}
