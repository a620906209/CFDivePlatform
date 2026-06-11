<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CourseSchedule;
use App\Models\DivingOffer;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\ReviewEdit;
use App\Models\ReviewVote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    // ── 測試資料建立輔助 ─────────────────────────────────────

    private function createMember(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => 'member'], $attrs));
    }

    private function createAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function createOffer(): DivingOffer
    {
        $provider = User::factory()->create(['role' => 'provider']);
        // 公開評價端點僅對已驗證教練的課程開放（provider-verification 規格）
        ProviderProfile::create(['user_id' => $provider->id, 'is_verified' => true]);
        return DivingOffer::create([
            'provider_id' => $provider->id,
            'title'       => '測試潛水課程',
            'location'    => '台北',
            'spot'        => '龍洞',
            'rating'      => 0,
            'reviews'     => 0,
            'price'       => 3000,
            'badges'      => [],
            'description' => '測試用課程',
            'tag'         => 'beginner',
            'region'      => 'north',
        ]);
    }

    private function createCompletedBooking(User $member, DivingOffer $offer): Booking
    {
        $schedule = CourseSchedule::create([
            'diving_offer_id'      => $offer->id,
            'provider_id'          => $offer->provider_id,
            'scheduled_date'       => now()->subDays(7)->toDateString(),
            'start_time'           => '09:00:00',
            'max_participants'     => 10,
            'current_participants' => 1,
            'status'               => 'open',
        ]);

        return Booking::create([
            'schedule_id'  => $schedule->id,
            'member_id'    => $member->id,
            'participants' => 1,
            'total_price'  => $offer->price,
            'status'       => BookingStatus::Completed->value,
        ]);
    }

    private function createReview(User $member, DivingOffer $offer, array $attrs = []): Review
    {
        return Review::create(array_merge([
            'diving_offer_id' => $offer->id,
            'member_id'       => $member->id,
            'rating'          => 5,
            'comment'         => '很棒的課程！',
        ], $attrs));
    }

    // ── 公開列表 ─────────────────────────────────────────────

    public function test_public_list_returns_empty_summary_when_no_reviews(): void
    {
        $offer = $this->createOffer();

        $response = $this->getJson("/api/diving-offers/{$offer->id}/reviews");

        $response->assertOk()
            ->assertJson([
                'status' => true,
                'data'   => [
                    'summary' => [
                        'average' => 0,
                        'total'   => 0,
                    ],
                    'reviews' => [],
                ],
            ]);
    }

    public function test_public_list_returns_distribution_with_all_keys(): void
    {
        $member = $this->createMember();
        $offer  = $this->createOffer();
        $this->createReview($member, $offer, ['rating' => 5]);

        $response = $this->getJson("/api/diving-offers/{$offer->id}/reviews");

        $dist = $response->json('data.summary.distribution');
        $this->assertArrayHasKey('1', $dist);
        $this->assertArrayHasKey('2', $dist);
        $this->assertArrayHasKey('3', $dist);
        $this->assertArrayHasKey('4', $dist);
        $this->assertArrayHasKey('5', $dist);
        $this->assertEquals(1, $dist['5']);
        $this->assertEquals(0, $dist['1']);
    }

    public function test_public_list_anonymous_user_has_no_is_mine_field(): void
    {
        $member = $this->createMember();
        $offer  = $this->createOffer();
        $this->createReview($member, $offer);

        $response = $this->getJson("/api/diving-offers/{$offer->id}/reviews");

        $review = $response->json('data.reviews.0');
        $this->assertArrayNotHasKey('is_mine', $review);
        $this->assertFalse($review['has_voted']);
        $this->assertEquals('匿名潛水者', $review['reviewer_name']);
    }

    public function test_public_list_authenticated_user_has_is_mine_field(): void
    {
        $member = $this->createMember();
        $offer  = $this->createOffer();
        $this->createReview($member, $offer);

        $response = $this->actingAs($member)->getJson("/api/diving-offers/{$offer->id}/reviews");

        $review = $response->json('data.reviews.0');
        $this->assertArrayHasKey('is_mine', $review);
        $this->assertTrue($review['is_mine']);
    }

    public function test_public_list_has_voted_is_true_when_voted(): void
    {
        $owner  = $this->createMember();
        $voter  = $this->createMember();
        $offer  = $this->createOffer();
        $review = $this->createReview($owner, $offer);
        ReviewVote::create(['review_id' => $review->id, 'member_id' => $voter->id, 'created_at' => now()]);

        $response = $this->actingAs($voter)->getJson("/api/diving-offers/{$offer->id}/reviews");

        $this->assertTrue($response->json('data.reviews.0.has_voted'));
    }

    public function test_public_list_sort_by_helpful(): void
    {
        $member = $this->createMember();
        $offer  = $this->createOffer();
        $r1 = $this->createReview($member, $offer, ['rating' => 3, 'helpful_count' => 10]);
        // Need a second review but UNIQUE constraint prevents same member/offer
        // So create another member
        $member2 = $this->createMember();
        $r2 = $this->createReview($member2, $offer, ['rating' => 5, 'helpful_count' => 1]);

        $response = $this->getJson("/api/diving-offers/{$offer->id}/reviews?sort=helpful");

        $ids = $response->json('data.reviews.*.id');
        $this->assertEquals($r1->id, $ids[0]);
    }

    public function test_public_list_sort_by_rating(): void
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();
        $offer   = $this->createOffer();
        $r1 = $this->createReview($member1, $offer, ['rating' => 2]);
        $r2 = $this->createReview($member2, $offer, ['rating' => 5]);

        $response = $this->getJson("/api/diving-offers/{$offer->id}/reviews?sort=rating");

        $ids = $response->json('data.reviews.*.id');
        $this->assertEquals($r2->id, $ids[0]);
    }

    public function test_public_list_sort_by_newest(): void
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();
        $offer   = $this->createOffer();
        $r1 = $this->createReview($member1, $offer);
        $r2 = $this->createReview($member2, $offer);

        $response = $this->getJson("/api/diving-offers/{$offer->id}/reviews?sort=newest");

        $ids = $response->json('data.reviews.*.id');
        $this->assertEquals($r2->id, $ids[0]);
    }

    // ── 新增評價 ──────────────────────────────────────────────

    public function test_guest_cannot_create_review(): void
    {
        $offer = $this->createOffer();
        $this->postJson('/api/member/reviews', [
            'diving_offer_id' => $offer->id,
            'rating'          => 5,
            'comment'         => '很棒',
        ])->assertUnauthorized();
    }

    public function test_member_cannot_review_without_completed_booking(): void
    {
        $member = $this->createMember();
        $offer  = $this->createOffer();

        $this->actingAs($member)->postJson('/api/member/reviews', [
            'diving_offer_id' => $offer->id,
            'rating'          => 5,
            'comment'         => '很棒',
        ])->assertStatus(403)
          ->assertJson(['message' => '須完成此課程後才能評價']);
    }

    public function test_member_can_create_review_with_completed_booking(): void
    {
        $member = $this->createMember();
        $offer  = $this->createOffer();
        $this->createCompletedBooking($member, $offer);

        $response = $this->actingAs($member)->postJson('/api/member/reviews', [
            'diving_offer_id' => $offer->id,
            'rating'          => 5,
            'comment'         => '課程很棒！',
        ]);

        $response->assertStatus(201)
            ->assertJson(['status' => true]);

        $this->assertDatabaseHas('reviews', [
            'diving_offer_id' => $offer->id,
            'member_id'       => $member->id,
            'rating'          => 5,
        ]);
    }

    public function test_create_review_recalculates_offer_rating(): void
    {
        $member = $this->createMember();
        $offer  = $this->createOffer();
        $this->createCompletedBooking($member, $offer);

        $this->actingAs($member)->postJson('/api/member/reviews', [
            'diving_offer_id' => $offer->id,
            'rating'          => 4,
            'comment'         => '不錯',
        ]);

        $offer->refresh();
        $this->assertEquals(4.0, $offer->rating);
        $this->assertEquals(1, $offer->reviews);
    }

    public function test_member_cannot_review_same_offer_twice(): void
    {
        $member = $this->createMember();
        $offer  = $this->createOffer();
        $this->createCompletedBooking($member, $offer);
        $this->createReview($member, $offer);

        $this->actingAs($member)->postJson('/api/member/reviews', [
            'diving_offer_id' => $offer->id,
            'rating'          => 3,
            'comment'         => '重複',
        ])->assertStatus(422)
          ->assertJson(['message' => '已評價，如需修改請使用編輯功能']);
    }

    public function test_create_review_validates_rating_range(): void
    {
        $member = $this->createMember();
        $offer  = $this->createOffer();
        $this->createCompletedBooking($member, $offer);

        $this->actingAs($member)->postJson('/api/member/reviews', [
            'diving_offer_id' => $offer->id,
            'rating'          => 6,
            'comment'         => '超出範圍',
        ])->assertStatus(422);
    }

    // ── 修改評價 ──────────────────────────────────────────────

    public function test_member_can_update_own_review(): void
    {
        $member = $this->createMember();
        $offer  = $this->createOffer();
        $review = $this->createReview($member, $offer);

        $this->actingAs($member)->putJson("/api/member/reviews/{$review->id}", [
            'rating'  => 3,
            'comment' => '修改後的評論',
        ])->assertOk()->assertJson(['status' => true]);

        $this->assertEquals(3, $review->fresh()->rating);
    }

    public function test_member_cannot_update_others_review(): void
    {
        $owner  = $this->createMember();
        $other  = $this->createMember();
        $offer  = $this->createOffer();
        $review = $this->createReview($owner, $offer);

        $this->actingAs($other)->putJson("/api/member/reviews/{$review->id}", [
            'rating' => 1,
        ])->assertStatus(403)
          ->assertJson(['message' => '無權修改此評價']);
    }

    public function test_update_creates_review_edit_and_sets_is_edited(): void
    {
        $member = $this->createMember();
        $offer  = $this->createOffer();
        $review = $this->createReview($member, $offer, ['rating' => 5, 'comment' => '原始評論']);

        $this->actingAs($member)->putJson("/api/member/reviews/{$review->id}", [
            'rating'  => 4,
            'comment' => '修改後',
        ]);

        $this->assertDatabaseHas('review_edits', [
            'review_id'  => $review->id,
            'old_rating' => 5,
        ]);
        $this->assertTrue($review->fresh()->is_edited);
    }

    public function test_second_update_overwrites_review_edit(): void
    {
        $member = $this->createMember();
        $offer  = $this->createOffer();
        $review = $this->createReview($member, $offer, ['rating' => 5]);

        $this->actingAs($member)->putJson("/api/member/reviews/{$review->id}", ['rating' => 4]);
        $this->actingAs($member)->putJson("/api/member/reviews/{$review->id}", ['rating' => 3]);

        $this->assertDatabaseCount('review_edits', 1);
        $this->assertEquals(3, $review->fresh()->rating);
    }

    public function test_update_review_recalculates_offer_rating(): void
    {
        $member = $this->createMember();
        $offer  = $this->createOffer();
        $review = $this->createReview($member, $offer, ['rating' => 5]);
        // Manually set offer rating to 5 first
        $offer->update(['rating' => 5, 'reviews' => 1]);

        $this->actingAs($member)->putJson("/api/member/reviews/{$review->id}", ['rating' => 2]);

        $offer->refresh();
        $this->assertEquals(2.0, $offer->rating);
    }

    // ── 刪除評價 ──────────────────────────────────────────────

    public function test_member_can_delete_own_review(): void
    {
        $member = $this->createMember();
        $offer  = $this->createOffer();
        $review = $this->createReview($member, $offer);

        $this->actingAs($member)->deleteJson("/api/member/reviews/{$review->id}")
            ->assertOk()->assertJson(['status' => true]);

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_member_cannot_delete_others_review(): void
    {
        $owner  = $this->createMember();
        $other  = $this->createMember();
        $offer  = $this->createOffer();
        $review = $this->createReview($owner, $offer);

        $this->actingAs($other)->deleteJson("/api/member/reviews/{$review->id}")
            ->assertStatus(403)
            ->assertJson(['message' => '無權刪除此評價']);
    }

    public function test_delete_review_recalculates_offer_rating(): void
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();
        $offer   = $this->createOffer();
        $r1 = $this->createReview($member1, $offer, ['rating' => 4]);
        $r2 = $this->createReview($member2, $offer, ['rating' => 2]);
        $offer->update(['rating' => 3.0, 'reviews' => 2]);

        $this->actingAs($member2)->deleteJson("/api/member/reviews/{$r2->id}");

        $offer->refresh();
        $this->assertEquals(4.0, $offer->rating);
        $this->assertEquals(1, $offer->reviews);
    }

    public function test_delete_review_resets_offer_rating_to_zero_when_no_reviews(): void
    {
        $member = $this->createMember();
        $offer  = $this->createOffer();
        $review = $this->createReview($member, $offer);
        $offer->update(['rating' => 5.0, 'reviews' => 1]);

        $this->actingAs($member)->deleteJson("/api/member/reviews/{$review->id}");

        $offer->refresh();
        $this->assertEquals(0, $offer->rating);
        $this->assertEquals(0, $offer->reviews);
    }

    // ── 有幫助投票 ────────────────────────────────────────────

    public function test_guest_cannot_vote(): void
    {
        $owner  = $this->createMember();
        $offer  = $this->createOffer();
        $review = $this->createReview($owner, $offer);

        $this->postJson("/api/reviews/{$review->id}/helpful")
            ->assertUnauthorized();
    }

    public function test_member_cannot_vote_own_review(): void
    {
        $member = $this->createMember();
        $offer  = $this->createOffer();
        $review = $this->createReview($member, $offer);

        $this->actingAs($member)->postJson("/api/reviews/{$review->id}/helpful")
            ->assertStatus(422)
            ->assertJson(['message' => '不可對自己的評價投票']);
    }

    public function test_member_can_vote_helpful(): void
    {
        $owner  = $this->createMember();
        $voter  = $this->createMember();
        $offer  = $this->createOffer();
        $review = $this->createReview($owner, $offer);

        $response = $this->actingAs($voter)->postJson("/api/reviews/{$review->id}/helpful");

        $response->assertOk()
            ->assertJson(['data' => ['helpful_count' => 1, 'has_voted' => true]]);

        $this->assertDatabaseHas('review_votes', [
            'review_id' => $review->id,
            'member_id' => $voter->id,
        ]);
    }

    public function test_second_vote_toggles_off(): void
    {
        $owner  = $this->createMember();
        $voter  = $this->createMember();
        $offer  = $this->createOffer();
        $review = $this->createReview($owner, $offer);

        $this->actingAs($voter)->postJson("/api/reviews/{$review->id}/helpful");
        $response = $this->actingAs($voter)->postJson("/api/reviews/{$review->id}/helpful");

        $response->assertOk()
            ->assertJson(['data' => ['helpful_count' => 0, 'has_voted' => false]]);

        $this->assertDatabaseMissing('review_votes', [
            'review_id' => $review->id,
            'member_id' => $voter->id,
        ]);
    }

    public function test_helpful_count_does_not_go_below_zero(): void
    {
        $owner  = $this->createMember();
        $voter  = $this->createMember();
        $offer  = $this->createOffer();
        $review = $this->createReview($owner, $offer, ['helpful_count' => 0]);

        // Force a vote record without incrementing (edge case simulation)
        ReviewVote::create(['review_id' => $review->id, 'member_id' => $voter->id, 'created_at' => now()]);

        $response = $this->actingAs($voter)->postJson("/api/reviews/{$review->id}/helpful");

        $this->assertGreaterThanOrEqual(0, $response->json('data.helpful_count'));
    }

    // ── Admin 評價管理 ────────────────────────────────────────

    public function test_admin_can_list_all_reviews(): void
    {
        $member = $this->createMember();
        $offer  = $this->createOffer();
        $this->createReview($member, $offer);

        $admin    = $this->createAdmin();
        $response = $this->actingAs($admin)->getJson('/api/admin/reviews');

        $response->assertOk()->assertJson(['status' => true]);
        $this->assertCount(1, $response->json('data'));

        $item = $response->json('data.0');
        $this->assertArrayHasKey('offer_title', $item);
        $this->assertArrayHasKey('member_email', $item);
        $this->assertArrayHasKey('rating', $item);
        $this->assertArrayHasKey('comment', $item);
    }

    public function test_admin_can_delete_review(): void
    {
        $member = $this->createMember();
        $offer  = $this->createOffer();
        $review = $this->createReview($member, $offer);

        $admin = $this->createAdmin();
        $this->actingAs($admin)->deleteJson("/api/admin/reviews/{$review->id}")
            ->assertOk()->assertJson(['status' => true]);

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_admin_delete_recalculates_offer_rating(): void
    {
        $member = $this->createMember();
        $offer  = $this->createOffer();
        $review = $this->createReview($member, $offer, ['rating' => 5]);
        $offer->update(['rating' => 5.0, 'reviews' => 1]);

        $admin = $this->createAdmin();
        $this->actingAs($admin)->deleteJson("/api/admin/reviews/{$review->id}");

        $offer->refresh();
        $this->assertEquals(0, $offer->rating);
        $this->assertEquals(0, $offer->reviews);
    }

    public function test_non_admin_cannot_access_admin_reviews(): void
    {
        $member = $this->createMember();
        $this->actingAs($member)->getJson('/api/admin/reviews')
            ->assertForbidden();
    }
}
