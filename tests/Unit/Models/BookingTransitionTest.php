<?php

namespace Tests\Unit\Models;

use App\Enums\BookingStatus;
use App\Models\Booking;
use PHPUnit\Framework\TestCase;

/**
 * Booking::canTransitionTo() 是純 PHP 邏輯（只讀 VALID_TRANSITIONS 常數），
 * 不需要資料庫連線，用 PHPUnit 原生 TestCase 而非 Laravel TestCase。
 *
 * 規格依據：booking-lifecycle spec VALID_TRANSITIONS 表。
 * 終態（completed/rejected/expired/member_cancelled/provider_cancelled）不可再轉換，
 * 破壞此不變式會導致名額帳務錯誤與評價資格污染。
 */
class BookingTransitionTest extends TestCase
{
    private function bookingWithStatus(BookingStatus $status): Booking
    {
        $booking = new Booking();
        $booking->status = $status;
        return $booking;
    }

    // ── pending 合法轉換 ─────────────────────────────────────

    public function test_pending_can_transition_to_confirmed(): void
    {
        $this->assertTrue(
            $this->bookingWithStatus(BookingStatus::Pending)
                ->canTransitionTo(BookingStatus::Confirmed)
        );
    }

    public function test_pending_can_transition_to_rejected(): void
    {
        $this->assertTrue(
            $this->bookingWithStatus(BookingStatus::Pending)
                ->canTransitionTo(BookingStatus::Rejected)
        );
    }

    public function test_pending_can_transition_to_expired(): void
    {
        $this->assertTrue(
            $this->bookingWithStatus(BookingStatus::Pending)
                ->canTransitionTo(BookingStatus::Expired)
        );
    }

    public function test_pending_can_transition_to_member_cancelled(): void
    {
        $this->assertTrue(
            $this->bookingWithStatus(BookingStatus::Pending)
                ->canTransitionTo(BookingStatus::MemberCancelled)
        );
    }

    // ── pending 非法轉換 ─────────────────────────────────────

    public function test_pending_cannot_transition_to_completed(): void
    {
        $this->assertFalse(
            $this->bookingWithStatus(BookingStatus::Pending)
                ->canTransitionTo(BookingStatus::Completed)
        );
    }

    public function test_pending_cannot_transition_to_provider_cancelled(): void
    {
        $this->assertFalse(
            $this->bookingWithStatus(BookingStatus::Pending)
                ->canTransitionTo(BookingStatus::ProviderCancelled)
        );
    }

    // ── confirmed 合法轉換 ───────────────────────────────────

    public function test_confirmed_can_transition_to_completed(): void
    {
        $this->assertTrue(
            $this->bookingWithStatus(BookingStatus::Confirmed)
                ->canTransitionTo(BookingStatus::Completed)
        );
    }

    public function test_confirmed_can_transition_to_member_cancelled(): void
    {
        $this->assertTrue(
            $this->bookingWithStatus(BookingStatus::Confirmed)
                ->canTransitionTo(BookingStatus::MemberCancelled)
        );
    }

    public function test_confirmed_can_transition_to_provider_cancelled(): void
    {
        $this->assertTrue(
            $this->bookingWithStatus(BookingStatus::Confirmed)
                ->canTransitionTo(BookingStatus::ProviderCancelled)
        );
    }

    // ── confirmed 非法轉換 ───────────────────────────────────

    public function test_confirmed_cannot_transition_to_pending(): void
    {
        $this->assertFalse(
            $this->bookingWithStatus(BookingStatus::Confirmed)
                ->canTransitionTo(BookingStatus::Pending)
        );
    }

    public function test_confirmed_cannot_transition_to_rejected(): void
    {
        $this->assertFalse(
            $this->bookingWithStatus(BookingStatus::Confirmed)
                ->canTransitionTo(BookingStatus::Rejected)
        );
    }

    public function test_confirmed_cannot_transition_to_expired(): void
    {
        $this->assertFalse(
            $this->bookingWithStatus(BookingStatus::Confirmed)
                ->canTransitionTo(BookingStatus::Expired)
        );
    }

    // ── 終態：不可再轉換 ─────────────────────────────────────

    /** @dataProvider terminalStatuses */
    public function test_terminal_status_cannot_transition_to_any_state(BookingStatus $terminal): void
    {
        $booking = $this->bookingWithStatus($terminal);

        foreach (BookingStatus::cases() as $target) {
            $this->assertFalse(
                $booking->canTransitionTo($target),
                "終態 {$terminal->value} 不應能轉換至 {$target->value}"
            );
        }
    }

    public static function terminalStatuses(): array
    {
        return [
            'completed'          => [BookingStatus::Completed],
            'rejected'           => [BookingStatus::Rejected],
            'expired'            => [BookingStatus::Expired],
            'member_cancelled'   => [BookingStatus::MemberCancelled],
            'provider_cancelled' => [BookingStatus::ProviderCancelled],
        ];
    }
}
