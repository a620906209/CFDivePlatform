## MODIFIED Requirements

### Requirement: 登入頁
前端 SHALL 提供 `/login` 頁面，供會員以 email/password 登入，以及 Google OAuth 登入入口。

#### Scenario: Email/Password 登入成功
- **WHEN** 使用者填入正確的 email 與 password 並送出
- **THEN** 呼叫 `POST /api/member/login`，儲存回傳的 token 至 localStorage，導航至 `/courses`

#### Scenario: 登入失敗
- **WHEN** 使用者填入錯誤的 email 或 password
- **THEN** 頁面顯示錯誤訊息，不跳轉

#### Scenario: Google OAuth 登入
- **WHEN** 使用者點擊「以 Google 登入」按鈕
- **THEN** 瀏覽器導航至後端 `GET /api/auth/google/redirect`，開始 OAuth 流程

#### Scenario: 超過登入頻率限制
- **WHEN** 同一 IP 在 1 分鐘內送出超過 5 次登入請求
- **THEN** 前端顯示適當的錯誤訊息（對應後端回傳的 HTTP 429）

## ADDED Requirements

### Requirement: Google OAuth Callback 處理
前端 SHALL 在 `/auth/callback` 路由讀取 URL fragment（`#token=<value>`）取得 Sanctum token，完成 OAuth 登入後將 token 存入 localStorage 並導航至 `/courses`。token 不得透過 URL query string 傳遞。

#### Scenario: OAuth callback 成功取得 token
- **WHEN** 後端 OAuth callback redirect 到 `/auth/callback#token=<token>`
- **THEN** 前端從 `window.location.hash` 解析 token，呼叫 `/api/member/profile` 取得使用者資料，呼叫 `auth.setAuth()` 儲存認證狀態，並導航至 `/courses`

#### Scenario: OAuth callback 缺少 token
- **WHEN** redirect 到 `/auth/callback` 但 hash 中無 `token` 參數
- **THEN** 前端導航至 `/login?error=oauth_failed`，顯示錯誤訊息

#### Scenario: URL 中不留存 token
- **WHEN** callback 頁面成功處理 token 後
- **THEN** 瀏覽器網址列不顯示 token（使用 `history.replaceState` 清除 hash）
