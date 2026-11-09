<?php

namespace Tests\Feature;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Insights\Models\ContentReport;
use App\Domain\Insights\Models\LearnerEvent;
use App\Domain\Insights\Models\LearnerNudge;
use App\Domain\Insights\Models\QualitySignal;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Services\LabCatalog;
use App\Domain\Workspace\Models\Workspace;
use App\Mail\AssetWaitingReminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/** غرفة التحكم (§6.1 الأقسام 3 و4 و6 و7، §6.6): إغلاق البلاغات، تذكير «أصلك ينتظرك»، تجاهل الإشارات، اقتصاد الرصيد، وكل تغيير في التدقيق. */
class AdminGapsControlRoomTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create(['platform_role' => 'admin']);
        $this->lesson = Course::query()->where('slug', 'marketing-foundations')->firstOrFail()->lessons()->firstOrFail();
    }

    private function stalledLearner(int $daysAgo = 9): User
    {
        $learner = User::factory()->create(['name' => 'متعلمة متوقفة']);
        LearnerEvent::query()->create(['user_id' => $learner->id, 'session_token' => 'stalled-'.$learner->id, 'event' => 'visit.lesson', 'lesson_id' => $this->lesson->id, 'created_at' => now()->subDays($daysAgo)]);

        return $learner;
    }

    public function test_reports_link_to_their_entity_and_close_as_resolved_or_dismissed_with_an_audit_row(): void
    {
        $item = ContentItem::query()->where('status', 'published')->firstOrFail();
        $lessonReport = ContentReport::query()->create(['lesson_id' => $this->lesson->id, 'part' => 'concept', 'reason' => 'unclear', 'note' => 'الفقرة الثانية غامضة']);
        $articleReport = ContentReport::query()->create(['content_item_id' => $item->id, 'reason' => 'outdated']);

        $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->assertSee('بلاغات من الدروس والمواد')->assertSee($item->title)->assertSee('معلومة متقادمة')->assertSee('الفقرة الثانية غامضة')
            ->assertSee(route('admin.content.edit', ['contentItem' => $item->id]), false)
            ->assertSee(route('admin.academy.lessons.edit', ['lesson' => $this->lesson, 'part' => 'concept']), false)
            ->assertSee(route('admin.reports.update', $lessonReport), false)->assertSee(route('admin.reports.update', $articleReport), false);

        $this->actingAs($this->admin)->patch('/admin/reports/'.$lessonReport->id, ['status' => 'resolved'])->assertRedirect()->assertSessionHas('status');
        $this->assertSame('resolved', $lessonReport->fresh()->status);
        $row = DB::table('audit_logs')->where('action', 'report.resolved')->where('subject_type', 'content_report')->where('subject_id', (string) $lessonReport->id)->first();
        $this->assertNotNull($row);
        $this->assertSame($this->admin->id, (int) $row->user_id);
        $this->assertNotEmpty($row->request_id);
        $context = json_decode($row->context, true);
        $this->assertSame(['open', 'resolved', $this->lesson->id, 'concept'], [$context['before'], $context['after'], $context['lesson_id'], $context['part']]);

        $this->actingAs($this->admin)->patch('/admin/reports/'.$articleReport->id, ['status' => 'dismissed'])->assertRedirect();
        $this->assertSame('dismissed', $articleReport->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.dismissed', 'subject_type' => 'content_report', 'subject_id' => (string) $articleReport->id, 'user_id' => $this->admin->id]);

        // Closed reports leave the box; an unknown status and a non-staff actor are refused; a missing report is a 404.
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('لا بلاغات مفتوحة')->assertDontSee('الفقرة الثانية غامضة');
        $this->actingAs($this->admin)->patch('/admin/reports/'.$lessonReport->id, ['status' => 'open'])->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->patch('/admin/reports/999999', ['status' => 'resolved'])->assertNotFound();
        $open = ContentReport::query()->create(['lesson_id' => $this->lesson->id, 'reason' => 'other']);
        $this->actingAs(User::factory()->create())->patch('/admin/reports/'.$open->id, ['status' => 'resolved'])->assertForbidden();
        $this->assertSame('open', $open->fresh()->status);
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'like', 'report.%')->count());
    }

    public function test_nudge_queues_one_branded_mail_records_the_row_and_blocks_a_repeat_within_seven_days(): void
    {
        Mail::fake();
        $learner = $this->stalledLearner();

        $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->assertSee('متعلمة متوقفة')->assertSee('أصلك ينتظرك')->assertSee('لم يُذكَّر بعد')
            ->assertSee(route('admin.nudges.store'), false)->assertDontSee('mailto:', false);

        $this->actingAs($this->admin)->post('/admin/nudges', ['user_id' => $learner->id, 'lesson_id' => $this->lesson->id])->assertRedirect()->assertSessionHas('status');
        Mail::assertQueued(AssetWaitingReminder::class, 1);
        Mail::assertQueued(AssetWaitingReminder::class, fn (AssetWaitingReminder $mail) => $mail->hasTo($learner->email) && $mail->nudge->lesson_id === $this->lesson->id);
        $this->assertDatabaseHas('learner_nudges', ['user_id' => $learner->id, 'lesson_id' => $this->lesson->id, 'sent_by' => $this->admin->id, 'kind' => 'asset_waiting', 'channel' => 'email', 'status' => 'queued']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'learner.nudged', 'subject_type' => 'user', 'subject_id' => (string) $learner->id, 'user_id' => $this->admin->id]);

        // Same learner, same lesson, inside the 7-day window: refused, nothing queued, nothing recorded.
        $this->actingAs($this->admin)->post('/admin/nudges', ['user_id' => $learner->id, 'lesson_id' => $this->lesson->id])->assertRedirect()->assertSessionHasErrors('nudge');
        Mail::assertQueued(AssetWaitingReminder::class, 1);
        $this->assertSame(1, LearnerNudge::query()->count());
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('آخر تذكير')->assertSee('في الطابور')->assertDontSee('لم يُذكَّر بعد');

        // A lesson the learner never opened, a missing user, and a non-staff actor are refused.
        $other = Lesson::query()->where('id', '!=', $this->lesson->id)->firstOrFail();
        $this->actingAs($this->admin)->post('/admin/nudges', ['user_id' => $learner->id, 'lesson_id' => $other->id])->assertSessionHasErrors('nudge');
        $this->actingAs($this->admin)->post('/admin/nudges', ['user_id' => 999999, 'lesson_id' => $this->lesson->id])->assertSessionHasErrors('user_id');
        $this->actingAs(User::factory()->create())->post('/admin/nudges', ['user_id' => $learner->id, 'lesson_id' => $this->lesson->id])->assertForbidden();
        Mail::assertQueued(AssetWaitingReminder::class, 1);

        // After the window the learner can be reminded again.
        $this->travel(LearnerNudge::COOLDOWN_DAYS + 1)->days();
        $this->actingAs($this->admin)->post('/admin/nudges', ['user_id' => $learner->id, 'lesson_id' => $this->lesson->id])->assertRedirect()->assertSessionHasNoErrors();
        Mail::assertQueued(AssetWaitingReminder::class, 2);
        $this->assertSame(2, LearnerNudge::query()->count());
    }

    public function test_nudge_records_the_delivery_result_and_the_learners_return(): void
    {
        $learner = $this->stalledLearner();

        // Real (array) mailer on the sync queue: the queued mailable is delivered and writes its result back.
        $this->actingAs($this->admin)->post('/admin/nudges', ['user_id' => $learner->id, 'lesson_id' => $this->lesson->id])->assertRedirect();
        $nudge = LearnerNudge::query()->firstOrFail();
        $this->assertSame('sent', $nudge->status);
        $this->assertNotNull($nudge->sent_at);
        $this->assertNull($nudge->returned_at);

        $html = (new AssetWaitingReminder($nudge))->render();
        $this->assertStringContainsString('أكمل من حيث توقفت', $html);
        $this->assertStringContainsString($this->lesson->title, $html);
        $this->assertStringContainsString(route('academy.lesson', [$this->lesson->course, $this->lesson]), $html);
        $this->assertSame('أصلك ينتظرك في أثر', (new AssetWaitingReminder($nudge))->envelope()->subject);

        // A failed delivery is recorded too, and does not hold the cooldown.
        (new AssetWaitingReminder($nudge))->failed(new \RuntimeException('SMTP down'));
        $this->assertSame(['failed', 'SMTP down'], [$nudge->fresh()->status, $nudge->fresh()->error]);
        $nudge->refresh()->update(['status' => 'sent', 'error' => null]);

        $this->travel(2)->days();
        LearnerEvent::query()->create(['user_id' => $learner->id, 'session_token' => 'back', 'event' => 'visit.lesson', 'lesson_id' => $this->lesson->id, 'created_at' => now()]);
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('عاد بعدها إلى درس: 1');
        $this->assertNotNull($nudge->fresh()->returned_at);
    }

    public function test_quality_signal_can_be_dismissed_and_status_changes_and_previews_are_audited(): void
    {
        $signal = QualitySignal::query()->create(['type' => 'lesson_dropoff', 'entity_type' => 'lesson', 'entity_id' => $this->lesson->id, 'part' => 'goal', 'value' => 67, 'impact' => 9, 'status' => 'open', 'summary' => 'تسرّب تجريبي عند الهدف', 'meta' => [], 'computed_at' => now()]);
        $second = QualitySignal::query()->create(['type' => 'lab_not_saved', 'entity_type' => 'lesson', 'entity_id' => $this->lesson->id, 'part' => 'lab', 'value' => 80, 'impact' => 4, 'status' => 'open', 'summary' => 'مختبر تجريبي بلا حفظ', 'meta' => [], 'computed_at' => now()]);
        cache()->put('platform:quality_signals_computed_at', now()->timestamp);

        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('تسرّب تجريبي عند الهدف')->assertSee('data-dismiss-signal', false)->assertSee('تجاهل');

        $this->actingAs($this->admin)->patch('/admin/quality/'.$signal->id, ['status' => 'ignored'])->assertRedirect()->assertSessionHas('status');
        $this->assertSame('ignored', $signal->fresh()->status);
        $row = DB::table('audit_logs')->where('action', 'quality_signal.status_changed')->where('subject_id', (string) $signal->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('quality_signal', $row->subject_type);
        $this->assertSame($this->admin->id, (int) $row->user_id);
        $context = json_decode($row->context, true);
        $this->assertSame(['open', 'ignored', 'lesson_dropoff'], [$context['before'], $context['after'], $context['type']]);

        $this->actingAs($this->admin)->patch('/admin/quality/'.$second->id, ['status' => 'handled'])->assertRedirect();
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'quality_signal.status_changed')->count());
        // No change, no audit row; a missing signal is a 404.
        $this->actingAs($this->admin)->patch('/admin/quality/'.$second->id, ['status' => 'handled'])->assertRedirect();
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'quality_signal.status_changed')->count());
        $this->actingAs($this->admin)->patch('/admin/quality/999999', ['status' => 'ignored'])->assertNotFound();

        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertDontSee('تسرّب تجريبي عند الهدف')->assertDontSee('مختبر تجريبي بلا حفظ');

        $this->actingAs($this->admin)->post('/admin/preview/visitor')->assertRedirect('/');
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.preview_started', 'subject_type' => 'preview', 'subject_id' => 'visitor', 'user_id' => $this->admin->id]);
        $this->actingAs($this->admin)->post('/admin/preview/off')->assertRedirect('/admin');
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.preview_ended', 'subject_id' => 'visitor', 'user_id' => $this->admin->id]);
    }

    public function test_credit_economy_shows_the_save_rate_of_the_most_expensive_tool(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('معدل حفظ الأداة الأغلى')->assertSee('data-top-tool-save', false);

        $member = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'مساحة', 'slug' => 'space']);
        $workspace->members()->attach($member, ['role' => 'owner']);
        $project = $workspace->projects()->create(['name' => 'مشروع', 'slug' => 'p']);
        $tool = Tool::query()->where('slug', 'campaign-brief-builder')->firstOrFail();
        $cheap = Tool::query()->where('kind', 'generator')->where('id', '!=', $tool->id)->firstOrFail();

        $input = LabCatalog::find('campaign-brief-builder')['example_input'];
        $this->actingAs($member)->post('/tools/campaign-brief-builder/generate', $input)->assertRedirect();
        $this->actingAs($member)->post('/tools/campaign-brief-builder/generate', $input)->assertRedirect();
        $result = session('lab.result.campaign-brief-builder');
        $this->actingAs($member)->post('/app/tools/campaign-brief-builder/save', ['project_id' => $project->id, 'title' => $result['title'], 'markdown' => $result['markdown']])->assertRedirect();
        foreach ([[$tool, 5], [$cheap, 1]] as [$t, $credits]) {
            DB::table('tool_runs')->insert([
                'workspace_id' => $workspace->id, 'project_id' => $project->id, 'tool_id' => $t->id, 'user_id' => $member->id, 'public_id' => (string) Str::uuid(),
                'status' => 'completed', 'input_payload' => '{}', 'used_credits' => $credits, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $page = $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('الأداة الأغلى')->assertSee($tool->name)->assertSee('1 محفوظ من 2 مولَّد');
        $this->assertMatchesRegularExpression('/data-top-tool-save>50٪/u', $page->getContent());
    }

    public function test_pipeline_makes_no_dead_promise_about_ai_skeletons(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('خط إنتاج الدروس')->assertDontSee('ولّد الهياكل');
    }
}
