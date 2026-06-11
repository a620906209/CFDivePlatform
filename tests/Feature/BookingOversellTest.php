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
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * 防超賣不變式：confirmed 預約的總人數永遠不得超過時段容量。
 *
 * pending 不佔名額是刻意設計（教練尚未承諾），因此同一時段允許
 * pending 總和超過容量——超賣防線在 confirm 時的 lockForUpdate 二次
 * 驗證。這裡的測試保護的是金錢與信任：超賣等於收了無法履行的預約。
 */
class BookingOversellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function makeProviderWithSchedule(int $maxParticipants): array
    {
        $provider = User::factory()->create(['role' => 'provider']);

        ProviderProfile::create([
            'user_id'     => $provider->id,
            'verification_status' => 'approved',
        ]);

        $offer = DivingOffer::create([
            'provider_id' => $provider->id,
            'title'       => 'Oversell Course',
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
            'scheduled_date'       => now()->addDays(7)->toDateString(),
            'start_time'           => '09:00',
            'max_participants'     => $maxParticipants,
            'current_participants' => 0,
            'status'               => ScheduleStatus::Open,
        ]);

        return [$provider, $schedule];
    }

    private function makePendingBooking(CourseSchedule $schedule, int $participants): Booking
    {
        return Booking::create([
            'schedule_id'  => $schedule->id,
            'member_id'    => User::factory()->create(['role' => 'member'])->id,
            'participants' => $participants,
            'total_price'  => 1000 * $participants,
            'status'       => BookingStatus::Pending,
        ]);
    }

    public function test_multiple_pendings_may_exceed_capacity_but_confirm_is_gated(): void
    {
        [$provider, $schedule] = $this->makeProviderWithSchedule(2);

        // pending 總和 3 > 容量 2，allowed by design
        $first  = $this->makePendingBooking($schedule, 2);
        $second = $this->makePendingBooking($schedule, 1);

        // 確認第一筆：佔滿容量
        $this->actingAs($provider)
            ->putJson("/api/provider/bookings/{$first->id}/confirm")
            ->assertOk();

        // 確認第二筆：名額不足，必須失敗
        $this->actingAs($provider)
            ->putJson("/api/provider/bookings/{$second->id}/confirm")
            ->assertStatus(422);

        $schedule->refresh();
        $this->assertSame(2, $schedule->current_participants);
        $this->assertSame(BookingStatus::Pending, $second->fresh()->status);

        // 不變式：confirmed 總人數 <= 容量
        $confirmedTotal = Booking::where('schedule_id', $schedule->id)
            ->where('status', BookingStatus::Confirmed->value)
            ->sum('participants');
        $this->assertLessThanOrEqual($schedule->max_participants, $confirmedTotal);
    }

    public function test_full_schedule_rejects_new_booking_requests(): void
    {
        [$provider, $schedule] = $this->makeProviderWithSchedule(2);
        $booking = $this->makePendingBooking($schedule, 2);

        $this->actingAs($provider)
            ->putJson("/api/provider/bookings/{$booking->id}/confirm")
            ->assertOk();

        // 滿員後時段轉 full，新預約在 Layer 1 即被擋下
        $this->assertSame(ScheduleStatus::Full, $schedule->fresh()->status);

        $this->actingAs(User::factory()->create(['role' => 'member']))
            ->postJson('/api/member/bookings', [
                'schedule_id'  => $schedule->id,
                'participants' => 1,
            ])->assertStatus(422);
    }

    public function test_released_spots_can_be_confirmed_again(): void
    {
        [$provider, $schedule] = $this->makeProviderWithSchedule(2);
        $first  = $this->makePendingBooking($schedule, 2);
        $second = $this->makePendingBooking($schedule, 2);

        $this->actingAs($provider)
            ->putJson("/api/provider/bookings/{$first->id}/confirm")
            ->assertOk();

        // 教練取消第一筆 → 名額釋放、full 轉回 open
        $this->actingAs($provider)
            ->putJson("/api/provider/bookings/{$first->id}/cancel")
            ->assertOk();

        // 釋放後第二筆可確認
        $this->actingAs($provider)
            ->putJson("/api/provider/bookings/{$second->id}/confirm")
            ->assertOk();

        $schedule->refresh();
        $this->assertSame(2, $schedule->current_participants);
        $this->assertSame(BookingStatus::Confirmed, $second->fresh()->status);
    }
}
