<?php

namespace Tests\Feature;

use App\Domain\Lab\Ai\AiContext;
use App\Domain\Lab\Services\AiGateway;
use App\Domain\Workspace\Models\Workspace;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The free-tier chain (plan 2026-11-09 §ب): stacked free allowances must behave as one dependable provider.
 * Every test here fixes one promise the zero-cost stack makes — fall through, park a spent tier, never leak
 * a member's data to a vendor that trains on it, count usage once, and stop a workspace at its month.
 */
class AiProviderChainTest extends TestCase
{
    use RefreshDatabase;

    /** Two free providers configured, Gemini first. */
    private function configureFreeChain(): void
    {
        config([
            'services.openai.key' => null,
            'ai.chain' => ['gemini', 'cloudflare'],
            'ai.providers.gemini.key' => 'gemini-test-key',
            'ai.providers.cloudflare.key' => 'cf-test-token',
            'ai.providers.cloudflare.account' => 'cf-account',
        ]);
    }

    private static function geminiOk(string $text, int $in = 11, int $out = 7): mixed
    {
        return Http::response([
            'candidates' => [['content' => ['parts' => [['text' => $text]]]]],
            'usageMetadata' => ['promptTokenCount' => $in, 'candidatesTokenCount' => $out],
        ]);
    }

    private static function cloudflareOk(string $text, int $in = 5, int $out = 3): mixed
    {
        return Http::response([
            'success' => true,
            'result' => ['response' => $text, 'usage' => ['prompt_tokens' => $in, 'completion_tokens' => $out]],
        ]);
    }

    public function test_enabled_is_true_when_any_single_provider_holds_its_credentials(): void
    {
        config(['services.openai.key' => null, 'ai.chain' => ['gemini', 'openai'], 'ai.providers.gemini.key' => null]);
        $this->assertFalse(app(AiGateway::class)->enabled());

        config(['ai.providers.gemini.key' => 'k']);
        $this->assertTrue(app(AiGateway::class)->enabled());
    }

    public function test_chain_falls_through_to_the_next_provider_when_the_first_fails(): void
    {
        $this->configureFreeChain();
        Http::fake([
            '*generativelanguage*' => Http::response(['error' => ['message' => 'down']], 500),
            '*api.cloudflare.com*' => self::cloudflareOk('أجاب المزوّد الثاني'),
        ]);

        $result = app(AiGateway::class)->generate('حلّل العرض');

        $this->assertSame('أجاب المزوّد الثاني', $result['text']);
        $this->assertSame('cloudflare', $result['provider']);
        $this->assertSame(8, $result['tokens']);
    }

    public function test_a_rate_limited_provider_is_skipped_on_the_next_run_instead_of_being_retried(): void
    {
        $this->configureFreeChain();
        Http::fake([
            '*generativelanguage*' => Http::response(['error' => ['message' => 'quota']], 429),
            '*api.cloudflare.com*' => self::cloudflareOk('بديل'),
        ]);

        app(AiGateway::class)->generate('أول تشغيل');
        Http::assertSentCount(2);

        // The spent tier is parked, so the second run must not spend a request on it at all.
        app(AiGateway::class)->generate('ثاني تشغيل');
        Http::assertSentCount(3);
    }

    public function test_sensitive_run_skips_a_provider_that_trains_on_input(): void
    {
        $this->configureFreeChain();
        Http::fake([
            '*generativelanguage*' => self::geminiOk('لا يجب أن يُستدعى'),
            '*api.cloudflare.com*' => self::cloudflareOk('مزوّد لا يتدرّب'),
        ]);

        $result = app(AiGateway::class)->generate('أرقام العميل', null, new AiContext(purpose: 'test', sensitive: true));

        $this->assertSame('cloudflare', $result['provider']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'generativelanguage'));
    }

    public function test_sensitive_run_is_refused_rather_than_sent_to_the_only_training_provider(): void
    {
        config([
            'services.openai.key' => null,
            'ai.chain' => ['gemini'],
            'ai.providers.gemini.key' => 'gemini-test-key',
        ]);
        Http::fake(['*' => self::geminiOk('تسريب')]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('هذه الميزة تعالج بيانات مشروعك');

        try {
            app(AiGateway::class)->generate('أرقام العميل', null, new AiContext(purpose: 'test', sensitive: true));
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_usage_is_recorded_once_per_operation_key_even_when_the_run_is_repeated(): void
    {
        $this->configureFreeChain();
        Http::fake(['*generativelanguage*' => self::geminiOk('مرة واحدة')]);
        $workspace = Workspace::query()->create(['name' => 'مساحة', 'slug' => 'usage-space']);
        $context = new AiContext(workspace: $workspace, purpose: 'tool:test', operationKey: 'run:fixed:ai');

        app(AiGateway::class)->generate('نفس المدخل', null, $context);
        app(AiGateway::class)->generate('نفس المدخل', null, $context);

        $rows = DB::table('ai_usages')->where('workspace_id', $workspace->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('gemini', $rows->first()->provider);
        $this->assertSame(11, (int) $rows->first()->input_tokens);
    }

    public function test_monthly_request_quota_refuses_the_run_before_any_provider_is_called(): void
    {
        $this->configureFreeChain();
        config(['ai.quota.workspace_monthly_requests' => 1, 'ai.quota.workspace_monthly_tokens' => 0]);
        Http::fake(['*generativelanguage*' => self::geminiOk('أول')]);
        $workspace = Workspace::query()->create(['name' => 'مساحة', 'slug' => 'quota-space']);

        app(AiGateway::class)->generate('أول', null, new AiContext(workspace: $workspace, purpose: 'tool:test'));
        Http::assertSentCount(1);

        try {
            app(AiGateway::class)->generate('ثانٍ', null, new AiContext(workspace: $workspace, purpose: 'tool:test'));
            $this->fail('expected the monthly quota to refuse the second run');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('حدّ تشغيلات الذكاء الاصطناعي لهذا الشهر', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_cache_returns_the_stored_answer_without_spending_the_allowance(): void
    {
        $this->configureFreeChain();
        config(['ai.cache.ttl_minutes' => 60]);
        Http::fake(['*generativelanguage*' => self::geminiOk('جواب مخزّن')]);
        $workspace = Workspace::query()->create(['name' => 'مساحة', 'slug' => 'cache-space']);

        $first = app(AiGateway::class)->generate('سؤال', 'تعليمات', new AiContext(workspace: $workspace, purpose: 'tool:test'));
        $second = app(AiGateway::class)->generate('سؤال', 'تعليمات', new AiContext(workspace: $workspace, purpose: 'tool:test'));

        $this->assertFalse($first['cached']);
        $this->assertTrue($second['cached']);
        $this->assertSame('جواب مخزّن', $second['text']);
        Http::assertSentCount(1);
        // A cached answer costs no allowance, so it must stay out of the monthly totals.
        $this->assertSame(1, DB::table('ai_usages')->where('cached', false)->count());
    }

    public function test_openai_keeps_using_the_responses_api_so_the_paid_key_remains_a_config_switch(): void
    {
        config(['services.openai.key' => 'paid-key', 'ai.chain' => ['openai']]);
        Http::fake(['*/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'مدفوع']]]],
            'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
        ])]);

        $result = app(AiGateway::class)->generate('حلّل');

        $this->assertSame('مدفوع', $result['text']);
        $this->assertSame(30, $result['tokens']);
        $this->assertSame('openai', $result['provider']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/responses'));
    }
}
