<?php

namespace Tests\Feature;

use App\Models\DivingOffer;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Provider 課程 CRUD（coach-offers-api 規格）。
 *
 * 所有權驗證是核心不變式：provider_id 決定哪位教練可操作，
 * 未加檢查會讓任何已登入教練刪改他人課程。
 */
class ProviderOfferCrudTest extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(): User
    {
        $provider = User::factory()->create(['role' => 'provider']);
        ProviderProfile::create(['user_id' => $provider->id, 'is_verified' => true]);
        return $provider;
    }

    private function makeOffer(User $provider): DivingOffer
    {
        return DivingOffer::create([
            'provider_id' => $provider->id,
            'title'       => 'Test Dive Course',
            'location'    => 'Kenting',
            'price'       => 3000,
            'region'      => '南部',
            'rating'      => 0,
            'reviews'     => 0,
        ]);
    }

    // ── store ────────────────────────────────────────────────

    public function test_provider_creates_offer_successfully(): void
    {
        $provider = $this->makeProvider();

        $response = $this->actingAs($provider)->postJson('/api/provider/offers', [
            'title'    => 'New Dive Course',
            'location' => 'Kenting',
            'price'    => 5000,
            'region'   => '南部',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.provider_id', $provider->id);

        $this->assertDatabaseHas('diving_offers', [
            'title'       => 'New Dive Course',
            'provider_id' => $provider->id,
        ]);
    }

    public function test_create_offer_missing_required_field_returns_422(): void
    {
        $provider = $this->makeProvider();

        $this->actingAs($provider)->postJson('/api/provider/offers', [
            // title is missing
            'location' => 'Kenting',
            'price'    => 5000,
            'region'   => '南部',
        ])->assertStatus(422);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/provider/offers', [
            'title'    => 'New Dive Course',
            'location' => 'Kenting',
            'price'    => 5000,
            'region'   => '南部',
        ])->assertStatus(401);
    }

    // ── update ───────────────────────────────────────────────

    public function test_provider_updates_own_offer(): void
    {
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);

        $this->actingAs($provider)->putJson("/api/provider/offers/{$offer->id}", [
            'title' => 'Updated Title',
            'price' => 9999,
        ])->assertOk()->assertJsonPath('status', true);

        $this->assertDatabaseHas('diving_offers', [
            'id'    => $offer->id,
            'title' => 'Updated Title',
            'price' => 9999,
        ]);
    }

    public function test_provider_cannot_update_others_offer(): void
    {
        $ownerProvider = $this->makeProvider();
        $otherProvider = $this->makeProvider();
        $offer         = $this->makeOffer($ownerProvider);

        $this->actingAs($otherProvider)
            ->putJson("/api/provider/offers/{$offer->id}", ['title' => 'Hacked Title'])
            ->assertStatus(403);
    }

    // ── destroy ──────────────────────────────────────────────

    public function test_provider_deletes_own_offer(): void
    {
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);

        $this->actingAs($provider)
            ->deleteJson("/api/provider/offers/{$offer->id}")
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseMissing('diving_offers', ['id' => $offer->id]);
    }

    public function test_provider_cannot_delete_others_offer(): void
    {
        $ownerProvider = $this->makeProvider();
        $otherProvider = $this->makeProvider();
        $offer         = $this->makeOffer($ownerProvider);

        $this->actingAs($otherProvider)
            ->deleteJson("/api/provider/offers/{$offer->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('diving_offers', ['id' => $offer->id]);
    }
}
