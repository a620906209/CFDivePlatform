## MODIFIED Requirements

### Requirement: 管理員切換教練驗證狀態
（本 requirement 由 provider-verification 的「Admin 審核佇列與裁決」取代）

`PUT /api/admin/providers/{id}/toggle-verified` SHALL 移除——單鍵切換允許繞過審核狀態機（無原因駁回、未送審直接通過）。教練驗證狀態的變更一律透過 `PUT /api/admin/verifications/{userId}/approve|reject`。

#### Scenario: toggle 端點回 404
- **WHEN** Admin 請求 `PUT /api/admin/providers/{id}/toggle-verified`
- **THEN** 回傳 HTTP 404

#### Scenario: 教練列表仍顯示驗證狀態
- **WHEN** Admin 請求 `GET /api/admin/providers`
- **THEN** 每筆教練資料包含 `verification_status`（與相容用 `is_verified` accessor）
