<?php

namespace Database\Seeders;

use App\Domain\Academy\Models\Certificate;
use App\Domain\Academy\Models\Course;
use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Database\Seeder;
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
