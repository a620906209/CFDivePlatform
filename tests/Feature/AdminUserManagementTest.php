<?php

namespace Tests\Feature;

use App\Models\MemberProfile;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin 使用者管理（admin-user-management 規格）。
 *
 * 列表端點必須依 role 過濾，否則 admin 介面會混入不同角色資料；
 * toggle-verified 的快取清除副作用已由 DivingOfferVisibilityTest 覆蓋，
 * 這裡只驗資料庫欄位變更與 HTTP 200。
 */
class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeMember(): User
    {
        $member = User::factory()->create(['role' => 'member']);
        MemberProfile::create(['user_id' => $member->id]);
        return $member;
    }

    private function makeProvider(string $verificationStatus = 'unsubmitted'): User
    {
        $provider = User::factory()->create(['role' => 'provider']);
        ProviderProfile::create(['user_id' => $provider->id, 'verification_status' => $verificationStatus]);
        return $provider;
    }

    // ── 列表 ─────────────────────────────────────────────────

    public function test_admin_lists_members_only(): void
    {
        $admin    = $this->makeAdmin();
        $member   = $this->makeMember();
        $provider = $this->makeProvider();

        $response = $this->actingAs($admin)->getJson('/api/admin/members');

        $response->assertOk()->assertJsonPath('status', true);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($member->id, $ids);
        $this->assertNotContains($provider->id, $ids);
    }

    public function test_admin_lists_providers_only(): void
    {
        $admin    = $this->makeAdmin();
        $member   = $this->makeMember();
        $provider = $this->makeProvider();

        $response = $this->actingAs($admin)->getJson('/api/admin/providers');

        $response->assertOk()->assertJsonPath('status', true);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($provider->id, $ids);
        $this->assertNotContains($member->id, $ids);
    }

    // ── toggle-active ────────────────────────────────────────

    public function test_admin_toggles_member_active_status(): void
    {
        $admin  = $this->makeAdmin();
        // is_active 預設由 DB 決定，factory 不設值；直接測試 toggle 的翻轉效果
        $member = User::factory()->create(['role' => 'member', 'is_active' => true]);

        $this->actingAs($admin)
            ->putJson("/api/admin/members/{$member->id}/toggle-active")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'id'        => $member->id,
            'is_active' => false,
        ]);
    }

    // ── approve ─────────────────────────────────────────────

    public function test_admin_approves_provider_verification(): void
    {
        $admin    = $this->makeAdmin();
        $provider = $this->makeProvider(verificationStatus: 'pending');

        $this->actingAs($admin)
            ->putJson("/api/admin/verifications/{$provider->id}/approve")
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('provider_profiles', [
            'user_id'             => $provider->id,
            'verification_status' => 'approved',
        ]);
    }

    // ── 角色保護 ─────────────────────────────────────────────

    public function test_non_admin_is_forbidden(): void
    {
        $member = $this->makeMember();

        $this->actingAs($member)
            ->getJson('/api/admin/members')
            ->assertStatus(403);
    }
}
