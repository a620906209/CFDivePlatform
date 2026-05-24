## ADDED Requirements

### Requirement: Provider 端點 Swagger 文件

`app/Docs/ProviderApiDoc.php` SHALL 文件化所有需要 Provider Bearer token 的端點（offers CRUD、圖片、schedules、bookings）。

#### Scenario: Provider offers CRUD 端點文件化

- **WHEN** 開啟 Swagger UI
- **THEN** 以下端點均有文件，標示 `security: bearerAuth`：
  - `GET /provider/offers`（分頁 response）
  - `POST /provider/offers`（request: title、location、spot?、price、region、tag?、badges?、description?）
  - `GET /provider/offers/{id}`（403 非本人）
  - `PUT /provider/offers/{id}`（request 同上，所有欄位 nullable）
  - `DELETE /provider/offers/{id}`

#### Scenario: Provider 圖片管理端點文件化

- **WHEN** 開啟 Swagger UI
- **THEN** 以下端點均有文件：
  - `POST /provider/offers/{id}/cover`（multipart/form-data: image；response: cover_image_url）
  - `DELETE /provider/offers/{id}/cover`
  - `POST /provider/offers/{id}/images`（multipart/form-data: images[]；最多 10 張）
  - `DELETE /provider/images/{id}`

#### Scenario: Provider schedules 端點文件化

- **WHEN** 開啟 Swagger UI
- **THEN** 以下端點均有文件：
  - `GET /provider/schedules`（query: offer_id?；response: CourseSchedule 陣列）
  - `POST /provider/schedules`（request: diving_offer_id、date、period、capacity）
  - `PUT /provider/schedules/{id}`
  - `DELETE /provider/schedules/{id}`

#### Scenario: Provider bookings 端點文件化

- **WHEN** 開啟 Swagger UI
- **THEN** 以下端點均有文件：
  - `GET /provider/bookings`（response: Booking 陣列，含 member 資訊）
  - `PUT /provider/bookings/{id}/confirm`
  - `PUT /provider/bookings/{id}/reject`
  - `PUT /provider/bookings/{id}/complete`
  - `PUT /provider/bookings/{id}/cancel`
