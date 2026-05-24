## ADDED Requirements

### Requirement: Admin 端點 Swagger 文件

`app/Docs/AdminApiDoc.php` SHALL 文件化所有需要 Admin Bearer token 的管理端點。

#### Scenario: Admin stats 端點文件化

- **WHEN** 開啟 Swagger UI
- **THEN** `GET /admin/stats` 端點有文件，response 包含 `total_members`、`total_providers`、`total_offers`；403 非 admin 亦有定義

#### Scenario: Admin 會員管理端點文件化

- **WHEN** 開啟 Swagger UI
- **THEN** 以下端點均有文件，標示 `security: bearerAuth`：
  - `GET /admin/members`（分頁 response，含 member_profile）
  - `GET /admin/members/{id}`
  - `PUT /admin/members/{id}/toggle-active`（response: is_active 新狀態）
  - `GET /admin/check-member/{id}`

#### Scenario: Admin 教練管理端點文件化

- **WHEN** 開啟 Swagger UI
- **THEN** 以下端點均有文件：
  - `GET /admin/providers`（分頁 response，含 provider_profile）
  - `GET /admin/providers/{id}`
  - `PUT /admin/providers/{id}/toggle-active`
  - `PUT /admin/providers/{id}/toggle-verified`（response: is_verified 新狀態）
  - `GET /admin/check-provider/{id}`

#### Scenario: Admin 課程、預約、評價管理端點文件化

- **WHEN** 開啟 Swagger UI
- **THEN** 以下端點均有文件：
  - `GET /admin/offers`（分頁 response）
  - `DELETE /admin/offers/{id}`
  - `GET /admin/bookings`（分頁 response）
  - `PUT /admin/bookings/{id}/complete`
  - `GET /admin/reviews`（分頁 response，含 per_page 參數，最大 100）
  - `DELETE /admin/reviews/{id}`
