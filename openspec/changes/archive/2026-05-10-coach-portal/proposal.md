## Why

會員端課程列表目前依賴手動塞入的測試資料，平台無法規模化運作。需要 Coach Portal 讓教練能自行上架、編輯、下架課程，使平台內容自給自足。

## What Changes

- **後端**：`diving_offers` 表新增 `provider_id` 欄位，綁定課程與教練
- **後端**：補完 Provider Auth API（register / login / logout / profile CRUD）
- **後端**：新增 Coach 課程管理 API（CRUD，需 provider 角色驗證）
- **前端**：在現有 Vue 3 SPA 新增 `/coach/*` 路由群組，整合教練登入與課程管理介面
- **修改** `diving-offers-api`：公開課程列表 API 新增 `provider_id` 欄位於 response，供未來關聯展示使用

## Capabilities

### New Capabilities

- `provider-auth`：教練帳號的註冊、登入、登出、個人資料讀取與更新 API
- `coach-offers-api`：教練專屬課程管理 API（列出自己課程、新增、更新、刪除）
- `coach-portal-ui`：教練後台前端介面（登入、課程 Dashboard、新增/編輯表單、個人資料頁）

### Modified Capabilities

- `diving-offers-api`：`diving_offers` 資料表新增 `provider_id` 欄位，response 加入此欄位（向後相容，nullable）

## Impact

**後端**
- 新增 migration：`diving_offers.provider_id`
- 補完 `AuthController` 中 Provider 相關方法（現有路由佔位但方法未實作）
- 新增 `ProviderOfferController`

**前端（frontend/ 目錄）**
- 新增 `src/stores/coachAuth.js`
- 新增 `src/views/coach/` 目錄下各頁面
- `src/router/index.js` 新增 `/coach/*` 路由與 guard

**資料庫**
- `diving_offers` 表結構變更（新增 nullable 欄位，不影響現有資料）
