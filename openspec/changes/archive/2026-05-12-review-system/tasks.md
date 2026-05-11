## 1. 資料庫層

- [x] 1.1 [後端] 建立 Migration `create_reviews_table`：欄位含 `diving_offer_id`、`member_id`、`rating` (tinyint)、`comment` (text)、`helpful_count` (int)、`is_edited` (boolean)；UNIQUE(member_id, diving_offer_id)；加索引 `(diving_offer_id, helpful_count)`、`(diving_offer_id, rating)`、`(diving_offer_id, created_at)`
- [x] 1.2 [後端] 建立 Migration `create_review_edits_table`：欄位含 `review_id` (UNIQUE FK)、`old_rating`、`old_comment`、`edited_at`
- [x] 1.3 [後端] 建立 Migration `create_review_votes_table`：欄位含 `review_id`、`member_id`；UNIQUE(review_id, member_id)
- [x] 1.4 [後端] 執行 Migration，確認三張資料表與索引正確

## 2. Model 層

- [x] 2.1 [後端] 建立 `app/Models/Review.php`：fillable、casts、關聯（belongsTo DivingOffer / belongsTo User as member、hasOne ReviewEdit、hasMany ReviewVote）
- [x] 2.2 [後端] 建立 `app/Models/ReviewEdit.php`：fillable、belongsTo Review
- [x] 2.3 [後端] 建立 `app/Models/ReviewVote.php`：fillable、belongsTo Review / belongsTo User as member
- [x] 2.4 [後端] 在 `DivingOffer` Model 新增 `hasMany Review` 關聯

## 3. Member 評價 API

- [x] 3.1 [後端] 建立 `app/Http/Controllers/API/ReviewController.php`：
  - 私有方法 `recalculateOfferRating(int $offerId)`：重算 AVG(rating) 與 COUNT(*)，並 UPDATE diving_offers，**必須在 DB transaction 內被呼叫**
  - `store`：資格驗證（`bookings JOIN course_schedules WHERE status=completed AND diving_offer_id=X`，否則 403）→ 重複評價檢查（422）→ DB transaction 建立 Review + 呼叫 recalculate
  - `update`：所有權驗證（他人 403）→ DB transaction：updateOrCreate review_edits（覆蓋舊版）→ 更新 Review 內容 + is_edited=true → 呼叫 recalculate
  - `destroy`：所有權驗證（他人 403）→ DB transaction：刪除 Review（cascade edits/votes）→ 呼叫 recalculate
- [x] 3.2 [後端] 在 `routes/api.php` 新增 `/member/reviews` 路由群組（POST / PUT /{id} / DELETE /{id}）

## 4. 有幫助投票 API

- [x] 4.1 [後端] 在 `ReviewController` 新增 `toggleHelpful` 方法：不可投自己（422）→ **整個 toggle 在 DB transaction 內**：查 ReviewVote → 有則 delete + `DB::raw('GREATEST(helpful_count - 1, 0)')` 原子更新（禁止兩段式 decrement+check）；無則 create + increment
- [x] 4.2 [後端] 在 `routes/api.php` 新增 `POST /reviews/{id}/helpful` 路由（auth:sanctum）

## 5. 公開評價列表 API

- [x] 5.1 [後端] 在 `ReviewController` 新增 `publicList` 方法：
  - 回傳 `summary`（AVG、COUNT、1–5 星分布）：分布用 `GROUP BY rating COUNT(*)` 動態查詢並補齊 key 1–5（含零值），不另存欄位
  - 依 sort 參數排序：helpful→`(helpful_count DESC, created_at DESC)`；rating→`(rating DESC, created_at DESC)`；newest→`(created_at DESC)`
  - 批次查詢 has_voted（已登入：`ReviewVote::whereIn('review_id', ...)->pluck('review_id')`；未登入：全 false）
  - is_mine：已登入才加此欄位（未登入省略）
  - reviewer_name 固定為「匿名潛水者」
- [x] 5.2 [後端] 在 `routes/api.php` 新增 `GET /diving-offers/{id}/reviews` 公開路由

## 6. Admin 評價管理 API

- [x] 6.1 [後端] 建立 `app/Http/Controllers/API/AdminReviewController.php`：
  - `index`：全量列出（`created_at DESC`）含課程名、member email、rating、comment 前 50 字
  - `destroy`：DB transaction 刪除 Review（cascade）→ 呼叫 `recalculateOfferRating`（**Admin 刪除也必須重算**，與 Member destroy 共用同一邏輯）
- [x] 6.2 [後端] 在 `routes/api.php` Admin 群組新增 `/admin/reviews` 路由（GET / DELETE /{id}）

## 7. 前端 API 封裝

- [x] 7.1 [前端] 建立 `frontend/src/api/reviewApi.js`：`getReviews(offerId, sort)`、`createReview(payload)`、`updateReview(id, payload)`、`deleteReview(id)`、`toggleHelpful(reviewId)`

## 8. 課程詳情頁評價區塊

- [x] 8.1 [前端] 更新 `frontend/src/views/CourseDetailView.vue`：新增評價區塊，顯示整體星等、評分分布條、排序切換按鈕（最多幫助 / 最高分 / 最新）
- [x] 8.2 [前端] 評價列表元件：顯示星等、「匿名潛水者」、日期、「已修改」標記、「有幫助 N 人」按鈕（登入後可點擊 Toggle）
- [x] 8.3 [前端] 評價表單：已登入 Member 且有 completed booking 才顯示；已評過則顯示「我的評價」含修改/刪除按鈕

## 9. Admin 評價管理頁

- [x] 9.1 [前端] 新增 `frontend/src/views/admin/ReviewsView.vue`：列出所有評價（課程名、內容、星等、刪除按鈕）
- [x] 9.2 [前端] 在 Admin Navbar 加入「評價管理」連結，路由 `/admin/reviews`
- [x] 9.3 [前端] 在 `frontend/src/router/index.js` 新增 `/admin/reviews` 路由（requiresAdmin）

## 10. 整合驗證

- [x] 10.1 [整合測試] 完整流程：Member 完成課程 → 新增評價 → 確認 diving_offers.rating / reviews 更新
- [x] 10.2 [整合測試] 修改評價：確認 is_edited=true、review_edits 有舊版、rating 重算正確
- [x] 10.3 [整合測試] 刪除評價：rating/reviews 歸零或重算正確
- [x] 10.4 [整合測試] 投票 Toggle：連點兩次確認 helpful_count 正確增減、第三次確認不低於 0
- [x] 10.5 [整合測試] 不可投自己：Member 對自己評價投票應回傳 422
- [x] 10.6 [整合測試] 匿名確認：API 回傳的 reviewer_name 一律為「匿名潛水者」，不含真實姓名
- [x] 10.7 [整合測試] 排序確認：三種 sort 參數回傳順序正確
- [x] 10.8 [整合測試] Admin 刪除重算：Admin 刪除評價後確認 diving_offers.rating / reviews 同步更新
- [x] 10.9 [整合測試] is_mine / has_voted 欄位規則：未登入不含 is_mine 欄位；登入後自己的評價 is_mine=true；has_voted 正確反映投票狀態
- [x] 10.10 [整合測試] 資格驗證：無 completed booking 的 Member 嘗試評價應回傳 403；有 completed booking 才能成功
