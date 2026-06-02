## ADDED Requirements

### Requirement: 教練帳號登入
後端 SHALL 提供 `POST /api/provider/login`，驗證 email/password 並回傳 Sanctum Bearer token，僅限 role=provider 帳號。回傳的 token 有效期為 7 天。

#### Scenario: 正確帳密登入成功
- **WHEN** 教練送出正確的 email 與 password
- **THEN** 回傳 HTTP 200，包含 `{ status: true, data: { user, token, token_type: "Bearer" } }`

#### Scenario: 錯誤帳密登入失敗
- **WHEN** 教練送出錯誤的 email 或 password
- **THEN** 回傳 HTTP 401，`{ status: false, message: "帳號或密碼錯誤" }`

#### Scenario: 會員帳號無法用教練登入
- **WHEN** role=member 的帳號嘗試呼叫 `/api/provider/login`
- **THEN** 回傳 HTTP 403，`{ status: false, message: "此帳號非教練角色" }`

#### Scenario: 超過登入頻率限制
- **WHEN** 同一 IP 在 1 分鐘內送出超過 5 次登入請求
- **THEN** 回傳 HTTP 429，帶有 `Retry-After` header

---

### Requirement: 教練帳號註冊
後端 SHALL 提供 `POST /api/provider/register`，建立 role=provider 的 User 與對應 ProviderProfile。

#### Scenario: 新帳號註冊成功
- **WHEN** 送出有效的 name / email / password / password_confirmation
- **THEN** 回傳 HTTP 201，`{ status: true, data: { user } }`

#### Scenario: Email 重複
- **WHEN** 送出已存在的 email
- **THEN** 回傳 HTTP 422，`{ status: false, message: "此 Email 已被使用" }`

---

### Requirement: 教練登出
後端 SHALL 提供 `POST /api/provider/logout`（需 Bearer token），撤銷當前 token。

#### Scenario: 登出成功
- **WHEN** 已登入教練送出登出請求
- **THEN** 回傳 HTTP 200，`{ status: true, message: "已登出" }`，token 失效

---

### Requirement: 教練個人資料讀取
後端 SHALL 提供 `GET /api/provider/profile`（需 Bearer token），回傳教練基本資訊與 ProviderProfile。

#### Scenario: 取得個人資料
- **WHEN** 已登入教練送出 GET 請求
- **THEN** 回傳 HTTP 200，包含 `{ id, name, email, role, profile: { bio, expertise, certification, avatar } }`

---

### Requirement: 教練個人資料更新
後端 SHALL 提供 `PUT /api/provider/profile`（需 Bearer token），更新教練基本資訊與 ProviderProfile。

#### Scenario: 更新成功
- **WHEN** 教練送出合法的更新資料
- **THEN** 回傳 HTTP 200，`{ status: true, message: "資料已更新", data: { ...profile } }`

---

### Requirement: Bearer Token 有效期
後端 SHALL 發行有效期為 7 天的 Sanctum Bearer Token。主動使用 API 的 session 可透過 refresh 端點取得新 token（sliding window），持續使用不需重新登入；閒置超過 7 天後 token 過期，需重新登入。

#### Scenario: Token 過期後請求被拒絕
- **WHEN** 教練使用已過期（超過 7 天未 refresh）的 token 送出 API 請求
- **THEN** 回傳 HTTP 401，token 視為無效

#### Scenario: 有效期內 token 正常運作
- **WHEN** 教練使用未過期的 token 送出 API 請求
- **THEN** 請求正常通過認證

#### Scenario: Refresh 延續有效期
- **WHEN** 教練在 token 過期前呼叫 `POST /api/provider/refresh`
- **THEN** 取得新的 7 天 token，舊 token 失效，有效期重置
