<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    // ── helpers ─────────────────────────────────────────────

    private function createMember(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role'      => 'member',
            'is_active' => true,
            'password'  => Hash::make('password123'),
        ], $attributes));
    }

    private function createProvider(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role'      => 'provider',
            'is_active' => true,
            'password'  => Hash::make('password123'),
        ], $attributes));
    }

    private function createAdmin(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role'      => 'admin',
            'is_active' => true,
            'password'  => Hash::make('password123'),
        ], $attributes));
    }

    // ── member 註冊 ──────────────────────────────────────────

    public function test_member_register_success(): void
    {
        $response = $this->postJson('/api/member/register', [
            'name'                  => 'Test Member',
            'email'                 => 'newmember@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('status', true)
                 ->assertJsonStructure(['data' => ['user']]);

        $this->assertDatabaseHas('users', [
            'email' => 'newmember@example.com',
            'role'  => 'member',
        ]);
    }

    public function test_member_register_duplicate_email_returns_422(): void
    {
        $this->createMember(['email' => 'dup@example.com']);

        $response = $this->postJson('/api/member/register', [
            'name'                  => 'Another Member',
            'email'                 => 'dup@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    // ── member 登入／登出 ────────────────────────────────────

    public function test_member_login_success(): void
    {
        $this->createMember(['email' => 'member@example.com']);

        $response = $this->postJson('/api/member/login', [
            'email'    => 'member@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', true)
                 ->assertJsonPath('data.token_type', 'Bearer')
                 ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);
    }

    public function test_member_login_wrong_password_returns_401(): void
    {
        $this->createMember(['email' => 'member@example.com']);

        $response = $this->postJson('/api/member/login', [
            'email'    => 'member@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
                 ->assertJsonPath('status', false);
    }

    public function test_member_login_inactive_account_returns_403(): void
    {
        $this->createMember(['email' => 'inactive@example.com', 'is_active' => false]);

        $response = $this->postJson('/api/member/login', [
            'email'    => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
    }

    // 下面這類「跨角色」測試在防的是：provider/admin 帳號明明存在於 users 表，
    // 卻拿著正確密碼跑去敲 member 的登入端點——查詢必須有 role 過濾，
    // 否則系統可能誤判成「帳號密碼正確」而放行，讓使用者用錯誤的角色身分登入。
    // 回傳 401（而非更精確的「角色不對」訊息）是刻意的：跟「帳號不存在」用同樣的錯誤，
    // 才不會讓外部攻擊者藉由錯誤訊息差異去探測「這個 email 是不是某個角色的帳號」。
    public function test_provider_account_cannot_use_member_login(): void
    {
        $this->createProvider(['email' => 'cross@example.com']);

        $response = $this->postJson('/api/member/login', [
            'email'    => 'cross@example.com',
            'password' => 'password123',
        ]);

        // 查詢以 role 過濾，跨角色帳號視同不存在，回傳與「帳號不存在」相同的 401
        $response->assertStatus(401)
                 ->assertJsonPath('status', false);
    }

    public function test_member_logout_revokes_token(): void
    {
        $user    = $this->createMember();
        $token   = $user->createToken('auth_token')->plainTextToken;
        $tokenId = explode('|', $token, 2)[0];

        $this->withToken($token)->postJson('/api/member/logout')
             ->assertStatus(200);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);

        // RequestGuard 會在同一個測試內快取已解析的 user，須先 forgetGuards() 才能讓
        // 下一個請求重新解析（此時 token 已被刪除，應得 401）
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/member/profile')
             ->assertStatus(401);
    }

    // ── provider 註冊 ────────────────────────────────────────

    public function test_provider_register_success(): void
    {
        $response = $this->postJson('/api/provider/register', [
            'name'                  => 'Test Provider',
            'email'                 => 'newprovider@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('status', true)
                 ->assertJsonStructure(['data' => ['user']]);

        $this->assertDatabaseHas('users', [
            'email' => 'newprovider@example.com',
            'role'  => 'provider',
        ]);
    }

    public function test_provider_register_duplicate_email_returns_422(): void
    {
        $this->createProvider(['email' => 'dup-provider@example.com']);

        $response = $this->postJson('/api/provider/register', [
            'name'                  => 'Another Provider',
            'email'                 => 'dup-provider@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    // ── provider 登入／登出 ──────────────────────────────────

    public function test_provider_login_success(): void
    {
        $this->createProvider(['email' => 'provider@example.com']);

        $response = $this->postJson('/api/provider/login', [
            'email'    => 'provider@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', true)
                 ->assertJsonPath('data.token_type', 'Bearer')
                 ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);
    }

    public function test_provider_login_inactive_account_returns_403(): void
    {
        $this->createProvider(['email' => 'inactive-provider@example.com', 'is_active' => false]);

        $response = $this->postJson('/api/provider/login', [
            'email'    => 'inactive-provider@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
    }

    public function test_member_account_cannot_use_provider_login(): void
    {
        $this->createMember(['email' => 'cross2@example.com']);

        $response = $this->postJson('/api/provider/login', [
            'email'    => 'cross2@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
                 ->assertJsonPath('status', false);
    }

    public function test_provider_logout_revokes_token(): void
    {
        $user    = $this->createProvider();
        $token   = $user->createToken('auth_token')->plainTextToken;
        $tokenId = explode('|', $token, 2)[0];

        $this->withToken($token)->postJson('/api/provider/logout')
             ->assertStatus(200);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);

        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/provider/profile')
             ->assertStatus(401);
    }

    // admin 註冊端點已移除（P0 漏洞），帳號建立途徑見 AdminAccountCreationTest

    // ── admin 登入／登出 ─────────────────────────────────────

    public function test_admin_login_success(): void
    {
        $this->createAdmin(['email' => 'admin@example.com']);

        $response = $this->postJson('/api/admin/login', [
            'email'    => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', true)
                 ->assertJsonPath('data.token_type', 'Bearer')
                 ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);
    }

    public function test_member_cannot_use_admin_login(): void
    {
        $this->createMember(['email' => 'cross3@example.com']);

        $response = $this->postJson('/api/admin/login', [
            'email'    => 'cross3@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
                 ->assertJsonPath('status', false);
    }

    public function test_provider_cannot_use_admin_login(): void
    {
        $this->createProvider(['email' => 'cross4@example.com']);

        $response = $this->postJson('/api/admin/login', [
            'email'    => 'cross4@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
                 ->assertJsonPath('status', false);
    }

    public function test_admin_logout_revokes_token(): void
    {
        $user    = $this->createAdmin();
        $token   = $user->createToken('auth_token')->plainTextToken;
        $tokenId = explode('|', $token, 2)[0];

        $this->withToken($token)->postJson('/api/admin/logout')
             ->assertStatus(200);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);

        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/admin/profile')
             ->assertStatus(401);
    }
}
