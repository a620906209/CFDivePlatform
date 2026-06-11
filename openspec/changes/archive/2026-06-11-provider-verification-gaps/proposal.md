## Why

2026-06-11 修補（archive: `2026-06-11-audit-remediation`）讓未驗證教練的課程從公開 `GET /api/diving-offers`（列表/詳情）消失，但 `provider-verification` 規格 Notes 載明三個遺留缺口：

1. `GET /api/diving-offers/{id}/schedules`（公開時段列表）未過濾——知道課程 id 仍可查到可預約時段
2. `GET /api/diving-offers/{id}/reviews`（公開評價列表）未過濾——隱藏課程的評價仍可讀取
3. `POST /api/member/bookings` 未檢查——會員可直接以 `schedule_id` 預約未驗證教練的課程，繞過整個可見性機制

缺口 3 最關鍵：可見性過濾的目的（平台對教練資質把關）在預約入口被完全繞過。

## What Changes

- `ScheduleController::publicList`：`DivingOffer::findOrFail` 改為 `DivingOffer::visibleToPublic()->findOrFail`，隱藏課程的時段查詢回 404
- `ReviewController::publicList`：同上，隱藏課程的評價查詢回 404
- `MemberBookingController::store`：建立預約前驗證 schedule 所屬課程對該會員「可預約」（課程屬已驗證教練或 `provider_id` 為 null），否則回 422
- **既有預約不受影響**：教練被取消驗證時，已成立的 pending / confirmed / completed 預約照常運作（含聊天、完課、評價）——只擋新預約，不毀既有合約
- 補充 Feature tests 並更新 `provider-verification` 規格（Notes 移除、改為正式 Requirements）

## Capabilities

### Modified Capabilities

- `provider-verification`：新增「公開子端點套用相同可見性」與「未驗證教練課程不可建立新預約」requirements；移除 Notes 中的已知限制聲明

## Impact

- 影響檔案：`ScheduleController`、`ReviewController`、`MemberBookingController`、`tests/Feature/DivingOfferVisibilityTest.php`（擴充）或新增測試檔
- 行為變更：DemoSeeder 未驗證教練的課程時段/評價將不可公開查詢、不可新預約
- 風險：低——`visibleToPublic` scope 已有 7 條測試保護，本 change 僅擴大套用範圍
- 不影響：教練自有管理端點（`/api/provider/*`）、Admin 端點、既有預約的聊天與評價流程
