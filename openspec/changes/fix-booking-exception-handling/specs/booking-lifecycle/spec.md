## ADDED Requirements

### Requirement: 預約交易錯誤分類
建立預約（`POST /api/member/bookings`）與確認預約（`PUT /api/provider/bookings/{id}/confirm`）的交易中，系統 SHALL 區分業務規則失敗與伺服器端故障：業務規則失敗（名額不足、重複預約）回傳 422 與既定訊息；其他任何例外（含資料庫錯誤）MUST NOT 以 422 回傳，MUST 回傳 500，回應內容不得包含 SQL、資料表或欄位名稱，且該例外 MUST 交由例外回報機制記錄（正式環境會送至 Sentry）。兩種情況下交易都 MUST rollback，不留下部分寫入。

#### Scenario: 重複預約仍回 422 與既定訊息
- **WHEN** 已登入 member 對同一時段已有 `pending` 預約，再次送出 `POST /api/member/bookings`（`schedule_id` 相同、`participants` 1）
- **THEN** 系統回傳 422，body 為 `{"status": false, "message": "您已預約此時段"}`，該 member 在此時段的預約筆數不變

#### Scenario: 交易內名額不足仍回 422 與既定訊息
- **WHEN** 已登入 member 送出 `POST /api/member/bookings` 時通過交易前的名額檢查，但交易內鎖定時段後重新計算的剩餘名額小於 `participants`
- **THEN** 系統回傳 422，body 為 `{"status": false, "message": "名額不足，請重新選擇"}`，不建立預約

#### Scenario: 確認預約時名額不足仍回 422 與既定訊息
- **WHEN** 已登入 provider 對自己課程的 `pending` 預約送出 `PUT /api/provider/bookings/{id}/confirm`，而該預約人數大於時段剩餘名額
- **THEN** 系統回傳 422，body 為 `{"status": false, "message": "名額不足，無法確認此預約"}`，預約維持 `pending`，`current_participants` 不變

#### Scenario: 建立預約時資料庫錯誤回 500 且不洩漏細節
- **WHEN** `APP_DEBUG` 為 false，已登入 member 送出合法的 `POST /api/member/bookings`，而交易內寫入預約之後發生資料庫例外
- **THEN** 系統回傳 500，body 為通用錯誤訊息且不含 SQL 語句、`bookings` 等資料表名稱與欄位名稱，資料庫例外被回報，`bookings` 筆數不變

#### Scenario: 確認預約時資料庫錯誤回 500 且不洩漏細節
- **WHEN** `APP_DEBUG` 為 false，已登入 provider 對自己課程的 `pending` 預約送出 `PUT /api/provider/bookings/{id}/confirm`（名額足夠），而交易內預約狀態已更新、更新時段名額時發生資料庫例外
- **THEN** 系統回傳 500，body 為通用錯誤訊息且不含 SQL 語句、資料表名稱與欄位名稱，資料庫例外被回報，預約維持 `pending`，`current_participants` 不變
