<?php

namespace Tests\Feature;

use App\Broadcasting\BookingPresenceChannel;
use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\CourseSchedule;
use App\Models\DivingOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 預約聊天室授權（booking-chat / user-presence 規格）。
 *
 * 聊天內容含個資與交易細節，授權失守等於任意使用者可讀他人對話。
 * 防線有二：HTTP 端點的參與方檢查、presence channel 的訂閱驗證，
 * 兩者都必須擋住非參與方與非 confirmed 狀態。
 */
class BookingChatAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $member;
    private User $provider;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member   = User::factory()->create(['role' => 'member']);
        $this->provider = User::factory()->create(['role' => 'provider']);

        $offer = DivingOffer::create([
            'provider_id' => $this->provider->id,
            'title'       => 'Chat Course',
            'location'    => 'Test',
            'spot'        => 'Test Spot',
            'price'       => 1000,
            'region'      => '南部',
            'rating'      => 0,
            'reviews'     => 0,
        ]);

        $schedule = CourseSchedule::create([
            'diving_offer_id'      => $offer->id,
            'provider_id'          => $this->provider->id,
            'scheduled_date'       => now()->addDays(7)->toDateString(),
            'start_time'           => '09:00',
            'max_participants'     => 4,
            'current_participants' => 1,
            'status'               => ScheduleStatus::Open,
        ]);

        $this->booking = Booking::create([
            'schedule_id'  => $schedule->id,
            'member_id'    => $this->member->id,
            'participants' => 1,
            'total_price'  => 1000,
            'status'       => BookingStatus::Confirmed,
        ]);
    }

    // ── HTTP 端點授權 ────────────────────────────────────────

    public function test_participants_can_read_messages_on_confirmed_booking(): void
    {
        $this->actingAs($this->member)
            ->getJson("/api/bookings/{$this->booking->id}/messages")
            ->assertOk();

        $this->actingAs($this->provider)
            ->getJson("/api/bookings/{$this->booking->id}/messages")
            ->assertOk();
    }

    public function test_outsider_member_cannot_read_messages(): void
    {
        $outsider = User::factory()->create(['role' => 'member']);

        $this->actingAs($outsider)
            ->getJson("/api/bookings/{$this->booking->id}/messages")
            ->assertStatus(403);
    }

    public function test_other_provider_cannot_read_messages(): void
    {
        $otherProvider = User::factory()->create(['role' => 'provider']);

        $this->actingAs($otherProvider)
            ->getJson("/api/bookings/{$this->booking->id}/messages")
            ->assertStatus(403);
    }

    public function test_messages_forbidden_on_pending_booking(): void
    {
        $this->booking->update(['status' => BookingStatus::Pending]);

        $this->actingAs($this->member)
            ->getJson("/api/bookings/{$this->booking->id}/messages")
            ->assertStatus(403);
    }

    public function test_outsider_cannot_send_message(): void
    {
        $outsider = User::factory()->create(['role' => 'member']);

        $this->actingAs($outsider)
            ->postJson("/api/bookings/{$this->booking->id}/messages", ['body' => 'hi'])
            ->assertStatus(403);
    }

    // ── Presence channel 訂閱驗證 ────────────────────────────

    public function test_presence_channel_admits_both_participants(): void
    {
        $channel = new BookingPresenceChannel();

        $memberResult = $channel->join($this->member, $this->booking);
        $this->assertIsArray($memberResult);
        $this->assertSame($this->member->id, $memberResult['user_id']);

        $providerResult = $channel->join($this->provider, $this->booking);
        $this->assertIsArray($providerResult);
        $this->assertSame('provider', $providerResult['user_type']);
    }

    public function test_presence_channel_rejects_outsiders(): void
    {
        $channel = new BookingPresenceChannel();

        $this->assertFalse($channel->join(User::factory()->create(['role' => 'member']), $this->booking));
        $this->assertFalse($channel->join(User::factory()->create(['role' => 'provider']), $this->booking));
    }

    public function test_presence_channel_rejects_non_confirmed_booking(): void
    {
        $this->booking->update(['status' => BookingStatus::Pending]);
        $this->booking->refresh();

        $channel = new BookingPresenceChannel();

        $this->assertFalse($channel->join($this->member, $this->booking));
    }
}
