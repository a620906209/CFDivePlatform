<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\CourseSchedule;
use App\Models\DivingOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin 端點權限邊界（admin-* 規格群）。
 *
 * /api/admin/* 可停權用戶、刪除課程與評價、讀取全平台個資，
 * 任何非 admin 角色（含已登入的 member/provider）都必須被 EnsureAdmin
 * middleware 擋在 403，未認證請求擋在 401。
 */
class AdminEndpointAuthTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_GET_ENDPOINTS = [
        '/api/admin/stats',
        '/api/admin/members',
        '/api/admin/providers',
        '/api/admin/offers',
        '/api/admin/bookings',
        '/api/admin/reviews',
    ];

    public function test_member_token_is_rejected_on_all_admin_endpoints(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        foreach (self::ADMIN_GET_ENDPOINTS as $endpoint) {
            $this->actingAs($member)->getJson($endpoint)->assertStatus(403);
        }
    }

    public function test_provider_token_is_rejected_on_all_admin_endpoints(): void
    {
        $provider = User::factory()->create(['role' => 'provider']);

        foreach (self::ADMIN_GET_ENDPOINTS as $endpoint) {
            $this->actingAs($provider)->getJson($endpoint)->assertStatus(403);
        }
    }

    public function test_unauthenticated_request_is_rejected_on_all_admin_endpoints(): void
    {
        foreach (self::ADMIN_GET_ENDPOINTS as $endpoint) {
            $this->getJson($endpoint)->assertStatus(401);
        }
    }

    public function test_admin_token_can_access_admin_endpoints(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (self::ADMIN_GET_ENDPOINTS as $endpoint) {
            $this->actingAs($admin)->getJson($endpoint)->assertOk();
        }
    }

    public function test_admin_complete_follows_booking_state_machine(): void
    {
        $admin    = User::factory()->create(['role' => 'admin']);
        $provider = User::factory()->create(['role' => 'provider']);
        $member   = User::factory()->create(['role' => 'member']);

        $offer = DivingOffer::create([
            'provider_id' => $provider->id,
            'title'       => 'Admin Course',
            'location'    => 'Test',
            'spot'        => 'Test Spot',
            'price'       => 1000,
            'region'      => '南部',
            'rating'      => 0,
            'reviews'     => 0,
        ]);

        $schedule = CourseSchedule::create([
            'diving_offer_id'      => $offer->id,
            'provider_id'          => $provider->id,
            'scheduled_date'       => now()->subDay()->toDateString(),
            'start_time'           => '09:00',
            'max_participants'     => 4,
            'current_participants' => 1,
            'status'               => ScheduleStatus::Open,
        ]);

        $confirmed = Booking::create([
            'schedule_id'  => $schedule->id,
            'member_id'    => $member->id,
            'participants' => 1,
            'total_price'  => 1000,
            'status'       => BookingStatus::Confirmed,
        ]);

        $this->actingAs($admin)
            ->putJson("/api/admin/bookings/{$confirmed->id}/complete")
            ->assertOk();
        $this->assertSame(BookingStatus::Completed, $confirmed->fresh()->status);

        // Admin 也不能繞過狀態機：rejected 是終態
        $rejected = Booking::create([
            'schedule_id'  => $schedule->id,
            'member_id'    => User::factory()->create(['role' => 'member'])->id,
            'participants' => 1,
            'total_price'  => 1000,
            'status'       => BookingStatus::Rejected,
        ]);

        $this->actingAs($admin)
            ->putJson("/api/admin/bookings/{$rejected->id}/complete")
            ->assertStatus(422);
        $this->assertSame(BookingStatus::Rejected, $rejected->fresh()->status);
    }
}
