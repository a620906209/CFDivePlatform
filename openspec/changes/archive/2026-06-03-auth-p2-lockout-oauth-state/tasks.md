## 1. 帳號鎖定設定檔與共用 Helper

- [x] 1.1 [後端] 建立 `config/auth_lockout.php`，定義 `max_attempts`（env `LOCKOUT_MAX_ATTEMPTS`, 預設 5）與 `decay_minutes`（env `LOCKOUT_DECAY_MINUTES`, 預設 15）
- [x] 1.2 [後端] 建立 `app/Traits/NormalizesEmail.php` Trait，定義 `normalizeEmail(string $email): string`（`strtolower(trim($email))`），在 `AuthController` 與 `ProviderAuthController` 均 use 此 Trait

## 2. Member 帳號鎖定邏輯

- [x] 2.1 [後端] 修改 `app/Http/Controllers/API/AuthController.php` → `loginMember()`：對 request email 執行 `strtolower(trim())` 正規化後，先查 Cache key `login_failures:member:{email}`，若已達閾值讀取 `login_expires_at:member:{email}` 回傳 HTTP 423（含 `locked_until`）
- [x] 2.2 [後端] DB 查無帳號時直接回傳 HTTP 401（訊息與密碼錯誤相同），**不**遞增計數
- [x] 2.3 [後端] 登入失敗（帳號存在但密碼錯）時：若 `login_failures` key 不存在則用 `Cache::put()` 設初始值 1 並同步寫入 `login_expires_at` companion key（TTL 相同）；若 key 已存在則只 `Cache::increment()`（不重設 TTL）；回傳 423 時讀取 `login_expires_at` key，若缺失則 fallback 為 `now()->addMinutes($decay_minutes)` 並寫 `Log::warning`
- [x] 2.4 [後端] 登入成功時 `Cache::forget('login_failures:member:{email}')` 與 `Cache::forget('login_expires_at:member:{email}')`

## 3. Provider 帳號鎖定邏輯

- [x] 3.1 [後端] 修改 `app/Http/Controllers/API/ProviderAuthController.php` → `loginProvider()`：同 Member 邏輯（email 正規化、不存在帳號不遞增、companion key、fixed window），Cache key 前綴改為 `login_failures:provider:` / `login_expires_at:provider:`
- [x] 3.2 [後端] 登入成功時清除兩個 Cache key（failures + expires_at）

## 4. OAuth State 驗證

- [x] 4.1 [後端] 修改 `app/Http/Controllers/API/SocialAuthController.php` → `redirectToProvider()`：移除 `stateless()`，產生 `bin2hex(random_bytes(32))` 存 `session('oauth_state')`，附加 `state` 於 redirect URL
- [x] 4.2 [後端] 修改 `handleProviderCallback()`：移除 `stateless()`，讀取 `request->state` 與 `session('oauth_state')` 比對；不符或缺失時回傳 HTTP 400；比對後立即 `session()->forget('oauth_state')`

## 5. 前端錯誤處理

- [x] 5.1 [前端] 修改 `frontend/src/views/LoginView.vue`：axios catch 中判斷 `error.response?.status === 423`，顯示 `response.data.message`（帳號鎖定提示），而非泛用錯誤
- [x] 5.2 [前端] 修改 `frontend/src/views/CoachLoginView.vue`：同上，處理 423 狀態碼
- [x] 5.3 [前端] 修改 `frontend/src/views/AuthCallbackView.vue`：當後端 OAuth callback 回傳 400 時，redirect 至 `/login?error=oauth_failed`
- [x] 5.4 [前端] 修改 `frontend/src/views/LoginView.vue`：若 `route.query.error === 'oauth_failed'`，顯示「OAuth 授權失敗，請重新嘗試」提示

## 6. 整合驗證

- [x] 6.1 [整合測試] 手動測試 Member 登入：連續失敗 5 次後收到 423，等待 15 分鐘後或改正確密碼（需清除 Cache）後可登入
- [x] 6.2 [整合測試] 手動測試 Provider 登入：同上
- [x] 6.3 [整合測試] 手動測試 OAuth：正常流程可完成；直接訪問 callback URL（無 state）收到 400 並 redirect 至 `/login?error=oauth_failed`
- [x] 6.4 [整合測試] 確認 `storage/framework/sessions` 目錄在 Docker 容器中有寫入權限（session 存 state 不報錯）
- [x] 6.5 [部署] 確認生產環境 `.env` 設定 `CACHE_DRIVER=redis`；驗證方式：`php artisan tinker` → `Cache::put('test', 1, 10); Cache::get('test');` 回傳 1 即 Redis 正常
