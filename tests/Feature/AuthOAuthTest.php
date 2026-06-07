<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class AuthOAuthTest extends TestCase
{
    use RefreshDatabase;

    // ── helpers ─────────────────────────────────────────────

    /**
     * 組一個假的 Google 使用者物件，餵給 mock 過的 Socialite。
     * 測試不應該真的打 Google API（會很慢、不穩定、還要真帳密），
     * 所以用 Mockery 把 Socialite 換成假的，並回傳這個假資料。
     */
    private function fakeGoogleUser(string $id, string $email, string $name = 'Google Test User'): SocialiteUser
    {
        $user        = new SocialiteUser();
        $user->id    = $id;
        $user->name  = $name;
        $user->email = $email;
        $user->token = 'fake-access-token-' . $id;

        return $user;
    }

    // ── state 缺失 / 不符 ────────────────────────────────────
    //
    // 「state」是 OAuth 流程裡用來防 CSRF 的一次性亂碼：後端發起登入時把它存進
    // session，Google callback 回來時要原樣帶回，兩邊對得上才繼續，對不上就視為
    // 攻擊／偽造請求，直接擋下、不跟 Google 要使用者資料。
    // 下面兩個測試就是在驗證「對不上的時候真的有擋下」這個安全底線。

    public function test_oauth_callback_without_state_redirects_to_error(): void
    {
        // shouldReceive('driver')->never()：斷言 Socialite::driver(...) 整個流程
        // 都「不應該被呼叫」。這是防回歸的關鍵——萬一未來有人改壞了驗證順序，
        // 導致系統在 state 比對「之前」就先去問 Google 拿使用者資料，這裡就會爆紅。
        Socialite::shouldReceive('driver')->never();

        $response = $this->get('/api/auth/google/callback');

        $response->assertRedirectContains('error=oauth_failed');
    }

    public function test_oauth_callback_with_wrong_state_redirects_to_error(): void
    {
        Socialite::shouldReceive('driver')->never();

        $response = $this->withSession(['oauth_state' => 'correct-state'])
                         ->get('/api/auth/google/callback?state=wrong-state');

        $response->assertRedirectContains('error=oauth_failed');
    }

    // ── state 正確 ───────────────────────────────────────────

    public function test_oauth_callback_with_correct_state_completes_login(): void
    {
        // shouldReceive('driver->user')：模擬「Socialite::driver('google')->user()」這條
        // 鏈式呼叫，讓它回傳上面準備好的假 Google 使用者，藉此驗證「state 對得上時，
        // 系統會繼續走完登入流程並帶著 token 導回前端」。
        Socialite::shouldReceive('driver->user')
                 ->andReturn($this->fakeGoogleUser('google-state-ok', 'state-ok@example.com'));

        $response = $this->withSession(['oauth_state' => 'matching-state'])
                         ->get('/api/auth/google/callback?state=matching-state');

        $response->assertRedirectContains('#token=');
    }

    public function test_oauth_state_is_consumed_after_successful_callback(): void
    {
        // 驗證「state 是一次性的」：用過一次就該從 session 移除，
        // 避免同一組 state 被重放（replay attack）拿來再次完成登入。
        Socialite::shouldReceive('driver->user')
                 ->andReturn($this->fakeGoogleUser('google-one-time', 'one-time@example.com'));

        // 第一次：state 正確，完成登入；session 中的 oauth_state 在驗證時被 pull() 消耗
        $this->withSession(['oauth_state' => 'one-time-state'])
             ->get('/api/auth/google/callback?state=one-time-state')
             ->assertRedirectContains('#token=');

        // 第二次：再帶相同 state 呼叫，但 session 已無 oauth_state，視同 state 缺失
        $response = $this->get('/api/auth/google/callback?state=one-time-state');

        $response->assertRedirectContains('error=oauth_failed');
    }
}
