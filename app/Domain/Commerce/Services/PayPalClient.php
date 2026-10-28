<?php

namespace App\Domain\Commerce\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client over PayPal REST (Orders v2 + webhook signature verification).
 * Amounts are handled in minor units (cents) at the boundary and sent as decimal strings.
 */
class PayPalClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.paypal.client_id'))
            && filled(config('services.paypal.secret'))
            && filled(config('services.paypal.webhook_id'));
    }

    public function baseUrl(): string
    {
        $configured = (string) config('services.paypal.base_url');
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Create a CAPTURE order. Returns ['id' => paypalOrderId, 'approve_url' => string].
     */
    public function createOrder(string $publicId, int $amountMinor, string $currency, string $description, string $returnUrl, string $cancelUrl): array
    {
        $response = $this->authorized()
            ->withHeaders(['PayPal-Request-Id' => 'order-'.$publicId])
            ->post($this->baseUrl().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $publicId,
                    'custom_id' => $publicId,
                    'description' => mb_substr($description, 0, 127),
                    'amount' => ['currency_code' => strtoupper($currency), 'value' => self::toDecimal($amountMinor)],
                ]],
                'payment_source' => ['paypal' => ['experience_context' => [
                    'user_action' => 'PAY_NOW',
                    'shipping_preference' => 'NO_SHIPPING',
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                ]]],
            ])
            ->throw()
            ->json();

        $approve = collect($response['links'] ?? [])->first(fn ($link) => in_array($link['rel'] ?? '', ['payer-action', 'approve'], true));
        if (empty($response['id']) || empty($approve['href'])) {
            throw new RuntimeException('PayPal order response is missing the approval link.');
        }

        return ['id' => (string) $response['id'], 'approve_url' => (string) $approve['href']];
    }

    /**
     * Capture an approved order. Returns the raw PayPal order resource.
     *
     * @throws RequestException on non-2xx responses (e.g. ORDER_ALREADY_CAPTURED, ORDER_NOT_APPROVED)
     */
    public function captureOrder(string $paypalOrderId): array
    {
        return $this->authorized()
            ->withHeaders(['PayPal-Request-Id' => 'capture-'.$paypalOrderId])
            ->post($this->baseUrl().'/v2/checkout/orders/'.rawurlencode($paypalOrderId).'/capture', (object) [])
            ->throw()
            ->json();
    }

    /**
     * Extract the completed capture from an order resource: ['id', 'amount_minor', 'currency', 'custom_id'] or null.
     */
    public static function completedCapture(array $order): ?array
    {
        foreach ($order['purchase_units'] ?? [] as $unit) {
            foreach ($unit['payments']['captures'] ?? [] as $capture) {
                if (($capture['status'] ?? null) === 'COMPLETED') {
                    return [
                        'id' => (string) $capture['id'],
                        'amount_minor' => self::toMinor((string) ($capture['amount']['value'] ?? '0')),
                        'currency' => strtoupper((string) ($capture['amount']['currency_code'] ?? '')),
                        'custom_id' => (string) ($capture['custom_id'] ?? $unit['custom_id'] ?? $unit['reference_id'] ?? ''),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Verify a webhook delivery against PayPal (no local secret: PayPal signs with its own certificate).
     */
    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        $event = json_decode($rawBody, true);
        if (! is_array($event)) {
            return false;
        }

        $required = ['paypal-auth-algo', 'paypal-cert-url', 'paypal-transmission-id', 'paypal-transmission-sig', 'paypal-transmission-time'];
        foreach ($required as $name) {
            if (blank($headers[$name] ?? null)) {
                return false;
            }
        }

        $response = $this->authorized()->post($this->baseUrl().'/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $headers['paypal-auth-algo'],
            'cert_url' => $headers['paypal-cert-url'],
            'transmission_id' => $headers['paypal-transmission-id'],
            'transmission_sig' => $headers['paypal-transmission-sig'],
            'transmission_time' => $headers['paypal-transmission-time'],
            'webhook_id' => (string) config('services.paypal.webhook_id'),
            'webhook_event' => $event,
        ]);

        return $response->successful() && ($response->json('verification_status') === 'SUCCESS');
    }

    public static function toDecimal(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2, '.', '');
    }

    public static function toMinor(string $decimal): int
    {
        return (int) round(((float) $decimal) * 100);
    }

    private function authorized(): PendingRequest
    {
        return Http::acceptJson()->withToken($this->accessToken())->timeout(20);
    }

    private function accessToken(): string
    {
        $cacheKey = 'paypal:access_token:'.md5((string) config('services.paypal.client_id').$this->baseUrl());

        return Cache::remember($cacheKey, now()->addMinutes(50), function (): string {
            $response = Http::asForm()
                ->withBasicAuth((string) config('services.paypal.client_id'), (string) config('services.paypal.secret'))
                ->timeout(20)
                ->post($this->baseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
                ->throw()
                ->json();

            if (empty($response['access_token'])) {
                throw new RuntimeException('PayPal did not return an access token.');
            }

            return (string) $response['access_token'];
        });
    }
}
