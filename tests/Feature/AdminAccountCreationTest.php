<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 管理員帳號建立途徑防護。
 *
 * 公開的 POST /api/admin/register 曾允許任何人註冊管理員帳號（P0 漏洞，
 * 2026-06-11 稽核移除）。這些測試確保該端點不會被重新打開，
 * 且唯一合法途徑是主機端的 app:create-admin command。
 */
class AdminAccountCreationTest extends TestCase
{
    use RefreshDatabase;

    // ── 公開註冊端點必須保持關閉 ─────────────────────────────

    public function test_public_admin_register_endpoint_is_closed(): void
    {
        $response = $this->postJson('/api/admin/register', [
            'name'                  => 'Evil Admin',
            'email'                 => 'evil@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('users', ['email' => 'evil@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'evil@example.com', 'role' => 'admin']);
    }

    // ── artisan command 為唯一建立途徑 ───────────────────────

    public function test_create_admin_command_creates_admin_with_profile(): void
    {
        $this->artisan('app:create-admin', [
            'name'       => 'Ops Admin',
            'email'      => 'ops@example.com',
            '--password' => 'StrongPass99',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'ops@example.com',
            'role'  => 'admin',
        ]);

        $admin = User::where('email', 'ops@example.com')->first();
        $this->assertDatabaseHas('admin_profiles', ['user_id' => $admin->id]);
    }

    public function test_create_admin_command_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'exists@example.com']);

        $this->artisan('app:create-admin', [
            'name'       => 'Dup Admin',
            'email'      => 'exists@example.com',
            '--password' => 'StrongPass99',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', [
            'email' => 'exists@example.com',
            'role'  => 'admin',
        ]);
    }

    public function test_create_admin_command_rejects_weak_password(): void
    {
        $this->artisan('app:create-admin', [
            'name'       => 'Weak Admin',
            'email'      => 'weak@example.com',
            '--password' => 'short',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'weak@example.com']);
    }
}
