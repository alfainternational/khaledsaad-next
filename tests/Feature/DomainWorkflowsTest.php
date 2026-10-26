<?php

namespace Tests\Feature;

use App\Domain\Academy\Actions\CompleteLesson;
use App\Domain\Academy\Actions\CompleteActivity;
use App\Domain\Academy\Models\Activity;
use App\Domain\Academy\Models\Course;
use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Content\Actions\PublishContent;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Lab\Services\AiGateway;
use App\Domain\Lab\Services\CampaignBriefBuilder;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DomainWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_translated_content_requires_source_and_human_review(): void
    {
        $item = ContentItem::query()->create([
            'type' => 'translated_analysis', 'status' => 'draft', 'title' => 'تحليل عالمي',
            'slug' => 'global-analysis', 'excerpt' => 'ملخص', 'body' => 'محتوى',
            'author_name' => 'فريق أثر', 'source_mode' => 'analysis',
        ]);

        $this->expectException(DomainException::class);
        app(PublishContent::class)->handle($item);
    }

    public function test_reviewed_sourced_content_can_be_published_with_version(): void
    {
        $item = ContentItem::query()->create([
            'type' => 'translated_analysis', 'status' => 'review', 'title' => 'تحليل عالمي',
            'slug' => 'reviewed-analysis', 'excerpt' => 'ملخص', 'body' => 'محتوى عربي تحليلي',
            'author_name' => 'فريق أثر', 'reviewer_name' => 'خالد سعد',
            'reviewed_at' => now(), 'source_mode' => 'analysis',
            'meta' => ['arabic_context' => 'يوضح التحليل أثر الاتجاه على السوق العربي.'],
        ]);
        $item->sources()->create(['publisher' => 'مصدر', 'title' => 'مرجع', 'url' => 'https://example.com', 'language' => 'en']);

        app(PublishContent::class)->handle($item);

        $this->assertSame('published', $item->fresh()->status);
        $this->assertDatabaseHas('content_versions', ['content_item_id' => $item->id, 'version' => 1]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.published', 'subject_id' => (string) $item->id]);
    }

    public function test_content_cannot_skip_the_editorial_review_state(): void
    {
        $item = ContentItem::query()->create([
            'type' => 'article', 'status' => 'draft', 'title' => 'مسودة',
            'slug' => 'draft-item', 'excerpt' => 'ملخص', 'body' => 'محتوى', 'author_name' => 'فريق أثر',
        ]);

        $this->expectException(DomainException::class);
        app(PublishContent::class)->handle($item);
    }

    public function test_lesson_completion_is_idempotent(): void
    {
        $user = User::factory()->create();
        $course = Course::query()->create(['title' => 'دورة', 'slug' => 'course', 'summary' => 'ملخص', 'is_published' => true]);
        $lesson = $course->lessons()->create(['title' => 'درس', 'slug' => 'lesson', 'objective' => 'هدف', 'content' => 'محتوى']);

        app(CompleteLesson::class)->handle($user, $lesson, ['answer' => 'مخرج']);
        app(CompleteLesson::class)->handle($user, $lesson, ['answer' => 'مخرج']);

        $this->assertDatabaseCount('activity_completions', 1);
    }

    public function test_activity_completion_is_idempotent(): void
    {
        $user = User::factory()->create();
        $course = Course::query()->create(['title' => 'دورة', 'slug' => 'activity-course', 'summary' => 'ملخص', 'is_published' => true]);
        $lesson = $course->lessons()->create(['title' => 'درس', 'slug' => 'activity-lesson', 'objective' => 'هدف', 'content' => 'محتوى']);
        $activity = Activity::query()->create(['lesson_id' => $lesson->id, 'title' => 'تمرين', 'instructions' => 'طبّق']);

        app(CompleteActivity::class)->handle($user, $activity, ['text' => 'مخرج']);
        app(CompleteActivity::class)->handle($user, $activity, ['text' => 'مخرج']);

        $this->assertDatabaseCount('activity_completions', 1);
    }

    public function test_credit_ledger_is_append_only_and_idempotent(): void
    {
        $workspace = Workspace::query()->create(['name' => 'مساحة', 'slug' => 'space']);
        $ledger = app(CreditLedger::class);

        $ledger->record($workspace, 'welcome:'.$workspace->id, 'grant', 20, 'رصيد ترحيبي');
        $ledger->record($workspace, 'welcome:'.$workspace->id, 'grant', 20, 'رصيد ترحيبي');
        $ledger->record($workspace, 'run:1', 'capture', -3, 'تشغيل أداة');

        $this->assertSame(17, $ledger->balance($workspace));
        $this->assertDatabaseCount('credit_entries', 2);
    }

    public function test_credit_reservation_rejects_an_insufficient_balance_atomically(): void
    {
        $workspace = Workspace::query()->create(['name' => 'مساحة', 'slug' => 'reserve-space']);
        $ledger = app(CreditLedger::class);
        $ledger->record($workspace, 'grant:reserve', 'grant', 2, 'رصيد');

        $ledger->reserve($workspace, 'run:first:reserve', 2, 'الحجز الأول');

        $this->expectException(DomainException::class);
        $ledger->reserve($workspace, 'run:second:reserve', 1, 'الحجز الثاني');
    }

    public function test_campaign_brief_builder_returns_a_structured_arabic_output(): void
    {
        $result = app(CampaignBriefBuilder::class)->build([
            'goal' => 'زيادة الطلبات المؤهلة', 'audience' => 'أصحاب المتاجر الناشئة', 'offer' => 'استشارة نمو',
        ]);

        $this->assertSame('زيادة الطلبات المؤهلة', $result['goal']);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('measurement', $result);
    }

    public function test_ai_gateway_uses_responses_api_and_returns_only_completed_text(): void
    {
        config()->set('services.openai.key', 'test-key');
        Http::fake(['*/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'تحليل عربي مكتمل']]]],
            'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
        ])]);

        $result = app(AiGateway::class)->generate('حلّل العرض التسويقي');

        $this->assertSame('تحليل عربي مكتمل', $result['text']);
        $this->assertSame(30, $result['tokens']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/responses'));
    }
}
