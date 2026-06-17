## 1. docker-compose.yml 調整

- [x] 1.1 [後端] 修改 `docker-compose.yml` app service healthcheck：將 `test: ["CMD", "php", "-v"]` 改為 `test: ["CMD-SHELL", "nc -z 127.0.0.1 9000 || exit 1"]`（app container 無 HTTP server，用 nc 驗證 PHP-FPM FastCGI port 9000）
- [x] 1.2 [後端] 從 `docker-compose.yml` 移除 `phpmyadmin` service 完整定義（包含 image、container_name、environment、networks、depends_on）
- [x] 1.3 [後端] 從 `docker-compose.yml` 移除 `mailpit` service 完整定義

## 2. 新增 compose.override.yml

- [x] 2.1 [後端] 在專案根目錄新建 `compose.override.yml`，加入 `phpmyadmin` service（保留原有的 PMA_HOST、PMA_PORT、PMA_USER、PMA_PASSWORD、MYSQL_ROOT_PASSWORD 環境變數設定與 networks、depends_on）
- [x] 2.2 [後端] 在 `compose.override.yml` 加入 `mailpit` service（保留原有設定與 networks）

## 3. .env.example 更新

- [x] 3.1 [後端] 將 `.env.example` 的 `QUEUE_CONNECTION` 值從 `database` 改為 `redis`
- [x] 3.2 [後端] 在 `.env.example` 的 `FILESYSTEM_DISK=local` 旁加上行內說明，標示雲端應改為 `s3`
- [x] 3.3 [後端] 在 `.env.example` 的 `LOG_CHANNEL` / `LOG_STACK` 區段加上說明，標示雲端建議改為 `stderr`
- [x] 3.4 [後端] 在 `.env.example` 的 `SESSION_DRIVER=database` 旁加上說明，標示雲端建議改為 `redis`
- [x] 3.5 [後端] 在 `.env.example` 的 `QUEUE_CONNECTION=redis` 旁加上說明，確認此為雲端建議值

## 4. 文件更新

- [x] 4.1 [後端] 更新 `docs/STARTUP.md`，新增「部署模式」章節，明確列出兩組指令：
  - **本機 / VPS**：`docker compose up -d`（自動合併 override，含 phpmyadmin + mailpit）
  - **雲端正式環境**：`docker compose -f docker-compose.yml up -d`（排除開發工具）
  - 說明 `compose.override.yml` 已進版控，VPS `git pull` 後即可取得，不需手動建立

## 5. 手動驗證

- [x] 5.1 [整合測試] 在本機執行 `docker compose up -d`，確認 phpmyadmin（8081）與 mailpit（8025）仍可正常存取
- [x] 5.2 [整合測試] 執行 `docker compose -f docker-compose.yml up -d --remove-orphans`，確認 phpmyadmin 與 mailpit 不啟動
- [x] 5.3 [整合測試] 確認 `app` container 的 healthcheck 狀態在啟動後顯示 healthy（`docker compose ps` 查看）
