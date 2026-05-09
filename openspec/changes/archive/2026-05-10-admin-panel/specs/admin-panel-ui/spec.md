## ADDED Requirements

### Requirement: 管理員登入頁
前端 SHALL 提供 `/admin/login` 頁面，供管理員以 email/password 登入，成功後導向 `/admin/dashboard`。

#### Scenario: 登入成功
- **WHEN** 管理員填入正確帳密並送出
- **THEN** 呼叫 `POST /api/admin/login`，token 存入 adminAuth store（localStorage key: admin_token），導向 `/admin/dashboard`

#### Scenario: 登入失敗
- **WHEN** 帳密錯誤
- **THEN** 頁面顯示錯誤訊息，不跳轉

---

### Requirement: 儀表板（統計數據）
前端 SHALL 提供 `/admin/dashboard` 頁面（需 admin 登入），顯示平台核心統計數據。

#### Scenario: 載入統計數據
- **WHEN** 管理員訪問 Dashboard
- **THEN** 呼叫 `GET /api/admin/stats`，顯示總會員數、總教練數、總課程數三個數字卡片

---

### Requirement: 會員管理頁
前端 SHALL 提供 `/admin/members` 頁面，列出所有會員，支援搜尋與啟用/停用操作。

#### Scenario: 載入會員列表
- **WHEN** 管理員訪問此頁面
- **THEN** 呼叫 `GET /api/admin/members`，以表格顯示姓名、email、註冊時間、帳號狀態

#### Scenario: 搜尋會員
- **WHEN** 管理員在搜尋框輸入關鍵字
- **THEN** 以 `?q=` 重新呼叫 API，列表更新

#### Scenario: 切換帳號狀態
- **WHEN** 管理員點擊啟用/停用按鈕
- **THEN** 呼叫 `PUT /api/admin/members/{id}/toggle-active`，成功後按鈕狀態更新

---

### Requirement: 教練管理頁
前端 SHALL 提供 `/admin/providers` 頁面，列出所有教練，支援搜尋、啟用/停用、驗證操作。

#### Scenario: 載入教練列表
- **WHEN** 管理員訪問此頁面
- **THEN** 呼叫 `GET /api/admin/providers`，顯示姓名、email、工作室名稱、驗證狀態、帳號狀態

#### Scenario: 切換驗證狀態
- **WHEN** 管理員點擊驗證/取消驗證按鈕
- **THEN** 呼叫 `PUT /api/admin/providers/{id}/toggle-verified`，成功後驗證狀態更新

---

### Requirement: 課程管理頁
前端 SHALL 提供 `/admin/offers` 頁面，列出全平台課程，支援搜尋與刪除。

#### Scenario: 載入課程列表
- **WHEN** 管理員訪問此頁面
- **THEN** 呼叫 `GET /api/admin/offers`，顯示課程標題、地點、教練 ID、價格

#### Scenario: 刪除課程（含確認）
- **WHEN** 管理員點擊刪除按鈕後確認
- **THEN** 呼叫 `DELETE /api/admin/offers/{id}`，成功後從列表移除

---

### Requirement: Admin 路由守衛
前端 SHALL 對所有 `/admin/*` 路由（login 除外）加上 navigation guard，未登入時導向 `/admin/login`。

#### Scenario: 未登入訪問後台頁面
- **WHEN** 未登入使用者直接訪問 `/admin/dashboard`
- **THEN** 自動導向 `/admin/login`

---

### Requirement: Admin Layout 與導覽
前端 SHALL 提供 `AdminLayout`，包含 `AdminNavBar`（顯示管理員姓名、各功能連結、登出），所有 `/admin/*` protected 頁面套用此 Layout。`/admin/*` 路由不顯示會員 NavBar。

#### Scenario: 會員 NavBar 隱藏
- **WHEN** 使用者訪問任何 `/admin/*` 路徑
- **THEN** App.vue 不渲染會員 NavBar
