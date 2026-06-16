## Context

現有 187 個 Feature test 分佈在認證、狀態機邊界、聊天授權等安全層，核心業務端點（Provider 課程管理、時段管理、Admin 使用者操作、通知觸發、預約列表）完全未覆蓋。缺口已於 2026-06-11 稽核報告中識別（通知系統「僅 Scheduler 路徑覆蓋」），此 change 補齊這五個區塊。

**現有 Factory 狀況**：`database/factories/` 只有 `UserFactory`。`DivingOffer`、`CourseSchedule`、`Booking` 均無 factory——現有測試一律使用 `Model::create([...])` 直接建立，新測試沿用此慣例，不新增 factory（避免引入 app 行為）。

**認證方式**：現有 Feature test 全部使用 `$this->actingAs($user)` + `getJson/postJson` 等 HTTP helpers，不手動建立 token。新測試維持相同模式。

## Goals / Non-Goals

**Goals:**
- 5 個新 Feature test 類別：`ProviderOfferCrudTest`、`ProviderScheduleCrudTest`、`AdminUserManagementTest`、`NotificationTriggerTest`、`BookingListTest`
- 涵蓋正常流程（happy path）+ 所有權邊界（403）+ 輸入驗證（422）
- 通知測試補齊直接事件路徑（建立/確認/拒絕/取消），不只驗證 Scheduler 完課路徑

**Non-Goals:**
- 不修改任何 app 程式碼
- 不補 Unit test（已於上一個 change 完成）
- 不測試 UI / 前端行為
- 不測試通知 Email 實際發送（`notification-email` 規格另有覆蓋）

## Decisions

### D1：延續現有 Feature test 架構，不引入 Service layer mock

現有測試全部使用 `RefreshDatabase` + 真實 DB，未 mock 任何 repository/service。維持一致性優先於執行速度——187 案例目前 42 秒，新增 ~50 案例後預估 55 秒以內，可接受。

**替代方案**：mock `Cache::tags()` 與 `Notification`。決議：`Notification::fake()` 繼續使用（現有慣例），`Cache::tags()` 讓它真實運行（Redis 在 Docker 中可用）。

### D2：通知測試使用 `Notification::fake()` + `assertSentTo()`

現有 `BookingSchedulerTest` 已驗證 Scheduler 完課觸發通知。`NotificationTriggerTest` 補齊 controller 直接觸發的路徑（`store`、`confirm`、`reject`、`member cancel`、`provider cancel`），同樣 fake 後斷言 `Notification::assertSentTo()`，不依賴 queue worker。

### D3：預約列表測試不涵蓋分頁（暫緩 O3.2）

`MemberBookingController::index()` 目前為 `->get()` 全量回傳（O3.2 未完成），測試僅斷言回傳正確的資料集合（`data` 陣列包含/不包含正確的 booking）。分頁斷言待 O3.2 實作後加入。

### D4：所有權邊界是必測案例，非選測

Provider CRUD 的「他人不可操作」403 路徑在現有 `BookingLifecycleTest` 已有先例，這裡對 Offer 和 Schedule 同樣必須測。Admin 操作已有 `AdminEndpointAuthTest` 覆蓋角色層的 401/403，`AdminUserManagementTest` 聚焦資料正確性與操作副作用（is_active 切換、is_verified 切換 + 快取清除）。

## Risks / Trade-offs

- **`Cache::tags()` 與 driver 相容性**：`phpunit.xml` 設定 `CACHE_STORE=array`。Laravel 11 的 `array` store 繼承 `TaggableStore`，因此 `Cache::tags(['diving_offers'])->flush()` 在測試環境可正常執行（`DivingOfferVisibilityTest::test_toggle_verified_takes_effect_immediately_despite_cache` 已驗證）。真正的風險是：若未來 CI 或本機改用 `file`、`database` 等不支援 tags 的 driver，所有呼叫 `Cache::tags()` 的 controller 測試都會拋出 `BadMethodCallException`。→ 緩解：在 tasks 加上驗收項目，確認 `phpunit.xml` 的 `CACHE_STORE` 為 `array` 或 `redis`，並加入 README 說明。
- **Admin toggle-verified 的快取副作用驗證方式**：`DivingOfferVisibilityTest` 已有端對端驗證（toggle → 公開列表立即消失）。`AdminUserManagementTest` 不重複這個斷言，只驗證 `is_verified` 欄位變更與 HTTP 200 即可，避免測試重複。
- **Notification::fake() 無法驗證 mail 實際送出**：這是刻意的邊界——只驗證通知物件被派發至正確收件者，不驗證 email 模板內容（屬於 `notification-email` 規格的範疇）。

## 各測試類別案例清單

| 測試類別 | 案例 | 涵蓋邊界 |
|----------|------|---------|
| `ProviderOfferCrudTest` | store 成功、缺必填 422、update 成功、update 他人 403、destroy 成功、destroy 他人 403、未認證 401 | happy path + ownership + auth |
| `ProviderScheduleCrudTest` | store 成功、store 他人課程 403、過去日期 422、update max_participants 成功、update 低於 current 422、update 他人 403、destroy 同時取消 bookings | happy path + 容量不變式 + ownership |
| `AdminUserManagementTest` | 列出 members 只含 member role、列出 providers 只含 provider role、toggle-active 改變 is_active、toggle-verified 改變 is_verified、非 admin 403 | 資料正確性 + 角色隔離 |
| `NotificationTriggerTest` | BookingCreated→provider、Confirmed→member、Rejected→member、CancelledByMember→provider、CancelledByProvider→member | 5 條直接觸發路徑，收件者各不同 |
| `BookingListTest` | `GET /api/member/bookings`（MemberBookingController::index）、`GET /api/provider/bookings`（ProviderBookingController::index）、`GET /api/admin/bookings`（AdminBookingController::index）——各建兩方資料斷言隔離、admin 全看、未認證 401 | 路由已確認存在；角色資料隔離邊界 |
