# provider-verification Specification

## Purpose

定義 `provider_profiles.is_verified` 的最小業務語意：未通過平台驗證的教練，其課程不對公開端點曝光。在完整教練審核流程（證照上傳、審核佇列、駁回原因）實作前，先以此約束堵住「未審核教練可公開曝光」的風險。

## ADDED Requirements

### Requirement: 未驗證教練的課程不對公開端點曝光
公開課程端點（`GET /api/diving-offers`、`GET /api/diving-offers/{id}`）SHALL 僅回傳符合以下任一條件的課程：(a) `provider_id` 為 null（平台自有資料）；(b) 課程所屬 Provider 的 `provider_profiles.is_verified = true`。未驗證教練的課程在列表中 SHALL 被排除，在詳情端點 SHALL 回傳 404。

#### Scenario: 已驗證教練的課程正常曝光
- **WHEN** 匿名使用者請求 `GET /api/diving-offers`
- **THEN** 已驗證教練（is_verified=true）的課程出現在結果中

#### Scenario: 未驗證教練的課程從列表排除
- **WHEN** 匿名使用者請求 `GET /api/diving-offers`
- **THEN** 未驗證教練（is_verified=false 或無 ProviderProfile）的課程不出現在結果中

#### Scenario: 未驗證教練的課程詳情回 404
- **WHEN** 匿名使用者請求 `GET /api/diving-offers/{id}`，該課程屬於未驗證教練
- **THEN** 回傳 HTTP 404

#### Scenario: provider_id 為 null 的課程不受限
- **WHEN** 匿名使用者請求公開課程端點，課程的 `provider_id` 為 null
- **THEN** 課程正常曝光

---

### Requirement: 驗證狀態切換立即生效
管理員透過 `PUT /api/admin/providers/{id}/toggle-verified` 切換驗證狀態後，公開課程列表的快取（`diving_offers` cache tag）SHALL 立即失效，下次請求反映最新可見性。

#### Scenario: 取消驗證後課程立即從公開列表消失
- **WHEN** 管理員將教練 is_verified 由 true 切為 false
- **THEN** 下一次 `GET /api/diving-offers` 請求不包含該教練的課程（不受 180 秒快取影響）

---

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

---

### Requirement: 教練自有管理端點不受可見性限制
Provider 對自己課程的管理端點（`/api/provider/offers*`）與 Admin 管理端點（`/api/admin/offers*`）SHALL 不受公開可見性過濾影響，未驗證教練仍可登入、編輯與管理自己的課程。

#### Scenario: 未驗證教練仍可管理自己的課程
- **WHEN** 未驗證教練以有效 token 請求 `GET /api/provider/offers`
- **THEN** 回傳該教練的全部課程

## Notes

完整審核流程（verification_status enum、證照上傳、審核佇列）見 `docs/analysis/2026-06-11-future-roadmap-feasibility.md` §2.1。
