### Requirement: Provider 建立開課時段
Provider SHALL 能為自己擁有的 DivingOffer 建立開課時段，指定日期、開始時間、人數上限。

#### Scenario: 成功建立時段
- **WHEN** Provider 送出 `POST /api/provider/schedules`，包含合法的 `diving_offer_id`、`scheduled_date`（未來日期）、`start_time`、`max_participants`（≥1）
- **THEN** 系統建立 CourseSchedule，status 為 `open`，回傳 201 與新時段資料

#### Scenario: 不可為他人課程建立時段
- **WHEN** Provider 送出的 `diving_offer_id` 屬於其他 Provider
- **THEN** 系統回傳 403 Forbidden

#### Scenario: 日期不可為過去
- **WHEN** `scheduled_date` 早於今天
- **THEN** 系統回傳 422，錯誤訊息指出日期無效

### Requirement: Provider 管理既有時段
Provider SHALL 能更新或取消自己的開課時段。

#### Scenario: 更新時段資訊
- **WHEN** Provider 送出 `PUT /api/provider/schedules/{id}`，修改 `start_time` 或 `max_participants`
- **THEN** 系統更新時段資料，回傳更新後內容

#### Scenario: 取消時段
- **WHEN** Provider 送出 `DELETE /api/provider/schedules/{id}`
- **THEN** 系統將時段 status 改為 `cancelled`，不實體刪除；cascade 處理所有相關 Booking（詳見下方 Requirement）

#### Scenario: 不可修改他人時段
- **WHEN** Provider 嘗試修改不屬於自己的時段
- **THEN** 系統回傳 403 Forbidden

### Requirement: 取消時段的 Booking Cascade 處理
Provider 取消時段時，系統 SHALL 在同一 DB transaction 內處理該時段下所有活躍 Booking，並明確定義各狀態的 cascade 規則。

#### Scenario: pending Booking cascade 為 provider_cancelled
- **WHEN** Provider 取消時段，時段下存在 status 為 `pending` 的 Booking
- **THEN** 這些 Booking status 全部改為 `provider_cancelled`

#### Scenario: confirmed Booking cascade 為 provider_cancelled
- **WHEN** Provider 取消時段，時段下存在 status 為 `confirmed` 的 Booking
- **THEN** 這些 Booking status 全部改為 `provider_cancelled`，`current_participants` 不需調整（時段已取消）

#### Scenario: 終態 Booking 不受 cascade 影響
- **WHEN** Provider 取消時段，時段下存在 status 為 `completed`、`rejected`、`expired`、`member_cancelled`、`provider_cancelled` 的 Booking
- **THEN** 這些 Booking status 維持不變

#### Scenario: cascade 在同一 transaction 內完成
- **WHEN** Provider 取消時段
- **THEN** 時段狀態更新與所有 Booking cascade 更新在同一 DB transaction 內完成；任一失敗則全部 rollback，API 回傳 500

### Requirement: 時段人數自動管理
系統 SHALL 在預約確認時自動累計 `current_participants`，並於額滿時將時段 status 改為 `full`。`current_participants` 只計算 confirmed 人數，pending 不佔位。

#### Scenario: 預約確認後人數更新
- **WHEN** Provider 確認一筆 Booking（confirmed），booking 的 `participants` 為 N
- **THEN** `course_schedules.current_participants` 增加 N；若達到 `max_participants` 則 status 改為 `full`

#### Scenario: 預約取消後人數釋放
- **WHEN** 一筆 `confirmed` 狀態的 Booking 被取消（member_cancelled 或 provider_cancelled）
- **THEN** `current_participants` 減少對應人數；若原本為 `full` 則 status 改回 `open`

### Requirement: Member 查詢可用時段
Member SHALL 能查詢指定課程的可用開課時段列表。

#### Scenario: 取得開放時段
- **WHEN** 任何人（含未登入）送出 `GET /api/diving-offers/{id}/schedules`
- **THEN** 系統回傳該課程 status 為 `open`、日期未過的時段列表（含剩餘名額 `remaining_spots`），依日期升冪排序

#### Scenario: 已滿時段不顯示
- **WHEN** 時段 status 為 `full`
- **THEN** 不包含在上述列表中
