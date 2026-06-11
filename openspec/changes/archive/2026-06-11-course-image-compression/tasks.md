## 1. 共用壓縮 trait

- [x] 1.1 新增 `app/Traits/CompressesImages.php`：`compressToJpeg(UploadedFile $file, string $directory): string`——Intervention v3 讀取 → 長邊 >2048 時 `scaleDown(2048, 2048)` → `toJpeg(quality: 85)` → `Storage::disk('public')->put("{$directory}/{uuid}.jpg", ...)`，回傳相對路徑

## 2. CourseImageController 套用

- [x] 2.1 `validateImage()`：`max:2048` → `max:10240`
- [x] 2.2 `uploadCover()`：`store()` 改用 `compressToJpeg($file, "offers/{$offerId}/cover")`
- [x] 2.3 `uploadImage()`：同上，目錄 `offers/{$offerId}/gallery`

## 3. 前端 lazy loading

- [x] 3.1 `frontend/src/views/CoursesView.vue` 與 `CourseDetailView.vue` 的課程圖 `<img>` 加 `loading="lazy"`（其他列表元件若有課程圖一併處理）

## 4. 測試

- [x] 4.1 更新 `CourseImageTest`：大小上限案例改 >10MB 422、新增 5MB 通過案例、儲存路徑斷言改 `.jpg`
- [x] 4.2 新增壓縮行為案例：上傳 3000×3000 fake 圖 → 讀回實際尺寸 ≤2048；上傳 PNG → 儲存為 `.jpg`
- [x] 4.3 容器內 `php artisan test` 全綠（基準：151 passed；本機缺 GD 不可作準）

## 5. 規格同步

- [x] 5.1 將本 change 的 specs 增量套用至主規格 `openspec/specs/course-image-upload/spec.md`
