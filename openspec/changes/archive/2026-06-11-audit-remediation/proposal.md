## Why

2026-06-11 規格與實作稽核（`docs/analysis/2026-06-11-spec-implementation-audit.md`）發現四項問題：

1. **P0**：`POST /api/admin/register` 完全公開且無任何防護，任何人一個 HTTP 請求即可註冊管理員帳號，使 P0~P2 認證安全強化形同虛設。該端點不在任何規格內（規格外實作）。
2. **P1**：`provider_profiles.is_verified` 從未被業務邏輯強制執行——教練免審核即可上架課程、被公開曝光，Admin 的驗證開關是純展示功能，且無任何規格定義其語意。
3. **P1**：`login-rate-limiting` 規格寫 5/min，實作與測試為 `throttle:10,1`（規格漂移，測試已在 commit 0dabc4e 改為配合實作但規格未同步）。
4. **P2**：預約狀態機、防超賣、Scheduler 自動轉移——平台核心業務——零測試覆蓋。

> 註：本 change 為事後補歸檔。稽核報告實質扮演 proposal 角色，實作先於本文件完成（branch `fix/audit-remediation`，PR #28）。

## What Changes

- **移除** `POST /api/admin/register` 路由、`AuthController::registerAdmin`、對應 Swagger 文件
- **新增** `php artisan app:create-admin` command（密碼門檻 min:8），管理員帳號僅限主機端建立
- **新增** `DivingOffer::visibleToPublic` scope：公開 index/show 排除未驗證教練的課程（`provider_id` null 不受限）；`toggle-verified` 後 flush `diving_offers` 快取
- **同步** `login-rate-limiting` 規格門檻至實作值 10/min
- **新增測試**：`AdminAccountCreationTest`（4）、`DivingOfferVisibilityTest`（7）、`BookingLifecycleTest`（17）、`BookingOversellTest`（3）、`BookingSchedulerTest`（6）、`BookingChatAuthTest`（8）、`AdminEndpointAuthTest`（5）
- **移除** `AuthLoginTest` 中測已刪端點的 2 個 admin register 測試
- **規格清理**：補 `auth-test-coverage` Purpose、修正 `member-portal-ui` repo 描述

## Capabilities

### New Capabilities

- `provider-verification`：定義 `is_verified` 的最小業務語意——未驗證教練的課程不對公開端點曝光、切換立即生效、教練自有管理端點不受限

### Modified Capabilities

- `admin-auth`：新增「管理員帳號建立途徑」requirement（公開註冊端點關閉、僅限 `app:create-admin`）；補「管理員查詢指定用戶資料」端點規格
- `login-rate-limiting`：Member/Provider 門檻 5/min → 10/min（與實作一致，帳號鎖定已涵蓋暴力破解防護）
- `auth-test-coverage`：admin register 場景改寫為「公開註冊端點保持關閉」；補歸檔遺留的 TBD Purpose
- `member-portal-ui`：前端位置由「獨立 repo」修正為本 repo `frontend/` 目錄

## Impact

- **行為變更**：未驗證教練（含 DemoSeeder 中 1 位 `is_verified=false` 的展示教練）課程從公開列表消失、詳情回 404；依賴 `POST /api/admin/register` 的外部工具需改用 `app:create-admin`
- **影響檔案**：`routes/api.php`、`AuthController`、`AuthApiDoc`、`DivingOffer`、`DivingOfferController`、`AdminUserController`、新增 `app/Console/Commands/CreateAdminUser.php`、`tests/Feature/`（新增 7 檔）
- **已知限制**（留待完整教練審核流程）：`/diving-offers/{id}/schedules`、`/diving-offers/{id}/reviews` 與預約建立流程未套用相同過濾
- 驗證：容器內 `php artisan test` 146 passed / 378 assertions
