<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class TokenRefreshTest extends TestCase
{
    use RefreshDatabase;

    // ── helpers ─────────────────────────────────────────────

    private function memberWithToken(): array
    {
        $user  = User::factory()->create(['role' => 'member', 'is_active' => true]);
        $token = $user->createToken('auth_token')->plainTextToken;
        return [$user, $token];
    }

    private function providerWithToken(): array
    {
        $user  = User::factory()->create(['role' => 'provider', 'is_active' => true]);
        $token = $user->createToken('auth_token')->plainTextToken;
        return [$user, $token];
    }

    private function adminWithToken(): array
    {
        $user  = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $token = $user->createToken('auth_token')->plainTextToken;
        return [$user, $token];
    }

    // ── member refresh ───────────────────────────────────────

    public function test_member_refresh_returns_new_token(): void
    {
        [, $token] = $this->memberWithToken();

        $response = $this->withToken($token)->postJson('/api/member/refresh');

        $response->assertStatus(200)
                 ->assertJsonStructure(['status', 'data' => ['token', 'token_type']]);

        $this->assertNotEquals($token, $response->json('data.token'));
    }

    public function test_member_refresh_revokes_old_token(): void
    {
        [, $token] = $this->memberWithToken();
        $tokenId   = explode('|', $token, 2)[0];

        $this->withToken($token)->postJson('/api/member/refresh');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    // ── provider refresh ─────────────────────────────────────

    public function test_provider_refresh_returns_new_token(): void
    {
        [, $token] = $this->providerWithToken();

        $response = $this->withToken($token)->postJson('/api/provider/refresh');

        $response->assertStatus(200)
                 ->assertJsonStructure(['status', 'data' => ['token', 'token_type']]);
    }

    public function test_provider_refresh_revokes_old_token(): void
    {
        [, $token] = $this->providerWithToken();
        $tokenId   = explode('|', $token, 2)[0];

        $this->withToken($token)->postJson('/api/provider/refresh');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    // ── admin refresh ────────────────────────────────────────

    public function test_admin_refresh_returns_new_token(): void
    {
        [, $token] = $this->adminWithToken();

        $response = $this->withToken($token)->postJson('/api/admin/refresh');

        $response->assertStatus(200)
                 ->assertJsonStructure(['status', 'data' => ['token', 'token_type']]);
    }

    public function test_admin_refresh_revokes_old_token(): void
    {
        [, $token] = $this->adminWithToken();
        $tokenId   = explode('|', $token, 2)[0];

        $this->withToken($token)->postJson('/api/admin/refresh');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    // ── cross-role 403 ───────────────────────────────────────

    public function test_member_token_cannot_use_provider_refresh(): void
    {
        [, $token] = $this->memberWithToken();

        $this->withToken($token)->postJson('/api/provider/refresh')
             ->assertStatus(403);
    }

    public function test_member_token_cannot_use_admin_refresh(): void
    {
        [, $token] = $this->memberWithToken();

        $this->withToken($token)->postJson('/api/admin/refresh')
             ->assertStatus(403);
    }

    public function test_provider_token_cannot_use_member_refresh(): void
    {
        [, $token] = $this->providerWithToken();

        $this->withToken($token)->postJson('/api/member/refresh')
             ->assertStatus(403);
    }

    // ── expired / revoked token ──────────────────────────────

    public function test_expired_token_cannot_refresh(): void
    {
        [$user,] = $this->memberWithToken();
        $pat = $user->tokens()->latest()->first();
        $pat->update(['expires_at' => now()->subDay()]);

        $expiredToken = $pat->token;
        $this->postJson('/api/member/refresh', [], [
            'Authorization' => 'Bearer ' . $expiredToken,
        ])->assertStatus(401);
    }

    public function test_revoked_token_cannot_refresh(): void
    {
        [$user, $token] = $this->memberWithToken();
        $user->tokens()->delete();

        $this->withToken($token)->postJson('/api/member/refresh')
             ->assertStatus(401);
    }
}
