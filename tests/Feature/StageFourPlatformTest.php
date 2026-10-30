<?php

namespace Tests\Feature;

use App\Domain\Academy\Models\Certificate;
use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\RealWorldCheckin;
use App\Domain\Academy\Models\ResourceTemplate;
use App\Domain\Academy\Services\ProgressService;
use App\Domain\Workspace\Models\Workspace;
use App\Mail\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class StageFourPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_certificate_verification_distinguishes_valid_revoked_and_missing_without_private_data(): void
    {
        $user = User::factory()->create(['name' => 'متعلم أثر']);
        $certificate = Certificate::create(['user_id' => $user->id, 'course_id' => Course::first()->id, 'verification_code' => (string) Str::uuid(), 'issued_at' => now()]);
        $url = '/certificates/'.$certificate->verification_code;
        $this->get($url)->assertOk()->assertSee('شهادة صالحة')->assertSee('متعلم أثر')->assertDontSee($user->email)->assertSee('noindex', false);
        $certificate->update(['revoked_at' => now()]);
        $this->get($url)->assertOk()->assertSee('شهادة ملغاة')->assertDontSee('شهادة صالحة');
        $this->get('/certificates/'.Str::uuid())->assertNotFound()->assertSee('لم نعثر على شهادة');
        $this->get('/certificates/invalid')->assertNotFound();
    }

    public function test_legacy_completion_cannot_bypass_saved_asset_and_questions(): void
    {
        $user = User::factory()->create();
        $lesson = Course::where('slug', 'marketing-foundations')->first()->lessons()->first();
        $this->actingAs($user)->post(route('app.lessons.complete', $lesson), ['deliverable' => 'done'])->assertSessionHasErrors('completion');
        $this->assertDatabaseMissing('activity_completions', ['user_id' => $user->id, 'lesson_id' => $lesson->id]);
    }

    public function test_certificate_requires_real_result_in_same_course_and_is_not_reissued_after_revocation(): void
    {
        $user = User::factory()->create();
        $space = Workspace::create(['name' => 'مساحة الشهادة', 'slug' => 'certificate-space']);
        $space->members()->attach($user, ['role' => 'owner']);
        DB::table('subscriptions')->insert(['workspace_id' => $space->id, 'plan' => 'pro', 'status' => 'active', 'ends_at' => now()->addMonth(), 'created_at' => now(), 'updated_at' => now()]);
        $course = Course::where('slug', 'marketing-foundations')->first();
        foreach ($course->lessons as $lesson) {
            DB::table('activity_completions')->insert(['user_id' => $user->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);
        }
        $service = app(ProgressService::class);
        $this->assertFalse($service->maybeIssueCertificate($user, $course));
        $checkin = RealWorldCheckin::create(['user_id' => $user->id, 'lesson_id' => $course->lessons->first()->id, 'mode' => 'practice', 'action' => 'تدريب', 'result' => ['leads' => 3], 'recorded_at' => now()]);
        $this->assertFalse($service->maybeIssueCertificate($user, $course));
        $checkin->update(['mode' => 'commit']);
        $checkin->update(['result' => ['note' => null]]);
        $this->assertFalse($service->maybeIssueCertificate($user, $course));
        $checkin->update(['result' => ['leads' => 0]]);
        $this->assertTrue($service->maybeIssueCertificate($user, $course));
        $service->maybeIssueCertificate($user, $course);
        $this->assertSame(1, Certificate::where('user_id', $user->id)->count());
        $cert = Certificate::where('user_id', $user->id)->first();
        $cert->update(['revoked_at' => now()]);
        $this->assertFalse($service->maybeIssueCertificate($user, $course));
        $this->assertNotNull($cert->fresh()->revoked_at);
        $this->actingAs($user)->get('/app/progress')->assertOk()->assertSee('/certificates/'.$cert->verification_code, false)->assertSee('ملغاة');
    }

    public function test_resource_preview_filters_downloads_and_publication(): void
    {
        $template = ResourceTemplate::where('slug', 'value-card-template')->first();
        $this->get('/resources?topic=marketing-foundations&format=pdf')->assertOk()->assertSee($template->name);
        $this->get('/resources?topic=does-not-exist')->assertOk()->assertDontSee($template->name)->assertSee('لا توجد قوالب');
        $this->get('/resources/'.$template->slug)->assertOk()->assertSee($template->name)->assertSee('معاينة القالب')->assertSee('احفظ في مشروعي');
        $this->get('/resources/'.$template->slug.'/download')->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        $this->get('/resources/'.$template->slug.'/download?format=pdf')->assertOk()->assertSee('احفظ كـ PDF');
        $csv = $this->get('/resources/'.$template->slug.'/download?format=csv')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('الحقل', $csv->streamedContent());
        $template->update(['is_published' => false]);
        $this->get('/resources/'.$template->slug)->assertNotFound();
        $this->get('/resources/'.$template->slug.'/download')->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee('/resources/'.$template->slug, false);
    }

    public function test_empty_real_result_is_rejected_but_zero_is_recorded(): void
    {
        $user = User::factory()->create();
        $course = Course::where('slug', 'marketing-foundations')->first();
        $lesson = $course->lessons()->where('slug', 'first-channel-and-budget')->first();
        $checkin = RealWorldCheckin::create(['user_id' => $user->id, 'lesson_id' => $lesson->id, 'mode' => 'commit', 'action' => 'تجربة']);
        $url = route('academy.lesson.result', [$course, $lesson]);
        $this->actingAs($user)->post($url, [])->assertSessionHasErrors('result');
        $this->assertNull($checkin->fresh()->recorded_at);
        $this->actingAs($user)->post($url, ['result' => ['leads' => 0]])->assertSessionHasNoErrors();
        $this->assertNotNull($checkin->fresh()->recorded_at);
        $this->assertEquals(0, $checkin->fresh()->result['leads']);
    }

    public function test_resource_save_checks_workspace_role_and_does_not_complete_a_lesson(): void
    {
        $user = User::factory()->create();
        $space = Workspace::create(['name' => 'مساحتي', 'slug' => 'mine']);
        $space->members()->attach($user, ['role' => 'owner']);
        $project = $space->projects()->create(['name' => 'مشروع', 'slug' => 'project']);
        $template = ResourceTemplate::where('slug', 'value-card-template')->first();
        $url = '/resources/'.$template->slug.'/save';
        $this->post($url, ['project_id' => $project->id])->assertRedirect('/login');
        $this->actingAs($user)->post($url, ['project_id' => $project->id])->assertRedirect();
        $this->assertDatabaseHas('tool_outputs', ['user_id' => $user->id, 'project_id' => $project->id, 'output_type' => 'resource_'.$template->id, 'lesson_id' => null]);
        $this->assertDatabaseMissing('activity_completions', ['user_id' => $user->id]);
        $space->members()->updateExistingPivot($user->id, ['role' => 'viewer']);
        $this->actingAs($user)->post($url, ['project_id' => $project->id])->assertForbidden();
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post($url, ['project_id' => $project->id])->assertForbidden();
    }

    public function test_contact_validates_queues_to_configured_support_and_ignores_honeypot(): void
    {
        Mail::fake();
        config(['platform.support_email' => 'support@example.test']);
        $this->post('/contact', ['name' => '', 'email' => 'bad', 'message' => ''])->assertSessionHasErrors(['name', 'email', 'message']);
        $data = ['name' => 'زائر', 'email' => 'visitor@example.test', 'message' => 'أرغب في معرفة طريقة استخدام الموارد.'];
        $this->post('/contact', $data + ['website' => 'spam'])->assertRedirect('/contact');
        Mail::assertNothingQueued();
        $this->post('/contact', $data)->assertRedirect('/contact')->assertSessionHas('status');
        Mail::assertQueued(ContactMessage::class, fn ($mail) => $mail->hasTo('support@example.test') && $mail->senderEmail === 'visitor@example.test');
    }

    public function test_information_pages_and_pricing_use_active_plan_values(): void
    {
        DB::table('plans')->where('slug', 'pro')->update(['price' => 1700, 'credits' => 150]);
        $this->get('/pricing')->assertOk()->assertSee('17')->assertSee('150')->assertSee('20')->assertSee('/app/billing', false);
        foreach (['about', 'pricing', 'contact', 'privacy', 'terms'] as $path) {
            $this->get('/'.$path)->assertOk()->assertSee('rel="canonical"', false)->assertSee('BreadcrumbList', false);
            $this->get('/sitemap.xml')->assertSee('/'.$path, false);
        }
        $this->get('/a-page-that-does-not-exist')->assertNotFound()->assertSee('الرابط لا يقابل صفحة');
    }

    public function test_authentication_pages_use_shared_identity_and_arabic_labels(): void
    {
        foreach (['login', 'register', 'forgot-password', 'reset-password/test-token'] as $path) {
            $this->get('/'.$path)->assertOk()->assertSee('data-auth-layout', false)->assertSee('تعلّم. طبّق. قِس الأثر.')->assertSee('athar-theme', false)->assertSee('<h1>', false);
        }
    }
}
