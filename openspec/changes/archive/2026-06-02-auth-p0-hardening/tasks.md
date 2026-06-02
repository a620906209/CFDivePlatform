## 1. Token 過期時間設定

- [x] 1.1 [後端] 修改 `config/sanctum.php`：將 `'expiration' => null` 改為 `'expiration' => 60 * 24 * 7`（10080 分鐘）
- [x] 1.2 [後端] 在 `routes/console.php` 新增排程：`Schedule::command('sanctum:prune-expired --hours=168')->daily();`，清除累積的過期 token 記錄
- [x] 1.3 [後端] 執行 `php artisan config:clear` 確認設定生效

## 2. Google OAuth Token 洩漏修復

- [x] 2.1 [後端] 修改 `app/Http/Controllers/API/SocialAuthController.php` 第 109、111 行：`env('FRONTEND_URL')` 改為 `config('app.frontend_url')`（防止 `config:cache` 後 env() 回傳 null），同時將第 109 行 `?token=` 改為 `#token=`（URL fragment）
- [x] 2.2 [前端] 修改 `frontend/src/views/AuthCallbackView.vue`：將讀取 `route.query.token` 改為讀取 `window.location.hash`（`new URLSearchParams(window.location.hash.substring(1)).get('token')`）

## 3. 登入端點 Rate Limiting

- [x] 3.1 [後端] 修改 `routes/api.php`：對 `POST /api/member/login` 套用 `throttle:5,1` middleware
- [x] 3.2 [後端] 修改 `routes/api.php`：對 `POST /api/provider/login` 套用 `throttle:5,1` middleware
- [x] 3.3 [後端] 修改 `routes/api.php`：對 `POST /api/admin/login` 套用 `throttle:3,1` middleware（管理員帳號影響範圍更廣，採用更嚴格限制）
- [x] 3.4 [後端] 執行 `php artisan route:clear` 清除路由快取

## 4. 自動化測試

- [x] 4.1 [測試] 在 `tests/Feature/AuthRateLimitTest.php` 建立 Feature test：驗證 `POST /api/member/login` 第 6 次請求回傳 HTTP 429，並斷言 response header 含 `Retry-After`
- [x] 4.2 [測試] 在同一 test file 補充：`POST /api/provider/login` 第 6 次回傳 429；`POST /api/admin/login` 第 4 次回傳 429（admin 限制為 throttle:3,1）
- [x] 4.3 [測試] 在 `tests/Feature/AuthRateLimitTest.php` 補充：正常登入（5 次以內）不受 throttle 影響，回傳 200 或 401

## 5. 手動驗證

- [x] 5.1 [整合測試] 驗證 Google OAuth 登入流程：登入後確認 Nginx access log 中 `/auth/google/callback` 的請求 URL 不含 token
- [x] 5.2 [整合測試] 驗證 Token 過期：在 `personal_access_tokens` 表手動將 `expires_at` 設為過去時間，確認 API 回傳 401 並自動登出
- [x] 5.3 [整合測試] 執行 `php artisan sanctum:prune-expired --hours=168`，確認 `personal_access_tokens` 中過期記錄被清除
- [x] 5.4 [整合測試] 驗證正常登入流程不受影響：member / provider 帳號各測試一次完整登入 → 操作 → 登出流程
