<?php

namespace Tests\Unit\Traits;

use App\Traits\NormalizesEmail;
use PHPUnit\Framework\TestCase;

/**
 * NormalizesEmail::normalizeEmail() 是純字串邏輯。
 * 帳號鎖定計數器（AuthLockoutTest）依賴此邏輯確保大小寫與空白
 * 不會被視為不同帳號而繞過鎖定——這裡驗證邏輯本身，
 * 而非驗證它被正確呼叫（那是 AuthLockoutTest 的職責）。
 */
class NormalizesEmailTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();

        // normalizeEmail 是 private，透過匿名類別暴露供測試
        $this->subject = new class {
            use NormalizesEmail;

            public function normalize(string $email): string
            {
                return $this->normalizeEmail($email);
            }
        };
    }

    public function test_uppercase_is_lowercased(): void
    {
        $this->assertSame('user@example.com', $this->subject->normalize('USER@EXAMPLE.COM'));
    }

    public function test_leading_and_trailing_whitespace_is_trimmed(): void
    {
        $this->assertSame('user@example.com', $this->subject->normalize('  user@example.com  '));
    }

    public function test_mixed_case_and_whitespace_are_both_normalized(): void
    {
        $this->assertSame('user@example.com', $this->subject->normalize('  User@Example.COM  '));
    }

    public function test_already_normalized_email_is_unchanged(): void
    {
        $this->assertSame('user@example.com', $this->subject->normalize('user@example.com'));
    }

    public function test_tab_whitespace_is_trimmed(): void
    {
        $this->assertSame('user@example.com', $this->subject->normalize("\tuser@example.com\t"));
    }
}
