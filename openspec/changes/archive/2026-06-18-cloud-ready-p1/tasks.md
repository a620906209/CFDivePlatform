## 1. Dockerfile 移除 cron

- [x] 1.1 [後端] 從 `Dockerfile` 的 `apt-get install -y` 清單移除 `cron`
- [x] 1.2 [後端] 從 `Dockerfile` 移除寫入 `/etc/cron.d/laravel-scheduler` 的 `RUN echo ...` 指令
- [x] 1.3 [後端] 從 `Dockerfile` 移除 `crontab /etc/cron.d/laravel-scheduler` 指令

## 2. docker-entrypoint.sh 移除 cron 啟動

- [x] 2.1 [後端] 從 `docker/php/docker-entrypoint.sh` 移除 `service cron start || cron || true` 這一行

## 3. docker-compose.yml 新增 scheduler service

- [x] 3.1 [後端] 在 `docker-compose.yml` 新增 `scheduler` service，設定如下：
  - `image: cfdive-platform`、`container_name: cfdive-scheduler`、`restart: unless-stopped`
  - `working_dir: /var/www/`、`command: php artisan schedule:work`
  - `volumes: - ./:/var/www`
  - 環境變數：`DB_CONNECTION`、`DB_HOST=db`、`DB_PORT`、`DB_DATABASE`、`DB_USERNAME`、`DB_PASSWORD`（與 queue-worker 相同）
  - `networks: - cfdive-network`
  - `depends_on: app: condition: service_healthy`

## 4. .env.example 更新

- [x] 4.1 [後端] 將 `.env.example` 的 `SESSION_DRIVER=database  # 雲端建議改為 redis` 改為 `SESSION_DRIVER=redis`（升級為預設值，移除說明）
- [x] 4.2 [後端] 將 `.env.example` 的 `LOG_CHANNEL=stack` 改為 `LOG_CHANNEL=stderr`
- [x] 4.3 [後端] 從 `.env.example` 移除 `LOG_STACK=daily` 與 `LOG_DAILY_DAYS=14` 兩行（改 stderr 後無作用）

## 5. 文件更新

- [x] 5.1 [後端] 更新 `docs/STARTUP.md`，補充以下內容：
  - 服務清單新增 `scheduler`（`docker compose ps` 應見 `cfdive-scheduler`）
  - Log 查看方式：`docker compose logs -f app`（不再有 `storage/logs/` 檔案）
  - **VPS 維護窗口操作**：說明 `sed` 更新 `.env` 的四個指令 + `docker compose up -d --build`
  - **Config cache 注意**：`.env` 改完須清 cache（rebuild 時 entrypoint 自動處理）
  - **Rollback 步驟**：Session/Log 還原指令（不需 rebuild）+ Scheduler 還原（需 rebuild）

## 6. 手動驗證（本機，不需 VPS .env）

- [x] 6.1 [整合測試] 在本機 rebuild 後確認 `cfdive-scheduler` 正常啟動：`docker compose ps` 顯示 scheduler Up
- [x] 6.2 [整合測試] 確認 app container 無 cron：`docker compose exec app sh -c "which cron 2>/dev/null || echo 'cron binary not found'"`（cron binary 已從 image 移除，輸出 `cron binary not found`）

## 7. 手動驗證（VPS，需更新 .env 後）

- [x] 7.1 [整合測試] 確認 log 走 stderr：呼叫任意 API 後 `docker compose logs app | tail -20` 有日誌輸出
- [x] 7.2 [整合測試] 確認 `storage/logs/` 無新增日誌檔（舊檔保留）
- [x] 7.3 [整合測試] 確認 session 走 Redis：登入後 `docker compose exec redis redis-cli keys "cfdiveplatform_database_*"` 有輸出（prefix 為 APP_NAME_database_，非 laravel_session*）
