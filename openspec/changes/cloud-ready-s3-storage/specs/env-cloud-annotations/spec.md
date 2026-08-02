## MODIFIED Requirements

### Requirement: .env.example 標示雲端必要變數
`.env.example` SHALL 以行內註解或正確預設值標示以下雲端部署時必須明確設定的變數：
- `FILESYSTEM_DISK`：**預設維持 `local`**（本機開發環境無 S3 相容服務可用）；雲端部署時 SHALL 手動設為 `s3` 並補齊 `AWS_ACCESS_KEY_ID`／`AWS_SECRET_ACCESS_KEY`／`AWS_BUCKET`／`AWS_ENDPOINT`／`AWS_URL`／`AWS_USE_PATH_STYLE_ENDPOINT` 等變數，此時上傳行為 SHALL 實際切換至 S3 相容物件儲存（不再只是文件註解提醒，`public` disk 驅動確實依此變數切換）
- `LOG_CHANNEL`：預設改為 `stderr`（雲端 logging aggregator 標準；原 `stack/daily` 寫檔案不適合雲端）
- `QUEUE_CONNECTION`：預設為 `redis`
- `SESSION_DRIVER`：預設改為 `redis`（取代 `database`，擴容更高效）

#### Scenario: 新環境照 .env.example 初始化 session driver
- **WHEN** 開發者以 `.env.example` 為基礎建立 `.env`，未手動修改 `SESSION_DRIVER`
- **THEN** Laravel session driver 使用 Redis

#### Scenario: 新環境照 .env.example 初始化 log channel
- **WHEN** 開發者以 `.env.example` 為基礎建立 `.env`，未手動修改 `LOG_CHANNEL`
- **THEN** Laravel 日誌寫入 stderr，可透過 `docker compose logs app` 查看

#### Scenario: 新環境照 .env.example 初始化檔案儲存
- **WHEN** 開發者以 `.env.example` 為基礎建立 `.env`，未手動修改 `FILESYSTEM_DISK`
- **THEN** 上傳檔案寫入本機磁碟，不需任何 S3 credentials 即可完整跑起來

#### Scenario: 操作者閱讀 .env.example 進行雲端部署
- **WHEN** 操作者參照 `.env.example` 設定雲端環境的 `.env`
- **THEN** 每個雲端關鍵變數旁有說明，提示預設值在雲端環境的限制與建議替代值，且 `FILESYSTEM_DISK` 的說明明確指出改為 `s3` 後需一併設定哪些 `AWS_*` 變數
