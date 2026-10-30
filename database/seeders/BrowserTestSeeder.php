<?php

namespace Database\Seeders;

use App\Domain\Academy\Models\Certificate;
use App\Domain\Academy\Models\Course;
use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic accounts for the Playwright browser suite (tests/browser).
 * Never run this seeder in production: the credentials are public test values.
 */
class BrowserTestSeeder extends Seeder
{
    public const MEMBER_EMAIL = 'browser-member@example.invalid';

    /** A second member so the desktop and mobile Playwright projects never share learner state. */
    public const MOBILE_MEMBER_EMAIL = 'browser-member-mobile@example.invalid';

    public const ADMIN_EMAIL = 'browser-admin@example.invalid';

    /** Stage-4 certificate journey accounts (one per Playwright project). */
    public const CERT_MEMBER_EMAIL = 'browser-cert@example.invalid';

    public const CERT_MOBILE_MEMBER_EMAIL = 'browser-cert-mobile@example.invalid';

    public const PASSWORD = 'BrowserPass!2026';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('BrowserTestSeeder is only for local test databases.');
        }
        foreach ([self::MEMBER_EMAIL => 'browser-member-space', self::MOBILE_MEMBER_EMAIL => 'browser-member-mobile-space'] as $email => $slug) {
            $member = User::query()->updateOrCreate(
                ['email' => $email],
                ['name' => 'عضو اختبار المتصفح', 'email_verified_at' => now(), 'password' => Hash::make(self::PASSWORD)],
            );

            $workspace = Workspace::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => 'مساحة اختبار المتصفح'],
            );
            $workspace->members()->syncWithoutDetaching([$member->id => ['role' => 'owner']]);
            $workspace->projects()->firstOrCreate(
                ['slug' => $slug.'-project'],
                ['name' => 'مشروع اختبار المتصفح'],
            );
            app(CreditLedger::class)->record($workspace, 'welcome:browser:'.$workspace->id, 'grant', 20, 'رصيد اختبار المتصفح');
            // Browser members are on the professional plan so the certificate journey (stage 4) can be exercised end to end.
            DB::table('subscriptions')->updateOrInsert(['workspace_id' => $workspace->id], [
                'plan_id' => DB::table('plans')->where('slug', 'pro')->value('id'), 'plan' => 'pro', 'status' => 'active', 'provider' => 'test',
                'ends_at' => now()->addYear(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        User::query()->updateOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'مدير اختبار المتصفح',
                'email_verified_at' => now(),
                'password' => Hash::make(self::PASSWORD),
                'platform_role' => 'admin',
            ],
        );
        // Certificate journey (stage 4): a professional-plan learner with five of six lessons of «الجمهور والشخصيات» already complete;
        // the browser test completes the first lesson, commits a real action, records its result and gets the certificate.
        $journeyCourse = Course::query()->where('slug', 'audience-and-personas')->with('lessons')->first();
        foreach ([self::CERT_MEMBER_EMAIL => 'browser-cert-space', self::CERT_MOBILE_MEMBER_EMAIL => 'browser-cert-mobile-space'] as $email => $slug) {
            $learner = User::query()->updateOrCreate(['email' => $email], ['name' => 'متعلمة الشهادة', 'email_verified_at' => now(), 'password' => Hash::make(self::PASSWORD), 'audience' => 'owner']);
            $workspace = Workspace::query()->firstOrCreate(['slug' => $slug], ['name' => 'مساحة رحلة الشهادة']);
            $workspace->members()->syncWithoutDetaching([$learner->id => ['role' => 'owner']]);
            $workspace->projects()->firstOrCreate(['slug' => $slug.'-project'], ['name' => 'مشروع رحلة الشهادة', 'profile' => ['offer' => 'عناية طبيعية بالبشرة', 'audience' => 'نساء 25–40 في الرياض', 'sector' => 'retail', 'city' => 'الرياض', 'budget' => 2000, 'channel' => 'instagram', 'goal' => 'sales', 'goal_number' => 40]]);
            DB::table('subscriptions')->updateOrInsert(['workspace_id' => $workspace->id], [
                'plan_id' => DB::table('plans')->where('slug', 'pro')->value('id'), 'plan' => 'pro', 'status' => 'active', 'provider' => 'test',
                'ends_at' => now()->addYear(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($journeyCourse) {
                foreach ($journeyCourse->lessons->skip(1) as $lesson) {
                    DB::table('activity_completions')->updateOrInsert(['user_id' => $learner->id, 'lesson_id' => $lesson->id], ['completed_at' => now()->subDay(), 'deliverable' => json_encode(['seed' => 'browser'])]);
                }
            }
        }

        $certificateUser = User::factory()->create(['name' => 'متعلم الشهادة التجريبية']);
        foreach (['11111111-1111-4111-8111-111111111111' => null, '22222222-2222-4222-8222-222222222222' => now()] as $code => $revoked) {
            Certificate::create([
                'user_id' => $certificateUser->id,
                'course_id' => Course::query()->where('slug', $revoked ? 'audience-and-personas' : 'marketing-foundations')->value('id'),
                'verification_code' => $code, 'issued_at' => now(), 'revoked_at' => $revoked,
            ]);
        }
    }
}
