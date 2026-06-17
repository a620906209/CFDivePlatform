## Context

P0 完成後 VPS 目前狀態：
- Session：`SESSION_DRIVER=database`（MySQL）
- Log：`LOG_CHANNEL=stack`、`LOG_STACK=daily`（寫檔案）
- Scheduler：cron daemon 跑在 app container 內，entrypoint 啟動

三項都是雲端不友善的設定：多實例時 cron 重複執行、檔案 log 無法被雲端 aggregator 收集、DB session 擴容效率低。

**Bind mount 暫時保留**：`./:/var/www` 目前仍保留，移除 bind mount 屬於 P0 剩餘項目（需將 code bake 進 image），本 change 不處理。scheduler service 同樣沿用 bind mount，與 app/queue-worker 一致。

## Goals / Non-Goals

**Goals:**
- Session 存 Redis，擴容時不需共享 DB session table
- Log 走 stderr，`docker compose logs` 及雲端 aggregator 皆可收
- Scheduler 獨立 container，水平擴容 app 時不會重複跑定時任務
- Dockerfile 移除 cron 依賴，image 更精簡

**Non-Goals:**
- 不處理 S3 檔案儲存（P0 待辦）
- 不移除 bind mount（P0 待辦）
- 不動任何 PHP 業務邏輯、config/ 目錄下的 PHP 設定檔

## Pre-flight 確認（實作前已驗證）

| 項目 | 結果 |
|---|---|
| `config/logging.php` 有 `stderr` channel | ✅ 已存在（line 97），不需改 code |
| `config/session.php` 的 `driver` 讀 `.env` | ✅ `env('SESSION_DRIVER', 'database')`（line 21） |
| `SESSION_CONNECTION` | 預設 `null`（使用 default Redis connection），VPS Redis 已在跑，不需額外設定 |

## Decisions

### D1：Scheduler 用 `php artisan schedule:work` 而非 crontab

`schedule:work` 是 Laravel 10+ 內建指令，在前台以無限迴圈每分鐘執行一次 `schedule:run`，不需要 cron daemon。符合 12-factor「一個 container 跑一個 process」原則。

**`depends_on` 取捨**：scheduler 設 `depends_on: app: condition: service_healthy`，確保首次啟動時 PHP-FPM 就緒後 scheduler 才開始執行。注意：`depends_on` 只控制啟動順序，不控制運行時依賴——app container 重啟時 scheduler **不會**跟著重啟，會繼續跑；若 app 還沒 ready 而 scheduler 已觸發任務，任務本身可能失敗，但 scheduler 程序不會崩潰。這是可接受的，定時任務本身應有冪等與錯誤處理。

```yaml
scheduler:
  image: cfdive-platform
  container_name: cfdive-scheduler
  restart: unless-stopped
  working_dir: /var/www/
  command: php artisan schedule:work
  volumes:
    - ./:/var/www
  environment:
    - DB_CONNECTION=mysql
    - DB_HOST=db
    - DB_PORT=3306
    - DB_DATABASE=${DB_DATABASE:-CFDivePlatform}
    - DB_USERNAME=${DB_USERNAME:-cfdiveuser}
    - DB_PASSWORD=${DB_PASSWORD}
  networks:
    - cfdive-network
  depends_on:
    app:
      condition: service_healthy
```

### D2：Log 改 `LOG_CHANNEL=stderr`

`config/logging.php` 已內建 `stderr` channel（`php://stderr`），Docker 自動對應到 container stderr stream，`docker compose logs app` 即可看到。不需新增任何 PHP 設定。

`.env.example` 同步移除 `LOG_STACK` 與 `LOG_DAILY_DAYS`（改 stderr 後這兩個無作用，留著造成混淆）。

**Config cache 注意**：VPS `.env` 改完後需清除 config cache 才能生效：
```bash
docker compose exec app php artisan config:clear
```
或直接 `docker compose up -d --build`（entrypoint 會自動 `config:clear`）。

### D3：Session 改 Redis，不需建新資料表

Redis 已在跑（`cfdive-redis`），`config/session.php` 讀 `.env`，開箱即用。`sessions` table 保留不刪（避免 rollback 需要重建）。

### D4：VPS `.env` 更新方式

選手動 SSH 更新（不進 deploy.yml 自動化），原因：`.env` 含機敏值，CI 自動覆寫風險高；此為一次性變更。

## Risks / Trade-offs

- **[Risk] Session 失效**：改 driver 後所有現有 session 立即失效，用戶需重新登入。  
  → **Mitigation**：排低流量時段；VPS `.env` 改完後直接 rebuild，縮短空窗。

- **[Risk] Rebuild image 期間短暫停機**：Dockerfile 移除 cron 需 rebuild。  
  → **Mitigation**：`docker compose up -d --build` 滾動更新，停機窗口約 30 秒。

- **[Risk] Config cache 未清除**：`.env` 改了但 `bootstrap/cache/config.php` 仍是舊值，session/log 不生效。  
  → **Mitigation**：Migration Plan 步驟明確包含 `config:clear`；entrypoint 在 rebuild 後也會自動清除。

- **[Risk] Log 遺失歷史**：改 stderr 後 `storage/logs/` 不再更新，但舊檔保留在磁碟。  
  → **Mitigation**：Sentry 持續收錯誤；需要查歷史時直接看舊檔案。

## Acceptance Criteria

- [ ] `docker compose ps` 顯示 `cfdive-scheduler` 容器 Up
- [ ] `docker compose exec app sh -c "which cron 2>/dev/null || echo 'cron binary not found'"` 輸出 `cron binary not found`（binary 已從 image 移除）
- [ ] 呼叫任意 API 後，`docker compose logs app` 有日誌輸出（stderr log 生效）
- [ ] `storage/logs/` 下無新增日誌檔（舊檔保留）
- [ ] 登入後 `docker compose exec redis redis-cli keys "laravel_session*"` 有輸出（session 走 Redis）

## Migration Plan

**程式碼（PR 合併前）：**
1. `Dockerfile`：移除 cron 安裝與 crontab 設定
2. `docker/php/docker-entrypoint.sh`：移除 `service cron start`
3. `docker-compose.yml`：新增 `scheduler` service
4. `.env.example`：`SESSION_DRIVER=redis`、`LOG_CHANNEL=stderr`、移除 `LOG_STACK`/`LOG_DAILY_DAYS`
5. `docs/STARTUP.md`：補充 scheduler、log 查看、VPS 操作說明

**VPS 維護窗口（PR merge + CI/CD 完成後）：**
```bash
# SSH 進 VPS
cd /root/myproject/CFDivePlatform

# 1. 更新 .env
sed -i 's/SESSION_DRIVER=database/SESSION_DRIVER=redis/' .env
sed -i 's/LOG_CHANNEL=stack/LOG_CHANNEL=stderr/' .env
sed -i '/^LOG_STACK=/d' .env
sed -i '/^LOG_DAILY_DAYS=/d' .env

# 2. Rebuild + 重啟（entrypoint 自動 config:clear）
docker compose up -d --build

# 3. 驗證
docker compose ps
docker compose logs app | tail -20
docker compose exec redis redis-cli keys "laravel_session*"
```

## Rollback Plan

Session/Log 還原（不需 rebuild）：
```bash
sed -i 's/SESSION_DRIVER=redis/SESSION_DRIVER=database/' .env
sed -i 's/LOG_CHANNEL=stderr/LOG_CHANNEL=stack/' .env
echo "LOG_STACK=daily" >> .env
echo "LOG_DAILY_DAYS=14" >> .env
docker compose exec app php artisan config:clear
docker compose restart app
```

Scheduler 還原（需 rebuild）：
```bash
git revert <commit-hash>
docker compose up -d --build
```
