---
name: hardener
description: 程式碼實作完成後、呼叫 strict-reviewer 之前，依本專案 CLAUDE.md 慣例為 Laravel 的 Controller／Service 方法補強防禦性檢查（參數驗證、資源存在性與擁有權、狀態機驗證、併發處理、例外邊界、日誌）。當使用者說「補強防禦」「hardener」「檢查防禦分支」，或新增、修改了會讀寫資料的端點與方法時使用。
keywords: [defensive-programming, validation, authorization, exception-handling, logging, concurrency, laravel]
category: code-review
author: RD-Hank
origin: 改寫自 ProjectC 的 hardener skill（原為 C#／.NET 版本），已替換為 Laravel 慣例
---

# Hardener Skill — 防禦性編程（Laravel 版，比照 CLAUDE.md）

## 目的

在實作完成後，為方法補上下列防禦，**只補缺漏，不重寫已正確的程式碼**：

- 參數驗證（在邊界完成，且失敗發生在任何寫入或鎖定之前）
- 資源存在性與擁有權檢查
- 狀態機驗證（走既有的 Enum 與 `canTransitionTo()`，不繞過）
- 併發處理（依實際需要序列化的資源設計，不套固定模板）
- 例外邊界（先分清是交易必要步驟還是 best-effort 副作用）
- 日誌（結構化、不含個資與機敏資訊、等級對應風險）

**核心原則（CLAUDE.md）**：
> 例外拋出時機僅在「無法在當層合理處理」時；可預期的業務失敗優先用明確的回傳值或明確的 HTTP 回應，不以例外控制流程。

## 先確認你在哪一層

這個專案目前沒有 `app/Services/`、`app/Http/Requests/`，Eloquent 直接在 Controller 使用。套用前先確認目標屬於哪一層，不同層適用的檢查不同：

| 層 | 可預期的失敗（驗證不過、查無資源、無權限、狀態衝突） | 適用節數 |
|---|---|---|
| **Controller**（`app/Http/Controllers/API/`） | 回傳 `{ status: false, message }` 與對應 HTTP 狀態碼（驗證 422、無權限 403、查無 404、狀態衝突沿用既有的 422） | 全部 1–8 |
| **Service**（新增業務邏輯時抽出的類別） | 以明確的回傳值或窄型的業務例外表達，由 Controller 轉成 HTTP 回應 | 全部 1–8 |
| **Model／Enum**（例如 `BookingStatus::canTransitionTo`） | 只確認既有不變量與慣例是否維持一致 | 僅第 3 節，不套用第 1、6、7 節 |
| **Artisan 指令、Queue job、排程** | 不經 HTTP 例外處理，需自行定義失敗行為（`tries`、`backoff`、`failed()`、指令 exit code） | 第 5–8 節，且見第 6 節的「非 HTTP 路徑」 |

**不套用本 Skill**：
- private helper、單純 getter／accessor、`formatBooking()` 這類純資料轉換
- 純計算邏輯（無 I/O）
- 方法尚未實作完整邏輯（先完成實作）

## 套用前必答（CLAUDE.md 安全強制規則）

方法涉及外部輸入、資料庫讀寫、授權或檔案時，套用前**必須在 commit 說明、PR 描述或工作紀錄實際寫下答案**，不能只是心裡想過：

```text
- 輸入驗證位置：（用了哪個 validate() 規則集或 FormRequest？還是目前完全沒有？）
- 權限檢查位置：（需要什麼角色與擁有權？檢查在哪一層？注意 member／provider 路由群組目前沒有角色 middleware）
- 是否有 N+1：（with()／load()／withCount() 是否已正確使用？）
- 是否需要悲觀鎖：（是／否，理由）
- 若需要，鎖定哪個資源、為什麼鎖它就足以涵蓋這個操作：（見第 5 節）
```

答不出來代表還沒讀懂現有程式碼，先停下來，不要繼續套用檢查清單。

## 檢查清單

### 1. 參數驗證

| 驗證內容 | 建議位置 |
|---|---|
| 欄位格式、數值範圍、集合不可空、`exists:` 外鍵 | `$request->validate()` 或 FormRequest（沿用同檔案既有寫法） |
| 簡單前置條件（路由參數 `int $id`、必要的 query 參數） | 方法一開始的 guard clause |
| 跨資源的業務規則（例如人數不得超過剩餘名額） | Service 或 Controller 的前置檢查，並在鎖之後再驗一次（見第 5 節） |
| 單一資源的不變量 | Model／Enum |

```php
$data = $request->validate([
    'schedule_id'  => 'required|integer|exists:course_schedules,id',
    'participants' => 'required|integer|min:1',
    'notes'        => 'nullable|string|max:500',
]);
```

- 驗證失敗必須發生在**任何資料庫寫入、交易、鎖定、外部呼叫之前**。
- 字串要有 `max`，數值要有上下界，陣列要限制元素數量，避免超大輸入。
- `mass assignment`：`create()`／`update()` 只傳入 `$validated` 或 `$request->only([...])` 的結果，不要傳 `$request->all()`，且 Model 的 `$fillable` 不得包含 `role`、`is_active`、`provider_id`、`verification_status` 這類不該被外部寫入的欄位。

### 2. 資源存在性與擁有權

單筆：

```php
$booking = Booking::with('schedule')->findOrFail($id);   // 查無 → 404
if ($booking->member_id !== $request->user()->id) {
    return response()->json(['status' => false, 'message' => '無權操作此預約'], 403);
}
```

- 沿用同一個資源既有的「404 先、403 後」順序與訊息，不要在同一個專案內兩種寫法並存；以對應 spec 為準。
- **擁有權檢查不可省略**：只要資源有 `member_id`、`provider_id` 等所屬欄位，就必須與呼叫者比對（IDOR）。
- 多筆：先對輸入 id 去重，再比對**實際找到的 id 集合**，不只比數量：

```php
$distinctIds = collect($ids)->unique()->values();
$found = Model::whereIn('id', $distinctIds)->pluck('id');
if ($found->count() !== $distinctIds->count()) {
    return response()->json(['status' => false, 'message' => '部分資源不存在'], 422);
}
```

輸入含重複 id（例如 `[1, 1, 2]`）時，資料庫只會回 2 筆卻要求 3 筆，會誤判成資料缺漏；去重之後再比對才正確。

### 3. 狀態機驗證

```php
if (! $booking->canTransitionTo(BookingStatus::Confirmed)) {
    return response()->json(['status' => false, 'message' => '當前狀態無法確認'], 422);
}
```

- 狀態轉換一律經過 Enum 與 `canTransitionTo()`，不要直接 `update(['status' => ...])` 繞過。
- 順序：先驗擁有權，再驗狀態；兩者都過了才進入寫入。
- 沿用既有的失敗狀態碼與訊息風格；不確定時對照同類端點，不要自創新的狀態碼。

### 4. 逾時與重試：不自創數值

- 外部呼叫（HTTP、S3、SMTP）若需要逾時（`Http::timeout()` 等），**數值與理由必須寫進對應 change 的 `design.md`**，不要在套用本 Skill 時隨手塞一個數字。
- Queue job 的 `$tries`、`$timeout`、`backoff()` 同理，沿用現有 Notification 的 `tries = 3` 並在 design 說明是否需要 `backoff`。
- 不要為了防禦而自行包一層 `retry()` 迴圈。

### 5. 併發處理 — 依實際需要序列化的資源設計，不是套固定模板

**先分清楚兩件事**：
- `lockForUpdate()` 才是真正取得資料庫列鎖（`SELECT ... FOR UPDATE`）。
- `$model->refresh()`、`->fresh()` **不取得任何鎖**，只是重新讀取目前的值，不能取代悲觀鎖。

專案既有的兩層驗證（`MemberBookingController::store`）是可參考的具體案例，不是通用模板：

```php
// Layer 1：交易外快速失敗（便宜、無鎖）
if ($data['participants'] > $schedule->remainingSpots()) { /* 422 */ }

DB::transaction(function () use ($data, $schedule) {
    // Layer 2：鎖住「真正需要序列化的資源」後，從資料庫重新讀取並再驗一次
    $schedule = CourseSchedule::lockForUpdate()->find($schedule->id);
    if ($data['participants'] > $schedule->remainingSpots()) { /* 中斷 */ }
    // ... 寫入
});
```

套用到新方法時：

1. 先問「這個方法真正需要序列化的是哪個資源？」，不要預設答案是 `CourseSchedule`。
2. **依快照做出的決策，與決策對應的寫入，必須在同一個鎖與交易內**。若狀態是在鎖之前讀取、鎖之後才寫入，中間的時間差本身就是一個競態視窗（例如兩個並發請求都讀到 `confirmed`，都各自扣減一次名額）。需要的話在鎖之後重新取得該列再判斷。
3. 計數器增減使用 `increment()`／`decrement()` 並在鎖內完成；之後要用最新值判斷（例如是否額滿）時，先 `refresh()`，這是「重讀」，不提供並發保護。
4. 重複建立防護依靠資料庫唯一索引或鎖內的存在性檢查，不要只靠交易外的 `exists()` 判斷。
5. 沒有既有鎖定機制可比照、也想不出該鎖什麼時，**不要自己發明重試迴圈**。先確認這個方法是否真的需要處理併發（多數查詢方法不需要），需要就把設計決策寫進 `design.md`。
6. **測試限制**：`phpunit.xml` 強制使用 SQLite `:memory:`，`lockForUpdate()` 在 SQLite 上沒有實質效果，單程序測試也無法重現真正的併發。因此併發行為的保證不能宣稱「已被自動化測試驗證」，必須另外在 MySQL 或容器內人工驗證，並在 PR 說明註明。

### 6. 例外邊界：先判斷是交易必要步驟還是 best-effort 副作用

**套用前先回答**：這段程式碼失敗時，是否應該讓整個請求失敗（交易必要步驟）？還是這個副作用允許失敗而不影響主流程（best-effort，且這個決策必須**已經寫在對應 change 的 spec 或 design**，不是套用本 Skill 時自己決定）？

**情境 A：交易必要步驟**（資料庫寫入、名額與狀態變更、授權變更、付款類操作）：
- 不要 `catch (Throwable)` 後吞掉。讓例外往外傳播，由 Laravel 例外處理器處理（本專案已透過 `Sentry\Laravel\Integration::handles` 回報）。
- 在 `DB::transaction()` 閉包內拋出例外會自動 rollback。
- 若需要在閉包內以例外中斷並轉成 422，**使用專用、窄型的業務例外**，且 Controller 只 catch 該型別。**不要 `catch (\RuntimeException $e)` 後把 `$e->getMessage()` 直接回給使用者**：`QueryException`／`PDOException` 也是 `RuntimeException` 的子類別，會把資料庫錯誤（含 SQL 與結構資訊）當成 422 回傳，也不會被 Sentry 收到。

**情境 B：post-commit 且 spec 已明確定義為 best-effort 的副作用**（例如通知派送、廣播）：

```php
try {
    $provider->notify(new BookingCreatedNotification($booking));
} catch (\Throwable $e) {
    report($e);   // 讓 Sentry 收到；主流程已成功，不重新拋出
}
```

- 只能用在**交易 commit 之後**，且該副作用失敗不應讓已成功的主流程回報錯誤。
- 通知類別已實作 `ShouldQueue` 時，`notify()` 只是入列，這個 catch 防的是「入列失敗」（例如 Redis 不可用）。
- 記錄請用 `report($e)`，或 `Log::error()` 搭配 context；單純 `Log::error('...' . $e->getMessage())` 不會送 Sentry。

**把情境 A 的作法套到情境 B 是錯的**（通知失敗讓已成功的預約回 500），**反過來更危險**：把資料庫寫入或名額扣減當成 best-effort 吞掉，會出現「回報成功、資料沒完成」。不確定屬於哪一種時，去讀對應 change 的 spec 與 design，不要自己猜。

**不要為每種失敗建立一個例外類別**。能用明確回應表達的失敗（驗證、查無、無權限、狀態衝突）就用回應；只有交易閉包內必須中斷流程時才需要業務例外，且整個專案用同一個窄型例外，不是一個失敗一個類別。

**非 HTTP 路徑**：Queue job、Artisan 指令、排程**不會經過 HTTP 例外處理**。
- Queue job：設定 `$tries`、`backoff()`，必要時實作 `failed()`；重試用完會進 `failed_jobs`，要確認有人監看。
- 指令與排程（例如 `app:expire-pending-bookings`）：自行處理失敗並回傳非 0 的 exit code，不要假設有全域處理器兜底。

### 7. 日誌：結構化、不含個資、等級對應風險

```php
Log::info('預約已建立', ['booking_id' => $booking->id, 'schedule_id' => $schedule->id]);
Log::warning('預約確認被拒絕', ['booking_id' => $booking->id, 'reason' => 'insufficient_spots']);
```

- 用 context 陣列傳遞欄位，不要用字串插值把值拼進訊息，才能在 log 系統個別查詢。
- **不記錄**密碼、token、API key、Email、電話、地址等個資；必要時遮蔽或只記錄 id。
- 預設只記錄失敗的**類型或代碼**，不記錄使用者輸入原文或完整的 `$e->getMessage()`（可能含個資與 SQL）。
- 不要把例外訊息、堆疊追蹤或 SQL 回傳給 API 使用者。
- 等級對應風險，不要每個分支用同一等級：

| 情境 | 建議等級 |
|---|---|
| 預期內的輸入驗證失敗 | 通常不記錄或 `debug`（外部輸入錯誤是正常流量） |
| 查無資源 | 視情境；高頻查詢的 404 不需要記錄 |
| 權限拒絕、狀態衝突 | `info` 或 `warning`，依業務風險決定 |
| 非預期技術例外 | `error`（並確認有送到 Sentry） |

### 8. 測試要求

先列出這次實際補強的防禦分支；每個分支至少要有一項 Feature 或 Unit 測試覆蓋，缺哪個就明確標注「未覆蓋，原因：…」，不要預設全部都測過：

- 驗證失敗 → 422，**且資料庫沒有任何寫入**（`assertDatabaseCount` 或 `assertDatabaseMissing`），證明失敗發生在寫入之前
- 資源不存在 → 404
- 擁有權不符、角色不符 → 403；涉及權限時要有 member／provider／admin／未登入的跨角色測試（參考 `tests/Feature/AdminEndpointAuthTest.php`）
- 狀態不合法 → 422，且狀態與名額沒有被改動
- 多筆存在性檢查的**重複 id** 邊界案例
- 併發行為 → 說明測試以 SQLite 執行、無法驗證行鎖；若寫了迴圈式的「模擬併發」測試，不得據此宣稱併發安全
- best-effort 副作用失敗 → 以 `Notification::fake()` 或讓 `notify()` 拋出例外，斷言**主流程仍成功**且已回報（例如 `report`）
- 交易必要步驟失敗 → 斷言整筆 rollback，沒有留下半完成的資料
- 日誌 → 斷言沒有洩漏個資或機敏資訊（可用 `Log::spy()`）

## 何時跳過

適用範圍已列於「先確認你在哪一層」。額外的跳過條件：
- 方法已有防禦：檢查清單全部通過，且分層正確
- 方法尚未實作完整邏輯

## 驗證步驟

套用本 Skill 後執行：

```bash
php artisan test
./vendor/bin/pint --test <本次變更的 PHP 檔案>
```

若套用本 Skill 導致任何程式碼變更，完成後必須再呼叫 `strict-reviewer`。若只是檢查過一遍、確認既有程式碼已符合清單、沒有任何修改，不需要重複呼叫。

涉及併發的變更，驗證時另外註明：測試未在 MySQL 驗證行鎖，需人工確認。
