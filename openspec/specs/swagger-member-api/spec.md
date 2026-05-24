## ADDED Requirements

### Requirement: Member 端點 Swagger 文件

`app/Docs/MemberApiDoc.php` SHALL 文件化所有需要 Member Bearer token 的端點（bookings、reviews、helpful 投票、notifications）。

#### Scenario: Member bookings 端點文件化

- **WHEN** 開啟 Swagger UI
- **THEN** 以下端點均有文件，並標示 `security: bearerAuth`：
  - `POST /member/bookings`（request: schedule_id；response 201: Booking）
  - `GET /member/bookings`（response: Booking 陣列 + 分頁 meta）
  - `GET /member/bookings/{id}`（response: Booking 詳情）
  - `DELETE /member/bookings/{id}`（response 200: message）

#### Scenario: Member reviews 端點文件化

- **WHEN** 開啟 Swagger UI
- **THEN** 以下端點均有文件：
  - `POST /member/reviews`（request: diving_offer_id、rating、comment；403 資格驗證失敗；422 重複評價）
  - `PUT /member/reviews/{id}`（request: rating?、comment?；403 非本人）
  - `DELETE /member/reviews/{id}`（403 非本人）
  - `POST /reviews/{id}/helpful`（toggle，response: helpful_count、has_voted）

#### Scenario: Member notifications 端點文件化

- **WHEN** 開啟 Swagger UI
- **THEN** 以下端點均有文件：
  - `GET /notifications`（response: 通知陣列 + 分頁 meta）
  - `GET /notifications/unread-count`（response: count）
  - `PATCH /notifications/{id}/read`
  - `PATCH /notifications/read-all`
  - `DELETE /notifications/{id}`
