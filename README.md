# CFDive Platform

潛水課程媒合平台 — 連結潛水教練與學員，提供課程瀏覽、線上預約、評價與通知等完整服務。

---

## 功能概覽

**會員（Member）**
- 註冊 / 登入（Email + Google OAuth）
- 瀏覽、搜尋、篩選潛水課程
- 查看課程時段並送出預約
- 對完成的課程留下評價（支援匿名、有幫助投票）
- 站內通知（Email + Polling）

**教練（Provider）**
- 課程 CRUD（含封面 + 相簿圖片上傳）
- 課程時段管理
- 預約管理（確認 / 拒絕 / 完成 / 取消）

**管理員（Admin）**
- 平台統計數據（會員數、教練數、課程數）
- 會員與教練帳號管理（啟用 / 停用 / 審核）
- 課程、預約、評價管理

---

## 技術棧

| 層級 | 技術 |
|------|------|
| 後端 | PHP 8.x / Laravel 11 |
| 前端 | Vue 3 |
| 資料庫 | MySQL 8.0 |
| 快取 | Redis（predis）|
| 認證 | Laravel Sanctum + Google OAuth |
| 容器 | Docker / Docker Compose |
| API 文件 | Swagger UI（l5-swagger）|

---

## API 文件

共 73 個端點，涵蓋：
- 認證（Email + Google OAuth）
- 公開課程查詢
- 會員預約 / 評價 / 通知
- 教練課程 / 時段 / 預約管理
- 管理員後台
