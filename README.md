# CFDive Platform

潛水課程媒合平台 — 連結潛水教練與學員，提供課程瀏覽、線上預約、即時訊息、評價與通知等完整服務。

---

## 功能概覽

**會員（Member）**
- 註冊 / 登入（Email + Google OAuth）
- Token 自動續期（401 refresh-then-retry，sessionStorage 儲存）
- 瀏覽、搜尋、篩選潛水課程
- 查看課程時段並送出預約
- 與教練即時訊息（文字 + 圖片，含已讀回執）
- 對完成的課程留下評價（支援匿名、有幫助投票）
- 站內通知（Bell Icon 即時更新 + 瀏覽器推播）

**教練（Provider）**
- 課程 CRUD（含封面 + 相簿圖片上傳，伺服器端壓縮）
- 課程時段管理
- 預約管理（確認 / 拒絕 / 完成 / 取消）
- 與學員即時訊息
- 證照上傳與教練資格送審

**管理員（Admin）**
- 平台統計數據（會員數、教練數、課程數）
- 會員與教練帳號管理（啟用 / 停用）
- 教練資格審核（送審 / 通過 / 駁回 / 撤銷）
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
| 認證 | Laravel Sanctum + Google OAuth + Token Refresh |
| 容器 | Docker / Docker Compose |
| API 文件 | Swagger UI（l5-swagger）|
| 錯誤監控 | Sentry |
| CI/CD | Gitea Actions（自動部署至 VPS）|
| 測試 | PHPUnit / Laravel Feature & Unit Tests |

---

## API 文件

Swagger UI（由 l5-swagger 生成）涵蓋所有端點，包含：
- 認證（Email + Google OAuth）、Token 刷新
- 公開課程查詢
- 會員預約 / 即時訊息 / 評價 / 通知
- 教練課程 / 時段 / 預約管理 / 資格驗證申請（證照送審）
- 管理員後台

| 環境 | Swagger UI |
|------|-----------|
| 本地 | http://localhost:8080/api/documentation |
| 生產 | https://api.hank-space.com/api/documentation |

---

## 健康檢查

```
GET /health
```

回傳 DB、Redis、Cache 狀態，供 UptimeRobot 監控使用。全部正常回 200，任一異常回 503。

---

## 本地開發

**測試用帳號**

| 角色 | 帳號 | 密碼 | 用途 |
|------|------|------|------|
| 會員 | Guest@cfdive.com | guestpassword | 體驗課程瀏覽、預約、會員預約紀錄 |
| 教練 | Guest_Coach@cfdive.com | coachpassword | 體驗課程管理、時段管理、預約管理 |

**教練頁入口**

目前首頁尚未提供明顯導引到教練後台的入口。若要體驗教練功能，請直接開啟 `/coach/login`，使用上方教練試用帳號登入後會進入 `/coach/dashboard`。

| 服務 | 本地 | 生產 |
|------|------|------|
| 前端 | http://localhost:8080 | https://app.hank-space.com |
| API | http://localhost:8080/api | https://api.hank-space.com/api |
| Swagger UI | http://localhost:8080/api/documentation | https://api.hank-space.com/api/documentation |
| phpMyAdmin | http://localhost:8081 | http://\<vps-ip\>:8081 |
| Mailpit | http://localhost:8025 | http://\<vps-ip\>:8025 |
| Reverb WebSocket | ws://localhost:8085 | wss://ws.hank-space.com |
