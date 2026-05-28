## ADDED Requirements

### Requirement: 發送文字訊息
Member 與 Provider SHALL 能在 `confirmed` 狀態的預約中透過 `POST /api/bookings/{id}/messages` 發送文字訊息。訊息儲存後系統 SHALL 廣播 `MessageSent` event 至 `presence-booking.{id}` 頻道。

#### Scenario: 成功發送文字訊息
- **WHEN** 已認證的 Member 或 Provider 對自己參與的 `confirmed` 預約送出 `POST /api/bookings/{id}/messages`，body 為 `{ type: 'text', content: '...' }`
- **THEN** 系統建立 `BookingMessage` 記錄，回傳 201，並透過 WebSocket 廣播 `MessageSent` event（含 `id`、`sender_id`、`sender_type`、`type`、`content`、`created_at`）

#### Scenario: 預約非 confirmed 狀態時不可發送
- **WHEN** 預約 status 不為 `confirmed`
- **THEN** 系統回傳 403，告知訊息功能僅在預約確認期間開放

#### Scenario: 非參與方不可發送
- **WHEN** 非該預約的 Member 或對應 Provider 嘗試發送訊息
- **THEN** 系統回傳 403 Forbidden

#### Scenario: 訊息內容不可為空
- **WHEN** `content` 為空字串或未提供
- **THEN** 系統回傳 422，`errors.content` 說明必填

### Requirement: 發送圖片訊息
Member 與 Provider SHALL 能在 `confirmed` 預約中上傳圖片作為訊息。圖片透過 HTTP multipart POST 上傳至 Laravel Storage，系統建立 `type: image` 的 `BookingMessage` 並廣播圖片 URL。

#### Scenario: 成功上傳並發送圖片
- **WHEN** 已認證使用者對 `confirmed` 預約送出 `POST /api/bookings/{id}/messages`，`Content-Type: multipart/form-data`，`type: image`，`file` 為有效圖片（jpg/png/gif/webp，≤ 10MB）
- **THEN** 系統儲存圖片至 Storage，建立 `BookingMessage`（`content` 存圖片 URL），廣播 `MessageSent` event，回傳 201

#### Scenario: 不支援的檔案類型被拒絕
- **WHEN** 上傳的 `file` 非 jpg/png/gif/webp
- **THEN** 系統回傳 422，`errors.file` 說明僅支援圖片格式

#### Scenario: 超過大小限制被拒絕
- **WHEN** 圖片檔案大小超過 10MB
- **THEN** 系統回傳 422，`errors.file` 說明大小限制

### Requirement: 讀取訊息歷史
Member 與 Provider SHALL 能透過 `GET /api/bookings/{id}/messages` 取得該預約的完整訊息歷史，按 `created_at` 升序排列。

#### Scenario: 成功取得訊息歷史
- **WHEN** 已認證的參與方送出 `GET /api/bookings/{id}/messages`
- **THEN** 系統回傳訊息陣列，每筆包含 `id`、`sender_id`、`sender_type`、`type`、`content`、`read_at`、`created_at`

#### Scenario: 非參與方無法讀取歷史
- **WHEN** 非該預約參與方嘗試讀取訊息
- **THEN** 系統回傳 403 Forbidden

#### Scenario: 預約完成後仍可讀取歷史
- **WHEN** 預約 status 為 `completed`，參與方送出 `GET /api/bookings/{id}/messages`
- **THEN** 系統回傳完整歷史訊息（唯讀，不可繼續發送）

### Requirement: 訊息封存
預約狀態轉換為 `completed` 時，系統 SHALL 自動關閉對應 Presence Channel，訊息歷史轉為唯讀。終態（rejected、expired、member_cancelled、provider_cancelled）的預約 SHALL 不具備訊息功能。

#### Scenario: completed 後不可發送新訊息
- **WHEN** 預約 status 為 `completed`，任一方嘗試 `POST /api/bookings/{id}/messages`
- **THEN** 系統回傳 403，告知預約已結束，訊息已封存

#### Scenario: 終態預約無訊息記錄也無法發送
- **WHEN** 預約 status 為 `rejected`、`expired`、`member_cancelled` 或 `provider_cancelled`
- **THEN** `GET /api/bookings/{id}/messages` 回傳空陣列；`POST` 回傳 403

### Requirement: 標記訊息已讀
接收方讀取訊息時，系統 SHALL 更新 `read_at`，並廣播 `MessageRead` event 至頻道。

#### Scenario: 成功標記已讀
- **WHEN** 已認證使用者送出 `POST /api/bookings/{id}/messages/read`（含 `last_read_message_id`）
- **THEN** 系統將該訊息及之前所有訊息（自己未讀的）的 `read_at` 更新為當前時間，廣播 `MessageRead` event（含 `reader_type`、`last_read_message_id`）

#### Scenario: 不可標記自己的訊息
- **WHEN** 發送方嘗試標記自己發出的訊息為已讀
- **THEN** 系統忽略（不更新、不廣播），回傳 200

### Requirement: 未讀訊息計數 Endpoint
系統 SHALL 提供 `GET /api/bookings/messages/unread-counts` endpoint，一次回傳當前使用者所有相關預約的未讀訊息數，避免前端逐筆呼叫造成 N+1 請求。

#### Scenario: 取得未讀計數
- **WHEN** 已認證的 Member 或 Provider 送出 `GET /api/bookings/messages/unread-counts`
- **THEN** 系統回傳 `{ status: true, data: { "{bookingId}": count, ... } }`，僅包含未讀數 > 0 的 booking；無未讀則回傳空物件 `{}`
- **AND** 計算邏輯為：對方發送（`sender_type` 為對方角色）且 `read_at IS NULL` 的訊息數量

#### Scenario: 路由優先順序正確
- **WHEN** 路由檔案定義 `/bookings/messages/unread-counts`
- **THEN** 該路由必須在 `/bookings/{booking}/messages` 之前註冊，以防 Route Model Binding 將 `messages` 誤判為 `{booking}` 參數

### Requirement: 預約列表未讀角標
前端 SHALL 在預約列表（Member 的 `MyBookingsView`、Coach 的 `BookingManagerView`）的每筆預約卡片上，以紅色角標顯示未讀訊息數。

#### Scenario: 有未讀訊息時顯示角標
- **WHEN** `GET /api/bookings/messages/unread-counts` 回傳某 booking 的 count > 0
- **THEN** 對應預約卡片上顯示紅色角標（數字）

#### Scenario: 開啟聊天視窗後角標即時清零
- **WHEN** 使用者展開某預約的 `BookingChat` 元件，元件 mount 時呼叫 `markRead`
- **THEN** `BookingChat` emit `read` 事件，父層呼叫 `clearCount(bookingId)` 即時清零角標，無需等下一輪 60s 輪詢

### Requirement: 站內通知（Bell Icon）即時更新
當使用者收到新的聊天訊息時，系統 SHALL 即時更新其 Bell Icon 的未讀通知計數，延遲不超過廣播傳輸時間（毫秒級），不依賴輪詢。

#### Scenario: 發送訊息觸發接收方 Bell 更新
- **WHEN** 使用者 A 透過 `POST /api/bookings/{id}/messages` 成功發送訊息
- **THEN** 系統同步（非 queue）寫入 `notifications` 資料表（`NewBookingMessageNotification`，database channel only）
- **AND** 系統 broadcast `NotificationCreated` event 至接收方的 `private-App.Models.User.{receiverId}` 頻道
- **AND** 接收方前端收到 `.notification.created` 事件後立即重新 fetch `/notifications/unread-count`

#### Scenario: 通知 title 包含寄件方姓名
- **WHEN** 通知寫入 DB
- **THEN** `title` 欄位格式為 `"{senderLabel} 傳來新訊息"`，其中 Provider 的 senderLabel 加上「教練 」前綴（例：「教練 王小明 傳來新訊息」），Member 直接使用姓名

#### Scenario: 不寄送 Email 通知
- **WHEN** 新訊息通知寫入
- **THEN** `via()` 僅回傳 `['database']`，不透過 mail channel 發送 Email

### Requirement: 瀏覽器 Web Notification（背景通知）
當使用者的瀏覽器分頁處於背景（`document.hidden === true`）時，系統 SHALL 觸發瀏覽器原生通知，告知有新的聊天訊息。

#### Scenario: 分頁在背景時收到訊息顯示通知
- **WHEN** `BookingChat` 收到對方的 `MessageSent` event
- **AND** `document.hidden === true`
- **AND** `Notification.permission === 'granted'`
- **THEN** 系統建立一則 `Notification`，`tag: 'booking-chat-{bookingId}'`（防止同預約疊加多則）

#### Scenario: 分頁在前景時不顯示通知
- **WHEN** `BookingChat` 收到對方的 `MessageSent` event
- **AND** `document.hidden === false`（使用者正在看畫面）
- **THEN** 系統不推送 Web Notification（避免干擾）

#### Scenario: 元件 mount 時請求通知權限
- **WHEN** `BookingChat` 元件 mount
- **THEN** 呼叫 `Notification.requestPermission()`，取得使用者授權後方可推送通知

#### Scenario: 自己的訊息不觸發通知
- **WHEN** `MessageSent` event 的 `sender_type` 與當前使用者角色相同
- **THEN** 不推送 Web Notification（只對「對方」的訊息觸發）
