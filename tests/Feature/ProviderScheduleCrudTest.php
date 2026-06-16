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
 * Provider 時段管理（course-scheduling 規格）。
 *
 * 容量不變式：max_participants 不可低於 current_participants，
 * 否則已確認名額會被靜默截斷；destroy 必須同步取消進行中預約，
 * 不處理會讓 member 對不存在/取消的時段保有 pending/confirmed 狀態。
 */
class ProviderScheduleCrudTest extends TestCase
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
            'title'       => 'Dive Course',
            'location'    => 'Kenting',
            'price'       => 3000,
            'region'      => '南部',
            'rating'      => 0,
            'reviews'     => 0,
        ]);
    }

    private function makeSchedule(DivingOffer $offer, int $currentParticipants = 0): CourseSchedule
    {
        return CourseSchedule::create([
            'diving_offer_id'      => $offer->id,
            'provider_id'          => $offer->provider_id,
            'scheduled_date'       => now()->addDays(30)->toDateString(),
            'start_time'           => '09:00',
            'max_participants'     => 10,
            'current_participants' => $currentParticipants,
            'status'               => ScheduleStatus::Open,
        ]);
    }

    // ── store ────────────────────────────────────────────────

    public function test_provider_creates_schedule_successfully(): void
    {
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);

        $response = $this->actingAs($provider)->postJson('/api/provider/schedules', [
            'diving_offer_id'  => $offer->id,
            'scheduled_date'   => now()->addDays(10)->toDateString(),
            'start_time'       => '09:00',
            'max_participants' => 8,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', 'open');
    }

    public function test_provider_cannot_create_schedule_for_others_offer(): void
    {
        $ownerProvider = $this->makeProvider();
        $otherProvider = $this->makeProvider();
        $offer         = $this->makeOffer($ownerProvider);

        $this->actingAs($otherProvider)->postJson('/api/provider/schedules', [
            'diving_offer_id'  => $offer->id,
            'scheduled_date'   => now()->addDays(10)->toDateString(),
            'start_time'       => '09:00',
            'max_participants' => 8,
        ])->assertStatus(403);
    }

    public function test_past_date_returns_422(): void
    {
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);

        $this->actingAs($provider)->postJson('/api/provider/schedules', [
            'diving_offer_id'  => $offer->id,
            'scheduled_date'   => now()->subDay()->toDateString(),
            'start_time'       => '09:00',
            'max_participants' => 8,
        ])->assertStatus(422);
    }

    // ── update ───────────────────────────────────────────────

    public function test_provider_updates_schedule_max_participants(): void
    {
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);
        $schedule = $this->makeSchedule($offer);

        $this->actingAs($provider)
            ->putJson("/api/provider/schedules/{$schedule->id}", ['max_participants' => 20])
            ->assertOk()
            ->assertJsonPath('data.max_participants', 20);

        $this->assertDatabaseHas('course_schedules', [
            'id'               => $schedule->id,
            'max_participants' => 20,
        ]);
    }

    public function test_max_participants_below_current_returns_422(): void
    {
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);
        $schedule = $this->makeSchedule($offer, currentParticipants: 3);

        $this->actingAs($provider)
            ->putJson("/api/provider/schedules/{$schedule->id}", ['max_participants' => 2])
            ->assertStatus(422);
    }

    public function test_provider_cannot_update_others_schedule(): void
    {
        $ownerProvider = $this->makeProvider();
        $otherProvider = $this->makeProvider();
        $offer         = $this->makeOffer($ownerProvider);
        $schedule      = $this->makeSchedule($offer);

        $this->actingAs($otherProvider)
            ->putJson("/api/provider/schedules/{$schedule->id}", ['max_participants' => 20])
            ->assertStatus(403);
    }

    // ── destroy ──────────────────────────────────────────────

    public function test_delete_schedule_marks_active_bookings_as_provider_cancelled(): void
    {
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);
        $schedule = $this->makeSchedule($offer);
        $member   = User::factory()->create(['role' => 'member']);

        $pendingBooking = Booking::create([
            'schedule_id'  => $schedule->id,
            'member_id'    => $member->id,
            'participants' => 1,
            'total_price'  => 3000,
            'status'       => BookingStatus::Pending,
        ]);

        $confirmedBooking = Booking::create([
            'schedule_id'  => $schedule->id,
            'member_id'    => User::factory()->create(['role' => 'member'])->id,
            'participants' => 1,
            'total_price'  => 3000,
            'status'       => BookingStatus::Confirmed,
        ]);

        $completedBooking = Booking::create([
            'schedule_id'  => $schedule->id,
            'member_id'    => User::factory()->create(['role' => 'member'])->id,
            'participants' => 1,
            'total_price'  => 3000,
            'status'       => BookingStatus::Completed,
        ]);

        $this->actingAs($provider)
            ->deleteJson("/api/provider/schedules/{$schedule->id}")
            ->assertOk();

        $this->assertDatabaseHas('course_schedules', [
            'id'     => $schedule->id,
            'status' => ScheduleStatus::Cancelled->value,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id'     => $pendingBooking->id,
            'status' => BookingStatus::ProviderCancelled->value,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id'     => $confirmedBooking->id,
            'status' => BookingStatus::ProviderCancelled->value,
        ]);
        // 終態 booking 不受影響
        $this->assertDatabaseHas('bookings', [
            'id'     => $completedBooking->id,
            'status' => BookingStatus::Completed->value,
        ]);
    }
}
