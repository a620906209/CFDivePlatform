# Proposal

## Why

`MemberBookingController::store` 與 `ProviderBookingController::confirm` 用 `catch (\RuntimeException $e)` 把交易內的業務失敗轉成 422，並直接回傳 `$e->getMessage()`。`Illuminate\Database\QueryException` 經由 `PDOException` 也是 `RuntimeException` 的子類別，因此交易內的資料庫錯誤（死結、逾時、約束違反、連線中斷）會被同一個 catch 接住：SQL 與資料表結構被當成 422 訊息回給使用者（資訊洩漏），且不經過 Laravel 例外處理器、不會送到 Sentry，營運端看不到伺服器故障（待辦 M7，`docs/analysis/2026-10-14-project-review-todo.md`）。

## What Changes

- 新增專用的業務例外 `BookingRuleViolationException`；兩個預約交易閉包內的業務規則失敗（名額不足、重複預約）改拋此例外
- 兩個 Controller 只 catch `BookingRuleViolationException` 並回 422 與其訊息；其他例外（含 `QueryException`）不再被攔截，交給 Laravel 例外處理器產生 500 並回報（既有 `Sentry\Laravel\Integration::handles` 會送到 Sentry）
- 業務失敗的 422 狀態碼與訊息文字維持不變
- 資料庫錯誤的回應由「422 + 原始例外訊息」改為「500 + Laravel 預設通用訊息」（`APP_DEBUG=false` 時 body 為 `{"message":"Server Error"}`）

## Capabilities

### New Capabilities
（無）

### Modified Capabilities
- `booking-lifecycle`：新增「預約交易錯誤分類」requirement，明定業務規則失敗回 422 與既有訊息、非業務例外回 500 且不洩漏資料庫細節並被回報

## Impact

- **程式碼**：新增 `app/Exceptions/BookingRuleViolationException.php`；修改 `app/Http/Controllers/API/MemberBookingController.php`（`store`）、`app/Http/Controllers/API/ProviderBookingController.php`（`confirm`）
- **API 回應格式**：業務失敗（422）不變。資料庫錯誤的回應由 422 改為 500，body 為 Laravel 預設格式 `{"message":"Server Error"}`，**不含**專案慣例的 `status` 欄位——這是刻意選擇（交給 Laravel 處理、不另寫轉換），與其他端點未被攔截的 500 一致
- **前端**：`CourseDetailView.vue`、`coach/BookingManagerView.vue` 以 `e.response?.data?.message` 顯示錯誤；資料庫錯誤時會顯示 `Server Error`（原本顯示 SQL 訊息），不需修改前端
- **監控**：預約交易內的資料庫錯誤開始出現在 Sentry
- **測試**：新增 Feature 測試；既有 `BookingOversellTest`、`BookingLifecycleTest` 的 422 行為不變
- **相依、migration、路由、Swagger**：無變更（`app/Docs/` 目前沒有記錄這兩個端點的 500，維持不新增）
