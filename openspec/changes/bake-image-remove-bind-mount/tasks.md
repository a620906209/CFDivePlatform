## 1. Dockerfile 改為 multi-stage：app 階段內嵌程式碼與相依套件

- [ ] 1.1 [後端] `Dockerfile`：第一階段命名為 `FROM php:8.2-fpm AS app`（既有內容套用此階段名稱，供後續 `web` 階段 `COPY --from=app` 使用）
- [ ] 1.2 [後端] `Dockerfile`：在 `docker-php-ext-install` 之後、`COPY docker/php/local.ini` 之前，先 `COPY composer.json composer.lock /var/www/` 並執行 `composer install --no-dev --optimize-autoloader --no-interaction --no-scripts`（利用 layer cache，composer 檔案未變動時不重跑；`--no-scripts` 是必要的，因為此時 `app/`、`artisan` 尚未存在，composer 的 `post-autoload-dump` hook 會失敗）
- [ ] 1.3 [後端] `Dockerfile`：新增 `COPY . /var/www`（放在 composer install 之後），並在其後重新執行 `chown -R www-data:www-data /var/www` 與 `chmod -R 775 storage bootstrap/cache`（COPY 進來的檔案預設 owner 是 root）
- [ ] 1.4 [後端] `Dockerfile`：`COPY . /var/www` 之後補執行 `composer dump-autoload --optimize`（不重複加 `--no-dev`，該旗標已由前面的 `composer install --no-dev` 決定 vendor 內容，這裡只是重新掃描產生 classmap）與 `php artisan package:discover --ansi`（補上 `--no-scripts` 跳過的服務提供者自動發現，確認 `bootstrap/cache/packages.php`／`services.php` 正確產生）
- [ ] 1.5 [後端] `Dockerfile`：`COPY . /var/www` 之後執行 `RUN ln -sfn ../storage/app/public /var/www/public/storage`（`-n` 避免既有符號連結指向目錄時被誤判巢狀建立），在 build 階段建立 `public/storage` 符號連結（對應 `config/filesystems.php` 的 `links` 設定），不依賴任何執行期 artisan 指令
- [ ] 1.6 [後端] 新增 `.dockerignore`（目前不存在，屬本次必要項目非優化）：排除 `vendor/`、`node_modules/`、`.env`、`storage/app/public/*`，避免機敏資訊（`.env` 內資料庫密碼）或本機殘留檔案被 `COPY . /var/www` 複製進 image

## 2. Dockerfile 新增 web 階段（nginx 程式碼來源）

- [ ] 2.1 [後端] `Dockerfile`：新增第二階段 `FROM nginx:alpine AS web`
- [ ] 2.2 [後端] `Dockerfile`：`web` 階段執行 `COPY --from=app /var/www/public /var/www/public`，取得 `app` 階段建置完成的 `public/` 目錄
- [ ] 2.3 [後端] `Dockerfile`：`web` 階段**再次獨立執行** `RUN ln -sfn ../storage/app/public /var/www/public/storage`（不依賴 2.2 的 `COPY --from` 隱性帶入 1.5 建立的符號連結——兩階段各自明確宣告，避免日後若複製方式調整導致符號連結無聲消失）
- [ ] 2.4 [後端] `Dockerfile`：`web` 階段執行 `COPY docker/nginx/conf.d/ /etc/nginx/conf.d/`，帶入既有 nginx 設定（`docker/nginx/conf.d/app.conf` 的 `root /var/www/public;` 設定不需更動，`SCRIPT_FILENAME` 路徑在 app／nginx 兩個容器間仍一致）

## 3. docker-compose.yml 調整（production）

- [ ] 3.1 [後端] `docker-compose.yml`：移除 `app`、`nginx`、`scheduler`、`queue-worker`、`reverb` 五個服務的 `./:/var/www` volume 定義
- [ ] 3.2 [後端] `docker-compose.yml`：`nginx` 服務的 `image: nginx:alpine` 改為 `build: {context: ., dockerfile: Dockerfile, target: web}`，並將 `image:` 欄位改為本地命名（如 `cfdive-nginx-web`，與既有 Vue/Vite 前端的 `cfdive-frontend` 區分）
- [ ] 3.3 [後端] `docker-compose.yml`：新增具名 volume `storage-app-public`（定名，見 design.md Decision 3），掛載至 `app` 與 `nginx` 服務容器內的 **`/var/www/storage/app/public`**（絕對路徑），並在底部 `volumes:` 區塊註冊
- [ ] 3.4 [後端] `docker-compose.yml`：**只在** `app`、`scheduler`、`queue-worker`、`reverb` **四個** Laravel runtime 服務加上 YAML list 格式的 `env_file:`：
  ```yaml
  env_file:
    - .env
  ```
  （不用 `env_file: .env` 純量寫法；`nginx` 服務**不**加 `env_file:`，它不需要讀取 `.env` 內容，避免不必要的機敏資訊暴露）；移除 `environment:` 區塊裡與 `.env` 重複的 `DB_*` 硬編碼項目（改由 `.env` 統一提供，避免兩處來源衝突）

## 4. 本機開發環境 override

- [ ] 4.1 [後端] 更新既有已版控的 `compose.override.yml`（`git ls-files` 確認已追蹤，非未版控檔案）：為 `app`、`nginx`、`scheduler`、`queue-worker`、`reverb` 補上 `./:/var/www` bind mount，維持本機開發改 code 立即生效；不需另建 `.example` 檔案，此檔案本身即為所有開發者共用的標準範本

## 5. entrypoint 職責調整（只保留啟動必要工作，migration/cache/swagger/storage:link 全部移除）

- [ ] 5.1 [後端] `docker/php/docker-entrypoint.sh`：移除「`.env` 不存在則複製 `.env.example` + `key:generate`」區塊
- [ ] 5.2 [後端] `docker/php/docker-entrypoint.sh`：移除「依 `composer.lock` 內容比對決定是否重新 `composer install`」區塊
- [ ] 5.3 [後端] `docker/php/docker-entrypoint.sh`：移除強制改寫 `DB_HOST=db` 的 `preg_replace` 邏輯（改由 `.env`／compose `environment:` 直接提供正確值）
- [ ] 5.4 [後端] `docker/php/docker-entrypoint.sh`：移除等待 MySQL 的背景迴圈，以及該迴圈之後的 migration、`config:clear`/`cache:clear`/`route:clear`/`view:clear`、`l5-swagger:generate` 整段背景執行邏輯（責任移交 deploy.yml，見 group 6）
- [ ] 5.5 [後端] `docker/php/docker-entrypoint.sh`：移除 `storage:link` 執行（symlink 已於 1.5 在 image build 階段建立，執行期不需要）
- [ ] 5.6 [後端] `docker/php/docker-entrypoint.sh`：確認保留目錄與權限設定（`storage`、`bootstrap/cache`）作為 entrypoint 最終僅存的啟動必要工作

## 6. 部署流程（.gitea/workflows/deploy.yml）

- [ ] 6.1 [後端] `deploy.yml`：`git reset --hard origin/master` 之後新增 `docker compose build` step
- [ ] 6.2 [後端] `deploy.yml`：把原本「Install Composer dependencies」step 移除（已在 image build 階段完成）
- [ ] 6.3 [後端] `deploy.yml`：新增 `docker compose up -d --remove-orphans` step，並加入等待 `app` container healthy 的檢查（如輪詢 `docker compose ps --format json` 或既有 healthcheck 狀態）後才繼續
- [ ] 6.4 [後端] `deploy.yml`：確認 migration、`config:cache`/`route:cache`/`view:cache`/`event:cache`、`l5-swagger:generate` 幾個 step 移到 `app` healthy 之後執行
- [ ] 6.5 [後端] `deploy.yml`：移除原本獨立的「Restart queue worker」step（`docker compose up -d` 已會因 image 變更重建該 container）

## 7. 驗證

- [ ] 7.1 [整合測試] 本機執行 `docker compose build && docker compose up -d`（含 override），確認五個服務容器全部啟動、`app` healthcheck（`nc -z 127.0.0.1 9000`）通過
- [ ] 7.2 [整合測試] 本機容器內執行 `php artisan test`，確認既有測試套件全綠（比對變更前的 baseline 通過數）
- [ ] 7.3 [整合測試] 驗證本機開發體驗：override 生效時修改一個 PHP 檔案，確認容器內立即反映變更（不需 rebuild）
- [ ] 7.4 [整合測試] 驗證 production 模式：`docker compose -f docker-compose.yml up -d`（不含 override）啟動後，暫時搬移或改名主機端專案目錄，確認容器仍正常回應 API 請求
- [ ] 7.5 [整合測試] 驗證上傳檔案持久性：上傳一張課程圖片，執行 `docker compose build && docker compose up -d` 重建 image，確認該圖片透過 nginx 仍可正常存取
- [ ] 7.6 [整合測試] 驗證 `.env` 熱更新：修改 `.env` 內一個非敏感值，`docker compose up -d`（不 build），確認容器內 `php artisan tinker` 讀到新值
- [ ] 7.7 [整合測試] 在 Gitea Actions 上以手動觸發或測試分支驗證完整 `deploy.yml` 新流程跑得通，確認 build 失敗時舊 container 是否仍維持運作（design.md 已知風險項）
- [ ] 7.8 [整合測試] VPS 正式部署後，人工驗證預約、聊天室、課程圖片上傳、教練證照上傳等現有功能正常，並確認 `scheduler`/`queue-worker`/`reverb` 皆以新 image 啟動（`docker compose ps` 確認 image ID 一致）
- [ ] 7.9 [整合測試] 確認 entrypoint／deploy 職責切分無重疊：`docker compose up -d` 啟動 `app`/`scheduler`/`queue-worker` 三個共用 entrypoint 的服務時，觀察其 log 不應出現 migration 或 `l5-swagger:generate` 執行紀錄，這些只應出現在 `deploy.yml` 執行紀錄裡
- [ ] 7.10 [整合測試] 驗證 nginx 靜態資源獨立於 app：`docker compose build` 後單獨檢查 `nginx` container 內 `/var/www/public` 存在且可直接存取（如 `docker compose exec nginx ls /var/www/public`），停掉 `app` container 後 nginx 仍能透過 HTTP 直接回應 `public/` 下的靜態檔案（如 `favicon.ico`），確認不依賴 `app` container 是否存在或執行中
- [ ] 7.11 [整合測試] 驗證 nginx 的 public/storage 符號連結獨立建立：`docker compose exec nginx ls -la /var/www/public/storage` 確認符號連結存在且指向 `../storage/app/public`，比對 `docker compose exec app ls -la /var/www/public/storage` 確認兩個容器**各自獨立**擁有此符號連結（非透過同一個共享狀態）
- [ ] 7.12 [整合測試] 驗證 nginx 透過 public/storage 正確服務已上傳檔案：先透過應用上傳一張課程圖片，直接對 nginx 發出該檔案的 HTTP 請求（`/storage/{path}`），確認回應內容正確，且此請求全程未經過 `app` container（php-fpm 只處理 `.php` 副檔名請求，見 `docker/nginx/conf.d/app.conf` 的 `location ~ \.php$`）
- [ ] 7.13 [整合測試] 驗證 composer 自動發現正確：`docker compose exec app php artisan route:list` 或任一依賴自動發現的第三方套件功能（如 l5-swagger）正常運作，確認 `package:discover` 於 build 階段正確執行
- [ ] 7.14 [整合測試] 驗證 nginx 容器不具備 `.env` 內容：`docker compose exec nginx env | grep -i "DB_PASSWORD\|APP_KEY"` 應無任何輸出，確認 `nginx` 服務未設定 `env_file:` 生效
