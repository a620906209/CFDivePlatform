## ADDED Requirements

### Requirement: 管理員查看會員列表
後端 SHALL 提供 `GET /api/admin/members`（需 Bearer token，role=admin），回傳所有 role=member 的用戶，支援關鍵字搜尋與分頁。

#### Scenario: 取得全部會員列表
- **WHEN** 管理員送出 GET 請求不帶參數
- **THEN** 回傳 HTTP 200，`{ status: true, data: [...members with memberProfile], meta: { total, per_page, current_page, last_page } }`，預設每頁 15 筆

#### Scenario: 搜尋會員
- **WHEN** 管理員送出 `?q=王小明`
- **THEN** 只回傳 name 或 email 包含「王小明」的會員

---

### Requirement: 管理員查看會員詳情
後端 SHALL 提供 `GET /api/admin/members/{id}`，回傳指定會員的完整資料。

#### Scenario: 取得存在的會員詳情
- **WHEN** 管理員送出有效 id 的 GET 請求
- **THEN** 回傳 HTTP 200，包含 user 資料與 memberProfile

#### Scenario: 會員不存在
- **WHEN** 指定 id 的用戶不存在或 role 非 member
- **THEN** 回傳 HTTP 404，`{ status: false, message: "用戶不存在" }`

---

### Requirement: 管理員啟用/停用會員帳號
後端 SHALL 提供 `PUT /api/admin/members/{id}/toggle-active`，反轉指定會員的 `is_active` 狀態。

#### Scenario: 停用啟用中的帳號
- **WHEN** 管理員對 is_active=true 的會員發送請求
- **THEN** 將 is_active 設為 false，回傳 HTTP 200，`{ status: true, message: "帳號已停用", data: { is_active: false } }`

#### Scenario: 啟用停用中的帳號
- **WHEN** 管理員對 is_active=false 的會員發送請求
- **THEN** 將 is_active 設為 true，回傳 HTTP 200，`{ status: true, message: "帳號已啟用", data: { is_active: true } }`

---

### Requirement: 管理員查看教練列表
後端 SHALL 提供 `GET /api/admin/providers`（需 Bearer token，role=admin），回傳所有 role=provider 的用戶，支援搜尋與分頁，含 providerProfile。

#### Scenario: 取得全部教練列表
- **WHEN** 管理員送出 GET 請求
- **THEN** 回傳 HTTP 200，含 providerProfile（包括 is_verified、business_name 等）與分頁 meta

---

### Requirement: 管理員啟用/停用教練帳號
後端 SHALL 提供 `PUT /api/admin/providers/{id}/toggle-active`，行為同會員版本。

#### Scenario: 停用/啟用教練帳號
- **WHEN** 管理員對教練帳號發送 toggle-active 請求
- **THEN** 反轉 is_active，回傳對應訊息

---

### Requirement: 管理員驗證教練（已由審核狀態機取代）
`PUT /api/admin/providers/{id}/toggle-verified` 已於 2026-06-12 移除——單鍵切換允許繞過審核狀態機（無原因駁回、未送審直接通過）。教練驗證狀態的變更一律透過 `PUT /api/admin/verifications/{userId}/approve|reject`（見 provider-verification 規格「Admin 審核佇列與裁決」）。

#### Scenario: toggle 端點回 404
- **WHEN** 管理員請求 `PUT /api/admin/providers/{id}/toggle-verified`
- **THEN** 回傳 HTTP 404

#### Scenario: 教練列表仍顯示驗證狀態
- **WHEN** 管理員請求 `GET /api/admin/providers`
- **THEN** 每筆教練的 provider_profile 包含 `verification_status` 與相容用 `is_verified`（accessor，= approved）
