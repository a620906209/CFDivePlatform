## 1. T1.1 封鎖 admin/register（P0）

- [x] 1.1 移除 `routes/api.php` 的公開 `POST /api/admin/register` 路由
- [x] 1.2 移除 `AuthController::registerAdmin()` 方法
- [x] 1.3 移除 `app/Docs/AuthApiDoc.php` 對應 Swagger 區塊
- [x] 1.4 新增 `app/Console/Commands/CreateAdminUser.php`（`app:create-admin`，密碼 min:8，支援 --position / --department，未帶 --password 時互動式輸入）
- [x] 1.5 [整合測試] `AdminAccountCreationTest`：端點回 404 不建帳號、command 建立 admin+profile、重複 email 失敗、弱密碼失敗（4 案例）
- [x] 1.6 更新 `admin-auth` 規格：新增「管理員帳號建立途徑」requirement
- [x] 1.7 確認前端無 admin 註冊入口（無引用）、Seeder 不受影響

## 2. T1.2 同步 login-rate-limiting 規格

- [x] 2.1 規格門檻 5/min → 10/min（與 `throttle:10,1` 實作及 AuthRateLimitTest 一致），註記放寬理由

## 3. T2.1 is_verified 最小業務語意（P1）

- [x] 3.1 `DivingOffer` 新增 `scopeVisibleToPublic`（provider_id null 或教練已驗證）
- [x] 3.2 `DivingOfferController` 公開 index / show 套用 scope
- [x] 3.3 `AdminUserController::toggleProviderVerified` 後 flush `diving_offers` 快取 tag
- [x] 3.4 新增 `provider-verification` 規格（含已知限制 Notes）
- [x] 3.5 [整合測試] `DivingOfferVisibilityTest`：列表含已驗證/排除未驗證/null 不受限、詳情 404、toggle 立即生效（快取失效）、教練自有端點不受限（7 案例）

## 4. T3.1 預約核心測試（P2）

- [x] 4.1 [整合測試] `BookingLifecycleTest`：狀態機合法/非法轉移、pending 不佔名額、價格快照、24h 取消截止、名額釋放、跨用戶授權邊界（17 案例）
- [x] 4.2 [整合測試] `BookingOversellTest`：confirm 時防超賣不變式、full 擋新預約、釋放後可再確認（3 案例）
- [x] 4.3 [整合測試] `BookingSchedulerTest`：`app:expire-pending-bookings` 48h 邊界、`app:complete-finished-bookings` 日期邊界與通知（6 案例）
- [x] 4.4 [整合測試] `BookingChatAuthTest`：HTTP 端點與 presence channel 雙防線（8 案例）
- [x] 4.5 [整合測試] `AdminEndpointAuthTest`：六個 admin GET 端點 401/403/200 矩陣、Admin 不可繞過狀態機（5 案例）

## 5. T4 規格清理

- [x] 5.1 補 `auth-test-coverage` 歸檔遺留的 TBD Purpose
- [x] 5.2 `auth-test-coverage` admin register 場景改寫為「公開註冊端點保持關閉」
- [x] 5.3 同步移除 `AuthLoginTest` 中測已刪端點的 2 個測試
- [x] 5.4 修正 `member-portal-ui` 前端 repo 描述（實際位於本 repo `frontend/`）
- [x] 5.5 `admin-auth` 補 check-member / check-provider 查詢端點規格

## 6. 驗證

- [x] 6.1 容器內 `php artisan test` 全綠：146 passed / 378 assertions
- [x] 6.2 確認本機 CourseImageTest 失敗為環境因素（laragon PHP 缺 GD），非回歸
