# Tasks

測試集中在新檔 `tests/Feature/BookingExceptionHandlingTest.php`（建立 provider／時段的輔助方法參考 `tests/Feature/BookingOversellTest.php`）。每個測試的 docblock 要寫明「為什麼」：資料庫錯誤曾以 422 把 SQL 回給使用者，且不會送到 Sentry。觸發與斷言方式見 design.md D3。

## 1. 業務例外與 Controller

- [x] 1.1 [後端] 新增 `app/Exceptions/BookingRuleViolationException.php`（`final class`，繼承 `\RuntimeException`，不加額外方法）；驗證：2.x 測試通過
- [x] 1.2 [後端] `app/Http/Controllers/API/MemberBookingController.php` 的 `store`：交易閉包內兩處 `throw new \RuntimeException(...)` 改為 `throw new BookingRuleViolationException(...)`，訊息文字不變；`catch (\RuntimeException $e)` 改為 `catch (BookingRuleViolationException $e)`
- [x] 1.3 [後端] `app/Http/Controllers/API/ProviderBookingController.php` 的 `confirm`：同 1.2，處理 `名額不足，無法確認此預約`
- [x] 1.4 [後端] 確認 `grep -rn "RuntimeException" app/` 只剩 `BookingRuleViolationException` 本身的繼承宣告

## 2. 測試

- [x] 2.1 [整合測試] `test_duplicate_booking_returns_422_with_rule_message`：member 已有該時段 `pending` 預約，再次 `POST /api/member/bookings`，回 422，`assertExactJson(['status' => false, 'message' => '您已預約此時段'])`，該 member 在此時段的預約仍為 1 筆；測試開頭 `Exceptions::fake()`，最後 `Exceptions::assertNotReported(BookingRuleViolationException::class)`（對應 Scenario「重複預約仍回 422 與既定訊息」）
- [x] 2.2 [整合測試] `test_capacity_lost_inside_transaction_returns_422_with_rule_message`：以 `CourseSchedule::retrieved` 事件在交易內把 `current_participants` 設為滿額（事件只能改動交易內 `lockForUpdate()->find()` 那次讀取；交易前的第一次讀取若被改動，會被前置檢查以「人數超過剩餘名額」攔下，測不到交易內路徑），`POST /api/member/bookings` 回 422，`assertExactJson(['status' => false, 'message' => '名額不足，請重新選擇'])`，`bookings` 筆數為 0；同 2.1 以 `Exceptions::fake()` 斷言未回報（對應 Scenario「交易內名額不足仍回 422 與既定訊息」）
- [x] 2.3 [整合測試] `test_confirm_without_capacity_returns_422_with_rule_message`：provider 確認人數大於剩餘名額的 `pending` 預約，回 422，`assertExactJson(['status' => false, 'message' => '名額不足，無法確認此預約'])`，預約仍為 `pending`、`current_participants` 不變；同 2.1 以 `Exceptions::fake()` 斷言未回報（對應 Scenario「確認預約時名額不足仍回 422 與既定訊息」）
- [x] 2.4 [整合測試] `test_database_error_on_create_returns_500_without_sql`：`config(['app.debug' => false])`、`Exceptions::fake()`，`Booking::created`（INSERT 已執行之後）拋出 SQL 含 `insert into "bookings"` 與欄位名稱的 `QueryException`；合法 `POST /api/member/bookings` 回 500，`assertExactJson(['message' => 'Server Error'])`，body 不含 `insert into`、`bookings`、`member_id`，`Exceptions::assertReported(QueryException::class)`，`bookings` 筆數為 0（INSERT 已發生，筆數為 0 才能證明 rollback）（對應 Scenario「建立預約時資料庫錯誤回 500 且不洩漏細節」）
- [x] 2.5 [整合測試] `test_database_error_on_confirm_returns_500_without_sql`：同 2.4 的設定，`CourseSchedule::updating`（在交易內預約狀態已更新、`increment` 執行時觸發）拋出 SQL 含 `update "course_schedules"` 的 `QueryException`；provider 確認名額足夠的 `pending` 預約，回 500，`assertExactJson(['message' => 'Server Error'])`，body 不含 `update`、`course_schedules`、`current_participants`，`Exceptions::assertReported(QueryException::class)`，預約仍為 `pending`（狀態更新已執行，仍為 `pending` 才能證明 rollback）、`current_participants` 不變（對應 Scenario「確認預約時資料庫錯誤回 500 且不洩漏細節」）
- [x] 2.6 [整合測試] 執行 `php artisan test --filter='BookingLifecycleTest|BookingOversellTest'`，確認既有 422 行為不變；完整 `php artisan test` 需在有 GD 的環境執行（見 `cfdive-test-gd` image 的做法）

## 3. 手動驗證

- [x] 3.1 [手動驗證] 變異驗證（只在本機執行，不提交）：暫時把 1.2、1.3 的 catch 改回 `\RuntimeException`，確認 2.4、2.5 失敗後再還原，證明測試抓得到原本的問題
- [ ] 3.2 [手動驗證] 部署後在正式環境建立一筆預約、確認一筆預約，確認業務流程正常（201／200）；Sentry 是否收到預約端點的 500 只能等實際錯誤發生，若期間沒有錯誤則記錄為「未觀察到」，不視為失敗（自動化驗證以 2.4、2.5 為準）
