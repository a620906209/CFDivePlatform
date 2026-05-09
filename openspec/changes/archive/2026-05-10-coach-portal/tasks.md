## 1. [後端] 資料庫 Migration

- [x] 1.1 建立 migration：`diving_offers` 新增 `provider_id` 欄位（`unsignedBigInteger` nullable，外鍵關聯 `users.id`，onDelete set null）
- [x] 1.2 執行 `docker exec cfdive-app php artisan migrate`，確認欄位新增成功
- [x] 1.3 更新 `DivingOffer` Model：`$fillable` 加入 `provider_id`

## 2. [後端] Provider Auth API 調整

- [x] 2.1 修改 `registerProvider()`：將 `business_name` 改為 nullable（單人教練不一定有業者名稱），驗證規則從 `required` 改為 `nullable|string|max:255`
- [x] 2.2 `loginProvider()` ✅ 直接可用，不需改動（role 驗證、token、load profile 皆正確）
- [x] 2.3 `logoutProvider()` ✅ 直接可用，不需改動
- [x] 2.4 `providerProfile()` ✅ 直接可用，不需改動
- [x] 2.5 補完 `updateProviderProfile()`：在現有更新邏輯後補上以下欄位的更新處理：
  - `certifications`（PADI / SSI 等認證資訊）
  - `dive_sites`（常駐潛點，逗號分隔字串）
  - `services`（提供服務類型）
  - `facilities`（設施說明）
  - `website`（官網連結）
  - `social_media`（社群媒體連結）
  - 同時在 Validator 規則加入這六個欄位（皆為 `nullable|string`）
- [x] 2.6 用 Postman 驗證：register（business_name 選填）→ login → GET profile → PUT profile（含新增欄位）→ logout

## 3. [後端] Coach 課程管理 API

- [x] 3.1 建立 `ProviderOfferController`，實作 `index()`：只回傳 `provider_id = auth()->id()` 的課程，含分頁
- [x] 3.2 實作 `show($id)`：`find()` null → 404；`provider_id !== auth()->id()` → 403；否則回傳課程資料
- [x] 3.3 實作 `store()`：驗證必填欄位（title / location / spot / price / region），強制將 `provider_id` 設為 `auth()->id()`（忽略 body 傳入值），回傳 201
- [x] 3.4 實作 `update($id)`：`find()` null → 404；`provider_id !== auth()->id()` → 403；更新欄位回傳 200
- [x] 3.5 實作 `destroy($id)`：`find()` null → 404；`provider_id !== auth()->id()` → 403；刪除回傳 200
- [x] 3.6 在 `routes/api.php` 的 `provider` middleware group 新增課程路由：GET(index) / GET(show) / POST / PUT / DELETE
- [x] 3.7 用 Postman 驗證：新增 → 列表 → 單筆詳情 → 更新 → 刪除；另測試跨教練操作：存在課程回 403、不存在 ID 回 404

## 4. [前端] coachAuth Store 與基礎設施

- [x] 4.1 建立 `frontend/src/stores/coachAuth.js`：管理 `coach_token` / `coach_user`，實作 `init()` / `setAuth()` / `logout()`
- [x] 4.2 建立 `frontend/src/api/coachAxios.js`：獨立 Axios instance，request interceptor 讀 `coach_token`
- [x] 4.3 在 `frontend/src/router/index.js` 新增 `/coach/*` 路由群組：login / dashboard / offers/new / offers/:id/edit / profile
- [x] 4.4 `/coach/*`（login 除外）加上 beforeEach guard，未登入導向 `/coach/login`
- [x] 4.5 在 `App.vue` 的 `onMounted` 加入 `coachAuth.init()`

## 5. [前端] Coach Layout 與導覽

- [x] 5.1 建立 `frontend/src/components/CoachNavBar.vue`：顯示教練姓名、「我的課程」、「個人資料」連結與登出按鈕
- [x] 5.2 建立 `frontend/src/layouts/CoachLayout.vue`：包含 CoachNavBar + `<RouterView>`，供所有 `/coach/*` 頁面使用

## 6. [前端] 教練認證頁面

- [x] 6.1 建立 `frontend/src/views/coach/RegisterView.vue`：帳號資訊 + 業者資訊兩段表單，送出呼叫 `POST /api/provider/register`，成功導向 `/coach/login?registered=1`，失敗顯示欄位錯誤；business_name 選填
- [x] 6.2 建立 `frontend/src/views/coach/LoginView.vue`：email/password 表單，送出呼叫 `POST /api/provider/login`，成功存 token 並導向 `/coach/dashboard`，失敗顯示錯誤；若 query 有 `?registered=1` 顯示「註冊成功，請登入」

## 7. [前端] 課程 Dashboard

- [x] 7.1 建立 `frontend/src/views/coach/DashboardView.vue`：掛載時呼叫 `GET /api/provider/offers`，以表格列出課程（標題、地點、價格）
- [x] 7.2 新增「新增課程」按鈕，點擊導向 `/coach/offers/new`
- [x] 7.3 每列新增「編輯」按鈕，點擊導向 `/coach/offers/:id/edit`
- [x] 7.4 每列新增「刪除」按鈕：顯示確認 dialog，確認後呼叫 `DELETE /api/provider/offers/{id}`，成功後重新載入列表
- [x] 7.5 無課程時顯示空狀態提示

## 8. [前端] 課程表單（新增 / 編輯）

- [x] 8.1 建立 `frontend/src/views/coach/OfferFormView.vue`（新增與編輯共用同一個組件，以 route param 判斷模式）
- [x] 8.2 欄位：title（必填）、location（必填）、spot、price（必填）、region、tag、badges（多選或逗號分隔輸入）、description
- [x] 8.3 新增模式：送出呼叫 `POST /api/provider/offers`，成功後導向 Dashboard
- [x] 8.4 編輯模式：掛載時取得課程資料預填，送出呼叫 `PUT /api/provider/offers/{id}`，成功後導向 Dashboard
- [x] 8.5 前端必填欄位驗證（title / location / price 為空時不送出）

## 9. [前端] 教練個人資料頁

- [x] 9.1 建立 `frontend/src/views/coach/ProfileView.vue`：掛載時呼叫 `GET /api/provider/profile`，顯示以下欄位：
  - 基本：name、email、phone
  - 業者：business_name（工作室/個人教練名稱）、description（自我介紹）
  - 專業：certifications（認證）、dive_sites（常駐潛點）、services（服務類型）
  - 聯絡：contact_person、contact_phone、contact_email、address、business_hours
  - 網路：website、social_media
  - 唯讀顯示（不可自改）：is_verified、rating
- [x] 9.2 實作編輯表單，送出呼叫 `PUT /api/provider/profile`（包含 task 2.5 補完的新欄位），成功顯示「資料已更新」提示

## 10. [整合測試] 端對端驗證

- [x] 10.1 驗證教練完整認證流程：註冊 → 登入 → 登出 → 重新登入
- [x] 10.2 驗證課程 CRUD：新增 → Dashboard 出現 → 編輯 → 刪除
- [x] 10.3 驗證 route guard：未登入訪問 `/coach/dashboard` 自動跳轉 `/coach/login`
- [x] 10.4 驗證權限隔離：教練 A 無法編輯/刪除教練 B 的課程（API 層回傳 403）
- [x] 10.5 驗證公開課程列表（`/courses`）能看到教練新增的課程
