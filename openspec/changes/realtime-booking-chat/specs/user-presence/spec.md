## ADDED Requirements

### Requirement: 訂閱預約 Presence Channel
已認證的 Member 與 Provider SHALL 能透過 Laravel Echo 訂閱 `presence-booking.{booking_id}` 頻道，訂閱時系統 SHALL 驗證使用者確為該預約的參與方。

#### Scenario: 合法參與方成功訂閱
- **WHEN** 已認證使用者帶有效 Bearer token 連線至 `wss://ws.hank-space.com`，並訂閱 `presence-booking.{id}`
- **THEN** `broadcasting/auth` 端點回傳授權成功，使用者加入頻道，頻道廣播 `joining` event（含 `user_id`、`user_type`）

#### Scenario: 非參與方訂閱被拒絕
- **WHEN** 非該預約參與方嘗試訂閱頻道
- **THEN** `broadcasting/auth` 回傳 403，連線不建立

#### Scenario: 預約非 confirmed 狀態時頻道不授權
- **WHEN** 預約 status 不為 `confirmed`，任何使用者嘗試訂閱
- **THEN** `broadcasting/auth` 回傳 403

### Requirement: 在線狀態感知
訂閱 Presence Channel 後，系統 SHALL 回傳目前在線成員清單。任一方加入或離開時 SHALL 廣播對應事件。

#### Scenario: 取得目前在線清單
- **WHEN** 使用者成功加入 `presence-booking.{id}` 頻道
- **THEN** Echo `.here()` callback 收到目前在線的使用者清單（含 `user_id`、`user_type`、`name`）

#### Scenario: 對方加入頻道
- **WHEN** 對方（Member 或 Provider）開啟訊息視窗並成功訂閱頻道
- **THEN** 已在頻道中的使用者收到 `.joining()` event，可顯示「對方已上線」

#### Scenario: 對方離開頻道
- **WHEN** 對方關閉訊息視窗或斷線
- **THEN** 仍在頻道中的使用者收到 `.leaving()` event，可顯示「對方已離線」

### Requirement: 已讀回執顯示
前端 SHALL 依據 `MessageRead` event 更新訊息的已讀狀態，顯示「已讀」標記。

#### Scenario: 己方訊息被對方讀取後顯示已讀
- **WHEN** 頻道收到 `MessageRead` event，`reader_type` 為對方，`last_read_message_id` >= 某訊息 id
- **THEN** 前端將該訊息及之前的己方訊息顯示「已讀」標記

#### Scenario: 對方不在頻道時訊息顯示未讀
- **WHEN** 對方未訂閱頻道（離線）
- **THEN** 新發送的訊息 `read_at` 為 null，顯示「未讀」狀態

### Requirement: 未讀訊息計數
系統 SHALL 在預約列表頁顯示每個預約的未讀訊息數量角標。

#### Scenario: 有未讀訊息時顯示角標
- **WHEN** Member 或 Provider 進入預約列表，某預約有 `read_at IS NULL` 且 `sender_type` 為對方的訊息
- **THEN** 對應預約卡片顯示未讀數量角標（數字或紅點）

#### Scenario: 進入訊息視窗後角標清除
- **WHEN** 使用者進入該預約的訊息視窗，送出 `POST /api/bookings/{id}/messages/read`
- **THEN** 未讀角標消失
