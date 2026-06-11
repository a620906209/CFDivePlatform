# CFDivePlatform 規格與實作分析報告暨修補紀錄

> 分析日期：2026-06-11
> 分析範圍：`openspec/specs/`（32 份規格）vs 後端實作（Laravel 11）、前端（`frontend/` Vue 3）、測試（`tests/`）
> 分析基準：branch `test/auth-tests-coverage`（commit ca5b843）
> **狀態更新（2026-06-11）**：修補計畫四階段已全部完成於 branch `fix/audit-remediation`（PR #28），本文件保留發現紀錄與執行結果，未完成事項見第四節。

---

## 一、現況總覽

### 1.1 規格狀態

- OpenSpec 規格共 **32 份**（修補後 33 份，新增 `provider-verification`），全部 change 已歸檔。
- 涵蓋範圍：認證安全（lockout / rate-limiting / OAuth state / token refresh）、三角色入口（Member / Coach / Admin）、預約生命週期、評價系統、通知系統、即時聊天、Swagger 文件、效能優化。

### 1.2 實作狀態（修補後）

| 模組 | 規格 | 實作 | 測試 |
|------|------|------|------|
| 認證（三角色登入/註冊/登出） | ✅ | ✅ | ✅ |
| 帳號鎖定 + OAuth state | ✅ | ✅ | ✅ |
| Token Refresh | ✅ | ✅ | ✅ |
| 課程 CRUD + 圖片上傳 | ✅ | ✅ | ✅ |
| 預約生命週期（七狀態機、防超賣、Scheduler） | ✅ | ✅ | ✅（修補新增 26 案例） |
| 評價系統 | ✅ | ✅ | ✅ |
| 通知系統 | ✅ | ✅ | ⚠️ 僅 Scheduler 路徑覆蓋 |
| 即時聊天 + Presence | ✅ | ✅ | ✅（授權路徑，修補新增 8 案例） |
| Admin 管理端點 | ✅ | ✅ | ✅（權限邊界，修補新增 5 案例） |
| 教練驗證（is_verified 最小語意） | ✅（修補新增） | ✅ | ✅（7 案例） |
| 教練審核流程（完整版） | ❌ 無規格 | ❌ | — |
| 金流整合 | ❌ 無規格 | ❌ | — |

---

## 二、稽核發現（保留原始紀錄，狀態見第三節）

### 🔴 P0-1：`POST /api/admin/register` 完全公開，任何人可註冊管理員帳號

- 該路由無任何 middleware、無邀請碼；request 通過基本驗證後直接 `role => 'admin'` 建立帳號。攻擊者一個 HTTP 請求即可取得全平台管理權限。`admin-auth` 規格從未定義此端點（規格外實作）。

### 🟠 P1-1：`is_verified`（教練驗證）從未被任何業務邏輯強制執行

- `is_verified` 只有 Admin toggle 開關與 Model 屬性，教練註冊後不需任何審核即可上架、曝光、接單。無任何規格定義其業務語意。

### 🟠 P1-2：登入頻率限制規格與實作不一致（規格漂移）

- 規格寫 Member/Provider 5 次/分鐘，實作 `throttle:10,1`，測試（commit 0dabc4e）已配合實作改為 10 次，規格未同步。

### 🟡 P2-1：核心業務流程（預約）零測試覆蓋

- 預約狀態機、防超賣鎖定、Scheduler 自動過期/完成沒有任何測試；時段管理、通知觸發、聊天授權、Admin 端點亦無。

### 🟡 P2-2：規格維護債

- `auth-test-coverage` Purpose 為 TBD；`member-portal-ui` repo 描述過時；`admin-auth` 未涵蓋 check-member / check-provider 端點。

---

## 三、修補執行紀錄（2026-06-11 全部完成）

分支 `fix/audit-remediation`（基底 `test/auth-tests-coverage`，PR #28），容器內全套件 **146 passed / 378 assertions**。

| 對應發現 | 修補內容 | Commit |
|---------|---------|--------|
| P0-1 | 移除公開 admin/register（路由/controller/Swagger），新增 `php artisan app:create-admin`（min:8），`AdminAccountCreationTest` 4 案例，`admin-auth` 規格補「帳號建立途徑」 | aeb8c97 |
| P1-2 | `login-rate-limiting` 規格同步至 10/min（以實作為準，註記放寬理由） | 88a81aa |
| P1-1 | `DivingOffer::visibleToPublic` scope（公開 index/show 過濾未驗證教練課程）、toggle-verified 後 flush 快取、新增 `provider-verification` 規格、`DivingOfferVisibilityTest` 7 案例 | 3c38d08 |
| P2-1 | 預約核心 39 案例：狀態機 17、防超賣 3、Scheduler 6、聊天授權 8、Admin 權限邊界 5 | 63b25f9 |
| P2-2 | 規格清理（Purpose / repo 描述 / 查詢端點）+ 同步移除測已刪端點的 2 個舊測試 | d3a0d30, cec2078 |
| — | OpenSpec 補歸檔 `2026-06-11-audit-remediation`（proposal / design / tasks / 規格增量） | a97a7d0 |

**與原計畫的偏差**：原計畫建議「Demo seeder 將教練預設 `is_verified=true`」，實際保留 seeder 中 1 位未驗證教練——其課程從公開列表消失正好展示新規則，屬刻意決策（記錄於 PR #28 說明）。

## 四、尚未處理事項

1. **provider-verification 已知限制**（規格 Notes 已載明）：`GET /api/diving-offers/{id}/schedules`、`GET /api/diving-offers/{id}/reviews` 與預約建立流程尚未套用可見性過濾，知道課程 id 仍可間接互動。
   → **已開 OpenSpec change `provider-verification-gaps` 規劃**（`openspec/changes/provider-verification-gaps/`），待實作。
2. **操作體驗優化**：見 `docs/analysis/2026-06-11-performance-optimization-plan.md`（O1.1 啟用 OPcache 起步），尚未實施。
3. **新功能路線圖**：教練審核完整版、金流整合等，見 `docs/analysis/2026-06-11-future-roadmap-feasibility.md`。

## 五、規格同步狀態聲明

修補已同步的規格：`admin-auth`、`login-rate-limiting`、`auth-test-coverage`、`member-portal-ui`、新增 `provider-verification`；變更軌跡歸檔於 `openspec/changes/archive/2026-06-11-audit-remediation/`。
