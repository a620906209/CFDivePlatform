# CFDivePlatform 啟動指令

## 部署模式

專案根目錄有兩個 compose 檔：

| 檔案 | 用途 |
|------|------|
| `docker-compose.yml` | 正式服務（app、nginx、frontend、db、redis、reverb、queue-worker、scheduler） |
| `compose.override.yml` | 開發工具（phpmyadmin、mailpit），已進版控 |

**本機 / VPS（預設）**：Docker Compose 自動合併兩檔，開發工具一同啟動：

```bash
docker compose up -d
```

**雲端正式環境**：明確指定單一檔案，排除開發工具：

```bash
docker compose -f docker-compose.yml up -d
```

> VPS 首次部署或更新後執行 `git pull` 即可取得最新的 `compose.override.yml`，不需手動建立。

---

## 專案位置

```powershell
C:\laragon\www\CFDivePlatform
```

## 1. 進入專案目錄

```powershell
cd C:\laragon\www\CFDivePlatform
```

## 2. 準備 `.env`

如果尚未建立 `.env`：

```powershell
copy .env.example .env
```

確認 `.env` 至少設定以下項目：

```env
APP_KEY=
DB_DATABASE=CFDivePlatform
DB_USERNAME=cfdiveuser
DB_PASSWORD=your_password
MYSQL_ROOT_PASSWORD=your_root_password

REVERB_APP_ID=your_reverb_app_id
REVERB_APP_KEY=your_reverb_app_key
REVERB_APP_SECRET=your_reverb_app_secret
VITE_REVERB_APP_KEY=your_reverb_app_key
```

如果 `APP_KEY` 是空的，可在容器啟動後執行：

```powershell
docker compose exec app php artisan key:generate
```

## 3. 啟動 Docker 服務

第一次啟動，或 Dockerfile / compose 設定有變更時：

```powershell
docker compose up -d --build
```

平常啟動：

```powershell
docker compose up -d
```

## 4. 查看服務狀態

```powershell
docker compose ps
```

查看所有服務 log：

```powershell
docker compose logs -f
```

只查看 Laravel App log：

```powershell
docker compose logs -f app
```

## 5. 初始化與維護指令

容器啟動時會自動執行部分初始化流程：

- 安裝 Composer 依賴
- 等待 MySQL 啟動
- 執行 migration
- 清除 Laravel cache
- 建立 storage link
- 產生 Swagger 文件

如需手動執行，可使用：

```powershell
docker compose exec app composer install
docker compose exec app php artisan migrate
docker compose exec app php artisan storage:link
docker compose exec app php artisan l5-swagger:generate
```

清除 Laravel 快取：

```powershell
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear
docker compose exec app php artisan route:clear
docker compose exec app php artisan view:clear
```

## 6. 建立管理員帳號

```powershell
docker compose exec app php artisan app:create-admin "Admin" "admin@example.com" --password="your_password"
```

密碼至少需要 8 碼。

## 7. 前端 Vite 開發模式

如果要使用本機 Vite 開發模式：

```powershell
npm install
npm run dev
```

正式建置：

```powershell
npm run build
```

Docker Compose 內也有 `frontend` 服務，預設網址為：

```text
http://localhost:5173
```

## 8. 重啟 frontend

如果 frontend 是透過 Docker Compose 啟動：

```powershell
docker compose restart frontend
```

如果有修改 frontend 的 Dockerfile、環境變數或 build 內容，建議重建：

```powershell
docker compose up -d --build frontend
```

如果是使用本機 Vite 開發模式，先在原本終端機按 `Ctrl + C` 停止，再重新執行：

```powershell
npm run dev
```

## 9. 服務網址

| 服務 | URL |
| --- | --- |
| API / Laravel + Nginx | <http://localhost:8080> |
| Frontend Docker | <http://localhost:5173> |
| phpMyAdmin | <http://localhost:8081> |
| Mailpit | <http://localhost:8025> |
| Reverb WebSocket | <ws://localhost:8085> |
| Health Check | <http://localhost:8080/health> |

## 10. Log 查看

Laravel 日誌走 stderr，透過 Docker 查看（不再有 `storage/logs/` 檔案）：

```bash
# 即時串流
docker compose logs -f app

# 查看最後 50 行
docker compose logs app --tail=50
```

## 11. VPS 維護窗口操作（P1 部署）

PR merge 後 CI/CD 自動跑 composer/migrate/cache，完成後 SSH 進 VPS 執行：

```bash
cd /root/myproject/CFDivePlatform

# 1. 更新 .env（一次性）
sed -i 's/SESSION_DRIVER=database/SESSION_DRIVER=redis/' .env
sed -i 's/LOG_CHANNEL=stack/LOG_CHANNEL=stderr/' .env
sed -i '/^LOG_STACK=/d' .env
sed -i '/^LOG_DAILY_DAYS=/d' .env

# 2. Rebuild + 重啟（entrypoint 自動 config:clear）
docker compose up -d --build

# 3. 驗證
docker compose ps                                          # scheduler 應顯示 Up
docker compose logs app --tail=20                         # 應有 stderr 日誌
docker compose exec redis redis-cli keys "laravel_session*"  # 登入後應有 session key
docker compose exec app sh -c "which cron 2>/dev/null || echo 'cron binary not found'"  # 應輸出 not found
```

**Rollback（若需還原）：**

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

## 12. 停止服務

停止容器，但保留資料庫 volume：

```powershell
docker compose down
```

停止容器並移除 volume，會清除 MySQL 資料：

```powershell
docker compose down -v
```

## 最短啟動流程

```powershell
cd C:\laragon\www\CFDivePlatform
copy .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose ps
```

啟動完成後開啟：

```text
http://localhost:8080
```
