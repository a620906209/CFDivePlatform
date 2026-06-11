<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\CourseSchedule;
use App\Models\DivingOffer;
use App\Models\User;
use App\Notifications\BookingCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Scheduler 自動轉移（booking-lifecycle 規格）。
 *
 * 48 小時與日期邊界是會員等待體驗與教練帳務的契約：過早 expire 會
 * 砍掉教練還來得及確認的單，過早 complete 會讓未上課的預約取得評價資格。
 */
class BookingSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function makeBookingOnDate(BookingStatus $status, string $scheduledDate): Booking
    {
        $provider = User::factory()->create(['role' => 'provider']);

        $offer = DivingOffer::create([
            'provider_id' => $provider->id,
            'title'       => 'Scheduler Course',
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
            'scheduled_date'       => $scheduledDate,
            'start_time'           => '09:00',
            'max_participants'     => 4,
            'current_participants' => 0,
            'status'               => ScheduleStatus::Open,
        ]);

        return Booking::create([
            'schedule_id'  => $schedule->id,
            'member_id'    => User::factory()->create(['role' => 'member'])->id,
            'participants' => 1,
            'total_price'  => 1000,
            'status'       => $status,
        ]);
    }

    private function backdateBooking(Booking $booking, int $hours): void
    {
        $booking->created_at = now()->subHours($hours);
        $booking->save();
    }

    // ── app:expire-pending-bookings ──────────────────────────

    public function test_pending_older_than_48_hours_is_expired(): void
    {
        $booking = $this->makeBookingOnDate(BookingStatus::Pending, now()->addDays(7)->toDateString());
        $this->backdateBooking($booking, 49);

        $this->artisan('app:expire-pending-bookings')->assertSuccessful();

        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
    }

    public function test_pending_within_48_hours_is_not_expired(): void
    {
        $booking = $this->makeBookingOnDate(BookingStatus::Pending, now()->addDays(7)->toDateString());
        $this->backdateBooking($booking, 47);

        $this->artisan('app:expire-pending-bookings')->assertSuccessful();

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    public function test_expire_command_does_not_touch_confirmed_bookings(): void
    {
        $booking = $this->makeBookingOnDate(BookingStatus::Confirmed, now()->addDays(7)->toDateString());
        $this->backdateBooking($booking, 100);

        $this->artisan('app:expire-pending-bookings')->assertSuccessful();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    // ── app:complete-finished-bookings ───────────────────────

    public function test_confirmed_booking_with_past_date_is_completed_and_member_notified(): void
    {
        $booking = $this->makeBookingOnDate(BookingStatus::Confirmed, now()->subDay()->toDateString());

        $this->artisan('app:complete-finished-bookings')->assertSuccessful();

        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
        Notification::assertSentTo($booking->member, BookingCompletedNotification::class);
    }

    public function test_confirmed_booking_today_is_not_completed_yet(): void
    {
        $booking = $this->makeBookingOnDate(BookingStatus::Confirmed, now()->toDateString());

        $this->artisan('app:complete-finished-bookings')->assertSuccessful();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_complete_command_does_not_touch_pending_bookings(): void
    {
        // pending 即使課程日期已過也不應被標記完成（應由 expire 流程處理）
        $booking = $this->makeBookingOnDate(BookingStatus::Pending, now()->subDay()->toDateString());

        $this->artisan('app:complete-finished-bookings')->assertSuccessful();

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }
}
