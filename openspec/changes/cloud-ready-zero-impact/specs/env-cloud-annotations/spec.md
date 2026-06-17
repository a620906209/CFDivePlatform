## ADDED Requirements

### Requirement: QUEUE_CONNECTION 預設值為 redis
`.env.example` 的 `QUEUE_CONNECTION` SHALL 預設為 `redis`，與 VPS 實際運作設定一致，避免新環境按範本初始化後跑在效能較差的 database queue。

#### Scenario: 新環境照 .env.example 初始化
- **WHEN** 開發者或 CI 以 `.env.example` 為基礎建立 `.env`，未手動修改 `QUEUE_CONNECTION`
- **THEN** Laravel queue driver 使用 Redis，queue worker 正常消費 Redis 佇列中的 job

### Requirement: .env.example 標示雲端必要變數
`.env.example` SHALL 以行內註解或分組說明標示以下雲端部署時必須明確設定的變數，協助操作者識別必填項目：
- `FILESYSTEM_DISK`：雲端應設為 `s3`（目前預設 `local`，雲端會導致檔案在容器重啟後遺失）
- `LOG_CHANNEL` / `LOG_STACK`：雲端建議改為 `stderr` 以接入雲端 logging aggregator
- `QUEUE_CONNECTION`：應為 `redis`
- `SESSION_DRIVER`：雲端建議改為 `redis`

#### Scenario: 操作者閱讀 .env.example 進行雲端部署
- **WHEN** 操作者參照 `.env.example` 設定雲端環境的 `.env`
- **THEN** 每個雲端關鍵變數旁有說明，提示預設值在雲端環境的限制與建議替代值
