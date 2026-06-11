<?php

namespace Tests\Feature;

use App\Models\ProviderProfile;
use App\Models\User;
use App\Notifications\ProviderVerificationApprovedNotification;
use App\Notifications\ProviderVerificationRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Admin 審核裁決（provider-verification / admin-user-management 規格）。
 *
 * 審核是平台對教練資質的把關承諾：駁回必須有原因（教練的申訴與
 * 補正依據）、裁決必須走狀態機（不可對未送審者直接通過）、
 * 舊 toggle 後門必須保持關閉。
 */
class AdminVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeProvider(string $status): User
    {
        $provider = User::factory()->create(['role' => 'provider']);
        ProviderProfile::create(['user_id' => $provider->id, 'verification_status' => $status]);

        return $provider;
    }

    // ── 佇列 ─────────────────────────────────────────────────

    public function test_queue_defaults_to_pending_only(): void
    {
        $pending = $this->makeProvider('pending');
        $this->makeProvider('unsubmitted');
        $this->makeProvider('approved');

        $response = $this->actingAs($this->makeAdmin())->getJson('/api/admin/verifications');

        $response->assertOk();
        $ids = array_column($response->json('data'), 'user_id');
        $this->assertSame([$pending->id], $ids);
    }

    public function test_queue_can_filter_all_statuses(): void
    {
        $this->makeProvider('pending');
        $this->makeProvider('approved');

        $response = $this->actingAs($this->makeAdmin())->getJson('/api/admin/verifications?status=all');

        $this->assertCount(2, $response->json('data'));
    }

    // ── 裁決狀態機 ───────────────────────────────────────────

    public function test_approve_pending_provider_and_notify(): void
    {
        $provider = $this->makeProvider('pending');

        $this->actingAs($this->makeAdmin())
            ->putJson("/api/admin/verifications/{$provider->id}/approve")
            ->assertOk();

        $this->assertSame('approved', $provider->providerProfile->fresh()->verification_status->value);
        Notification::assertSentTo($provider, ProviderVerificationApprovedNotification::class);
    }

    public function test_reject_requires_reason(): void
    {
        $provider = $this->makeProvider('pending');

        $this->actingAs($this->makeAdmin())
            ->putJson("/api/admin/verifications/{$provider->id}/reject")
            ->assertStatus(422);

        $this->assertSame('pending', $provider->providerProfile->fresh()->verification_status->value);
    }

    public function test_reject_pending_provider_stores_reason_and_notifies(): void
    {
        $provider = $this->makeProvider('pending');

        $this->actingAs($this->makeAdmin())
            ->putJson("/api/admin/verifications/{$provider->id}/reject", ['reason' => '證照已過期'])
            ->assertOk();

        $profile = $provider->providerProfile->fresh();
        $this->assertSame('rejected', $profile->verification_status->value);
        $this->assertSame('證照已過期', $profile->rejection_reason);
        Notification::assertSentTo($provider, ProviderVerificationRejectedNotification::class);
    }

    public function test_cannot_approve_unsubmitted_provider(): void
    {
        $provider = $this->makeProvider('unsubmitted');

        $this->actingAs($this->makeAdmin())
            ->putJson("/api/admin/verifications/{$provider->id}/approve")
            ->assertStatus(422);
    }

    public function test_can_revoke_approved_provider_with_reason(): void
    {
        $provider = $this->makeProvider('approved');

        $this->actingAs($this->makeAdmin())
            ->putJson("/api/admin/verifications/{$provider->id}/reject", ['reason' => '收到檢舉，資料不實'])
            ->assertOk();

        $this->assertSame('rejected', $provider->providerProfile->fresh()->verification_status->value);
    }

    // ── 權限邊界與舊端點 ─────────────────────────────────────

    public function test_non_admin_cannot_access_verification_endpoints(): void
    {
        $provider = $this->makeProvider('pending');
        $member   = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)->getJson('/api/admin/verifications')->assertStatus(403);
        $this->actingAs($member)
            ->putJson("/api/admin/verifications/{$provider->id}/approve")
            ->assertStatus(403);
        $this->actingAs($provider)
            ->putJson("/api/admin/verifications/{$provider->id}/approve")
            ->assertStatus(403);
    }

    public function test_legacy_toggle_verified_endpoint_is_removed(): void
    {
        $provider = $this->makeProvider('approved');

        $this->actingAs($this->makeAdmin())
            ->putJson("/api/admin/providers/{$provider->id}/toggle-verified")
            ->assertStatus(404);
    }
}
