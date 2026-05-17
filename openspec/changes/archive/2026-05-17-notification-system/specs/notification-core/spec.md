## ADDED Requirements

### Requirement: 通知資料模型

系統 SHALL 使用 Laravel 內建 `notifications` 資料表儲存站內通知，每筆通知包含：`id`（UUID）、`type`（Notification class 名稱）、`notifiable_type` / `notifiable_id`（多型關聯至 User）、`data`（JSON，含 type / title / body / action_url / related_id / related_type）、`read_at`（nullable）、`created_at` / `updated_at`。

#### Scenario: 通知建立

- **WHEN** 業務邏輯觸發 `$user->notify(new XxxNotification(...))`
- **THEN** `notifications` 資料表新增一筆記錄，`read_at` 為 null

---

### Requirement: 取得通知列表 API

`GET /api/notifications` SHALL 回傳當前登入使用者的通知列表（含已讀/未讀），分頁 20 筆，依 `created_at` DESC 排序。

Response data 格式：
```json
{
  "data": [
    {
      "id": "uuid",
      "type": "booking_confirmed",
      "title": "預約已確認",
      "body": "...",
      "action_url": "http://localhost:5173/my-bookings",
      "read_at": null,
      "created_at": "2026-05-17T10:00:00Z"
    }
  ],
  "unread_count": 3,
  "meta": { "current_page": 1, "last_page": 2 }
}
```

#### Scenario: 已登入使用者取得通知

- **WHEN** 已登入 Member 呼叫 `GET /api/notifications`
- **THEN** 回傳 `status: true`，`data` 陣列包含該使用者的通知，最新在前

#### Scenario: 未登入拒絕存取

- **WHEN** 未帶 Token 呼叫 `GET /api/notifications`
- **THEN** 回傳 HTTP 401

---

### Requirement: 取得未讀數量 API

`GET /api/notifications/unread-count` SHALL 回傳當前使用者未讀通知數量，用於 Polling。

Response：`{ "status": true, "data": { "count": 3 } }`

#### Scenario: 有未讀通知

- **WHEN** 使用者有 3 筆 `read_at = null` 的通知時呼叫
- **THEN** 回傳 `count: 3`

#### Scenario: 無未讀通知

- **WHEN** 所有通知 `read_at` 均不為 null
- **THEN** 回傳 `count: 0`

---

### Requirement: 標記單一通知為已讀

`PATCH /api/notifications/{id}/read` SHALL 將指定通知的 `read_at` 設為當前時間。

#### Scenario: 標記成功

- **WHEN** 已登入使用者對自己的通知呼叫此 API
- **THEN** 回傳 `status: true`，`read_at` 不再為 null

#### Scenario: 非本人通知拒絕

- **WHEN** 使用者嘗試標記他人通知
- **THEN** 回傳 HTTP 403

---

### Requirement: 標記全部通知為已讀

`PATCH /api/notifications/read-all` SHALL 將當前使用者所有未讀通知一次標記為已讀。

#### Scenario: 批次標記

- **WHEN** 使用者有 5 筆未讀，呼叫此 API
- **THEN** 所有 5 筆 `read_at` 更新，回傳 `status: true`

---

### Requirement: 刪除通知

`DELETE /api/notifications/{id}` SHALL 永久刪除指定通知。

#### Scenario: 刪除成功

- **WHEN** 已登入使用者刪除自己的通知
- **THEN** 該通知從資料庫移除，回傳 HTTP 204

#### Scenario: 非本人通知拒絕刪除

- **WHEN** 使用者嘗試刪除他人通知
- **THEN** 回傳 HTTP 403

---

### Requirement: 前端 Bell Icon 未讀計數

NavBar（MemberNavBar + CoachNavBar）SHALL 顯示通知鈴鐺圖示，未讀數量 > 0 時顯示紅色 Badge。

#### Scenario: 有未讀通知

- **WHEN** 使用者登入後 Pinia store polling 回傳 `count > 0`
- **THEN** Bell Icon 顯示紅色數字 Badge

#### Scenario: 無未讀通知

- **WHEN** `count === 0`
- **THEN** Badge 不顯示（隱藏，不佔位）

---

### Requirement: 前端通知中心 Drawer

點擊 Bell Icon SHALL 開啟側邊 Drawer，列出最新 20 筆通知，每筆顯示 title、body（截斷 80 字）、時間（相對時間）、已讀/未讀狀態。

#### Scenario: 點擊通知項目

- **WHEN** 使用者點擊通知項目
- **THEN** 通知標記為已讀（Optimistic update），並以 `new URL(action_url).pathname` 提取路徑後呼叫 `router.push()`，跳轉至對應頁面

#### Scenario: 點擊「全部標記已讀」

- **WHEN** 使用者點擊 Drawer 頂部「全部標為已讀」按鈕
- **THEN** 呼叫 `PATCH /api/notifications/read-all`，所有項目變為已讀樣式

---

### Requirement: Polling 機制

前端 Pinia `notificationStore` SHALL 在使用者登入後立即執行第一次 fetch，並依未讀數量動態調整輪詢間隔：未讀 > 0 → 30 秒；未讀 = 0 → 60 秒。間隔切換時 MUST `clearInterval` 後以新間隔重新建立。登出後清除計時器與 Page Visibility 監聽器。

#### Scenario: 登入後立即 fetch

- **WHEN** 使用者成功登入（Member 或 Coach）
- **THEN** `notificationStore.startPolling()` 立即呼叫一次 `fetchUnreadCount()`，不等待第一個 interval 到期

#### Scenario: 有未讀時使用 30 秒間隔

- **WHEN** `fetchUnreadCount()` 回傳 `count > 0`
- **THEN** interval 設為 30 秒（若目前為 60 秒則 clearInterval 重啟）

#### Scenario: 無未讀時降頻至 60 秒

- **WHEN** `fetchUnreadCount()` 回傳 `count === 0`
- **THEN** interval 設為 60 秒（若目前為 30 秒則 clearInterval 重啟）

#### Scenario: 頁面切換至背景時暫停

- **WHEN** `document.visibilityState === 'hidden'`（使用者切換 Tab 或最小化視窗）
- **THEN** clearInterval 暫停 polling，不發出 API 請求

#### Scenario: 頁面重新顯示時恢復

- **WHEN** `document.visibilityState === 'visible'`（使用者回到此 Tab）
- **THEN** 立即執行一次 `fetchUnreadCount()`，然後依最新 count 重啟 interval

#### Scenario: 登出後停止

- **WHEN** 使用者登出
- **THEN** `notificationStore.stopPolling()` 執行 `clearInterval` 並 `removeEventListener('visibilitychange', ...)`，不再發出任何請求
