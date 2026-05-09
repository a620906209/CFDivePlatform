## Context

後端（Laravel + Sanctum）已有會員認證、個人資料、Google OAuth 等 API，但 `diving_offers` 表雖已建立，尚無對應的公開 API。前端完全從零開始，需建立獨立 repo，透過 HTTP 呼叫後端 API。兩端分離部署，前端在開發期間以 `http://localhost:5173` 運行，後端以 `http://localhost:80`（Laragon）運行。

## Goals / Non-Goals

**Goals:**
- 建立可運行的 Vue 3 前端 MVP，讓會員能瀏覽和搜尋潛水課程
- 後端補齊 `diving-offers` 公開 API（列表 + 詳情）
- 整合現有 Auth API（登入、註冊、Google OAuth）
- 會員個人資料頁可讀取與更新

**Non-Goals:**
- 預約/訂單系統（本次不做）
- 金流整合
- Provider 端介面
- Admin 後台
- SSR / SEO 優化
- 自動化測試

## Decisions

### D1：前端獨立 repo，不用 Inertia.js

**決定**：前端為獨立 Vue 3 SPA repo，透過 REST API 與後端溝通。

**理由**：前後端分離讓未來可獨立部署、擴展。Inertia.js 雖整合方便，但綁定 Laravel monolith，不符長期架構方向。

**替代方案**：Inertia.js（Laravel + Vue 同 repo）→ 捨棄，因為部署彈性不足。

---

### D2：Auth 策略 — Sanctum Token（Bearer）

**決定**：前端登入後儲存 Bearer token 於 `localStorage`，每次請求附加 `Authorization: Bearer <token>` header。

**理由**：Sanctum 在 SPA 跨域場景下有兩種模式（cookie-based SPA 和 token-based API）。Token 模式對跨 origin 最簡單，不需設定 session cookie、CSRF。

**替代方案**：Sanctum SPA cookie 模式 → 需要同 domain 或複雜 CORS cookie 設定，開發期間繁瑣。

---

### D3：樣式策略 — Tailwind CSS，無 UI 框架

**決定**：純 Tailwind CSS 配合自定義 Vue 組件。

**理由**：設計彈性最高，不被 Element Plus / Naive UI 的元件語言綁定，適合建立品牌識別感強的潛水平台。

---

### D4：狀態管理 — Pinia（只管 Auth 狀態）

**決定**：僅用 Pinia 管理認證狀態（user、token、isLoggedIn）。課程列表等資料用組件本地 `ref` + Axios 取得，不過度使用全域 store。

**理由**：MVP 階段避免過早引入複雜的全域狀態。

---

### D5：Google OAuth Callback 改為 Redirect

**決定**：`SocialAuthController::handleGoogleCallback()` 現行實作回傳 JSON response，但前後端分離時瀏覽器停在後端 origin（`:80`），前端無法取得 token。**必須改為 redirect 至前端 callback 頁面。**

**OAuth 完整流程契約：**

```
cf-dive-frontend (:5173)    CFDivePlatform (:80)        Google
        |                           |                       |
        | 點擊「Google 登入」        |                       |
        | window.location =         |                       |
        | :80/api/auth/google/      |                       |
        | redirect                  |                       |
        |──────────────────────────▶|                       |
        |                           | stateless()->redirect()|
        |                           |──────────────────────▶|
        |                           |  302 → Google 同意頁  |
        |◀──────────────────────────|                       |
        | (使用者在 Google 同意)     |                       |
        |                           |◀──────────────────────|
        |                           | callback?code=xxx      |
        |                           |                       |
        |                           | 1. 取得 Google user   |
        |                           | 2. 建立/查詢 User     |
        |                           | 3. 建立 Sanctum token |
        |                           |                       |
        |  [成功] 302 redirect →    |                       |
        |  :5173/auth/callback      |                       |
        |  ?token=<sanctum_token>   |                       |
        |◀──────────────────────────|                       |
        |                           |                       |
        |  [失敗] 302 redirect →    |                       |
        |  :5173/login              |                       |
        |  ?error=oauth_failed      |                       |
        |◀──────────────────────────|                       |
        |                           |                       |
        | /auth/callback 頁面：      |                       |
        | 讀取 ?token=              |                       |
        | 存入 Pinia + localStorage  |                       |
        | 導向 /courses             |                       |
```

**後端改動**：`handleGoogleCallback()` 末段改為：
```php
// 成功
return redirect(env('FRONTEND_URL') . '/auth/callback?token=' . $token);
// 失敗（catch 區塊）
return redirect(env('FRONTEND_URL') . '/login?error=oauth_failed');
```

**前端新增**：`/auth/callback` 路由對應 `AuthCallbackView.vue`，讀取 `?token=` 後存入 store，再導向 `/courses`。

---

## Contracts

### Contract 1 — API Schema

所有 API response 遵循統一結構：
```
成功：{ "status": true,  "message": "...", "data": {...} 或 [...] }
失敗：{ "status": false, "message": "錯誤說明" }
```

---

#### `GET /api/diving-offers`（公開，無需 auth）

```
Query Parameters:
  q        : string   搜尋 title / location / spot（LIKE 模糊匹配）
  region   : string   完全匹配 region 欄位
  tag      : string   LIKE 匹配 tag 欄位
  per_page : integer  default=12, max=50
  page     : integer  default=1

Response 200:
{
  "status": true,
  "data": [
    {
      "id":          1,
      "title":       "墾丁海底探險",
      "location":    "屏東縣",
      "spot":        "龍坑生態保護區",
      "rating":      4.8,
      "reviews":     32,
      "price":       2500,
      "badges":      ["PADI認證", "含裝備"],   ← JSON decode 後為陣列
      "description": "課程描述文字...",
      "tag":         "初學者",
      "region":      "南部",
      "created_at":  "2025-05-01T00:00:00.000000Z"
    }
  ],
  "meta": {
    "total":        48,
    "per_page":     12,
    "current_page": 1,
    "last_page":    4
  }
}
```

---

#### `GET /api/diving-offers/{id}`（公開，無需 auth）

```
Response 200:
{
  "status": true,
  "data": {
    "id":          1,
    "title":       "墾丁海底探險",
    "location":    "屏東縣",
    "spot":        "龍坑生態保護區",
    "rating":      4.8,
    "reviews":     32,
    "price":       2500,
    "badges":      ["PADI認證", "含裝備"],
    "description": "課程描述文字...",
    "tag":         "初學者",
    "region":      "南部",
    "created_at":  "2025-05-01T00:00:00.000000Z"
  }
}

Response 404:
{ "status": false, "message": "課程不存在" }
```

---

#### `POST /api/member/login`（現有 API，前端需遵守）

```
Request Body (application/json):
{ "email": "user@example.com", "password": "password123" }

Response 200:
{
  "status": true,
  "message": "登入成功",
  "data": {
    "user": {
      "id":    1,
      "name":  "王小明",
      "email": "user@example.com",
      "role":  "member"
    },
    "token":      "1|abcdef...",
    "token_type": "Bearer"
  }
}

Response 401/422:
{ "status": false, "message": "帳號或密碼錯誤" }
```

---

#### `POST /api/member/register`（現有 API）

```
Request Body (application/json):
{ "name": "王小明", "email": "user@example.com", "password": "password123", "password_confirmation": "password123" }

Response 201:
{
  "status": true,
  "message": "註冊成功",
  "data": { "user": { "id": 1, "name": "王小明", "email": "...", "role": "member" } }
}

Response 422:
{ "status": false, "message": "此 Email 已被使用" }
```

---

#### `GET /api/member/profile`（需 Bearer token）

```
Request Header: Authorization: Bearer <token>

Response 200:
{
  "status": true,
  "data": {
    "id":    1,
    "name":  "王小明",
    "email": "user@example.com",
    "role":  "member",
    "profile": {
      "birthday":          "1990-01-01",
      "gender":            "male",
      "address":           "台北市信義區...",
      "emergency_contact": "王大明",
      "emergency_phone":   "0987654321"
    }
  }
}
```

---

#### `PUT /api/member/profile`（需 Bearer token）

```
Request Header: Authorization: Bearer <token>
Request Body (application/json):
{
  "name":               "王小明",
  "birthday":           "1990-01-01",
  "gender":             "male",
  "address":            "台北市信義區...",
  "emergency_contact":  "王大明",
  "emergency_phone":    "0987654321"
}

Response 200:
{ "status": true, "message": "資料已更新", "data": { ...同 GET profile } }
```

---

#### `POST /api/member/logout`（需 Bearer token）

```
Request Header: Authorization: Bearer <token>

Response 200:
{ "status": true, "message": "已登出" }
```

---

### Contract 2 — 環境變數

#### 後端（`CFDivePlatform/.env`）需新增

```
# Google OAuth
GOOGLE_CLIENT_ID=<從 Google Cloud Console 取得>
GOOGLE_CLIENT_SECRET=<從 Google Cloud Console 取得>
GOOGLE_REDIRECT_URI=http://localhost:80/api/auth/google/callback

# 前端 URL（OAuth callback redirect 用）
FRONTEND_URL=http://localhost:5173
```

#### 前端（`cf-dive-frontend/.env`）

```
VITE_API_URL=http://localhost:80
```

---

### Contract 3 — CORS 設定

`config/cors.php`（Laravel 11 預設不存在，需執行 `php artisan config:publish cors` 建立後修改）：

```php
'allowed_origins'         => [env('FRONTEND_URL', 'http://localhost:5173')],
'allowed_origins_patterns'=> [],
'allowed_methods'         => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
'allowed_headers'         => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With'],
'exposed_headers'         => [],
'max_age'                 => 0,
'supports_credentials'    => false,   // Token 模式不需要 cookie，false 即可
```

> `supports_credentials: false` 是刻意的決定。若改用 Sanctum cookie 模式才需設為 true，但會帶來 SameSite / CSRF 複雜度。Token 模式維持 false 最簡單。

---

## Risks / Trade-offs

| 風險 | 緩解策略 |
|------|----------|
| `localStorage` 存 token 有 XSS 風險 | MVP 階段接受，未來可改用 httpOnly cookie |
| `diving_offers` 表無 `provider_id`，課程無法關聯教練 | MVP 不處理，僅展示靜態課程資料 |
| Google OAuth callback redirect 帶 token 在 URL 中，有 Referer 洩漏風險 | token 在 query param 僅短暫存在，前端取得後立即存 localStorage 並清除 URL（`history.replaceState`） |
| 前端 repo 與 Laravel repo 分開，OpenSpec tasks 橫跨兩個位置 | Tasks 以 `[後端]` / `[前端]` 標記，分別在對應 repo 操作 |

## Open Questions

- [x] **Google OAuth callback 邏輯**：`SocialAuthController::handleGoogleCallback()` 現行回傳 JSON，**確認需改為 redirect 至 `FRONTEND_URL/auth/callback?token=<token>`**。失敗時 redirect 至 `FRONTEND_URL/login?error=oauth_failed`。
- [x] **前端 repo 名稱**：確認為 `cf-dive-frontend`。
