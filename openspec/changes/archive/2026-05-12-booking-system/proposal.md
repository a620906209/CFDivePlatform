## Why

CFDivePlatform 目前只有課程瀏覽，Member 無法預約課程，Provider 無法管理開課時段，平台缺少核心商業閉環。預約系統是金流整合與平台商業化的前置條件，必須優先實作。

## What Changes

- 新增 `course_schedules` 資料表：Provider 建立開課時段（日期、時間、人數上限）
- 新增 `bookings` 資料表：記錄 Member 預約紀錄，含價格快照
- 新增 Member API：查詢可用時段、送出預約、取消預約
- 新增 Provider API：管理開課時段 CRUD、接受/拒絕/取消預約
- 新增 Laravel Scheduler：pending 超 48 小時自動 expired；課程日期過後自動 completed
- 新增前端頁面：Member 課程詳情頁加入時段選擇與預約流程；Provider Dashboard 加入時段管理與預約管理

## Capabilities

### New Capabilities

- `course-scheduling`：Provider 建立與管理開課時段，含日期、時間、人數上限、狀態（open/full/cancelled）
- `booking-lifecycle`：Member 送出預約、取消預約；Provider 確認/拒絕/取消預約；系統自動過期與完成；七狀態狀態機（pending / confirmed / completed / rejected / expired / member_cancelled / provider_cancelled）

### Modified Capabilities

（無既有 spec 受影響）

## Impact

**後端**
- 新增 Migration：`course_schedules`、`bookings`
- 新增 Model：`CourseSchedule`、`Booking`
- 新增 Controller：`ScheduleController`（Provider）、`BookingController`（Member/Provider）
- 新增 Laravel Scheduler：`ExpirePendingBookings`、`CompleteFinishedBookings`
- 更新 `api.php`：新增 `/member/bookings`、`/member/schedules`、`/provider/schedules`、`/provider/bookings` 路由群組

**前端**
- 更新 `CourseDetail.vue`（或新建）：加入時段列表與預約按鈕
- 新增 `src/pages/member/MyBookings.vue`：我的預約列表
- 新增 Coach Dashboard 子頁面：`ScheduleManager.vue`、`BookingManager.vue`
- 新增 `src/api/bookingApi.js`：封裝預約相關 API 呼叫

**資料庫**
- 兩張新資料表，無現有資料表結構變更
- `diving_offers.price` 作為預約時的價格快照來源
