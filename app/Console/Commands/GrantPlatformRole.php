<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Assign a platform role to an existing user. Roles gate /admin:
 * editor, operations, finance, admin, super_admin; "none" resets to the default member role.
 */
class GrantPlatformRole extends Command
{
    protected $signature = 'platform:grant-role {email} {role=admin}';

    protected $description = 'Set the platform role of a user by email (editor|operations|finance|admin|super_admin|none)';

    public const ROLES = ['editor', 'operations', 'finance', 'admin', 'super_admin', 'none'];

    public function handle(): int
    {
        $role = strtolower((string) $this->argument('role'));
        if (! in_array($role, self::ROLES, true)) {
            $this->error('Role must be one of: '.implode(', ', self::ROLES));

            return self::FAILURE;
        }
        $user = User::query()->where('email', strtolower(trim((string) $this->argument('email'))))->first();
        if (! $user) {
            $this->error('No user with that email. Register on the site first, then re-run.');

            return self::FAILURE;
        }

        $previous = $user->platform_role;
        $user->forceFill(['platform_role' => $role === 'none' ? 'member' : $role])->save();
        DB::table('audit_logs')->insert([
            'user_id' => $user->id, 'action' => 'platform_role.changed', 'subject_type' => 'user', 'subject_id' => (string) $user->id,
            'context' => json_encode(['from' => $previous, 'to' => $user->platform_role, 'via' => 'console']),
            'request_id' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->info("{$user->email}: ".($previous ?: 'none').' -> '.($user->platform_role ?: 'none'));

        return self::SUCCESS;
    }
}
