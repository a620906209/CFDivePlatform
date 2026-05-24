## MODIFIED Requirements

### Requirement: 評價公開顯示（匿名）

任何人（含未登入）SHALL 能查看課程評價列表，評價人統一顯示為「匿名潛水者」。Provider 在 Coach Portal 亦可查看自己課程的評價（只讀）。評價列表 SHALL 支援分頁，預設每頁 20 筆，最大 50 筆。

#### Scenario: 取得評價列表（含 summary，含分頁）

- **WHEN** 任何人送出 `GET /api/diving-offers/{id}/reviews?sort=helpful|rating|newest&page=1&per_page=20`
- **THEN** 系統回傳 `summary`（平均星等、總數、1–5 星分布）與分頁後的 `reviews` 列表；`reviewer_name` 一律為「匿名潛水者」；已登入 Member 額外回傳 `is_mine`；未登入 `has_voted` 固定為 `false`、`is_mine` 欄位省略；回傳包含分頁 meta（`current_page`、`last_page`、`per_page`、`total`）

#### Scenario: per_page 超出上限時截斷

- **WHEN** 請求帶有 `per_page=200`
- **THEN** 系統以 `per_page=50` 處理，不回傳錯誤

#### Scenario: 三種排序

- **WHEN** `sort=helpful`（預設）
- **THEN** 依 `helpful_count DESC, created_at DESC` 排序
- **WHEN** `sort=rating`
- **THEN** 依 `rating DESC, created_at DESC` 排序
- **WHEN** `sort=newest`
- **THEN** 依 `created_at DESC` 排序

#### Scenario: votes 透過 eager loading 查詢

- **WHEN** 已登入 Member 送出評價列表請求
- **THEN** `has_voted` 欄位透過 eager loaded `votes` collection 判斷，不額外發 SQL 查詢
