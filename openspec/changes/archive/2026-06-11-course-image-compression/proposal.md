## Why

課程封面與圖庫圖片目前**原檔直存**（`CourseImageController` 只驗證格式與 2MB 上限，無任何處理），而聊天圖片已有成熟的壓縮管線（`scaleDown(2048) + toJpeg(85)`，`BookingMessageController.php:115`）。後果：

1. 課程列表/詳情頁直接載入未壓縮原圖，頁面重量大、行動網路下體感慢（效能優化計畫 O3.1，`docs/analysis/2026-06-11-performance-optimization-plan.md` §Phase 3）
2. 2MB 上限對手機拍攝原圖（5~10MB）太嚴格，教練必須自行縮圖才能上傳——體驗差且實務上常失敗

## What Changes

- `CourseImageController` 的 `uploadCover` 與 `uploadImage` 套用與聊天圖片一致的壓縮管線：超過 2048px 等比縮小、轉存 JPEG quality 85
- 上傳大小上限 2MB → **10MB**（與聊天圖片一致；反正入庫前會壓縮）
- 壓縮邏輯抽共用 trait `App\Traits\CompressesImages`（聊天與課程兩處重複，依維護性規則抽共用；聊天端改用 trait 留待後續——該處無測試覆蓋，不在本 change 冒險重構）
- 前端課程列表/詳情的 `<img>` 加 `loading="lazy"`
- 更新 `CourseImageTest` 既有案例（儲存副檔名、大小上限）並新增壓縮行為測試

## Capabilities

### Modified Capabilities

- `course-image-upload`：上傳大小上限 2MB→10MB；新增「伺服器端壓縮」requirement（縮圖、JPEG 轉存、尺寸上限 2048px）

## Impact

- 影響檔案：`CourseImageController`、新增 `app/Traits/CompressesImages.php`、`tests/Feature/CourseImageTest.php`、前端 `CoursesView.vue` / `CourseDetailView.vue`
- 行為變更：儲存檔案一律為 `.jpg`（PNG 透明區域轉為實色背景——課程照片皆為實拍照，可接受，見 design）；既有已上傳的舊圖不處理（數量少，自然汰換）
- 依賴：Intervention Image v3 + GD 已安裝（聊天功能在用）；測試需在容器內跑（本機 PHP 缺 GD）
