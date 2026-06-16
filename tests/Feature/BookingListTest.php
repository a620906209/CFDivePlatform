<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\CourseSchedule;
use App\Models\DivingOffer;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 預約列表端點資料隔離（booking-lifecycle 規格）。
 *
 * 路由對應（routes/api.php 已確認）：
 *   GET /api/member/bookings   → MemberBookingController::index（auth:sanctum，無 role check）
 *   GET /api/provider/bookings → ProviderBookingController::index（auth:sanctum，無 role check）
 *   GET /api/admin/bookings    → AdminBookingController::index（auth:sanctum + admin middleware）
 *
 * 資料隔離靠 controller query 條件，不靠 middleware role 強制，
 * 因此測試需分別以正確角色 actingAs 驗證隔離邊界。
 */
class BookingListTest extends TestCase
{
    use RefreshDatabase;

    private function makeProviderWithSchedule(): array
    {
        $provider = User::factory()->create(['role' => 'provider']);
        ProviderProfile::create(['user_id' => $provider->id, 'verification_status' => 'approved']);

        $offer = DivingOffer::create([
            'provider_id' => $provider->id,
            'title'       => 'Dive Course',
            'location'    => 'Kenting',
            'price'       => 3000,
            'region'      => '南部',
            'rating'      => 0,
            'reviews'     => 0,
        ]);

        $schedule = CourseSchedule::create([
            'diving_offer_id'      => $offer->id,
            'provider_id'          => $provider->id,
            'scheduled_date'       => now()->addDays(30)->toDateString(),
            'start_time'           => '09:00',
            'max_participants'     => 10,
            'current_participants' => 0,
            'status'               => ScheduleStatus::Open,
        ]);

        return [$provider, $schedule];
    }

    private function makePendingBooking(User $member, CourseSchedule $schedule): Booking
    {
        return Booking::create([
            'schedule_id'  => $schedule->id,
            'member_id'    => $member->id,
            'participants' => 1,
            'total_price'  => 3000,
            'status'       => BookingStatus::Pending,
        ]);
    }

    // ── Member 列表 ──────────────────────────────────────────

    public function test_member_sees_only_own_bookings(): void
    {
        [$provider, $schedule] = $this->makeProviderWithSchedule();
        $memberA  = User::factory()->create(['role' => 'member']);
        $memberB  = User::factory()->create(['role' => 'member']);
        $bookingA = $this->makePendingBooking($memberA, $schedule);
        $bookingB = $this->makePendingBooking($memberB, $schedule);

        $response = $this->actingAs($memberA)->getJson('/api/member/bookings');

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($bookingA->id, $ids);
        $this->assertNotContains($bookingB->id, $ids);
    }

    // ── Provider 列表 ────────────────────────────────────────

    public function test_provider_sees_only_own_course_bookings(): void
    {
        [$providerA, $scheduleA] = $this->makeProviderWithSchedule();
        [$providerB, $scheduleB] = $this->makeProviderWithSchedule();
        $member   = User::factory()->create(['role' => 'member']);
        $bookingA = $this->makePendingBooking($member, $scheduleA);
        $bookingB = $this->makePendingBooking($member, $scheduleB);

        $response = $this->actingAs($providerA)->getJson('/api/provider/bookings');

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($bookingA->id, $ids);
        $this->assertNotContains($bookingB->id, $ids);
    }

    // ── Admin 列表 ───────────────────────────────────────────

    public function test_admin_sees_all_bookings(): void
    {
        $admin    = User::factory()->create(['role' => 'admin']);
        [$providerA, $scheduleA] = $this->makeProviderWithSchedule();
        [$providerB, $scheduleB] = $this->makeProviderWithSchedule();
        $memberA  = User::factory()->create(['role' => 'member']);
        $memberB  = User::factory()->create(['role' => 'member']);
        $bookingA = $this->makePendingBooking($memberA, $scheduleA);
        $bookingB = $this->makePendingBooking($memberB, $scheduleB);

        $response = $this->actingAs($admin)->getJson('/api/admin/bookings');

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($bookingA->id, $ids);
        $this->assertContains($bookingB->id, $ids);
    }

    // ── 未認證 ───────────────────────────────────────────────

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/member/bookings')->assertStatus(401);
    }
}
