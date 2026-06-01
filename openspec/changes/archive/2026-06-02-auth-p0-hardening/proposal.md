## Why

目前認證機制存在三個高危漏洞：Token 永不過期（一旦洩漏攻擊者永久持有存取權）、Google OAuth callback 將 Sanctum token 附在 URL query string（出現在 server log、瀏覽器歷史、Referer header），以及登入端點無頻率限制（暴力破解無防護）。三項缺陷均可在不入侵伺服器的前提下被利用。

## What Changes

- `config/sanctum.php`：將 `expiration` 從 `null` 改為 `10080`（7 天 × 24 小時 × 60 分鐘）
- `SocialAuthController::handleGoogleCallback()`：redirect URL 改用 `#token=` fragment 取代 `?token=` query string
- `AuthCallbackView.vue`：改從 `window.location.hash` 讀取 token，不再使用 `route.query.token`
- `routes/api.php`：對 member / provider / admin 三個登入端點套用 `throttle:5,1` middleware

## Capabilities

### New Capabilities

- `login-rate-limiting`: 登入端點的請求頻率限制——每個 IP 每分鐘最多 5 次，超過回傳 429

### Modified Capabilities

- `provider-auth`: 新增登入 rate limiting 場景；新增 token 有效期行為（7 天後失效）
- `admin-auth`: 新增登入 rate limiting 場景；新增 token 有效期行為（7 天後失效）
- `member-portal-ui`: Google OAuth callback 行為變更——token 改由 URL fragment 傳遞，前端改從 hash 讀取

## Impact

- **後端**：`config/sanctum.php`、`app/Http/Controllers/API/SocialAuthController.php`、`routes/api.php`
- **前端**：`frontend/src/views/AuthCallbackView.vue`
- **現有 token**：`expiration` 設定僅影響**新建立**的 token，既有的 `personal_access_tokens` 不受影響（`expires_at` 欄位為 null 的舊 token 仍長期有效），需另行清除或接受自然淘汰
- **無破壞性變更**：API 介面、response 格式、前端路由均不改變
