## Context

平台目前已有完整的預約七狀態機與評價系統，但所有狀態轉換都是「靜默」執行，使用者只能回到頁面主動查看。本設計在不引入複雜即時通訊基礎設施的前提下，以 Laravel 內建 Notification + 前端 Polling 實作通知系統。

## Goals / Non-Goals

**Goals:**
- 站內通知（In-App）：Bell Icon 未讀計數 + 通知中心 Drawer，覆蓋 Member / Provider 兩個角色
- Email 通知：以 Laravel Queue + Mailable 非同步寄出，本地用 Mailpit 測試
- 觸發整合：BookingService、ReviewService、Admin 審核流程各轉換點
- 標記已讀（單一 / 全部）、刪除通知

**Non-Goals:**
- WebSocket / Push Notification（瀏覽器推播）
- SMS 通知
- 通知偏好設定（使用者選擇開關）
- Admin 角色通知（本次範圍僅 Member + Provider）

## Decisions

### 1. 使用 Laravel 內建 Notification 系統

**選擇**：`Notifiable` trait + 各 Notification class 各自控制 `via()`

**理由**：
- `database` channel 自動建立 `notifications` 資料表，schema 標準化
- `mail` channel 直接整合 Mailable + Queue
- **每個 class 獨立控制 `via()`**，避免「評價通知意外寄出 Email」等誤觸；新增類型只需新增一個 class

**各 Notification class 的 `via()` 設定**：

| Class | `via()` | 說明 |
|-------|---------|------|
| `BookingCreatedNotification` | `['database', 'mail']` | 有新預約，Provider 需即時知道 |
| `BookingConfirmedNotification` | `['database', 'mail']` | 確認是 Member 最期待的通知 |
| `BookingRejectedNotification` | `['database', 'mail']` | 需要 Email 確保 Member 收到 |
| `BookingCancelledNotification` | `['database', 'mail']` | 取消對雙方均重要 |
| `BookingCompletedNotification` | `['database', 'mail']` | Email CTA 引導評價 |
| `ReviewReceivedNotification` | `['database']` | 告知性通知，不值得寄 Email 打擾 |

**放棄的方案**：所有 class 共用一個 `via()` 設定 — 會導致評價通知也寄 Email，過度打擾 Provider。

---

### 2. 前端即時性：Polling（非 WebSocket）

**選擇**：前端登入後 Polling `GET /api/notifications/unread-count`，搭配 Page Visibility API 節省請求

**理由**：
- 平台目前流量低，WebSocket 基礎設施（Pusher / Laravel Echo Server）成本不對等
- SSE 需要長連線，Docker 環境 Nginx timeout 需另外調整
- 30 秒延遲對「預約確認」類通知可接受

**降頻邏輯（細化）**：

```
登入後 → 立即執行第一次 fetch（不等待 30s）
有未讀（count > 0） → interval = 30s
無未讀（count = 0） → interval = 60s
頁面隱藏（visibilitychange = hidden） → 暫停 interval
頁面重新顯示（visibilitychange = visible） → 立即 fetch 一次，然後重啟 interval
登出 → clearInterval + removeEventListener
```

**實作方式**：`startPolling()` 建立 `setInterval`，每次 fetch 後比較新舊 count：若 count 從 > 0 變為 0（或反之），`clearInterval` 並以新 interval 重啟。Page Visibility 由 `document.addEventListener('visibilitychange', handler)` 控制。

**升級路徑**：未來可替換為 Laravel Reverb（官方 WebSocket server），前端改用 Echo，store 的 `unreadCount`/`notifications` state 介面不變。

---

### 3. Queue Driver：database（現有 MySQL）

**選擇**：`QUEUE_CONNECTION=database`，使用現有 MySQL

**理由**：
- 專案已有 MySQL，不需額外部署 Redis
- Email 通知量少（非高頻），database queue 足夠
- 啟動命令加入 `php artisan queue:work --daemon` 或在 Docker CMD 中加入

**升級路徑**：`QUEUE_CONNECTION=redis`，只需改 .env，不動業務邏輯。

---

### 4. 通知類型設計（data JSON 欄位統一格式）

每個 Notification class 的 `toArray()` 回傳統一結構：
```json
{
  "type": "booking_confirmed",
  "title": "預約已確認",
  "body": "你的《自由潛水入門》課程預約已由教練確認",
  "action_url": "http://localhost:5173/my-bookings",
  "related_id": 123,
  "related_type": "booking"
}
```

**action_url 格式決定（修正）**：`action_url` 儲存完整 URL（含 `FRONTEND_URL` prefix），前端以 `new URL(action_url).pathname` 提取路徑再傳入 `router.push()`。**不含個別 booking ID**，原因：前端路由只有 `/my-bookings`（列表），無 `/my-bookings/:id` 詳情頁，帶 ID 會導致 404。

前端根據 `type` 決定 icon 顏色與動作連結。

---

### 5. 通知觸發架構：直接插入現有 Controller（不建立 Service 層）

**現況確認**：專案**無 BookingService / ReviewService**。業務邏輯分散於：
- `MemberBookingController`（建立預約、Member 取消）
- `ProviderBookingController`（確認、拒絕、Provider 取消、手動完成）
- `CompleteFinishedBookings` Command（排程自動完成）
- `ReviewController::store()`（評價建立）

**選擇**：直接在上述 Controller / Command 的對應方法中，於主業務 DB 操作後插入 `$user->notify(...)`，以 try/catch 包裹。

**理由**：
- 本次任務不需要 Service 抽象，建立 Service 只是為了通知而引入不必要的重構
- Inline notify 可讀性佳，出問題容易定位到發送點
- Observer 或 Event/Listener 會讓觸發點不直觀（多一層間接）

**DivingOffer `provider()` 關聯需新增**：

`DivingOffer` 有 `provider_id` FK 但**無 Eloquent 關聯方法**。實作前需在 `DivingOffer` model 補上：
```php
public function provider(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(User::class, 'provider_id');
}
```

之後 ReviewController 及各 BookingController 統一使用 `$offer->provider`（而非 `$offer->user`）。

**ReviewController 取得 Provider 的正確方式**：
```php
// ReviewController::store() 中
$offer = DivingOffer::with('provider')->findOrFail($offerId);
$provider = $offer->provider;
try {
    $provider->notify(new ReviewReceivedNotification($review));
} catch (\Throwable $e) {
    \Log::error('ReviewNotification failed: ' . $e->getMessage());
}
```

**BookingCancelledNotification 依 `$cancelledBy` 區分文案**：

| `$cancelledBy` | 通知對象 | title | body |
|----------------|---------|-------|------|
| `'member'` | Provider | 學員取消了預約 | 學員已取消《課程名稱》的預約（時段：日期）|
| `'provider'` | Member | 教練取消了你的預約 | 教練已取消你的《課程名稱》預約（時段：日期），如有疑問請聯繫教練 |

```php
// 使用範例
// MemberBookingController::cancel() 中
$provider = $booking->schedule->divingOffer->provider;
try {
    $provider->notify(new BookingCancelledNotification($booking, cancelledBy: 'member'));
} catch (\Throwable $e) { \Log::error(...); }

// ProviderBookingController::cancel() 中
$member = $booking->member;
try {
    $member->notify(new BookingCancelledNotification($booking, cancelledBy: 'provider'));
} catch (\Throwable $e) { \Log::error(...); }
```

---

### 6. Email 模板：Laravel Markdown Mailable

使用 `php artisan make:mail` + `markdown` 參數，產生 `resources/views/emails/notifications/` 下的 Blade 模板。本地使用 Mailpit（Docker service `mailpit`，port 1025/8025）攔截信件，不真實發送。

### 7-前置. Email action_url — FRONTEND_URL 設定

**現況**：`.env` 已有 `FRONTEND_URL=http://localhost:5173`，但 `config/app.php` **未註冊**此值，無法透過 `config()` 讀取。

**決定**：在 `config/app.php` 加入：
```php
'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
```

Notification class 中使用：
```php
'action_url' => config('app.frontend_url') . '/my-bookings/' . $this->booking->id,
```

`.env.example` 同步補上 `FRONTEND_URL=http://localhost:5173`。

**各場景 action_url 對應**：

| 通知 | action_url |
|------|-----------|
| BookingCreated（→ Provider） | `{FRONTEND_URL}/coach/bookings` |
| BookingConfirmed / Rejected / Cancelled / Completed（→ Member） | `{FRONTEND_URL}/my-bookings`（無 booking ID，前端路由無 `/my-bookings/:id`） |
| ReviewReceived（→ Provider） | `{FRONTEND_URL}/coach/reviews` |

### 7. API 路由完整定義

所有路由掛在 `auth:sanctum` middleware 下，Member token 與 Provider token 均適用（`Notifiable` 基於 `User` model，兩者共用同一張 `notifications` 資料表）。

| Method | Path | Controller@method | 說明 |
|--------|------|-------------------|------|
| `GET` | `/api/notifications` | `NotificationController@index` | 列表（分頁 20，DESC），含 `unread_count` |
| `GET` | `/api/notifications/unread-count` | `NotificationController@unreadCount` | Polling 專用，回傳 `{ count }` |
| `PATCH` | `/api/notifications/{id}/read` | `NotificationController@markRead` | 單一標記已讀 |
| `PATCH` | `/api/notifications/read-all` | `NotificationController@markAllRead` | 全部標記已讀 |
| `DELETE` | `/api/notifications/{id}` | `NotificationController@destroy` | 刪除單筆 |

**路由順序注意**：`/read-all` 必須定義在 `/{id}/read` **之前**，避免 Laravel 把 `read-all` 當成 `{id}` 綁定。

### 8. 觸發場景完整列表

| # | 事件 | 觸發位置 | 通知對象 | Channels |
|---|------|---------|---------|---------|
| 1 | 預約建立（`pending`） | `BookingService::create()` | Provider | DB + Mail |
| 2 | 預約確認（`confirmed`） | `BookingService::confirm()` | Member | DB + Mail |
| 3 | 預約拒絕（`rejected`） | `BookingService::reject()` | Member | DB + Mail |
| 4 | 預約取消（`member_cancelled`） | `BookingService::cancelByMember()` | Provider | DB + Mail |
| 5 | 預約取消（`provider_cancelled`） | `BookingService::cancelByProvider()` | Member | DB + Mail |
| 6 | 預約完成（`completed`） | `BookingService::complete()` | Member | DB + Mail |
| 7 | 收到評價 | `ReviewService::create()` | Provider | DB only |

> 場景共 7 個（含取消分 Member/Provider 兩方），對應 6 個 Notification class（`BookingCancelledNotification` 透過 `$cancelledBy` 參數區分文案）。

## Risks / Trade-offs

| 風險 | 緩解策略 |
|------|----------|
| `CompleteFinishedBookings` N+1 查詢 | 現行用 bulk `->update()` 無法逐筆 notify，**需改為 `->with(['member', 'schedule.divingOffer.provider'])->get()` + loop**；notify 仍在 loop 內，但 eager load 確保無 N+1 |
| Polling 造成 API 請求量上升 | 只在使用者登入且頁面 visible 時輪詢；未讀數 0 時降頻至 60s |
| Queue Worker 未啟動導致 Email 卡住 | Docker Compose 加入 `queue-worker` service，supervisor 管理 |
| `notifications` 資料表無限增長 | 建議每月清理 90 天前已讀通知（`php artisan notifications:prune`，Laravel 內建） |
| Email 寄信失敗無重試上限 | Queue job 設定 `$tries = 3`，失敗寫入 `failed_jobs` |

## Migration Plan

1. 執行 `php artisan notifications:table` + `php artisan queue:table` → migrate
2. 建立 Notification classes（6 種觸發場景）
3. 整合 BookingService / ReviewService / Admin controller
4. 建立 NotificationController + API routes
5. Docker Compose 加入 queue-worker service
6. 前端：Notification Pinia store → Bell Icon 元件 → Drawer 元件 → 整合至兩個 NavBar

### 9. 前端 Store 初始化時序

**問題**：Vue Router 的 `beforeEach` guard 在 `App.vue` 的 `onMounted` 之前執行。原本設計把三個 auth store 的 `init()`（讀 localStorage → 設定 `token.value`）放在 `onMounted`，導致 guard 跑時 `isLoggedIn` 永遠是 false，所有 protected route 均被踢回 login。

**決定**：在 `main.js` 中，`app.use(pinia)` 安裝後、`app.use(router)` 安裝前，同步呼叫三個 store 的 `init()`：

```js
app.use(pinia)
useAuthStore().init()
useCoachAuthStore().init()
useAdminAuthStore().init()
app.use(router)
app.mount('#app')
```

**影響**：`App.vue` 不再需要 `onMounted`，三個 auth store import 從 `App.vue` 移至 `main.js`。

---

### 10. 通知 API Token 選擇邏輯

**問題**：Member 與 Coach 使用同一個 `notificationAxios` 實例，interceptor 原本固定以 `token || coach_token` 順序取用。若瀏覽器同時持有兩種 token（測試情境），永遠使用 member token，導致 coach 通知 API 回傳 member 的空資料。

**決定**：依當前頁面路徑動態選 token：

```js
const isCoachPage = window.location.pathname.startsWith('/coach')
const token = isCoachPage
  ? (localStorage.getItem('coach_token') || localStorage.getItem('token'))
  : (localStorage.getItem('token') || localStorage.getItem('coach_token'))
```

**理由**：路徑是判斷「使用者當前身份上下文」最直接的信號，無需引入 Pinia store 至 axios interceptor（避免循環依賴）。

---

## Open Questions

> 所有問題已關閉，實作可直接開始。

| 問題 | 決定 |
|------|------|
| Mailpit 是否已加入 Docker Compose？ | **否，需在 task 1.6 補上**。`docker-compose.yml` 新增 `mailpit` service（`axllent/mailpit`），`.env` 設定 `MAIL_HOST=mailpit MAIL_PORT=1025`。 |
| Admin 角色通知未來是否需要？ | **本次排除**。Admin 主要操作在後台（有即時 UI feedback），不在此 change 範圍，未來若需要另開 change。 |
| 通知是否需要「點擊後自動標記已讀」行為？ | **是**。點擊 Drawer 中任一通知項目時，前端呼叫 `PATCH /api/notifications/{id}/read`，然後才執行 `router.push(action_url)`（不需等待 API response，Optimistic update）。 |
