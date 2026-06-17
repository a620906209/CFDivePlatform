## ADDED Requirements

### Requirement: Scheduler 以獨立 container 執行
`docker-compose.yml` SHALL 定義 `scheduler` service，使用 `cfdive-platform` image，以 `php artisan schedule:work` 前台輪詢方式執行 Laravel Scheduler，取代原本在 app container 內的 cron daemon。

#### Scenario: Scheduler container 隨 app 啟動
- **WHEN** 執行 `docker compose up -d` 且 `app` container 已 healthy
- **THEN** `scheduler` container 啟動並持續執行 `php artisan schedule:work`

#### Scenario: App container 重啟時 scheduler 等待就緒
- **WHEN** `app` container 重啟中（unhealthy）
- **THEN** `scheduler` container 依 `depends_on` 等待 `app` healthy 後才啟動，避免 scheduler 在 PHP-FPM 未就緒時執行任務

### Requirement: App container 不再包含 cron daemon
`Dockerfile` SHALL 不安裝 `cron` 套件，`docker-entrypoint.sh` SHALL 不執行 `service cron start`，確保 app container 職責單一（只跑 PHP-FPM）。

#### Scenario: App container 啟動不啟動 cron
- **WHEN** `app` container 啟動並執行 entrypoint
- **THEN** 不存在 cron daemon 程序，`pgrep cron` 回傳 exit code 1
