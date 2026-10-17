# Proposal

## Why

`routes/api.php` 的 `member`、`provider` 路由群組只掛 `auth:sanctum`，沒有角色檢查（待辦 H1，見 `docs/analysis/2026-10-14-project-review-todo.md`）。任何已登入的帳號都能跨角色呼叫：member 或 admin 可呼叫 `POST /api/provider/offers` 建立課程（`provider_id` 會被設成呼叫者），provider 或 admin 可呼叫 `POST /api/member/bookings` 建立預約。這兩個群組內只有 `AuthController` 的帳號類端點自己檢查角色，其餘業務端點都沒有防護。

## What Changes

- 新增 member 角色 middleware 與 provider 角色 middleware，比照既有 `admin` middleware 的行為：角色不符時回 `403 { status: false, message: "無權限存取" }`
- 將它們分別掛到 `/api/member/*` 與 `/api/provider/*` 路由群組；未登入仍由 `auth:sanctum` 先回 401
- provider middleware **只檢查角色，不檢查審核狀態**：未審核或審核中的教練仍需能進入 `/api/provider/*`（例如送審證照）；審核狀態的限制維持在既有的業務邏輯
- admin 也會被擋在 `/api/member/*` 與 `/api/provider/*` 之外（前端 admin 端目前沒有呼叫這兩組路由，已核對 `frontend/src`）
- **BREAKING（僅對越權呼叫）**：原本能成功的跨角色呼叫（例如 member 呼叫 `POST /api/provider/offers`）改為 403。合法角色的行為與回應格式不變
- 不在範圍內：通知（`/api/notifications/*`）、即時訊息（`/api/bookings/{booking}/messages*`）、`POST /api/reviews/{id}/helpful`、`/api/logout`、`/api/user` 等跨角色共用路由維持現狀；`AuthController` 內既有的重複角色檢查不在此次移除（屬待辦 M1）

## Capabilities

### New Capabilities
- `role-authorization`：依使用者角色限制 member、provider 專屬 API 路由群組的存取（各角色與未登入的預期結果）

### Modified Capabilities
- `feature-test-coverage`：「預約列表端點測試覆蓋」Requirement 內文描述 member／provider 預約列表的 middleware 只有 `auth:sanctum`，需改為反映新增的角色 middleware

## Impact

- 程式碼：`app/Http/Middleware/`（新增兩個 middleware）、`bootstrap/app.php`（註冊 alias）、`routes/api.php`（兩個群組加 middleware）
- 測試：新增跨角色 Feature 測試（參考 `tests/Feature/AdminEndpointAuthTest.php`）；既有測試若依賴跨角色呼叫需檢視
- API：回應格式不變；跨角色呼叫從原本的 2xx／422 改為 403
- 前端：不需修改（三組前端各自只呼叫自己角色的路由）
- Swagger：`app/Docs/MemberApiDoc.php`、`ProviderApiDoc.php` 已有 403 回應，只需核對描述是否涵蓋「角色不符」
- 不影響主規格：`provider-auth`、`admin-auth`（其 Requirement 未描述路由群組的角色限制，行為也不變）
- 部署：無 migration、無新相依套件、無環境變數
