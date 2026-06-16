# feature-test-coverage Specification

## Purpose
定義核心業務流程端點的測試覆蓋契約：Provider 課程 CRUD、時段管理、Admin 使用者操作、通知直接觸發路徑、預約列表端點。補齊 2026-06-11 稽核報告識別的 Feature test 缺口，確保這些端點的回歸能被測試套件即時攔截。

## ADDED Requirements

---

### Requirement: Provider 課程 CRUD 測試覆蓋
測試套件 SHALL 驗證 `POST /api/provider/offers`、`PUT /api/provider/offers/{id}`、`DELETE /api/provider/offers/{id}` 的正常流程與所有權邊界。

#### Scenario: Provider 建立課程成功
- **WHEN** 已認證的 Provider 送出有效的 title / location / price / region 至 `POST /api/provider/offers`
- **THEN** 回傳 HTTP 201，body 包含 `{ status: true, data: { id, title, provider_id } }`，DB 存在對應記錄且 `provider_id` 為當前 Provider

#### Scenario: 建立課程缺少必填欄位回傳 422
- **WHEN** Provider 送出缺少 `title` 或 `price` 的請求至 `POST /api/provider/offers`
- **THEN** 回傳 HTTP 422，body 包含對應欄位的驗證錯誤

#### Scenario: Provider 更新自己的課程
- **WHEN** 已認證的 Provider 送出有效更新欄位至 `PUT /api/provider/offers/{id}`（該課程屬於此 Provider）
- **THEN** 回傳 HTTP 200，body 包含更新後的課程資料，DB 記錄已變更

#### Scenario: Provider 不可更新他人課程
- **WHEN** Provider A 嘗試 `PUT /api/provider/offers/{id}`，但該課程屬於 Provider B
- **THEN** 回傳 HTTP 403，body 包含 `{ status: false }`

#### Scenario: Provider 刪除自己的課程
- **WHEN** 已認證的 Provider 送出 `DELETE /api/provider/offers/{id}`（該課程屬於此 Provider）
- **THEN** 回傳 HTTP 200，body 包含 `{ status: true }`，DB 記錄已不存在

#### Scenario: Provider 不可刪除他人課程
- **WHEN** Provider A 嘗試 `DELETE /api/provider/offers/{id}`，但該課程屬於 Provider B
- **THEN** 回傳 HTTP 403

#### Scenario: 未認證請求被拒絕
- **WHEN** 未帶任何 token 送出 `POST /api/provider/offers`
- **THEN** 回傳 HTTP 401

---

### Requirement: Provider 時段管理測試覆蓋
測試套件 SHALL 驗證 `POST /api/provider/schedules`、`PUT /api/provider/schedules/{id}`、`DELETE /api/provider/schedules/{id}` 的正常流程、所有權邊界與容量驗證。

#### Scenario: Provider 建立時段成功
- **WHEN** Provider 送出有效的 diving_offer_id / scheduled_date（未來日期）/ start_time / max_participants 至 `POST /api/provider/schedules`
- **THEN** 回傳 HTTP 201，body 包含 `{ status: true, data: { id, scheduled_date, status: "open" } }`

#### Scenario: Provider 不可為他人課程建立時段
- **WHEN** Provider A 送出 Provider B 課程的 diving_offer_id 至 `POST /api/provider/schedules`
- **THEN** 回傳 HTTP 403

#### Scenario: 時段日期不可為過去
- **WHEN** Provider 送出 scheduled_date 為過去日期
- **THEN** 回傳 HTTP 422

#### Scenario: Provider 更新時段人數上限
- **WHEN** Provider 送出新的 max_participants 至 `PUT /api/provider/schedules/{id}`（新值 ≥ 目前 current_participants）
- **THEN** 回傳 HTTP 200，DB 記錄已更新

#### Scenario: 人數上限不可低於已確認人數
- **WHEN** Provider 嘗試將 max_participants 設為低於時段 current_participants 的值
- **THEN** 回傳 HTTP 422，message 說明不可低於已確認人數

#### Scenario: Provider 不可更新他人時段
- **WHEN** Provider A 嘗試 `PUT /api/provider/schedules/{id}`，但該時段屬於 Provider B
- **THEN** 回傳 HTTP 403

#### Scenario: 刪除（取消）時段同時將進行中預約標記為 provider_cancelled
- **WHEN** Provider 對有 pending/confirmed 預約的時段送出 `DELETE /api/provider/schedules/{id}`
- **THEN** 回傳 HTTP 200；時段 status 改為 `cancelled`（記錄保留，不刪除）；該時段的 pending/confirmed booking status 改為 `provider_cancelled`（記錄保留，不刪除）；已是終態的 booking（completed/expired/已取消）不受影響

---

### Requirement: Admin 使用者管理測試覆蓋
測試套件 SHALL 驗證 Admin 查詢會員/教練列表、切換帳號啟用狀態、切換教練驗證狀態的正確行為。

#### Scenario: Admin 取得會員列表
- **WHEN** Admin 送出 `GET /api/admin/members`
- **THEN** 回傳 HTTP 200，data 陣列只包含 role=member 的使用者，含 meta 分頁資訊

#### Scenario: Admin 取得教練列表
- **WHEN** Admin 送出 `GET /api/admin/providers`
- **THEN** 回傳 HTTP 200，data 陣列只包含 role=provider 的使用者

#### Scenario: Admin 切換會員帳號啟用狀態
- **WHEN** Admin 送出 `POST /api/admin/members/{id}/toggle-active`（目標會員 is_active=true）
- **THEN** 回傳 HTTP 200，DB 記錄 is_active 由 true 變為 false

#### Scenario: Admin 切換教練驗證狀態並清除快取
- **WHEN** Admin 送出 `POST /api/admin/providers/{id}/toggle-verified`（目標教練 is_verified=false）
- **THEN** 回傳 HTTP 200，provider_profiles.is_verified 由 false 變為 true，diving_offers 快取被清除（後續公開列表可見該教練課程）

#### Scenario: 非 Admin 角色被拒絕
- **WHEN** role=member 的使用者送出 `GET /api/admin/members`
- **THEN** 回傳 HTTP 403

---

### Requirement: 通知直接觸發路徑測試覆蓋
測試套件 SHALL 驗證預約 controller 操作直接觸發的通知事件，補足 Scheduler 路徑以外的覆蓋缺口。收件者依實際 controller 實作：新預約與 member 取消通知教練，其餘通知學員。

#### Scenario: 建立預約觸發 BookingCreatedNotification 至教練
- **WHEN** Member 成功建立預約（`POST /api/member/bookings`）
- **THEN** `BookingCreatedNotification` 被派發至該預約所屬的 **Provider**（`MemberBookingController::store` 呼叫 `$provider->notify(...)`）

#### Scenario: Provider 確認預約觸發 BookingConfirmedNotification 至學員
- **WHEN** Provider 對 pending 預約呼叫 `PUT /api/provider/bookings/{id}/confirm`
- **THEN** `BookingConfirmedNotification` 被派發至該預約的 **Member**

#### Scenario: Provider 拒絕預約觸發 BookingRejectedNotification 至學員
- **WHEN** Provider 對 pending 預約呼叫 `PUT /api/provider/bookings/{id}/reject`
- **THEN** `BookingRejectedNotification` 被派發至該預約的 **Member**

#### Scenario: Member 取消預約觸發 BookingCancelledNotification 至教練
- **WHEN** Member 對 pending 預約呼叫 `DELETE /api/member/bookings/{id}`
- **THEN** `BookingCancelledNotification` 被派發至該預約所屬的 **Provider**（`cancelledBy: 'member'`）

#### Scenario: Provider 取消預約觸發 BookingCancelledNotification 至學員
- **WHEN** Provider 對 confirmed 預約呼叫 `PUT /api/provider/bookings/{id}/cancel`
- **THEN** `BookingCancelledNotification` 被派發至該預約的 **Member**（`cancelledBy: 'provider'`）

---

### Requirement: 預約列表端點測試覆蓋
測試套件 SHALL 驗證下列三個已存在路由的資料隔離行為：
- `GET /api/member/bookings`（middleware: `auth:sanctum`，資料隔離在 controller `where member_id = auth()->id()`）
- `GET /api/provider/bookings`（middleware: `auth:sanctum`，資料隔離在 controller `whereHas schedule.provider_id`）
- `GET /api/admin/bookings`（middleware: `auth:sanctum` + `admin`，EnsureAdmin 於 middleware 層強制 403）

#### Scenario: Member 只能看到自己的預約
- **WHEN** Member A 呼叫 `GET /api/member/bookings`，系統中同時存在 Member A 與 Member B 各一筆預約
- **THEN** 回傳 data 陣列長度為 1，且 id 為 Member A 的 booking id；Member B 的 booking id 不在 data 中

#### Scenario: Provider 只能看到屬於自己課程的預約
- **WHEN** Provider A 呼叫 `GET /api/provider/bookings`，系統中有屬於 Provider A 課程與 Provider B 課程各一筆預約
- **THEN** 回傳 data 中只包含 Provider A 課程的 booking id；Provider B 課程的 booking id 不在 data 中

#### Scenario: Admin 可查看所有預約
- **WHEN** Admin 呼叫 `GET /api/admin/bookings`，系統中有 2 筆不同 Member 的預約
- **THEN** 回傳 data count ≥ 2，兩筆 booking id 均在 data 中

#### Scenario: 未認證請求被拒絕
- **WHEN** 未帶 token 呼叫 `GET /api/member/bookings`
- **THEN** 回傳 HTTP 401
