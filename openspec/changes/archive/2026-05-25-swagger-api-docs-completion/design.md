## Context

現有 `app/Docs/AuthApiDoc.php` 已建立 Swagger 基礎設定（`@OA\Info`、`@OA\Server`、`@OA\SecurityScheme`）並文件化 15 個 Auth 端點。其餘 61 個端點分散在 8 個 Controller 中，沒有任何 `@OA` 標注。`darkaonline/l5-swagger` 已安裝，執行 `php artisan l5-swagger:generate` 即可重新產生 JSON。

## Goals / Non-Goals

**Goals:**
- 補齊所有 61 個未文件化端點的 `@OA` 標注
- 定義共用 Schema（分頁 meta、統一錯誤格式、DivingOffer、Review、Booking 等）
- 文件依模組分檔，每個新檔對應一個 `app/Docs/*Doc.php`
- 每個端點包含：summary、parameters、request body（POST/PUT）、response（200/201/400/401/403/422）

**Non-Goals:**
- 不修改任何 Controller / Route / Model 邏輯
- 不為尚未實作的端點（金流、訂閱）補文件

## Decisions

### 決策 1：分檔策略 — 依模組建立獨立 Doc 類別

**選擇**：每個模組一個 `app/Docs/*Doc.php`

| 檔案 | 涵蓋端點 |
|------|---------|
| `AuthApiDoc.php` | **修正路徑錯誤**（`/register/member` → `/member/register` 等）；補 `POST /logout`、`GET /user` |
| `PublicApiDoc.php` | 公開端點（diving-offers、reviews、schedules） |
| `MemberApiDoc.php` | Member bookings、reviews、helpful、notifications |
| `ProviderApiDoc.php` | Provider offers、images、schedules、bookings |
| `AdminApiDoc.php` | Admin stats、users、offers、bookings、reviews |
| `AuthSupplementDoc.php` | **僅** Google OAuth 2 個端點（`SocialAuthController`） |

**理由**：單一大檔難以維護；按模組分檔讓每個 Doc 類別職責清晰，對應 Controller 結構

**替代方案**：直接在 Controller 方法上加 `@OA` → 拒絕，Controller 文件與業務邏輯混雜，讀性差

### 決策 2：共用 Schema 集中定義在 `PublicApiDoc.php`

共用 Schema（`DivingOfferSchema`、`ReviewSchema`、`BookingSchema`、`PaginationMeta`、`ApiErrorResponse`）集中放在 `PublicApiDoc.php` 頂層 `@OA\Schema` 標注。

**理由**：l5-swagger 掃描所有 `app/` 目錄，Schema 定義位置不影響其他檔案引用；放在 Public 層語意上最自然（核心資料結構）

### 決策 3：Security 標注策略

- 公開端點：不加 `security`
- Member 端點：`security={{"bearerAuth": {}}}`（現有 scheme）
- Provider 端點：`security={{"bearerAuth": {}}}`
- Admin 端點：`security={{"bearerAuth": {}}}`

三個 role 共用同一個 `bearerAuth` scheme，由後端 middleware 區分角色，不需要在 Swagger 定義三個不同 scheme。

## Risks / Trade-offs

- **[Risk] 手寫標注與實際 response 不同步** → 以實際 Controller 程式碼為準手寫；未來有 API 變更時需同步更新 Doc 檔
- **[Risk] Schema 巢狀複雜導致 Swagger UI 渲染慢** → 分頁 response 使用 `allOf` 組合，避免深層巢狀

## Migration Plan

1. 依序建立 5 個新 Doc 檔案
2. 在容器內執行 `php artisan l5-swagger:generate`
3. 開啟 `http://localhost:8080/api/documentation` 確認 UI 正確渲染
4. 確認所有 tag 與端點均出現在 Swagger UI

**Rollback**：直接刪除新增的 Doc 檔案，重新 generate 即可回復到只有 Auth 端點的狀態
