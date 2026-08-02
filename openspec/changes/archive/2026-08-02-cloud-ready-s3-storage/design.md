## Context

現況：
- 上傳功能有 6 個呼叫點，全部寫死 `Storage::disk('public')`（`CourseImageController`、`CompressesImages` trait、`CourseImage`／`ProviderCertification` model 的 `Storage::disk('public')->url(...)`、`BookingMessageController`）
- `config/filesystems.php` 的 `default` 設定（`FILESYSTEM_DISK` env）**沒有任何程式碼讀取**，因為所有呼叫點都直接指名 `'public'`，不吃 default disk
- `public` disk 目前寫死 `driver => local`，`root => storage_path('app/public')`
- `s3` disk 設定區塊已存在（Laravel 骨架預設），但未安裝 `league/flysystem-aws-s3-v3`，且應用程式從未使用這個 disk key
- 本機開發環境（docker-compose）沒有任何 S3 相容服務（無 MinIO container）

## Goals / Non-Goals

**Goals:**
- 上傳檔案（課程圖片、教練證照、聊天室圖片）改存 S3 相容物件儲存，實現 container 無狀態化，容器可自由重建/複製/水平擴容而不遺失使用者資料
- 零改動 6 個既有呼叫點程式碼
- 本機開發環境不受影響，不需要開發者額外申請 S3 帳號才能跑起來
- 既有已上傳檔案完整遷移，切換後所有舊圖片網址仍可正常顯示

**Non-Goals:**
- 不處理 P0 另一項待辦「移除 bind mount / code 烘進 image」
- 不引入 MinIO 或任何本機 S3 模擬服務——開發環境維持 local disk
- 不重構 6 個呼叫點的程式碼結構（即使 `Storage::disk('public')` 這種寫死字串不是最佳實踐，此次不動，避免範圍擴大）
- 不處理 `storage/logs/` 或其他非使用者上傳內容的磁碟佔用（那是 log rotation 的範疇，非本次目標）

## Decisions

### D1：讓 `public` disk 的 `driver` 本身吃 env，而非新增第二顆 disk

`config/filesystems.php` 的 `public` disk 改成：

```php
'public' => [
    'driver' => env('FILESYSTEM_DISK', 'local'),
    'root' => storage_path('app/public'),
    'url' => env('AWS_URL') ?: env('APP_URL').'/storage',
    'visibility' => 'public',
    'throw' => false,
    // S3 專用欄位，driver=local 時被忽略，不影響本機開發
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
],
```

**為什麼不是新增一顆 `s3` disk 再改 6 個呼叫點指過去**：呼叫點越少改動風險越低；`FILESYSTEM_DISK` 這個 env 名稱原本就是為了「決定用什麼儲存」而存在（`env-cloud-annotations` spec 也是這樣描述的語意），只是它原本控制的是沒人用的 `default` key。把它直接接到 `public` disk 的 `driver`，是重新賦予這個既有 env 變數「做它原本應該做的事」，不是新造一個變數。

**取捨**：這是刻意偏離 Laravel 慣例（`FILESYSTEM_DISK` 官方語意是選預設 disk，不是切換單一 disk 的 driver）。因為程式碼現狀已經把 `public` 寫死，順著現狀改動 blast radius 最小；若未來重構掉 6 個寫死呼叫點，屆時可以再拆回標準的雙 disk 寫法。

### D2：本機開發維持 `local`，`.env.example` 預設不動

`.env.example` 的 `FILESYSTEM_DISK=local` 保留不變（只更新註解文字），開發者 clone 專案照舊直接動。VPS 的 `.env`（不進版控）手動改成 `FILESYSTEM_DISK=s3` + 補齊 `AWS_*` credentials，跟 `cloud-ready-p1` 階段 Session/Log 的做法一致（機敏值不走 CI 自動化，一次性手動 SSH 改）。

### D3：S3 相容服務選 Cloudflare R2

理由：免費額度（10GB 儲存 + 無出口流量費）對目前用量綽綽有餘；API 相容 S3，`league/flysystem-aws-s3-v3` 原生支援（透過 `AWS_ENDPOINT` + `AWS_USE_PATH_STYLE_ENDPOINT=true` 指向 R2 endpoint）。非鎖定 R2——設計上任何 S3 相容服務（AWS S3、MinIO 等）都能用同一套 env 變數切換，不影響 code。

**取捨**：R2 bucket 預設不開放公開讀取，需要在 R2 後台額外設定 public access 或綁自訂網域，否則圖片網址會回 403。這是外部平台設定，不是 code 範疇，但屬於本次上線前必須確認的步驟。

### D4：既有檔案遷移用一次性 artisan command，不自動刪除本機檔案

新增 `app/Console/Commands/MigrateStorageToS3.php`：
- 掃描 `storage/app/public/` 底下所有檔案（排除 `storage:link` 產生的符號連結本身）
- 逐一用 `Storage::disk('s3')->put()` 上傳到 S3/R2，保留原本相對路徑（DB 裡 `image_path` 欄位值不用改，因為路徑結構不變）
- 上傳後用 `Storage::disk('s3')->exists()` 驗證，失敗的路徑列表印出來，不中斷整個流程（方便重跑補上傳失敗的部分）
- 支援 `--dry-run` 只列出會上傳的檔案數量與清單，不實際執行
- **不刪除本機檔案**——遷移完成、人工確認 S3 上圖片都能正常顯示後，本機檔案清理是後續手動動作，不在這個 command 的職責內（避免遷移腳本本身變成一個刪檔風險點）

## Risks / Trade-offs

- **[Risk] R2 bucket 未開放公開讀取，切換後圖片全部 403/404**
  → **Mitigation**：上線前先用 `curl` 直接打 R2 網址驗證至少一張測試圖可公開存取，再切 `FILESYSTEM_DISK=s3`

- **[Risk] 遷移腳本執行中網路中斷，部分檔案未上傳成功，但沒人發現**
  → **Mitigation**：command 明確印出失敗清單並回傳非 0 exit code；上線前先跑 `--dry-run` 確認檔案總數，遷移後跑一次全量 `exists()` 檢查

- **[Risk] `env('FILESYSTEM_DISK')` 語意被重新定義，未來新開發者看 Laravel 官方文件會誤解**
  → **Mitigation**：在 `config/filesystems.php` 加註解說明此專案的特殊用法（此為文件層級緩解，非 code 邏輯風險）

- **[Risk] Composer 新依賴需要 rebuild image，VPS 有短暫停機窗口**
  → **Mitigation**：沿用 `cloud-ready-p1` 已驗證過的 `docker compose up -d --build` 流程，排低流量時段

## Migration Plan

**程式碼（PR 合併前）：**
1. `composer require league/flysystem-aws-s3-v3`
2. `config/filesystems.php`：`public` disk 改為 D1 的設定
3. `.env.example`：補齊 `AWS_ENDPOINT`／`AWS_URL`／`AWS_USE_PATH_STYLE_ENDPOINT` 說明
4. 新增 `MigrateStorageToS3` artisan command + 測試

**VPS 維護窗口（PR merge + CI/CD 完成後）：**
```bash
# 1. R2 後台先建好 bucket，設定 public access，取得 endpoint/bucket 資訊
# 2. SSH 進 VPS，更新 .env（不進版控）
#    AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY / AWS_DEFAULT_REGION=auto
#    AWS_BUCKET / AWS_ENDPOINT / AWS_URL / AWS_USE_PATH_STYLE_ENDPOINT=true
#    FILESYSTEM_DISK 暫時保留 local（先不切）

# 3. Rebuild（帶入新 composer 依賴）
docker compose up -d --build

# 4. 用 s3 disk 跑遷移（此時 FILESYSTEM_DISK 仍是 local，遷移用另一個 artisan 參數指定目標 disk）
docker compose exec app php artisan storage:migrate-to-s3 --dry-run
docker compose exec app php artisan storage:migrate-to-s3

# 5. 驗證幾張圖片的 R2 直連網址可正常開啟後，才切換
sed -i 's/FILESYSTEM_DISK=local/FILESYSTEM_DISK=s3/' .env
docker compose exec app php artisan config:clear

# 6. 實際跑一次上傳/刪除/顯示（三個功能各測一次）
```

## Open Questions

- R2 帳號是否已申請？需要 Hank 先建好 bucket 才能進入 VPS 維護窗口步驟。
- 是否綁自訂網域到 R2（例如 `cdn.hank-space.com`）取代 R2 預設網域？影響 `AWS_URL` 設定值，建議上線前確認。
