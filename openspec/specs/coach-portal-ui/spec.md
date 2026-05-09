## ADDED Requirements

### Requirement: 教練註冊頁
前端 SHALL 提供 `/coach/register` 頁面，供教練填寫帳號資訊與業者資料後申請帳號，成功後導向 `/coach/login`。

#### Scenario: 註冊成功
- **WHEN** 教練填入必填欄位（name / email / password / password_confirmation）並送出
- **THEN** 呼叫 `POST /api/provider/register`，成功後導向 `/coach/login?registered=1`，顯示「註冊成功，請登入」提示

#### Scenario: Email 重複
- **WHEN** 送出已存在的 email
- **THEN** 頁面顯示後端回傳的錯誤訊息，不跳轉

#### Scenario: 密碼不一致
- **WHEN** password 與 password_confirmation 不一致
- **THEN** 後端回傳 422，頁面顯示欄位錯誤提示

#### Scenario: business_name 為選填
- **WHEN** 教練不填寫工作室名稱直接送出
- **THEN** 正常完成註冊，business_name 存為 null

---

### Requirement: 教練登入頁
前端 SHALL 提供 `/coach/login` 頁面，供教練以 email/password 登入，成功後導向 `/coach/dashboard`。

#### Scenario: 登入成功
- **WHEN** 教練填入正確帳密並送出
- **THEN** 呼叫 `POST /api/provider/login`，token 存入 coachAuth store（localStorage key: coach_token），導向 `/coach/dashboard`

#### Scenario: 登入失敗
- **WHEN** 帳密錯誤
- **THEN** 頁面顯示錯誤訊息，不跳轉

#### Scenario: 已登入教練訪問登入頁
- **WHEN** coachAuth.isLoggedIn 為 true 時訪問 `/coach/login`
- **THEN** 自動導向 `/coach/dashboard`

---

### Requirement: 課程 Dashboard
前端 SHALL 提供 `/coach/dashboard` 頁面（需教練登入），顯示自己的課程列表，並提供新增、編輯、刪除操作入口。

#### Scenario: 載入課程列表
- **WHEN** 已登入教練訪問 Dashboard
- **THEN** 呼叫 `GET /api/provider/offers`，以表格或卡片渲染課程（標題、地點、價格、狀態）

#### Scenario: 無課程時顯示空狀態
- **WHEN** 教練尚無課程
- **THEN** 顯示「尚無課程，立即新增第一堂課」提示與新增按鈕

#### Scenario: 刪除課程確認
- **WHEN** 教練點擊刪除按鈕
- **THEN** 顯示確認提示，確認後呼叫 `DELETE /api/provider/offers/{id}`，成功後更新列表

---

### Requirement: 新增課程表單
前端 SHALL 提供 `/coach/offers/new` 頁面，教練填寫課程資訊後送出新增。

#### Scenario: 新增課程成功
- **WHEN** 教練填入所有必填欄位並送出
- **THEN** 呼叫 `POST /api/provider/offers`，成功後導向 `/coach/dashboard` 並顯示成功提示

#### Scenario: 表單驗證失敗
- **WHEN** 必填欄位（title / location / price）為空
- **THEN** 前端顯示欄位錯誤提示，不送出 API

---

### Requirement: 編輯課程表單
前端 SHALL 提供 `/coach/offers/:id/edit` 頁面，預填現有課程資料供教練修改。

#### Scenario: 載入課程資料並編輯
- **WHEN** 教練訪問編輯頁
- **THEN** 從 Dashboard 傳入或呼叫 API 取得課程資料，預填表單，送出後呼叫 `PUT /api/provider/offers/{id}`，成功後返回 Dashboard

#### Scenario: 無權限編輯
- **WHEN** API 回傳 403
- **THEN** 頁面顯示「無權限修改此課程」並返回 Dashboard

---

### Requirement: 教練個人資料頁
前端 SHALL 提供 `/coach/profile` 頁面（需教練登入），顯示並允許更新教練基本資訊與專業資料。

#### Scenario: 讀取並更新資料
- **WHEN** 教練訪問個人資料頁
- **THEN** 呼叫 `GET /api/provider/profile`，顯示 name / email / bio / expertise / certification，儲存時呼叫 `PUT /api/provider/profile`

---

### Requirement: Coach 路由守衛
前端 SHALL 對所有 `/coach/*` 路由（login 除外）加上 navigation guard，未登入時導向 `/coach/login`。

#### Scenario: 未登入訪問 Dashboard
- **WHEN** 未登入使用者直接訪問 `/coach/dashboard`
- **THEN** 自動導向 `/coach/login`

#### Scenario: 登出
- **WHEN** 教練點擊登出
- **THEN** 呼叫 `POST /api/provider/logout`，清除 coach_token / coach_user，導向 `/coach/login`
