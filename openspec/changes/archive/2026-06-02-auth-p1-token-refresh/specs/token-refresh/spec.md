## ADDED Requirements

### Requirement: Member Token Refresh
後端 SHALL 提供 `POST /api/member/refresh`（需有效 Bearer token），revoke 現有 token 並發行新的 7 天 token。

#### Scenario: 有效 token refresh 成功
- **WHEN** 已登入會員以有效 Bearer token 呼叫 `POST /api/member/refresh`
- **THEN** 回傳 HTTP 200，`{ status: true, data: { token, token_type: "Bearer" } }`，舊 token 同時失效

#### Scenario: 無效或過期 token 無法 refresh
- **WHEN** 以過期或無效的 token 呼叫 refresh 端點
- **THEN** 回傳 HTTP 401，`{ status: false, message: "無效的 token" }`

#### Scenario: 非 member 角色無法使用 member refresh
- **WHEN** role=provider 的 token 呼叫 `/api/member/refresh`
- **THEN** 回傳 HTTP 403

---

### Requirement: Provider Token Refresh
後端 SHALL 提供 `POST /api/provider/refresh`（需有效 Bearer token），revoke 現有 token 並發行新的 7 天 token。

#### Scenario: 有效 token refresh 成功
- **WHEN** 已登入教練以有效 Bearer token 呼叫 `POST /api/provider/refresh`
- **THEN** 回傳 HTTP 200，`{ status: true, data: { token, token_type: "Bearer" } }`，舊 token 同時失效

#### Scenario: 無效或過期 token 無法 refresh
- **WHEN** 以過期或無效的 token 呼叫 refresh 端點
- **THEN** 回傳 HTTP 401

#### Scenario: 非 provider 角色無法使用 provider refresh
- **WHEN** role=member 的 token 呼叫 `/api/provider/refresh`
- **THEN** 回傳 HTTP 403

---

### Requirement: Admin Token Refresh
後端 SHALL 提供 `POST /api/admin/refresh`（需有效 Bearer token），revoke 現有 token 並發行新的 7 天 token。

#### Scenario: 有效 token refresh 成功
- **WHEN** 已登入管理員以有效 Bearer token 呼叫 `POST /api/admin/refresh`
- **THEN** 回傳 HTTP 200，`{ status: true, data: { token, token_type: "Bearer" } }`，舊 token 同時失效

#### Scenario: 無效或過期 token 無法 refresh
- **WHEN** 以過期或無效的 token 呼叫 refresh 端點
- **THEN** 回傳 HTTP 401

#### Scenario: 非 admin 角色無法使用 admin refresh
- **WHEN** role=member 或 role=provider 的 token 呼叫 `/api/admin/refresh`
- **THEN** 回傳 HTTP 403

---

### Requirement: 前端 Refresh-Then-Retry 攔截
Member（`axios.js`）與 Provider/Coach（`coachAxios.js`）的 axios interceptor SHALL 在收到 401 時先嘗試 refresh，成功後以新 token retry 原始請求；refresh 失敗才清除 token 並導向登入頁。同時多個 401 只觸發一次 refresh，其餘請求排隊等待結果。Admin 前端 interceptor 不在此 change 範圍內（Admin Panel 尚未實作）。

#### Scenario: 401 觸發 refresh 並 retry 成功
- **WHEN** API 請求回傳 401 且 refresh 端點回傳新 token
- **THEN** 以新 token 重送原始請求，使用者無感知（不被導向登入頁）

#### Scenario: Refresh 失敗後登出
- **WHEN** API 請求回傳 401 且 refresh 端點也回傳 401
- **THEN** 清除 sessionStorage token，導向登入頁

#### Scenario: 多個並發 401 只觸發一次 refresh
- **WHEN** 同時有多個 API 請求收到 401
- **THEN** 只發出一次 refresh 請求，其他請求等待 refresh 完成後統一以新 token retry

#### Scenario: 登入與 Refresh 端點的 401 不觸發 refresh
- **WHEN** `/login`、`/register` 或 `/refresh` 端點本身回傳 401
- **THEN** 不觸發 refresh，直接將錯誤傳遞給呼叫方（防止無限遞迴）

#### Scenario: 已 retry 的請求不再觸發 refresh
- **WHEN** 某請求已經過一次 refresh-retry，retry 後仍回傳 401
- **THEN** 不再嘗試 refresh，直接 reject 並導向登入頁
