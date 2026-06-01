## MODIFIED Requirements

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
