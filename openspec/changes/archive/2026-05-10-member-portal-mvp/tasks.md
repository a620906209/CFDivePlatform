## 1. [後端] 環境與 CORS 設定

- [x] 1.1 在 `.env` 新增 `FRONTEND_URL=http://localhost:5173`、`GOOGLE_CLIENT_ID`、`GOOGLE_CLIENT_SECRET`、`GOOGLE_REDIRECT_URI=http://localhost:80/api/auth/google/callback`
- [x] 1.2 執行 `php artisan config:publish cors` 建立 `config/cors.php`，設定 `allowed_origins=[FRONTEND_URL]`、`allowed_methods`、`allowed_headers`、`supports_credentials=false`（參考 design.md Contract 3）
- [x] 1.3 確認 `bootstrap/app.php`（或 `app/Http/Kernel.php`）已啟用 `HandleCors` middleware

## 2. [後端] 修正 Google OAuth Callback

- [x] 2.1 修改 `SocialAuthController::handleGoogleCallback()`：成功時改為 `redirect(env('FRONTEND_URL') . '/auth/callback?token=' . $token)`
- [x] 2.2 修改 catch 區塊：失敗時改為 `redirect(env('FRONTEND_URL') . '/login?error=oauth_failed')`
- [x] 2.3 手動測試 OAuth 流程：點擊 Google 登入後確認瀏覽器最終落在 `:5173/auth/callback?token=...`

## 3. [後端] Diving Offers API

- [x] 3.1 更新 `DivingOffer` Model：設定 `$fillable`、`$table`，`badges` 欄位加上 `$casts = ['badges' => 'array']` 自動 JSON decode
- [x] 3.2 建立 `DivingOfferController`，實作 `index()` 方法（支援 q / region / tag 篩選，分頁預設 12 筆，max 50）
- [x] 3.3 實作 `show($id)` 方法：找不到時回傳 `{ "status": false, "message": "課程不存在" }`（HTTP 404）
- [x] 3.4 在 `routes/api.php` 新增公開路由：`GET /diving-offers` 和 `GET /diving-offers/{id}`
- [x] 3.5 用 Postman 驗證：列表（含 q / region / tag / 分頁）、詳情、404 情境，確認 response 結構符合 design.md Contract 1

## 4. [前端] 專案初始化

- [x] 4.1 在 Laravel repo 外建立新目錄 `cf-dive-frontend`，執行 `npm create vite@latest . -- --template vue`
- [x] 4.2 安裝依賴：`npm install`，再安裝 `vue-router@4 pinia axios`
- [x] 4.3 安裝並設定 Tailwind CSS（`tailwindcss postcss autoprefixer`，初始化 `tailwind.config.js`）
- [x] 4.4 建立 `.env` 文件，設定 `VITE_API_URL=http://localhost:80`
- [x] 4.5 建立 `src/api/axios.js`：設定 Axios instance，base URL 讀自 `import.meta.env.VITE_API_URL`，request interceptor 讀 localStorage token 並附加 `Authorization: Bearer <token>`
- [x] 4.6 建立 `src/stores/auth.js`：Pinia store 管理 `user`、`token`、`isLoggedIn`，`init()` 從 localStorage 還原狀態
- [x] 4.7 設定 Vue Router（`src/router/index.js`）：定義所有路由（含 `/auth/callback`），`/profile` 加上 beforeEach navigation guard（未登入導向 `/login`）
- [x] 4.8 在 `App.vue` 呼叫 `authStore.init()`，並加入 `<RouterView>`
- [x] 4.9 執行 `npm run dev`，確認開發環境正常啟動無錯誤

## 5. [前端] Layout 與共用組件

- [x] 5.1 建立 `src/components/NavBar.vue`：顯示 logo、「探索課程」連結，已登入顯示「個人資料」和「登出」，未登入顯示「登入」和「註冊」
- [x] 5.2 建立 `src/components/CourseCard.vue`：接收 offer 資料，顯示標題、地點、價格、評分、標籤

## 6. [前端] 首頁

- [x] 6.1 建立 `src/views/HomeView.vue`：Hero section（平台名稱、簡介）+ 「探索課程」CTA 按鈕，點擊導向 `/courses`

## 7. [前端] 課程列表頁

- [x] 7.1 建立 `src/views/CoursesView.vue`，掛載時呼叫 `GET /api/diving-offers`，渲染 `CourseCard` 列表
- [x] 7.2 新增搜尋框：輸入後按 Enter 或點搜尋重新呼叫 API（帶 `q` 參數）
- [x] 7.3 新增地區下拉選單：選擇後以 `region` 參數重新呼叫 API
- [x] 7.4 處理無結果狀態：顯示「找不到符合的課程」提示

## 8. [前端] 課程詳情頁

- [x] 8.1 建立 `src/views/CourseDetailView.vue`，掛載時呼叫 `GET /api/diving-offers/:id`
- [x] 8.2 顯示課程完整資訊：標題、地點、景點、價格、評分、評論數、描述、徽章（badges 陣列）、標籤
- [x] 8.3 處理 404 情境：顯示「課程不存在」並提供「返回列表」按鈕

## 9. [前端] 認證頁面

- [x] 9.1 建立 `src/views/LoginView.vue`：email/password 表單，送出呼叫 `POST /api/member/login`，成功存 token + user 至 Pinia 並導向 `/courses`，失敗顯示錯誤訊息
- [x] 9.2 在 `LoginView.vue` 加入「以 Google 登入」按鈕：點擊執行 `window.location.href = VITE_API_URL + '/api/auth/google/redirect'`
- [x] 9.3 建立 `src/views/AuthCallbackView.vue`（路由 `/auth/callback`）：讀取 `?token=` query param → 存入 Pinia + localStorage → 呼叫 `history.replaceState` 清除 URL token → 導向 `/courses`；若 `?error=oauth_failed` 則導向 `/login` 並顯示錯誤提示
- [x] 9.4 建立 `src/views/RegisterView.vue`：name / email / password / password_confirmation 表單，送出呼叫 `POST /api/member/register`，成功導向 `/login` 並顯示成功提示，失敗顯示錯誤

## 10. [前端] 會員個人資料頁

- [x] 10.1 建立 `src/views/ProfileView.vue`，掛載時呼叫 `GET /api/member/profile`，顯示姓名、email、生日、性別、地址、緊急聯絡人
- [x] 10.2 實作編輯表單：使用者修改後點擊「儲存」呼叫 `PUT /api/member/profile`，成功顯示「資料已更新」提示

## 11. [整合測試] 端對端驗證

- [x] 11.1 驗證訪客流程：首頁 → 課程列表（搜尋/篩選）→ 課程詳情（無需登入）
- [x] 11.2 驗證 Email 認證流程：註冊 → 登入 → 個人資料 → 登出
- [x] 11.3 驗證 Google OAuth 流程：點擊 Google 登入 → 同意 → 回到前端 `/auth/callback` → 自動存 token → 導向課程列表
- [x] 11.4 驗證 navigation guard：未登入直接訪問 `/profile` 自動跳轉至 `/login`
- [x] 11.5 驗證 CORS：確認 Network tab 無 CORS 錯誤，所有 API 請求正常回應
