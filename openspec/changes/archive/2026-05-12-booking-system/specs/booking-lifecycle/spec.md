## ADDED Requirements

### Requirement: Member 送出預約
Member SHALL 能選擇一個開放時段送出預約，系統記錄價格快照。

#### Scenario: 成功建立預約
- **WHEN** 已登入 Member 送出 `POST /api/member/bookings`，指定 `schedule_id` 與 `participants`（≥1）
- **THEN** 系統建立 Booking，status 為 `pending`，`total_price` 快照為 `diving_offer.price × participants`，回傳 201

#### Scenario: 時段已滿無法預約
- **WHEN** 指定時段 status 為 `full` 或 `cancelled`
- **THEN** 系統回傳 422，告知時段不可用

#### Scenario: 超過剩餘名額（API 層快速驗證）
- **WHEN** `participants` 大於時段當前剩餘名額（`max_participants - current_participants`），在進入 DB transaction 前
- **THEN** 系統回傳 422，告知人數超過上限，不進入 lockForUpdate 流程

#### Scenario: 超過剩餘名額（DB 層二次驗證）
- **WHEN** API 層通過但 lockForUpdate 後重新計算剩餘名額仍不足（race condition 情境）
- **THEN** 系統 rollback transaction，回傳 422，告知名額不足

#### Scenario: 不可重複預約同一時段
- **WHEN** Member 對同一 `schedule_id` 已有 `pending` 或 `confirmed` 狀態的 Booking
- **THEN** 系統回傳 422，告知已有預約

### Requirement: 預約狀態機
系統 SHALL 維護七個合法狀態，且只允許以下轉換：
- `pending` → `confirmed`（Provider 確認）
- `pending` → `rejected`（Provider 拒絕）
- `pending` → `member_cancelled`（Member 取消）
- `pending` → `expired`（Scheduler 超時）
- `confirmed` → `completed`（Scheduler 課程後自動）
- `confirmed` → `member_cancelled`（Member 取消）
- `confirmed` → `provider_cancelled`（Provider 取消）

#### Scenario: 非法狀態轉換被拒絕
- **WHEN** 任何角色嘗試執行上述以外的狀態轉換
- **THEN** 系統回傳 422，說明當前狀態不允許此操作

### Requirement: Provider 確認或拒絕預約
Provider SHALL 能對自己課程的 `pending` 預約執行確認或拒絕。

#### Scenario: 確認預約
- **WHEN** Provider 送出 `PUT /api/provider/bookings/{id}/confirm`
- **THEN** Booking status 改為 `confirmed`，時段 `current_participants` 更新

#### Scenario: 拒絕預約
- **WHEN** Provider 送出 `PUT /api/provider/bookings/{id}/reject`
- **THEN** Booking status 改為 `rejected`

#### Scenario: 只能操作自己課程的預約
- **WHEN** Provider 嘗試操作不屬於自己課程的 Booking
- **THEN** 系統回傳 403 Forbidden

### Requirement: Provider 取消已確認預約
Provider SHALL 能取消 `confirmed` 狀態的預約（例如天氣因素）。

#### Scenario: Provider 取消確認中預約
- **WHEN** Provider 送出 `PUT /api/provider/bookings/{id}/cancel`
- **THEN** Booking status 改為 `provider_cancelled`，時段名額釋放

### Requirement: Member 取消預約
Member SHALL 能取消自己的 `pending` 或 `confirmed` 預約，但須在課程開始前 24 小時之前提出。

#### Scenario: 取消 pending 預約（期限內）
- **WHEN** Member 送出 `DELETE /api/member/bookings/{id}`，Booking status 為 `pending`，且當前時間早於 `scheduled_date + start_time - 24h`
- **THEN** Booking status 改為 `member_cancelled`

#### Scenario: 取消 confirmed 預約（期限內）
- **WHEN** Member 送出 `DELETE /api/member/bookings/{id}`，Booking status 為 `confirmed`，且當前時間早於 `scheduled_date + start_time - 24h`
- **THEN** Booking status 改為 `member_cancelled`，時段名額釋放

#### Scenario: 課程開始前 24h 內不可取消
- **WHEN** Member 送出 `DELETE /api/member/bookings/{id}`，但當前時間距 `scheduled_date + start_time` 不足 24 小時
- **THEN** 系統回傳 422，告知「距課程開始不足 24 小時，無法取消，請聯繫教練」；Booking 狀態不變

#### Scenario: 不可取消已終態預約
- **WHEN** Booking status 為 `completed`、`rejected`、`expired`、`provider_cancelled`
- **THEN** 系統回傳 422，告知無法取消

### Requirement: 系統自動過期 pending 預約
Scheduler SHALL 每小時掃描 `pending` 超過 48 小時的 Booking 並標記為 `expired`。

#### Scenario: 過期觸發
- **WHEN** Booking status 為 `pending` 且 `created_at` 早於 48 小時前
- **THEN** Scheduler 將 status 改為 `expired`

### Requirement: 系統自動完成 confirmed 預約
Scheduler SHALL 每日掃描課程日期已過的 `confirmed` Booking 並標記為 `completed`。

#### Scenario: 自動完成
- **WHEN** Booking status 為 `confirmed`，對應 `course_schedule.scheduled_date` 早於今天
- **THEN** Scheduler 將 status 改為 `completed`

### Requirement: Member 查看自己的預約列表
Member SHALL 能查詢自己所有預約的列表及詳情。

#### Scenario: 取得預約列表
- **WHEN** 已登入 Member 送出 `GET /api/member/bookings`
- **THEN** 系統回傳該 Member 所有 Booking，含課程名稱、時段日期、狀態、金額

#### Scenario: 取得單一預約詳情
- **WHEN** 已登入 Member 送出 `GET /api/member/bookings/{id}`
- **THEN** 系統回傳該 Booking 詳情；若非本人預約則回傳 403
