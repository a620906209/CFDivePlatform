# Spec Delta

## MODIFIED Requirements

### Requirement: 預約列表端點測試覆蓋
測試套件 SHALL 驗證下列三個路由的資料隔離行為：
- `GET /api/member/bookings`（middleware: `auth:sanctum` + `member`，非 member 角色於 middleware 層強制 403；隔離靠 `where member_id = auth()->id()`）
- `GET /api/provider/bookings`（middleware: `auth:sanctum` + `provider`，非 provider 角色於 middleware 層強制 403；隔離靠 `whereHas schedule.provider_id`）
- `GET /api/admin/bookings`（middleware: `auth:sanctum` + `admin`，EnsureAdmin 於 middleware 層強制 403）

#### Scenario: Member 只能看到自己的預約
- **WHEN** Member A 呼叫 `GET /api/member/bookings`，系統中同時存在 Member A 與 Member B 各一筆預約
- **THEN** 回傳 data 只包含 Member A 的 booking id；Member B 的 booking id 不在 data 中

#### Scenario: Provider 只能看到屬於自己課程的預約
- **WHEN** Provider A 呼叫 `GET /api/provider/bookings`，系統中有屬於 Provider A 與 Provider B 課程各一筆預約
- **THEN** 回傳 data 只包含 Provider A 課程的 booking id；Provider B 課程的 booking id 不在 data 中

#### Scenario: Admin 可查看所有預約
- **WHEN** Admin 呼叫 `GET /api/admin/bookings`，系統中有 2 筆不同 Member 的預約
- **THEN** 回傳 data 包含兩筆 booking id

#### Scenario: 未認證請求被拒絕
- **WHEN** 未帶 token 呼叫 `GET /api/member/bookings`
- **THEN** 回傳 HTTP 401
