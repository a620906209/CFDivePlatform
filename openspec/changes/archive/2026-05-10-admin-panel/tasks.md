## 1. [後端] Admin Auth — 確認現有方法可用

- [x] 1.1 `loginAdmin()` ✅ 直接可用，確認 role=admin 驗證邏輯正確
- [x] 1.2 `logoutAdmin()` ✅ 直接可用，不需改動
- [x] 1.3 `adminProfile()` ✅ 直接可用，不需改動
- [x] 1.4 用 Postman 建立測試用 admin 帳號：`docker exec cfdive-app php artisan tinker`，建立 role=admin 的 User + AdminProfile，測試 login → profile → logout

## 2. [後端] AdminStatsController

- [x] 2.1 建立 `AdminStatsController`，實作 `index()`：驗證 role=admin，查詢 `User::where('role','member')->count()`、`User::where('role','provider')->count()`、`DivingOffer::count()`，回傳統計數據
- [x] 2.2 在 `routes/api.php` 的 admin middleware group 新增 `GET /stats` 路由

## 3. [後端] AdminUserController

- [x] 3.1 建立 `AdminUserController`，宣告 private `checkAdmin()` helper（驗證 role=admin，不符回傳 403）
- [x] 3.2 實作 `members(Request $request)`：搜尋 role=member 用戶（q 參數 LIKE name/email），load memberProfile，分頁 15 筆
- [x] 3.3 實作 `member(int $id)`：find role=member 用戶，不存在回 404，load memberProfile 後回傳
- [x] 3.4 實作 `toggleMemberActive(int $id)`：find → 404，反轉 is_active，回傳新狀態與對應訊息
- [x] 3.5 實作 `providers(Request $request)`：同 members，查 role=provider，load providerProfile
- [x] 3.6 實作 `provider(int $id)`：同 member，查 role=provider，load providerProfile
- [x] 3.7 實作 `toggleProviderActive(int $id)`：同 toggleMemberActive，查 role=provider
- [x] 3.8 實作 `toggleProviderVerified(int $id)`：find role=provider → 404，取得 providerProfile，反轉 is_verified，儲存，回傳新狀態
- [x] 3.9 在 `routes/api.php` admin group 新增路由：
  - `GET /members`、`GET /members/{id}`、`PUT /members/{id}/toggle-active`
  - `GET /providers`、`GET /providers/{id}`、`PUT /providers/{id}/toggle-active`、`PUT /providers/{id}/toggle-verified`

## 4. [後端] AdminOfferController

- [x] 4.1 建立 `AdminOfferController`，實作 `index()`：驗證 admin，搜尋所有課程（q 參數 LIKE title/location），分頁 15 筆
- [x] 4.2 實作 `destroy(int $id)`：find → 404，刪除，回傳 200
- [x] 4.3 在 routes 新增 `GET /offers`、`DELETE /offers/{id}`

## 5. [前端] Admin 基礎設施

- [x] 5.1 建立 `frontend/src/stores/adminAuth.js`：管理 `admin_token` / `admin_user`，實作 `init()` / `setAuth()` / `logout()`
- [x] 5.2 建立 `frontend/src/api/adminAxios.js`：獨立 Axios instance，request interceptor 讀 `admin_token`
- [x] 5.3 在 `frontend/src/router/index.js` 新增 `/admin/*` 路由：login（public）+ dashboard / members / providers / offers / profile（requiresAdmin）
- [x] 5.4 router `beforeEach` 加入 `requiresAdmin` guard，未登入導向 `/admin/login`
- [x] 5.5 在 `App.vue` 的 `onMounted` 加入 `adminAuth.init()`，並擴充 `isCoachPage` → `isBackofficePage`（涵蓋 `/coach/*` 和 `/admin/*`），會員 NavBar 在這兩個路徑下都不顯示

## 6. [前端] Admin Layout 與導覽

- [x] 6.1 建立 `frontend/src/components/AdminNavBar.vue`：顯示管理員姓名、「儀表板」、「會員管理」、「教練管理」、「課程管理」連結與登出按鈕
- [x] 6.2 建立 `frontend/src/layouts/AdminLayout.vue`：包含 AdminNavBar + `<RouterView>`

## 7. [前端] 管理員登入頁

- [x] 7.1 建立 `frontend/src/views/admin/LoginView.vue`：email/password 表單，送出呼叫 `POST /api/admin/login`，成功存 token 至 adminAuth store 並導向 `/admin/dashboard`，失敗顯示錯誤

## 8. [前端] 儀表板

- [x] 8.1 建立 `frontend/src/views/admin/DashboardView.vue`：掛載時呼叫 `GET /api/admin/stats`，以三個數字卡片顯示總會員數、總教練數、總課程數

## 9. [前端] 會員管理頁

- [x] 9.1 建立 `frontend/src/views/admin/MembersView.vue`：掛載時呼叫 `GET /api/admin/members`，以表格顯示姓名、email、帳號狀態（啟用/停用 badge）
- [x] 9.2 新增搜尋框，輸入後按 Enter 重新呼叫 API（帶 q 參數）
- [x] 9.3 每列新增啟用/停用按鈕，呼叫 `PUT /api/admin/members/{id}/toggle-active`，成功後更新該列狀態

## 10. [前端] 教練管理頁

- [x] 10.1 建立 `frontend/src/views/admin/ProvidersView.vue`：掛載時呼叫 `GET /api/admin/providers`，顯示姓名、email、工作室名稱、驗證狀態、帳號狀態
- [x] 10.2 新增搜尋框
- [x] 10.3 每列新增啟用/停用按鈕（呼叫 toggle-active）與驗證/取消驗證按鈕（呼叫 toggle-verified），成功後更新對應欄位

## 11. [前端] 課程管理頁

- [x] 11.1 建立 `frontend/src/views/admin/OffersView.vue`：掛載時呼叫 `GET /api/admin/offers`，顯示課程標題、地點、地區、價格、provider_id
- [x] 11.2 新增搜尋框
- [x] 11.3 每列新增刪除按鈕：顯示確認 dialog，確認後呼叫 `DELETE /api/admin/offers/{id}`，成功後重新載入列表

## 12. [整合測試] 端對端驗證

- [x] 12.1 驗證管理員登入流程：tinker 建立 admin 帳號 → 登入 → 顯示 AdminNavBar → 登出
- [x] 12.2 驗證 Dashboard 統計數據正確顯示
- [x] 12.3 驗證會員管理：搜尋 → 停用 → 確認帳號無法登入 → 重新啟用
- [x] 12.4 驗證教練驗證：切換 is_verified → 確認 /coach/profile 顯示狀態更新
- [x] 12.5 驗證課程刪除：Admin 刪除課程 → 確認 /courses 列表消失
- [x] 12.6 驗證 route guard：未登入訪問 `/admin/dashboard` 自動跳轉 `/admin/login`
- [x] 12.7 驗證 `/admin/*` 路由不顯示會員 NavBar
