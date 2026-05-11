## 1. 資料庫層

- [x] 1.1 [後端] 建立 Migration `create_course_schedules_table`：欄位含 `diving_offer_id`、`provider_id`、`scheduled_date`、`start_time`、`max_participants`、`current_participants`、`status` string（非 enum）；加索引 `(diving_offer_id, status, scheduled_date)` 與 `(provider_id)`
- [x] 1.2 [後端] 建立 Migration `create_bookings_table`：欄位含 `schedule_id`、`member_id`、`participants`、`total_price`、`status` string（非 enum）、`notes`；加索引 `(member_id, status)`、`(schedule_id, status)`、`(status, created_at)`
- [x] 1.3 [後端] 執行 Migration，確認 DB schema 與索引正確（`SHOW INDEX FROM course_schedules`）

## 2. Enum 與 Model 層

- [x] 2.1 [後端] 建立 `app/Enums/BookingStatus.php`：PHP BackedEnum，七個 case（pending / confirmed / completed / rejected / expired / member_cancelled / provider_cancelled）
- [x] 2.2 [後端] 建立 `app/Enums/ScheduleStatus.php`：PHP BackedEnum，三個 case（open / full / cancelled）
- [x] 2.3 [後端] 建立 `app/Models/CourseSchedule.php`：fillable、`casts = ['status' => ScheduleStatus::class]`、關聯（belongsTo DivingOffer / belongsTo User as provider、hasMany Booking）
- [x] 2.4 [後端] 建立 `app/Models/Booking.php`：fillable、`casts = ['status' => BookingStatus::class]`、`VALID_TRANSITIONS` 常數（pending→{confirmed,rejected,expired,member_cancelled}；confirmed→{completed,member_cancelled,provider_cancelled}；其餘終態→[]）、`canTransitionTo()` 驗證方法、關聯（belongsTo CourseSchedule / belongsTo User as member）
- [x] 2.5 [後端] 在 `DivingOffer` Model 新增 `hasMany CourseSchedule` 關聯

## 3. Provider 時段管理 API

- [x] 3.1 [後端] 建立 `app/Http/Controllers/API/ScheduleController.php`：`index`、`store`（含所有權驗證、日期驗證）、`update`、`destroy`（DB transaction：時段 → cancelled + 批次 cascade pending/confirmed Booking → provider_cancelled）
- [x] 3.2 [後端] 在 `routes/api.php` 新增 `/provider/schedules` 路由群組（CRUD）
- [x] 3.3 [後端] 在 `routes/api.php` 新增公開路由 `GET /diving-offers/{id}/schedules`；Controller 查詢條件：`status = 'open' AND scheduled_date >= CURDATE()` ORDER BY `scheduled_date ASC, start_time ASC`；回傳每筆含 `remaining_spots = max_participants - current_participants`

## 4. Provider 預約管理 API

- [x] 4.1 [後端] 建立 `app/Http/Controllers/API/ProviderBookingController.php`：
  - `confirm`（階段 B 佔位）：DB transaction + lockForUpdate 取得 schedule → 重新計算剩餘名額（`max - current_participants`）→ 不足則 422 → 更新 Booking status=confirmed + `increment('current_participants')` → 若達滿則 schedule status=full
  - `reject`：Booking status → rejected，不動 current_participants（pending 本來就未佔位）
  - `cancel`：Booking status → provider_cancelled + `decrement('current_participants')` + 若原為 full 則 schedule status 改回 open（僅 confirmed 才需釋放）
  - `index`：列出自己課程的預約
- [x] 4.2 [後端] 在 `routes/api.php` 新增 `/provider/bookings` 路由群組（index / confirm / reject / cancel）

## 5. Member 預約 API

- [x] 5.1 [後端] 建立 `app/Http/Controllers/API/MemberBookingController.php`：
  - `store`（階段 A，pending 不佔位）：Layer 1 快速名額檢查（`max - current_participants`，422 early return）→ DB transaction 內：lockForUpdate 取得 schedule + Layer 2 名額再次驗證 + 重複預約檢查（同一 member_id + schedule_id 是否已有 pending/confirmed）→ 建立 Booking + 價格快照；**不 increment `current_participants`**
  - `destroy`：24h 截止驗證（Carbon datetime 比較）→ 合法則改 member_cancelled；**僅 confirmed 狀態需 decrement `current_participants` + 若原為 full 改回 open**；pending 取消不動人數
  - `index`、`show`：一般查詢
- [x] 5.2 [後端] 在 `routes/api.php` 新增 `/member/bookings` 路由群組（CRUD）

## 6. Scheduler 自動任務

- [x] 6.1 [後端] 建立 `app/Console/Commands/ExpirePendingBookings.php`：查詢 `status=pending` 且 `created_at < now()-48h`（利用索引 `status, created_at`），批次更新為 `expired`；執行結尾 `Log::info("ExpirePendingBookings: {$count} expired")`
- [x] 6.2 [後端] 建立 `app/Console/Commands/CompleteFinishedBookings.php`：查詢 `status=confirmed` 且 join schedule 的 `scheduled_date < today()`，批次更新為 `completed`；執行結尾 `Log::info("CompleteFinishedBookings: {$count} completed")`
- [x] 6.3 [後端] 在 `routes/console.php` 註冊：`ExpirePendingBookings` 每小時（`->hourly()`）、`CompleteFinishedBookings` 每日凌晨（`->dailyAt('00:05')`）
- [x] 6.4 [後端] 在 Docker `cfdive-app` 容器的 Dockerfile / entrypoint 加入 cron job：`* * * * * php /var/www/html/artisan schedule:run >> /dev/null 2>&1`；確認 cron daemon 已啟動
- [x] 6.5 [後端] 手動驗證兩支 Command 可獨立執行：`php artisan app:expire-pending-bookings`、`php artisan app:complete-finished-bookings` 不報錯，且 `storage/logs/laravel.log` 有對應紀錄

## 7. 前端 API 封裝

- [x] 7.1 [前端] 建立 `frontend/src/api/scheduleApi.js`：封裝 `getSchedulesByOffer(offerId)`
- [x] 7.2 [前端] 建立 `frontend/src/api/bookingApi.js`（member）：`getMyBookings()`、`getBooking(id)`、`createBooking(payload)`、`cancelBooking(id)`
- [x] 7.3 [前端] 建立 `frontend/src/api/coachBookingApi.js`（provider）：`getProviderBookings()`、`confirmBooking(id)`、`rejectBooking(id)`、`cancelBooking(id)`
- [x] 7.4 [前端] 建立 `frontend/src/api/coachScheduleApi.js`：`getSchedules()`、`createSchedule(payload)`、`updateSchedule(id, payload)`、`deleteSchedule(id)`

## 8. Member 前端頁面

- [x] 8.1 [前端] 更新課程詳情頁（`frontend/src/views/CourseDetailView.vue`）：新增「可用時段」區塊，顯示日期、時間、剩餘名額，含「立即預約」按鈕（呼叫 createBooking）
- [x] 8.2 [前端] 新增 `frontend/src/views/MyBookingsView.vue`：列出 Member 所有預約，顯示狀態 Badge（七種狀態對應不同顏色），含取消按鈕（pending/confirmed）
- [x] 8.3 [前端] 在 Member Navbar 加入「我的預約」連結，路由 `/my-bookings`
- [x] 8.4 [前端] 在 `frontend/src/router/index.js` 新增 `/my-bookings` 路由（requiresAuth）

## 9. Coach 前端頁面

- [x] 9.1 [前端] 新增 `frontend/src/views/coach/ScheduleManagerView.vue`：時段列表（含狀態、剩餘名額）、建立時段表單（日期選擇、時間、人數上限）、取消時段按鈕
- [x] 9.2 [前端] 新增 `frontend/src/views/coach/BookingManagerView.vue`：預約列表（依課程分組或全部列出）、顯示 Member 姓名、人數、金額、狀態、確認/拒絕/取消按鈕
- [x] 9.3 [前端] 在 Coach Navbar（`CoachNavBar.vue`）加入「時段管理」與「預約管理」連結
- [x] 9.4 [前端] 在 `frontend/src/router/index.js` 新增 `/coach/schedules`、`/coach/bookings` 路由（requiresCoach）

## 10. 整合驗證

- [x] 10.1 [整合測試] 完整流程測試：Provider 建立時段 → Member 預約 → Provider 確認 → 驗證人數扣減
- [x] 10.2 [整合測試] 超賣防護測試：最後一個名額同時送出兩筆預約，驗證只有一筆成功（Layer 2 lockForUpdate 生效）
- [x] 10.3 [整合測試] 取消流程測試：①Member 取消 confirmed 預約 → current_participants 減少、schedule 若原為 full 改回 open；②Member 取消 pending 預約 → current_participants **不變**（pending 本來就未佔位）
- [x] 10.4 [整合測試] Scheduler 測試：手動執行 `php artisan app:expire-pending-bookings`、`php artisan app:complete-finished-bookings`，驗證狀態正確更新
- [x] 10.5 [整合測試] Cascade 測試：Provider 取消時段後驗證 pending/confirmed Booking 全部變 provider_cancelled；completed/rejected Booking 狀態不變
- [x] 10.6 [整合測試] 取消截止測試：建立一筆課程開始前 12h 的 confirmed 預約，Member 嘗試取消應回傳 422；課程前 36h 的預約應可取消
- [x] 10.7 [整合測試] Participants 雙層驗證測試：超過剩餘名額的預約在 Layer 1 被攔截，回傳 422 且不進入 DB transaction
- [x] 10.8 [整合測試] 重複預約防護測試：Member 對同一時段送出第二筆 pending 預約應回傳 422；第一筆取消後再送出第三筆應成功
- [x] 10.9 [整合測試] 公開 API 過濾測試：`GET /api/diving-offers/{id}/schedules` 不回傳 status=full、cancelled 及過去日期時段；回傳時段含正確的 remaining_spots
