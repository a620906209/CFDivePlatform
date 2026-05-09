## ADDED Requirements

### Requirement: 平台統計數據 API
後端 SHALL 提供 `GET /api/admin/stats`（需 Bearer token，role=admin），回傳平台核心數據。

#### Scenario: 取得統計數據
- **WHEN** 管理員送出 GET /api/admin/stats
- **THEN** 回傳 HTTP 200，`{ status: true, data: { total_members: N, total_providers: N, total_offers: N } }`

#### Scenario: 非管理員存取
- **WHEN** 非 admin role 的 token 送出請求
- **THEN** 回傳 HTTP 403，`{ status: false, message: "無權限存取" }`
