# CFDivePlatform 規格與實作分析報告暨修補計畫

> 分析日期：2026-06-11
> 分析範圍：`openspec/specs/`（32 份規格）vs 後端實作（Laravel 11）、前端（`frontend/` Vue 3）、測試（`tests/`）
> 分析基準：branch `test/auth-tests-coverage`（commit ca5b843，與 master 同步）

---

## 一、現況總覽

### 1.1 規格狀態

- OpenSpec 規格共 **32 份**，全部 change 已歸檔（`openspec/changes/` 僅剩 `archive/`，無進行中 change）。
- 涵蓋範圍：認證安全（lockout / rate-limiting / OAuth state / token refresh）、三角色入口（Member / Coach / Admin）、預約生命週期、評價系統、通知系統、即時聊天、Swagger 文件、效能優化。

### 1.2 實作狀態

| 模組 | 規格 | 實作 | 測試 |
|------|------|------|------|
| 認證（三角色登入/註冊/登出） | ✅ | ✅ | ✅ 39 案例 |
| 帳號鎖定 + OAuth state | ✅ | ✅ | ✅ 15 案例 |
| Token Refresh | ✅ | ✅ | ✅ 11 案例 |
| 課程 CRUD + 圖片上傳 | ✅ | ✅ | ✅ 14 案例 |
| 開課時段管理 | ✅ | ✅ | ❌ 無 |
| 預約生命週期（七狀態機、防超賣、Scheduler） | ✅ | ✅ | ❌ 無 |
| 評價系統（匿名、投票、rating 重算） | ✅ | ✅ | ✅ 32 案例 |
| 通知系統（站內 + Email） | ✅ | ✅ | ❌ 無 |
| 即時聊天 + Presence | ✅ | ✅ | ❌ 無 |
| Admin 管理端點（stats / users / offers / bookings / reviews） | ✅ | ✅ | ❌ 無 |
| 教練審核流程 | ❌ 無規格 | ⚠️ 僅半套（見 P1-1） | ❌ 無 |
| 金流整合 | ❌ 無規格 | ❌ 未實作 | — |

整體而言規格與實作的對應度高，路由、端點、middleware 配置大致與規格一致。以下列出本次稽核發現的偏差與風險。

---

## 二、發現問題（依嚴重度排序）

### 🔴 P0-1：`POST /api/admin/register` 完全公開，任何人可註冊管理員帳號

- **位置**：`routes/api.php:106`、`AuthController::registerAdmin()`（`AuthController.php:852`）
- **事實**：該路由無任何 middleware、無邀請碼、無「僅限第一位管理員」檢查；request 通過基本驗證後直接 `role => 'admin'` 建立帳號。
- **影響**：攻擊者一個 HTTP 請求即可取得全平台管理權限（停權用戶、刪課程、刪評價、看全部個資），**使 P0~P2 所有認證安全強化形同虛設**。
- **規格對應**：`admin-auth` 規格只定義「管理員登入」，**從未定義公開註冊端點**——此端點本身就是規格外實作。
- **附帶問題**：密碼規則僅 `min:6`，低於一般管理帳號標準。

### 🟠 P1-1：`is_verified`（教練驗證）從未被任何業務邏輯強制執行

- **事實**：`is_verified` 只出現在兩處——Admin 的 `toggleProviderVerified()`（`AdminUserController.php:144`）與 Model 屬性定義。教練**註冊後不需任何審核即可登入、上架課程、被公開列表曝光、接受預約**。
- **影響**：Admin 後台的「驗證教練」開關是純展示功能，平台對教練資質零把關。這就是記憶中「教練審核流程未實作」的實際狀態：開關存在、約束不存在。
- **規格對應**：目前**沒有任何規格**定義 `is_verified` 的業務語意（未驗證教練能做什麼/不能做什麼），屬規格空洞。

### 🟠 P1-2：登入頻率限制規格與實作不一致（規格漂移）

- **規格**（`openspec/specs/login-rate-limiting/spec.md`）：Member / Provider 每 IP 每分鐘最多 **5 次**。
- **實作**（`routes/api.php:31,67`）：`throttle:10,1`，即 **10 次**。
- **測試**（`AuthRateLimitTest.php`）：已在 commit 0dabc4e 改為斷言 10 次，**與實作一致、與規格矛盾**。
- **影響**：規格失去單一真實來源（single source of truth）地位。未來任何人依規格實作或稽核都會得出錯誤結論。Admin 的 3 次限制則規格實作一致。

### 🟡 P2-1：核心業務流程（預約）零測試覆蓋

- 現有 97 個測試案例集中在認證（70）、評價（32 中含投票）、圖片（14）。
- **預約狀態機（七狀態）、防超賣鎖定、Scheduler 自動過期/完成**（`ExpirePendingBookings`、`CompleteFinishedBookings`）——平台最核心、含金量最高、最容易因修改而回歸的邏輯——**沒有任何一條測試**。
- 同樣無測試：時段管理（時段重疊/容量）、通知觸發、聊天授權（presence channel 參與方驗證）、全部 Admin 端點（含 P0-1 的權限邊界）。
- 依 CLAUDE.md Rule 9，這些測試需編碼「為什麼」：防超賣測試保護的是金錢與信任，狀態機測試保護的是不可逆操作的合法轉移。

### 🟡 P2-2：規格維護債

1. `auth-test-coverage/spec.md` 的 Purpose 仍是 `TBD - created by archiving change`，歸檔後未補。
2. `member-portal-ui` 規格寫「前端 SHALL 建立於獨立 repo」，實際前端在本 repo 的 `frontend/` 目錄——規格描述已過時。
3. `admin-auth` 規格未涵蓋實際存在的 `/admin/register`、`/admin/check-member/{id}`、`/admin/check-provider/{id}` 端點。

---

## 三、修補計畫

### Phase 1 — 立即（建議今天，0.5 天內）

**T1.1 封鎖 `POST /api/admin/register`**（對應 P0-1）

建議方案（擇一，建議 a）：
- (a) **移除公開路由**，改為 artisan command `php artisan admin:create`（僅能從主機執行），生產環境的管理員一律由既有管理員或主機端建立。
- (b) 移入 `auth:sanctum + admin` middleware group（僅既有 admin 可開新 admin）——但需先用 (a) 或 seeder 解決第一位管理員的雞生蛋問題。

驗收標準：
- [ ] 未認證請求 `POST /api/admin/register` 回傳 404 或 401（不再是 201）。
- [ ] 補 Feature test：未授權者無法建立 admin 帳號。
- [ ] Demo seeder 既有的 admin 帳號不受影響。
- [ ] 同步新增/更新 `admin-auth` 規格段落，明確定義管理員帳號建立途徑。

**T1.2 解決 rate-limiting 規格漂移**（對應 P1-2）

- 先決定基準：5/min（規格）或 10/min（實作）。10/min 是後來有意調整（測試已配合修改），建議**以實作為準、更新規格**；若當初是誤改，則改回 5 並還原測試。
- 驗收標準：規格、`routes/api.php`、`AuthRateLimitTest` 三者數字一致。

### Phase 2 — 短期（1~2 天）

**T2.1 賦予 `is_verified` 最小業務語意**（對應 P1-1，為完整教練審核流程鋪路）

最小可行約束（不做完整審核流程，僅堵住風險）：
- 未驗證 Provider 的課程不出現在公開列表 `GET /api/diving-offers`（或：未驗證 Provider 無法將課程上架/`store()` 回 403，二擇一，依產品決策）。
- 課程詳情頁顯示教練 `is_verified` 徽章（前端已有欄位可用）。
- 驗收標準：
  - [ ] 新增規格 `provider-verification`（最小版），定義未驗證教練的能力邊界。
  - [ ] Feature test：未驗證教練的課程不可被公開查詢（或不可建立）。
  - [ ] Admin `toggle-verified` 後行為立即生效（注意 `api-cache-layer` 的快取失效）。

> 完整審核流程（證照上傳、審核佇列、駁回原因）屬新功能，見《未來發展可行性評估》文檔。

### Phase 3 — 中期（2~3 天）

**T3.1 補預約核心測試**（對應 P2-1，優先序由高至低）

1. `BookingLifecycleTest`：七狀態機合法/非法轉移（pending→confirmed→completed、reject、cancel、不可逆狀態防護）。
2. `BookingOversellTest`：併發/邊界情境下 confirmed 不超過時段容量；pending 不佔名額。
3. `BookingSchedulerTest`：`app:expire-pending-bookings` 與 `app:complete-finished-bookings` 的時間邊界。
4. `BookingChatAuthTest`：非參與方無法讀寫訊息、無法訂閱 presence channel。
5. `AdminEndpointTest`：非 admin token 存取 `/api/admin/*` 一律 403。

驗收標準：以上測試全綠且任一狀態機規則被改壞時至少一條測試會失敗（mutation 自查）。

### Phase 4 — 規格清理（0.5 天，可與 Phase 3 並行）

- [ ] 補 `auth-test-coverage` 的 Purpose。
- [ ] 更新 `member-portal-ui` 的 repo 描述。
- [ ] `admin-auth` 補 `check-member` / `check-provider` 端點描述（`register` 端點依 T1.1 決策寫入或標記移除）。

---

## 四、風險與依賴

| 項目 | 風險 | 緩解 |
|------|------|------|
| T1.1 | 前端 Admin 註冊頁（若存在）會失效 | 檢查 `frontend/src/views/admin/`，同步移除入口 |
| T2.1 | 公開列表過濾可能影響 demo 資料展示 | Demo seeder 將教練預設 `is_verified=true` |
| T3.1 | 測試需 MySQL 容器運行 | 沿用既有 `php artisan test` 容器內流程 |

## 五、規格同步狀態聲明

本報告本身不改動程式碼。執行修補計畫時需同步的規格文件：`admin-auth`（T1.1）、`login-rate-limiting`（T1.2）、新增 `provider-verification`（T2.1）、`auth-test-coverage` 與 `member-portal-ui`（Phase 4）。
