## ADDED Requirements

### Requirement: 課程列表 API
後端 SHALL 提供公開的 `GET /api/diving-offers` endpoint，回傳分頁的潛水課程列表，支援關鍵字搜尋與篩選，無需認證即可存取。

#### Scenario: 取得全部課程列表
- **WHEN** 客戶端發送 `GET /api/diving-offers` 且不帶任何參數
- **THEN** 回傳 HTTP 200，body 包含 `{ data: [...], meta: { total, per_page, current_page } }`，預設每頁 12 筆

#### Scenario: 依關鍵字搜尋課程
- **WHEN** 客戶端發送 `GET /api/diving-offers?q=墾丁`
- **THEN** 回傳 `title` 或 `location` 包含「墾丁」的課程列表

#### Scenario: 依地區篩選課程
- **WHEN** 客戶端發送 `GET /api/diving-offers?region=南部`
- **THEN** 只回傳 `region` 欄位等於「南部」的課程

#### Scenario: 依標籤篩選課程
- **WHEN** 客戶端發送 `GET /api/diving-offers?tag=初學者`
- **THEN** 只回傳 `tag` 欄位包含「初學者」的課程

#### Scenario: 分頁參數
- **WHEN** 客戶端發送 `GET /api/diving-offers?page=2&per_page=6`
- **THEN** 回傳第 2 頁資料，每頁 6 筆，`meta` 包含正確的分頁資訊

---

### Requirement: 課程詳情 API
後端 SHALL 提供公開的 `GET /api/diving-offers/{id}` endpoint，回傳單一課程完整資訊，無需認證即可存取。

#### Scenario: 取得存在的課程詳情
- **WHEN** 客戶端發送 `GET /api/diving-offers/1`（該 id 存在）
- **THEN** 回傳 HTTP 200，body 包含 `{ data: { id, title, location, spot, rating, reviews, price, badges, description, tag, region, created_at } }`

#### Scenario: 課程不存在
- **WHEN** 客戶端發送 `GET /api/diving-offers/99999`（該 id 不存在）
- **THEN** 回傳 HTTP 404，body 包含 `{ message: "課程不存在" }`

---

### Requirement: CORS 允許前端 Origin
後端 SHALL 在 `config/cors.php` 中允許來自前端開發 origin（`http://localhost:5173`）的跨域請求。

#### Scenario: 前端跨域請求課程列表
- **WHEN** 瀏覽器從 `http://localhost:5173` 發送 `GET /api/diving-offers`
- **THEN** 後端回應包含正確的 CORS header，瀏覽器不阻擋請求
