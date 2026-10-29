<?php

namespace Tests\Feature;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Academy\Models\RealWorldCheckin;
use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use App\Notifications\RealWorldCheckinReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LessonFlowTest extends TestCase
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

    private function member(array $profile = []): User
    {
        $user = User::factory()->create(['audience' => 'owner']);
        $workspace = Workspace::query()->create(['name' => 'مساحة', 'slug' => 'space-'.$user->id]);
        $workspace->members()->attach($user, ['role' => 'owner']);
        $workspace->projects()->create(['name' => 'متجر نَدى', 'slug' => 'nada', 'profile' => $profile]);
        app(CreditLedger::class)->record($workspace, 'grant:'.$user->id, 'grant', 20, 'رصيد');

        return $user;
    }

    private function profile(): array
    {
        return ['offer' => 'منتجات عناية طبيعية للنساء في الرياض', 'sector' => 'beauty', 'city' => 'الرياض', 'budget' => 3000, 'channel' => 'instagram', 'goal' => 'leads', 'goal_number' => 60, 'audience' => 'نساء 25–40 ذوات بشرة حساسة'];
    }

    public function test_academy_lists_two_programs_and_twelve_published_courses(): void
    {
        $page = $this->get('/academy')->assertOk()->assertSee('برنامج أصحاب المشاريع')->assertSee('برنامج المسوّقين')->assertSee('"@type":"ItemList"', false)->assertSee('ثمانية أجزاء في كل درس');
        foreach (['أساسيات التسويق وفهم السوق', 'الجمهور والشخصيات', 'بناء خطة التسويق المتكاملة'] as $title) {
            $page->assertSee($title);
        }
        $this->assertSame(12, Course::query()->count());
        $this->assertSame(12, Course::query()->where('is_published', true)->count());
        $page->assertSee('href="'.route('academy.show', 'marketing-foundations').'"', false)->assertSee('/academy/paid-ads', false);
        $this->get('/academy/paid-ads')->assertOk();
        $this->get('/sitemap.xml')->assertOk()->assertSee('/academy/paid-ads', false);
        Course::query()->where('slug', 'paid-ads')->update(['is_published' => false]);
        $this->get('/academy/paid-ads')->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/academy/paid-ads', false);
    }

    public function test_course_page_lists_six_full_lessons_with_assets_and_templates(): void
    {
        $page = $this->get('/academy/marketing-foundations')->assertOk()->assertSee('"@type":"Course"', false)->assertSee('"@type":"BreadcrumbList"', false)->assertSee('ما ستخرج به')->assertSee('ابدأ الدرس الأول');
        $this->assertSame(6, $this->course->lessons()->count());
        foreach ($this->course->lessons as $lesson) {
            $page->assertSee($lesson->title)->assertSee($lesson->outcome_title);
        }
        $page->assertSee('قوالب المسار')->assertSee('/download', false)->assertSee('قبل هذا المسار')->assertSee('بعده');
        $this->get(route('resources.download', 'value-card-template'))->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')->assertSee('بطاقة القيمة');
    }

    public function test_every_lesson_has_the_full_form_content(): void
    {
        foreach ($this->course->lessons as $lesson) {
            $parts = $lesson->partCompleteness();
            $this->assertSame([], array_keys(array_filter($parts, fn ($ok) => ! $ok)), $lesson->slug.' missing parts');
            $this->assertGreaterThanOrEqual(600, str_word_count_utf8($lesson->concept_body), $lesson->slug.' concept words');
            $this->assertLessThanOrEqual(1000, str_word_count_utf8($lesson->concept_body), $lesson->slug.' concept words');
            $this->assertNotEmpty($lesson->market_example);
            $this->assertSame(3, $lesson->questions()->where('is_opening', false)->count());
            $this->assertSame(1, $lesson->questions()->where('is_opening', true)->count());
            foreach ($lesson->questions()->where('is_opening', false)->get() as $q) {
                $this->assertSame(1, collect($q->options)->filter(fn ($o) => ! empty($o['is_best']))->count(), $lesson->slug.' best option');
                foreach ($q->options as $o) {
                    $this->assertNotEmpty($o['feedback']);
                }
            }
            $this->assertNotEmpty($lesson->template->body_markdown);
            $this->assertNotEmpty($lesson->whatif_rules['variable']);
        }
    }

    public function test_visitor_sees_the_general_situation_all_eight_parts_and_generates_an_asset(): void
    {
        $url = route('academy.lesson', [$this->course, $this->lesson]);
        $page = $this->get($url)->assertOk()->assertSee('"@type":"LearningResource"', false)->assertSee('الموقف التدريبي العام')->assertSee('نَدى')->assertSee('قرارك الأول');
        foreach (['part-opening', 'part-goal', 'part-concept', 'part-situation', 'part-guided', 'part-lab', 'part-questions', 'part-template', 'part-apply'] as $id) {
            $page->assertSee('id="'.$id.'"', false);
        }
        $page->assertSee('ما يدخل من الدروس السابقة')->assertSee('غير محفوظ بعد')->assertSee('مثال من السوق السعودي')->assertSee('الخطأ الشائع')
            ->assertSee('عميق')->assertSee('سريع · 8 د')->assertSee('هل غيّرت رأيك؟')->assertSee('لا أملك حملة الآن')->assertDontSee('اسأل عن هذا الدرس')
            ->assertSee('أبلغ المحرر')
            // Markdown renders headings and keeps UTF-8 intact (\R without /u once split inside the letter «م»).
            ->assertSee('<h3>قناة واحدة أولاً</h3>', false)->assertDontSee("\u{FFFD}");

        $this->post($url.'/lab', ['situation' => 'general', 'channel' => 'instagram', 'why_channel' => 'شريحتي هناك', 'margin' => 120, 'budget' => 3000, 'goal_number' => 60])
            ->assertRedirect()->assertSessionHas('status');
        $after = $this->get($url)->assertOk()->assertSee('id="asset"', false)->assertSee('خطة القناة الأولى')->assertSee('75 طلباً')->assertSee('ضمن الهدف')->assertSee('ماذا لو؟')->assertSee('حفظ في مشروعي (بحساب)');
        $after->assertSee('تكلفة الطلب المقبولة')->assertSee('36 ريال');

        $this->getJson($url.'/whatif?value=1200&situation=general')->assertOk()->assertJsonPath('verdict', 'outside')->assertJsonPath('outputs.0.key', 'expected_leads')->assertJsonPath('outputs.0.value', 30);
        $this->getJson($url.'/whatif?value=2000&situation=general')->assertOk()->assertJsonPath('verdict', 'edge');

        $this->post($url.'/lab', ['situation' => 'general', 'channel' => 'instagram', 'why_channel' => '', 'margin' => '', 'budget' => 3000, 'goal_number' => 60])->assertSessionHasErrors('lab');
        $this->post($url.'/decision', ['stage' => 'first', 'choice' => 1])->assertRedirect();
        $this->post($url.'/report', ['part' => 'lab', 'reason' => 'not_my_case', 'note' => 'أرقامي مختلفة'])->assertRedirect();
        $this->assertDatabaseHas('content_reports', ['lesson_id' => $this->lesson->id, 'part' => 'lab', 'reason' => 'not_my_case']);
        $this->assertDatabaseHas('learner_events', ['event' => 'lesson.lab_generated', 'lesson_id' => $this->lesson->id]);
    }

    public function test_member_lesson_is_built_on_their_project_and_completes_with_asset_and_questions(): void
    {
        Notification::fake();
        $user = $this->member($this->profile());
        $url = route('academy.lesson', [$this->course, $this->lesson]);

        $page = $this->actingAs($user)->get($url)->assertOk();
        $page->assertSee('موقفك')->assertSee('متجر نَدى')->assertSee('3,000 ريال')->assertSee('إنستغرام')->assertSee('من المشروع')->assertSee('value="3000"', false)->assertSee('value="60"', false)->assertSee('مشروعك');

        $this->actingAs($user)->post($url.'/lab', ['situation' => 'project', 'channel' => 'instagram', 'why_channel' => 'شريحتي تسأل هناك', 'margin' => 120, 'budget' => 3000, 'goal_number' => 60])->assertRedirect();
        $generated = session('lab.asset.'.$this->lesson->id);
        $this->assertNotEmpty($generated['markdown']);

        $this->actingAs($user)->post($url.'/save', ['title' => 'خطة القناة الأولى', 'type' => 'channel_plan', 'markdown' => $generated['markdown']])->assertRedirect()->assertSessionHas('status');
        $asset = ToolOutput::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('channel_plan', $asset->output_type);
        $this->assertSame($this->lesson->id, $asset->lesson_id);
        $this->assertSame('within', $asset->quality_report['verdict']);
        $this->assertDatabaseMissing('activity_completions', ['user_id' => $user->id, 'lesson_id' => $this->lesson->id]);

        foreach ($this->lesson->questions()->where('is_opening', false)->get() as $q) {
            $this->actingAs($user)->postJson('/events', ['event' => 'question.answered', 'meta' => ['question_id' => $q->id, 'choice' => $q->bestIndex(), 'part' => 'questions']])->assertOk();
        }
        $this->assertDatabaseHas('activity_completions', ['user_id' => $user->id, 'lesson_id' => $this->lesson->id]);
        $this->assertDatabaseHas('learner_events', ['user_id' => $user->id, 'event' => 'lesson.completed']);

        $this->actingAs($user)->post($url.'/decision', ['stage' => 'first', 'choice' => 0])->assertRedirect();
        $this->actingAs($user)->post($url.'/decision', ['stage' => 'final', 'choice' => 1, 'note' => 'فهمت قاعدة القرار'])->assertRedirect();
        $this->assertDatabaseHas('decision_journal', ['user_id' => $user->id, 'lesson_id' => $this->lesson->id, 'first_choice' => 0, 'final_choice' => 1]);

        $this->actingAs($user)->post($url.'/checkin', ['mode' => 'commit', 'action' => 'أشغّل إنستغرام 7 أيام بـ100 ريال يومياً', 'due_at' => now()->addDays(7)->format('Y-m-d')])->assertRedirect();
        $checkin = RealWorldCheckin::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNull($checkin->recorded_at);
        $this->actingAs($user)->get($url)->assertOk()->assertSee('سجّل النتيجة الحقيقية')->assertSee('مكتمل ✓')->assertSee('✓ محفوظ');

        RealWorldCheckin::query()->whereKey($checkin->id)->update(['due_at' => now()->subHour()]);
        $this->artisan('platform:send-checkin-reminders')->assertSuccessful();
        Notification::assertSentTo($user, RealWorldCheckinReminder::class);
        $this->assertNotNull($checkin->fresh()->reminder_sent_at);

        $this->actingAs($user)->post($url.'/result', ['result' => ['spent' => 700, 'leads' => 16, 'actual_cpl' => 44], 'note' => 'أفضل من المتوقع'])->assertRedirect();
        $this->assertSame('16', (string) $checkin->fresh()->result['leads']);
        $this->assertNotNull($checkin->fresh()->recorded_at);

        // Asset continuity: the plan lesson shows the saved channel plan as incoming.
        $plan = $this->course->lessons()->where('slug', 'first-marketing-plan')->firstOrFail();
        $this->actingAs($user)->get(route('academy.lesson', [$this->course, $plan]))->assertOk()->assertSee('✓ محفوظ: خطة القناة الأولى');
    }

    public function test_completing_all_lessons_with_a_real_result_issues_the_course_certificate(): void
    {
        $user = $this->member($this->profile());
        foreach ($this->course->lessons as $lesson) {
            $url = route('academy.lesson', [$this->course, $lesson]);
            $this->actingAs($user)->post($url.'/save', ['title' => $lesson->outcome_title, 'type' => $lesson->outcome_type, 'markdown' => '# '.$lesson->outcome_title])->assertRedirect();
            foreach ($lesson->questions()->where('is_opening', false)->get() as $q) {
                $this->actingAs($user)->postJson('/events', ['event' => 'question.answered', 'meta' => ['question_id' => $q->id, 'choice' => $q->bestIndex()]])->assertOk();
            }
        }
        $this->assertSame(6, DB::table('activity_completions')->where('user_id', $user->id)->count());
        $this->assertDatabaseMissing('certificates', ['user_id' => $user->id]);

        $url = route('academy.lesson', [$this->course, $this->lesson]);
        $this->actingAs($user)->post($url.'/checkin', ['mode' => 'commit', 'action' => 'جولة'])->assertRedirect();
        $this->actingAs($user)->post($url.'/result', ['result' => ['spent' => 500, 'leads' => 10, 'actual_cpl' => 50]])->assertRedirect();
        $this->assertDatabaseHas('certificates', ['user_id' => $user->id, 'course_id' => $this->course->id]);
        $this->actingAs($user)->get('/app/progress')->assertOk()->assertSee('رمز التحقق')->assertSee('مكتمل ✓');
    }

    public function test_coach_is_hidden_without_a_key_and_answers_for_one_credit_with_a_key(): void
    {
        $user = $this->member($this->profile());
        $url = route('academy.lesson', [$this->course, $this->lesson]);
        $this->actingAs($user)->get($url)->assertOk()->assertDontSee('اسأل عن هذا الدرس');
        $this->actingAs($user)->post($url.'/coach', ['question' => 'هل قناتي مناسبة؟'])->assertNotFound();

        config(['services.openai.key' => 'test-key']);
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => 'قناتك مناسبة لأن شريحتك هناك؛ ثبّت تكلفة طلب مقبولة.']]]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 10]])]);
        $this->actingAs($user)->get($url)->assertOk()->assertSee('اسأل عن هذا الدرس');
        $this->actingAs($user)->post($url.'/coach', ['question' => 'هل قناتي مناسبة؟', 'compare' => 1])->assertRedirect();
        $this->actingAs($user)->get($url)->assertOk()->assertSee('قناتك مناسبة لأن شريحتك هناك');
        $this->assertSame(19, (int) DB::table('credit_entries')->where('workspace_id', $user->workspaces()->first()->id)->sum('amount'));
    }

    public function test_events_endpoint_rejects_unknown_events(): void
    {
        $this->postJson('/events', ['event' => 'hack.me'])->assertStatus(422);
        $this->postJson('/events', ['event' => 'lesson.part_reached', 'meta' => ['lesson_id' => $this->lesson->id, 'part' => 'concept']])->assertOk();
        $this->assertDatabaseHas('learner_events', ['event' => 'lesson.part_reached', 'part' => 'concept', 'lesson_id' => $this->lesson->id]);
    }

    public function test_legacy_completion_route_requires_the_same_learning_evidence(): void
    {
        $user = $this->member();
        $this->actingAs($user)->post(route('app.lessons.complete', $this->lesson), ['deliverable' => 'x'])->assertRedirect()->assertSessionHasErrors('completion');
        $this->assertDatabaseMissing('activity_completions', ['user_id' => $user->id, 'lesson_id' => $this->lesson->id]);
    }
}
