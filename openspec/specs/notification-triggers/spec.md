## ADDED Requirements

### Requirement: 預約建立觸發通知

系統 SHALL 在預約成功建立（status = `pending`）時，通知課程所屬 Provider（站內 + Email）。觸發點在 `MemberBookingController::store()` 的 DB transaction commit 之後。

#### Scenario: Member 建立預約

- **WHEN** `MemberBookingController::store()` 成功建立預約並回傳 201
- **THEN** 取得 `$booking->schedule->divingOffer->provider`（Provider），呼叫 `$provider->notify(new BookingCreatedNotification($booking))`，以 try/catch 包裹

---

### Requirement: 預約確認觸發通知

系統 SHALL 在 Provider 確認預約（status `pending` → `confirmed`）時，通知 Member（站內 + Email）。觸發點在 `ProviderBookingController::confirm()` 的 DB transaction commit 之後。

#### Scenario: Provider 確認預約

- **WHEN** `ProviderBookingController::confirm()` 執行，狀態更新為 `confirmed`
- **THEN** 取得 `$booking->member`，呼叫 `$member->notify(new BookingConfirmedNotification($booking))`

---

### Requirement: 預約拒絕觸發通知

系統 SHALL 在 Provider 拒絕預約（status → `rejected`）時，通知 Member（站內 + Email）。觸發點在 `ProviderBookingController::reject()` 的 `$booking->update()` 之後。

#### Scenario: Provider 拒絕預約

- **WHEN** `ProviderBookingController::reject()` 執行
- **THEN** 取得 `$booking->member`，呼叫 `$member->notify(new BookingRejectedNotification($booking))`

---

### Requirement: BookingCancelledNotification 文案區分

`BookingCancelledNotification` SHALL 依建構子參數 `cancelledBy: 'member' | 'provider'` 產生不同文案：

| cancelledBy | 通知對象 | title | body |
|-------------|---------|-------|------|
| `'member'` | Provider | 學員取消了預約 | 學員已取消《{課程名稱}》的預約（時段：{日期}） |
| `'provider'` | Member | 教練取消了你的預約 | 教練已取消你的《{課程名稱}》預約（時段：{日期}），如有疑問請聯繫教練 |

`toArray()` 的 `action_url`：
- `cancelledBy: 'member'` → `{FRONTEND_URL}/coach/bookings`
- `cancelledBy: 'provider'` → `{FRONTEND_URL}/my-bookings/{booking.id}`

#### Scenario: 文案依角色區分

- **WHEN** `new BookingCancelledNotification($booking, cancelledBy: 'member')` 的 `toArray()` 被呼叫
- **THEN** `title` 為「學員取消了預約」，`action_url` 指向 `/coach/bookings`

#### Scenario: Provider 取消文案

- **WHEN** `new BookingCancelledNotification($booking, cancelledBy: 'provider')` 的 `toArray()` 被呼叫
- **THEN** `title` 為「教練取消了你的預約」，`action_url` 指向 `/my-bookings/{id}`

---

### Requirement: 預約取消觸發通知（Member 發起）

系統 SHALL 在 Member 取消預約（status → `member_cancelled`）時，通知 Provider（站內 + Email）。觸發點在 `MemberBookingController::cancel()` 的 DB transaction commit 之後。

#### Scenario: Member 取消預約

- **WHEN** `MemberBookingController::cancel()` 執行，`$booking->update(['status' => BookingStatus::MemberCancelled])`
- **THEN** 取得 `$booking->schedule->divingOffer->provider`（Provider），呼叫 `$provider->notify(new BookingCancelledNotification($booking, cancelledBy: 'member'))`

---

### Requirement: 預約取消觸發通知（Provider 發起）

系統 SHALL 在 Provider 取消預約（status → `provider_cancelled`）時，通知 Member（站內 + Email）。觸發點在 `ProviderBookingController::cancel()` 的 DB transaction commit 之後。

#### Scenario: Provider 取消預約

- **WHEN** `ProviderBookingController::cancel()` 執行，`$booking->update(['status' => BookingStatus::ProviderCancelled])`
- **THEN** 取得 `$booking->member`，呼叫 `$member->notify(new BookingCancelledNotification($booking, cancelledBy: 'provider'))`

---

### Requirement: 預約完成觸發通知

系統 SHALL 在預約標記為完成（status → `completed`）時，通知 Member 可前往評價（站內 + Email）。觸發點包含：`ProviderBookingController::complete()`（手動）與 `CompleteFinishedBookings` Command（排程自動完成）。

#### Scenario: 手動完成

- **WHEN** `ProviderBookingController::complete()` 執行
- **THEN** 取得 `$booking->member`，呼叫 `$member->notify(new BookingCompletedNotification($booking))`

#### Scenario: 排程自動完成（含 N+1 防護）

- **WHEN** `CompleteFinishedBookings::handle()` 執行
- **THEN** 使用 `->with(['member', 'schedule.divingOffer'])->get()` 取得 booking 集合（**禁止 bulk `->update()`**），loop 內逐筆 `$booking->update(status: Completed)` + try/catch notify；單筆 notify 失敗不中斷整個批次

---

### Requirement: 收到評價觸發通知

系統 SHALL 在 Member 成功提交評價後，通知被評價課程的 Provider（僅站內通知，無 Email）。觸發點在 `ReviewController::store()` 的 DB transaction commit 之後。

取得 Provider 的方式：`DivingOffer::with('provider')->findOrFail($offerId)->provider`（DivingOffer `belongsTo` User）。

#### Scenario: Member 提交評價

- **WHEN** `ReviewController::store()` 的 DB transaction 成功，`$review` 建立完成
- **THEN** 取得 `$offer->provider`（Provider），呼叫 `$provider->notify(new ReviewReceivedNotification($review))`（僅 `['database']`）

---

### Requirement: 通知觸發為原子操作，不影響主業務

所有 notify 呼叫 SHALL 以 `try/catch (\Throwable $e)` 包裹，若失敗僅寫入 Laravel log，不得造成主業務回傳錯誤或 rollback。

#### Scenario: notify 失敗不影響主業務

- **WHEN** `$user->notify(...)` 拋出任何例外
- **THEN** 預約/評價主業務資料已正確儲存，HTTP response 正常回傳，`\Log::error(...)` 記錄錯誤
