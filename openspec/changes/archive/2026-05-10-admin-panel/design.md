## Context

後端 Admin Auth 方法（loginAdmin / logoutAdmin / adminProfile / updateAdminProfile）已存在於 AuthController，路由也已佔位。User model 有 `is_active` 欄位；ProviderProfile 有 `is_verified` 欄位，目前無 API 可修改。前端已有 memberAuth / coachAuth 兩套認證模式，Admin 循同一模式新增第三套。

## Goals / Non-Goals

**Goals:**
- 管理員可登入後台、查看平台數據
- 管理員可列表/搜尋會員與教練，並啟用/停用帳號
- 管理員可驗證教練（設定 ProviderProfile.is_verified）
- 管理員可查看全平台課程並刪除違規內容
- 前端 `/admin/*` 有獨立 Layout，不顯示會員 NavBar

**Non-Goals:**
- Admin 帳號自助註冊（透過後端 seeder 或直接 DB 建立）
- 細粒度角色權限（RBAC）
- 操作日誌（Audit Log）
- 批次操作（批量停用）

## Decisions

### D1：Admin Auth 沿用現有 AuthController，不新建 Controller

**決定**：`loginAdmin`、`logoutAdmin`、`adminProfile`、`updateAdminProfile` 直接沿用，不修改。

**理由**：方法已存在且邏輯完整，路由也已佔位。

---

### D2：業務邏輯拆到獨立 Controller

**決定**：新增 `AdminUserController`（用戶管理）、`AdminOfferController`（課程管理）、`AdminStatsController`（統計）。

**理由**：與 AuthController 職責分開，避免繼續膨脹。所有方法在開頭驗證 `auth()->user()->role === 'admin'`，非管理員回傳 403。

---

### D3：Toggle 語意（啟用/停用、驗證/取消驗證）

**決定**：`toggle-active` 和 `toggle-verified` 為 PUT 端點，後端直接反轉當前值（`is_active = !is_active`），不接受 body 傳入布林值。

**理由**：UI 是單一按鈕切換狀態，反轉語意最直覺，避免前端傳錯值。

---

### D4：adminAuth 獨立 Store，localStorage key `admin_token` / `admin_user`

**決定**：循 coachAuth 模式，新增第三套獨立 store。

**理由**：三種角色可能在不同 tab 同時使用，共用 store 會互相污染。

---

## Contracts

### API Schema

#### `POST /api/admin/login`（現有）
```
Body: { "email": "...", "password": "..." }
Response 200: { "status": true, "data": { "user": {...}, "token": "...", "token_type": "Bearer" } }
```

#### `GET /api/admin/stats`（需 Bearer token，role=admin）
```
Response 200:
{
  "status": true,
  "data": {
    "total_members":   120,
    "total_providers": 18,
    "total_offers":    64
  }
}
```

#### `GET /api/admin/members`（需 Bearer token，role=admin）
```
Query: q（搜尋 name / email）, page, per_page（default 15）
Response 200: { "status": true, "data": [...users with memberProfile], "meta": {...} }
```

#### `GET /api/admin/members/{id}`
```
Response 200: { "status": true, "data": { ...user, profile: {...} } }
Response 404: { "status": false, "message": "用戶不存在" }
```

#### `PUT /api/admin/members/{id}/toggle-active`
```
Response 200: { "status": true, "message": "帳號已停用" | "帳號已啟用", "data": { "is_active": false | true } }
Response 404: { "status": false, "message": "用戶不存在" }
```

#### `GET /api/admin/providers`（同 members 結構，含 providerProfile）
#### `GET /api/admin/providers/{id}`
#### `PUT /api/admin/providers/{id}/toggle-active`

#### `PUT /api/admin/providers/{id}/toggle-verified`
```
Response 200: { "status": true, "message": "教練已驗證" | "已取消驗證", "data": { "is_verified": true | false } }
```

#### `GET /api/admin/offers`
```
Query: q（搜尋 title / location）, page, per_page（default 15）
Response 200: { "status": true, "data": [...offers with provider_id], "meta": {...} }
```

#### `DELETE /api/admin/offers/{id}`
```
Response 200: { "status": true, "message": "課程已刪除" }
Response 404: { "status": false, "message": "課程不存在" }
```

---

## Risks / Trade-offs

| 風險 | 緩解策略 |
|------|----------|
| toggle 反轉語意若網路重試，可能連按兩次回到原狀態 | MVP 接受，未來可改為明確 `{ is_active: true/false }` body |
| Admin 帳號只能透過 DB 或 seeder 建立，無自助註冊 | 開發期間用 tinker 建立，正式環境透過 seeder |
| `AdminUserController` 對 member / provider 各需重複驗證邏輯 | 用 private helper method 共用，避免複製貼上 |
| `/admin/*` 頁面無額外安全層（任何人知道路徑都可訪問登入頁） | MVP 接受，route guard 在 frontend 層足夠 |
