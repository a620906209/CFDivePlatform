## 1. 後端 Refresh 端點

- [x] 1.1 [後端] 在 `app/Http/Controllers/API/AuthController.php` 新增 `refreshMember(Request $request)`：驗證 role=member，revoke 當前 token，發行新 token，回傳 `{ status: true, data: { token, token_type: "Bearer" } }`
- [x] 1.2 [後端] 同上新增 `refreshProvider(Request $request)`：驗證 role=provider，revoke + 發新 token
- [x] 1.3 [後端] 同上新增 `refreshAdmin(Request $request)`：驗證 role=admin，revoke + 發新 token
- [x] 1.4 [後端] 在 `routes/api.php` 新增三個 refresh 路由（需 auth:sanctum middleware）：`POST /api/member/refresh`、`POST /api/provider/refresh`、`POST /api/admin/refresh`
- [x] 1.5 [後端] 執行 `php artisan route:clear` 確認路由生效

## 2. 前端 Interceptor 改寫（Member）

- [x] 2.1 [前端] 改寫 `frontend/src/api/axios.js`：response interceptor 改為 refresh-then-retry 邏輯，加入 `isRefreshing` flag 與 `pendingRequests` queue 防止並發重複 refresh
- [x] 2.2 [前端] 確認 `axios.js` 的 request interceptor 改從 `sessionStorage` 讀取 `token`（非 localStorage）

## 3. 前端 Interceptor 改寫（Coach）

- [x] 3.1 [前端] 改寫 `frontend/src/api/coachAxios.js`：response interceptor 改為 refresh-then-retry，呼叫 `POST /api/provider/refresh`，含 `isRefreshing` + queue 邏輯
- [x] 3.2 [前端] 確認 `coachAxios.js` 的 request interceptor 改從 `sessionStorage` 讀取 `coach_token`

## 4. 前端 Store 改為 sessionStorage

- [x] 4.1 [前端] 修改 `frontend/src/stores/auth.js`：`init()`、`setAuth()`、`logout()` 中所有 `localStorage` 改為 `sessionStorage`（keys: `token`、`user`）
- [x] 4.2 [前端] 修改 `frontend/src/stores/coachAuth.js`：同上，keys: `coach_token`、`coach_user`
- [x] 4.3 [前端] 修改 `frontend/src/views/AuthCallbackView.vue`：第 21 行 `localStorage.setItem('token', token)` 改為 `sessionStorage.setItem('token', token)`

## 5. 自動化測試

- [x] 5.1 [測試] 新增 `tests/Feature/TokenRefreshTest.php`：驗證 `POST /api/member/refresh` 以有效 token 回傳新 token，且舊 token 同時失效（再打一次原 token 回 401）
- [x] 5.2 [測試] 同檔案補充：`/api/provider/refresh` 和 `/api/admin/refresh` 相同行為；跨角色呼叫（member token 打 provider/refresh）回 403
- [x] 5.3 [測試] 同檔案補充：過期 token 呼叫 refresh 回 401；已 revoke token 呼叫 refresh 回 401

## 6. 手動驗證

- [x] 6.1 [整合測試] Token 過期行為確認：將 `expires_at` 改成過去時間，打 API 確認被踢回 `/login`（refresh 端點同樣受 auth:sanctum 保護，過期 token 無法 refresh，此為預期行為。Sliding window 由 7 天有效期自然實現，主動使用期間不會到期）
- [x] 6.2 [整合測試] 多並發 401 只 refresh 一次：DevTools Network 確認只有一個 `/refresh` 請求，其他請求等待後以新 token 完成
- [x] 6.3 [整合測試] Refresh 失敗全部請求正確 reject：revoke 所有 token 後觸發 API，確認被踢回登入頁且沒有無限 loop
- [x] 6.4 [整合測試] OAuth callback 改為 sessionStorage：Google 登入後用 DevTools Application 確認 token 在 sessionStorage（非 localStorage）
- [x] 6.5 [整合測試] 關閉分頁後 token 消失：登入後關閉分頁重開，確認 sessionStorage 已清空需重新登入
- [x] 6.6 [整合測試] Role endpoint 對應正確：member token 打 `/api/provider/refresh` 回 403，不會混用
