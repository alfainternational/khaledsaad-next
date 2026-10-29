<?php

namespace Tests\Feature;

use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPalCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://paypal.test';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.paypal', [
            'client_id' => 'client-id', 'secret' => 'client-secret', 'mode' => 'sandbox', 'webhook_id' => 'WH-TEST', 'base_url' => self::API,
        ]);
    }

    /** @return array{0:User,1:Workspace,2:int} */
    private function ownerWithPlan(string $slug = 'owner-space'): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'مساحة '.$slug, 'slug' => $slug]);
        $workspace->members()->attach($user, ['role' => 'owner']);
        $planId = DB::table('plans')->insertGetId([
            'slug' => 'pro', 'name' => 'الخطة الاحترافية', 'provider_price_id' => null, 'price' => 1300, 'currency' => 'USD',
            'credits' => 100, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$user, $workspace, $planId];
    }

    private function pendingOrder(Workspace $workspace, int $planId, string $publicId, string $paypalId): void
    {
        DB::table('orders')->insert([
            'public_id' => $publicId, 'workspace_id' => $workspace->id, 'plan_id' => $planId, 'amount' => 1300, 'currency' => 'USD',
            'status' => 'pending', 'provider_checkout_id' => $paypalId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function captureResource(string $captureId, string $orderPublicId, string $amount = '13.00'): array
    {
        return [
            'id' => $captureId, 'status' => 'COMPLETED', 'custom_id' => $orderPublicId,
            'amount' => ['value' => $amount, 'currency_code' => 'USD'],
        ];
    }

    private function webhookHeaders(): array
    {
        return [
            'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA', 'HTTP_PAYPAL_CERT_URL' => 'https://api.paypal.test/cert.pem',
            'HTTP_PAYPAL_TRANSMISSION_ID' => 'tx-1', 'HTTP_PAYPAL_TRANSMISSION_SIG' => 'sig', 'HTTP_PAYPAL_TRANSMISSION_TIME' => '2026-10-28T10:00:00Z',
            'CONTENT_TYPE' => 'application/json',
        ];
    }

    public function test_owner_is_sent_to_paypal_approval_and_pending_order_is_reused(): void
    {
        [$user, $workspace, $planId] = $this->ownerWithPlan();
        Http::fake([
            self::API.'/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            self::API.'/v2/checkout/orders' => Http::response([
                'id' => 'PP-1', 'status' => 'PAYER_ACTION_REQUIRED',
                'links' => [['rel' => 'self', 'href' => self::API.'/v2/checkout/orders/PP-1'], ['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PP-1']],
            ]),
        ]);

        $this->actingAs($user)->post('/app/billing/orders', ['workspace_id' => $workspace->id, 'plan_id' => $planId])
            ->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=PP-1');

        $this->assertDatabaseHas('orders', ['workspace_id' => $workspace->id, 'plan_id' => $planId, 'status' => 'pending', 'provider_checkout_id' => 'PP-1', 'amount' => 1300, 'currency' => 'USD']);
        Http::assertSent(fn (ClientRequest $request) => $request->url() === self::API.'/v2/checkout/orders'
            && $request['intent'] === 'CAPTURE'
            && $request['purchase_units'][0]['amount']['value'] === '13.00'
            && $request['purchase_units'][0]['amount']['currency_code'] === 'USD'
            && $request['purchase_units'][0]['custom_id'] === DB::table('orders')->value('public_id'));

        $this->actingAs($user)->post('/app/billing/orders', ['workspace_id' => $workspace->id, 'plan_id' => $planId])->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_member_without_owner_role_cannot_start_checkout(): void
    {
        [, $workspace, $planId] = $this->ownerWithPlan();
        $member = User::factory()->create();
        $workspace->members()->attach($member, ['role' => 'member']);

        $this->actingAs($member)->post('/app/billing/orders', ['workspace_id' => $workspace->id, 'plan_id' => $planId])->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_is_disabled_when_paypal_is_not_configured(): void
    {
        [$user, $workspace, $planId] = $this->ownerWithPlan();
        config()->set('services.paypal.client_id', null);

        $this->actingAs($user)->from('/app/billing')->post('/app/billing/orders', ['workspace_id' => $workspace->id, 'plan_id' => $planId])
            ->assertRedirect('/app/billing')->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('orders', 0);
        $this->actingAs($user)->get('/app/billing')->assertOk()->assertSee('غير مفعّل');
    }

    public function test_return_captures_the_order_and_activates_the_subscription_once(): void
    {
        [$user, $workspace, $planId] = $this->ownerWithPlan();
        $this->pendingOrder($workspace, $planId, 'order-2', 'PP-2');
        Http::fake([
            self::API.'/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            self::API.'/v2/checkout/orders/PP-2/capture' => Http::response([
                'id' => 'PP-2', 'status' => 'COMPLETED',
                'purchase_units' => [['reference_id' => 'order-2', 'payments' => ['captures' => [$this->captureResource('CAP-2', 'order-2')]]]],
            ]),
        ]);

        $this->actingAs($user)->get('/app/billing/paypal/return?token=PP-2')->assertRedirect('/app/billing')->assertSessionHas('status');

        $this->assertDatabaseHas('payments', ['provider_payment_id' => 'CAP-2', 'provider' => 'paypal', 'amount' => 1300, 'currency' => 'USD', 'status' => 'succeeded']);
        $this->assertDatabaseHas('orders', ['public_id' => 'order-2', 'status' => 'paid']);
        $this->assertDatabaseHas('subscriptions', ['workspace_id' => $workspace->id, 'plan' => 'pro', 'status' => 'active', 'provider' => 'paypal']);
        $this->assertSame(100, app(CreditLedger::class)->balance($workspace));

        $periodEnd = DB::table('subscriptions')->where('workspace_id', $workspace->id)->value('ends_at');
        $this->assertSame(now()->addMonthNoOverflow()->toDateString(), substr($periodEnd, 0, 10));
        $this->actingAs($user)->get('/app/billing/paypal/return?token=PP-2')->assertRedirect('/app/billing');
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(100, app(CreditLedger::class)->balance($workspace));
        $this->assertSame($periodEnd, DB::table('subscriptions')->where('workspace_id', $workspace->id)->value('ends_at'));
    }

    public function test_return_rejects_a_capture_whose_amount_does_not_match_the_order(): void
    {
        [$user, $workspace, $planId] = $this->ownerWithPlan();
        $this->pendingOrder($workspace, $planId, 'order-3', 'PP-3');
        Http::fake([
            self::API.'/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            self::API.'/v2/checkout/orders/PP-3/capture' => Http::response([
                'id' => 'PP-3', 'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [$this->captureResource('CAP-3', 'order-3', '1.00')]]]],
            ]),
        ]);

        $this->actingAs($user)->get('/app/billing/paypal/return?token=PP-3')->assertRedirect('/app/billing')->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('orders', ['public_id' => 'order-3', 'status' => 'pending']);
        $this->assertSame(0, app(CreditLedger::class)->balance($workspace));
    }

    public function test_return_and_cancel_are_scoped_to_the_users_own_workspaces(): void
    {
        [, $workspace, $planId] = $this->ownerWithPlan();
        $this->pendingOrder($workspace, $planId, 'order-4', 'PP-4');
        $stranger = User::factory()->create();
        Http::fake();

        $this->actingAs($stranger)->get('/app/billing/paypal/return?token=PP-4')->assertNotFound();
        $this->actingAs($stranger)->get('/app/billing/paypal/cancel?token=PP-4')->assertNotFound();
        Http::assertNothingSent();
        $this->assertDatabaseHas('orders', ['public_id' => 'order-4', 'status' => 'pending']);
    }

    public function test_cancel_marks_a_pending_order_cancelled(): void
    {
        [$user, $workspace, $planId] = $this->ownerWithPlan();
        $this->pendingOrder($workspace, $planId, 'order-5', 'PP-5');

        $this->actingAs($user)->get('/app/billing/paypal/cancel?token=PP-5')->assertRedirect('/app/billing');
        $this->assertDatabaseHas('orders', ['public_id' => 'order-5', 'status' => 'cancelled']);
    }

    public function test_verified_capture_webhook_fulfills_once_and_ignores_duplicates(): void
    {
        [, $workspace, $planId] = $this->ownerWithPlan();
        $this->pendingOrder($workspace, $planId, 'order-9', 'PP-9');
        Http::fake([
            self::API.'/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            self::API.'/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        ]);
        $event = [
            'id' => 'WH-EVT-9', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => $this->captureResource('CAP-9', 'order-9') + ['supplementary_data' => ['related_ids' => ['order_id' => 'PP-9']]],
        ];
        $body = json_encode($event);

        $this->call('POST', '/webhooks/paypal', [], [], [], $this->webhookHeaders(), $body)->assertOk();
        $this->call('POST', '/webhooks/paypal', [], [], [], $this->webhookHeaders(), $body)->assertOk()->assertJson(['duplicate' => true]);

        $this->assertDatabaseCount('webhook_events', 1);
        $this->assertDatabaseHas('webhook_events', ['provider' => 'paypal', 'event_id' => 'WH-EVT-9']);
        $this->assertDatabaseHas('payments', ['provider_payment_id' => 'CAP-9', 'amount' => 1300]);
        $this->assertDatabaseHas('orders', ['public_id' => 'order-9', 'status' => 'paid']);
        $this->assertSame(100, app(CreditLedger::class)->balance($workspace));
        Http::assertSent(fn (ClientRequest $request) => str_ends_with($request->url(), '/verify-webhook-signature') && $request['webhook_id'] === 'WH-TEST' && $request['transmission_id'] === 'tx-1');
    }

    public function test_webhook_with_invalid_signature_is_rejected_and_not_stored(): void
    {
        Http::fake([
            self::API.'/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            self::API.'/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'FAILURE']),
        ]);
        $body = json_encode(['id' => 'WH-EVT-BAD', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => []]);

        $this->call('POST', '/webhooks/paypal', [], [], [], $this->webhookHeaders(), $body)->assertUnauthorized();
        $this->call('POST', '/webhooks/paypal', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertUnauthorized();
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_webhook_with_mismatched_amount_is_acknowledged_but_not_fulfilled(): void
    {
        [, $workspace, $planId] = $this->ownerWithPlan();
        $this->pendingOrder($workspace, $planId, 'order-10', 'PP-10');
        Http::fake([
            self::API.'/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            self::API.'/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        ]);
        $event = [
            'id' => 'WH-EVT-10', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => $this->captureResource('CAP-10', 'order-10', '1.00') + ['supplementary_data' => ['related_ids' => ['order_id' => 'PP-10']]],
        ];

        $this->call('POST', '/webhooks/paypal', [], [], [], $this->webhookHeaders(), json_encode($event))->assertOk();

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(0, app(CreditLedger::class)->balance($workspace));
        $stored = DB::table('webhook_events')->where('event_id', 'WH-EVT-10')->first();
        $this->assertNotNull($stored->processed_at);
        $this->assertStringContainsString('_note', $stored->payload);
    }

    public function test_approved_webhook_captures_server_side_when_buyer_never_returned(): void
    {
        [, $workspace, $planId] = $this->ownerWithPlan();
        $this->pendingOrder($workspace, $planId, 'order-11', 'PP-11');
        Http::fake([
            self::API.'/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            self::API.'/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
            self::API.'/v2/checkout/orders/PP-11/capture' => Http::response([
                'id' => 'PP-11', 'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [$this->captureResource('CAP-11', 'order-11')]]]],
            ]),
        ]);
        $event = ['id' => 'WH-EVT-11', 'event_type' => 'CHECKOUT.ORDER.APPROVED', 'resource' => ['id' => 'PP-11', 'status' => 'APPROVED']];

        $this->call('POST', '/webhooks/paypal', [], [], [], $this->webhookHeaders(), json_encode($event))->assertOk();

        $this->assertDatabaseHas('payments', ['provider_payment_id' => 'CAP-11', 'amount' => 1300]);
        $this->assertDatabaseHas('orders', ['public_id' => 'order-11', 'status' => 'paid']);
        $this->assertSame(100, app(CreditLedger::class)->balance($workspace));
    }
}
