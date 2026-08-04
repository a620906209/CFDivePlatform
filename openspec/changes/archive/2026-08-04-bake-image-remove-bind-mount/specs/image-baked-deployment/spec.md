## ADDED Requirements

### Requirement: Image build 內嵌完整程式碼與相依套件
`Dockerfile` 的 `app` 階段 SHALL 依序執行 `COPY composer.json composer.lock`、`composer install --no-dev --optimize-autoloader --no-scripts`、`COPY . /var/www`，使 build 完成的 `cfdive-platform` image 包含完整可執行程式碼（`app/`、`routes/`、`config/` 等）與 `vendor/`，不依賴任何執行期 bind mount 才能取得程式碼。composer manifests SHALL 先於全專案 COPY，以利用 Docker layer cache。

#### Scenario: 全新環境不需主機程式碼即可啟動
- **WHEN** 在一台全新機器上僅有 `cfdive-platform` image（無主機端 checkout 的程式碼目錄）
- **THEN** 以該 image 啟動的 container 可正常執行 `php artisan` 指令與服務既有 API 請求

#### Scenario: Build 完成後檔案權限正確
- **WHEN** `docker build` 執行完成
- **THEN** `/var/www/storage` 與 `/var/www/bootstrap/cache` 的擁有者為 `www-data`，且具備 PHP-FPM 寫入所需權限

#### Scenario: 只修改應用程式碼時 composer 層命中快取
- **WHEN** `composer.json`／`composer.lock` 內容未變動，僅應用程式碼變動後重新 `docker build`
- **THEN** `composer install` 所在的 layer 命中 Docker build cache，不重新執行

### Requirement: COPY . 之後補跑 composer 的 post-autoload 腳本
`composer.json` 的 `post-autoload-dump` 定義了 `Illuminate\Foundation\ComposerScripts::postAutoloadDump` 與 `php artisan package:discover --ansi`，這兩者依賴 Laravel 應用程式碼已存在才能執行，因此 `composer install` 階段 SHALL 使用 `--no-scripts` 跳過。`Dockerfile` 的 `app` 階段 SHALL 在 `COPY . /var/www` 之後補執行 `composer dump-autoload --optimize`（不重複加 `--no-dev`——該旗標已在 `composer install` 階段決定 vendor 內容，此處只是重新掃描 vendor 與新增的 `app/` 目錄產生 classmap）與 `php artisan package:discover --ansi`，確保 optimized classmap 包含專案自身 `App\` 命名空間下的類別、服務提供者自動發現快取正確產生。

#### Scenario: Image 內服務提供者自動發現快取存在
- **WHEN** `docker build` 執行完成
- **THEN** `/var/www/bootstrap/cache/packages.php` 與 `/var/www/bootstrap/cache/services.php` 存在且內容反映 `composer.json` 宣告的套件

#### Scenario: 專案自身類別可被 optimized autoloader 找到
- **WHEN** container 以烘好的 image 啟動並執行任一使用 `App\` 命名空間類別的 artisan 指令
- **THEN** 指令正常執行，不因 classmap 缺少專案自身類別而拋出 class not found 錯誤

### Requirement: public/storage 符號連結於 build 階段建立，app 與 web 兩階段各自獨立建立
`Dockerfile` 的 `app` 階段與 `web` 階段 SHALL **各自獨立**在其 `public/` 目錄就緒後執行 `ln -sfn ../storage/app/public /var/www/public/storage`（對應 `config/filesystems.php` 的 `links` 設定；`-n` 避免既有符號連結指向目錄時被誤判為目錄而巢狀建立），不依賴 `COPY --from=app` 隱性傳遞符號連結、也不依賴任何容器執行期指令（如 `php artisan storage:link`）。

#### Scenario: app 階段內存在符號連結
- **WHEN** `docker build` 執行完成
- **THEN** `cfdive-platform`（`app` 階段）image 內 `/var/www/public/storage` 是一個指向 `../storage/app/public` 的符號連結，不需要容器啟動後才產生

#### Scenario: web 階段獨立存在符號連結
- **WHEN** `docker build` 執行完成
- **THEN** `cfdive-nginx-web`（`web` 階段）image 內 `/var/www/public/storage` 也是一個指向 `../storage/app/public` 的符號連結，此連結由 `web` 階段自身的 `RUN ln -sfn` 指令建立，不依賴 `app` 階段的 build 產物或執行期狀態

#### Scenario: 重複執行符號連結指令具冪等性
- **WHEN** Docker layer cache 失效、`RUN ln -sfn ../storage/app/public /var/www/public/storage` 在同一路徑上重複執行
- **THEN** 該指令直接取代既有符號連結，不會在其中巢狀建立新的連結檔案

### Requirement: nginx 服務程式碼與靜態資源來自獨立 build target，不依賴執行期共享機制
`Dockerfile` SHALL 定義第二個 build 階段 `web`（`FROM nginx:alpine`），執行 `COPY --from=app /var/www/public /var/www/public` 取得 `app` 階段建置完成的 `public/` 目錄，並 `COPY docker/nginx/conf.d/ /etc/nginx/conf.d/` 帶入既有 nginx 設定。`docker-compose.yml` 的 `nginx` 服務 SHALL 改為從此 `web` target 本地建置（而非拉取官方 `nginx:alpine` image），不依賴任何執行期 volume 或其他跨容器共享機制取得程式碼。

#### Scenario: nginx image 內建 public 目錄
- **WHEN** 執行 `docker compose build`（`nginx` 服務對應 `web` target）
- **THEN** 建置完成的 `nginx` image 內 `/var/www/public` 存在且內容與 `app` image build 時的 `public/` 一致

#### Scenario: 程式碼變更後 nginx 靜態資源同步更新
- **WHEN** 應用程式碼（含 `public/` 下的靜態資源）有變更並重新執行 `docker compose build`
- **THEN** `nginx` image 的 `/var/www/public` 內容反映最新的建置結果，不存在因具名 volume 只在首次掛載複製一次而導致的內容過期問題

#### Scenario: nginx 透過 HTTP 正確服務 public/ 下的靜態檔案
- **WHEN** `nginx` container 以 build 完成的 `web` image 啟動，對其發出一個 `public/` 目錄下既有靜態檔案（例如 `favicon.ico` 或前端建置後的靜態資源）的 HTTP 請求
- **THEN** nginx 直接回應該檔案內容（`try_files` 命中實體檔案），不需要透過 `app` container 轉發

#### Scenario: nginx 透過 HTTP 正確服務 public/storage 下的已上傳檔案
- **WHEN** `nginx` container 已掛載 `storage-app-public` 具名 volume 且該 volume 內存在一個已上傳檔案，對其發出 `/storage/{檔案路徑}` 的 HTTP 請求
- **THEN** nginx 透過 `public/storage` 符號連結解析到 `storage-app-public` volume 內的實際檔案並正確回應，不需要透過 `app` container 轉發

### Requirement: Build context 排除機敏與非必要檔案
`.dockerignore` SHALL 排除 `vendor/`、`node_modules/`、`.env`、`storage/app/public/*` 等路徑，確保 `COPY . /var/www` 不會把本機殘留檔案或機敏資訊（如 `.env` 內的資料庫密碼）複製進 image。

#### Scenario: .env 不出現在 image 內
- **WHEN** 主機 build context 目錄存在 `.env`（含資料庫密碼等機敏資訊）並執行 `docker build`
- **THEN** build 完成的 image 內 `/var/www/.env` 不存在

#### Scenario: 本機 vendor 不覆蓋 build 階段安裝結果
- **WHEN** 主機 build context 目錄存在本機已安裝的 `vendor/`（可能與 image 內 PHP 版本或平台不相容）
- **THEN** build 完成的 image 內 `vendor/` 內容完全來自 image build 階段的 `composer install`，不受主機 `vendor/` 內容影響

### Requirement: Production compose 不依賴主機 bind mount 取得程式碼
`docker-compose.yml`（production）SHALL 不對 `app`、`nginx`、`scheduler`、`queue-worker`、`reverb` 任何一個服務定義 `./:/var/www` 這類將整個原始碼目錄掛進容器的 volume。

#### Scenario: 僅用 production compose 啟動時程式碼來自 image
- **WHEN** 執行 `docker compose -f docker-compose.yml up -d`
- **THEN** 五個服務容器內的 `/var/www` 內容與 image build 時內嵌的內容一致，即使主機端程式碼目錄被刪除，容器仍正常運作

### Requirement: 每個 compose service 明確指定 multi-stage build target
`docker-compose.yml` 中依賴這份 multi-stage `Dockerfile` 建置的每一個服務（`app` 與 `nginx`）SHALL 在其 `build:` 設定明確指定 `target:`（`app` 服務指定 `target: app`，`nginx` 服務指定 `target: web`），不得省略。

#### Scenario: app 服務建置出的是 php-fpm image 而非 nginx
- **WHEN** 執行 `docker compose build app`
- **THEN** 建置完成的 `cfdive-platform` image 其 entrypoint／預設指令為啟動 php-fpm，而非 nginx（`docker image inspect cfdive-platform` 確認 `Config.Cmd` 為 `["php-fpm"]`）

#### Scenario: 省略 target 會建到 Dockerfile 最後一個 stage
- **WHEN** 某服務的 `build:` 未指定 `target:`
- **THEN** Docker 依規範建置到 Dockerfile 中定義的最後一個 stage，可能與該服務實際需要的 stage 不同——此為本次 multi-stage 化後所有服務都必須明確宣告 target 的原因

### Requirement: 本機開發環境透過 override 疊加 bind mount
`compose.override.yml` SHALL 為 `app`、`nginx`、`scheduler`、`queue-worker`、`reverb` 定義 `./:/var/www` bind mount，供本機開發時「改 code 立即生效」使用；此 override 檔案不影響 production compose 行為。

#### Scenario: 本機開發改 code 立即生效
- **WHEN** 開發者於本機執行 `docker compose up -d`（未指定 `-f`，自動疊加 override）並修改後端 PHP 檔案
- **THEN** 容器內看到的檔案內容即時反映主機端變更，不需重新 `docker compose build`

#### Scenario: VPS 部署不受 override 影響
- **WHEN** VPS 執行 `docker compose -f docker-compose.yml up -d --remove-orphans`（不吃 override）
- **THEN** 容器程式碼完全來自 image，忽略主機上是否存在 `compose.override.yml`

### Requirement: 上傳檔案透過具名 volume 在 image 重建後保留
`docker-compose.yml` SHALL 定義具名 volume `storage-app-public`，掛載至 `app` 與 `nginx` 服務的 `/var/www/storage/app/public`（絕對路徑）路徑，確保使用者上傳的檔案（課程圖片、教練證照、聊天室圖片）在 image 重新 build 並重啟 container 後不遺失。

#### Scenario: Image 重建後既有上傳檔案仍可存取
- **WHEN** 執行 `docker compose build && docker compose up -d` 重建 image 並重啟 container
- **THEN** 重建前已上傳的課程圖片等檔案透過 `nginx` 仍可正常存取，內容與重建前一致

### Requirement: 部署流程改為 build-then-up，且為部署動作唯一執行位置
`.gitea/workflows/deploy.yml` SHALL 在 `git reset --hard origin/master` 之後執行 `docker compose build` 重新建置 image，再執行 `docker compose up -d` 套用新 image，取代原本「改寫主機檔案後於既有容器內執行 `composer install`」的流程。Migration、`config:cache`／`route:cache`／`view:cache`／`event:cache`、`l5-swagger:generate` SHALL 只在 `deploy.yml` 執行、且只在新 container 啟動且健康後才執行，`docker-entrypoint.sh` SHALL 不重複執行這些動作。

#### Scenario: 部署新版程式碼觸發 image 重建
- **WHEN** master 分支有新 commit 推送並觸發部署流程
- **THEN** 部署流程執行 `docker compose build`，`app`/`scheduler`/`queue-worker`/`reverb` 四個依賴 `cfdive-platform` image 的服務容器、以及 `nginx`（依賴 `cfdive-nginx-web` image）皆以新 image 重新建立

#### Scenario: Migration 在新 container 就緒後才執行
- **WHEN** `docker compose up -d` 執行完成
- **THEN** 部署流程等待 `app` container healthcheck 通過後，才執行 `php artisan migrate --force`

#### Scenario: Migration 與 cache 不在容器啟動時重複執行
- **WHEN** `app`、`scheduler`、`queue-worker` 三個共用同一 entrypoint 的服務各自啟動
- **THEN** 沒有任何一個容器在啟動過程中執行 migration、cache 系列 artisan 指令或 `l5-swagger:generate`——這些動作僅由 `deploy.yml` 執行一次

### Requirement: 環境變數透過 env_file 注入 Laravel runtime 服務，nginx 不注入
`docker-compose.yml` 中 `app`、`scheduler`、`queue-worker`、`reverb` 四個實際執行 Laravel/PHP 的服務 SHALL 使用 YAML list 格式的 `env_file:`（即 `env_file:` 後接 `- .env` 列表項，而非 `env_file: .env` 純量寫法）指向主機上的 `.env` 檔案路徑，將其內容以環境變數形式注入容器程序，不再透過 bind mount 整個目錄的方式讓容器讀到 `.env`。`nginx` 服務 SHALL **不**設定 `env_file:`——它不需要讀取任何 `.env` 內容，給予存取權屬於不必要的機敏資訊暴露。`env_file:` SHALL 只負責注入環境變數，容器內的檔案系統不會因此產生一份實體 `.env` 檔案。

#### Scenario: 更新 .env 後不需重建 image 即可套用
- **WHEN** 操作者修改主機上的 `.env` 內容並執行 `docker compose up -d`（不重新 build）
- **THEN** `app`、`scheduler`、`queue-worker`、`reverb` 四個服務容器重新建立並讀取到更新後的環境變數

#### Scenario: 容器內不存在實體 .env 檔案
- **WHEN** container 以 `env_file:` 注入環境變數的方式啟動
- **THEN** 容器內 `/var/www/.env` 檔案不存在，程式讀取設定值皆透過環境變數（`env()`/`getenv()`）取得

#### Scenario: nginx 容器不具備 .env 內容存取權
- **WHEN** `nginx` container 啟動
- **THEN** 該容器的環境變數中不存在 `.env` 檔案內定義的變數（如 `DB_PASSWORD`、`APP_KEY`），因為 `nginx` 服務未設定 `env_file:`

### Requirement: Entrypoint 移除主機目錄假設邏輯
`docker-entrypoint.sh` SHALL 不再包含「`.env` 不存在時從 `.env.example` 複製並執行 `key:generate`」「依 `composer.lock` 內容比對決定是否重新 `composer install`」「強制以 `preg_replace` 改寫 `.env` 內 `DB_HOST` 值」這三段邏輯，因為 image 已在 build 階段完成 vendor 安裝、環境變數已由 `env_file:` 於執行期注入。

#### Scenario: Container 啟動不再執行執行期 composer install
- **WHEN** container 以烘好程式碼的 image 啟動
- **THEN** entrypoint 不執行任何 `composer install` 指令，`vendor/` 內容與 image build 時完全一致

#### Scenario: Container 啟動不再自動產生 .env
- **WHEN** container 啟動且未透過 `env_file:` 提供有效環境變數
- **THEN** entrypoint 不自動複製 `.env.example` 或執行 `php artisan key:generate`，由部署流程確保 `.env` 存在為前提條件

#### Scenario: Container 啟動不再改寫 .env 檔案內容
- **WHEN** container 啟動
- **THEN** entrypoint 不執行任何修改 `.env` 檔案內容的指令，`DB_HOST` 等設定值一律由 `docker-compose.yml` 的 `environment:` 或 `.env` 提供

### Requirement: Entrypoint 只保留啟動必要工作
`docker-entrypoint.sh` SHALL 只保留目錄與權限設定（`storage`、`bootstrap/cache`），不再包含等待 MySQL 的背景迴圈、migration、cache clear 系列指令、`l5-swagger:generate`、`storage:link`（symlink 已於 image build 階段建立，見「public/storage 符號連結於 build 階段建立」requirement）。

#### Scenario: Entrypoint 不等待 MySQL
- **WHEN** container 啟動且 MySQL 尚未就緒
- **THEN** entrypoint 不因此阻塞或執行任何等待迴圈，php-fpm 正常啟動

#### Scenario: Entrypoint 不執行 storage:link
- **WHEN** container 啟動且 `storage-app-public` 具名 volume 已掛載
- **THEN** entrypoint 不執行任何 `storage:link` 相關指令，`/var/www/public/storage` 符號連結已存在於 image 內容中
