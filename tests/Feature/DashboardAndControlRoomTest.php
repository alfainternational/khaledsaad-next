<?php

namespace Tests\Feature;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Assessment\Models\AssessmentQuestion;
use App\Domain\Insights\Models\LearnerEvent;
use App\Domain\Insights\Models\QualitySignal;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAndControlRoomTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->course = Course::query()->where('slug', 'marketing-foundations')->firstOrFail();
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'مساحة', 'slug' => 'space-'.$user->id]);
        $workspace->members()->attach($user, ['role' => 'owner']);
        $workspace->projects()->create(['name' => 'مشروعي', 'slug' => 'p', 'profile' => ['offer' => 'قهوة', 'budget' => 2000, 'channel' => 'instagram', 'goal' => 'leads', 'goal_number' => 40]]);

        return $user;
    }

    public function test_dashboard_shows_indicators_weekly_plan_progress_assets_and_certificate_box(): void
    {
        $user = $this->member();
        $lesson = $this->course->lessons()->first();
        $url = route('academy.lesson', [$this->course, $lesson]);
        $this->actingAs($user)->get($url)->assertOk();
        $this->actingAs($user)->post($url.'/save', ['title' => 'بطاقة القيمة', 'type' => 'value_card', 'markdown' => '# بطاقة'])->assertRedirect();
        $this->actingAs($user)->post($url.'/checkin', ['mode' => 'commit', 'action' => 'اتصل بثلاثة عملاء'])->assertRedirect();

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('خطة الأسبوع')->assertSee('سجّل نتيجتك الواقعية')->assertSee('أكمل من حيث توقفت')->assertSee($lesson->title)
            ->assertSee('درس مكتمل')->assertSee('أصل محفوظ')->assertSee('بطاقة القيمة')->assertSee('أبعاد النضج')->assertSee('المقترحات')->assertSee('الشهادة القادمة')->assertSee('نتيجة واقعية واحدة')
            ->assertSee('النشاط')->assertSee('حفظت أصلاً')->assertSee('مشاريعي')->assertSee('رصيد متبقٍ');
        $this->actingAs($user)->get('/app/progress')->assertOk()->assertSee('المسارات بترتيبها')->assertSee('الدروس المكتملة')->assertSee('الشهادات');
    }

    public function test_member_is_forbidden_from_admin_and_staff_sees_the_control_room(): void
    {
        $this->actingAs($this->member())->get('/admin')->assertForbidden();
        $admin = User::factory()->create(['platform_role' => 'admin']);
        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee('أعمدة الرحلة الخمسة')->assertSee('اكتشف')->assertSee('طوّر')->assertSee('إشارات الجودة')->assertSee('خط إنتاج الدروس')
            ->assertSee('صندوق نجاح المتعلمين')->assertSee('اقتصاد الرصيد')->assertSee('التشغيل')->assertSee('اعرض المنصة كـ')->assertSee('⌘K');
        foreach (['/admin/academy', '/admin/assessment'] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
        $this->actingAs(User::factory()->create(['platform_role' => 'editor']))->get('/admin/academy')->assertOk();
    }

    public function test_quality_signals_are_computed_from_learner_behaviour_and_open_in_the_studio(): void
    {
        config(['platform.quality_min_sample' => 2]);
        $lesson = $this->course->lessons()->first();
        $question = $lesson->questions()->where('is_opening', false)->first();
        $wrong = collect($question->options)->search(fn ($o) => empty($o['is_best']));
        foreach (['s1', 's2', 's3'] as $token) {
            LearnerEvent::query()->create(['session_token' => $token, 'event' => 'visit.lesson', 'lesson_id' => $lesson->id, 'created_at' => now()]);
            LearnerEvent::query()->create(['session_token' => $token, 'event' => 'lesson.part_reached', 'lesson_id' => $lesson->id, 'part' => 'opening', 'created_at' => now()]);
            LearnerEvent::query()->create(['session_token' => $token, 'event' => 'question.answered', 'lesson_id' => $lesson->id, 'question_id' => $question->id, 'meta' => ['correct' => false, 'choice' => $wrong], 'created_at' => now()]);
            LearnerEvent::query()->create(['session_token' => $token, 'event' => 'lesson.lab_generated', 'lesson_id' => $lesson->id, 'part' => 'lab', 'created_at' => now()]);
        }
        LearnerEvent::query()->create(['session_token' => 's1', 'event' => 'lesson.part_reached', 'lesson_id' => $lesson->id, 'part' => 'goal', 'created_at' => now()]);

        $this->artisan('platform:compute-quality-signals')->assertSuccessful();
        $this->assertDatabaseHas('quality_signals', ['type' => 'lesson_dropoff', 'entity_id' => $lesson->id, 'part' => 'goal', 'status' => 'open']);
        $this->assertDatabaseHas('quality_signals', ['type' => 'question_wrong_majority', 'entity_id' => $question->id, 'status' => 'open']);
        $this->assertDatabaseHas('quality_signals', ['type' => 'lab_not_saved', 'entity_id' => $lesson->id, 'status' => 'open']);

        $admin = User::factory()->create(['platform_role' => 'admin']);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('يُخطئ فيه الأغلبية')->assertSee('تسرّب في درس')->assertSee('افتح في مكان المشكلة')->assertSee('#question-'.$question->id, false);
        $this->actingAs($admin)->get('/admin/academy/lessons/'.$lesson->id)->assertOk()
            ->assertSee('استوديو الدرس')->assertSee('المساعد التحريري')->assertSee('أعد صياغة سؤال يُخطئ فيه الأغلبية')->assertSee('نسبة التعبئة')->assertSee('معاينة كمتعلم')->assertSee('33٪');
        $signal = QualitySignal::query()->where('type', 'lab_not_saved')->firstOrFail();
        $this->actingAs($admin)->patch('/admin/quality/'.$signal->id, ['status' => 'handled'])->assertRedirect();
        $this->assertSame('handled', $signal->fresh()->status);
    }

    public function test_studio_saves_a_new_version_and_can_restore_it_and_order_changes_reflect_publicly(): void
    {
        $admin = User::factory()->create(['platform_role' => 'editor']);
        $lesson = $this->course->lessons()->first();
        $question = $lesson->questions()->where('is_opening', false)->first();
        $original = $lesson->title;

        $this->actingAs($admin)->put('/admin/academy/lessons/'.$lesson->id, [
            'title' => 'عنوان محدّث', 'goal' => $lesson->goal, 'publish' => 1, 'reason' => 'اختبار', 'part' => 'questions',
            'questions' => [$question->id => ['scenario' => 'سيناريو محدّث', 'prompt' => $question->prompt, 'best' => $question->bestIndex(), 'options' => collect($question->options)->map(fn ($o) => ['label' => $o['label'], 'feedback' => $o['feedback']])->all()]],
        ])->assertRedirect();
        $this->assertSame('عنوان محدّث', $lesson->fresh()->title);
        $this->assertSame('سيناريو محدّث', $question->fresh()->scenario);
        $this->assertDatabaseHas('lesson_versions', ['lesson_id' => $lesson->id, 'version' => 1]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lesson.updated']);

        $this->actingAs($admin)->put('/admin/academy/lessons/'.$lesson->id, ['title' => 'x', 'lab_config' => '{not json', 'part' => 'lab'])->assertSessionHasErrors('lab_config');

        $this->actingAs($admin)->post('/admin/academy/lessons/'.$lesson->id.'/restore/1')->assertRedirect();
        $this->assertSame($original, $lesson->fresh()->title);

        $second = $this->course->lessons()->skip(1)->first();
        $this->actingAs($admin)->patch('/admin/academy/courses/marketing-foundations', ['positions' => [$lesson->id => 2, $second->id => 1], 'is_published' => 1])->assertRedirect();
        $html = $this->get('/academy/marketing-foundations')->assertOk()->getContent();
        $this->assertLessThan(mb_strpos($html, $lesson->title), mb_strpos($html, $second->title));
    }

    public function test_assessment_admin_updates_questions_rules_and_shows_coverage(): void
    {
        $admin = User::factory()->create(['platform_role' => 'editor']);
        $page = $this->actingAs($admin)->get('/admin/assessment')->assertOk()->assertSee('خريطة التغطية')->assertSee('قواعد التوجيه');
        $this->assertSame(0, substr_count($page->getContent(), '>✗<'));

        $q = AssessmentQuestion::query()->where('audience', 'owner')->first();
        $this->actingAs($admin)->put('/admin/assessment/questions/'.$q->id, ['audience' => 'owner', 'dimension' => 'goal', 'prompt' => 'سؤال محدّث {{budget}}', 'position' => 1, 'is_active' => 1, 'options' => [['label' => 'أ', 'weight' => 0], ['label' => 'ب', 'weight' => 1], ['label' => 'ج', 'weight' => 3]]])->assertRedirect();
        $this->assertSame('سؤال محدّث {{budget}}', $q->fresh()->prompt);

        $lesson = Lesson::query()->where('slug', 'market-map')->firstOrFail();
        $this->actingAs($admin)->post('/admin/assessment/rules', ['audience' => 'owner', 'level' => 'beginner', 'weakest_dimension' => 'goal', 'lesson_id' => $lesson->id, 'priorities' => [['title' => 'أ', 'reason' => 'ب', 'lesson' => 'market-map'], ['title' => 'ج', 'reason' => 'د'], ['title' => 'هـ', 'reason' => 'و', 'course' => 'marketing-foundations']]])->assertRedirect();
        $this->assertDatabaseHas('assessment_rules', ['audience' => 'owner', 'level' => 'beginner', 'weakest_dimension' => 'goal', 'lesson_id' => $lesson->id]);
    }

    public function test_admin_can_preview_the_platform_as_a_visitor(): void
    {
        $admin = User::factory()->create(['platform_role' => 'admin']);
        $this->actingAs($admin)->post('/admin/preview/visitor')->assertRedirect('/');
        $this->actingAs($admin)->get('/')->assertOk()->assertSee('معاينة إدارية')->assertSee('ابدأ مجاناً')->assertDontSee('لوحتي');
        $this->actingAs($admin)->post('/admin/preview/off')->assertRedirect();
        $this->actingAs($admin)->get('/')->assertOk()->assertSee('لوحتي');
    }
}
