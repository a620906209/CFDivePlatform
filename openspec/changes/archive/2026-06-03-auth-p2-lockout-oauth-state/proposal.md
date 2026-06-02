## Why

IP-based throttle（P0 已實作）只能防單一 IP 暴力攻擊，無法阻擋分散式密碼暴破（攻擊者用多 IP 對同一帳號嘗試）；OAuth 流程目前以 `stateless()` 繞過 CSRF 驗證，存在被偽造授權碼的風險。在金流整合上線前先補齊這兩個帳號層防護，可大幅降低帳號接管與 OAuth CSRF 風險。

## What Changes

- **新增帳號層鎖定機制**：Member / Provider 登入連續失敗 N 次後，鎖定該帳號（而非 IP），鎖定期間回傳 HTTP 423；鎖定計數以 Laravel Cache 儲存，N 分鐘後自動解鎖。
- **新增 OAuth state 驗證**：`SocialAuthController` 產生隨機 state 字串存入 session，callback 時驗證 state 是否吻合；不吻合時拒絕並回傳 HTTP 400；移除 `stateless()` 呼叫。

## Capabilities

### New Capabilities

- `account-lockout`：帳號層連續失敗鎖定，覆蓋 Member / Provider 登入流程（Admin 登入失敗計數另行討論，暫不列入）
- `oauth-state-validation`：OAuth CSRF 防護，補 state parameter 生成與驗證，適用 Member Google OAuth 流程

### Modified Capabilities

- `login-rate-limiting`：無需變更需求（IP throttle 與帳號鎖定並行，各自獨立，Requirement 不衝突）

## Impact

**後端**
- `app/Http/Controllers/API/AuthController.php`（Member login）
- `app/Http/Controllers/API/ProviderAuthController.php`（Provider login）
- `app/Http/Controllers/API/SocialAuthController.php`（OAuth redirect / callback）
- `routes/api.php`（可能新增解鎖端點）
- `config/auth_lockout.php`（鎖定參數：最大失敗次數、鎖定時間）
- Laravel Cache（驗證失敗計數，driver 使用現有 Redis 或 file）

**前端**
- `src/views/LoginView.vue` / `CoachLoginView.vue`：顯示「帳號已鎖定，請稍後再試」錯誤訊息（HTTP 423 對應）
- `src/plugins/echo.js` / `src/api/axios.js`：無需變更（OAuth callback 不走 Axios）
- `src/views/AuthCallbackView.vue`：無需變更（後端回傳 400 時 redirect 到 login 並帶錯誤訊息）

**不影響**
- 現有 Sanctum token、sessionStorage、refresh interceptor
- Admin Panel（Admin 登入鎖定不在此 change 範圍）
