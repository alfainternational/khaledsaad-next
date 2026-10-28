<?php

namespace Database\Seeders;

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

    public const ADMIN_EMAIL = 'browser-admin@example.invalid';

    public const PASSWORD = 'BrowserPass!2026';

    public function run(): void
    {
        $member = User::query()->updateOrCreate(
            ['email' => self::MEMBER_EMAIL],
            ['name' => 'عضو اختبار المتصفح', 'email_verified_at' => now(), 'password' => Hash::make(self::PASSWORD)],
        );

        $workspace = Workspace::query()->firstOrCreate(
            ['slug' => 'browser-member-space'],
            ['name' => 'مساحة اختبار المتصفح'],
        );
        $workspace->members()->syncWithoutDetaching([$member->id => ['role' => 'owner']]);
        $workspace->projects()->firstOrCreate(
            ['slug' => 'browser-first-project'],
            ['name' => 'مشروع اختبار المتصفح'],
        );
        app(CreditLedger::class)->record($workspace, 'welcome:browser:'.$workspace->id, 'grant', 20, 'رصيد اختبار المتصفح');

        User::query()->updateOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'مدير اختبار المتصفح',
                'email_verified_at' => now(),
                'password' => Hash::make(self::PASSWORD),
                'platform_role' => 'admin',
            ],
        );
    }
}
