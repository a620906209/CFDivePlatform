## 1. 公開子端點套用可見性

- [x] 1.1 `ScheduleController::publicList`：`DivingOffer::findOrFail($offerId)` → `DivingOffer::visibleToPublic()->findOrFail($offerId)`
- [x] 1.2 `ReviewController::publicList`：同 1.1
- [x] 1.3 [整合測試] `DivingOfferVisibilityTest` 擴充：未驗證教練課程的 `/schedules` 回 404、`/reviews` 回 404、已驗證教練兩端點正常（3~4 案例）

## 2. 預約入口檢查

- [x] 2.1 `MemberBookingController::store` Layer 1 增加課程可預約檢查（provider_id null 或教練已驗證），不符回 422 `此課程目前不開放預約`
- [x] 2.2 [整合測試] `BookingLifecycleTest` 擴充：未驗證教練課程的 schedule 不可建立預約（422）、已驗證可預約、教練被取消驗證後既有 confirmed 預約聊天/完課照常（3 案例）

## 3. 規格同步

- [x] 3.1 `openspec/specs/provider-verification/spec.md`：新增「公開子端點套用相同可見性」「未驗證教練課程不可建立新預約」requirements（含既有預約不受影響 scenario），移除 Notes 已知限制段落

## 4. 驗證

- [x] 4.1 容器內 `php artisan test` 全綠（基準：146 passed）
- [x] 4.2 手動驗證 DemoSeeder 未驗證教練課程：時段/評價查詢 404、無法新預約
