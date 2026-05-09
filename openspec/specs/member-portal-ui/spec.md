## ADDED Requirements

### Requirement: 專案基礎建設
前端 SHALL 建立於獨立 repo，使用 Vue 3 + Vite + Tailwind CSS + Vue Router 4 + Pinia + Axios，並設定 `.env` 指定後端 API base URL。

#### Scenario: 開發環境啟動
- **WHEN** 開發者執行 `npm run dev`
- **THEN** 應用在 `http://localhost:5173` 啟動，無編譯錯誤

#### Scenario: API base URL 設定
- **WHEN** `.env` 中設定 `VITE_API_URL=http://localhost:80`
- **THEN** 所有 Axios 請求以此為 base URL

---

### Requirement: 首頁 Landing Page
前端 SHALL 提供靜態首頁，展示平台品牌、簡介，以及引導至課程列表的 CTA（Call to Action）按鈕。

#### Scenario: 訪客瀏覽首頁
- **WHEN** 使用者訪問 `/`
- **THEN** 看到平台名稱、簡介文字、「探索課程」按鈕

#### Scenario: 點擊 CTA 跳轉
- **WHEN** 使用者點擊「探索課程」按鈕
- **THEN** 導航至 `/courses`（課程列表頁）

---

### Requirement: 課程列表頁
前端 SHALL 提供 `/courses` 頁面，顯示從後端取得的潛水課程卡片列表，並支援搜尋與篩選。

#### Scenario: 載入課程列表
- **WHEN** 使用者訪問 `/courses`
- **THEN** 頁面呼叫 `GET /api/diving-offers` 並渲染課程卡片（含標題、地點、價格、評分、標籤）

#### Scenario: 搜尋課程
- **WHEN** 使用者在搜尋框輸入關鍵字後按 Enter 或點搜尋
- **THEN** 以 `?q=<keyword>` 重新呼叫 API，列表更新

#### Scenario: 地區篩選
- **WHEN** 使用者從地區下拉選單選擇某地區
- **THEN** 以 `?region=<region>` 重新呼叫 API，列表更新

#### Scenario: 無結果
- **WHEN** 搜尋/篩選後後端回傳空陣列
- **THEN** 頁面顯示「找不到符合的課程」提示訊息

---

### Requirement: 課程詳情頁
前端 SHALL 提供 `/courses/:id` 頁面，顯示單一課程的完整資訊。

#### Scenario: 載入課程詳情
- **WHEN** 使用者訪問 `/courses/1`
- **THEN** 頁面呼叫 `GET /api/diving-offers/1` 並顯示標題、地點、景點、價格、評分、評論數、描述、徽章、標籤

#### Scenario: 課程不存在
- **WHEN** 使用者訪問不存在的課程 id
- **THEN** 頁面顯示「課程不存在」並提供返回列表按鈕

---

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

---

### Requirement: 註冊頁
前端 SHALL 提供 `/register` 頁面，供訪客建立會員帳號。

#### Scenario: 註冊成功
- **WHEN** 使用者填入 name、email、password 並送出
- **THEN** 呼叫 `POST /api/member/register`，成功後導航至 `/login`，顯示「註冊成功，請登入」

#### Scenario: Email 已被使用
- **WHEN** 使用者填入已存在的 email 送出
- **THEN** 頁面顯示「此 Email 已被使用」錯誤訊息

---

### Requirement: 會員個人資料頁
前端 SHALL 提供 `/profile` 頁面，已登入會員可查看並更新個人資料。此頁面需登入後才能訪問。

#### Scenario: 已登入會員訪問個人資料
- **WHEN** 已登入使用者訪問 `/profile`
- **THEN** 頁面呼叫 `GET /api/member/profile` 並顯示姓名、email、生日、性別、地址、緊急聯絡人

#### Scenario: 未登入訪問個人資料
- **WHEN** 未登入使用者訪問 `/profile`
- **THEN** 自動導向 `/login`

#### Scenario: 更新個人資料成功
- **WHEN** 已登入使用者修改欄位後點擊儲存
- **THEN** 呼叫 `PUT /api/member/profile`，成功後顯示「資料已更新」提示

---

### Requirement: 認證狀態管理
前端 SHALL 使用 Pinia store 管理認證狀態，token 持久化至 localStorage，並在所有需認證的 API 請求自動附加 Bearer token。

#### Scenario: 頁面刷新後保持登入狀態
- **WHEN** 已登入使用者重新整理頁面
- **THEN** 從 localStorage 還原 token，使用者仍為登入狀態

#### Scenario: 登出
- **WHEN** 使用者點擊登出
- **THEN** 呼叫 `POST /api/member/logout`，清除 localStorage token，導向 `/login`
