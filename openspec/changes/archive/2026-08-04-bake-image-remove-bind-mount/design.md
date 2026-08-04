## Context

`docker-compose.yml` 目前對 `app`、`nginx`、`scheduler`、`queue-worker`、`reverb` 五個服務都掛 `./:/var/www` bind mount，`Dockerfile` 完全沒有 `COPY . /var/www`。這代表：

- build 出來的 `cfdive-platform` image 是空殼，不含 `app/`、`routes/`、`vendor/`，無法脫離主機獨立運作。
- 部署（`.gitea/workflows/deploy.yml`）流程是 `git reset --hard` 改寫主機檔案 → `docker compose exec app composer install`（裝在 bind mount 出去的目錄上）→ artisan cache 系列，全程沒有 `docker compose build`。
- `docker/php/docker-entrypoint.sh` 承擔了大量「假設容器內是主機目錄」的邏輯：`.env` 不存在就從 `.env.example` 複製並 `key:generate`、composer.lock 內容比對決定是否重裝 vendor。
- 本機開發長期依賴 bind mount 做到「改 code 立即生效」（後端用 `kill -USR2 1` 讓 OPcache 重新驗證，前端 Vite HMR）。

P1（log 改 stderr、session 改 Redis）與 `file-storage-s3`（上傳檔案可切 S3，但 VPS 尚未真的切換，`.env` 仍是 `FILESYSTEM_DISK=local`）已經拆掉了「container 必須綁死主機磁碟」的兩個主要理由（log 與可選的檔案儲存），這次要處理最後一塊：程式碼本身。

## Goals / Non-Goals

**Goals:**
- production（VPS 實際部署用）的 `docker-compose.yml` 不再依賴主機 bind mount 取得程式碼，image build 完成即可獨立運作。
- 部署流程改為每次 `git pull` 後重新 `docker compose build`，取代現在的「改主機檔案 + exec 裝套件」。
- `.env` 改用 `env_file:` 傳遞，不再依賴 bind mount 把整個目錄（含 `.env`）帶進容器。
- 本機開發體驗（改 code 立即生效）不能被破壞——透過既有的 `compose.override.yml` 機制（`compose-cloud-baseline` 已建立此慣例）讓開發環境疊加 bind mount，production compose 本身不含。

**Non-Goals:**
- 不強制本次一併把 VPS 正式切到 `FILESYSTEM_DISK=s3`（`file-storage-s3` 程式邏輯已完成，但帳號申請與正式切換是獨立決策，留給後續變更）。
- 不處理零停機（blue-green / rolling）部署——`docker compose build && up -d` 期間服務仍會有短暫中斷，此為已知取捨，不在本次解決範圍。
- 不改動 `frontend/` 的既有 build 流程（`frontend` 服務本來就是獨立 build、不掛 bind mount，不受影響）。

## Decisions

### 1. Dockerfile 統一策略：先 copy composer manifests、install vendor，再 copy 全專案
在 `docker-php-ext-install` 之後、`ENTRYPOINT` 之前，依序：(1) `COPY composer.json composer.lock /var/www/`，(2) `RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts`，(3) `COPY . /var/www`，(4) 重新執行 `chown -R www-data:www-data` 與 `storage`/`bootstrap/cache` 權限設定（COPY 進來的檔案預設是 root）。這個順序讓 Docker layer cache 生效——只要 `composer.json`/`composer.lock` 沒變動，重建 image 時 `composer install` 這層可以直接命中快取、不重跑，只有應用程式碼變動的部分需要重新 COPY。**這是本次唯一策略，不與其他順序並存**（先前草稿一度在 Risk 段落與此處出現順序不一致，已統一）。

**替代方案考慮**：用 multi-stage build（composer stage 只複製 vendor 進最終 image）——本次不採用，因為專案規模小、build 時間不是痛點，multi-stage 增加的複雜度不值得；先用單階段搭配上述 layer cache 順序，未來若 image 太大或 build 太慢再考慮 multi-stage。

**`.dockerignore` 是本次的必要項目，不是可有可無的優化**：`COPY . /var/www` 會把 build context 內所有內容複製進 image，若無 `.dockerignore` 排除，`vendor/`（本機可能已存在、與容器內版本不一致）、`node_modules/`、`.env`（含資料庫密碼等機敏資訊）、`storage/app/public/*`（本機開發時已上傳的檔案，不應烘進 image）都會被複製進去，造成 image 內容錯誤或機敏資訊外洩進 image layer（即使之後刪除檔案，內容仍留在較早的 layer 歷史中）。新增 `.dockerignore` 排除上述路徑，屬於本次變更的正確性要求。

**composer scripts 驗證結果，`COPY . /var/www` 之後需補執行的步驟**：查證 `composer.json` 的 `scripts` 區塊，確認：
- `post-autoload-dump` 定義了 `Illuminate\Foundation\ComposerScripts::postAutoloadDump` 與 `@php artisan package:discover --ansi`——這兩者依賴 Laravel 應用程式碼（`artisan`、`bootstrap/`）已存在才能執行，但 Decision 1 的 `composer install` 發生在 `COPY . /var/www` **之前**（只有 composer manifests），此時執行會失敗，因此必須用 `--no-scripts` 跳過。
- `post-update-cmd`、`post-root-package-install`、`post-create-project-cmd` 是 `composer update`／`create-project` 才會觸發的 hook，一般 `composer install` 不會執行，不受影響，不需處理。
- 因此 Dockerfile 在 `COPY . /var/www` **之後**必須手動補執行 `composer dump-autoload --optimize`（重新掃描此時才存在的 `app/` 目錄，產生完整的 optimized classmap；`--no-scripts` 之前產生的 classmap 缺少專案自身 `App\` 命名空間下的類別）與 `php artisan package:discover --ansi`（等效於原本 `post-autoload-dump` 會執行的服務提供者自動發現，寫入 `bootstrap/cache/packages.php`／`services.php`），否則跳過的 `--no-scripts` 造成的缺口不會被補上。**修正**：這裡不再重複加 `--no-dev`——`--no-dev` 是控制 `composer install` 階段要不要把 dev 依賴裝進 `vendor/` 的旗標，先前的 `composer install --no-dev` 已經決定了 vendor 內容不含 dev 套件；後續的 `dump-autoload` 只是重新掃描「已經裝好的 vendor 內容」＋這次新出現的 `app/` 目錄來產生 classmap，重複加 `--no-dev` 是多餘的，拿掉能讓兩個指令的職責更清楚（install 決定裝什麼、dump-autoload 只重新產生對應的 classmap），也符合 Laravel/Docker 社群常見的分層 build 慣例。

### 2. Production compose 移除 bind mount，開發環境靠既有版控的 `compose.override.yml` 疊加
`docker-compose.yml`（production 用）移除 app/nginx/scheduler/queue-worker/reverb 的 `./:/var/www`。**修正**：`compose.override.yml` 實際上**已經進版控**（`git ls-files` 確認追蹤中，內含 `phpmyadmin`/`mailpit`，`compose-cloud-baseline` spec 已規範其用途）——先前草稿誤判為「被 `.gitignore` 排除、不進版控」（`.gitignore` 排除的是另一個沒人使用的檔名 `docker-compose.override.yml`），已更正。既然是既有已版控檔案，直接在其中新增這五個服務的 `./:/var/www` bind mount 即可，不需要額外的 `.example` 範本檔案——這個檔案本身就是所有開發者共用的標準範本。開發者本機執行 `docker compose up`（不指定 `-f`）時自動疊加、維持「改 code 立即生效」的體驗；VPS 執行 `docker compose -f docker-compose.yml up -d`（已是現行慣例，`compose-cloud-baseline` 已要求正式環境不吃 override）不受影響。

**替代方案考慮**：改用 volume mount 只掛特定子目錄（如只掛 `app/`、`resources/`）——本次不採用，因為切分維護成本高且容易漏掉新增目錄，不如整個目錄一起在 override 掛/不掛。

### 3. 上傳檔案改用具名 volume `storage-app-public` 過渡，不強制切 S3
新增具名 volume，命名為 `storage-app-public`（定名，不再是待決項目），掛載至 `app` 與 `nginx` 服務容器內的 **`/var/www/storage/app/public`**（絕對路徑，明確指定，避免與相對路徑寫法混淆）這一層路徑，讓使用者上傳的檔案在 image 重建後仍保留。

**為什麼不強制先切 S3**：R2 帳號申請與正式切換是獨立於本次「拿掉 bind mount」的決策（`file-storage-s3` 已記錄暫緩），不應該把兩個決策綁在一起、互相卡進度。具名 volume 是本次的暫時解法，日後若正式切到 S3，這個 volume 可以直接移除（`storage/app/public` 不再需要持久化，因為檔案都在 S3 上）。

**替代方案考慮**：把 `storage/app/public` 也一起 COPY 進 image——不採用，因為使用者上傳的檔案是執行期產生的資料，不該烘進 image（每次 rebuild 會用 build context 裡的舊內容覆蓋掉 volume 資料，或造成 image 越滾越大）。

### 4. nginx 的程式碼來源：改用 Dockerfile multi-stage 的獨立 build target，不依賴任何執行期共享機制
**這是先前草稿的一個實質缺漏**：拿掉 `./:/var/www` bind mount 後，`nginx` 服務（原本用官方 `nginx:alpine` image + bind mount 取得 `/var/www/public`）完全沒有其他方式取得 Laravel 的 `public/` 目錄——先前草稿只處理了 app/scheduler/queue-worker/reverb 四個跑 `cfdive-platform` image 的服務，遺漏了 nginx 這個用不同 base image、且職責只是靜態檔案服務＋反向代理的服務。

**決策**：把 `Dockerfile` 改為 multi-stage：
1. 第一階段 `FROM php:8.2-fpm AS app`（即 Decision 1 描述的既有內容：composer install → COPY . /var/www → 建立 `public/storage` 符號連結，見下方符號連結策略）。
2. 新增第二階段 `FROM nginx:alpine AS web`，執行 `COPY --from=app /var/www/public /var/www/public` 把 `app` 階段 build 完成的 `public/` 目錄複製進 nginx 專用的 image，再 `COPY docker/nginx/conf.d/ /etc/nginx/conf.d/` 帶入既有 nginx 設定。
3. `docker-compose.yml` 的 `nginx` 服務改為 `build: {context: ., dockerfile: Dockerfile, target: web}`（而非 `image: nginx:alpine` 直接從 registry 拉取），`image:` 欄位命名為 `cfdive-nginx-web`（與既有 `cfdive-frontend`——Vue/Vite 前端——區分，避免混淆）。

**為什麼不用共享 volume 讓 nginx 讀 app 的 `public/`**：具名 volume 一旦第一次由某容器掛載並帶入初始內容，之後即使 image 重新 build（新程式碼、新前端資源），volume 內容不會自動更新（Docker 只在 volume 為空時才從 image 複製一次）——這會導致「重新部署後 nginx 仍在服務舊的靜態資源」的嚴重 bug。改用 `COPY --from=app` 的 multi-stage 方式，`web` 階段的 `public/` 內容是每次 `docker compose build` 都重新從 `app` 階段複製的，沒有這個過期風險。

**與 Decision 1「不採用 multi-stage」的關係**：Decision 1 拒絕的是「composer stage 只複製 vendor 進最終 image」這種為了縮小 app image 體積的 multi-stage 優化，理由是複雜度不值得。這裡的 multi-stage 是為了解決「nginx 需要 app 的 public/ 內容」這個功能性缺口，屬於不同目的，兩者不衝突——app 階段本身仍是單一 `RUN composer install` 的簡單流程，沒有引入 vendor 相關的 multi-stage 複雜度。

### 5. `public/storage` symlink 策略：app 與 web 兩階段各自獨立建立，不依賴跨階段繼承
**這是先前草稿的另一個缺漏**：原草稿假設「entrypoint 在 app container 內執行 `storage:link` 之後，nginx 就看得到」——這個假設是錯的，`app` 與 `nginx` 是兩個完全獨立的容器檔案系統，一個容器內建立的符號連結不會出現在另一個容器裡。

**決策（修正版）**：`config/filesystems.php` 的 `links` 設定只有單一固定項目（`public_path('storage') => storage_path('app/public')`），對應的符號連結是一個不依賴執行期狀態、內容固定的相對路徑連結（`public/storage -> ../storage/app/public`）。改用純檔案系統操作、在 **build 階段**建立，且 `app` 與 `web` 兩個階段**各自獨立執行一次**，不依賴 `COPY --from=app` 把 `app` 階段建立的符號連結隱性帶到 `web` 階段：
- `app` 階段於 `COPY . /var/www` 之後執行 `RUN ln -sfn ../storage/app/public /var/www/public/storage`
- `web` 階段於 `COPY --from=app /var/www/public /var/www/public` 之後**再執行一次同樣的指令** `RUN ln -sfn ../storage/app/public /var/www/public/storage`

**為什麼不依賴 `COPY --from` 隱性繼承**：雖然 Docker `COPY --from` 技術上會保留符號連結本身（不解析成實體檔案），讓 `web` 階段「順帶」拿到 `app` 階段建立的符號連結，但這是一個隱性、容易被日後維護者忽略的耦合——如果之後有人調整 `COPY --from` 的複製範圍（例如改成只複製特定子目錄、或換成其他複製方式），這個符號連結就會無聲消失且不易察覺。改成兩階段**各自明確宣告**同一件事，即使其中一階段的建置方式改變，另一階段仍不受影響，且從 Dockerfile 原始碼就能直接看出「兩個 image 都需要這個符號連結」的意圖，不需要理解 `COPY --from` 的符號連結保留語意才能推斷。

**`ln -sf` 改為 `ln -sfn`**：`-n`（`--no-dereference`）避免一個常見陷阱——若目的地路徑已經是一個指向目錄的符號連結，`ln -sf`（不含 `-n`）會把新連結建立在該目錄「裡面」而非取代它。對 Docker 重建 image 的情境（layer 快取失效後重跑同一條 `RUN` 指令）加上 `-n`可確保每次執行都是冪等地取代既有符號連結，而不是意外在裡面巢狀建立。

**不使用 `php artisan storage:link`**：因為這只是建立一個符號連結檔案，不需要 Laravel 執行期或資料庫連線。

符號連結指向的實際檔案內容（`storage/app/public/` 下的檔案本身）則透過 Decision 3 的 `storage-app-public` 具名 volume，同時掛載到 `app` 與 `nginx` 容器的 `/var/www/storage/app/public` 路徑——兩個容器的符號連結都指向同一個相對路徑，該路徑在兩邊都被同一個具名 volume 覆蓋，內容一致。

**因此 entrypoint 不再需要執行 `storage:link`**（與先前草稿不同，見 Decision 8 更新）。

### 6. deploy.yml 改為 build-then-up，migration/cache 挪到新 container 啟動後
新流程：
```
git reset --hard origin/master
docker compose build
docker compose up -d --remove-orphans
# 等 app healthy 後：
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan config:cache / route:cache / view:cache / event:cache
docker compose exec -T app php artisan l5-swagger:generate
```
`docker compose up -d` 本身會因為 image 內容改變觸發 app/scheduler/queue-worker/reverb/nginx 重新建立 container（compose 偵測 image ID 變化）；`restart queue-worker` 這步可以拿掉，因為 `up -d` 已經會重建它。

**風險**：`docker compose build` 若失敗（例如 composer 依賴解析錯誤），現行 `git reset --hard` 已經把主機檔案改到新版本，但 container 還在跑舊 image——這與現行流程風險相同（現行流程 composer install 失敗一樣會卡在中間態），不是本次變更新增的風險，但要在 tasks 裡明確測試「build 失敗時 compose 是否維持舊 container 運作」。

### 7. `.env` 改用 `env_file:` 注入環境變數，範圍只給 Laravel runtime 服務，entrypoint 移除「主機目錄」假設邏輯
**範圍修正**：`env_file:` **只加在 `app`、`scheduler`、`queue-worker`、`reverb` 四個實際執行 Laravel/PHP 的服務**，**不加在 `nginx` 服務**。`nginx` 目前完全沒有 `environment:` 設定、不需要讀取任何 `.env` 內容（DB 密碼、`APP_KEY`、`AWS_*` 等）——它的職責只是服務 `web` 階段內建的靜態檔案並反向代理 `.php` 請求到 `app:9000`。給它 `.env` 存取權屬於不必要的機敏資訊暴露（最小權限原則），也容易誤導未來維護者以為 nginx 有讀取這些變數的需求。

四個 Laravel runtime 服務加上 `env_file:`，**明確用 YAML list 格式**：
```yaml
env_file:
  - .env
```
（不用 `env_file: .env` 這種單一純量寫法，list 格式是本次統一採用的寫法，VPS 上該檔案由既有部署流程手動維護，路徑不變）。**文案修正**：`env_file:` 的作用是把該檔案內容**以環境變數形式**注入容器程序（等同 `docker run --env-file`），**不會在容器檔案系統裡生成一份 `.env` 檔案**。先前草稿「`.env` 已由 `env_file:` 於執行期注入」這句話容易誤讀成「容器內會有一個 `.env` 檔案」，已修正措辭。若程式碼中有任何地方依賴讀取實體 `.env` 檔案（而非 `getenv()`/`env()` 讀環境變數），需額外處理，但目前 Laravel 的 `env()` 本來就是讀環境變數，本次不受影響。

`docker-entrypoint.sh` 移除：
- `.env` 不存在則複製 `.env.example` + `key:generate`（`env_file:` 已確保環境變數存在，不需要容器內自動生成任何 `.env` 檔案）
- composer.lock 內容比對後才 `composer install`（vendor 已在 image build 階段裝好，執行期不需要）
- 強制改寫 `DB_HOST=db` 那段 `preg_replace` 邏輯（改成直接在 `docker-compose.yml` 的 `environment:` 或 `.env` 裡設定，不用執行期改寫檔案）

### 8. 消除 deploy 與 entrypoint 的責任重疊
先前草稿讓 `docker-entrypoint.sh` 背景執行 migration/cache clear/swagger 生成，同時 `.gitea/workflows/deploy.yml` 又在 app healthy 後執行 migration/cache/swagger——兩邊職責重疊，且 entrypoint 這段邏輯會在 `app`、`scheduler`、`queue-worker` 三個服務啟動時各跑一次（三者都用同一個 entrypoint script，只是 `command:` 不同），造成 migration 被多個 container 併發嘗試執行的風險。改為明確切分：

- **deploy.yml 專責**：migration、`config:cache`/`route:cache`/`view:cache`/`event:cache`、`l5-swagger:generate`——這些是「部署一次新版本」該做的事，只需執行一次，且只在 CI/CD 流程裡由人/流程明確觸發。
- **entrypoint 只保留啟動必要工作**：目錄與權限設定（`storage`/`bootstrap/cache`，因為 `storage-app-public` 具名 volume 掛載時會覆蓋 image 內建的權限，每次啟動都需要重新確保）。**`storage:link` 不再是 entrypoint 的工作**——依 Decision 5（修正版），符號連結已在 `app` 與 `web` 兩個階段 build 時各自獨立建立，執行期不需要再做任何事。
- 移除 entrypoint 裡「等待 MySQL」的背景迴圈：這段原本只是為了讓後續的 migration 有 DB 可用，migration 移到 deploy.yml 後，entrypoint 不再需要等 MySQL 才能完成它剩下的工作（目錄權限設定不需要 DB）。

**風險**：若日後有人在 `queue-worker`／`reverb` 啟動時就需要 migration 已完成（例如 migration 會改變 queue 用到的資料表結構），deploy.yml 的「migration 在所有服務都 `up -d` 之後才執行」可能有短暫窗口讓 worker 用到舊 schema 處理任務。緩解：這與現行流程風險相近（現行 migration 也是部署腳本晚於 container 啟動才跑），且 Laravel migration 多為向下相容設計（新增欄位等），非本次變更引入的新風險，故不在本次額外處理，僅記錄於此供未來參考。

## Risks / Trade-offs

- **部署變慢** → 每次 `docker compose build` 預期數十秒到數分鐘（視 composer install 網路狀況），取代現在近乎瞬間的 `git reset --hard`。緩解：Decision 1 的 layer 順序（composer manifests 先於程式碼）已利用 Docker layer cache 降低影響。
- **本機改 code 不再瞬間生效（若忘記用 override）** → 開發者若誤用 `docker compose -f docker-compose.yml up`（不帶 override）會觸發「改 code 要 rebuild」的體驗，容易誤判為 bug。緩解：README／CLAUDE.md 補充說明本機開發指令固定用 `docker compose up`（不加 `-f`），CI/部署腳本固定用 `-f docker-compose.yml`。
- **`storage/app/public` 具名 volume 是過渡方案，非最終架構** → 之後正式切 S3 時需要額外一個變更去移除這個 volume 並確認舊檔案已遷移（`storage:migrate-to-s3` 已存在）。緩解：本次 design 已明確標注這是暫時方案，避免未來誤以為是最終狀態。
- **`docker compose build` 失敗時的中間態** → 現行流程本來就有「`git reset --hard` 已改主機檔案但 container 還沒套用」的窗口，本次不惡化此風險，但需在 tasks 裡加一項手動驗證。

## Migration Plan

1. 本機驗證：修改 `Dockerfile`、`docker-compose.yml`、調整既有已版控的 `compose.override.yml`、`docker-entrypoint.sh`，本機執行 `docker compose build && docker compose up -d` 確認所有服務正常啟動、`nc -z 127.0.0.1 9000` healthcheck 通過、既有測試套件全綠。
2. 本機驗證開發體驗：確認 `compose.override.yml` 生效後改 PHP 檔案不需 rebuild 即可看到效果（沿用現行 `kill -USR2 1` 或容器重啟機制）。
3. 更新 `.gitea/workflows/deploy.yml` 為 build-then-up 流程，先在非 master 分支（或手動觸發）驗證整個 CI 流程跑得通。
4. Merge 進 master，觀察 VPS 首次「build-then-up」部署，確認：image 建置成功、五個服務都用新 image 啟動、上傳檔案（`storage/app/public` volume）沒有遺失、既有功能（預約、聊天室、圖片上傳）正常。
5. **Rollback 策略**：VPS 上舊的 `cfdive-platform` image（build 前的版本）不會被 `docker compose build` 自動刪除，若新版本異常可 `git revert` 該次 commit 並重新 `docker compose build && up -d` 回到前一版；因為程式碼與 vendor 都烘進 image，rollback 不再需要擔心「主機檔案已改但 vendor 版本不對」的情況（這其實是烘進 image 帶來的額外好處：rollback 更乾淨）。

## Open Questions

（本次規劃階段已收斂的問題不再列於此——volume 命名已定名為 `storage-app-public`，見 Decision 3；`compose.override.yml` 版控狀態已查證澄清，見 Decision 2；nginx 程式碼來源與 symlink 策略已定案，見 Decision 4、5。目前無待決問題。）

## 實作驗證後追加（2026-08-04）

本機完整跑過 `docker compose build` + `up -d`（含 override 與 production 兩種模式）後，發現並修復三個規劃階段未預見的問題：

1. **`app` 服務的 `build:` 必須明確指定 `target: app`**：`Dockerfile` 改 multi-stage 後，若 `build:` 未指定 `target:`，Docker 預設建到 Dockerfile 中**最後一個** stage（本例是 `web`）。這代表 `cfdive-platform` image 若沒補上 `target: app`，實際內容會是 nginx 而非 php-fpm——`docker image inspect` 驗證過（entrypoint 顯示為 `nginx -g daemon off;`），屬於會讓整個 `app` container 完全無法運作的嚴重錯誤。已在 `docker-compose.yml` 的 `app` 服務 `build:` 區塊加上 `target: app` 修正，`nginx` 服務原本就有 `target: web` 不受影響。**這是本次多階段 Dockerfile 設計裡容易被忽略的一點：每一個引用同一份 multi-stage Dockerfile 的 compose service，都必須明確宣告自己要的 target，不能依賴預設值。**
2. **`.dockerignore` 需額外排除 `public/storage`**：本機開發環境長期有一個先前用 `php artisan storage:link`（Laravel 預設，非本次新增的相對路徑版本）建立的 `public/storage` 符號連結，指向絕對路徑 `/var/www/storage/app/public`。這個符號連結若留在 build context 內，會讓 Docker Desktop for Windows 在打包 build context 時因為 Windows 符號連結的檔案模式無法正確轉換而失敗（`archive/tar: unknown file mode`）。已加入 `.dockerignore`。
3. **`env_file:` 讓 `.env` 全部變數成為真實環境變數，破壞既有測試隔離機制**：這個專案先前已經因為「compose `environment:` 把 `DB_CONNECTION`/`DB_DATABASE` 設成真實環境變數，導致 PHPUnit 測試在容器內清空開發用 MySQL」踩過一次坑（2026-06-12），並在 `tests/bootstrap.php` 寫了強制覆蓋 `$_SERVER`/`$_ENV`/`putenv` 三處的修復。但那次修復**只覆蓋了 `DB_CONNECTION`/`DB_DATABASE` 兩個 key**，因為當時只有這兩個透過 compose `environment:` 洩漏成真實環境變數。這次 Decision 7 把 `env_file: .env` 套用到 `app` 服務後，`.env` 檔案裡**所有**變數都變成真實環境變數，包括與 `phpunit.xml` 宣告的 testing 值衝突的 `APP_ENV=local`（非 `testing`）、`CACHE_STORE=redis`（非 `array`）、`SESSION_DRIVER=database`（非 `array`）、`QUEUE_CONNECTION=database`（非 `sync`）、`MAIL_MAILER=smtp`（非 `array`）、`BCRYPT_ROUNDS=12`（非 `4`）——由於 Laravel 的 `env()` 透過 phpdotenv 優先讀 `$_SERVER`，這些真實環境變數蓋掉了 `phpunit.xml` 的宣告值，造成 9 個測試失敗（快取跨測試污染、意外走真實 SMTP mailer、`APP_ENV` 非 testing 導致例外處理行為不同等）。**修復方式是把既有 `tests/bootstrap.php` 的強制覆蓋清單，從只有 2 個 key 擴大到涵蓋 `phpunit.xml` 宣告的全部 11 個 key**，同時在 `phpunit.xml` 補上 `force="true"` 做自我說明（實際生效仍是 `tests/bootstrap.php` 的 `$_SERVER` 寫入，`force="true"` 本身不夠）。這不是本次變更引入的新架構問題，而是既有測試隔離機制的覆蓋範圍需要跟著 `env_file:` 的採用範圍同步擴大——**未來任何時候擴大 `env_file:` 涵蓋的變數範圍，都要重新檢查 `tests/bootstrap.php` 的強制覆蓋清單是否需要同步更新**。
