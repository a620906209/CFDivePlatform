## 1. 依賴與設定 [後端]

- [x] 1.1 [後端] `composer require league/flysystem-aws-s3-v3`
- [x] 1.2 [後端] `config/filesystems.php`：`public` disk 改為 D1 設計（`driver` 吃 `env('FILESYSTEM_DISK', 'local')`，補上 `key`/`secret`/`region`/`bucket`/`endpoint`/`use_path_style_endpoint`），並加註解說明此專案對 `FILESYSTEM_DISK` 的特殊用法
- [x] 1.3 [後端] `.env.example`：`FILESYSTEM_DISK` 保留 `local` 預設，更新行內註解；補齊 `AWS_ENDPOINT`、`AWS_URL`、`AWS_USE_PATH_STYLE_ENDPOINT` 三個變數與說明

## 2. 遷移 Command [後端]

- [x] 2.1 [後端] 新增 `app/Console/Commands/MigrateStorageToS3.php`（`storage:migrate-to-s3` 指令），掃描 `storage/app/public/` 並上傳至 `Storage::disk('s3')`，保留相對路徑，排除 `storage:link` 符號連結
- [x] 2.2 [後端] 實作 `--dry-run` 參數：只列出檔案清單與總數，不執行上傳
- [x] 2.3 [後端] 上傳後用 `Storage::disk('s3')->exists()` 驗證，失敗檔案收集成清單並於結尾印出，command 以非 0 exit code 結束（若有失敗）
- [x] 2.4 [後端] 確認 command 不刪除任何本機檔案（無論成功或失敗）

## 3. 測試 [後端]

- [x] 3.1 [後端] `MigrateStorageToS3` command 測試：正常上傳、`--dry-run` 不執行上傳、部分失敗時正確回報且不中斷
- [x] 3.2 [後端] `config/filesystems.php` 改動後，既有 `CourseImageTest`／`ProviderCertification` 相關測試在 `FILESYSTEM_DISK=local`（預設）下全數通過，確認零回歸
- [x] 3.3 [後端] 新增測試驗證 `FILESYSTEM_DISK=local` 與未設定時行為一致（呼叫 `Storage::disk('public')` 不需任何 AWS credentials 即可運作）

## 4. R2 環境準備 [整合測試]

- [ ] 4.1 [整合測試] 於 Cloudflare R2 建立 bucket，設定 public access（或綁自訂網域）
- [ ] 4.2 [整合測試] 取得 `AWS_ACCESS_KEY_ID`／`AWS_SECRET_ACCESS_KEY`／`AWS_BUCKET`／`AWS_ENDPOINT` 等 R2 credentials
- [ ] 4.3 [整合測試] 用 `curl` 直接打 R2 網址驗證至少一個手動上傳的測試檔案可公開存取（403 排查必須在此步驟完成，不帶進生產切換）

## 5. VPS 部署與遷移 [整合測試]

- [ ] 5.1 [整合測試] SSH 進 VPS，`.env` 補齊 R2 credentials，`FILESYSTEM_DISK` 暫時保留 `local`
- [ ] 5.2 [整合測試] `docker compose up -d --build`（帶入新 composer 依賴）
- [ ] 5.3 [整合測試] 執行 `docker compose exec app php artisan storage:migrate-to-s3 --dry-run`，確認檔案數量與清單合理
- [ ] 5.4 [整合測試] 執行 `docker compose exec app php artisan storage:migrate-to-s3`，記錄失敗清單（若有）並重跑補上傳
- [ ] 5.5 [整合測試] 抽查數個已遷移檔案的 R2 直連網址，確認可正常開啟

## 6. 切換與驗證 [整合測試]

- [ ] 6.1 [整合測試] VPS `.env` 將 `FILESYSTEM_DISK` 改為 `s3`，`docker compose exec app php artisan config:clear`
- [ ] 6.2 [整合測試] 手動驗證課程圖片上傳／刪除／顯示三個流程
- [ ] 6.3 [整合測試] 手動驗證教練證照上傳／顯示流程
- [ ] 6.4 [整合測試] 手動驗證預約聊天室圖片上傳／顯示流程
- [ ] 6.5 [整合測試] 確認 `storage/app/public/` 本機檔案仍完整保留（作為遷移後的備份，暫不刪除）

## 7. OpenSpec 同步

- [ ] 7.1 更新 `openspec/specs/env-cloud-annotations/spec.md`（套用本次 delta）
- [ ] 7.2 新增 `openspec/specs/file-storage-s3/spec.md`
- [ ] 7.3 完成後歸檔本 change（`openspec archive cloud-ready-s3-storage`）
