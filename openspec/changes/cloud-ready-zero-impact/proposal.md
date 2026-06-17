## Why

現有 Docker Compose 設定存在若干與雲端部署不相容的配置（開發工具混入正式服務、healthcheck 無法真實反映 app 狀態、queue 預設值不一致），在不動任何 PHP 程式碼、不重建 image、不影響 VPS 現有運作的前提下，先修正這四個零風險項目，作為雲端就緒路線圖的第一步。

## What Changes

- **`.env.example`**：`QUEUE_CONNECTION` 預設值從 `database` 改為 `redis`；補充 `FILESYSTEM_DISK` 與 log 相關變數的說明與分組，標記哪些是雲端部署時必須設定的項目
- **`docker-compose.yml`**：app service healthcheck 從 `php -v` 改為呼叫 `/health` 端點；移除 `phpmyadmin` service
- **新增 `compose.override.yml`**：將 `phpmyadmin`、`mailpit` 放入 override 檔，VPS/開發環境自動載入，正式雲端部署時只用 `-f docker-compose.yml` 即可排除

## Capabilities

### New Capabilities

- `compose-cloud-baseline`：Docker Compose 雲端基準設定——healthcheck 使用真實端點、正式 compose 不含開發工具、dev-only 服務透過 override 隔離
- `env-cloud-annotations`：`.env.example` 補充雲端部署必要環境變數的說明與正確預設值

### Modified Capabilities

（本次不修改任何既有 spec 的需求行為）

## Impact

- **`docker-compose.yml`**：healthcheck 指令、移除 phpmyadmin service
- **新增 `compose.override.yml`**：phpmyadmin、mailpit service 定義搬移至此
- **`.env.example`**：QUEUE_CONNECTION 預設值、新增說明行
- **不影響**：所有 PHP 程式碼、VPS `.env`、image 內容、Scheduler、Session、Log
