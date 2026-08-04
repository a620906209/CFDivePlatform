## MODIFIED Requirements

### Requirement: Scheduler 以獨立 container 執行
`docker-compose.yml` SHALL 定義 `scheduler` service，使用 `cfdive-platform` image（其中已於 build 階段內嵌完整程式碼，不依賴 `./:/var/www` bind mount），以 `php artisan schedule:work` 前台輪詢方式執行 Laravel Scheduler，取代原本在 app container 內的 cron daemon。

#### Scenario: Scheduler container 隨 app 啟動
- **WHEN** 執行 `docker compose up -d` 且 `app` container 已 healthy
- **THEN** `scheduler` container 啟動並持續執行 `php artisan schedule:work`

#### Scenario: App container 重啟時 scheduler 等待就緒
- **WHEN** `app` container 重啟中（unhealthy）
- **THEN** `scheduler` container 依 `depends_on` 等待 `app` healthy 後才啟動，避免 scheduler 在 PHP-FPM 未就緒時執行任務

#### Scenario: Scheduler 不依賴主機 bind mount 取得程式碼
- **WHEN** 執行 `docker compose -f docker-compose.yml up -d`（production，不含 override）
- **THEN** `scheduler` container 執行的排程任務程式碼完全來自 `cfdive-platform` image build 時內嵌的內容，與主機端程式碼目錄是否存在無關
