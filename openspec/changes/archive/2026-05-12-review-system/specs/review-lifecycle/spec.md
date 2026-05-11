## ADDED Requirements

### Requirement: Member 新增評價
已完成特定課程的 Member SHALL 能對該課程留下一次評價（星等 + 文字）。

#### Scenario: 成功新增評價
- **WHEN** 已登入 Member 送出 `POST /api/member/reviews`，包含 `diving_offer_id`、`rating`（1–5 整數）、`comment`（非空字串），且系統查詢 `bookings JOIN course_schedules` 找到至少一筆 `member_id = X AND diving_offer_id = Y AND status = 'completed'`
- **THEN** 系統建立 Review，回傳 201

#### Scenario: 未完成課程不可評價（資格驗證）
- **WHEN** Member 送出評價，但 `bookings` 中不存在任何 `status = 'completed'` 且對應 `diving_offer_id` 的紀錄
- **THEN** 系統回傳 **403**，message：「須完成此課程後才能評價」

#### Scenario: 每門課只能評一次
- **WHEN** `reviews` 中已存在同一 `member_id` + `diving_offer_id` 的紀錄
- **THEN** 系統回傳 **422**（非 409），message：「已評價，如需修改請使用編輯功能」

#### Scenario: 星等範圍驗證
- **WHEN** `rating` 不在 1–5 之間
- **THEN** 系統回傳 422

### Requirement: 評價後即時更新課程統計
Member 新增、修改或刪除評價時，系統 SHALL 在同一 DB transaction 內重算 `diving_offers.rating` 與 `reviews`。

#### Scenario: 新增評價後重算
- **WHEN** Review 建立成功
- **THEN** `diving_offers.rating = ROUND(AVG(rating), 1)`、`diving_offers.reviews = COUNT(*)` 即時更新

#### Scenario: 刪除評價後重算
- **WHEN** Review 被 Member 或 Admin 刪除
- **THEN** `rating` 與 `reviews` 在同一 transaction 內重算；若剩餘 0 筆評價，`rating = 0`、`reviews = 0`

### Requirement: Member 修改評價
Member SHALL 能修改自己的評價，系統保留最近一次修改前的版本並標記已修改。

#### Scenario: 成功修改評價
- **WHEN** Member 送出 `PUT /api/member/reviews/{id}`，包含新的 `rating` 或 `comment`
- **THEN** 系統將舊版 `rating` / `comment` 寫入 `review_edits`（若已存在則覆蓋）；更新 Review 內容；將 `is_edited = true`；重算課程統計

#### Scenario: 只能修改自己的評價
- **WHEN** Member 嘗試修改他人的評價
- **THEN** 系統回傳 403

### Requirement: Member 刪除評價
Member SHALL 能刪除自己的評價，Admin SHALL 能刪除任何評價。

#### Scenario: Member 刪除自己的評價
- **WHEN** Member 送出 `DELETE /api/member/reviews/{id}`
- **THEN** 系統刪除 Review 及對應的 review_edits / review_votes；重算課程統計

#### Scenario: Admin 刪除任意評價
- **WHEN** Admin 送出 `DELETE /api/admin/reviews/{id}`
- **THEN** 系統刪除 Review 及關聯資料；重算課程統計

#### Scenario: 只能刪除自己的評價（非 Admin）
- **WHEN** 非 Admin Member 嘗試刪除他人評價
- **THEN** 系統回傳 403

### Requirement: 評價公開顯示（匿名）
任何人（含未登入）SHALL 能查看課程評價列表，評價人統一顯示為「匿名潛水者」。

#### Scenario: 取得評價列表（含 summary）
- **WHEN** 任何人送出 `GET /api/diving-offers/{id}/reviews?sort=helpful|rating|newest`
- **THEN** 系統回傳 `summary`（平均星等、總數、1–5 星分布）與 `reviews` 列表；`reviewer_name` 一律為「匿名潛水者」；已登入 Member 額外回傳 `is_mine`；未登入 `has_voted` 固定為 `false`、`is_mine` 欄位省略

#### Scenario: 三種排序
- **WHEN** `sort=helpful`（預設）
- **THEN** 依 `helpful_count DESC, created_at DESC` 排序
- **WHEN** `sort=rating`
- **THEN** 依 `rating DESC, created_at DESC` 排序
- **WHEN** `sort=newest`
- **THEN** 依 `created_at DESC` 排序
