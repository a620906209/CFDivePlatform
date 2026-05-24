## 1. 基礎設施：Redis 設定

- [x] 1.1 [後端] `docker-compose.yml` 新增 `redis:alpine` service，並在 `cfdive-app` depends_on 加入 `redis`
- [x] 1.2 [後端] `.env` 設定 `CACHE_STORE=redis`、`REDIS_HOST=redis`、`REDIS_PORT=6379`；`.env.example` 同步補上
- [x] 1.3 [後端] `config/cache.php` 確認 redis connection 設定正確（`REDIS_HOST`、`REDIS_PASSWORD`、`REDIS_PORT`）
- [x] 1.4 [後端] 執行 `docker-compose up --build`，確認 redis container 啟動，`php artisan cache:clear` 無連線錯誤

## 2. 資料庫索引補充

- [x] 2.1 [後端] 建立 migration `add_performance_indexes`，為 `notifications` 表新增 `[notifiable_type, notifiable_id, read_at]` 複合索引
- [x] 2.2 [後端] 同一 migration 為 `diving_offers` 表新增 `provider_id` 索引
- [x] 2.3 [後端] 執行 `php artisan migrate`，確認索引建立成功（可用 `SHOW INDEX FROM notifications` 驗證）

## 3. 查詢優化：ReviewController::publicList

- [x] 3.1 [後端] `ReviewController::publicList()` 加入分頁：`->paginate(min($perPage, 50))`，`per_page` 預設 20，從 request 取得
- [x] 3.2 [後端] 評價查詢改為 `Review::with('votes')->where(...)` eager load votes，移除獨立的 `ReviewVote::where()->pluck()` SQL
- [x] 3.3 [後端] `has_voted` 判斷改從 eager loaded `$review->votes` collection 計算（`$review->votes->contains('member_id', $memberId)`）
- [x] 3.4 [後端] 評價分布統計加上 `Cache::remember("offer_review_distribution_{$offerId}", 600, fn() => ...)` 快取（TTL 10 分鐘）
- [x] 3.5 [後端] Member 新增/修改/刪除評價的 service 方法中，加入 `Cache::forget("offer_review_distribution_{$offerId}")` 快取失效
- [x] 3.6 [後端] Response 加入 Laravel 分頁 meta：`current_page`、`last_page`、`per_page`、`total`

## 4. 查詢優化：AdminReviewController

- [x] 4.1 [後端] `AdminReviewController::index()` 的 `.get()` 改為 `.paginate(min($perPage, 100))`，`per_page` 預設 20

## 5. 快取層：AdminStatsController

- [x] 5.1 [後端] `AdminStatsController` 的三個 `count()` 查詢，用 `Cache::remember('admin_stats', 300, fn() => [...])` 包裹（TTL 5 分鐘）

## 6. 快取層：課程列表

- [x] 6.1 [後端] `DivingOfferController::index()` 對搜尋結果加入 `Cache::remember()` 快取，Cache key 為 `diving_offers_` + md5(serialize($request->all()))，TTL 180 秒
- [x] 6.2 [後端] `DivingOfferController::store()`、`update()`、`destroy()` 加入 `Cache::flush()` 或 `Cache::forget()` 清除對應快取 key

## 7. 前端相容性調整

- [x] 7.1 [前端] `CourseDetailView.vue` 的 reviews API 呼叫確認 response 結構相容（`data.data` 現在含分頁 meta，原本是陣列）
- [x] 7.2 [前端] `ReviewsView.vue`（Coach Portal）確認 API response 結構相容

## 8. 手動驗證

- [x] 8.1 [整合測試] 開啟 `GET /api/diving-offers/{id}/reviews`，確認回傳包含 `meta.total`、`meta.current_page` 等分頁欄位
- [x] 8.2 [整合測試] 呼叫 `GET /api/admin/stats` 兩次，確認第二次無 DB 查詢（可用 Laravel Telescope 或 query log 確認）
- [x] 8.3 [整合測試] Provider 修改課程後，再次請求課程列表確認資料已更新（快取失效正常）
- [x] 8.4 [整合測試] 執行 `SHOW INDEX FROM notifications`、`SHOW INDEX FROM diving_offers`，確認索引存在
