<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Exceptions\BookingRuleViolationException;
use App\Models\Booking;
use App\Models\CourseSchedule;
use App\Models\DivingOffer;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * 預約交易的錯誤分類：業務規則失敗回 422，其他例外交給 Laravel 例外處理器。
 *
 * 原本兩個 Controller 以 catch (\RuntimeException) 接住交易內的錯誤，而
 * QueryException 也是 RuntimeException 的子類別，資料庫錯誤因此以 422
 * 把 SQL 與資料表結構回給使用者，且不會被回報到 Sentry。
 */
class BookingExceptionHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Exceptions::fake();
    }

    private function makeProviderWithSchedule(int $maxParticipants, int $currentParticipants = 0): array
    {
        $provider = User::factory()->create(['role' => 'provider']);

        ProviderProfile::create([
            'user_id' => $provider->id,
            'verification_status' => 'approved',
        ]);

        $offer = DivingOffer::create([
            'provider_id' => $provider->id,
            'title' => 'Exception Course',
            'location' => 'Test',
            'spot' => 'Test Spot',
            'price' => 1000,
            'region' => '南部',
            'rating' => 0,
            'reviews' => 0,
        ]);

        $schedule = CourseSchedule::create([
            'diving_offer_id' => $offer->id,
            'provider_id' => $provider->id,
            'scheduled_date' => now()->addDays(7)->toDateString(),
            'start_time' => '09:00',
            'max_participants' => $maxParticipants,
            'current_participants' => $currentParticipants,
            'status' => ScheduleStatus::Open,
        ]);

        return [$provider, $schedule];
    }

    private function makePendingBooking(CourseSchedule $schedule, int $participants, ?User $member = null): Booking
    {
        return Booking::create([
            'schedule_id' => $schedule->id,
            'member_id' => ($member ?? User::factory()->create(['role' => 'member']))->id,
            'participants' => $participants,
            'total_price' => 1000 * $participants,
            'status' => BookingStatus::Pending,
        ]);
    }

    // 刻意不用 "database is locked" 這類併發錯誤訊息：巢狀交易（RefreshDatabase）中 Laravel 會改拋 DeadlockException
    private function makeQueryException(string $sql): QueryException
    {
        return new QueryException('sqlite', $sql, [], new \PDOException('SQLSTATE[23000]: Integrity constraint violation'));
    }

    /**
     * 業務失敗的訊息是前端直接顯示給使用者的文字，改型別不能改到它；
     * 也不應回報到 Sentry，否則正常的業務拒絕會淹沒真正的伺服器故障。
     */
    public function test_duplicate_booking_returns_422_with_rule_message(): void
    {
        [, $schedule] = $this->makeProviderWithSchedule(5);
        $member = User::factory()->create(['role' => 'member']);
        $this->makePendingBooking($schedule, 1, $member);

        $this->actingAs($member)
            ->postJson('/api/member/bookings', ['schedule_id' => $schedule->id, 'participants' => 1])
            ->assertStatus(422)
            ->assertExactJson(['status' => false, 'message' => '您已預約此時段']);

        $this->assertSame(1, Booking::where('member_id', $member->id)->where('schedule_id', $schedule->id)->count());
        Exceptions::assertNotReported(BookingRuleViolationException::class);
    }

    /**
     * 交易內名額不足只在併發時發生：交易前的檢查通過後，另一筆預約搶先佔位。
     * 只改動第二次讀取（交易內 lockForUpdate），第一次讀取若被改動會被前置檢查攔下，測不到這條路徑。
     */
    public function test_capacity_lost_inside_transaction_returns_422_with_rule_message(): void
    {
        [, $schedule] = $this->makeProviderWithSchedule(2);
        $member = User::factory()->create(['role' => 'member']);

        $retrievalCount = 0;
        CourseSchedule::retrieved(function (CourseSchedule $retrieved) use (&$retrievalCount) {
            $retrievalCount++;
            if ($retrievalCount === 2) {
                $retrieved->current_participants = $retrieved->max_participants;
            }
        });

        $this->actingAs($member)
            ->postJson('/api/member/bookings', ['schedule_id' => $schedule->id, 'participants' => 1])
            ->assertStatus(422)
            ->assertExactJson(['status' => false, 'message' => '名額不足，請重新選擇']);

        $this->assertSame(0, Booking::count());
        Exceptions::assertNotReported(BookingRuleViolationException::class);
    }

    /**
     * 確認時的名額檢查是防超賣的最後一道防線，其 422 訊息與「不留下部分寫入」都必須維持。
     */
    public function test_confirm_without_capacity_returns_422_with_rule_message(): void
    {
        [$provider, $schedule] = $this->makeProviderWithSchedule(2, 1);
        $booking = $this->makePendingBooking($schedule, 2);

        $this->actingAs($provider)
            ->putJson("/api/provider/bookings/{$booking->id}/confirm")
            ->assertStatus(422)
            ->assertExactJson(['status' => false, 'message' => '名額不足，無法確認此預約']);

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
        $this->assertSame(1, $schedule->fresh()->current_participants);
        Exceptions::assertNotReported(BookingRuleViolationException::class);
    }

    /**
     * 資料庫錯誤曾以 422 把 SQL 回給使用者且不會送到 Sentry。
     * 例外在 INSERT 之後才拋出，筆數為 0 才能證明交易確實 rollback。
     */
    public function test_database_error_on_create_returns_500_without_sql(): void
    {
        config(['app.debug' => false]);
        [, $schedule] = $this->makeProviderWithSchedule(5);
        $member = User::factory()->create(['role' => 'member']);

        Booking::created(function () {
            throw $this->makeQueryException('insert into "bookings" ("schedule_id", "member_id", "participants") values (?, ?, ?)');
        });

        $this->actingAs($member)
            ->postJson('/api/member/bookings', ['schedule_id' => $schedule->id, 'participants' => 1])
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Server Error'])
            ->assertDontSee('insert into', false)
            ->assertDontSee('bookings', false)
            ->assertDontSee('member_id', false);

        Exceptions::assertReported(QueryException::class);
        $this->assertSame(0, Booking::count());
    }

    /**
     * 同上，針對確認預約。例外在預約狀態更新之後才拋出，預約仍為 pending 才能證明 rollback。
     */
    public function test_database_error_on_confirm_returns_500_without_sql(): void
    {
        config(['app.debug' => false]);
        [$provider, $schedule] = $this->makeProviderWithSchedule(5);
        $booking = $this->makePendingBooking($schedule, 2);

        CourseSchedule::updating(function () {
            throw $this->makeQueryException('update "course_schedules" set "current_participants" = "current_participants" + 2 where "id" = ?');
        });

        $this->actingAs($provider)
            ->putJson("/api/provider/bookings/{$booking->id}/confirm")
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Server Error'])
            ->assertDontSee('update', false)
            ->assertDontSee('course_schedules', false)
            ->assertDontSee('current_participants', false);

        Exceptions::assertReported(QueryException::class);
        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
        $this->assertSame(0, $schedule->fresh()->current_participants);
    }
}
