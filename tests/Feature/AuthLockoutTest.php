<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * P2 帳號鎖定機制：同一帳號連續登入失敗達 5 次就鎖定，回傳 423 + locked_until。
 * 失敗次數記在 cache key `login_failures:{role}:{email}`，
 * 鎖定到期時間記在 companion key `login_expires_at:{role}:{email}`。
 * 採 Fixed Window（固定視窗）策略：視窗只在第一次失敗時開啟、寫入到期時間，
 * 之後的失敗只累加次數、不會重設或延長視窗（區別於會「越測越鎖越久」的 Sliding Window）。
 */
class AuthLockoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Cache 不像 DB 有 RefreshDatabase 幫忙重置，上一個測試殘留的失敗計數
        // 會直接影響下一個測試的鎖定門檻判斷，所以每個測試前都要手動清空。
        Cache::flush();
    }

    // ── helpers ─────────────────────────────────────────────

    private function createMember(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role'      => 'member',
            'is_active' => true,
            'password'  => Hash::make('password123'),
        ], $attributes));
    }

    private function createProvider(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role'      => 'provider',
            'is_active' => true,
            'password'  => Hash::make('password123'),
        ], $attributes));
    }

    private function failMemberLogin(string $email, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->postJson('/api/member/login', ['email' => $email, 'password' => 'wrong-password']);
        }
    }

    // ── Fixed Window 計數 ────────────────────────────────────

    public function test_four_failures_do_not_lock_account(): void
    {
        $email = 'lockout1@example.com';
        $this->createMember(['email' => $email]);

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/member/login', ['email' => $email, 'password' => 'wrong-password'])
                 ->assertStatus(401);
        }

        // 帳號未鎖定：用正確密碼仍可登入（若已鎖定，無論密碼對錯都會回傳 423）
        $this->postJson('/api/member/login', ['email' => $email, 'password' => 'password123'])
             ->assertStatus(200);
    }

    public function test_fifth_failure_triggers_423_with_locked_until(): void
    {
        $email = 'lockout2@example.com';
        $this->createMember(['email' => $email]);

        $this->failMemberLogin($email, 4);

        $response = $this->postJson('/api/member/login', ['email' => $email, 'password' => 'wrong-password']);

        $response->assertStatus(423)
                 ->assertJsonPath('status', false)
                 ->assertJsonStructure(['locked_until']);

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $response->json('locked_until'),
            'locked_until 應為 ISO 8601 格式'
        );
    }

    public function test_locked_account_rejects_correct_password(): void
    {
        $email = 'lockout3@example.com';
        $this->createMember(['email' => $email]);

        $this->failMemberLogin($email, 5);

        $this->postJson('/api/member/login', ['email' => $email, 'password' => 'password123'])
             ->assertStatus(423);
    }

    public function test_nonexistent_email_does_not_increment_counter(): void
    {
        // 防的是「用不存在的 email 灌爆 cache」這種資源耗盡攻擊：
        // 帳號不存在時，系統不該為它建立任何計數 key（因為帳號本來就鎖不了）。
        $email = 'nosuchaccount@example.com';

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/member/login', ['email' => $email, 'password' => 'whatever'])
                 ->assertStatus(401);
        }

        $this->assertNull(Cache::get("login_failures:member:{$email}"));
    }

    public function test_successful_login_clears_failure_counter(): void
    {
        // 驗證「失敗計數會在成功登入後歸零」：避免使用者偶爾打錯密碼幾次、
        // 之後成功登入了，卻因為舊的失敗次數一直累積，下次只是再打錯 1、2 次
        // 就被鎖定——那會讓鎖定機制變得對正常使用者太敏感。
        $email = 'lockout4@example.com';
        $this->createMember(['email' => $email]);

        $this->failMemberLogin($email, 3);

        $this->postJson('/api/member/login', ['email' => $email, 'password' => 'password123'])
             ->assertStatus(200);

        // 計數已從 0 重新累積：再失敗 4 次仍應是 401（未達閾值）
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/member/login', ['email' => $email, 'password' => 'wrong-password'])
                 ->assertStatus(401);
        }
    }

    // ── Email 正規化 ─────────────────────────────────────────
    //
    // 如果 cache key 是用「使用者輸入的原始字串」組出來的，攻擊者只要每次
    // 換個大小寫或多敲一個空白，就能讓系統誤判成不同帳號、產生新的計數 key，
    // 藉此繞過鎖定機制無限期暴力破解。下面兩個測試確認系統有把 email
    // 正規化（轉小寫、去頭尾空白）後再記錄，讓變體仍然落在同一個 key 上。

    public function test_email_case_normalization_counts_same_account(): void
    {
        $email = 'normalize-case@example.com';
        $this->createMember(['email' => $email]);

        $this->failMemberLogin($email, 3);

        $this->postJson('/api/member/login', ['email' => 'Normalize-Case@EXAMPLE.COM', 'password' => 'wrong-password'])
             ->assertStatus(401);

        // 累計第 5 次失敗（大小寫變體正規化後計入同一個 cache key）
        $this->postJson('/api/member/login', ['email' => 'Normalize-Case@EXAMPLE.COM', 'password' => 'wrong-password'])
             ->assertStatus(423);
    }

    public function test_email_trim_normalization_counts_same_account(): void
    {
        $email = 'normalize-trim@example.com';
        $this->createMember(['email' => $email]);

        $this->failMemberLogin($email, 3);

        $this->postJson('/api/member/login', ['email' => '  normalize-trim@example.com  ', 'password' => 'wrong-password'])
             ->assertStatus(401);

        // 累計第 5 次失敗（前後空白變體正規化後計入同一個 cache key）
        $this->postJson('/api/member/login', ['email' => '  normalize-trim@example.com  ', 'password' => 'wrong-password'])
             ->assertStatus(423);
    }

    // ── 角色隔離 ─────────────────────────────────────────────

    public function test_member_login_attempts_do_not_affect_provider_lockout_for_same_email(): void
    {
        // users.email 在 DB 層級全域 unique，無法同時存在同 email 的 member + provider 帳號，
        // 故改驗證「對錯誤角色 endpoint 嘗試不會汙染或鎖定正確角色帳號」這個對等的安全性質。
        $email = 'cross-namespace@example.com';
        $this->createProvider(['email' => $email]);

        // member namespace 中沒有此帳號：失敗不增加計數
        $this->failMemberLogin($email, 4);
        $this->assertNull(Cache::get("login_failures:member:{$email}"));

        // provider namespace 中累積 4 次失敗，未達閾值
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/provider/login', ['email' => $email, 'password' => 'wrong-password'])
                 ->assertStatus(401);
        }
        $this->assertSame(4, Cache::get("login_failures:provider:{$email}"));

        // provider 帳號仍可用正確密碼正常登入（未被鎖定）
        $this->postJson('/api/provider/login', ['email' => $email, 'password' => 'password123'])
             ->assertStatus(200);
    }

    // ── 解鎖與 companion key ─────────────────────────────────

    public function test_account_can_login_after_lockout_entry_removed(): void
    {
        // 鎖定不是永久的——cache key 帶 TTL，到期後會自動消失。
        // 測試裡沒辦法真的等待 TTL 倒數，所以用 Cache::forget 直接模擬「TTL 到期、
        // entry 被清掉」之後的狀態，驗證帳號會自動恢復可登入（不需要任何人手動解鎖）。
        $email = 'lockout5@example.com';
        $this->createMember(['email' => $email]);

        $this->failMemberLogin($email, 5);
        $this->postJson('/api/member/login', ['email' => $email, 'password' => 'password123'])
             ->assertStatus(423);

        // 模擬 TTL 自然過期：移除 lockout cache entry
        Cache::forget("login_failures:member:{$email}");
        Cache::forget("login_expires_at:member:{$email}");

        $this->postJson('/api/member/login', ['email' => $email, 'password' => 'password123'])
             ->assertStatus(200);
    }

    public function test_locked_until_comes_from_companion_cache_key(): void
    {
        // 確認 API 回傳給前端的 locked_until，跟後端實際拿來判斷「何時可以解鎖」
        // 的 companion cache key 是同一個值——避免兩邊各算各的，導致前端顯示的
        // 倒數時間跟後端真正解鎖的時間對不上。
        $email = 'lockout6@example.com';
        $this->createMember(['email' => $email]);

        $this->failMemberLogin($email, 4);

        $response = $this->postJson('/api/member/login', ['email' => $email, 'password' => 'wrong-password']);
        $response->assertStatus(423);

        $expiresAtFromCache = Cache::get("login_expires_at:member:{$email}");

        $this->assertNotNull($expiresAtFromCache);
        $this->assertSame($expiresAtFromCache, $response->json('locked_until'));
    }

    public function test_repeated_failures_do_not_extend_lockout_window(): void
    {
        // 這條在區分 Fixed Window 與 Sliding Window 兩種策略：
        // 如果每次失敗都重新計算 locked_until，攻擊者（或誤觸的使用者）只要持續嘗試，
        // 鎖定視窗就會被無限往後推遲，等同於永久鎖死。Fixed Window 的作法是
        // 「視窗在第一次失敗時就固定下來」，之後不論再失敗幾次，到期時間都不變。
        $email = 'lockout7@example.com';
        $this->createMember(['email' => $email]);

        $this->failMemberLogin($email, 1);
        $firstExpiresAt = Cache::get("login_expires_at:member:{$email}");
        $this->assertNotNull($firstExpiresAt);

        $this->failMemberLogin($email, 3);
        $expiresAtAfterMoreFailures = Cache::get("login_expires_at:member:{$email}");

        // Fixed Window：companion key 只在第一次失敗時寫入，後續失敗只遞增計數，不重設視窗
        $this->assertSame($firstExpiresAt, $expiresAtAfterMoreFailures);
    }
}
