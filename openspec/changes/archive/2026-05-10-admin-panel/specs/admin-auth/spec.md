## ADDED Requirements

### Requirement: 管理員登入
後端 SHALL 提供 `POST /api/admin/login`（現有 AuthController 方法），驗證 email/password 並確認 role=admin，回傳 Bearer token。

#### Scenario: 正確帳密登入
- **WHEN** 管理員送出正確 email 與 password
- **THEN** 回傳 HTTP 200，`{ status: true, data: { user, token, token_type: "Bearer" } }`

#### Scenario: 非 admin 角色帳號嘗試登入
- **WHEN** role 非 admin 的帳號嘗試呼叫此端點
- **THEN** 回傳 HTTP 401，`{ status: false, message: "電子郵件或密碼錯誤" }`

---

### Requirement: 管理員登出
後端 SHALL 提供 `POST /api/admin/logout`（需 Bearer token），撤銷當前 token。

#### Scenario: 登出成功
- **WHEN** 已登入管理員送出登出請求
- **THEN** 回傳 HTTP 200，`{ status: true, message: "..." }`，token 失效

---

### Requirement: 管理員個人資料
後端 SHALL 提供 `GET /api/admin/profile`（需 Bearer token），回傳管理員基本資訊與 AdminProfile。

#### Scenario: 取得個人資料
- **WHEN** 已登入管理員送出 GET 請求
- **THEN** 回傳 HTTP 200，包含 name / email / role / adminProfile（position / department）
