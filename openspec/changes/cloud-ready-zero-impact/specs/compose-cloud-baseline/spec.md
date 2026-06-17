## ADDED Requirements

### Requirement: App service healthcheck 驗證 PHP-FPM FastCGI port
`docker-compose.yml` 的 `app` service healthcheck SHALL 使用 `nc -z 127.0.0.1 9000` 確認 PHP-FPM FastCGI port 正在監聽，而非執行 `php -v`（只驗證 binary 存在）。

注意：app container 內無 HTTP server，無法透過 HTTP 打 `/health`；`netcat-openbsd` 已在 Dockerfile 安裝。

#### Scenario: PHP-FPM 啟動後 healthcheck 通過
- **WHEN** `app` container 啟動且 PHP-FPM 程序已監聽 port 9000
- **THEN** `nc -z 127.0.0.1 9000` 回傳 exit code 0，container 標記為 healthy

#### Scenario: PHP-FPM 未啟動時 healthcheck 失敗
- **WHEN** `app` container 啟動但 PHP-FPM 程序尚未就緒（port 9000 未開）
- **THEN** `nc -z 127.0.0.1 9000` 回傳非 0 exit code，container 維持 unhealthy 狀態直到重試成功

### Requirement: 正式 compose 不含開發工具服務
`docker-compose.yml` SHALL 不包含 `phpmyadmin` 及 `mailpit` service 定義。這兩個服務屬開發/管理工具，不得出現在正式雲端部署的 compose 設定中。

#### Scenario: 只使用 docker-compose.yml 啟動
- **WHEN** 執行 `docker compose -f docker-compose.yml up -d`
- **THEN** 啟動的服務不包含 `phpmyadmin`（port 8081）及 `mailpit`（port 8025）

### Requirement: 開發工具服務透過 compose.override.yml 提供
`compose.override.yml` SHALL 定義 `phpmyadmin` 與 `mailpit` service，供本機開發與 VPS 管理使用。

#### Scenario: 有 override 檔時開發工具正常啟動
- **WHEN** 專案根目錄存在 `compose.override.yml` 且執行 `docker compose up -d`（不指定 -f）
- **THEN** Docker Compose 自動合併 override，`phpmyadmin`（port 8081）與 `mailpit`（port 8025）一同啟動

#### Scenario: 雲端部署排除開發工具
- **WHEN** CI/CD 執行 `docker compose -f docker-compose.yml up -d`（明確指定單一檔案）
- **THEN** `phpmyadmin` 與 `mailpit` 不啟動，不佔用任何 port 或資源
