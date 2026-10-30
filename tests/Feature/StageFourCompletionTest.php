<?php

namespace Tests\Feature;

use App\Domain\Academy\Models\Certificate;
use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Academy\Models\RealWorldCheckin;
use App\Domain\Academy\Services\CertificateIssuer;
use App\Domain\Academy\Services\ProgressService;
use App\Domain\Assessment\Models\AssessmentRule;
use App\Domain\Assessment\Services\AssessmentEngine;
use App\Domain\Assessment\Services\Catalog;
use App\Domain\Lab\Services\LabCatalog;
use App\Domain\Support\Models\ContactMessage;
use App\Domain\Workspace\Models\Workspace;
use App\Events\CourseCompleted;
use App\Events\SubscriptionActivated;
use App\Listeners\IssueCertificate;
use App\Listeners\IssuePendingCertificates;
use App\Mail\ContactMessage as ContactMessageMail;
use App\Models\User;
use Database\Seeders\AcademySeeder;
use Database\Seeders\StageFourCompletionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Stage 4 completion: certificate event + plan gate, contact log, routing for all twelve paths, tool per path, error pages. */
class StageFourCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** @return array{0:User,1:Workspace} */
    private function member(): array
    {
        $user = User::factory()->create(['audience' => 'owner']);
        $space = Workspace::create(['name' => 'مساحة '.$user->id, 'slug' => 'space-'.$user->id]);
        $space->members()->attach($user, ['role' => 'owner']);

        return [$user, $space];
    }

    private function earn(User $user, Course $course): void
    {
        foreach ($course->lessons as $lesson) {
            DB::table('activity_completions')->insert(['user_id' => $user->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);
        }
        RealWorldCheckin::create(['user_id' => $user->id, 'lesson_id' => $course->lessons->first()->id, 'mode' => 'commit', 'action' => 'تجربة', 'result' => ['leads' => 4], 'recorded_at' => now()]);
    }

    public function test_course_completion_dispatches_an_event_handled_by_the_certificate_listener(): void
    {
        Event::fake([CourseCompleted::class]);
        [$user] = $this->member();
        $course = Course::where('slug', 'marketing-foundations')->first();
        $this->assertFalse(app(ProgressService::class)->maybeIssueCertificate($user, $course));
        Event::assertNotDispatched(CourseCompleted::class);
        $this->earn($user, $course);
        app(ProgressService::class)->maybeIssueCertificate($user, $course);
        Event::assertDispatched(CourseCompleted::class, fn (CourseCompleted $e) => $e->user->is($user) && $e->course->is($course));
        Event::assertListening(CourseCompleted::class, IssueCertificate::class);
        Event::assertListening(SubscriptionActivated::class, IssuePendingCertificates::class);
    }

    public function test_certificates_are_gated_to_the_paid_plan_and_released_on_upgrade(): void
    {
        [$user, $space] = $this->member();
        $course = Course::where('slug', 'marketing-foundations')->first();
        $this->earn($user, $course);
        $this->assertFalse(app(ProgressService::class)->maybeIssueCertificate($user, $course));
        $this->assertDatabaseMissing('certificates', ['user_id' => $user->id]);
        $this->assertTrue(app(CertificateIssuer::class)->pendingPlan($user, $course));
        $this->actingAs($user)->get('/app/progress')->assertOk()->assertSee('شهادة مستحقة')->assertSee('/app/billing', false)->assertSee('data-pending-certificates', false);

        // An expired subscription does not open the gate.
        DB::table('subscriptions')->insert(['workspace_id' => $space->id, 'plan' => 'pro', 'status' => 'active', 'ends_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now()]);
        $this->assertFalse(app(CertificateIssuer::class)->hasPaidPlan($user));
        DB::table('subscriptions')->where('workspace_id', $space->id)->update(['ends_at' => now()->addMonth()]);
        $this->assertTrue(app(CertificateIssuer::class)->hasPaidPlan($user));

        SubscriptionActivated::dispatch($space, 'pro');
        $this->assertDatabaseHas('certificates', ['user_id' => $user->id, 'course_id' => $course->id]);
        $this->assertSame(1, Certificate::where('user_id', $user->id)->count());
        SubscriptionActivated::dispatch($space, 'pro');
        $this->assertSame(1, Certificate::where('user_id', $user->id)->count());
        $this->actingAs($user)->get('/app/progress')->assertOk()->assertDontSee('شهادة مستحقة')->assertSee('عرض الشهادة والتحقق');
        $code = Certificate::where('user_id', $user->id)->value('verification_code');
        $this->get('/certificates/'.$code)->assertOk()->assertSee('شهادة صالحة')->assertSee($course->title);
    }

    public function test_pricing_states_certificates_belong_to_the_professional_plan(): void
    {
        $this->get('/pricing')->assertOk()->assertSee('شهادة إكمال المسار')->assertSee('مزايا الخطة الاحترافية');
    }

    public function test_contact_messages_are_recorded_with_status_and_shown_to_staff(): void
    {
        Mail::fake();
        $data = ['name' => 'سارة', 'email' => 'Sara@Example.test', 'message' => 'أريد معرفة كيف أبدأ بمسار الإعلانات المدفوعة.'];
        $this->post('/contact', $data)->assertRedirect('/contact');
        $this->post('/contact', $data + ['website' => 'http://spam'])->assertRedirect('/contact');
        Mail::assertQueued(ContactMessageMail::class, 1);
        $this->assertDatabaseHas('contact_messages', ['email' => 'sara@example.test', 'status' => 'new', 'name' => 'سارة']);
        $this->assertDatabaseHas('contact_messages', ['email' => 'sara@example.test', 'status' => 'spam']);
        $this->assertSame(2, ContactMessage::count());

        $this->actingAs(User::factory()->create())->get('/admin/contact-messages')->assertForbidden();
        $this->actingAs(User::factory()->create(['platform_role' => 'editor']))->get('/admin/contact-messages')->assertForbidden();
        $staff = User::factory()->create(['platform_role' => 'operations']);
        $this->actingAs($staff)->get('/admin/contact-messages')->assertOk()->assertSee('رسائل الاتصال')->assertSee('سارة')->assertSee('مسار الإعلانات المدفوعة')->assertDontSee('http://spam');
        $this->actingAs($staff)->get('/admin/contact-messages?status=spam')->assertOk()->assertSee('سخام');
        $this->actingAs($staff)->get('/admin/contact-messages?q=الإعلانات')->assertOk()->assertSee('سارة');
        $message = ContactMessage::where('status', 'new')->first();
        $this->actingAs($staff)->patch('/admin/contact-messages/'.$message->id, ['status' => 'handled'])->assertRedirect();
        $this->assertSame('handled', $message->fresh()->status);
        $this->assertSame($staff->id, $message->fresh()->handled_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'contact_message.status_changed', 'subject_id' => (string) $message->id, 'user_id' => $staff->id]);
    }

    public function test_routing_rules_cover_every_audience_level_dimension_and_all_twelve_paths(): void
    {
        $rules = AssessmentRule::query()->with('lesson.course')->get();
        $this->assertCount(2 * count(Catalog::LEVELS) * count(Catalog::DIMENSIONS), $rules);
        $referenced = collect();
        foreach ($rules as $rule) {
            $this->assertNotNull($rule->lesson, "rule {$rule->audience}/{$rule->level}/{$rule->weakest_dimension} has no start lesson");
            $this->assertTrue($rule->lesson->isPublished() && $rule->lesson->course->is_published);
            $this->assertCount(3, $rule->priorities);
            $referenced->push($rule->lesson->course->slug);
            foreach ($rule->priorities as $priority) {
                $this->assertNotEmpty($priority['title']);
                $this->assertNotEmpty($priority['reason']);
                if (! empty($priority['lesson'])) {
                    $lesson = Lesson::where('slug', $priority['lesson'])->with('course')->first();
                    $this->assertNotNull($lesson, 'missing lesson '.$priority['lesson']);
                    $referenced->push($lesson->course->slug);
                } else {
                    $this->assertTrue(Course::where('slug', $priority['course'])->where('is_published', true)->exists(), 'missing course '.($priority['course'] ?? '?'));
                    $referenced->push($priority['course']);
                }
            }
            if ($rule->audience === 'owner') {
                $this->assertContains($rule->lesson->course->slug, AcademySeeder::OWNER_ORDER, 'owners are routed inside their program');
            }
        }
        $all = collect(AcademySeeder::COURSES)->pluck(0)->sort()->values();
        $this->assertEquals($all->all(), $referenced->unique()->sort()->values()->all());
        // Beginners start in the foundations course; advanced learners never do.
        $this->assertTrue($rules->where('level', 'beginner')->every(fn ($r) => $r->lesson->course->slug === 'marketing-foundations'));
        $this->assertTrue($rules->where('level', 'advanced')->every(fn ($r) => $r->lesson->course->slug !== 'marketing-foundations'));
    }

    public function test_routing_seeder_is_idempotent_and_rendered_in_the_diagnosis(): void
    {
        $before = AssessmentRule::count();
        $this->seed(StageFourCompletionSeeder::class);
        $this->assertSame($before, AssessmentRule::count());
        $rule = AssessmentRule::where('audience', 'marketer')->where('level', 'advanced')->where('weakest_dimension', 'channel')->first();
        $this->assertSame('ads-objective-economics', $rule->lesson->slug);
        $priorities = app(AssessmentEngine::class)->priorities($rule, ['offer' => 'اشتراك مساحة عمل']);
        $this->assertStringContainsString('اشتراك مساحة عمل', $priorities[0]['title']);
        $this->assertStringContainsString('/academy/paid-ads/ads-objective-economics', $priorities[0]['link']);
        $this->assertStringContainsString('/academy/seo-content-marketing', $priorities[1]['link']);
    }

    public function test_every_path_lesson_links_a_published_lab_tool(): void
    {
        foreach (Course::where('is_published', true)->with('lessons')->get() as $course) {
            $this->assertArrayHasKey($course->slug, LabCatalog::COURSE_TOOLS);
            $lesson = $course->lessons->first();
            $response = $this->get(route('academy.lesson', [$course, $lesson]))->assertOk();
            $html = $response->getContent();
            $start = mb_strpos($html, 'id="part-apply"');
            $this->assertNotFalse($start, $course->slug);
            $this->assertStringContainsString('المختبر', mb_substr($html, $start), 'lesson of '.$course->slug.' has no tool card');
        }
        $this->get('/academy/paid-ads/ads-objective-economics')->assertOk()->assertSee('/tools/simulator', false);
        $this->get('/academy/social-media/social-channel-role')->assertOk()->assertSee('/tools/content-calendar', false);
    }

    public function test_error_pages_follow_the_blueprint_and_gone_redirects_return_410(): void
    {
        $this->get('/no-such-page-anywhere')->assertNotFound()->assertSee('الرابط لا يقابل صفحة')->assertSee('action="/search"', false)
            ->assertSee('href="/academy"', false)->assertSee('href="/tools"', false)->assertSee('href="/articles"', false)->assertSee('data-theme', false);
        DB::table('redirects')->insert(['from_path' => '/old-ebook', 'to_path' => '/', 'status_code' => 410, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->get('/old-ebook')->assertStatus(410)->assertSee('أُزيلت هذه الصفحة')->assertSee('data-theme', false);
    }

    public function test_discovery_files_list_the_stage_four_pages(): void
    {
        $this->get('/llms.txt')->assertOk()->assertSee('/certificates/{code}')->assertSee('/resources')->assertSee('/pricing');
        $sitemap = $this->get('/sitemap.xml')->assertOk();
        foreach (['/resources', '/about', '/pricing', '/contact', '/privacy', '/terms', '/academy/paid-ads', '/academy/paid-ads/ads-objective-economics'] as $path) {
            $sitemap->assertSee($path, false);
        }
    }
}
