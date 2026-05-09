## Why

CFDivePlatform 後端 API 已具備會員認證與基礎資料管理能力，但目前缺乏任何前端介面，使用者無法透過瀏覽器使用平台。建立獨立的會員端前端 MVP，讓潛水愛好者能瀏覽、搜尋課程，是平台從「有 API」走向「可用產品」的第一步。

## What Changes

- **新建獨立前端 repo**（Vue 3 + Vite + Tailwind CSS），與此 Laravel repo 分開部署
- **後端新增 Diving Offers 公開 API**：課程列表（含搜尋/篩選）與課程詳情兩支 endpoint
- 前端實作六個頁面：首頁、課程列表、課程詳情、登入、註冊、會員個人資料
- 前端整合現有 Auth API（Sanctum token）與 Google OAuth redirect 流程

## Capabilities

### New Capabilities

- `diving-offers-api`：後端提供公開的潛水課程列表與詳情 API，支援關鍵字搜尋、地區與標籤篩選
- `member-portal-ui`：獨立前端應用，包含課程瀏覽、認證流程、會員個人資料等完整使用者介面

### Modified Capabilities

(無)

## Impact

**後端（此 Laravel repo）**
- 新增 `DivingOfferController` 與兩條 API 路由
- `diving_offers` 資料表已存在，僅需新增 Model fillable 與 Controller

**前端（新 repo）**
- 獨立 Vue 3 repo，需另行建立專案結構
- 依賴後端 API base URL（透過 `.env` 設定）
- CORS 需在 Laravel 端設定允許前端 origin
