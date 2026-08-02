# file-storage-s3 Specification

## Purpose

使用者上傳檔案（課程圖片、教練證照、聊天室圖片）可透過 `FILESYSTEM_DISK` 環境變數切換存放於本機磁碟或 S3 相容物件儲存，並提供既有檔案的一次性遷移工具。

## Requirements
### Requirement: 上傳檔案可切換為 S3 相容物件儲存
`public` disk SHALL 支援透過 `FILESYSTEM_DISK` 與 `AWS_*` 環境變數切換底層儲存為 S3 相容物件儲存（如 Cloudflare R2），且不需修改任何呼叫 `Storage::disk('public')` 的既有程式碼。

#### Scenario: FILESYSTEM_DISK 設為 s3 時上傳走物件儲存
- **WHEN** `.env` 設定 `FILESYSTEM_DISK=s3` 並提供有效的 `AWS_ACCESS_KEY_ID`／`AWS_SECRET_ACCESS_KEY`／`AWS_BUCKET`／`AWS_ENDPOINT`
- **THEN** 課程圖片上傳（`CourseImageController`）、教練證照上傳、預約聊天室圖片上傳皆寫入該 S3 相容物件儲存，本機 `storage/app/public/` 不再新增檔案

#### Scenario: FILESYSTEM_DISK 設為 local 時維持本機磁碟（開發環境預設）
- **WHEN** `.env` 未設定或設定 `FILESYSTEM_DISK=local`
- **THEN** 上傳行為與現行本機磁碟儲存完全一致，不需任何 S3 credentials

#### Scenario: 顯示已上傳檔案的網址依 disk 驅動自動切換
- **WHEN** 呼叫 `CourseImage`／`ProviderCertification` model 的 `Storage::disk('public')->url(...)`
- **THEN** 回傳的網址依當前 `FILESYSTEM_DISK` 設定，分別指向本機 `/storage/...` 路徑或 S3 相容物件儲存的公開網址

### Requirement: 既有本機檔案可一次性遷移至 S3 相容物件儲存
系統 SHALL 提供一個 artisan command，將 `storage/app/public/` 下既有檔案上傳至當前設定的 S3 相容物件儲存，且不刪除本機原始檔案。

#### Scenario: 執行遷移 command 上傳既有檔案
- **WHEN** 操作者執行 `php artisan storage:migrate-to-s3`
- **THEN** `storage/app/public/` 下所有檔案（不含 `storage:link` 符號連結本身）被上傳至 S3 相容物件儲存的對應相對路徑，且本機檔案保持不變

#### Scenario: 遷移 command 支援 dry-run 預覽
- **WHEN** 操作者執行 `php artisan storage:migrate-to-s3 --dry-run`
- **THEN** command 僅列出將被上傳的檔案清單與總數，不實際執行任何上傳

#### Scenario: 部分檔案上傳失敗不中斷整體流程
- **WHEN** 遷移過程中某個檔案上傳失敗（如網路中斷）
- **THEN** command 記錄該檔案為失敗、繼續處理其餘檔案，結束後印出失敗清單並以非 0 exit code 結束

