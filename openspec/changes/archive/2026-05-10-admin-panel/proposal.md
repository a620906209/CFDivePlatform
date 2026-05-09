## Why

Member Portal 和 Coach Portal 已上線，但平台缺乏管理工具：教練驗證無法操作、問題帳號無法停用、課程品質無法把關。Admin Panel 補上這塊，讓平台可以實際營運。

## What Changes

- **後端**：新增 `AdminUserController` 處理會員/教練的列表、詳情、啟用/停用、教練驗證
- **後端**：新增 `AdminOfferController` 處理全平台課程列表與刪除
- **後端**：新增 `AdminStatsController` 提供統計數據
- **前端**：新增 `/admin/*` 路由群組，包含登入、儀表板、用戶管理、課程管理、個人資料
- **前端**：`App.vue` 擴充隱藏邏輯，`/admin/*` 也不顯示會員 NavBar

## Capabilities

### New Capabilities

- `admin-auth`：管理員登入/登出/個人資料（沿用現有 AuthController 方法，不需新增）
- `admin-user-management`：管理員查看、啟用/停用會員與教練，驗證/取消驗證教練
- `admin-offer-management`：管理員查看全平台課程並刪除違規內容
- `admin-stats`：平台統計數據 API（會員數、教練數、課程數）
- `admin-panel-ui`：管理後台前端介面（儀表板、用戶管理、課程管理）

### Modified Capabilities

（無）

## Impact

**後端**
- 新增 `AdminUserController`、`AdminOfferController`、`AdminStatsController`
- 現有 `AuthController` Admin 方法（login / logout / profile）直接沿用，路由已存在
- 所有新 Admin API 套用 `auth:sanctum` middleware 並在 Controller 層驗證 `role === admin`

**前端（frontend/ 目錄）**
- 新增 `src/stores/adminAuth.js`、`src/api/adminAxios.js`
- 新增 `src/layouts/AdminLayout.vue`、`src/components/AdminNavBar.vue`
- 新增 `src/views/admin/` 目錄下各頁面
- `src/router/index.js` 新增 `/admin/*` 路由與 `requiresAdmin` guard
- `App.vue` 隱藏邏輯擴充：`/admin/*` 同樣不顯示會員 NavBar
