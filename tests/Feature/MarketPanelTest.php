<?php

namespace Tests\Feature;

use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Lab\Services\SyntheticMarket;
use App\Domain\Workspace\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * السوق المحاكى (plan 2026-11-09 البند 3). Every test here holds one promise the feature makes: the ceiling is
 * arithmetic and not a prompt, the member pays for the voices that actually spoke, the count is computed and never
 * generated, and a panel carrying the member's own project data never reaches a vendor that trains on it.
 */
class MarketPanelTest extends TestCase
{
    use RefreshDatabase;

    private Tool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->tool = Tool::query()->where('slug', 'market-panel')->firstOrFail();
        // One free provider that does not train on input: a panel is sensitive, so this is the only kind it may use.
        config([
            'services.openai.key' => null,
            'ai.chain' => ['cloudflare'],
            'ai.providers.cloudflare.key' => 'cf-test-token',
            'ai.providers.cloudflare.account' => 'cf-account',
        ]);
    }

    /** A Workers AI answer carrying one voice's JSON. */
    private static function voice(string $verdict, string $reason, string $objection = '', string $unlock = ''): array
    {
        return [
            'success' => true,
            'result' => [
                'response' => json_encode(compact('verdict', 'reason', 'objection', 'unlock'), JSON_UNESCAPED_UNICODE),
                'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 60],
            ],
        ];
    }

    private static function raw(string $text): array
    {
        return ['success' => true, 'result' => ['response' => $text, 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5]]];
    }

    /**
     * The shape the live API actually returns for a voice: Workers AI hands back `result.response` ALREADY PARSED
     * when the model answers with valid JSON — which is what every voice is asked for (verified 2026-11-10).
     */
    private static function voiceParsed(string $verdict, string $reason, string $objection = '', string $unlock = ''): array
    {
        return [
            'success' => true,
            'result' => [
                'response' => compact('verdict', 'reason', 'objection', 'unlock'),
                'choices' => [['message' => ['content' => json_encode(compact('verdict', 'reason', 'objection', 'unlock'), JSON_UNESCAPED_UNICODE)]]],
                'usage' => ['prompt_tokens' => 265, 'completion_tokens' => 74],
            ],
        ];
    }

    public function test_a_voice_is_read_when_the_provider_returns_the_answer_already_parsed(): void
    {
        $user = $this->member();
        Http::fake(['*api.cloudflare.com*' => Http::sequence()
            ->push(self::voiceParsed('maybe', 'أريد دليلاً على بشرتي', 'لا أعرف إن كان مناسباً', 'عينة مجانية'))
            ->push(self::voiceParsed('no', 'السعر مرتفع', 'السعر'))
            ->push(['success' => true, 'result' => ['choices' => [['message' => ['content' => '{"verdict":"buy","reason":"واضح","objection":"","unlock":""}']]], 'usage' => ['prompt_tokens' => 9, 'completion_tokens' => 4]]])]);

        $this->actingAs($user)->post(route('app.tools.panel', $this->tool), $this->payload($user))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $counts = session('lab.panel.market-panel')['counts'];
        $this->assertSame(['buy' => 1, 'maybe' => 1, 'no' => 1, 'unclear' => 0], $counts, 'a parsed JSON answer must be read, not discarded as empty');
        $this->assertStringContainsString('عينة مجانية', session('lab.panel.market-panel')['markdown']);
        $this->assertSame(3, DB::table('ai_usages')->where('provider', 'cloudflare')->count());
        $this->assertSame(265, (int) DB::table('ai_usages')->orderBy('id')->value('input_tokens'), 'usage is read from the same payload');
    }

    private function member(int $credits = 40): User
    {
        $user = User::factory()->create(['audience' => 'owner']);
        $workspace = Workspace::query()->create(['name' => 'مساحة', 'slug' => 'space-'.$user->id]);
        $workspace->members()->attach($user, ['role' => 'owner']);
        $workspace->projects()->create(['name' => 'متجر نَدى', 'slug' => 'nada-'.$user->id, 'profile' => ['offer' => 'سيروم طبيعي', 'sector' => 'beauty']]);
        app(CreditLedger::class)->record($workspace, 'grant:'.$user->id, 'grant', $credits, 'رصيد');

        return $user;
    }

    private function balance(User $user): int
    {
        return (int) DB::table('credit_entries')->where('workspace_id', $user->workspaces()->first()->id)->sum('amount');
    }

    private function payload(User $user, array $override = []): array
    {
        return array_merge([
            'project_id' => Project::query()->where('workspace_id', $user->workspaces()->first()->id)->value('id'),
            'offer' => 'سيروم طبيعي بلا عطور',
            'sector' => 'beauty',
            'message' => 'بشرة هادئة بلا تهيّج خلال 7 أيام، وضمان استرجاع 14 يوماً.',
            'price' => '189 ريالاً',
            'rounds' => '1',
            'personas' => "نساء 25–40 ذوات بشرة حساسة\nأم عاملة تشتري بسرعة\nمشترٍ حساس للسعر",
        ], $override);
    }

    public function test_the_panel_page_states_the_method_its_ceiling_and_that_it_is_not_market_research(): void
    {
        $page = $this->get('/tools/panel')->assertOk()
            ->assertSee($this->tool->name)
            ->assertSee('محاكاة لا بحث سوق')
            ->assertSee('id="panel-form"', false)
            ->assertSee('شغّل اللوحة (بحساب)')
            ->assertSee('أقصى 5 أصوات و2 جولات لكل تشغيل.');
        // The ceiling is shown as a number, so a visitor can price the run before signing up.
        $page->assertSee('الحد الأقصى 10 رصيد للتشغيل');
        $this->get('/tools/market-panel')->assertRedirect(route('tools.panel'));
        $this->get('/tools')->assertOk()->assertSee(route('tools.panel'), false)->assertSee('محاكاة لا بحث سوق');
        Http::assertNothingSent();
    }

    public function test_plan_clamps_voices_and_rounds_and_prices_the_run_before_anything_is_spent(): void
    {
        Http::fake();
        $market = app(SyntheticMarket::class);
        $plan = $market->plan($this->tool, [
            'offer' => 'عرض', 'sector' => 'beauty', 'message' => 'رسالة', 'rounds' => '2',
            'personas' => "أولى\nثانية\nثالثة\nرابعة\nخامسة\nسادسة\nسابعة",
        ]);

        $this->assertCount(5, $plan['voices'], 'the sixth and seventh persona are dropped, not sent');
        $this->assertSame(2, $plan['rounds']);
        $this->assertSame(10, $plan['calls']);
        $this->assertSame(10, $plan['credits']);
        $this->assertNotEmpty($plan['clamped']);

        // Lowering the ceiling in config lowers the run, whatever the form submits.
        config(['ai.panel.max_voices' => 2, 'ai.panel.max_rounds' => 1]);
        $tightened = $market->plan($this->tool, [
            'offer' => 'عرض', 'sector' => 'beauty', 'message' => 'رسالة', 'rounds' => '2',
            'personas' => "أولى\nثانية\nثالثة",
        ]);
        $this->assertCount(2, $tightened['voices']);
        $this->assertSame(1, $tightened['rounds']);
        $this->assertSame(2, $tightened['credits']);
        Http::assertNothingSent();
    }

    public function test_a_run_asks_each_voice_alone_counts_the_verdicts_and_charges_per_answer(): void
    {
        $user = $this->member();
        Http::fake(['*api.cloudflare.com*' => Http::sequence()
            ->push(self::voice('buy', 'الوعد واضح والضمان يطمئنني'))
            ->push(self::voice('maybe', 'أحتاج دليلاً على نوع البشرة', 'لا أعرف إن كان مناسباً لبشرتي', 'صورة اختبار جلدي'))
            ->push(self::voice('no', 'السعر أعلى من الصيدلية', 'السعر'))]);

        $project = Project::query()->where('workspace_id', $user->workspaces()->first()->id)->firstOrFail();
        $this->actingAs($user)->post(route('app.tools.panel', $this->tool), $this->payload($user))
            ->assertRedirect(route('tools.panel', ['project' => $project->id]).'#panel-result');

        Http::assertSentCount(3);
        // Lock 2: every voice gets the panel's own small output budget, not the platform's 4000.
        Http::assertSent(fn ($request) => (int) $request->data()['max_tokens'] === 600);
        $session = session('lab.panel.market-panel');
        $this->assertSame(['buy' => 1, 'maybe' => 1, 'no' => 1, 'unclear' => 0], $session['counts']);
        $this->assertSame(3, $session['answered']);
        $this->assertFalse($session['partial']);
        $this->assertSame(37, $this->balance($user), 'three voices at one credit each');

        // The panel's own run row, not the bookkeeping row AssetVault writes for every saved asset.
        $run = DB::table('tool_runs')->where('tool_id', $this->tool->id)->where('input_payload', 'like', '%"panel"%')->sole();
        $this->assertSame('completed', $run->status);
        $this->assertSame(3, (int) $run->used_credits);
        $this->assertSame(0, (int) $run->reserved_credits);
        // The record must be reproducible even though the model is not: seed, voices and rounds are kept.
        $input = json_decode($run->input_payload, true);
        $this->assertSame($session['seed'], $input['panel']['seed']);
        $this->assertCount(3, $input['panel']['voices']);
        $this->assertSame(3, DB::table('ai_usages')->where('purpose', 'panel:market-panel')->count());

        // The result renders on the page the run redirects to: counts, the decision rule and the asset card.
        $this->actingAs($user)->get(route('tools.panel', ['project' => $project->id]))->assertOk()
            ->assertSee('id="panel-result"', false)
            ->assertSee('اللوحة منقسمة')
            ->assertSee('لم يتضح')
            ->assertSee('id="panel-asset"', false)
            ->assertSee('بذرة التشغيل')
            ->assertSee('3 إجابة · 3 رصيد');
    }

    public function test_the_second_round_puts_the_panel_objections_to_each_voice_and_reports_what_moved(): void
    {
        $user = $this->member();
        Http::fake(['*api.cloudflare.com*' => Http::sequence()
            ->push(self::voice('no', 'لا أثق بالادعاء', 'لا دليل على الاختبار الجلدي'))
            ->push(self::voice('buy', 'الضمان يكفيني'))
            ->push(self::voice('maybe', 'الاعتراض على الدليل منطقي، سأنتظر الصورة', 'لا دليل منشور'))
            ->push(self::voice('buy', 'ما زال الضمان يكفيني'))]);

        $this->actingAs($user)->post(route('app.tools.panel', $this->tool), $this->payload($user, [
            'rounds' => '2', 'personas' => "نساء 25–40 ذوات بشرة حساسة\nأم عاملة تشتري بسرعة",
        ]))->assertRedirect();

        Http::assertSentCount(4);
        // Round two must carry round one's objections, otherwise it is just a second first round.
        Http::assertSent(function ($request) {
            $prompt = (string) ($request->data()['messages'][1]['content'] ?? '');

            return str_contains($prompt, 'لا دليل على الاختبار الجلدي') && str_contains($prompt, 'ثم سمعت اعتراضات بقية اللوحة');
        });
        $session = session('lab.panel.market-panel');
        $this->assertSame(4, $session['answered']);
        $this->assertSame(['buy' => 1, 'maybe' => 1, 'no' => 0, 'unclear' => 0], $session['counts'], 'the final verdict of each voice is the second round');
        $this->assertStringContainsString('تحوّل من «لن يشتري» إلى «مهتم بشرط»', $session['markdown']);
        $this->assertSame(36, $this->balance($user));
    }

    public function test_a_voice_that_answers_outside_the_agreed_shape_is_counted_as_unclear_not_as_a_buyer(): void
    {
        $user = $this->member();
        Http::fake(['*api.cloudflare.com*' => Http::sequence()
            ->push(self::raw('أعتقد أن المنتج جيد جداً وسأشتريه بالتأكيد'))
            ->push(self::voice('buy', 'واضح'))
            ->push(self::raw('```json'."\n".'{"verdict":"غير معروف","reason":"ربما","objection":"","unlock":""}'."\n".'```'))]);

        $this->actingAs($user)->post(route('app.tools.panel', $this->tool), $this->payload($user))->assertRedirect();

        $counts = session('lab.panel.market-panel')['counts'];
        $this->assertSame(1, $counts['buy'], 'an unreadable answer must never be counted as a buyer');
        $this->assertSame(2, $counts['unclear']);
        $this->assertSame('لم يتضح', SyntheticMarket::VERDICTS['unclear']);
    }

    public function test_a_provider_that_dies_midway_keeps_the_voices_that_answered_and_charges_only_for_them(): void
    {
        $user = $this->member();
        Http::fake(['*api.cloudflare.com*' => Http::sequence()
            ->push(self::voice('buy', 'مقنع'))
            ->pushStatus(500)
            ->pushStatus(500)
            ->pushStatus(500)]);

        $this->actingAs($user)->post(route('app.tools.panel', $this->tool), $this->payload($user))->assertRedirect();

        $session = session('lab.panel.market-panel');
        $this->assertTrue($session['partial']);
        $this->assertSame(1, $session['answered']);
        $this->assertSame(1, $session['credits']);
        $this->assertSame(39, $this->balance($user), 'the reservation of three is released and only one voice is captured');
        $run = DB::table('tool_runs')->where('tool_id', $this->tool->id)->where('input_payload', 'like', '%"panel"%')->sole();
        $this->assertSame('completed', $run->status);
        $this->assertSame(1, (int) $run->used_credits);
    }

    public function test_a_panel_that_fails_on_its_first_voice_releases_the_whole_reservation(): void
    {
        $user = $this->member();
        Http::fake(['*api.cloudflare.com*' => Http::response(['errors' => [['message' => 'down']]], 500)]);

        $this->actingAs($user)->post(route('app.tools.panel', $this->tool), $this->payload($user))
            ->assertRedirect()
            ->assertSessionHasErrors('panel');

        $this->assertSame(40, $this->balance($user), 'nothing is charged for a panel that never spoke');
        $run = DB::table('tool_runs')->where('tool_id', $this->tool->id)->latest('id')->first();
        $this->assertSame('failed', $run->status);
        $this->assertSame(0, (int) $run->reserved_credits);
        $this->assertNull(ToolOutput::query()->where('output_type', 'market_panel')->first());
    }

    public function test_the_panel_refuses_to_send_project_data_to_a_provider_that_trains_on_input(): void
    {
        $user = $this->member();
        config(['ai.chain' => ['gemini'], 'ai.providers.gemini.key' => 'g', 'ai.providers.cloudflare.key' => null]);
        Http::fake();

        $this->actingAs($user)->post(route('app.tools.panel', $this->tool), $this->payload($user))
            ->assertRedirect()
            ->assertSessionHasErrors('panel');

        Http::assertNothingSent();
        $this->assertSame(40, $this->balance($user));
    }

    public function test_the_run_is_refused_without_any_provider_and_the_ledger_is_never_touched(): void
    {
        $user = $this->member();
        config(['ai.chain' => ['cloudflare'], 'ai.providers.cloudflare.key' => null]);
        Http::fake();

        $this->actingAs($user)->post(route('app.tools.panel', $this->tool), $this->payload($user))
            ->assertRedirect()
            ->assertSessionHasErrors('panel');

        $this->assertSame(40, $this->balance($user));
        $this->assertSame(0, DB::table('tool_runs')->where('tool_id', $this->tool->id)->count());
        Http::assertNothingSent();
    }

    public function test_the_monthly_allowance_refuses_the_panel_before_a_credit_is_reserved(): void
    {
        $user = $this->member();
        config(['ai.quota.workspace_monthly_requests' => 1, 'ai.quota.workspace_monthly_tokens' => 0]);
        $this->recordUsage($user);
        Http::fake();

        $this->actingAs($user)->post(route('app.tools.panel', $this->tool), $this->payload($user))
            ->assertRedirect()
            ->assertSessionHasErrors('panel');

        $this->assertSame(40, $this->balance($user));
        $this->assertSame(0, DB::table('tool_runs')->where('tool_id', $this->tool->id)->count());
        Http::assertNothingSent();
    }

    public function test_a_panel_too_big_for_what_is_left_of_the_month_is_refused_whole_not_half_spoken(): void
    {
        $user = $this->member();
        // Two calls left in the month, a panel of three voices: refusing it whole is better than one useless voice.
        config(['ai.quota.workspace_monthly_requests' => 3, 'ai.quota.workspace_monthly_tokens' => 0]);
        $this->recordUsage($user);
        Http::fake(['*api.cloudflare.com*' => self::voice('buy', 'مقنع')]);

        $this->actingAs($user)->post(route('app.tools.panel', $this->tool), $this->payload($user))
            ->assertRedirect()
            ->assertSessionHasErrors(['panel' => 'ما بقي من حصة الذكاء الاصطناعي لهذا الشهر لا يكفي تشغيلاً بهذا الحجم. قلّل عدد الشخصيات أو الجولات، أو انتظر تجديد الحد مع بداية الشهر. لم يُخصم أي رصيد.']);

        $this->assertSame(40, $this->balance($user));
        $this->assertSame(0, DB::table('tool_runs')->where('tool_id', $this->tool->id)->count());
        Http::assertNothingSent();

        // The same month fits a panel of two.
        $this->actingAs($user)->post(route('app.tools.panel', $this->tool), $this->payload($user, ['personas' => "أولى\nثانية"]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(38, $this->balance($user));
    }

    public function test_saving_the_panel_from_the_tool_studio_keeps_its_template_and_its_voice_instructions(): void
    {
        $admin = User::factory()->create(['platform_role' => 'admin']);
        $template = (string) $this->tool->output_template;
        $this->assertNotSame('', $template);

        $this->actingAs($admin)->get(route('admin.tools.show', $this->tool))->assertOk()
            ->assertSee('رصيد كل صوت في كل جولة')
            ->assertSee('قالب المخرج', false)
            ->assertSee('وتعليمات الأصوات');

        $this->actingAs($admin)->put(route('admin.tools.update', $this->tool), [
            'name' => $this->tool->name, 'description' => $this->tool->description, 'tagline' => $this->tool->tagline,
            'category' => $this->tool->category, 'credit_cost' => 2, 'is_published' => '1',
            'feeds_from' => $this->tool->feeds_from, 'feeds_into' => $this->tool->feeds_into,
            'input_schema' => json_encode($this->tool->input_schema, JSON_UNESCAPED_UNICODE),
            'output_template' => $template,
        ])->assertRedirect();

        $this->tool->refresh();
        $this->assertSame($template, (string) $this->tool->output_template, 'saving a panel must never blank its template');
        $this->assertSame(2, (int) $this->tool->credit_cost);
        $this->assertSame(['profile', 'customer_persona', 'value_proposition'], $this->tool->feeds_from);
        $this->assertStringContainsString('أجب بـ JSON فقط', (string) $this->tool->activeVersion()?->ai_instructions);
    }

    public function test_the_additive_seeder_creates_the_panel_once_and_never_overwrites_a_tuned_template(): void
    {
        // Production carries .seeded-stage2, so this tool arrives through the additive seeder — safe to rerun.
        $this->tool->update(['output_template' => '# قالب حرّره محرر في الاستوديو']);
        $this->seed(\Database\Seeders\AdditiveToolsSeeder::class);

        $this->assertSame(1, Tool::query()->where('slug', 'market-panel')->count());
        $this->assertSame('# قالب حرّره محرر في الاستوديو', (string) $this->tool->fresh()->output_template);

        $this->tool->versions()->delete();
        $this->tool->delete();
        $this->seed(\Database\Seeders\AdditiveToolsSeeder::class);

        $created = Tool::query()->where('slug', 'market-panel')->firstOrFail();
        $this->assertTrue($created->is_published);
        $this->assertSame('panel', $created->kind);
        $this->assertStringContainsString('محاكاة لا بحث سوق', (string) $created->output_template);
        $this->assertStringContainsString('أجب بـ JSON فقط', (string) $created->activeVersion()?->ai_instructions);
    }

    private function recordUsage(User $user, int $rows = 1): void
    {
        for ($i = 0; $i < $rows; $i++) {
            DB::table('ai_usages')->insert([
                'workspace_id' => $user->workspaces()->first()->id, 'operation_key' => 'seed:'.$i, 'provider' => 'cloudflare', 'model' => 'm',
                'capability' => 'text', 'purpose' => 'tool:x', 'input_tokens' => 10, 'output_tokens' => 10, 'cost_micro_usd' => 0,
                'cached' => false, 'sensitive' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_an_identical_panel_is_served_from_cache_without_spending_the_allowance_again(): void
    {
        $user = $this->member();
        Http::fake(['*api.cloudflare.com*' => self::voice('buy', 'مقنع')]);

        $this->actingAs($user)->post(route('app.tools.panel', $this->tool), $this->payload($user, ['personas' => 'نساء 25–40 ذوات بشرة حساسة']))->assertRedirect();
        Http::assertSentCount(1);

        $this->actingAs($user)->post(route('app.tools.panel', $this->tool), $this->payload($user, ['personas' => 'نساء 25–40 ذوات بشرة حساسة']))->assertRedirect();
        Http::assertSentCount(1);

        $this->assertTrue(session('lab.panel.market-panel')['cached']);
        $this->assertSame(38, $this->balance($user), 'a cached panel still costs its credit; it is the allowance it spares');
        $this->assertSame(1, DB::table('ai_usages')->where('cached', false)->count());
        $this->assertSame(1, DB::table('ai_usages')->where('cached', true)->count());
    }

    public function test_the_saved_asset_carries_the_disclaimer_the_counts_and_the_decision_rule(): void
    {
        $user = $this->member();
        Http::fake(['*api.cloudflare.com*' => Http::sequence()
            ->push(self::voice('buy', 'الوعد واضح'))
            ->push(self::voice('buy', 'الضمان يطمئنني'))
            ->push(self::voice('maybe', 'أحتاج دليلاً', 'لا دليل منشور', 'صورة اختبار'))]);

        $this->actingAs($user)->post(route('app.tools.panel', $this->tool), $this->payload($user))->assertRedirect();

        $output = ToolOutput::query()->where('output_type', 'market_panel')->firstOrFail();
        $this->assertSame(1, $output->version);
        $this->assertStringContainsString('محاكاة لا بحث سوق', $output->body_markdown);
        $this->assertStringContainsString('قاعدة القرار:', $output->body_markdown);
        $this->assertStringContainsString('اختبرها كما هي بـ10٪ من الميزانية', $output->body_markdown, 'two of three buying is the "test it" rule');
        $this->assertStringContainsString('لا دليل منشور', $output->body_markdown);
        $this->assertStringNotContainsString('{{', $output->body_markdown, 'no placeholder may survive into a saved asset');
        $this->assertSame('panel', $output->payload['source']);
        $this->assertSame('لوحة السوق المحاكى', \App\Domain\Lab\Services\LabCatalog::label('market_panel'));

        // The asset is readable on its own page like any other output.
        $this->actingAs($user)->get(route('app.outputs.show', $output))->assertOk()->assertSee('محاكاة لا بحث سوق');
    }

    public function test_an_incomplete_panel_is_sent_back_to_the_inputs_without_a_run(): void
    {
        $user = $this->member();
        Http::fake();

        $this->actingAs($user)->post(route('app.tools.panel', $this->tool), $this->payload($user, ['personas' => '', 'message' => '']))
            ->assertRedirect()
            ->assertSessionHasErrors('panel');

        $this->assertSame(0, DB::table('tool_runs')->where('tool_id', $this->tool->id)->count());
        $this->assertSame(40, $this->balance($user));
        Http::assertNothingSent();
    }

    public function test_a_reader_cannot_run_a_panel_in_a_workspace_they_only_read(): void
    {
        $user = $this->member();
        $reader = User::factory()->create(['audience' => 'owner']);
        $user->workspaces()->first()->members()->attach($reader, ['role' => 'viewer']);
        Http::fake();

        $this->actingAs($reader)->post(route('app.tools.panel', $this->tool), $this->payload($user))->assertForbidden();
        Http::assertNothingSent();
    }
}
