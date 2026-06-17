## Why

P0 零影響改動完成後，P1 處理三個需要維護窗口但改完後功能完全等效的雲端就緒項目：Session 存 Redis 取代 MySQL（擴容必要）、Log 走 stdout 取代檔案（雲端 logging 標準）、Scheduler 抽成獨立 container 取代 app 容器內 cron（水平擴容不重複執行）。

## What Changes

- **`.env.example`**：`SESSION_DRIVER` 預設改 `redis`；`LOG_CHANNEL` 預設改 `stderr`
- **`Dockerfile`**：移除 `cron` 安裝、移除 scheduler cron job 寫入
- **`docker/php/docker-entrypoint.sh`**：移除 `service cron start`
- **`docker-compose.yml`**：新增 `scheduler` service（`php artisan schedule:work`）
- **`docs/STARTUP.md`**：更新服務清單，說明 scheduler container 與 log 查看方式
- **VPS `.env`**：需在維護窗口更新 `SESSION_DRIVER=redis` 與 `LOG_CHANNEL=stderr`（不進版控）

## Capabilities

### New Capabilities

- `scheduler-container`：Scheduler 以獨立 container 方式執行，不依賴 app container 內的 cron daemon

### Modified Capabilities

- `env-cloud-annotations`：補充 `SESSION_DRIVER` 與 `LOG_CHANNEL` 的正確雲端預設值（原 spec 已標注說明，現在升級為實際預設值）

## Impact

- **`Dockerfile`**：移除 cron 相關 2 行，需 rebuild image
- **`docker/php/docker-entrypoint.sh`**：移除 1 行
- **`docker-compose.yml`**：新增 `scheduler` service
- **`.env.example`**：2 個預設值變動
- **不影響**：所有 PHP 業務邏輯、API 行為、前端、資料庫 schema
- **部署影響**：Session 失效一次（用戶登出）、需 rebuild app image
