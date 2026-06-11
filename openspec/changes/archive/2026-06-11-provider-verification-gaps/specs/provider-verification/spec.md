## ADDED Requirements

### Requirement: 公開子端點套用相同可見性
課程的公開子端點（`GET /api/diving-offers/{id}/schedules`、`GET /api/diving-offers/{id}/reviews`）SHALL 套用與課程詳情相同的可見性規則：課程屬未驗證教練時回傳 HTTP 404。

#### Scenario: 隱藏課程的時段查詢回 404
- **WHEN** 匿名使用者請求 `GET /api/diving-offers/{id}/schedules`，該課程屬未驗證教練
- **THEN** 回傳 HTTP 404

#### Scenario: 隱藏課程的評價查詢回 404
- **WHEN** 匿名使用者請求 `GET /api/diving-offers/{id}/reviews`，該課程屬未驗證教練
- **THEN** 回傳 HTTP 404

#### Scenario: 可見課程的子端點正常
- **WHEN** 課程屬已驗證教練或 `provider_id` 為 null
- **THEN** 時段與評價端點照常回傳資料

---

### Requirement: 未驗證教練的課程不可建立新預約
`POST /api/member/bookings` SHALL 在建立預約前驗證 schedule 所屬課程可預約（`provider_id` 為 null 或教練 `is_verified = true`），不符時回傳 HTTP 422，不建立預約。既有預約（pending / confirmed / completed）SHALL 不受教練驗證狀態變動影響：教練仍可處理 pending、confirmed 的聊天與完課流程照常、completed 可正常評價。

#### Scenario: 未驗證教練課程的新預約被拒絕
- **WHEN** 會員以未驗證教練課程的 schedule_id 送出預約
- **THEN** 回傳 HTTP 422，`{ status: false, message: "此課程目前不開放預約" }`，不建立 Booking

#### Scenario: 教練被取消驗證後既有預約照常
- **WHEN** 教練在預約 confirmed 之後被取消驗證
- **THEN** 該預約的聊天、完課、評價流程照常運作；僅新預約被擋
