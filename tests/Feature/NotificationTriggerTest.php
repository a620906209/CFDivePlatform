<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\CourseSchedule;
use App\Models\DivingOffer;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\BookingRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * 預約通知直接觸發路徑（notification-triggers 規格）。
 *
 * 補齊 BookingSchedulerTest 以外的 controller 直接觸發路徑，
 * 確保收件者正確：新預約與 member 取消通知教練，其餘通知學員。
 *
 * 收件者依 controller 原始碼確認：
 *   - BookingCreated      → $provider->notify()
 *   - BookingConfirmed    → $booking->member->notify()
 *   - BookingRejected     → $booking->member->notify()
 *   - BookingCancelled (member)   → $provider->notify()
 *   - BookingCancelled (provider) → $booking->member->notify()
 */
class NotificationTriggerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function makeProvider(): User
    {
        $provider = User::factory()->create(['role' => 'provider']);
        ProviderProfile::create(['user_id' => $provider->id, 'verification_status' => 'approved']);
        return $provider;
    }

    private function makeSchedule(User $provider): CourseSchedule
    {
        $offer = DivingOffer::create([
            'provider_id' => $provider->id,
            'title'       => 'Dive Course',
            'location'    => 'Kenting',
            'price'       => 3000,
            'region'      => '南部',
            'rating'      => 0,
            'reviews'     => 0,
        ]);

        return CourseSchedule::create([
            'diving_offer_id'      => $offer->id,
            'provider_id'          => $provider->id,
            'scheduled_date'       => now()->addDays(60)->toDateString(),
            'start_time'           => '09:00',
            'max_participants'     => 10,
            'current_participants' => 0,
            'status'               => ScheduleStatus::Open,
        ]);
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

    // ── 建立預約 → 通知教練 ──────────────────────────────────

    public function test_booking_created_notifies_provider(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($provider);
        $member   = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)->postJson('/api/member/bookings', [
            'schedule_id'  => $schedule->id,
            'participants' => 1,
        ])->assertStatus(201);

        Notification::assertSentTo($provider, BookingCreatedNotification::class);
    }

    // ── 確認預約 → 通知學員 ──────────────────────────────────

    public function test_booking_confirmed_notifies_member(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($provider);
        $member   = User::factory()->create(['role' => 'member']);
        $booking  = $this->makePendingBooking($member, $schedule);

        $this->actingAs($provider)
            ->putJson("/api/provider/bookings/{$booking->id}/confirm")
            ->assertOk();

        Notification::assertSentTo($member, BookingConfirmedNotification::class);
    }

    // ── 拒絕預約 → 通知學員 ──────────────────────────────────

    public function test_booking_rejected_notifies_member(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($provider);
        $member   = User::factory()->create(['role' => 'member']);
        $booking  = $this->makePendingBooking($member, $schedule);

        $this->actingAs($provider)
            ->putJson("/api/provider/bookings/{$booking->id}/reject")
            ->assertOk();

        Notification::assertSentTo($member, BookingRejectedNotification::class);
    }

    // ── Member 取消 → 通知教練 ───────────────────────────────

    public function test_member_cancel_notifies_provider(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($provider);
        $member   = User::factory()->create(['role' => 'member']);
        $booking  = $this->makePendingBooking($member, $schedule);

        $this->actingAs($member)
            ->deleteJson("/api/member/bookings/{$booking->id}")
            ->assertOk();

        Notification::assertSentTo($provider, BookingCancelledNotification::class);
    }

    // ── Provider 取消 → 通知學員 ─────────────────────────────

    public function test_provider_cancel_notifies_member(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($provider);
        $member   = User::factory()->create(['role' => 'member']);
        $booking  = $this->makePendingBooking($member, $schedule);

        // 先確認，再取消（provider_cancelled 只能從 confirmed 轉換）
        $booking->update(['status' => BookingStatus::Confirmed]);
        $schedule->increment('current_participants', 1);

        $this->actingAs($provider)
            ->putJson("/api/provider/bookings/{$booking->id}/cancel")
            ->assertOk();

        Notification::assertSentTo($member, BookingCancelledNotification::class);
    }
}
