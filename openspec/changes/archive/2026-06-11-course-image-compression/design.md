# Design — course-image-compression

## D1：壓縮參數與聊天管線完全一致

`scaleDown(2048×2048，僅縮不放大) + toJpeg(quality: 85)`。理由：聊天圖片已用此參數運行無客訴，平台內影像品質一致；2048px 足夠 Retina 全螢幕顯示，quality 85 是檔案大小與畫質的常見甜蜜點（5MB 手機照通常壓到 300~600KB）。

不做多尺寸縮圖（列表 600px 版本）：Simplicity First——先用單一尺寸 + 前端 lazy load 解決主要痛點，若日後列表頁仍慢再開 change 加 responsive 尺寸。

## D2：共用邏輯抽 trait、聊天端暫不重構

壓縮邏輯在聊天與課程兩處重複，抽 `App\Traits\CompressesImages::compressToJpeg(UploadedFile, string $directory): string`（回傳儲存路徑）。本 change 只讓 `CourseImageController` 使用；`BookingMessageController` 改用 trait 屬行為等價重構，但該處**無測試覆蓋**，依 Rule 3（surgical）不在本 change 動，列為後續清理項。

選 trait 而非 Service class：專案已有 `app/Traits/` 慣例、無 `app/Services/`；單一方法的無狀態工具用 trait 侵入最小。

## D3：一律轉存 .jpg 的取捨

- PNG 透明 → GD toJpeg 轉為實色背景。課程圖片皆為實拍照片，透明場景不存在，可接受
- 副檔名統一 `.jpg`：儲存路徑由 `store()` 隨機檔名改為 `Storage::put` + uuid 檔名（與聊天一致），舊圖路徑不受影響（DB 存相對路徑，URL accessor 不變）
- 既有舊圖不回溯處理：數量少且會隨教練更新自然汰換；回溯腳本是過度工程

## D4：驗證上限 10MB 的位置

維持 Laravel validation `max:10240`（與聊天一致）。壓縮在 validation 之後、儲存之前，記憶體峰值由 `memory_limit=512M`（local.ini）涵蓋——10MB JPEG 解碼後約 2048×2048×4bytes ≈ 16MB 級別，安全。

## D5：測試策略

既有 `CourseImageTest` 14 案例的調整面：
- 「upload cover too large」：5MB → 改為 >10MB 才 422；新增 5MB 通過案例
- 路徑斷言：`offers/{id}/cover/` 目錄不變，副檔名改 `.jpg`
- 新增：>2048px 的 fake 圖上傳後實際尺寸 ≤2048（用 Intervention 讀回驗證）
- 測試必須在容器內跑（本機 laragon PHP 缺 GD）
