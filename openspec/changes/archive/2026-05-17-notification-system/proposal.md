## Why

預約確認、取消、評價等關鍵事件目前完全沒有通知機制，使用者只能主動回頁面查看，造成重要訊息遺漏。實作通知系統可閉合「事件發生→使用者知情」這段空白，提升平台使用黏著度。

## What Changes

- 新增**站內通知（In-App Notification）**：所有角色（Member / Provider / Admin）可在導覽列看到未讀數量，點開通知中心查看全部通知
- 新增**Email 通知**：重要事件以信件寄送，使用 Laravel Queued Mailable + Markdown 模板
- 新增**通知觸發點**整合至現有業務邏輯（預約、評價、教練審核）：
  - 預約建立 → 通知 Provider
  - 預約確認/拒絕 → 通知 Member
  - 預約取消（任一方） → 通知對方
  - 預約完成 → 通知 Member（可評價）
  - Member 送出評價 → 通知 Provider
  - Admin 審核/拒絕教練申請 → 通知 Provider

## Capabilities

### New Capabilities

- `notification-core`: 通知資料模型、API（取得列表、標記已讀、刪除）、Vue 站內通知元件（Bell Icon + 通知中心 Drawer）
- `notification-email`: Laravel Mail 設定、Markdown 模板、Queue 投遞機制
- `notification-triggers`: 在 BookingService / ReviewService / Admin 審核流程中插入通知觸發邏輯

### Modified Capabilities

- `booking-lifecycle`: 預約七狀態機各轉換點需加上通知觸發
- `review-lifecycle`: 評價建立後需觸發 Provider 通知

## Impact

- **新增資料表**：`notifications`（Laravel 內建 `database` notification channel schema）
- **新增 API**：`GET /api/notifications`、`PATCH /api/notifications/{id}/read`、`PATCH /api/notifications/read-all`、`DELETE /api/notifications/{id}`
- **後端依賴**：Laravel Notification + Queue（database driver，可升級為 Redis）、Laravel Mail（SMTP/Mailpit 本地測試）
- **前端依賴**：Pinia store for notifications、Polling 或 SSE 取得即時未讀數
- **影響範圍**：BookingService、ReviewService、Admin 教練審核 controller
