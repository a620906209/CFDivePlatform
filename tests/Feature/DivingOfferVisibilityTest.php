<?php

namespace Tests\Feature;

use App\Models\DivingOffer;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 公開課程可見性（provider-verification 規格）。
 *
 * is_verified 是平台對教練資質的把關開關：若未驗證教練的課程
 * 仍可公開曝光與被預約，Admin 的驗證機制形同虛設。
 */
class DivingOfferVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(bool $isVerified): User
    {
        $provider = User::factory()->create(['role' => 'provider']);

        ProviderProfile::create([
            'user_id'     => $provider->id,
            'is_verified' => $isVerified,
        ]);

        return $provider;
    }

    private function makeOffer(?int $providerId, string $title): DivingOffer
    {
        return DivingOffer::create([
            'provider_id' => $providerId,
            'title'       => $title,
            'location'    => 'Test',
            'spot'        => 'Test Spot',
            'price'       => 1000,
            'region'      => '南部',
            'rating'      => 0,
            'reviews'     => 0,
        ]);
    }

    // ── 公開列表 ─────────────────────────────────────────────

    public function test_index_includes_verified_provider_offers(): void
    {
        $verified = $this->makeProvider(true);
        $this->makeOffer($verified->id, 'Verified Course');

        $response = $this->getJson('/api/diving-offers');

        $response->assertOk();
        $this->assertContains('Verified Course', array_column($response->json('data'), 'title'));
    }

    public function test_index_excludes_unverified_provider_offers(): void
    {
        $unverified = $this->makeProvider(false);
        $this->makeOffer($unverified->id, 'Unverified Course');

        $response = $this->getJson('/api/diving-offers');

        $response->assertOk();
        $this->assertNotContains('Unverified Course', array_column($response->json('data'), 'title'));
    }

    public function test_index_includes_platform_owned_offers_without_provider(): void
    {
        $this->makeOffer(null, 'Platform Course');

        $response = $this->getJson('/api/diving-offers');

        $response->assertOk();
        $this->assertContains('Platform Course', array_column($response->json('data'), 'title'));
    }

    // ── 課程詳情 ─────────────────────────────────────────────

    public function test_show_returns_404_for_unverified_provider_offer(): void
    {
        $unverified = $this->makeProvider(false);
        $offer = $this->makeOffer($unverified->id, 'Hidden Course');

        $this->getJson("/api/diving-offers/{$offer->id}")->assertStatus(404);
    }

    public function test_show_returns_verified_provider_offer(): void
    {
        $verified = $this->makeProvider(true);
        $offer = $this->makeOffer($verified->id, 'Visible Course');

        $this->getJson("/api/diving-offers/{$offer->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Visible Course');
    }

    // ── 公開子端點套用相同可見性 ─────────────────────────────

    public function test_schedules_endpoint_returns_404_for_unverified_provider_offer(): void
    {
        $unverified = $this->makeProvider(false);
        $offer = $this->makeOffer($unverified->id, 'Hidden Schedules Course');

        $this->getJson("/api/diving-offers/{$offer->id}/schedules")->assertStatus(404);
    }

    public function test_reviews_endpoint_returns_404_for_unverified_provider_offer(): void
    {
        $unverified = $this->makeProvider(false);
        $offer = $this->makeOffer($unverified->id, 'Hidden Reviews Course');

        $this->getJson("/api/diving-offers/{$offer->id}/reviews")->assertStatus(404);
    }

    public function test_sub_endpoints_work_for_verified_provider_offer(): void
    {
        $verified = $this->makeProvider(true);
        $offer = $this->makeOffer($verified->id, 'Visible Sub Course');

        $this->getJson("/api/diving-offers/{$offer->id}/schedules")->assertOk();
        $this->getJson("/api/diving-offers/{$offer->id}/reviews")->assertOk();
    }

    // ── 驗證狀態切換立即生效（快取失效） ─────────────────────

    public function test_toggle_verified_takes_effect_immediately_despite_cache(): void
    {
        $provider = $this->makeProvider(true);
        $this->makeOffer($provider->id, 'Toggle Course');
        $admin = User::factory()->create(['role' => 'admin']);

        // 先打一次列表讓結果進快取
        $this->assertContains(
            'Toggle Course',
            array_column($this->getJson('/api/diving-offers')->json('data'), 'title')
        );

        $this->actingAs($admin)
            ->putJson("/api/admin/providers/{$provider->id}/toggle-verified")
            ->assertOk()
            ->assertJsonPath('data.is_verified', false);

        $this->assertNotContains(
            'Toggle Course',
            array_column($this->getJson('/api/diving-offers')->json('data'), 'title')
        );
    }

    // ── 教練自有管理端點不受限 ───────────────────────────────

    public function test_unverified_provider_can_still_manage_own_offers(): void
    {
        $unverified = $this->makeProvider(false);
        $this->makeOffer($unverified->id, 'My Own Course');

        $response = $this->actingAs($unverified)->getJson('/api/provider/offers');

        $response->assertOk();
        $this->assertContains('My Own Course', array_column($response->json('data'), 'title'));
    }
}
