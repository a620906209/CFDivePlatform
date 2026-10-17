# Design

## Context

背景見 proposal.md「Why」。現況：

- `MemberBookingController::store` 的 `DB::transaction` 閉包拋 `\RuntimeException('名額不足，請重新選擇')`、`\RuntimeException('您已預約此時段')`；`ProviderBookingController::confirm` 拋 `\RuntimeException('名額不足，無法確認此預約')`。兩處都以 `catch (\RuntimeException $e)` 回 422 + `$e->getMessage()`
- `bootstrap/app.php` 只有 `Sentry\Laravel\Integration::handles($exceptions)`，沒有自訂 render；未被攔截的例外由 Laravel 預設處理器回應，`APP_DEBUG=false` 時 JSON body 為 `{"message":"Server Error"}`
- 全專案 grep `RuntimeException` 只有這兩個 Controller；其他 `catch (\Throwable)` 都只包通知派送（記 log 後繼續），不在本次範圍
- `phpunit.xml` 沒有設定 `APP_DEBUG`，測試會沿用 `.env` 的值（本機為 true），因此驗證「不洩漏細節」的測試必須自行設定 `app.debug = false`

## Goals / Non-Goals

**Goals**
- 只有刻意拋出的業務例外會變成 422；其他例外一律走 Laravel 例外處理器

**Non-Goals**
- 不統一全站 500 的 JSON 格式（不在 `withExceptions` 加自訂 render）；500 body 沒有 `status` 欄位，與其他端點未攔截的 500 一致
- 不處理 M8（鎖之前讀取狀態的併發問題）與 M2（抽出 `BookingService`）；本次只改例外型別與 catch 範圍
- 不調整前端；資料庫錯誤時前端會顯示 `Server Error`
- 不拆分 `booking-lifecycle` 中超過 500 字元的「預約狀態機」requirement（不在本次修改範圍，需要時另開 change）

## Decisions

### D1：新增窄型業務例外 `App\Exceptions\BookingRuleViolationException`
- 放在新目錄 `app/Exceptions/`（目前不存在，Laravel 11 慣例位置）
- 繼承 `\RuntimeException`。安全性來自 Controller 只 catch 這個具體類別，而不是父類別的選擇；繼承 `RuntimeException` 讓語意（執行期業務規則失敗）不變
- 訊息只由程式內字串常值傳入，不包含使用者輸入或其他例外的訊息，因此可以安全回給使用者
- **替代方案**：
  - 在 catch 內判斷 `instanceof QueryException` 後重新拋出：仍會漏接其他非業務的 `RuntimeException`（例如 `PDOException` 以外的驅動錯誤），是黑名單做法，不採用
  - 交易閉包回傳錯誤碼而非拋例外：拋例外才能觸發 `DB::transaction` 的 rollback，改回傳值需要手動處理 rollback，不採用

### D2：非業務例外不在 Controller 攔截
- 依使用者決定：交給 Laravel 例外處理器。好處是 Sentry 透過既有 `Integration::handles` 自動收到，不需在 Controller 呼叫 `report()`
- **替代方案**：Controller 內 catch `\Throwable` + `report()` + 專案格式 500，或在 `withExceptions` 全域統一 JSON 500 格式。前者造成兩個端點格式與其他端點不一致；後者影響全站所有端點，範圍超出 M7。兩者皆不採用

### D3：測試方式
- **觸發資料庫例外**：在測試內註冊 Eloquent model 事件，於交易內拋出 `Illuminate\Database\QueryException`，其 SQL 字串含資料表與欄位名稱。這樣能真的經過 Controller 的 catch 路徑，不需 mock DB facade
  - 例外必須發生在交易內**已有一筆寫入之後**，否則「資料沒有變動」在沒有 rollback 時也會成立，無法證明 rollback：建立預約用 `Booking::created`（INSERT 之後）；確認預約用 `CourseSchedule::updating`（預約狀態已更新、`increment` 時觸發；Laravel 11 的 `Model::increment` 會觸發 `updating` 事件）
- **斷言 500 body**：以 `assertExactJson(['message' => 'Server Error'])` 鎖住完整 body，避免空 body 也讓「不含 SQL」的斷言通過
- **觸發 store 交易內的名額不足**：此分支只在併發時發生（交易前已有相同檢查）。測試以 `CourseSchedule::retrieved` 事件，在交易內第二次讀取時段時把 `current_participants` 設為滿額，模擬另一筆預約搶先佔位
- **斷言回報**：以 `Exceptions::fake()` 搭配 `Exceptions::assertReported(QueryException::class)`；fake 仍委派原處理器 render，因此可同時斷言 500 回應。Sentry 本身是否收到屬於整合設定，CI 無法驗證，以「例外有被回報」作為可測量的替代
- **斷言不洩漏**：設定 `config(['app.debug' => false])` 後，斷言 body 不含 `insert into`／`update`、`bookings` 與測試用 SQL 中的欄位名稱
- **業務失敗的 422**：以 `assertExactJson` 斷言完整 body，確保訊息文字沒有被改動；並在 `Exceptions::fake()` 下斷言 `Exceptions::assertNotReported(BookingRuleViolationException::class)`，業務失敗不應出現在 Sentry
- **變異驗證**：暫時把 catch 改回 `\RuntimeException`，確認 500 測試會失敗。這是本機手動步驟、不提交，放在 tasks 的手動驗證 group，不算 Scenario 覆蓋

## 安全確認

- **輸入驗證**：沿用既有 `$request->validate()`，不變；沒有拼接 SQL 或 shell
- **資料庫存取**：沿用 Eloquent／Query Builder 與 `lockForUpdate`，不變；不新增查詢，無 N+1 變化
- **權限**：路由不變。`POST /api/member/bookings` 掛 `auth:sanctum` + `member` middleware；`PUT /api/provider/bookings/{id}/confirm` 掛 `auth:sanctum` + `provider` middleware，Controller 內 `authorizeProvider()` 檢查預約屬於呼叫者的課程。本次不改任何權限邏輯
- **資訊洩漏**：本次的主要目的；回給使用者的訊息只剩 D1 中的程式內常值，以及 Laravel 的通用 500 訊息
- **正式環境前提**：500 body 不洩漏細節依賴 `APP_DEBUG=false`；2026-10-18 已在 VPS 確認（H2）

## Risks / Trade-offs

- [資料庫錯誤的 HTTP 狀態由 422 改為 500，前端與監控會看到更多 5xx] → 這是正確分類；目前前端都以 `e.response?.data?.message` 顯示，不會壞掉，只是文字變成 `Server Error`
- [之後有人在交易閉包內又拋一般 `RuntimeException` 表示業務失敗，會變成 500] → 測試以 `assertExactJson` 鎖住三個業務訊息的 422；新增業務規則時必須使用 `BookingRuleViolationException`
- [死結、逾時這類可重試的暫時性錯誤，使用者只看到 `Server Error`，沒有「請稍後重試」的提示] → 本次接受；若之後要區分可重試錯誤，屬於全站錯誤格式的另一個 change
- [`APP_DEBUG=true` 的環境（本機、若有預備環境）500 body 仍會含例外訊息與 SQL] → 這是 Laravel debug 模式的設計行為，只有正式環境（已確認 `APP_DEBUG=false`）保證不洩漏；任何對外開放的環境都必須設為 false
- [SQLite 測試無法重現真實的死結或逾時] → 測試只需要證明「任何 `QueryException` 都會走 500」，例外類型本身與資料庫驅動無關，SQLite 足以驗證分類邏輯

## Migration Plan

純程式碼變更，無 migration、無設定變更。部署後若需回滾，revert 這個 PR 即可。
