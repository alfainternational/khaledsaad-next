<?php

namespace Tests\Feature;

use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Workspace\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use App\Support\Web\UrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * اقرأ موقعك (spec 2026-11-09 §5، الموجة 1). The feature's promise is narrow and worth pinning: it reads a page it is
 * allowed to read, it proposes rather than writes, it leaves fields empty instead of inventing them, and it charges
 * only when a model actually took part.
 */
class BrandProfileReaderTest extends TestCase
{
    use RefreshDatabase;

    private Tool $tool;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        UrlGuard::resolveUsing(fn (string $host) => $host === 'intranet.example.com' ? ['10.0.0.5'] : ['93.184.216.34']);
        $this->tool = Tool::query()->where('slug', 'brand-profile')->firstOrFail();
        $this->user = User::factory()->create(['audience' => 'owner']);
        $workspace = Workspace::query()->create(['name' => 'مساحة', 'slug' => 'reader-space']);
        $workspace->members()->attach($this->user, ['role' => 'owner']);
        $this->project = $workspace->projects()->create(['name' => 'متجر نَدى', 'slug' => 'reader-project', 'profile' => ['offer' => 'كلامي أنا']]);
        app(CreditLedger::class)->record($workspace, 'grant:'.$this->user->id, 'grant', 20, 'رصيد');
        config(['services.openai.key' => null, 'ai.chain' => ['cloudflare'], 'ai.providers.cloudflare.key' => null]);
    }

    private static function page(): string
    {
        return "Title: متجر نَدى | عناية طبيعية\n\n# متجر نَدى\n\n".
            "نصنع سيروم عناية طبيعياً بلا عطور للبشرة الحساسة، ونشحنه داخل السعودية خلال يومين، وكل تركيبة مختبَرة جلدياً قبل طرحها.\n\n".
            "## منتجاتنا\n## الشحن والإرجاع\n\nالسعر 189 ريال، والإرجاع خلال 14 يوماً. تواصل معنا على wa.me/9665000000\n";
    }

    private static function fakes(): array
    {
        return [
            '*robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200),
            'r.jina.ai/*' => Http::response(self::page(), 200),
        ];
    }

    public function test_the_page_explains_what_it_refuses_to_fetch(): void
    {
        Http::fake();
        $this->get('/tools/reader')->assertOk()
            ->assertSee($this->tool->name)
            ->assertSee('id="reader-form"', false)
            ->assertSee('اقرأ موقعك (بحساب)')
            ->assertSee('العناوين الداخلية والشبكات الخاصة');
        $this->get('/tools/brand-profile')->assertRedirect(route('tools.reader'));
        $this->get('/tools')->assertOk()->assertSee(route('tools.reader'), false)->assertSee('يقترح ولا يكتب');
        Http::assertNothingSent();
    }

    public function test_it_extracts_a_profile_from_the_page_without_any_ai_and_charges_nothing(): void
    {
        Http::fake(self::fakes());

        $this->actingAs($this->user)
            ->post(route('app.tools.read', $this->tool), ['project_id' => $this->project->id, 'url' => 'https://example.com/about'])
            ->assertRedirect(route('tools.reader', ['project' => $this->project->id]).'#reader-result')
            ->assertSessionHasNoErrors();

        $result = session('lab.reader.brand-profile');
        $this->assertFalse($result['used_ai']);
        $this->assertSame(0, $result['credits']);
        $this->assertSame('متجر نَدى', $result['proposal']['brand']);
        $this->assertStringContainsString('سيروم', $result['proposal']['offer']);
        $this->assertSame('beauty', $result['proposal']['sector'], 'the sector comes from words the page itself uses');
        $this->assertSame('whatsapp', $result['proposal']['channel']);
        $this->assertStringContainsString('189 ريال', $result['proposal']['proof']);

        // Nothing invented: the page never names an audience, so the field stays empty.
        $this->assertSame('', $result['proposal']['audience']);

        $this->assertSame(20, (int) DB::table('credit_entries')->sum('amount'), 'a base extraction is free');
    }

    public function test_the_asset_quotes_the_page_back_so_the_member_can_check_it(): void
    {
        Http::fake(self::fakes());

        $this->actingAs($this->user)->post(route('app.tools.read', $this->tool), [
            'project_id' => $this->project->id, 'url' => 'https://example.com/about',
        ])->assertRedirect();

        $output = ToolOutput::query()->where('output_type', 'brand_profile')->sole();
        $this->assertStringContainsString('ما قالته الصفحة نفسها', $output->body_markdown);
        $this->assertStringContainsString('متجر نَدى | عناية طبيعية', $output->body_markdown);
        $this->assertStringContainsString('189 ريال', $output->body_markdown);
        $this->assertStringContainsString('استخلاص من صفحة واحدة', $output->body_markdown);
        $this->assertStringNotContainsString('{{', $output->body_markdown);
        $this->assertSame('reader', $output->payload['source']);
        $this->assertSame('example.com', $output->payload['host']);
    }

    public function test_reading_proposes_and_never_writes_the_project_profile_by_itself(): void
    {
        Http::fake(self::fakes());

        $this->actingAs($this->user)->post(route('app.tools.read', $this->tool), [
            'project_id' => $this->project->id, 'url' => 'https://example.com/about',
        ])->assertRedirect();

        $this->assertSame(['offer' => 'كلامي أنا'], $this->project->fresh()->profile, 'the profile is untouched until the member applies');
    }

    public function test_applying_writes_only_the_ticked_fields(): void
    {
        $this->actingAs($this->user)->post(route('app.tools.read.apply', [$this->tool, $this->project]), [
            'fields' => ['offer', 'sector'],
            'offer' => 'سيروم طبيعي بلا عطور',
            'audience' => 'نساء 25–40',
            'sector' => 'beauty',
            'channel' => 'whatsapp',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $profile = $this->project->fresh()->profile;
        $this->assertSame('سيروم طبيعي بلا عطور', $profile['offer']);
        $this->assertSame('beauty', $profile['sector']);
        $this->assertArrayNotHasKey('audience', $profile, 'an unticked field is not written');
        $this->assertArrayNotHasKey('channel', $profile);
    }

    public function test_applying_nothing_useful_is_refused_rather_than_silently_doing_nothing(): void
    {
        $this->actingAs($this->user)->post(route('app.tools.read.apply', [$this->tool, $this->project]), [
            'fields' => ['audience'], 'audience' => '',
        ])->assertRedirect()->assertSessionHasErrors('reader');

        $this->assertSame(['offer' => 'كلامي أنا'], $this->project->fresh()->profile);
    }

    public function test_an_internal_address_is_refused_and_nothing_is_fetched_or_charged(): void
    {
        Http::fake();

        $this->actingAs($this->user)->post(route('app.tools.read', $this->tool), [
            'project_id' => $this->project->id, 'url' => 'http://intranet.example.com/',
        ])->assertRedirect()->assertSessionHasErrors('reader');

        Http::assertNothingSent();
        $this->assertSame(20, (int) DB::table('credit_entries')->sum('amount'));
        $this->assertSame(0, ToolOutput::query()->where('output_type', 'brand_profile')->count());
    }

    public function test_a_page_with_almost_no_text_is_refused_with_a_useful_reason(): void
    {
        Http::fake([
            '*robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200),
            'r.jina.ai/*' => Http::response("# مرحباً\n", 200),
        ]);

        $this->actingAs($this->user)->post(route('app.tools.read', $this->tool), [
            'project_id' => $this->project->id, 'url' => 'https://example.com/empty',
        ])->assertRedirect()->assertSessionHasErrors(['reader' => 'الصفحة تكاد تكون بلا نصّ مقروء (قد تكون مبنية بالكامل بجافاسكربت). جرّب صفحة «من نحن» أو صفحة منتج.']);
    }

    public function test_a_reader_cannot_run_it_in_a_workspace_they_only_read(): void
    {
        Http::fake();
        $viewer = User::factory()->create(['audience' => 'owner']);
        $this->project->workspace->members()->attach($viewer, ['role' => 'viewer']);

        $this->actingAs($viewer)->post(route('app.tools.read', $this->tool), [
            'project_id' => $this->project->id, 'url' => 'https://example.com/about',
        ])->assertForbidden();

        $this->actingAs($viewer)->post(route('app.tools.read.apply', [$this->tool, $this->project]), [
            'fields' => ['offer'], 'offer' => 'محاولة',
        ])->assertForbidden();

        Http::assertNothingSent();
    }

    protected function tearDown(): void
    {
        UrlGuard::resolveUsing(null);
        Cache::flush();
        parent::tearDown();
    }
}
