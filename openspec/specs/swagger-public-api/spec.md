## ADDED Requirements

### Requirement: 公開端點 Swagger 文件及共用 Schema

`app/Docs/PublicApiDoc.php` SHALL 文件化所有無需認證的公開端點，並定義全域共用 Schema。

#### Scenario: 共用 Schema 定義完整

- **WHEN** 執行 `php artisan l5-swagger:generate`
- **THEN** 以下 Schema 出現在產生的 JSON：`DivingOffer`、`Review`、`CourseSchedule`、`PaginationMeta`、`ApiErrorResponse`

#### Scenario: GET /api/diving-offers 文件化

- **WHEN** 開啟 Swagger UI
- **THEN** `GET /diving-offers` 端點顯示 query parameters（`q`、`region`、`tag`、`per_page`、`page`）及包含分頁 meta 的 response schema

#### Scenario: GET /api/diving-offers/{id} 文件化

- **WHEN** 開啟 Swagger UI
- **THEN** `GET /diving-offers/{id}` 端點顯示 path parameter `id` 及包含 `cover_image_url`、`images` 陣列的 response schema；404 response 亦有定義

#### Scenario: GET /api/diving-offers/{id}/reviews 文件化

- **WHEN** 開啟 Swagger UI
- **THEN** 端點顯示 `sort`（helpful/rating/newest）、`page`、`per_page` 參數；response 包含 `summary`（average、total、distribution）與分頁 `meta`

#### Scenario: GET /api/diving-offers/{id}/schedules 文件化

- **WHEN** 開啟 Swagger UI
- **THEN** 端點顯示 response 包含 `CourseSchedule` 陣列（id、date、period、capacity、booked_count、status）
