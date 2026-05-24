## Context

前端請求回應偏慢，調查後確認三個根本原因：
1. **無快取**：所有請求直打 DB，cache driver 設為 `database`（等於又多一次 DB 查詢）
2. **全表查詢**：`AdminReviewController::index()` 與 `ReviewController::publicList()` 直接 `.get()` 無分頁，資料量增長後線性惡化
3. **缺少索引**：`notifications` 與 `diving_offers` 表缺少 WHERE clause 中常用欄位的索引，導致全表掃描

## Goals / Non-Goals

**Goals:**
- 補全缺少的 DB 索引，消除 notifications / diving_offers 的全表掃描
- 將 `ReviewController::publicList()` 的 3 次 SQL 合併，並加入分頁
- `AdminReviewController::index()` 加入分頁（預設 20 筆）
- 引入 Redis 快取層，覆蓋 Admin Stats、課程列表、評價分布三個高頻端點
- 將 `CACHE_STORE` 切換為 `redis`

**Non-Goals:**
- 前端 HTTP 層級的 CDN / HTTP Cache-Control header 優化
- Elasticsearch 全文搜尋（課程 LIKE 搜尋的長期方案）
- 資料庫連線池調整（屬於 DevOps 層級）
- API Response Compression（gzip，屬於 nginx 設定）

## Decisions

### 決策 1：Cache driver 改為 Redis 而非 Memcached

**選擇**：Redis

**理由**：
- Docker 環境中加一個 `redis:alpine` service 即可，成本低
- Redis 支援 TTL、Pub/Sub、Queue（未來 Job Queue 可複用同一個 Redis）
- Memcached 無法用於 Laravel Queue，擴展性較差

**替代方案**：保留 `database` driver → 拒絕，因為快取本身又增加 DB 負擔，反效果

---

### 決策 2：ReviewController::publicList 合併查詢策略

**目前狀況**：3 次獨立 SQL
1. `Review::where()->get()` — 撈評價列表
2. `ReviewVote::where()->pluck()` — 撈已投票 ID
3. `Review::selectRaw()->groupBy()->pluck()` — 統計分布

**選擇**：合併為 2 次 SQL
- SQL 1：`Review::with('votes')->where()->paginate()` — 評價列表（含 votes eager load）
- SQL 2：`Review::selectRaw()->groupBy()->pluck()` — 統計分布（保留，邏輯獨立）
- `has_voted` 改從 eager loaded `votes` collection 中判斷，不再發第 3 次 SQL

**理由**：分布統計邏輯與列表查詢職責不同，維持獨立 SQL 可讀性更高；votes 用 eager load 解決 N+1

**替代方案**：全部合一次 SQL（subquery）→ 拒絕，可讀性差，維護困難

---

### 決策 3：快取 TTL 策略

| 端點 | TTL | 失效時機 |
|------|-----|----------|
| Admin Stats（總數統計）| 5 分鐘 | 手動清除（可接受少許延遲）|
| 課程列表（`GET /api/diving-offers`）| 3 分鐘 | Provider 新增/修改/刪除課程時 `Cache::forget()` |
| 評價分布（per offer）| 10 分鐘 | Member 新增/修改/刪除評價時 `Cache::forget()` |

**理由**：Stats 為 Admin 用，接受延遲；課程列表對 SEO 重要，失效時機明確；評價分布計算成本高，TTL 稍長

---

### 決策 4：分頁預設值

- `ReviewController::publicList()`：`per_page` 預設 20，最大 50
- `AdminReviewController::index()`：`per_page` 預設 20，最大 100
- **相容性**：前端目前不傳 `page`，Laravel `paginate()` 預設 `page=1`，現有行為不破壞

## Risks / Trade-offs

- **[Risk] Redis 服務故障** → `CACHE_STORE=redis` 時 Redis 掛掉會拋出 Exception → Mitigation：在 `.env.example` 補 fallback 說明；生產環境考慮 `CACHE_STORE` 動態切換
- **[Risk] 快取資料與 DB 短暫不一致** → 課程列表快取 3 分鐘內不即時 → Mitigation：寫入操作（新增/修改/刪除課程、新增評價）主動 `Cache::forget()`，只有統計類允許延遲
- **[Risk] Migration 補索引在生產環境加鎖** → MySQL 8 ADD INDEX 為 online DDL，不鎖表 → 低風險

## Migration Plan

1. 執行 `php artisan migrate`（補索引 migration）
2. `docker-compose.yml` 加入 Redis service
3. `.env` 設定 `CACHE_STORE=redis`、`REDIS_HOST=redis`
4. 部署後重啟 `cfdive-app` container

**Rollback**：
- 索引可 `DROP INDEX`（不影響資料）
- 快取失效直接 `CACHE_STORE=database` 回退，行為與現在相同

## Open Questions

- Redis 是否需要設定 `maxmemory-policy`（防止記憶體溢出）？建議 `allkeys-lru`，但需確認 Docker 記憶體限制
- 課程列表的快取 key 是否要包含搜尋參數？若包含，失效邏輯需改為 tag-based invalidation（`Cache::tags()`）
