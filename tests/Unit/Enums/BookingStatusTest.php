<?php

namespace Tests\Unit\Enums;

use App\Enums\BookingStatus;
use PHPUnit\Framework\TestCase;

/**
 * BookingStatus enum 完整性測試。
 * 確保 7 個狀態值與 booking-lifecycle 規格一致，
 * 防止新增/刪除狀態時忘記同步 VALID_TRANSITIONS 常數。
 */
class BookingStatusTest extends TestCase
{
    public function test_all_seven_cases_exist(): void
    {
        $values = array_map(fn ($case) => $case->value, BookingStatus::cases());

        $this->assertEqualsCanonicalizing([
            'pending',
            'confirmed',
            'completed',
            'rejected',
            'expired',
            'member_cancelled',
            'provider_cancelled',
        ], $values);
    }

    /** @dataProvider validStringProvider */
    public function test_from_returns_correct_case(string $value, BookingStatus $expected): void
    {
        $this->assertSame($expected, BookingStatus::from($value));
    }

    public static function validStringProvider(): array
    {
        return [
            ['pending',            BookingStatus::Pending],
            ['confirmed',          BookingStatus::Confirmed],
            ['completed',          BookingStatus::Completed],
            ['rejected',           BookingStatus::Rejected],
            ['expired',            BookingStatus::Expired],
            ['member_cancelled',   BookingStatus::MemberCancelled],
            ['provider_cancelled', BookingStatus::ProviderCancelled],
        ];
    }

    public function test_try_from_returns_null_for_invalid_value(): void
    {
        $this->assertNull(BookingStatus::tryFrom('invalid_status'));
        $this->assertNull(BookingStatus::tryFrom(''));
        $this->assertNull(BookingStatus::tryFrom('PENDING'));
    }

    public function test_valid_transitions_covers_all_cases(): void
    {
        $allCases = array_map(fn ($case) => $case->value, BookingStatus::cases());
        $transitionKeys = array_keys(\App\Models\Booking::VALID_TRANSITIONS);

        $this->assertEqualsCanonicalizing(
            $allCases,
            $transitionKeys,
            'VALID_TRANSITIONS 必須包含所有 BookingStatus 狀態，避免未知狀態繞過狀態機'
        );
    }
}
