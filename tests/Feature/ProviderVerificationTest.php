<?php

namespace Tests\Feature;

use App\Models\ProviderCertification;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 教練端驗證申請流程（provider-verification 規格）。
 *
 * 證照是 Admin 的審核依據：pending/approved 期間若可變更，
 * 等於審核通過後可抽換證照，狀態機與鎖定規則是審核公信力的基礎。
 */
class ProviderVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(string $status = 'unsubmitted'): User
    {
        $provider = User::factory()->create(['role' => 'provider']);
        ProviderProfile::create(['user_id' => $provider->id, 'verification_status' => $status]);

        return $provider;
    }

    private function addCertification(User $provider): ProviderCertification
    {
        return ProviderCertification::create([
            'user_id'    => $provider->id,
            'image_path' => "providers/{$provider->id}/certifications/test.jpg",
        ]);
    }

    // ── 查詢 ─────────────────────────────────────────────────

    public function test_provider_can_view_own_verification_state(): void
    {
        $provider = $this->makeProvider('rejected');
        $provider->providerProfile->update(['rejection_reason' => '證照不清晰']);
        $this->addCertification($provider);

        $this->actingAs($provider)->getJson('/api/provider/verification')
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'rejected')
            ->assertJsonPath('data.rejection_reason', '證照不清晰')
            ->assertJsonCount(1, 'data.certifications');
    }

    // ── 證照上傳與鎖定 ───────────────────────────────────────

    public function test_unsubmitted_provider_can_upload_certification(): void
    {
        Storage::fake('public');
        $provider = $this->makeProvider();

        $this->actingAs($provider)
            ->postJson('/api/provider/verification/certifications', [
                'image' => UploadedFile::fake()->image('cert.jpg', 800, 600),
            ])->assertStatus(201);

        $this->assertSame(1, $provider->providerCertifications()->count());
    }

    public function test_certification_limit_is_three(): void
    {
        Storage::fake('public');
        $provider = $this->makeProvider();
        foreach (range(1, 3) as $i) {
            $this->addCertification($provider);
        }

        $this->actingAs($provider)
            ->postJson('/api/provider/verification/certifications', [
                'image' => UploadedFile::fake()->image('cert4.jpg', 800, 600),
            ])->assertStatus(422);
    }

    public function test_certifications_are_locked_while_pending(): void
    {
        Storage::fake('public');
        $provider = $this->makeProvider('pending');
        $cert     = $this->addCertification($provider);

        $this->actingAs($provider)
            ->postJson('/api/provider/verification/certifications', [
                'image' => UploadedFile::fake()->image('late.jpg', 800, 600),
            ])->assertStatus(422);

        $this->actingAs($provider)
            ->deleteJson("/api/provider/verification/certifications/{$cert->id}")
            ->assertStatus(422);
    }

    public function test_provider_cannot_delete_others_certification(): void
    {
        $owner    = $this->makeProvider();
        $cert     = $this->addCertification($owner);
        $intruder = $this->makeProvider();

        $this->actingAs($intruder)
            ->deleteJson("/api/provider/verification/certifications/{$cert->id}")
            ->assertStatus(403);
    }

    // ── 送審狀態機 ───────────────────────────────────────────

    public function test_submit_requires_at_least_one_certification(): void
    {
        $provider = $this->makeProvider();

        $this->actingAs($provider)
            ->postJson('/api/provider/verification/submit')
            ->assertStatus(422);

        $this->assertSame('unsubmitted', $provider->providerProfile->fresh()->verification_status->value);
    }

    public function test_submit_moves_unsubmitted_to_pending(): void
    {
        $provider = $this->makeProvider();
        $this->addCertification($provider);

        $this->actingAs($provider)
            ->postJson('/api/provider/verification/submit')
            ->assertOk();

        $this->assertSame('pending', $provider->providerProfile->fresh()->verification_status->value);
    }

    public function test_rejected_provider_can_resubmit_and_reason_is_cleared(): void
    {
        $provider = $this->makeProvider('rejected');
        $provider->providerProfile->update(['rejection_reason' => '證照過期']);
        $this->addCertification($provider);

        $this->actingAs($provider)
            ->postJson('/api/provider/verification/submit')
            ->assertOk();

        $profile = $provider->providerProfile->fresh();
        $this->assertSame('pending', $profile->verification_status->value);
        $this->assertNull($profile->rejection_reason);
    }

    public function test_pending_or_approved_provider_cannot_submit_again(): void
    {
        foreach (['pending', 'approved'] as $status) {
            $provider = $this->makeProvider($status);
            $this->addCertification($provider);

            $this->actingAs($provider)
                ->postJson('/api/provider/verification/submit')
                ->assertStatus(422);

            $this->assertSame($status, $provider->providerProfile->fresh()->verification_status->value);
        }
    }
}
