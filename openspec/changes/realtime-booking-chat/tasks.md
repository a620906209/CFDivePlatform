## 1. 基礎建設與套件安裝

- [x] 1.1 [後端] 安裝 `laravel/reverb`：`composer require laravel/reverb`，執行 `php artisan reverb:install` 生成 `config/reverb.php`
- [x] 1.2 [後端] 安裝 `intervention/image`：`composer require intervention/image`；用途：上傳圖片時移除 EXIF（含 GPS 座標）、強制重新編碼為 jpg/png 以防格式偽裝、限制最大尺寸（長邊 2048px）
- [x] 1.3 [前端] 安裝 `laravel-echo` 與 `pusher-js`：`npm install laravel-echo pusher-js`
- [x] 1.4 [後端] 更新 `.env`：設定 `BROADCAST_CONNECTION=reverb`、`REVERB_APP_ID=cfdive`、`REVERB_APP_KEY`（32 字元隨機）、`REVERB_APP_SECRET`（32 字元隨機）、`REVERB_HOST=reverb`（Docker service DNS，非 bind address）、`REVERB_PORT=8080`；另補 Vite 前端用的 `VITE_REVERB_APP_KEY`、`VITE_REVERB_HOST=ws.hank-space.com`、`VITE_REVERB_PORT=443`、`VITE_REVERB_SCHEME=https`
- [x] 1.5 [後端] 在 `config/broadcasting.php` 確認 `reverb` driver 設定正確（`host`、`port` 從 env 讀取）
- [x] 1.6 [後端] 在 `bootstrap/app.php` 啟用 broadcasting：確認 `->withBroadcasting()` 已加入，或在 `routes/api.php` 明確呼叫 `Broadcast::routes(['middleware' => ['auth:sanctum']])` 以確保 `/broadcasting/auth` endpoint 存在並受 Sanctum 保護

## 2. Docker 與 Infrastructure 設定

- [x] 2.1 [後端] 在 `docker-compose.yml` 新增 `reverb` service：複用 `cfdive-platform` image，`command: php artisan reverb:start --host=0.0.0.0 --port=8080 --debug`，連接 `cfdive-network` 與 `proxy_net`，`restart: unless-stopped`，`depends_on: app`
- [ ] 2.2 [Infrastructure] 在 DNS 新增 A Record：`ws.hank-space.com` → VPS IP
- [ ] 2.3 [Infrastructure] 在 Nginx Proxy Manager 新增 Proxy Host：Domain `ws.hank-space.com`，Forward 至 `reverb:8080`，啟用 WebSocket support，申請 SSL 憑證
- [ ] 2.4 [後端] 執行 `docker-compose up --build reverb` 驗證 Reverb 容器啟動正常

## 3. 資料庫

- [x] 3.1 [後端] 建立 migration `create_booking_messages_table`：欄位 `id`、`booking_id`（FK）、`sender_id`（FK users）、`sender_type`（enum: member/provider）、`type`（enum: text/image）、`content`（text）、`read_at`（timestamp nullable）、`timestamps`；加 index `(booking_id, created_at)`
- [x] 3.2 [後端] 執行 `php artisan migrate` 並確認資料表建立

## 4. 後端 Model、Channel 與 Event

- [x] 4.1 [後端] 建立 `app/Models/BookingMessage.php`：定義 `$fillable`、`booking()` belongsTo、`sender()` morphTo 或一般 belongsTo（依 sender_type 切換）
- [x] 4.2 [後端] 建立 `app/Broadcasting/BookingPresenceChannel.php`：實作 `join()` 方法，eager load `schedule`；Member 驗證 `booking->member_id === $user->id`，Provider 驗證 `booking->schedule->provider_id === $user->id`；`confirmed` 以外狀態返回 `false`；授權成功回傳 `['user_id', 'user_type', 'name']`
- [x] 4.3 [後端] 在 `routes/channels.php` 註冊 `Broadcast::channel('presence-booking.{bookingId}', BookingPresenceChannel::class)`
- [x] 4.4 [後端] 建立 `app/Events/MessageSent.php`：implements `ShouldBroadcastNow`（同步廣播，MVP 不走 queue），`broadcastOn()` 返回 `new PresenceChannel("presence-booking.{$this->message->booking_id}")`，`broadcastWith()` 回傳 `id`、`sender_id`、`sender_type`、`type`、`content`、`created_at`
- [x] 4.5 [後端] 建立 `app/Events/MessageRead.php`：implements `ShouldBroadcastNow`，廣播 `reader_type`、`last_read_message_id`

## 5. 後端 API

- [x] 5.1 [後端] 建立 `app/Http/Controllers/BookingMessageController.php`，方法：`index`（取得歷史）、`store`（發送文字/圖片）、`markRead`（標記已讀）
- [x] 5.2 [後端] `index` 方法：驗證使用者為參與方，回傳 `booking.messages()->orderBy('created_at')->get()`，`confirmed` 與 `completed` 均可讀取
- [x] 5.3 [後端] `store` 方法：驗證 booking status 為 `confirmed`（其他狀態回傳 403）；text 訊息驗證 `content` 非空；image 訊息驗證 `file`（mimes: jpg,png,gif,webp，max: 10240KB），存至 `Storage::disk('public')`，路徑 `booking-images/{uuid}.{ext}`，`content` 存完整 URL（`Storage::url(...)`）；建立 `BookingMessage`；dispatch `MessageSent` event（ShouldBroadcastNow）
- [x] 5.4 [後端] `markRead` 方法：更新「對方發送」且「id ≤ last_read_message_id」且「read_at IS NULL」的訊息之 `read_at`；booking status 為 `confirmed` 時 dispatch `MessageRead` event；`completed` 時只更新 DB，不 broadcast（頻道已關閉）
- [x] 5.5 [後端] 在 `routes/api.php` 新增路由（member 與 provider 各自的 auth middleware group）：`GET /api/bookings/{booking}/messages`、`POST /api/bookings/{booking}/messages`、`POST /api/bookings/{booking}/messages/read`

## 6. 前端 Echo 初始化

- [x] 6.1 [前端] 在 `frontend/src/plugins/echo.js`（新建）初始化 `Laravel Echo`：`broadcaster: 'reverb'`，`wsHost: ws.hank-space.com`，`wsPort: 443`，`wssPort: 443`，`forceTLS: true`，`enabledTransports: ['ws', 'wss']`
- [x] 6.2 [前端] 在 `frontend/src/plugins/echo.js` 支援雙 token：Member 用 `localStorage.getItem('token')`，Coach 用 `localStorage.getItem('coach_token')`，依當前角色動態設定 Authorization header；`authEndpoint` 使用完整 URL：`${import.meta.env.VITE_API_URL}/broadcasting/auth`（跨 domain 不可使用相對路徑）
- [x] 6.3 [前端] 在 `main.js` 掛載 Echo plugin

## 7. 前端訊息元件

- [x] 7.1 [前端] 建立 `frontend/src/components/BookingChat.vue`：props 接收 `bookingId`、`bookingStatus`、`currentUserType`
- [x] 7.2 [前端] `BookingChat.vue` 訂閱 `presence-booking.{bookingId}`，處理 `.here()`（顯示在線狀態）、`.joining()`、`.leaving()`
- [x] 7.3 [前端] `BookingChat.vue` 監聽 `MessageSent` event，新訊息即時 append 到訊息列表
- [x] 7.4 [前端] `BookingChat.vue` 監聽 `MessageRead` event，更新訊息「已讀」標記
- [x] 7.5 [前端] `BookingChat.vue` 實作文字輸入框與送出按鈕，call `POST /api/bookings/{id}/messages`
- [x] 7.6 [前端] `BookingChat.vue` 實作圖片上傳按鈕（`<input type="file" accept="image/*">`），以 `FormData` 送出
- [x] 7.7 [前端] `BookingChat.vue` 在 `confirmed` 狀態顯示輸入區；`completed` 狀態顯示「對話已封存」提示；其他狀態不渲染元件
- [x] 7.8 [前端] 元件 mount 時呼叫 `GET /api/bookings/{id}/messages` 載入歷史訊息，並送出 `markRead`

## 8. 前端嵌入預約詳情頁

- [x] 8.1 [前端] 在 Member 的預約詳情頁（`src/views/MyBookingsView.vue` 展開區塊）嵌入 `<BookingChat>` 元件
- [x] 8.2 [前端] 在 Coach 的預約管理頁（`src/views/coach/BookingManagerView.vue`）嵌入 `<BookingChat>` 元件，點擊「訊息」按鈕展開
- [x] 8.3 [前端] 在預約列表卡片顯示未讀訊息角標：實作 `GET /api/bookings/messages/unread-counts`（一次回傳所有 booking 的未讀數，非逐筆呼叫）；建立 `useBookingUnreadCounts` composable（60s 輪詢）；`BookingChat` 加 `emit('read')` 讓父層即時清零角標

## 8.5 訊息通知系統（實作中追加，超出原始 scope）

- [x] 8.5.1 [後端] 建立 `NewBookingMessageNotification`：`database` channel only（不寄信）；`title` 含寄件方姓名（provider 加「教練」前綴）；**不走 Queue**（同步寫入，確保廣播前 DB 已有資料）
- [x] 8.5.2 [後端] 建立 `NotificationCreated` event：`ShouldBroadcastNow`，廣播至 `private-App.Models.User.{id}`（複用 channels.php 已有的授權）；`broadcastAs()` = `'notification.created'`
- [x] 8.5.3 [後端] `BookingMessageController::store()` 在 broadcast `MessageSent` 後，同步 notify receiver，再 broadcast `NotificationCreated`
- [x] 8.5.4 [前端] `notifications.js` store 新增 `startRealtime(userId)` / `stopRealtime()`：訂閱 `private-App.Models.User.{id}`，收到 `notification.created` 立刻呼叫 `fetchUnreadCount()`
- [x] 8.5.5 [前端] `auth.js` 與 `coachAuth.js` 的 `init()` / `setAuth()` / `logout()` 均呼叫 `startRealtime` / `stopRealtime`
- [x] 8.5.6 [前端] `BookingChat.vue` 新增瀏覽器通知（Web Notifications API）：mount 時請求權限；收到對方 `MessageSent` 且 `document.hidden` 時推送；`tag: booking-chat-{id}` 防止同一預約疊加通知

## 9. 整合測試與手動驗證

- [ ] 9.1 [整合測試] 驗證 `/broadcasting/auth` 端點可存取（不是 404）：分別用 member token 與 coach token 送出請求，確認路由已正確註冊且 Sanctum middleware 生效；合法參與方回傳 200，非參與方回傳 403，非 confirmed 狀態回傳 403
- [ ] 9.2 [整合測試] 驗證 `POST /api/bookings/{id}/messages`：text 訊息成功建立且廣播；image 上傳成功且 URL 可存取；invalid 檔案回傳 422
- [ ] 9.3 [整合測試] 驗證 `markRead`：己方訊息不更新；對方訊息 `read_at` 被設定；`MessageRead` 廣播觸發
- [ ] 9.4 [整合測試] 驗證封存：`completed` 預約 POST 訊息回傳 403，GET 歷史正常回傳
- [ ] 9.5 [手動驗證] 開兩個瀏覽器分別登入 Member 與 Coach，確認訊息即時雙向傳達、在線狀態顯示正確、已讀回執正常觸發
- [ ] 9.6 [手動驗證] 測試圖片訊息：上傳圖片後對方即時看到圖片
- [ ] 9.7 [手動驗證] 關閉一個視窗，確認另一端顯示「對方已離線」
