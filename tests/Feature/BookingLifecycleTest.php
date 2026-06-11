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
 * 預約七狀態機（booking-lifecycle 規格）。
 *
 * 狀態機保護的是不可逆操作的合法性：completed/rejected/expired/cancelled
 * 是終態，任何繞過 VALID_TRANSITIONS 的修改都會破壞名額帳務與評價資格
 * （只有 completed 才能評價）。pending 不佔名額、confirmed 才佔名額，
 * 是防超賣帳務的基礎不變式。
 */
class BookingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    // ── helpers ─────────────────────────────────────────────

    private function makeMember(): User
    {
        return User::factory()->create(['role' => 'member']);
    }

    private function makeProvider(bool $isVerified = true): User
    {
        $provider = User::factory()->create(['role' => 'provider']);

        ProviderProfile::create([
            'user_id'     => $provider->id,
            'verification_status' => $isVerified ? 'approved' : 'unsubmitted',
        ]);

        return $provider;
    }

    private function makeOffer(User $provider): DivingOffer
    {
        return DivingOffer::create([
            'provider_id' => $provider->id,
            'title'       => 'Lifecycle Course',
            'location'    => 'Test',
            'spot'        => 'Test Spot',
            'price'       => 1000,
            'region'      => '南部',
            'rating'      => 0,
            'reviews'     => 0,
        ]);
    }

    private function makeSchedule(DivingOffer $offer, array $attributes = []): CourseSchedule
    {
        return CourseSchedule::create(array_merge([
            'diving_offer_id'      => $offer->id,
            'provider_id'          => $offer->provider_id,
            'scheduled_date'       => now()->addDays(7)->toDateString(),
            'start_time'           => '09:00',
            'max_participants'     => 4,
            'current_participants' => 0,
            'status'               => ScheduleStatus::Open,
        ], $attributes));
    }

    private function makeBooking(User $member, CourseSchedule $schedule, BookingStatus $status, int $participants = 1): Booking
    {
        return Booking::create([
            'schedule_id'  => $schedule->id,
            'member_id'    => $member->id,
            'participants' => $participants,
            'total_price'  => 1000 * $participants,
            'status'       => $status,
        ]);
    }

    // ── 建立預約（pending） ──────────────────────────────────

    public function test_member_creates_pending_booking_without_occupying_spots(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($this->makeOffer($provider));
        $member   = $this->makeMember();

        $response = $this->actingAs($member)->postJson('/api/member/bookings', [
            'schedule_id'  => $schedule->id,
            'participants' => 2,
        ]);

        $response->assertStatus(201)->assertJsonPath('data.status', 'pending');
        // 價格快照 = 單價 × 人數
        $response->assertJsonPath('data.total_price', 2000);
        // pending 不佔名額
        $this->assertSame(0, $schedule->fresh()->current_participants);
    }

    public function test_member_cannot_book_same_schedule_twice(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($this->makeOffer($provider));
        $member   = $this->makeMember();
        $this->makeBooking($member, $schedule, BookingStatus::Pending);

        $this->actingAs($member)->postJson('/api/member/bookings', [
            'schedule_id'  => $schedule->id,
            'participants' => 1,
        ])->assertStatus(422);
    }

    public function test_booking_rejected_when_participants_exceed_remaining_spots(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($this->makeOffer($provider), [
            'max_participants'     => 4,
            'current_participants' => 3,
        ]);

        $this->actingAs($this->makeMember())->postJson('/api/member/bookings', [
            'schedule_id'  => $schedule->id,
            'participants' => 2,
        ])->assertStatus(422);
    }

    public function test_booking_rejected_on_non_open_schedule(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($this->makeOffer($provider), [
            'status' => ScheduleStatus::Cancelled,
        ]);

        $this->actingAs($this->makeMember())->postJson('/api/member/bookings', [
            'schedule_id'  => $schedule->id,
            'participants' => 1,
        ])->assertStatus(422);
    }

    // ── 可見性繞過防護（provider-verification 規格） ─────────

    public function test_cannot_book_unverified_provider_course_via_schedule_id(): void
    {
        $unverified = $this->makeProvider(isVerified: false);
        $schedule   = $this->makeSchedule($this->makeOffer($unverified));

        $this->actingAs($this->makeMember())->postJson('/api/member/bookings', [
            'schedule_id'  => $schedule->id,
            'participants' => 1,
        ])->assertStatus(422)
          ->assertJsonPath('message', '此課程目前不開放預約');

        $this->assertSame(0, Booking::count());
    }

    public function test_existing_confirmed_booking_survives_provider_unverification(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($this->makeOffer($provider), ['current_participants' => 1]);
        $member   = $this->makeMember();
        $booking  = $this->makeBooking($member, $schedule, BookingStatus::Confirmed);

        // 教練在預約成立後被撤銷驗證（approved→rejected）：只擋新預約，不毀既有合約
        $provider->providerProfile->update(['verification_status' => 'rejected', 'rejection_reason' => '測試撤銷']);

        $this->actingAs($member)
            ->getJson("/api/bookings/{$booking->id}/messages")
            ->assertOk();

        $this->actingAs($provider)
            ->putJson("/api/provider/bookings/{$booking->id}/complete")
            ->assertOk();

        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
    }

    // ── Provider 確認 / 拒絕 ─────────────────────────────────

    public function test_provider_confirms_pending_booking_and_occupies_spots(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($this->makeOffer($provider));
        $booking  = $this->makeBooking($this->makeMember(), $schedule, BookingStatus::Pending, 2);

        $this->actingAs($provider)
            ->putJson("/api/provider/bookings/{$booking->id}/confirm")
            ->assertOk();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertSame(2, $schedule->fresh()->current_participants);
    }

    public function test_confirming_last_spots_marks_schedule_full(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($this->makeOffer($provider), ['max_participants' => 2]);
        $booking  = $this->makeBooking($this->makeMember(), $schedule, BookingStatus::Pending, 2);

        $this->actingAs($provider)
            ->putJson("/api/provider/bookings/{$booking->id}/confirm")
            ->assertOk();

        $this->assertSame(ScheduleStatus::Full, $schedule->fresh()->status);
    }

    public function test_provider_cannot_confirm_rejected_booking(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($this->makeOffer($provider));
        $booking  = $this->makeBooking($this->makeMember(), $schedule, BookingStatus::Rejected);

        $this->actingAs($provider)
            ->putJson("/api/provider/bookings/{$booking->id}/confirm")
            ->assertStatus(422);

        $this->assertSame(BookingStatus::Rejected, $booking->fresh()->status);
    }

    public function test_provider_rejects_pending_booking(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($this->makeOffer($provider));
        $booking  = $this->makeBooking($this->makeMember(), $schedule, BookingStatus::Pending);

        $this->actingAs($provider)
            ->putJson("/api/provider/bookings/{$booking->id}/reject")
            ->assertOk();

        $this->assertSame(BookingStatus::Rejected, $booking->fresh()->status);
        // 從未佔用名額，拒絕後也不應變動
        $this->assertSame(0, $schedule->fresh()->current_participants);
    }

    // ── 完成 ─────────────────────────────────────────────────

    public function test_provider_completes_confirmed_booking(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($this->makeOffer($provider), ['current_participants' => 1]);
        $booking  = $this->makeBooking($this->makeMember(), $schedule, BookingStatus::Confirmed);

        $this->actingAs($provider)
            ->putJson("/api/provider/bookings/{$booking->id}/complete")
            ->assertOk();

        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
    }

    public function test_provider_cannot_complete_pending_booking(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($this->makeOffer($provider));
        $booking  = $this->makeBooking($this->makeMember(), $schedule, BookingStatus::Pending);

        $this->actingAs($provider)
            ->putJson("/api/provider/bookings/{$booking->id}/complete")
            ->assertStatus(422);
    }

    // ── 取消與名額釋放 ───────────────────────────────────────

    public function test_provider_cancel_releases_spots_and_reopens_full_schedule(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($this->makeOffer($provider), [
            'max_participants'     => 2,
            'current_participants' => 2,
            'status'               => ScheduleStatus::Full,
        ]);
        $booking = $this->makeBooking($this->makeMember(), $schedule, BookingStatus::Confirmed, 2);

        $this->actingAs($provider)
            ->putJson("/api/provider/bookings/{$booking->id}/cancel")
            ->assertOk();

        $schedule->refresh();
        $this->assertSame(BookingStatus::ProviderCancelled, $booking->fresh()->status);
        $this->assertSame(0, $schedule->current_participants);
        $this->assertSame(ScheduleStatus::Open, $schedule->status);
    }

    public function test_member_cancels_pending_booking(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($this->makeOffer($provider));
        $member   = $this->makeMember();
        $booking  = $this->makeBooking($member, $schedule, BookingStatus::Pending);

        $this->actingAs($member)
            ->deleteJson("/api/member/bookings/{$booking->id}")
            ->assertOk();

        $this->assertSame(BookingStatus::MemberCancelled, $booking->fresh()->status);
    }

    public function test_member_cancel_confirmed_booking_releases_spots(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($this->makeOffer($provider), ['current_participants' => 2]);
        $member   = $this->makeMember();
        $booking  = $this->makeBooking($member, $schedule, BookingStatus::Confirmed, 2);

        $this->actingAs($member)
            ->deleteJson("/api/member/bookings/{$booking->id}")
            ->assertOk();

        $this->assertSame(BookingStatus::MemberCancelled, $booking->fresh()->status);
        $this->assertSame(0, $schedule->fresh()->current_participants);
    }

    public function test_member_cannot_cancel_within_24_hours_of_course_start(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($this->makeOffer($provider), [
            'scheduled_date' => now()->addHours(5)->toDateString(),
            'start_time'     => now()->addHours(5)->format('H:i'),
        ]);
        $member  = $this->makeMember();
        $booking = $this->makeBooking($member, $schedule, BookingStatus::Confirmed);

        $this->actingAs($member)
            ->deleteJson("/api/member/bookings/{$booking->id}")
            ->assertStatus(422);

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_member_cannot_cancel_completed_booking(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($this->makeOffer($provider));
        $member   = $this->makeMember();
        $booking  = $this->makeBooking($member, $schedule, BookingStatus::Completed);

        $this->actingAs($member)
            ->deleteJson("/api/member/bookings/{$booking->id}")
            ->assertStatus(422);
    }

    // ── 授權邊界 ─────────────────────────────────────────────

    public function test_other_provider_cannot_operate_booking(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($this->makeOffer($provider));
        $booking  = $this->makeBooking($this->makeMember(), $schedule, BookingStatus::Pending);

        $this->actingAs($this->makeProvider())
            ->putJson("/api/provider/bookings/{$booking->id}/confirm")
            ->assertStatus(403);
    }

    public function test_other_member_cannot_view_or_cancel_booking(): void
    {
        $provider = $this->makeProvider();
        $schedule = $this->makeSchedule($this->makeOffer($provider));
        $booking  = $this->makeBooking($this->makeMember(), $schedule, BookingStatus::Pending);
        $intruder = $this->makeMember();

        $this->actingAs($intruder)
            ->getJson("/api/member/bookings/{$booking->id}")
            ->assertStatus(403);

        $this->actingAs($intruder)
            ->deleteJson("/api/member/bookings/{$booking->id}")
            ->assertStatus(403);
    }
}
