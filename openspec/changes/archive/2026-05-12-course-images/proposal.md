## Why

目前課程頁面以 🤿 emoji 作為佔位，缺乏視覺吸引力。課程圖片是潛水平台的核心體驗，直接影響會員瀏覽意願與教練品牌形象。

## What Changes

- 新增 `diving_offers.cover_image` 欄位：課程封面，顯示於課程卡與詳情頁頂部
- 新增 `course_images` 資料表：相簿最多 3 張，顯示於課程詳情頁
- 新增 Provider API：上傳/刪除封面、上傳/刪除相簿圖片
- 更新前端：CourseCard 顯示封面、CourseDetailView 顯示封面 + 相簿、OfferFormView 加入圖片管理 UI
- Docker volume 掛載 `storage/app/public`，圖片跨 build 持久化
- `php artisan storage:link` 整合至 entrypoint，確保 symlink 正確

## Capabilities

### New Capabilities

- `course-image-upload`：Provider 上傳課程封面與相簿圖片（本地 public disk、max 2MB、支援 jpg/jpeg/png/webp）、相簿上限 3 張、刪除圖片同步移除實體檔案

### Modified Capabilities

- `coach-offers-api`：新增圖片上傳/刪除端點，`DivingOffer` 回應加入 `cover_image_url` 與 `images`

## Impact

**後端**
- Migration：`diving_offers` 加 `cover_image`（nullable string）
- Migration：新增 `course_images`（id、diving_offer_id、image_path、sort_order）
- Model：`CourseImage`；`DivingOffer` 加 `hasMany CourseImage`、`cover_image_url` accessor
- Controller：`CourseImageController`（上傳封面、刪除封面、上傳相簿、刪除相簿）
- Route：`/provider/offers/{id}/cover`（POST/DELETE）、`/provider/offers/{id}/images`（POST）、`/provider/images/{id}`（DELETE）

**前端**
- `frontend/src/api/courseImageApi.js`
- `CourseCard.vue`：有封面顯示圖片，無封面顯示漸層佔位
- `CourseDetailView.vue`：頂部大圖（封面）+ 相簿縮圖列
- `OfferFormView.vue`（或新建 `OfferImageManager.vue`）：封面上傳預覽、相簿管理

**Docker**
- `docker-compose.yml` 新增 named volume `storage-data` 掛載 `/var/www/storage/app/public`
- `docker-entrypoint.sh` 加入 `php artisan storage:link --force`
