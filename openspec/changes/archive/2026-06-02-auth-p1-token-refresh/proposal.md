## Why

P0 已設定 Bearer Token 7 天過期，但目前缺乏自動續期機制——token 過期後使用者直接被踢回登入頁，且 token 持久存在 localStorage（JS 可讀，XSS 攻擊面）。引入 token refresh 搭配 sessionStorage 可同時解決「過期 UX 不佳」和「token 洩漏視窗過長」兩個問題，且兩者必須一起實作才有意義。

## What Changes

- **後端**：新增三個 refresh 端點（`POST /api/member/refresh`、`POST /api/provider/refresh`、`POST /api/admin/refresh`），接受現有 Bearer token，revoke 舊 token 並發行新 7 天 token（sliding window）
- **前端 axios interceptor**：將現有「401 直接登出」改為「401 → 先嘗試 refresh → 成功則 retry 原始請求 → 失敗才登出」；同時處理多個並發 401 只發一次 refresh 請求
- **前端 token 儲存**：`auth.js`、`coachAuth.js` 的 localStorage 改為 sessionStorage；`axios.js`、`coachAxios.js` interceptor 讀取來源同步更新
- **`AuthCallbackView.vue`**：Google OAuth callback 存 token 改寫 sessionStorage

## Capabilities

### New Capabilities

- `token-refresh`: 三個角色的 refresh API 端點（revoke 舊 token + 發行新 token）；前端 axios refresh-then-retry 攔截邏輯，含並發 refresh 去重

### Modified Capabilities

- `member-portal-ui`: `認證狀態管理` 行為變更——token 從 localStorage 改存 sessionStorage；新增自動 refresh 行為（401 時先 refresh 再 retry）
- `coach-portal-ui`: `Coach 路由守衛` 讀取的 auth state 改以 sessionStorage 為來源；同樣支援 refresh-then-retry
- `provider-auth`: `Bearer Token 有效期` 新增 sliding window 行為——主動使用 API 的 session 可透過 refresh 無限延續，不需重新登入
- `admin-auth`: `管理員 Bearer Token 有效期` 同上，sliding window via refresh

## Impact

- **後端**：`app/Http/Controllers/API/AuthController.php`（新增 refresh 方法）、`routes/api.php`（新增 refresh 路由）
- **前端**：`frontend/src/api/axios.js`、`frontend/src/api/coachAxios.js`（interceptor 重寫）、`frontend/src/stores/auth.js`、`frontend/src/stores/coachAuth.js`（sessionStorage）、`frontend/src/views/AuthCallbackView.vue`
- **行為差異**：sessionStorage 為 per-tab，多個分頁不共享登入狀態（與現有 localStorage 共享行為不同）
- **無 DB schema 變更**：使用現有 `personal_access_tokens` 表，refresh 透過 revoke + createToken 實作
