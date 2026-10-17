# Design

## Context

動機見 proposal.md（Why）。目前現況：

- `bootstrap/app.php` 只註冊一個角色 middleware alias：`admin` → `App\Http\Middleware\EnsureAdmin`。它在 `!$request->user() || !$request->user()->isAdmin()` 時回 `403 { status: false, message: "無權限存取" }`
- `App\Models\User` 已有 `isMember()`、`isProvider()`、`isAdmin()`，都是直接比對 `role` 字串
- `/api/member/*`、`/api/provider/*` 群組內，只有 `AuthController` 的 logout／refresh／profile／change-password 自己檢查角色，回應同樣是 `403 { status: false, message: "無權限存取" }`；`MemberBookingController`、`ReviewController`（store／update／destroy）、`ProviderOfferController`、`ScheduleController`、`ProviderBookingController`、`CourseImageController`、`ProviderVerificationController` 只檢查資源所有權，不檢查角色
- 前端 `axios.js`、`coachAxios.js` 的攔截器只對 401 做 refresh 重試，403 直接交給呼叫端；`adminAxios.js` 沒有呼叫 `/member/*`、`/provider/*`
- 部署流程（`deploy.yml`）每次都會執行 `route:cache`，路由變更隨部署生效

## Goals / Non-Goals

**Goals:**
- 在路由層擋下所有跨角色呼叫 member／provider 專屬端點
- 角色不符時的回應與現有 `EnsureAdmin`、`AuthController` 完全一致，前端不需要改

**Non-Goals:**
- 不檢查 `is_active`（停用帳號沿用既有行為，與角色 middleware 無關）、教練審核狀態（審核狀態的限制維持在既有業務邏輯，例如 `visibleToPublic` scope）
- 不移除 `AuthController` 內既有的角色檢查（重構屬待辦 M1）
- 不調整跨角色共用路由（通知、即時訊息、`/reviews/{id}/helpful`、`/logout`、`/user`），包含「admin 能否存取這些路由」也不在此次決定
- 不更動 `EnsureAdmin`

## Decisions

### D1：新增 `EnsureMember`、`EnsureProvider` 兩個 middleware，而非參數化的 `EnsureRole`
- 做法：在 `app/Http/Middleware/` 新增兩個 class，結構與 `EnsureAdmin` 相同，分別呼叫 `isMember()`、`isProvider()`；在 `bootstrap/app.php` 註冊 alias `member`、`provider`
- 理由：既有慣例是每個角色一個 middleware（`EnsureAdmin` + alias `admin`）。若新增參數化的 `role:member`，專案內會同時存在兩種寫法；要統一就得改 `EnsureAdmin` 的使用處，超出此次範圍
- 替代方案：`EnsureRole` 搭配 `role:member,provider`。程式碼較少，但會跟既有的 `admin` alias 形成兩種寫法並存，因此不採用

### D2：403 回應沿用 `{ status: false, message: "無權限存取" }`
- 理由：與 `EnsureAdmin`、`AuthController::getUnauthorizedResponse()` 一致，前端與 Swagger 已記載的 403 不需變動
- middleware 內同時處理 `$request->user()` 為 null 的情況（與 `EnsureAdmin` 相同的防禦寫法）；實際上 `auth:sanctum` 排在前面，未登入會先回 401

### D3：middleware 只比對角色，不檢查審核狀態
- 理由：未審核（`unsubmitted`）、審核中（`pending`）、被駁回（`rejected`）的教練都需要進入 `/api/provider/verification*` 送審或補件，也需要在審核通過前先建立課程。若在 middleware 檢查審核狀態，這些流程會整個斷掉

### D4：掛在既有群組的 middleware 陣列，順序為 `['auth:sanctum', 'member']`／`['auth:sanctum', 'provider']`
- 理由：與 admin 群組 `['auth:sanctum', 'admin']` 寫法一致；`auth:sanctum` 先執行，保證未登入回 401、角色不符回 403
- 公開的 register／login 路由定義在群組外，不受影響（已核對 `routes/api.php`）

### D5：Controller 內既有的資源所有權檢查全部保留
- 理由：CLAUDE.md 授權規範要求 middleware 補上後仍保留 Controller 內的所有權檢查；角色 middleware 只解決「角色錯」，不解決「同角色存取別人的資源」

## 安全確認

- **輸入驗證**：此次不新增外部輸入；既有端點的 `$request->validate()` 不變。middleware 只讀取 Sanctum 認證後的 `$request->user()->role`，沒有讀取任何請求參數
- **SQL／shell 拼接**：無
- **資料庫存取**：middleware 不額外查詢資料庫（`$request->user()` 由 Sanctum 認證時已載入）；無 raw query，無 N+1
- **權限檢查層級**：路由層（`member`、`provider` middleware），加上 Controller 內既有的資源所有權檢查（例如 `MemberBookingController::show` 比對 `member_id`、`ProviderOfferController` 比對 `provider_id`）
- **未授權觸發可能性**：修正後，非 member 角色無法存取 `/api/member/*`，非 provider 角色無法存取 `/api/provider/*`；未登入一律 401
- **各路由所掛 middleware**：
  - `/api/member/*`（register、login 除外）：`auth:sanctum` + `member`
  - `/api/provider/*`（register、login 除外）：`auth:sanctum` + `provider`
  - 其他路由不變
- **前端**：不涉及 DOM 渲染；前端呼叫已帶各自的 Bearer token，不需改

## Risks / Trade-offs

- [既有測試可能以錯誤角色呼叫 member／provider 路由，加上 middleware 後改回 403] → 實作時先跑完整 `php artisan test`，逐一判斷失敗的測試是「測試本身用錯角色」（修正測試）還是「真的依賴跨角色行為」（停下來回報）
- [AuthController 內的角色檢查與 middleware 重複] → 行為一致、無害；移除留給待辦 M1，避免此次範圍擴大
- [admin 被擋在 member／provider 路由之外，若未來後台想借用這些端點會失敗] → 已核對前端 admin 端目前沒有呼叫；未來需要時應在 `/api/admin/*` 新增端點
- [跨角色共用路由（通知、即時訊息）對 admin 的行為未定義] → 不在此次範圍；即時訊息的 `authorizeParticipant` 對 admin 已回 false，通知只會讀到 admin 自己的資料

## Migration Plan

- 無 migration、無新套件、無環境變數
- 部署：merge 後由 `deploy.yml` 重建 image，並執行 `route:cache`，新的路由 middleware 即生效
- 回滾：revert 此次 commit 再部署即可，沒有資料需要還原
