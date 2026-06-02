## ADDED Requirements

### Requirement: 管理員登入
後端 SHALL 提供 `POST /api/admin/login`（現有 AuthController 方法），驗證 email/password 並確認 role=admin，回傳有效期 7 天的 Bearer token。

#### Scenario: 正確帳密登入
- **WHEN** 管理員送出正確 email 與 password
- **THEN** 回傳 HTTP 200，`{ status: true, data: { user, token, token_type: "Bearer" } }`

#### Scenario: 非 admin 角色帳號嘗試登入
- **WHEN** role 非 admin 的帳號嘗試呼叫此端點
- **THEN** 回傳 HTTP 401，`{ status: false, message: "電子郵件或密碼錯誤" }`

#### Scenario: 超過登入頻率限制
- **WHEN** 同一 IP 在 1 分鐘內送出超過 3 次登入請求
- **THEN** 回傳 HTTP 429，帶有 `Retry-After` header

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

---

### Requirement: 管理員 Bearer Token 有效期
後端 SHALL 發行有效期為 7 天的管理員 Bearer Token。主動使用 API 的 session 可透過 refresh 端點取得新 token（sliding window）；閒置超過 7 天後需重新登入。

#### Scenario: Token 過期後管理員請求被拒絕
- **WHEN** 管理員使用已過期（超過 7 天未 refresh）的 token 送出 API 請求
- **THEN** 回傳 HTTP 401，token 視為無效

#### Scenario: 有效期內 token 正常通過認證
- **WHEN** 管理員使用未過期的 token 送出 API 請求
- **THEN** 請求正常通過認證，回傳對應資源

#### Scenario: Refresh 延續有效期
- **WHEN** 管理員在 token 過期前呼叫 `POST /api/admin/refresh`
- **THEN** 取得新的 7 天 token，舊 token 失效，有效期重置
