## Why

稽核報告（2026-06-11）標註通知系統「僅 Scheduler 路徑覆蓋」，且 Provider Offer/Schedule CRUD、Admin 使用者管理、預約列表端點至今零 Feature test。187 個現有案例全集中在認證與狀態機邊界，核心業務流程端點的回歸一旦發生，測試套件無法攔截。

## What Changes

- 新增 `ProviderOfferCrudTest`：教練課程的建立、更新、刪除，含所有權驗證（他人不可操作）
- 新增 `ProviderScheduleCrudTest`：課程時段的建立、更新、刪除，含容量驗證與所有權邊界
- 新增 `AdminUserManagementTest`：Admin 查詢使用者列表、切換教練驗證狀態（toggle-verified）
- 新增 `NotificationTriggerTest`：預約事件直接觸發通知（建立、確認、拒絕、取消、完成）；補足現有 Scheduler 路徑以外的觸發路徑
- 新增 `BookingListTest`：Member / Provider / Admin 各自的預約列表端點（分頁與角色過濾）

## Capabilities

### New Capabilities
- `feature-test-coverage`：定義核心業務流程端點的測試覆蓋契約，涵蓋 Provider Offer CRUD、Schedule CRUD、Admin 使用者管理、通知觸發路徑、預約列表端點；對應 auth-test-coverage 在認證層的角色

### Modified Capabilities
<!-- 現有行為規格無異動，僅補測試覆蓋，無須 delta spec -->

## Impact

- 新增 Feature test 檔案：`tests/Feature/` 下 5 個新測試類別（約 40~55 案例）
- 不修改任何 app 程式碼
- 不影響現有 API 行為或資料結構
- 現有 187 案例零 regression（Unit test 已驗證）
