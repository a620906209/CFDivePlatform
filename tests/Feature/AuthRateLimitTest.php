<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ── member (throttle:10,1) ───────────────────────────────

    public function test_member_login_within_limit_is_not_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/member/login', [
                'email'    => 'notexist@example.com',
                'password' => 'wrong',
            ]);
            $this->assertNotEquals(429, $response->status());
        }
    }

    public function test_member_login_exceeds_limit_returns_429(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/member/login', [
                'email'    => 'notexist@example.com',
                'password' => 'wrong',
            ]);
        }

        $response = $this->postJson('/api/member/login', [
            'email'    => 'notexist@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
        $this->assertTrue($response->headers->has('Retry-After'));
    }

    // ── provider (throttle:10,1) ─────────────────────────────

    public function test_provider_login_exceeds_limit_returns_429(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/provider/login', [
                'email'    => 'notexist@example.com',
                'password' => 'wrong',
            ]);
        }

        $response = $this->postJson('/api/provider/login', [
            'email'    => 'notexist@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
        $this->assertTrue($response->headers->has('Retry-After'));
    }

    // ── admin (throttle:3,1) ─────────────────────────────────

    public function test_admin_login_within_limit_is_not_throttled(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/api/admin/login', [
                'email'    => 'notexist@example.com',
                'password' => 'wrong',
            ]);
            $this->assertNotEquals(429, $response->status());
        }
    }

    public function test_admin_login_exceeds_stricter_limit_returns_429(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/admin/login', [
                'email'    => 'notexist@example.com',
                'password' => 'wrong',
            ]);
        }

        $response = $this->postJson('/api/admin/login', [
            'email'    => 'notexist@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
        $this->assertTrue($response->headers->has('Retry-After'));
    }
}
