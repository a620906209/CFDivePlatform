# role-authorization Specification

## Purpose

依使用者角色限制 member、provider 專屬 API 路由群組的存取，避免已登入的其他角色跨角色呼叫課程、時段、預約等業務端點。

## Requirements

### Requirement: Member 專屬路由僅限 member 角色存取
`/api/member/*` 路由群組（不含公開的 `POST /api/member/register`、`POST /api/member/login`）SHALL 只允許 `role = member` 的已登入使用者存取。其他角色 MUST 收到 HTTP 403，回應為 `{ "status": false, "message": "無權限存取" }`，且請求 MUST NOT 產生任何資料變更。未帶 token 的請求 MUST 收到 HTTP 401。

#### Scenario: member 可存取 member 路由
- **WHEN** 一個 `role = member` 的使用者，以該使用者的 token 呼叫 `GET /api/member/profile` 與 `GET /api/member/bookings`
- **THEN** 回傳 HTTP 200，`status` 為 `true`

#### Scenario: provider 呼叫 member 路由被拒絕
- **WHEN** 一個 `role = provider` 的使用者，以該使用者的 token 呼叫 `GET /api/member/profile`、`GET /api/member/bookings`、`POST /api/member/bookings`、`POST /api/member/reviews`
- **THEN** 每個請求都回傳 HTTP 403，body 為 `{ "status": false, "message": "無權限存取" }`

#### Scenario: admin 呼叫 member 路由被拒絕
- **WHEN** 一個 `role = admin` 的使用者，以該使用者的 token 呼叫 `GET /api/member/profile`、`GET /api/member/bookings`、`POST /api/member/bookings`
- **THEN** 每個請求都回傳 HTTP 403，body 為 `{ "status": false, "message": "無權限存取" }`

#### Scenario: 非 member 建立預約不會產生資料
- **WHEN** 一個可預約的課程時段，以及一個 `role = provider` 的使用者，以該使用者的 token 呼叫 `POST /api/member/bookings` 並帶入合法的時段 id
- **THEN** 回傳 HTTP 403，`bookings` 資料表筆數不變，該時段的 `current_participants` 不變

#### Scenario: 未登入呼叫 member 路由
- **WHEN** 不帶 token 呼叫 `GET /api/member/profile`、`GET /api/member/bookings`
- **THEN** 回傳 HTTP 401

### Requirement: Provider 專屬路由僅限 provider 角色存取
`/api/provider/*` 路由群組（不含公開的 `POST /api/provider/register`、`POST /api/provider/login`）SHALL 只允許 `role = provider` 的已登入使用者存取，且 MUST NOT 以教練審核狀態作為進入條件。其他角色 MUST 收到 HTTP 403，回應為 `{ "status": false, "message": "無權限存取" }`，且請求 MUST NOT 產生任何資料變更。未帶 token 的請求 MUST 收到 HTTP 401。

#### Scenario: provider 可存取 provider 路由
- **WHEN** 一個 `role = provider` 的使用者，以該使用者的 token 呼叫 `GET /api/provider/profile`、`GET /api/provider/offers`、`GET /api/provider/schedules`、`GET /api/provider/bookings`
- **THEN** 回傳 HTTP 200，`status` 為 `true`

#### Scenario: 未通過審核的 provider 仍可進入 provider 路由
- **WHEN** 一個 `role = provider`、`verification_status` 不是 approved（例如 unsubmitted 或 pending）的使用者，以該使用者的 token 呼叫 `GET /api/provider/verification`
- **THEN** 回傳 HTTP 200，不會因角色 middleware 回 403

#### Scenario: member 呼叫 provider 路由被拒絕
- **WHEN** 一個 `role = member` 的使用者，以該使用者的 token 呼叫 `GET /api/provider/profile`、`GET /api/provider/offers`、`POST /api/provider/offers`、`POST /api/provider/schedules`、`GET /api/provider/bookings`、`GET /api/provider/verification`
- **THEN** 每個請求都回傳 HTTP 403，body 為 `{ "status": false, "message": "無權限存取" }`

#### Scenario: admin 呼叫 provider 路由被拒絕
- **WHEN** 一個 `role = admin` 的使用者，以該使用者的 token 呼叫 `GET /api/provider/profile`、`GET /api/provider/offers`、`POST /api/provider/offers`、`GET /api/provider/bookings`
- **THEN** 每個請求都回傳 HTTP 403，body 為 `{ "status": false, "message": "無權限存取" }`

#### Scenario: 非 provider 建立課程不會產生資料
- **WHEN** 一個 `role = member` 的使用者，以該使用者的 token 呼叫 `POST /api/provider/offers` 並帶入通過驗證的完整課程資料
- **THEN** 回傳 HTTP 403，`diving_offers` 資料表筆數不變

#### Scenario: 未登入呼叫 provider 路由
- **WHEN** 不帶 token 呼叫 `GET /api/provider/profile`、`GET /api/provider/offers`
- **THEN** 回傳 HTTP 401

### Requirement: 跨角色共用路由不受角色限制影響
通知（`/api/notifications/*`）與預約即時訊息（`/api/bookings/{booking}/messages*`、`/api/bookings/messages/unread-counts`）路由 SHALL 維持既有行為：member 與 provider 皆可存取，存取權限由既有的資源歸屬檢查決定，不套用 member 或 provider 角色限制。

#### Scenario: member 與 provider 皆可讀取通知
- **WHEN** 一個 `role = member` 與一個 `role = provider` 的使用者，分別以各自的 token 呼叫 `GET /api/notifications`
- **THEN** 兩者皆回傳 HTTP 200

#### Scenario: member 與 provider 皆可讀取未讀訊息數
- **WHEN** 一個 `role = member` 與一個 `role = provider` 的使用者，分別以各自的 token 呼叫 `GET /api/bookings/messages/unread-counts`
- **THEN** 兩者皆回傳 HTTP 200
