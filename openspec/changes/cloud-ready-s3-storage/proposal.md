## Why

作為實踐雲原生架構的一環，延續 P1 階段（Session/Log/Scheduler 雲端化）的方向，將使用者上傳檔案（課程圖片、教練證照、預約聊天室圖片）改為 S3 相容物件儲存，是 container 無狀態化的標準作法——容器可以隨時被重建、複製、水平擴容，而不必依賴本機磁碟保留使用者資料。`env-cloud-annotations` spec 已標注 `FILESYSTEM_DISK` 雲端應設為 `s3`，但目前只是註解提醒，尚未實際遷移——`.env.example` 仍預設 `local`，`public` disk 驅動也還是本機磁碟。這是 cloud-ready 路線圖 P0 剩餘兩項之一（另一項為移除 bind mount，不在本次範圍）。

## What Changes

- **`composer.json`**：新增 `league/flysystem-aws-s3-v3`（Laravel 官方 S3 驅動套件，目前未安裝）
- **`config/filesystems.php`**：`public` disk 的 `driver` 從 `local` 改為 `s3` 相容設定（**disk key 名稱維持 `public` 不變**，因為 6 個既有呼叫點都寫死 `Storage::disk('public')`，此法零程式碼改動）
- **`.env.example`**：`FILESYSTEM_DISK` **預設維持 `local`**（本機開發環境沒有 S3 相容服務可用，維持 dev 友善），補上 `AWS_ENDPOINT`、`AWS_URL`、`AWS_USE_PATH_STYLE_ENDPOINT` 等 R2 相容欄位與「雲端環境請設為 s3」的說明註解
- **新增一次性遷移 artisan command**：把 `storage/app/public/` 現有檔案（課程圖片、教練證照、聊天室圖片）上傳到 S3/R2，避免切換後舊圖 404
- **VPS `.env`**：手動新增 AWS/R2 credentials（機敏值，不進版控）
- **驗證**：上傳、刪除、顯示三個流程在課程圖片、教練證照、預約聊天室圖片三個既有功能上皆需重新驗證

## Capabilities

### New Capabilities
- `file-storage-s3`：使用者上傳檔案（課程圖片、教練證照、聊天室圖片）存放於 S3 相容物件儲存，而非 container 本地磁碟；container 重建/水平擴容時檔案不遺失

### Modified Capabilities
- `env-cloud-annotations`：`FILESYSTEM_DISK` 需求從「標注建議值」升級為「實際預設值」，比照 P1 階段 `SESSION_DRIVER`／`LOG_CHANNEL` 已完成的模式

## Impact

- **`config/filesystems.php`**：`public` disk 驅動設定
- **`composer.json` / `composer.lock`**：新增 S3 驅動依賴
- **`.env.example`**：新增/調整 AWS_* 相關變數與說明
- **新增檔案**：一次性遷移 command（`app/Console/Commands/`）
- **VPS `.env`**：需手動補齊 credentials（不進版控）
- **外部依賴（新增）**：需要一組 S3 相容物件儲存帳號（建議 Cloudflare R2，免費額度足夠現行用量）
- **不影響**：`CourseImageController`、`CompressesImages` trait、`CourseImage`／`ProviderCertification` model、`BookingMessageController` 這 6 個呼叫點程式碼本身（disk 名稱不變）；資料庫 schema；前端
- **部署影響**：需 rebuild app image（新 composer 依賴）；遷移舊檔案時有短暫視窗新舊圖網址不一致，建議排低流量時段
