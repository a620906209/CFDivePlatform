## Why

預約系統完成後，平台已能撮合教練與學員完成課程。但 `diving_offers.rating` 與 `reviews` 兩個欄位目前是假資料，平台缺乏真實評價機制，Member 瀏覽課程時無法參考其他人的體驗。評價系統是提升平台信任度與課程品質的關鍵閉環。

## What Changes

- 新增 `reviews` 資料表：Member 對完成的課程留下星等（1–5）與文字評論
- 新增 `review_edits` 資料表：記錄最近一次修改前的舊版本（一評價一筆上限）
- 新增 `review_votes` 資料表：Member 對評價投「有幫助」票（可取消，防重複）
- `diving_offers.rating` / `.reviews` 在新增、修改、刪除評價時即時重算
- 評價公開顯示，評價人統一匿名為「匿名潛水者」
- 三種排序切換：最多幫助（預設）/ 最高分 / 最新
- Member 可修改與刪除自己的評價；Admin 可刪除任何評價

## Capabilities

### New Capabilities

- `review-lifecycle`：評價的建立（資格驗證、一課一評）、修改（is_edited 標記 + 舊版備份）、刪除（Member 本人或 Admin）、rating 即時重算
- `review-voting`：Member 登入後對評價投「有幫助」票，可取消；依 `helpful_count` 排序為預設

### Modified Capabilities

（無）

## Impact

**後端**
- 新增 Migration：`reviews`、`review_edits`、`review_votes`
- 新增 Model：`Review`、`ReviewEdit`、`ReviewVote`
- 新增 Controller：`ReviewController`（Member）、`AdminReviewController`（Admin）
- 更新 `routes/api.php`：公開列表、Member 評價 CRUD + 投票、Admin 刪除
- 更新 `DivingOffer` Model：加入 `hasMany Review` 關聯

**前端**
- 新增課程詳情頁評價區塊：星等分布、評價列表、排序切換、「有幫助」按鈕
- 新增 Member 評價表單：完課後可寫評、已評可修改
- 新增 `frontend/src/api/reviewApi.js`
- Admin Panel 新增評價管理頁（刪除問題評論）

**資料庫**
- 三張新資料表，`diving_offers` 現有 `rating` / `reviews` 欄位語意從假資料改為真實計算值
