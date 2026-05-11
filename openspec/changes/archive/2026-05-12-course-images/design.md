## Context

CFDivePlatform 課程目前無圖片欄位。Laravel 的 `public` disk（`storage/app/public`）透過 `storage:link` symlink 至 `public/storage`，讓外部可直接存取。Docker 部署需要 named volume 掛載 storage 目錄，確保圖片跨 build 持久化。

## Goals / Non-Goals

**Goals:**
- Provider 可上傳課程封面（1 張）與相簿（最多 3 張）
- 圖片存於本地 public disk，URL 可直接用於 `<img src>`
- Docker volume 持久化，重建容器不丟圖
- `FILESYSTEM_DISK=public` 從 env 控制，未來切換 S3 只改設定

**Non-Goals:**
- 圖片後端處理（resize、WebP 轉換）
- S3 / CDN 整合（留給未來）
- 管理員上傳課程圖片（僅 Provider 可上傳自己的課程）
- 圖片排序拖拉（sort_order 按上傳順序自動遞增）

## Decisions

### 決策一：資料表設計

**`diving_offers` 新增欄位：**
```
cover_image  varchar(500) nullable   ← 儲存相對路徑，如 offers/7/cover/abc.jpg
```

**新增 `course_images` 資料表：**
```
id                bigint PK
diving_offer_id   bigint FK → diving_offers (cascade)
image_path        varchar(500)         ← 相對路徑
sort_order        unsignedSmallInt DEFAULT 0
created_at        timestamp

索引：(diving_offer_id, sort_order)
```

### 決策二：URL 產生方式

`cover_image_url` accessor 與相簿 `url` 統一透過 `Storage::url($path)` 產生：

```php
// DivingOffer Model accessor
public function getCoverImageUrlAttribute(): ?string
{
    return $this->cover_image
        ? Storage::disk('public')->url($this->cover_image)
        : null;
}
```

**理由**：`Storage::url()` 自動對應 `APP_URL`，未來切換 S3 disk 後 URL 自動改為 S3 endpoint，零修改。

### 決策三：檔案儲存路徑

```
storage/app/public/
  offers/
    {offer_id}/
      cover/
        {uuid}.jpg        ← 封面（只會有一個）
      gallery/
        {uuid}.jpg        ← 相簿圖片（最多 3 個）
```

UUID 檔名防止猜測，覆蓋封面時先刪舊 UUID 再存新檔。

### 決策四：封面覆蓋策略

```php
if ($offer->cover_image) {
    Storage::disk('public')->delete($offer->cover_image);
}
$path = $request->file('image')->store("offers/{$offer->id}/cover", 'public');
$offer->update(['cover_image' => $path]);
```

不保留歷史封面，節省磁碟空間。

### 決策五：Docker 持久化（Bind Mount）

`app` 與 `nginx` 容器都已掛載 `./:/var/www` bind mount，圖片存至 `./storage/app/public/`，兩個容器透過同一份 host 目錄共享。

**不使用 named volume** 的原因：`nginx` 容器需要能直接讀取圖片（Laravel 只寫檔，Nginx 才是實際提供靜態檔的服務）。若只在 `app` 掛 named volume，Nginx 透過 bind mount 找不到該 volume 的檔案，導致圖片 404。Bind mount 對兩個容器一致，是最簡單的解法。

`docker-entrypoint.sh` 加入 `php artisan storage:link --force`，每次啟動確保 symlink 正確指向 `./storage/app/public/`。

### 決策六：課程刪除時的孤兒檔案清理

**選擇**：在 `DivingOffer` Model 加 `static::deleting()` observer，刪除整個課程目錄：

```php
static::deleting(function ($offer) {
    Storage::disk('public')->deleteDirectory("offers/{$offer->id}");
});
```

**理由**：`course_images` cascade 只清 DB 紀錄，實體檔案殘留。若課程反覆建刪，磁碟累積孤兒目錄。一行程式碼解決，不算 over-engineering。`deleteDirectory` 目錄不存在時不報錯，安全。

**放棄**：排程清理孤兒檔 → 有時間差，且需額外邏輯比對 DB 與 storage。

---

### 決策七：sort_order 不連續為預期行為

刪除中間相簿圖片後再新增，`sort_order = MAX(sort_order) + 1`，序號會不連續（如：1, 3 而非 1, 2）。

**決定**：MVP 接受不連續。前端依 `sort_order ASC` 排序顯示即可，不需重新排號。

**理由**：重新排號需要 UPDATE 多筆紀錄，複雜度不值得（相簿最多 3 張，視覺影響極小）。

---

### 決策八：course_images 的 created_at 自動填入

Migration 使用 `$table->timestamp('created_at')->useCurrent()`，讓 DB 自動填入，不依賴 PHP 層。

Model 設定：
```php
public $timestamps = false;
const CREATED_AT = 'created_at';  // 告知 Eloquent 此欄位存在（用於排序）
```

**理由**：`useCurrent()` 與 `review_votes` 的做法一致，無需手動傳入 `created_at`。

---

### 決策九：`FILESYSTEM_DISK` 設定

`.env` 的 `FILESYSTEM_DISK=local` 改為 `public`，確保 `Storage::disk()` 預設指向公開路徑。`CourseImageController` 明確指定 `disk('public')`，不依賴預設值，避免混淆。

## API 路由總覽

```
Provider (auth:sanctum)
  POST   /api/provider/offers/{id}/cover    ← 上傳/覆蓋封面
  DELETE /api/provider/offers/{id}/cover    ← 刪除封面
  POST   /api/provider/offers/{id}/images   ← 上傳相簿圖片（最多 3 張）
  DELETE /api/provider/images/{imageId}     ← 刪除特定相簿圖片
```

## Risks / Trade-offs

- **本地 disk 無法多機部署**：目前單機 Docker 可接受；未來水平擴展必須改 S3，設計已預留切換彈性（`Storage::disk('public')`）
- **檔案孤兒**：若刪除課程（`DivingOffer`）時，`course_images` cascade 刪除 DB 紀錄，但實體檔案不會自動清除。MVP 可接受（磁碟空間有限時手動清理）
- **storage:link 在 Docker 重啟時**：每次 entrypoint 執行 `storage:link --force` 覆蓋重建，確保 symlink 正確
