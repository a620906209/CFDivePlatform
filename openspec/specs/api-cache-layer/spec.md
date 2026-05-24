### Requirement: Redis 作為快取驅動

系統 SHALL 使用 Redis 作為 Laravel 快取驅動，取代現有的 `database` driver。

#### Scenario: 環境設定正確

- **WHEN** `.env` 中 `CACHE_STORE=redis`、`REDIS_HOST=redis`（Docker service name）、`REDIS_CLIENT=predis`
- **THEN** `docker-compose up` 後 `php artisan cache:clear` 執行成功，無連線錯誤

#### Scenario: Redis service 在 docker-compose 中存在

- **WHEN** 執行 `docker-compose up`
- **THEN** `redis` container 正常啟動，`cfdive-app` 可連線至 Redis

---

### Requirement: Admin 統計數據快取

`GET /api/admin/stats` SHALL 使用 `Cache::remember()` 快取結果，TTL 5 分鐘，Cache key 為 `admin_stats`。

#### Scenario: 首次請求寫入快取

- **WHEN** Admin 送出 `GET /api/admin/stats`，且快取中無 `admin_stats` key
- **THEN** 系統執行 DB 查詢並將結果寫入 Redis，回傳統計數據

#### Scenario: 後續請求命中快取

- **WHEN** Admin 在 5 分鐘內再次送出 `GET /api/admin/stats`
- **THEN** 系統直接從 Redis 回傳，不執行任何 DB 查詢

#### Scenario: 快取過期後自動重整

- **WHEN** 5 分鐘 TTL 到期後再次請求
- **THEN** 系統重新執行 DB 查詢並更新快取

---

### Requirement: 課程列表快取

`GET /api/diving-offers` SHALL 快取搜尋結果，TTL 3 分鐘，Cache key 包含查詢參數的 hash，並使用 `diving_offers` tag 管理失效。

#### Scenario: 相同搜尋條件命中快取

- **WHEN** 同樣的搜尋參數（region、tag、keyword 等）在 3 分鐘內再次請求
- **THEN** 系統從 Redis 回傳，不執行 DB 查詢

#### Scenario: Provider 異動課程時清除快取

- **WHEN** Provider 成功新增、修改或刪除課程（`POST/PUT/DELETE /api/provider/offers`）
- **THEN** 系統呼叫 `Cache::tags(['diving_offers'])->flush()` 清除所有課程列表快取，下次請求重新查詢

---

### Requirement: 評價分布快取

`GET /api/diving-offers/{id}/reviews` 的 `distribution`（1–5 星分布統計）SHALL 獨立快取，TTL 10 分鐘，Cache key 為 `offer_review_distribution_{id}`。

#### Scenario: 分布統計命中快取

- **WHEN** 同一課程 reviews 端點在 10 分鐘內再次請求
- **THEN** `distribution` 欄位從 Redis 取得，不執行 `GROUP BY` SQL

#### Scenario: 新增/修改/刪除評價時清除分布快取

- **WHEN** Member 成功新增、修改或刪除某課程的評價
- **THEN** 系統呼叫 `Cache::forget("offer_review_distribution_{offerId}")` 清除對應快取
