## Context

CFDivePlatform 是 Laravel 11 + Vue 3 的潛水課程媒合平台。目前 Member 只能瀏覽課程，無法完成預約。需要在現有 Sanctum 認證體系上新增預約流程，並引入 Laravel Scheduler 處理自動狀態轉換。

現有關鍵資料模型：`users`（三角色）、`diving_offers`（含 `price`、`provider_id`）。本次不修改任何現有資料表。

## Goals / Non-Goals

**Goals:**
- Provider 可建立/管理開課時段（`course_schedules`）
- Member 可對時段送出預約（`bookings`）
- 七狀態狀態機，含自動過期（48h）與自動完成（課程後）
- 前後端完整串接

**Non-Goals:**
- 金流整合（payment 欄位預留但不串接）
- 推播通知（Email/SMS）
- 管理員預約管理介面（Admin Panel 待後續）
- 退款流程（取消後僅改狀態，不觸發退款）

## Decisions

### 決策一：狀態機實作方式 — PHP BackedEnum + string 欄位

**選擇**：DB 欄位用 `string`（非 MySQL ENUM），應用層用 PHP `BackedEnum` 管理合法值。

```php
// app/Enums/BookingStatus.php
enum BookingStatus: string {
    case Pending           = 'pending';
    case Confirmed         = 'confirmed';
    case Completed         = 'completed';
    case Rejected          = 'rejected';
    case Expired           = 'expired';
    case MemberCancelled   = 'member_cancelled';
    case ProviderCancelled = 'provider_cancelled';
}

// app/Enums/ScheduleStatus.php
enum ScheduleStatus: string {
    case Open      = 'open';
    case Full      = 'full';
    case Cancelled = 'cancelled';
}
```

Migration 使用 `$table->string('status')->default('pending')`，Model 用 `$casts = ['status' => BookingStatus::class]`。

在 `Booking` Model 定義 `VALID_TRANSITIONS` 常數，transition 前統一驗證合法性：

```php
const VALID_TRANSITIONS = [
    'pending'            => ['confirmed', 'rejected', 'expired', 'member_cancelled'],
    'confirmed'          => ['completed', 'member_cancelled', 'provider_cancelled'],
    'completed'          => [],
    'rejected'           => [],
    'expired'            => [],
    'member_cancelled'   => [],
    'provider_cancelled' => [],
];
```

**理由**：MySQL ENUM 加欄位值需要 `ALTER TABLE`（鎖表），在大資料量下有停機風險。用 string 欄位，未來加狀態只需改 PHP Enum，零 Migration。PHP 8.1 BackedEnum 提供 IDE 自動補全與型別安全，兼顧可維護性。

**放棄**：DB ENUM → 維護成本高，每次加狀態都要 Migration；引入狀態機套件 → 過度設計，transition 數量不值得。

---

### 決策二：人數計數 — DB 欄位 + 悲觀鎖

**選擇**：`course_schedules.current_participants` 實體欄位，更新時用 `lockForUpdate()`。

```
DB::transaction(function () use ($booking, $schedule) {
    $schedule = CourseSchedule::lockForUpdate()->find($schedule->id);
    // 驗證剩餘名額...
    $schedule->increment('current_participants', $booking->participants);
});
```

**理由**：比每次 COUNT(bookings) 查詢效率高；悲觀鎖防止超賣 race condition。

**放棄**：樂觀鎖（version column）→ 需要 retry 邏輯，複雜度不值得。

---

### 決策三：Scheduler 頻率

| Job | 頻率 | 原因 |
|-----|------|------|
| `ExpirePendingBookings` | 每小時 | 過期精確度到小時即可 |
| `CompleteFinishedBookings` | 每日凌晨 | 課程完成以「日」為單位 |

---

### 決策四：價格快照

**選擇**：建立 Booking 時將 `diving_offer.price × participants` 存入 `bookings.total_price`。

**理由**：Provider 日後調整課程價格不應影響已建立的預約；金流整合時直接使用此欄位。

---

### 決策五：取消時段的 Cascade 實作

**選擇**：在 `ScheduleController::destroy()` 內用單一 DB transaction 同時更新時段與相關 Booking。

```
DB::transaction(function () use ($schedule) {
    $schedule->update(['status' => ScheduleStatus::Cancelled]);
    $schedule->bookings()
        ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
        ->update(['status' => BookingStatus::ProviderCancelled]);
});
```

**理由**：Booking cascade 必須與時段取消原子性完成，避免時段已取消但 Booking 仍掛 `confirmed` 的髒狀態。批次 `update` 不逐筆觸發 Model Event，效率優先（MVP 規模不需要逐筆通知）。

**放棄**：逐筆呼叫 `$booking->transitionTo(ProviderCancelled)` → 在大量預約時效率差，且 Model Event 觸發通知屬於未來功能。

---

### 決策六：Member 取消截止時間

**選擇**：課程開始前 24 小時為截止點，計算方式為 `$schedule->scheduled_date + $schedule->start_time`（Carbon datetime）。

```php
$courseStart = Carbon::parse($schedule->scheduled_date . ' ' . $schedule->start_time);
if (now()->diffInHours($courseStart, false) < 24) {
    return response()->json(['status' => false, 'message' => '距課程開始不足 24 小時，無法取消'], 422);
}
```

**理由**：潛水課程有實際的人力與設備準備成本，24h 截止是業界常見標準。`pending` 狀態同樣受此限制，避免 Member 在課程即將開始時仍送出再取消的操作。

**放棄**：只限制 `confirmed` 取消、`pending` 不限 → 業務上應一致處理，24h 截止對兩種狀態同樣適用。

---

### 決策七：Participants 驗證時機

**選擇**：分兩個階段各做一次名額驗證。

**階段 A — 建立 pending 時（早期拒絕，`current_participants` = 已確認人數）**
```
Layer 1 (Controller，進 transaction 前):
    $remaining = $schedule->max_participants - $schedule->current_participants;
    if ($participants > $remaining) return 422;  // 連確認的名額都滿了，早期拒絕

Layer 2 (transaction 內，lockForUpdate 後):
    $schedule = CourseSchedule::lockForUpdate()->find($id);
    $remaining = $schedule->max_participants - $schedule->current_participants;
    if ($participants > $remaining) throw new InsufficientSlotsException();
    // 通過後只建立 Booking，不 increment（pending 不佔位）
```

**階段 B — Provider 確認時（真正佔位，lockForUpdate + increment）**
```
DB::transaction(function () use ($booking) {
    $schedule = CourseSchedule::lockForUpdate()->find($booking->schedule_id);
    $remaining = $schedule->max_participants - $schedule->current_participants;
    if ($booking->participants > $remaining) throw new InsufficientSlotsException();
    $booking->update(['status' => 'confirmed']);
    $schedule->increment('current_participants', $booking->participants);
    $schedule->refresh();
    if ($schedule->current_participants >= $schedule->max_participants) {
        $schedule->update(['status' => 'full']);
    }
});
```

**理由**：`current_participants` 只計算 confirmed 人數。pending 是「申請」不是「保留」，Provider 確認時才真正佔位。階段 A 的早期拒絕防止在所有 confirmed 額度滿後仍接受新 pending；階段 B 的 lockForUpdate 是真正防超賣機制。

---

### 決策八：重複預約防護 — 應用層 transaction 內檢查

**選擇**：不使用 DB UNIQUE constraint，改在建立 Booking 的 DB transaction 內執行重複性檢查。

```php
DB::transaction(function () use ($memberId, $scheduleId, ...) {
    // 在 lockForUpdate 取得 schedule 的同一 transaction 內檢查
    $duplicate = Booking::where('member_id', $memberId)
        ->where('schedule_id', $scheduleId)
        ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
        ->exists();
    if ($duplicate) {
        throw new DuplicateBookingException();
    }
    // ... 建立 Booking
});
```

**理由**：DB UNIQUE(member_id, schedule_id) 會阻止 Member 在取消後重新預約同一時段（如原 pending 取消後想改約），業務上不合理。應用層只檢查活躍狀態（pending/confirmed），允許對同一時段在取消後再次預約。將檢查放在 transaction 內確保與建立操作原子性，避免 TOCTOU race condition。

**放棄**：DB UNIQUE constraint → 語意過強，阻擋合法的取消後重訂；Controller 層（transaction 外）檢查 → 有 TOCTOU 風險。

---

### 決策九：公開時段 API 的明確過濾條件

**選擇**：`GET /api/diving-offers/{id}/schedules` 回傳條件為：
```sql
WHERE diving_offer_id = :id
  AND status = 'open'
  AND scheduled_date >= CURDATE()
ORDER BY scheduled_date ASC, start_time ASC
```

`full` 和 `cancelled` 時段不回傳；過去日期時段不回傳。

**理由**：只回傳 `open` 確保 Member 看到的全是可預約的時段，前端不需要再做客戶端過濾。`full` 不顯示（MVP 不做候補名單功能），`scheduled_date < today` 的時段對 Member 無意義。

**放棄**：回傳 `open` + `full`（前端再過濾）→ 增加前端複雜度，且 full 時段對無候補功能的 MVP 無用。

---

### 決策十：Coach / Provider 命名慣例

**現況**：codebase 存在兩套命名：前端用 `coach`，後端 API 和 DB 用 `provider`。這是前期開發的歷史遺留，本次不重構。

**決策**：
- **前端路由**：維持 `/coach/*`（已存在，不破壞現有 URL）
- **後端 API 路由**：統一用 `/provider/*`（與 DB role 欄位值一致）
- **DB `users.role` 欄位值**：`'provider'`（PHP 端 `isProvider()` 方法判斷）
- **本次新增程式碼**：後端一律用 `provider` 命名（Controller、Policy、Middleware）；前端新增頁面放在 `/coach/*` 路由下

新加入開發者應知道：前端 `/coach/*` = 後端 `/api/provider/*` = DB `role = 'provider'`，三者指同一群用戶。

---

### 決策十一：審計追蹤（已知限制）

**現況**：`bookings` 表只有 `created_at`/`updated_at`，無法從 DB 層直接查「何時確認」「何時取消」。

**MVP 決策**：接受此限制。`updated_at` 可作為最後一次狀態變更的時間戳，精確度足夠 MVP 使用。

**未來（金流整合前必須處理）**：加入 `booking_status_logs` 歷史表，記錄每次 status transition 的 `from_status`、`to_status`、`changed_by`（user_id）、`changed_at`。屆時新增一個 Migration 即可，不影響現有 `bookings` 結構。

---

### 決策十三：名額回收與時段狀態自動轉換

**`current_participants` 增減規則**（只計算 confirmed 人數）：

| 觸發動作 | current_participants | 說明 |
| ------- | ------------------- | ---- |
| pending → confirmed（Provider 確認） | +participants | 確認時才佔位 |
| confirmed → member_cancelled | -participants | Member 取消，釋放名額 |
| confirmed → provider_cancelled | -participants | Provider 取消時段或單筆取消，釋放名額 |
| confirmed → completed | 不變 | 課程已完成，名額已消耗 |
| pending → rejected | 不變 | pending 從未佔位，無需釋放 |
| pending → expired | 不變 | pending 從未佔位，無需釋放 |
| pending → member_cancelled | 不變 | pending 從未佔位，無需釋放 |

**`course_schedules.status` 自動轉換規則**（在 confirm/cancel 的同一 DB transaction 內執行）：

```php
// confirm 後 increment，檢查是否 full：
if ($schedule->current_participants >= $schedule->max_participants) {
    $schedule->update(['status' => ScheduleStatus::Full]);
}

// cancel 後 decrement，檢查是否回 open：
if ($schedule->current_participants < $schedule->max_participants
    && $schedule->status === ScheduleStatus::Full) {
    $schedule->update(['status' => ScheduleStatus::Open]);
}
```

`cancelled` 是終態，不受上述規則影響。

**理由**：與 `specs/course-scheduling/spec.md` 一致，`current_participants` 反映已確認（實際佔用）的人數。pending 是申請，不保留名額；Provider 確認時才真正佔位。

---

### 決策十四：`ExpirePendingBookings` 過期條件

**選擇**：過期條件為 `status = 'pending'` 且 `created_at <= now() - 48 hours`。批次 update 即可，無需碰 `current_participants`（pending 從未佔位）。

```php
$count = Booking::where('status', BookingStatus::Pending)
    ->where('created_at', '<=', now()->subHours(48))
    ->update(['status' => BookingStatus::Expired]);

Log::info("ExpirePendingBookings: {$count} expired");
```

**理由**：pending 確認時才佔位（決策十三），因此過期只需改狀態，`current_participants` 與時段 `status` 均不受影響。批次 `update` 效率優於逐筆 transaction。

---

### 決策十二：前端路由新增

```
Member 新路由：
  /courses/:id          → 課程詳情（新增時段選擇區塊）
  /my-bookings          → 我的預約列表

Coach 新路由（在現有 /coach/* 下）：
  /coach/schedules      → 時段管理
  /coach/bookings       → 預約管理
```

## 資料表設計

### course_schedules
```
id                   bigint PK
diving_offer_id      bigint FK → diving_offers.id
provider_id          bigint FK → users.id
scheduled_date       date NOT NULL
start_time           time NOT NULL
max_participants     int NOT NULL (≥1)
current_participants int DEFAULT 0
status               string DEFAULT 'open'   ← PHP ScheduleStatus BackedEnum
created_at           timestamp
updated_at           timestamp

索引：
  idx_offer_status_date  (diving_offer_id, status, scheduled_date)  ← 公開 API 查詢
  idx_provider_id        (provider_id)                               ← Provider 管理頁
```

### bookings
```
id          bigint PK
schedule_id bigint FK → course_schedules.id
member_id   bigint FK → users.id
participants int NOT NULL DEFAULT 1
total_price  int NOT NULL  (快照，單位：元)
status       string DEFAULT 'pending'   ← PHP BookingStatus BackedEnum
notes        text nullable
created_at   timestamp
updated_at   timestamp

索引：
  idx_member_status      (member_id, status)         ← Member 預約列表
  idx_schedule_status    (schedule_id, status)        ← 重複預約檢查、人數統計
  idx_status_created_at  (status, created_at)         ← ExpirePendingBookings Scheduler
  idx_status_sched       (status, schedule_id)        ← CompleteFinishedBookings Scheduler
```

## API 路由總覽

```
公開
  GET  /api/diving-offers/{id}/schedules   → 取得課程可用時段

Member (auth:sanctum)
  GET  /api/member/bookings                → 我的預約列表
  POST /api/member/bookings                → 建立預約
  GET  /api/member/bookings/{id}           → 預約詳情
  DELETE /api/member/bookings/{id}         → 取消預約

Provider (auth:sanctum)
  GET  /api/provider/schedules             → 我的時段列表
  POST /api/provider/schedules             → 建立時段
  PUT  /api/provider/schedules/{id}        → 更新時段
  DELETE /api/provider/schedules/{id}      → 取消時段
  GET  /api/provider/bookings              → 課程預約列表
  PUT  /api/provider/bookings/{id}/confirm → 確認預約
  PUT  /api/provider/bookings/{id}/reject  → 拒絕預約
  PUT  /api/provider/bookings/{id}/cancel  → 取消預約
```

## Risks / Trade-offs

- **Race condition on 最後一個名額** → 已用 `lockForUpdate()` 在 DB transaction 內處理

- **Scheduler 停擺（高風險）**
  Scheduler 若未啟動，`pending` 預約永遠不過期、`confirmed` 課程永遠不完成，資料持續累積髒狀態。
  Mitigation 三層：
  1. **日誌**：每次 Job 執行結尾記錄 `Log::info("ExpirePendingBookings: {$count} expired")`，可在 Laravel log 中查驗
  2. **可觀測性**：開發環境啟用 Laravel Telescope 監控 Schedule 執行；生產環境至少保留 `storage/logs/laravel.log` 並定期 rotate
  3. **手動補跑**：兩支 Artisan Command 須可獨立執行（`php artisan app:expire-pending-bookings`），維運人員可在 Scheduler 異常時手動補跑，不依賴 cron

- **取消後無退款** → 目前僅狀態標記，金流整合時需補充退款邏輯
- **`completed` 自動轉換** → 課程當天仍進行中的預約到凌晨才會轉 completed，業務上可接受
- **string status 未加 DB CHECK constraint** → 合法值由應用層 BackedEnum 控制，若繞過 ORM 直接寫 DB 可能插入非法值；可接受此 trade-off，未來有需要可加 DB constraint

## Closed Questions

- **Q1：`notes` 欄位是否強制填寫？** → **已決定：nullable**。Member 預約時不強制填寫，`notes` 保留為選填欄位供日後使用。
- **Q2：Provider 取消時段時，confirmed Booking 的通知方式？** → **已決定：只改狀態**。取消後 Booking 狀態變為 `provider_cancelled`，本次不觸發任何通知。Email/推播通知留給未來 Email 模組實作。
