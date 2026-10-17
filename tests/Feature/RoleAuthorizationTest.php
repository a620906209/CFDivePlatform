<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\CourseSchedule;
use App\Models\DivingOffer;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Member / Provider 路由群組角色邊界（role-authorization 規格）。
 *
 * 在 EnsureMember / EnsureProvider 之前，任何已登入帳號都能跨角色呼叫：
 * member 可建立課程（provider_id 被設成自己）、provider 可建立預約。
 * 這裡驗證角色不符一律在 middleware 層回 403 且不產生資料；
 * POST 請求都帶通過驗證的完整資料，確保 403 不是被 422 或資源檢查遮蓋。
 */
class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const FORBIDDEN_BODY = ['status' => false, 'message' => '無權限存取'];

    private function createProvider(string $verificationStatus = 'approved'): User
    {
        $provider = User::factory()->create(['role' => 'provider']);
        ProviderProfile::create(['user_id' => $provider->id, 'verification_status' => $verificationStatus]);

        return $provider;
    }

    private function createOfferWithOpenSchedule(User $provider): CourseSchedule
    {
        $offer = DivingOffer::create([
            'provider_id' => $provider->id,
            'title' => 'Dive Course',
            'location' => 'Kenting',
            'price' => 3000,
            'region' => '南部',
            'rating' => 0,
            'reviews' => 0,
        ]);

        return CourseSchedule::create([
            'diving_offer_id' => $offer->id,
            'provider_id' => $provider->id,
            'scheduled_date' => now()->addDays(30)->toDateString(),
            'start_time' => '09:00',
            'max_participants' => 10,
            'current_participants' => 0,
            'status' => ScheduleStatus::Open,
        ]);
    }

    private function validOfferPayload(): array
    {
        return [
            'title' => 'Cross Role Course',
            'location' => 'Green Island',
            'price' => 2000,
            'region' => '東部',
        ];
    }

    private function assertForbidden(User $user, string $method, string $uri, array $payload = []): void
    {
        $this->actingAs($user)
            ->json($method, $uri, $payload)
            ->assertStatus(403)
            ->assertExactJson(self::FORBIDDEN_BODY);
    }

    // ── Member 路由 ──────────────────────────────────────────────

    public function test_member_can_access_member_routes(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)->getJson('/api/member/profile')->assertOk()->assertJsonPath('status', true);
        $this->actingAs($member)->getJson('/api/member/bookings')->assertOk()->assertJsonPath('status', true);
    }

    public function test_provider_is_rejected_on_member_routes(): void
    {
        $provider = $this->createProvider();
        $schedule = $this->createOfferWithOpenSchedule($provider);

        $this->assertForbidden($provider, 'GET', '/api/member/profile');
        $this->assertForbidden($provider, 'GET', '/api/member/bookings');
        $this->assertForbidden($provider, 'POST', '/api/member/bookings', [
            'schedule_id' => $schedule->id,
            'participants' => 1,
        ]);
        $this->assertForbidden($provider, 'POST', '/api/member/reviews', [
            'diving_offer_id' => $schedule->diving_offer_id,
            'rating' => 5,
            'comment' => 'self review',
        ]);

        // provider 為自己的課程寫評價會灌水評分
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_admin_is_rejected_on_member_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $schedule = $this->createOfferWithOpenSchedule($this->createProvider());

        $this->assertForbidden($admin, 'GET', '/api/member/profile');
        $this->assertForbidden($admin, 'GET', '/api/member/bookings');
        $this->assertForbidden($admin, 'POST', '/api/member/bookings', [
            'schedule_id' => $schedule->id,
            'participants' => 1,
        ]);
    }

    public function test_non_member_booking_attempt_creates_no_booking(): void
    {
        $provider = $this->createProvider();
        $schedule = $this->createOfferWithOpenSchedule($provider);

        $this->assertForbidden($provider, 'POST', '/api/member/bookings', [
            'schedule_id' => $schedule->id,
            'participants' => 2,
        ]);

        // 越權預約若寫入會佔用真實學員的名額
        $this->assertDatabaseCount('bookings', 0);
        $this->assertSame(0, $schedule->fresh()->current_participants);
    }

    public function test_unauthenticated_request_is_rejected_on_member_routes(): void
    {
        $this->getJson('/api/member/profile')->assertStatus(401);
        $this->getJson('/api/member/bookings')->assertStatus(401);
    }

    // ── Provider 路由 ────────────────────────────────────────────

    public function test_provider_can_access_provider_routes(): void
    {
        $provider = $this->createProvider();

        foreach (['/api/provider/profile', '/api/provider/offers', '/api/provider/schedules', '/api/provider/bookings'] as $uri) {
            $this->actingAs($provider)->getJson($uri)->assertOk()->assertJsonPath('status', true);
        }
    }

    public function test_unapproved_provider_can_access_verification(): void
    {
        // 未通過審核的教練必須能進入 provider 路由才能送審或補件
        foreach (['unsubmitted', 'pending', 'rejected'] as $status) {
            $provider = $this->createProvider($status);

            $this->actingAs($provider)
                ->getJson('/api/provider/verification')
                ->assertOk()
                ->assertJsonPath('data.verification_status', $status);
        }
    }

    public function test_member_is_rejected_on_provider_routes(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $schedule = $this->createOfferWithOpenSchedule($this->createProvider());

        $this->assertForbidden($member, 'GET', '/api/provider/profile');
        $this->assertForbidden($member, 'GET', '/api/provider/offers');
        $this->assertForbidden($member, 'POST', '/api/provider/offers', $this->validOfferPayload());
        $this->assertForbidden($member, 'POST', '/api/provider/schedules', [
            'diving_offer_id' => $schedule->diving_offer_id,
            'scheduled_date' => now()->addDays(10)->toDateString(),
            'start_time' => '10:00',
            'max_participants' => 5,
        ]);
        $this->assertForbidden($member, 'GET', '/api/provider/bookings');
        $this->assertForbidden($member, 'GET', '/api/provider/verification');

        $this->assertDatabaseCount('course_schedules', 1);
    }

    public function test_admin_is_rejected_on_provider_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertForbidden($admin, 'GET', '/api/provider/profile');
        $this->assertForbidden($admin, 'GET', '/api/provider/offers');
        $this->assertForbidden($admin, 'POST', '/api/provider/offers', $this->validOfferPayload());
        $this->assertForbidden($admin, 'GET', '/api/provider/bookings');
    }

    public function test_non_provider_offer_attempt_creates_no_offer(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $this->assertForbidden($member, 'POST', '/api/provider/offers', $this->validOfferPayload());

        // 越權建立的課程 provider_id 會是 member，出現在公開列表
        $this->assertDatabaseCount('diving_offers', 0);
    }

    public function test_unauthenticated_request_is_rejected_on_provider_routes(): void
    {
        $this->getJson('/api/provider/profile')->assertStatus(401);
        $this->getJson('/api/provider/offers')->assertStatus(401);
    }

    // ── 跨角色共用路由 ───────────────────────────────────────────

    public function test_member_and_provider_can_read_notifications(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $provider = $this->createProvider();

        $this->actingAs($member)->getJson('/api/notifications')->assertOk();
        $this->actingAs($provider)->getJson('/api/notifications')->assertOk();
    }

    public function test_member_and_provider_can_read_unread_message_counts(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $provider = $this->createProvider();

        $this->actingAs($member)->getJson('/api/bookings/messages/unread-counts')->assertOk();
        $this->actingAs($provider)->getJson('/api/bookings/messages/unread-counts')->assertOk();
    }
}
