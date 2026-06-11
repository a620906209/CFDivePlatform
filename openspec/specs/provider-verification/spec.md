# provider-verification Specification

## Purpose

定義教練資質審核的完整生命週期：教練上傳證照送審、Admin 審核裁決（通過/駁回含原因）、結果通知，以及「未通過審核（approved）之教練的課程不對公開端點曝光、不可接受新預約」的可見性約束。

## ADDED Requirements

### Requirement: 教練驗證狀態機
`provider_profiles.verification_status` SHALL 為四狀態字串：`unsubmitted`（註冊預設）、`pending`、`approved`、`rejected`。合法轉移僅限：`unsubmitted→pending`（送審）、`pending→approved`（通過）、`pending→rejected`（駁回）、`rejected→pending`（重新送審，同時清空 `rejection_reason`）、`approved→rejected`（撤銷，原因必填）。原 boolean `is_verified` 欄位移除，API 輸出以 accessor 保留 `is_verified`（= status 為 approved）。

#### Scenario: 新教練註冊預設未送審
- **WHEN** 教練完成註冊
- **THEN** `verification_status = unsubmitted`，不出現在 Admin 待審佇列

#### Scenario: 非法轉移被拒絕
- **WHEN** 嘗試對 `unsubmitted` 教練執行 approve，或對 `approved` 教練執行 approve
- **THEN** 回傳 HTTP 422，狀態不變

---

### Requirement: 教練上傳證照與送審
教練 SHALL 能於後台上傳 1~3 張證照圖片（jpeg/png/webp、≤10MB，伺服器端壓縮為 `.jpg`，存 `providers/{user_id}/certifications/`）並送出審核。證照僅於 `unsubmitted` / `rejected` 狀態可增刪；`pending` / `approved` 狀態鎖定（審核依據不可變動）。送審需至少 1 張證照。

#### Scenario: 上傳證照
- **WHEN** `unsubmitted` 教練 `POST /api/provider/verification/certifications` 上傳合法圖片且現有 < 3 張
- **THEN** 壓縮儲存並建立 `provider_certifications` 紀錄，回傳圖片資訊

#### Scenario: 超過 3 張上限
- **WHEN** 教練已有 3 張證照再上傳
- **THEN** 回傳 HTTP 422

#### Scenario: 送審成功
- **WHEN** 教練至少有 1 張證照，`POST /api/provider/verification/submit`
- **THEN** 狀態轉為 `pending`，`rejection_reason` 清空

#### Scenario: 無證照不可送審
- **WHEN** 教練無任何證照即送審
- **THEN** 回傳 HTTP 422，狀態不變

#### Scenario: pending 期間證照鎖定
- **WHEN** `pending` 或 `approved` 教練嘗試上傳或刪除證照
- **THEN** 回傳 HTTP 422

#### Scenario: 查詢自身驗證狀態
- **WHEN** 教練 `GET /api/provider/verification`
- **THEN** 回傳 `status`、`rejection_reason`、`certifications[]`（id、url）

---

### Requirement: Admin 審核佇列與裁決
Admin SHALL 能查詢審核佇列（`GET /api/admin/verifications?status=pending`，預設 pending，含教練資料與證照 URL），並對 `pending` 教練執行通過（`PUT /api/admin/verifications/{userId}/approve`）或駁回（`PUT /api/admin/verifications/{userId}/reject`，`reason` 必填、max 500）。Admin SHALL 能駁回 `approved` 教練（撤銷驗證，原因必填）。裁決後 SHALL flush `diving_offers` 快取（可見性立即生效）。原 `PUT /api/admin/providers/{id}/toggle-verified` 端點已移除。

#### Scenario: 通過審核
- **WHEN** Admin 對 pending 教練執行 approve
- **THEN** 狀態轉 `approved`，教練課程立即恢復公開可見性（不受 180 秒快取影響）

#### Scenario: 駁回需附原因
- **WHEN** Admin 執行 reject 未帶 `reason`
- **THEN** 回傳 HTTP 422，狀態不變

#### Scenario: 撤銷已通過教練
- **WHEN** Admin 對 approved 教練執行 reject 並附原因
- **THEN** 狀態轉 `rejected`，課程立即從公開列表消失；既有預約照常

#### Scenario: 舊 toggle 端點已移除
- **WHEN** 任何人請求 `PUT /api/admin/providers/{id}/toggle-verified`
- **THEN** 回傳 HTTP 404

---

### Requirement: 審核結果通知
系統 SHALL 於 approve / reject 後通知該教練（站內 + Email）：通過通知告知課程已可公開曝光；駁回通知包含駁回原因。通知失敗不阻斷審核主流程。

#### Scenario: 通過通知
- **WHEN** Admin approve
- **THEN** 教練收到站內通知與 Email，內容含審核通過訊息

#### Scenario: 駁回通知含原因
- **WHEN** Admin reject 並附原因
- **THEN** 教練收到的通知內容包含該原因

---

### Requirement: 未通過審核教練的課程不對公開端點曝光
公開課程端點（`GET /api/diving-offers` 列表、`GET /api/diving-offers/{id}` 詳情、`/{id}/schedules`、`/{id}/reviews`）SHALL 僅回傳符合以下任一條件的課程：(a) `provider_id` 為 null（平台自有資料）；(b) 課程所屬教練 `verification_status = 'approved'`。其餘狀態（unsubmitted / pending / rejected，或無 ProviderProfile）的課程在列表中排除、詳情與子端點回傳 404。

#### Scenario: approved 教練的課程正常曝光
- **WHEN** 匿名使用者請求公開課程端點
- **THEN** approved 教練的課程出現在結果中，詳情/時段/評價端點照常回傳

#### Scenario: 非 approved 教練的課程不曝光
- **WHEN** 課程教練狀態為 unsubmitted / pending / rejected
- **THEN** 列表排除該課程，詳情/時段/評價端點回傳 404

#### Scenario: provider_id 為 null 的課程不受限
- **WHEN** 匿名使用者請求公開課程端點，課程的 `provider_id` 為 null
- **THEN** 課程正常曝光

---

### Requirement: 未通過審核教練的課程不可建立新預約
`POST /api/member/bookings` SHALL 在建立預約前驗證 schedule 所屬課程可預約（`provider_id` 為 null 或教練 `verification_status = 'approved'`），不符時回傳 HTTP 422，不建立預約。既有預約（pending / confirmed / completed）SHALL 不受教練驗證狀態變動影響：教練仍可處理 pending、confirmed 的聊天與完課流程照常、completed 可正常評價。

#### Scenario: 未通過審核教練課程的新預約被拒絕
- **WHEN** 會員以非 approved 教練課程的 schedule_id 送出預約
- **THEN** 回傳 HTTP 422，`{ status: false, message: "此課程目前不開放預約" }`，不建立 Booking

#### Scenario: 教練被撤銷驗證後既有預約照常
- **WHEN** 教練在預約 confirmed 之後被撤銷驗證（approved→rejected）
- **THEN** 該預約的聊天、完課、評價流程照常運作；僅新預約被擋

---

### Requirement: 教練自有管理端點不受可見性限制
Provider 對自己課程的管理端點（`/api/provider/offers*`）與 Admin 管理端點（`/api/admin/offers*`）SHALL 不受公開可見性過濾影響，未通過審核的教練仍可登入、編輯與管理自己的課程、上傳證照與送審。

#### Scenario: 未通過審核教練仍可管理自己的課程
- **WHEN** 未通過審核教練以有效 token 請求 `GET /api/provider/offers`
- **THEN** 回傳該教練的全部課程
