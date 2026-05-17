## 0. 前置設定

- [x] 0.1 [後端] `config/app.php` 加入 `'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173')`
- [x] 0.2 [後端] `.env.example` 補上 `FRONTEND_URL=http://localhost:5173`

## 1. 基礎設施：資料庫與 Queue

- [x] 1.1 [後端] 執行 `php artisan notifications:table` 產生 notifications migration，確認 schema 欄位正確
- [x] 1.2 [後端] 執行 `php artisan queue:table` 產生 jobs / failed_jobs migration（若尚未存在）
- [x] 1.3 [後端] 執行 `php artisan migrate` 建立兩張資料表（需 Docker 啟動後執行）
- [x] 1.4 [後端] 在 `.env` 設定 `QUEUE_CONNECTION=database`（已存在）
- [x] 1.5 [後端] `docker-compose.yml` 新增 `queue-worker` service（`php artisan queue:work --sleep=3 --tries=3`）
- [x] 1.6 [後端] `docker-compose.yml` 新增 `mailpit` service（image: `axllent/mailpit`，port 1025/8025），`.env` 設定 `MAIL_HOST=mailpit MAIL_PORT=1025`

## 2. Notification Classes（後端）

- [x] 2.1 [後端] 建立 `app/Notifications/BookingCreatedNotification.php`，`via()` 回傳 `['database', 'mail']`，實作 `toArray()` 與 `toMail()`
- [x] 2.2 [後端] 建立 `app/Notifications/BookingConfirmedNotification.php`（`via`: database + mail）
- [x] 2.3 [後端] 建立 `app/Notifications/BookingRejectedNotification.php`（`via`: database + mail）
- [x] 2.4 [後端] 建立 `app/Notifications/BookingCancelledNotification.php`（`via`: database + mail，含 `cancelledBy` 參數）
- [x] 2.5 [後端] 建立 `app/Notifications/BookingCompletedNotification.php`（`via`: database + mail）
- [x] 2.6 [後端] 建立 `app/Notifications/ReviewReceivedNotification.php`（`via`: database 僅站內）
- [x] 2.7 [後端] 所有 Notification class 的 `toArray()` 回傳統一結構：`{ type, title, body, action_url, related_id, related_type }`

## 3. Email Markdown 模板

- [x] 3.1 [後端] 建立 `resources/views/emails/notifications/booking-created.blade.php`（Markdown）（改用 toMail() 內聯實作）
- [x] 3.2 [後端] 建立 `resources/views/emails/notifications/booking-confirmed.blade.php`
- [x] 3.3 [後端] 建立 `resources/views/emails/notifications/booking-rejected.blade.php`
- [x] 3.4 [後端] 建立 `resources/views/emails/notifications/booking-cancelled.blade.php`
- [x] 3.5 [後端] 建立 `resources/views/emails/notifications/booking-completed.blade.php`
- [x] 3.6 [後端] 確認所有模板包含：平台名稱、通知標題、正文、CTA 按鈕（action_url）、底部免責聲明

## 4. Notification API（後端）

- [x] 4.1 [後端] 建立 `app/Http/Controllers/Api/NotificationController.php`，實作 `index()`、`unreadCount()`、`markRead()`、`markAllRead()`、`destroy()`
- [x] 4.2 [後端] `routes/api.php` 新增路由群組（Sanctum middleware）
- [x] 4.3 [後端] `index()` 分頁 20 筆，依 `created_at` DESC，response 含 `unread_count` 與 `meta`
- [x] 4.4 [後端] `markRead()` / `destroy()` 驗證通知屬於當前使用者（findOrFail 在 user->notifications() 作用域內自動限制）

## 5. 業務觸發整合（後端，無 Service 層，直接插入 Controller）

- [x] 5.1 [後端] `app/Models/DivingOffer.php` 補上 `provider()` 關聯
- [x] 5.2 [後端] 確認 `app/Models/User.php` 已使用 `Notifiable` trait（已存在）
- [x] 5.3 [後端] `MemberBookingController::store()`：notify `BookingCreatedNotification`
- [x] 5.4 [後端] `ProviderBookingController::confirm()`：notify `BookingConfirmedNotification`
- [x] 5.5 [後端] `ProviderBookingController::reject()`：notify `BookingRejectedNotification`
- [x] 5.6 [後端] `MemberBookingController::cancel()`：notify `BookingCancelledNotification(cancelledBy: 'member')`
- [x] 5.7 [後端] `ProviderBookingController::cancel()`：notify `BookingCancelledNotification(cancelledBy: 'provider')`
- [x] 5.8 [後端] `ProviderBookingController::complete()`：notify `BookingCompletedNotification`
- [x] 5.9 [後端] `CompleteFinishedBookings::handle()`：改為 get()+loop，逐筆 notify
- [x] 5.10 [後端] `ReviewController::store()`：notify `ReviewReceivedNotification`

## 6. 前端 Pinia Store

- [x] 6.1 [前端] 建立 `frontend/src/stores/notifications.js`，含 state: `{ unreadCount, notifications, isOpen }`
- [x] 6.2 [前端] `notificationStore.startPolling()`：登入後立即 fetch 一次，未讀 > 0 每 30 秒、= 0 每 60 秒；count 改變時 clearInterval 重啟新間隔
- [x] 6.3 [前端] Page Visibility API 整合：`visibilitychange = hidden` 暫停 interval；`= visible` 立即 fetch 並重啟
- [x] 6.4 [前端] `notificationStore.stopPolling()`：登出時 clearInterval + removeEventListener('visibilitychange')
- [x] 6.5 [前端] `notificationStore.fetchNotifications()`：呼叫 `GET /api/notifications`，更新 `notifications` 與 `unreadCount`
- [x] 6.6 [前端] `notificationStore.markRead(id)` / `markAllRead()` / `remove(id)` actions（markRead 採 Optimistic update）

## 7. 前端通知元件

- [x] 7.1 [前端] 建立 `frontend/src/components/NotificationBell.vue`：Bell Icon + 未讀 Badge（紅色，count > 0 才顯示）
- [x] 7.2 [前端] 建立 `frontend/src/components/NotificationDrawer.vue`：側邊 Drawer，列出通知列表，每項顯示 title / body（截 80 字）/ 相對時間 / 已讀狀態
- [x] 7.3 [前端] Drawer 頂部加「全部標為已讀」按鈕，點擊後呼叫 `markAllRead()`
- [x] 7.4 [前端] 點擊通知項目：呼叫 `markRead(id)` 後 `router.push(action_url)`
- [x] 7.5 [前端] 點擊通知項目右側刪除 Icon：呼叫 `remove(id)`

## 8. 整合至 NavBar

- [x] 8.1 [前端] `frontend/src/components/NavBar.vue`（Member）：加入 `<NotificationBell />`
- [x] 8.2 [前端] `frontend/src/components/CoachNavBar.vue`（Coach）：加入 Bell Icon
- [x] 8.3 [前端] `frontend/src/App.vue`：加入 `<NotificationDrawer />`
- [x] 8.4 [前端] `frontend/src/stores/auth.js`：setAuth/init 呼叫 startPolling，logout 呼叫 stopPolling
- [x] 8.5 [前端] `frontend/src/stores/coachAuth.js`：同上整合 polling 生命週期

## 9. 手動驗證

- [x] 9.1 [整合測試] 啟動 Docker Compose（含 queue-worker + mailpit），確認所有 service 正常
- [x] 9.2 [整合測試] Member 建立預約 → Provider 站內通知出現 + Mailpit 收到信
- [x] 9.3 [整合測試] Provider 確認預約 → Member 站內通知出現 + Email
- [x] 9.4 [整合測試] Member 提交評價 → Provider 站內通知出現（無 Email）
- [x] 9.5 [整合測試] Bell Icon 未讀 Badge 顯示正確數量，全部標已讀後 Badge 消失
- [x] 9.6 [整合測試] 點擊通知項目 → 標記已讀 → 跳轉 action_url
- [x] 9.7 [整合測試] Mailpit Web UI（`http://localhost:8025`）確認 Email 格式與 CTA 連結正確

## 10. 整合測試中發現的 Bug 修正

- [x] 10.1 [前端] `main.js`：將三個 auth store 的 `init()` 移至 `app.use(router)` 之前執行，修正 `beforeEach` guard 在 store 初始化前跑導致 protected route 被誤踢的問題
- [x] 10.2 [後端] `BookingConfirmedNotification` / `BookingRejectedNotification` / `BookingCancelledNotification`：`action_url` 移除 `/{booking.id}` 尾綴，改為 `{FRONTEND_URL}/my-bookings`（前端路由無 `/my-bookings/:id` 詳情頁）
- [x] 10.3 [資料庫] 修正已存在的歷史通知中錯誤的 `action_url`（`UPDATE notifications SET data = JSON_SET(...)`）
- [x] 10.4 [後端] 建立 `failed_jobs` 資料表（`php artisan queue:failed-table && php artisan migrate`），修正 queue job 失敗時無法寫入錯誤記錄的問題
- [x] 10.5 [前端] `notificationAxios.js`：依 `window.location.pathname` 動態選擇 token（`/coach` 開頭優先 `coach_token`，其餘優先 `token`），修正雙 token 環境下通知 API 用錯帳號的問題
- [x] 10.6 [前端] `NotificationDrawer.vue`：`clickItem()` 改用 `new URL(action_url).pathname` 提取路徑，取代原本 `replace(window.location.origin, '')` 的不穩定做法
