# CFDive Platform

潛水課程媒合平台 — 連結潛水教練與學員，提供課程瀏覽、線上預約、即時訊息、評價與通知等完整服務。

---

## 功能概覽

**會員（Member）**
- 註冊 / 登入（Email + Google OAuth）
- 瀏覽、搜尋、篩選潛水課程
- 查看課程時段並送出預約
- 與教練即時訊息（文字 + 圖片，含已讀回執）
- 對完成的課程留下評價（支援匿名、有幫助投票）
- 站內通知（Bell Icon 即時更新 + 瀏覽器推播）

**教練（Provider）**
- 課程 CRUD（含封面 + 相簿圖片上傳）
- 課程時段管理
- 預約管理（確認 / 拒絕 / 完成 / 取消）
- 與學員即時訊息

**管理員（Admin）**
- 平台統計數據（會員數、教練數、課程數）
- 會員與教練帳號管理（啟用 / 停用 / 審核）
- 課程、預約、評價管理

---

## 技術棧

| 層級 | 技術 |
|------|------|
| 後端 | PHP 8.x / Laravel 11 |
| 前端 | Vue 3 + Vite + Tailwind CSS |
| 資料庫 | MySQL 8.0 |
| 快取 | Redis |
| 即時通訊 | Laravel Reverb（WebSocket，`wss://ws.hank-space.com`）|
| 認證 | Laravel Sanctum + Google OAuth |
| 容器 | Docker / Docker Compose |
| API 文件 | Swagger UI（l5-swagger）|
| 錯誤監控 | Sentry |
| CI/CD | Gitea Actions（自動部署至 VPS）|

---

## API 文件

共 73 個端點，涵蓋：
- 認證（Email + Google OAuth）
- 公開課程查詢
- 會員預約 / 訊息 / 評價 / 通知
- 教練課程 / 時段 / 預約管理
- 管理員後台

---

## 健康檢查

```
GET /health
```

回傳 DB、Redis、Cache 狀態，供 UptimeRobot 監控使用。全部正常回 200，任一異常回 503。

---

## 本地開發

**啟動**

```bash
docker compose up -d
```

| 服務 | URL |
|------|-----|
| API / 前端 | http://localhost:8080 |
| phpMyAdmin | http://localhost:8081 |
| Mailpit | http://localhost:8025 |
| Reverb WebSocket | ws://localhost:8085 |

**環境設定**

複製 `.env.example` 為 `.env`，填入以下必要值：

```env
REVERB_APP_KEY=        # 32 字元隨機字串
REVERB_APP_SECRET=     # 32 字元隨機字串
SENTRY_LARAVEL_DSN=    # Sentry DSN（留空則停用）
```
