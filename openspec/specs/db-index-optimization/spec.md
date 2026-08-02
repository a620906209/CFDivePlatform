# db-index-optimization Specification

## Purpose

定義為加速常用查詢（未讀通知計數、教練課程列表）而新增的資料庫複合索引與單欄索引，避免這些高頻查詢隨資料量成長退化為 full table scan。

## Requirements

### Requirement: Notifications 表補複合索引

`notifications` 表 SHALL 新增 `[notifiable_type, notifiable_id, read_at]` 複合索引，以加速 `unreadNotifications()` 查詢。

#### Scenario: Migration 執行成功

- **WHEN** 執行 `php artisan migrate`
- **THEN** `notifications` 表上存在 `notifications_notifiable_read_at_index` 複合索引，`EXPLAIN` 結果不再為 full table scan

#### Scenario: 未讀通知查詢走索引

- **WHEN** 系統執行 `Notification::where('notifiable_type', User::class)->where('notifiable_id', $userId)->whereNull('read_at')->get()`
- **THEN** MySQL `EXPLAIN` 顯示使用複合索引，`type` 為 `ref` 而非 `ALL`

---

### Requirement: DivingOffers 表補 provider_id 索引

`diving_offers` 表 SHALL 新增 `provider_id` 單欄索引，以加速 Provider 課程列表查詢。

#### Scenario: Migration 執行成功

- **WHEN** 執行 `php artisan migrate`
- **THEN** `diving_offers` 表上存在 `provider_id` 索引

#### Scenario: Provider 課程列表查詢走索引

- **WHEN** 系統執行 `DivingOffer::where('provider_id', $providerId)->get()`
- **THEN** MySQL `EXPLAIN` 顯示使用 `provider_id` 索引，`type` 為 `ref`
