## Why

目前 `cfdive-platform` image 是空殼——`Dockerfile` 沒有 `COPY . /var/www`，程式碼完全靠 `docker-compose.yml` 對 app、nginx、scheduler、queue-worker、reverb 五個服務的 `./:/var/www` bind mount 從主機借過去。這代表 image 本身不含可執行的程式碼，無法搬到另一台機器獨立運作，也不是可版本化、可回溯的部署單位。P1（log 改 stderr、session 改 Redis）與 `file-storage-s3`（上傳檔案可切到 S3）已經拆掉了「container 必須綁死同一台主機磁碟」的兩個主要理由，現在是把程式碼真正烘進 image、讓 container 變成自足部署單位的時機，也是未來水平擴容的前提。

## What Changes

- **Dockerfile 改為 multi-stage**：`app` 階段（`php:8.2-fpm`）統一策略為「先 COPY composer manifests → `composer install --no-scripts` → COPY 全專案 → 補跑 `composer dump-autoload --optimize`（不重複加 `--no-dev`，該旗標已在 install 階段決定 vendor 內容）與 `php artisan package:discover --ansi`（`--no-scripts` 跳過的 composer hook 手動補上）→ 執行 `ln -sfn ../storage/app/public public/storage` 建立符號連結」；新增 `web` 階段（`nginx:alpine`）以 `COPY --from=app /var/www/public /var/www/public` 取得 Laravel 的 `public/` 目錄，並**再次獨立執行**同一條 `ln -sfn` 指令建立自己的符號連結（不依賴 `COPY --from` 隱性帶過去，兩階段各自明確宣告）。新增 `.dockerignore` 排除 `vendor/`、`node_modules/`、`.env`、`storage/app/public/*`，避免機敏資訊或本機殘留檔案被烘進 image——這是本次的正確性要求，不是可選優化。
- **docker-compose.yml**：移除 app、nginx、scheduler、queue-worker、reverb 五個服務的 `./:/var/www` bind mount；`nginx` 服務改從 `image: nginx:alpine`（直接拉取）改為本地 build（`target: web`，image 命名 `cfdive-nginx-web`）取得程式碼，不再依賴任何執行期共享機制。**BREAKING**：改 code 不再瞬間生效，必須重新 build image 才能部署新版本。
- **上傳檔案（storage）處理方式**：本次變更**不強制**先切換到 S3——決定改用具名 volume `storage-app-public`（已定名），掛載至 `app` 與 `nginx` 服務容器內的絕對路徑 `/var/www/storage/app/public`，讓上傳檔案在 image 重建後仍保留；待日後正式切到 `FILESYSTEM_DISK=s3` 後，此 volume 可再移除（留給後續變更處理，不在本次範圍）。
- **`.gitea/workflows/deploy.yml`**：部署流程從「`git reset --hard` 改主機檔案 → `docker compose exec app composer install`」改為「`git reset --hard` → `docker compose build` → `docker compose up -d`」，並成為 migration／`config:cache`／`route:cache`／`view:cache`／`event:cache`／`l5-swagger:generate` 這些「部署一次新版本」動作的**唯一**執行位置。**BREAKING**：部署時間變長（每次都要重建 image），不再有「改 code 幾乎瞬間生效」的能力。
- **`.env` 傳遞方式**：拿掉 bind mount 後 `.env` 不能再依賴主機檔案直接出現在容器裡，**只在 `app`、`scheduler`、`queue-worker`、`reverb` 四個 Laravel runtime 服務**改用 YAML list 格式的 `env_file:`（`env_file:\n  - .env`）指向主機上的 `.env` 路徑——`env_file:` 是把檔案內容以環境變數形式注入容器程序，**不會**在容器內生成一份實體 `.env` 檔案。`nginx` 服務**不**加 `env_file:`，它不需要讀取任何 `.env` 內容，避免不必要的機敏資訊暴露。
- **`docker-entrypoint.sh`**：移除「composer.lock 內容比對後才 composer install」「.env 不存在則從 .env.example 複製 + key:generate」「強制改寫 DB_HOST」三段假設容器內是 bind mount 主機目錄的邏輯；同時**移除 migration／cache clear／swagger 生成**（改由 deploy.yml 統一負責，避免與 deploy.yml 職責重疊、也避免 app/scheduler/queue-worker 三個服務各自啟動時重複跑）與**移除 `storage:link`**（symlink 已在 image build 階段建立，執行期不需要）。entrypoint 最終只保留目錄與權限設定這項啟動必要工作。

## Capabilities

### New Capabilities
- `image-baked-deployment`：涵蓋 image 建置時內嵌程式碼與 vendor、compose 移除 bind mount、部署流程改為 build-then-up、`.env` 改用 env_file 傳遞、entrypoint 職責調整後的完整行為規格。

### Modified Capabilities
- `scheduler-container`：現有 requirement「Scheduler 以獨立 container 執行」隱含透過 bind mount 取得程式碼，需更新為透過 image 內建程式碼執行，不再掛 `./:/var/www`。

## Impact

- **受影響檔案**：`Dockerfile`（改為 multi-stage：`app` + `web`）、`.dockerignore`（新增，含 `public/storage` 排除本機既有符號連結）、`docker-compose.yml`（`app` 服務需明確 `target: app`，否則預設建到最後一個 stage）、`.gitea/workflows/deploy.yml`、`docker/php/docker-entrypoint.sh`、`compose.override.yml`；另因 `env_file:` 讓 `.env` 全部變數成為真實環境變數而牽動 `tests/bootstrap.php`、`phpunit.xml`（擴大既有測試環境隔離的強制覆蓋範圍，非新增機制，詳見 design.md 實作驗證後追加）
- **受影響服務**：app、nginx（改為本地 build 而非拉取 `nginx:alpine`）、scheduler、queue-worker、reverb（全部五個掛 bind mount 的服務）
- **受影響流程**：VPS 部署流程（Gitea Actions self-hosted runner）、本機開發流程（開發者需改用 `docker compose build` 才能讓程式碼變動生效，或另外規劃開發環境仍用 bind mount 的 override 方案）
- **不受影響**：`file-storage-s3`（本次不強制切換到 S3，上傳檔案改走具名 volume 過渡）、`compose-cloud-baseline`（healthcheck／開發工具隔離邏輯不變）
