## Why

前端請求回應偏慢，調查後確認根本原因為：無任何快取層、多處 Controller 直接執行全表查詢（無分頁）、資料庫缺少關鍵索引，導致每次請求都是全量 DB 掃描。

## What Changes

- **補充 DB 索引**：`notifications` 表補 `[notifiable_type, notifiable_id, read_at]` 複合索引；`diving_offers` 表補 `provider_id` 索引
- **查詢優化**：`ReviewController::publicList()` 將 3 次獨立 SQL 合併；`AdminReviewController::index()` 加入分頁
- **引入快取層（Redis）**：`AdminStatsController` 統計數據、課程列表搜尋結果、評價分布加 `Cache::remember()`
- **Cache driver 切換為 Redis**：目前 driver 為 `database`，改為 Redis 以降低快取本身的 DB 負擔

## Capabilities

### New Capabilities

- `api-cache-layer`：為高頻讀取端點（統計、課程列表、評價分布）加入 Redis 快取，定義 TTL 策略與快取失效時機
- `db-index-optimization`：補充缺少的資料庫索引，涵蓋 notifications 與 diving_offers 表

### Modified Capabilities

- `review-lifecycle`：`publicList` 端點行為改變——合併查詢、加入分頁參數（`per_page` 預設 20）

## Impact

- **後端**：`ReviewController`、`AdminReviewController`、`AdminStatsController`、`config/cache.php`、`.env`（CACHE_STORE=redis）
- **資料庫**：新增一個 migration 補索引
- **Docker**：需確認 `docker-compose.yml` 中已有 Redis 服務（若無則補上）
- **前端**：`ReviewController` 加分頁後，前端呼叫端需傳入 `page` 參數（或保持預設，相容現有行為）
