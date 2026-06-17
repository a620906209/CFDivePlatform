## Context

CFDivePlatform 跑在單台 VPS 上，全部服務以 Docker Compose 管理。現有設定有三個雲端就緒問題：
1. `app` service healthcheck 用 `php -v`，只驗證 binary 存在，無法確認 PHP-FPM 是否監聽
2. `phpmyadmin`、`mailpit` 這兩個開發/管理工具寫死在 `docker-compose.yml`，雲端正式環境不應存在
3. `.env.example` 的 `QUEUE_CONNECTION=database` 與 VPS 實際使用 Redis 不一致，新環境按範本初始化會跑在效能較差的 DB queue

本次只改 compose 設定和 `.env.example`，不觸碰任何 PHP 程式碼。

## Goals / Non-Goals

**Goals:**
- healthcheck 改為確認 PHP-FPM FastCGI port 9000 正常監聽，比 `php -v` 更能反映 FPM 是否就緒
- 透過 `compose.override.yml` 機制隔離開發工具，VPS 行為不變，雲端部署只需 `-f docker-compose.yml`
- `.env.example` 的 queue 預設值與雲端慣例對齊，並補充雲端必要變數的說明

**Non-Goals:**
- 不修改 Session driver（留待 P1 change）
- 不修改 Log 輸出方式（留待 P1 change）
- 不修改 Scheduler 架構（留待 P1 change）
- 不改任何 PHP 程式碼或 image 內容
- 不處理 S3 檔案儲存（P0 項目，需獨立 change）

## Decisions

### D1：healthcheck 用 `nc -z 127.0.0.1 9000` 檢查 PHP-FPM port

**為什麼不用 `/health` HTTP 端點：**
app container 跑的是 PHP-FPM（FastCGI，port 9000），容器內**沒有 HTTP server**。
`/health` 路由必須經過 Nginx 才能到達 PHP-FPM，而 Nginx 是另一個 container。
所以 `curl` 或 `wget` 打 `http://127.0.0.1/health` 在 app container 內部完全打不到任何東西。

**為什麼不用 `wget`：**
Dockerfile 安裝清單確認 `wget` 未安裝（僅裝 `curl`）。Nginx container 的 `wget` 是 busybox-wget（Alpine），兩者環境不同，不可混用。

**選定方案：**
```yaml
healthcheck:
  test: ["CMD-SHELL", "nc -z 127.0.0.1 9000 || exit 1"]
```
`netcat-openbsd` 已在 Dockerfile 安裝。`nc -z` 只做 TCP connect，確認 PHP-FPM 的 FastCGI port 正在監聽，是 app container 內部能做到最準確的 FPM 就緒判斷。

**能力邊界：** 此 healthcheck 確認 PHP-FPM 程序已啟動並監聽 port，但不驗證 Nginx；Nginx 有自己的獨立 healthcheck。

### D2：`compose.override.yml` 放在 repo 根目錄並加進版控

**選項 A**：放進 `.gitignore`，每台機器手動建立  
**選項 B**：放進 repo，所有環境共用

選 B。`compose.override.yml` 只定義開發工具（phpmyadmin、mailpit），不含任何 secret 或環境特定值，放進版控讓新成員 `git clone` 後直接可用。雲端部署 CI 指定 `-f docker-compose.yml` 排除即可。

### D3：`mailpit` 同步移入 override

`mailpit` 與 `phpmyadmin` 性質相同（開發輔助工具），一起移入 override，保持 `docker-compose.yml` 只含正式服務。

## Risks / Trade-offs

- **[Risk] healthcheck 只驗證 TCP 連線，不驗證請求成功**：`nc -z` 確認 port 開著，但不確認 PHP-FPM 能正確處理 FastCGI 請求。  
  → **Mitigation**：對 `depends_on` 的用途（等待 FPM 啟動再起 Nginx）已足夠；若要更深層驗證需用 `cgi-fcgi` 工具（過度工程，暫不引入）。

- **[Risk] VPS 若沒有 `compose.override.yml`**：移除後首次 `docker compose up` 會少 phpmyadmin/mailpit。  
  → **Mitigation**：`compose.override.yml` 已進版控，VPS 執行 `git pull` 即可取得，不需手動建立。STARTUP.md 同步更新說明此流程。

## Migration Plan

1. `docker-compose.yml`：改 healthcheck、移除 phpmyadmin service、移除 mailpit service
2. 新增 `compose.override.yml`：加入 phpmyadmin、mailpit
3. `.env.example`：改 QUEUE_CONNECTION 預設值、補說明
4. `STARTUP.md`：新增「部署模式」章節，明確區分：
   - **VPS（預設）**：`docker compose up -d`，自動合併 override，含 phpmyadmin + mailpit
   - **雲端正式環境**：`docker compose -f docker-compose.yml up -d`，排除開發工具
5. **VPS 部署**：執行 `git pull` 取得版控內的 `compose.override.yml`，不需 rebuild image，`docker compose up -d` 即可

## Open Questions

無。本次改動範圍明確，無待決事項。
