## Why

目前 Swagger 文件僅涵蓋 15 個 Auth 端點，其餘 61 個端點（課程、評價、預約、通知、圖片上傳、Coach Portal、Admin Panel）完全未文件化，導致前後端協作與第三方整合缺乏規格依據。現有 `darkaonline/l5-swagger` 套件已安裝，補全文件的邊際成本低。

## What Changes

- 為所有未文件化端點補上 `@OA` PHPDoc 標注（`app/Docs/` 目錄下依模組分檔）
- 定義完整的 Request / Response Schema（含分頁 meta、錯誤格式）
- 補全 Swagger Security 設定（Bearer token per-role）
- 重新產生 `storage/api-docs/api-docs.json`

**端點範圍（新增 61 個）：**
- Public API：GET diving-offers 列表/詳情、reviews、schedules（4）
- Member API：bookings CRUD、reviews CRUD、helpful 投票、notifications（10）
- Provider API：offers CRUD + 圖片、schedules CRUD、bookings 管理（19）
- Admin API：stats、members、providers、offers、bookings、reviews 管理（17）
- Auth 補全：Google OAuth、change-password（3 roles）（7）

## Capabilities

### New Capabilities

- `swagger-public-api`：公開端點 Swagger 文件（diving-offers、reviews、schedules）
- `swagger-member-api`：Member 端點 Swagger 文件（bookings、reviews、notifications）
- `swagger-provider-api`：Provider 端點 Swagger 文件（offers、images、schedules、bookings）
- `swagger-admin-api`：Admin 端點 Swagger 文件（stats、user/offer/booking/review 管理）
- `swagger-auth-supplement`：補全 Auth 端點（Google OAuth、change-password）

### Modified Capabilities

（無，本 change 純為文件補全，不變更任何 API 行為）

## Impact

- 新增：`app/Docs/PublicApiDoc.php`、`MemberApiDoc.php`、`ProviderApiDoc.php`、`AdminApiDoc.php`、`AuthSupplementDoc.php`
- 更新：`storage/api-docs/api-docs.json`（自動產生）
- 不影響任何 Controller、Model、Route、Migration
