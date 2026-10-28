<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrantPlatformRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_can_be_granted_and_revoked_by_email(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->actingAs($user)->get('/admin')->assertForbidden();

        $this->artisan('platform:grant-role', ['email' => 'Owner@Example.com', 'role' => 'admin'])->assertSuccessful();
        $this->assertSame('admin', $user->fresh()->platform_role);
        $this->actingAs($user->fresh())->get('/admin')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform_role.changed', 'user_id' => $user->id]);

        $this->artisan('platform:grant-role', ['email' => 'owner@example.com', 'role' => 'none'])->assertSuccessful();
        $this->assertSame('member', $user->fresh()->platform_role);
    }

    public function test_unknown_email_or_role_fails_without_changes(): void
    {
        $this->artisan('platform:grant-role', ['email' => 'nobody@example.com'])->assertFailed();
        $user = User::factory()->create();
        $this->artisan('platform:grant-role', ['email' => $user->email, 'role' => 'god'])->assertFailed();
        $this->assertSame('member', $user->fresh()->platform_role);
    }
}
