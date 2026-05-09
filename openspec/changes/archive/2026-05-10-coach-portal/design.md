## Context

後端 `AuthController` 已有完整的 Provider Auth 方法實作（login / logout / profile / register / update），經審查後大部分可直接沿用，僅需對 `registerProvider` 與 `updateProviderProfile` 做小幅欄位調整以對齊 Coach Portal 使用情境。`diving_offers` 表無 `provider_id`，課程與教練無法關聯。前端（`frontend/`）已有 Member Portal 的架構（Pinia、Vue Router、Axios、Tailwind），Coach Portal 將整合進同一個 SPA，以 `/coach/*` 路由群組區分。

## Goals / Non-Goals

**Goals:**
- 教練可以申請註冊帳號（含工作室/個人資訊）
- 教練可以用獨立帳號登入後台
- 教練可以 CRUD 管理自己上架的潛水課程
- 教練可以讀取與更新個人資料
- 課程與建立者（provider）綁定，不同教練只能看/改自己的課程

**Non-Goals:**
- 訂單 / 預約系統
- 課程圖片上傳（MVP 用 emoji 佔位）
- 教練與會員的配對管理
- 課程審核流程（Admin 功能）

## Decisions

### D1：Coach Portal 整合進現有 SPA，不另開 repo

**決定**：`/coach/*` 路由加進 `frontend/src/router/index.js`，與 Member Portal 共用同一個 Vue app。

**理由**：frontend/ 已在同一個 repo，共用 Tailwind、Axios instance、router 基礎設施。分開 repo 收益不大，反而增加維護成本。

---

### D2：獨立的 coachAuth Pinia Store

**決定**：新增 `src/stores/coachAuth.js`，與現有 `auth.js`（member）完全分開。localStorage key 用 `coach_token` / `coach_user` 區分。

**理由**：同一個瀏覽器可能同時開著會員頁和教練後台（不同 tab）。共用 store 會互相污染登入狀態。

---

### D3：Provider Auth / Profile 沿用現有 AuthController，只做必要調整

**決定**：沿用現有 `AuthController` 的 `registerProvider`、`loginProvider`、`logoutProvider`、`providerProfile`、`updateProviderProfile` 方法，僅針對教練情境補充欄位與調整驗證規則。

**理由**：現有路由與主要邏輯已存在，本次以最小修改滿足 Coach Portal 需求，避免重複開發。

---

### D4：課程 CRUD — 新增 ProviderOfferController

**決定**：新增獨立的 `ProviderOfferController`，處理教練的課程 CRUD。`index()` 只返回當前 provider 的課程；`store()` 強制將 `provider_id` 設為 `auth()->id()`；`show()`、`update()`、`destroy()` 則驗證課程擁有權。

**理由**：與公開的 `DivingOfferController` 職責分開，避免授權邏輯混雜。

**Invariant — provider_id 所有權（實用優先）**

單一課程操作端點（show / update / destroy）依序執行兩步驟，不可合併：

1. `DivingOffer::find($id)` → null 時回傳 **404**
2. `offer->provider_id !== auth()->id()` → 回傳 **403**

此設計會洩漏資源存在性，為刻意取捨：`diving_offers` 使用自增整數 ID，資源存在性本可枚舉，安全遮蔽收益有限；而對教練而言，明確區分「課程不存在」與「無權限」有實際操作價值。

`store()` 補充規則：強制將 `provider_id` 設為 `auth()->id()`，忽略 request body 中任何傳入值。

---

### D5：diving_offers.provider_id — Nullable Migration

**決定**：`provider_id` 為 `nullable` 外鍵，現有測試資料不受影響。

**理由**：現有 6 筆手動塞入的課程 `provider_id` 為 null，保留以免資料遺失。之後可用 seeder 補上。

---

## Contracts

### API Schema

#### `POST /api/provider/register`
```
Body: { name, email, password, password_confirmation, phone?,
        business_name?, description?, contact_phone?, contact_email?, address? }
Response 201: { "status": true, "message": "服務提供者註冊成功", "data": { "user": {...}, "token": "...", "token_type": "Bearer" } }
Response 422: { "status": false, "message": "驗證失敗", "errors": {...} }
```

#### `POST /api/provider/login`
```
Body: { "email": "...", "password": "..." }
Response 200: { "status": true, "data": { "user": {...}, "token": "...", "token_type": "Bearer" } }
Response 401: { "status": false, "message": "帳號或密碼錯誤" }
```

#### `GET /api/provider/offers`（需 Bearer token，role=provider）
```
Response 200: { "status": true, "data": [...offers], "meta": { total, per_page, current_page, last_page } }
```

#### `GET /api/provider/offers/{id}`（需 Bearer token，role=provider）
```
Response 200: { "status": true, "data": { ...offer } }
Response 403: { "status": false, "message": "無權限查看此課程" }
Response 404: { "status": false, "message": "課程不存在" }
```

#### `POST /api/provider/offers`
```
Body: { title, location, spot, price, region, tag, badges (array), description }
Response 201: { "status": true, "data": { ...offer } }
Response 422: { "status": false, "message": "...", "errors": {...} }
```

#### `PUT /api/provider/offers/{id}`
```
Body: 同 POST（部分欄位可選）
Response 200: { "status": true, "data": { ...offer } }
Response 403: { "status": false, "message": "無權限修改此課程" }
Response 404: { "status": false, "message": "課程不存在" }
```

#### `DELETE /api/provider/offers/{id}`
```
Response 200: { "status": true, "message": "課程已刪除" }
Response 403: { "status": false, "message": "無權限刪除此課程" }
Response 404: { "status": false, "message": "課程不存在" }
```

---

## Risks / Trade-offs

| 風險 | 緩解策略 |
|------|----------|
| `AuthController` 已很龐大，繼續加方法會更難維護 | MVP 接受，下一個 change 可拆分成 `ProviderAuthController` |
| 同一 SPA 混合 Member 和 Coach 路由，bundle 變大 | 所有頁面已用動態 import（`() => import(...)`），不影響首次載入 |
| `provider_id` nullable 導致公開課程列表混有無主課程 | 公開 API 不過濾 null，視為平台示範課程；Coach 的列表 API 只返回自己的 |

## Open Questions

- [x] `ProviderProfile` 和 `CoachProfile` 兩個 model 並存，目前 provider login 應該用哪個 profile？→ 決定統一使用 `ProviderProfile`，`CoachProfile` 暫時忽略（legacy）

## 現有方法審查結果（實作前確認）

| 方法 | 狀態 | 說明 |
|------|------|------|
| `loginProvider` | ✅ 直接可用 | role 驗證、token、is_active 檢查皆完整 |
| `logoutProvider` | ✅ 直接可用 | role 檢查 + token 撤銷正確 |
| `providerProfile` (GET) | ✅ 直接可用 | 回傳 user + providerProfile |
| `registerProvider` | ⚠️ 小調整 | `business_name` 改為 nullable（單人教練不一定有業者名稱）|
| `updateProviderProfile` | ⚠️ 補欄位 | 補上 certifications / dive_sites / services / facilities / website / social_media |

**ProviderProfile 欄位使用策略：**
- 教練可自行編輯：business_name、description、certifications、dive_sites、services、facilities、contact_person、contact_phone、contact_email、address、business_hours、website、social_media
- 系統/Admin 管理（前端唯讀顯示）：is_verified、rating、is_active、logo_url、banner_url
