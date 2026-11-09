<?php

namespace Tests\Feature;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Academy\Services\LessonAssetEnhancer;
use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Lab\Services\AssetVault;
use App\Domain\Lab\Services\LabCatalog;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Learner-facing gaps closed after the blueprint audit: lesson AI polish, home chain labels, dashboard assets table. */
class LearnerGapsTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->course = Course::query()->where('slug', 'marketing-foundations')->firstOrFail();
        $this->lesson = $this->course->lessons()->where('slug', 'first-channel-and-budget')->firstOrFail();
    }

    private function member(): User
    {
        $user = User::factory()->create(['audience' => 'owner']);
        $workspace = Workspace::query()->create(['name' => 'مساحة', 'slug' => 'space-'.$user->id]);
        $workspace->members()->attach($user, ['role' => 'owner']);
        $workspace->projects()->create(['name' => 'متجر نَدى', 'slug' => 'nada-'.$user->id, 'profile' => []]);
        app(CreditLedger::class)->record($workspace, 'grant:'.$user->id, 'grant', 20, 'رصيد');

        return $user;
    }

    private function balance(User $user): int
    {
        return (int) DB::table('credit_entries')->where('workspace_id', $user->workspaces()->first()->id)->sum('amount');
    }

    private function generate(User $user, string $url): void
    {
        $this->actingAs($user)->post($url.'/lab', ['situation' => 'general', 'channel' => 'instagram', 'why_channel' => 'شريحتي هناك', 'margin' => 120, 'budget' => 3000, 'goal_number' => 60])->assertRedirect();
    }

    public function test_lesson_asset_polish_is_hidden_without_a_key_and_charges_the_declared_cost_with_one(): void
    {
        $user = $this->member();
        $url = route('academy.lesson', [$this->course, $this->lesson]);
        $this->generate($user, $url);

        $this->actingAs($user)->get($url)->assertOk()->assertDontSee('حسّن بالذكاء الاصطناعي');
        $this->actingAs($user)->post($url.'/enhance')->assertNotFound();

        config(['services.openai.key' => 'test-key']);
        $cost = LessonAssetEnhancer::cost($this->lesson);
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => "## خطة القناة الأولى\n\nنسخة محسّنة الصياغة بالأرقام نفسها."]]]]])]);

        $this->actingAs($user)->get($url)->assertOk()->assertSee("حسّن بالذكاء الاصطناعي ({$cost} رصيد)");
        $this->actingAs($user)->post($url.'/enhance', ['situation' => 'general'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->get($url)->assertOk()->assertSee('نسخة محسّنة الصياغة بالأرقام نفسها')->assertSee('حُسّنت الصياغة');

        $this->assertSame(20 - $cost, $this->balance($user));
        $this->assertDatabaseHas('learner_events', ['event' => 'lesson.asset_enhanced', 'lesson_id' => $this->lesson->id, 'user_id' => $user->id]);
    }

    public function test_failed_polish_keeps_the_asset_and_refunds_the_reservation(): void
    {
        $user = $this->member();
        $url = route('academy.lesson', [$this->course, $this->lesson]);
        $this->generate($user, $url);
        $before = session('lab.asset.'.$this->lesson->id)['markdown'];

        config(['services.openai.key' => 'test-key']);
        Http::fake(['*' => Http::response(['error' => ['message' => 'upstream down']], 500)]);

        $this->actingAs($user)->post($url.'/enhance', ['situation' => 'general'])->assertRedirect()->assertSessionHasErrors('lab');
        $this->assertSame($before, session('lab.asset.'.$this->lesson->id)['markdown']);
        $this->assertSame(20, $this->balance($user));
    }

    public function test_polish_needs_a_generated_asset_and_enough_credits(): void
    {
        config(['services.openai.key' => 'test-key']);
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => 'نص']]]]])]);
        $user = $this->member();
        $url = route('academy.lesson', [$this->course, $this->lesson]);

        $this->actingAs($user)->post($url.'/enhance')->assertRedirect()->assertSessionHasErrors('lab');

        $this->generate($user, $url);
        $workspace = $user->workspaces()->first();
        app(CreditLedger::class)->record($workspace, 'spend:'.$user->id, 'capture', -20, 'استهلاك كامل الرصيد');
        $this->actingAs($user)->post($url.'/enhance')->assertRedirect()->assertSessionHasErrors('lab');
        $this->assertSame(0, $this->balance($user));
        Http::assertNothingSent();

        $this->post(route('logout'));
        $this->post($url.'/enhance')->assertRedirect(route('login'));
    }

    public function test_home_shows_what_each_tool_feeds_and_the_tool_each_article_applies(): void
    {
        $tools = Tool::query()->where('is_published', true)->where('kind', 'generator')->where('slug', '!=', 'lesson-lab')->orderBy('position')->limit(3)->get();
        $articles = ContentItem::published()->latest('published_at')->limit(3)->get();
        $articles->first()->update(['related_tool_id' => $tools->last()->id]);

        $response = $this->get('/')->assertOk();
        foreach ($tools as $tool) {
            $this->assertNotEmpty($tool->feeds_into, $tool->slug.' declares what it feeds');
            $response->assertSee(LabCatalog::label($tool->feeds_into[0]));
        }
        $response->assertSee('<b>طبّق بـ:</b> '.e($tools->last()->name), false);
        $response->assertDontSee('الأصول التالية في السلسلة');
    }

    public function test_dashboard_assets_table_names_project_type_and_links_each_asset(): void
    {
        $user = $this->member();
        $project = $user->workspaces()->first()->projects()->first();
        $asset = app(AssetVault::class)->save($user, $project, null, 'موجز حملة رمضان', 'campaign_brief', "# موجز حملة رمضان\n\nهدف واحد وقناة واحدة.");
        $this->assertInstanceOf(ToolOutput::class, $asset);

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('<th>المشروع</th>', false)
            ->assertSee(LabCatalog::label('campaign_brief'))
            ->assertSee('متجر نَدى')
            ->assertSee(route('app.outputs.show', $asset), false)
            ->assertSee('كل أصولي')
            ->assertSee('الخطة المجانية')
            ->assertDontSee('aria-label="تم"', false);
    }

    public function test_dashboard_and_billing_name_the_plan_and_ignore_an_expired_period(): void
    {
        $user = $this->member();
        $workspaceId = $user->workspaces()->first()->id;
        DB::table('subscriptions')->insert(['workspace_id' => $workspaceId, 'plan' => 'pro', 'status' => 'active', 'ends_at' => now()->addMonth(), 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('الخطة الاحترافية');
        $this->actingAs($user)->get('/app/billing')->assertOk()->assertSee('الاحترافية')->assertSee('نشطة')->assertDontSee('<strong>pro</strong>', false);

        DB::table('subscriptions')->where('workspace_id', $workspaceId)->update(['ends_at' => now()->subDay()]);
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('الخطة المجانية')->assertDontSee('الخطة الاحترافية');
    }
}
