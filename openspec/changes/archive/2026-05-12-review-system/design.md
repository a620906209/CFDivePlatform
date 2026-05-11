## Context

CFDivePlatform 已完成預約系統，`bookings.status = completed` 是評價資格的天然觸發點。`diving_offers` 已有 `rating`（float）與 `reviews`（int）欄位，目前為假資料，本次將接管這兩個欄位，改由真實評價計算。

## Goals / Non-Goals

**Goals:**
- Member 完課後可留評（1–5 星 + 文字），每門課一次
- 修改評價留下 is_edited 標記與最近一筆舊版備份
- 有幫助投票（Toggle，防重複，不可投自己）
- 公開列表三種排序，評價人匿名

**Non-Goals:**
- 教練回覆評價（未來功能）
- 多維度評分（服務、設備等）
- 評價檢舉/審核流程（Admin 直接刪除即可）
- 分頁（MVP 全量回傳，課程評價數量有限）

## Decisions

### 決策一：資料表結構

```
reviews
  id, diving_offer_id, member_id
  rating (tinyint 1-5)
  comment (text)
  helpful_count (int DEFAULT 0)
  is_edited (boolean DEFAULT false)
  created_at, updated_at
  UNIQUE(member_id, diving_offer_id)
  索引: (diving_offer_id, helpful_count DESC)
       (diving_offer_id, rating DESC)
       (diving_offer_id, created_at DESC)

review_edits
  id, review_id (UNIQUE FK), old_rating, old_comment, edited_at

review_votes
  id, review_id, member_id, created_at
  UNIQUE(review_id, member_id)
```

### 決策二：rating 重算時機與方式

在 ReviewController 的 create / update / destroy 內，與 Review 操作同一 DB transaction：

```php
$stats = Review::where('diving_offer_id', $offerId)
    ->selectRaw('ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as total')
    ->first();

DivingOffer::where('id', $offerId)->update([
    'rating'  => $stats->total > 0 ? $stats->avg_rating : 0,
    'reviews' => $stats->total,
]);
```

**放棄**：Observer 模式 → 同樣是即時，但執行點分散難追蹤；排程重算 → 有延遲。

### 決策三：review_edits 覆蓋策略

`review_edits` 的 `review_id` 為 UNIQUE，每次修改用 `updateOrCreate`：

```php
ReviewEdit::updateOrCreate(
    ['review_id' => $review->id],
    ['old_rating' => $review->rating, 'old_comment' => $review->comment, 'edited_at' => now()]
);
```

**理由**：用戶需求是「知道改過就好」，無需完整歷史，一筆足夠。

### 決策四：評價資格驗證具體邏輯

```php
$eligible = Booking::where('member_id', $memberId)
    ->whereHas('schedule', fn($q) => $q->where('diving_offer_id', $offerId))
    ->where('status', BookingStatus::Completed)
    ->exists();

if (!$eligible) {
    return response()->json(['status' => false, 'message' => '須完成此課程後才能評價'], 403);
}
```

**規則**：有任意一筆 `completed` booking（不限場次）即可評價。已評過同一課程則回傳 422（非 409，因為提供的是「操作不合法」而非「資源衝突」）。

---

### 決策五：有幫助投票 Toggle — **transaction 為強制規範**

`POST /api/reviews/{id}/helpful` 同一端點，**整個操作必須在 DB transaction 內**：

```php
DB::transaction(function () use ($review, $memberId) {
    $vote = ReviewVote::where('review_id', $review->id)
        ->where('member_id', $memberId)->first();

    if ($vote) {
        $vote->delete();
        // 單一 SQL 原子操作，避免 decrement + check 兩次 SQL 的競態風險：
        DB::table('reviews')
            ->where('id', $review->id)
            ->update(['helpful_count' => DB::raw('GREATEST(helpful_count - 1, 0)')]);
    } else {
        ReviewVote::create(['review_id' => $review->id, 'member_id' => $memberId]);
        $review->increment('helpful_count');
    }
});
```

**理由**：`decrement` 後再 `if ($review->helpful_count < 0)` 是兩次 SQL，即使在 transaction 內，高並發下兩筆寫入之間仍可能讀到中間負值。`GREATEST(helpful_count - 1, 0)` 是單一原子 SQL，天然防負。

**理由**：ReviewVote 與 helpful_count 必須原子性同步，transaction 外任一失敗都會造成計數與投票紀錄不一致。這是**強制要求**，非建議。

**放棄**：分開 POST（投票）和 DELETE（取消）端點 → Toggle 在前端更直覺，一個按鈕即可。

---

### 決策六：匿名化方式

回傳 `reviewer_name = '匿名潛水者'`（固定字串），不揭露 member_id。

**理由**：最簡單、最安全。若未來需要識別（如「你評過這門課」），透過 `is_mine` flag 處理，不需暴露身份。

---

### 決策七：has_voted / is_mine 注入規則

列表 API 依登入狀態差異化回傳：

| 欄位 | 未登入 | 已登入 Member |
|------|--------|---------------|
| `reviewer_name` | `匿名潛水者` | `匿名潛水者` |
| `has_voted` | `false`（固定，不省略） | 批次查詢 review_votes 後注入 |
| `is_mine` | **省略**（不出現在 response） | `true` 或 `false` |

`is_mine` 未登入時省略（非 false）的理由：前端可用 `'is_mine' in review` 判斷是否登入，而非 `review.is_mine === true`，語意更清晰。

批次查詢避免 N+1：
```php
$myVotes = $user ? ReviewVote::where('member_id', $user->id)
    ->whereIn('review_id', $reviews->pluck('id'))
    ->pluck('review_id')->flip() : collect();
```

---

### 決策八：Admin 評價列表範圍

`GET /api/admin/reviews` MVP 回傳全量（依 `created_at DESC`），不做分頁或 offer 篩選。

**理由**：Admin Panel 評價管理目的是快速找到問題評論並刪除，全量夠用。若日後評價量大，加 `?offer_id=` 篩選參數即可，不是 breaking change。

---

### 決策九：Admin DELETE 必須觸發 rating 重算

Admin `DELETE /api/admin/reviews/{id}` 與 Member 刪除共用同一個重算邏輯（抽成 private method `recalculateOfferRating($offerId)`），兩個 Controller 都呼叫，確保不遺漏。

```php
private function recalculateOfferRating(int $offerId): void
{
    $stats = Review::where('diving_offer_id', $offerId)
        ->selectRaw('ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as total')
        ->first();

    DivingOffer::where('id', $offerId)->update([
        'rating'  => $stats->total > 0 ? $stats->avg_rating : 0,
        'reviews' => $stats->total,
    ]);
}
```

## 資料表索引

```
reviews:
  idx_offer_helpful   (diving_offer_id, helpful_count DESC)  ← 預設排序
  idx_offer_rating    (diving_offer_id, rating DESC)          ← 高分排序
  idx_offer_newest    (diving_offer_id, created_at DESC)      ← 最新排序
  idx_member_offer    (member_id, diving_offer_id)            ← 資格驗證

review_votes:
  UNIQUE(review_id, member_id)  ← 防重複投票
```

## API 路由總覽

```
公開
  GET  /api/diving-offers/{id}/reviews?sort=helpful|rating|newest

Member (auth:sanctum)
  POST   /api/member/reviews
  PUT    /api/member/reviews/{id}
  DELETE /api/member/reviews/{id}
  POST   /api/reviews/{id}/helpful   ← Toggle，需登入

Admin (auth:sanctum)
  GET    /api/admin/reviews
  DELETE /api/admin/reviews/{id}
```

## Response Schema

### GET /api/diving-offers/{id}/reviews

**`summary.distribution` 計算方式**：每次請求動態 `GROUP BY rating COUNT(*)`，不存入資料表。

```php
$distribution = Review::where('diving_offer_id', $offerId)
    ->selectRaw('rating, COUNT(*) as count')
    ->groupBy('rating')
    ->pluck('count', 'rating');

// 補齊 1–5 全部 key（避免前端處理 undefined）：
$dist = collect([1=>0, 2=>0, 3=>0, 4=>0, 5=>0])
    ->merge($distribution);
```

**理由**：`diving_offers` 只存 `rating`（avg）和 `reviews`（count），分布需動態查詢。不另存欄位，因為每次評價變動都需同步五個計數，維護成本高於查詢成本（評價數量有限）。

```json
{
  "status": true,
  "data": {
    "summary": {
      "average": 4.5,
      "total": 12,
      "distribution": { "5": 7, "4": 3, "3": 1, "2": 1, "1": 0 }
    },
    "reviews": [
      {
        "id": 1,
        "reviewer_name": "匿名潛水者",
        "rating": 5,
        "comment": "課程非常棒！",
        "helpful_count": 8,
        "is_edited": false,
        "created_at": "2026-05-12T10:00:00Z",
        "has_voted": false,
        "is_mine": true        // 僅登入用戶才有此欄位
      }
    ]
  }
}
```

### Error Codes 完整定義

**POST /api/member/reviews（新增評價）**

| 情況 | HTTP | message |
|------|------|---------|
| 未完成課程 | 403 | 須完成此課程後才能評價 |
| 已評過同課程 | 422 | 已評價，如需修改請使用編輯功能 |
| rating 不在 1–5 | 422 | rating 須為 1–5 的整數 |
| comment 為空 | 422 | 評論內容不可為空 |

**PUT /api/member/reviews/{id}（修改評價）**

| 情況 | HTTP | message |
|------|------|---------|
| 評價不存在 | 404 | 找不到此評價 |
| 非本人評價 | 403 | 無權修改此評價 |
| rating 不在 1–5 | 422 | rating 須為 1–5 的整數 |
| comment 為空字串 | 422 | 評論內容不可為空 |

**DELETE /api/member/reviews/{id}（刪除評價）**

| 情況 | HTTP | message |
|------|------|---------|
| 評價不存在 | 404 | 找不到此評價 |
| 非本人評價 | 403 | 無權刪除此評價 |

**POST /api/reviews/{id}/helpful（投票）**

| 情況 | HTTP | message |
|------|------|---------|
| 未登入 | 401 | Unauthenticated |
| 投自己的評價 | 422 | 不可對自己的評價投票 |
| 評價不存在 | 404 | 找不到此評價 |

## Risks / Trade-offs

- **helpful_count 與 review_votes 同步**：已在決策五明確要求 transaction，此風險已關閉
- **全量回傳無分頁**：若單一課程累積大量評價（>200），效能下降。MVP 可接受，日後加 cursor pagination
- **匿名化無法「回溯」**：若未來需要顯示名字，需 migration 補欄位。目前決策鎖定匿名，風險低
