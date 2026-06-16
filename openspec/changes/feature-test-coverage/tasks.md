## 0. 前置確認（實作前一次性）

- [x] 0.1 確認 `phpunit.xml` 的 `CACHE_STORE=array`（Laravel 11 array store 支援 tags，若非 array/redis 須先修正再實作）
- [x] 0.2 確認現有認證模式：所有新測試使用 `$this->actingAs($user)` + `postJson/putJson`，不手動建立 Sanctum token
- [x] 0.3 確認 factory 狀況：`DivingOffer`、`CourseSchedule`、`Booking` 均無 factory，一律使用 `Model::create([...])` 直接建立，不新增 factory

## 1. Provider 課程 CRUD 測試

- [x] 1.1 [整合測試] 建立 `tests/Feature/ProviderOfferCrudTest.php`，加入 private helpers：
  - `makeProvider(): User`（User::create + ProviderProfile::create，is_verified=true）
  - `makeOffer(User $provider): DivingOffer`（DivingOffer::create 最小必填欄位）
- [x] 1.2 [整合測試] `test_provider_creates_offer_successfully`：POST 201，data.provider_id = auth provider id，DB 存在
- [x] 1.3 [整合測試] `test_create_offer_missing_required_field_returns_422`：缺 title 送出，回傳 422（代表性一個欄位即可）
- [x] 1.4 [整合測試] `test_provider_updates_own_offer`：PUT 200，DB 欄位已變更
- [x] 1.5 [整合測試] `test_provider_cannot_update_others_offer`：Provider B 更新 Provider A 的課程，403
- [x] 1.6 [整合測試] `test_provider_deletes_own_offer`：DELETE 200，DB 記錄不存在
- [x] 1.7 [整合測試] `test_provider_cannot_delete_others_offer`：403
- [x] 1.8 [整合測試] `test_unauthenticated_request_is_rejected`：無 token POST，401

## 2. Provider 時段管理測試

- [x] 2.1 [整合測試] 建立 `tests/Feature/ProviderScheduleCrudTest.php`，加入 private helpers：
  - `makeProvider(): User`
  - `makeOffer(User $provider): DivingOffer`
  - `makeSchedule(DivingOffer $offer, int $currentParticipants = 0): CourseSchedule`
- [x] 2.2 [整合測試] `test_provider_creates_schedule_successfully`：POST 201，status=open
- [x] 2.3 [整合測試] `test_provider_cannot_create_schedule_for_others_offer`：403
- [x] 2.4 [整合測試] `test_past_date_returns_422`：scheduled_date = yesterday，422（代表性驗證案例）
- [x] 2.5 [整合測試] `test_provider_updates_schedule_max_participants`：PUT 200，DB 更新
- [x] 2.6 [整合測試] `test_max_participants_below_current_returns_422`：current_participants=3，PUT max_participants=2，422
- [x] 2.7 [整合測試] `test_provider_cannot_update_others_schedule`：403
- [x] 2.8 [整合測試] `test_delete_schedule_marks_active_bookings_as_provider_cancelled`：建立一筆 pending + 一筆 confirmed booking，DELETE 200；斷言時段 status=`cancelled`（記錄保留）；兩筆 booking status=`provider_cancelled`（記錄保留，不刪除）；不在 pending/confirmed 的 booking 不受影響

## 3. Admin 使用者管理測試

- [x] 3.1 [整合測試] 建立 `tests/Feature/AdminUserManagementTest.php`，加入 private helpers：
  - `makeAdmin(): User`（role=admin）
  - `makeMember(): User`（role=member + MemberProfile）
  - `makeProvider(bool $isVerified = false): User`（role=provider + ProviderProfile）
- [x] 3.2 [整合測試] `test_admin_lists_members_only`：建立 member + provider 各一，GET /api/admin/members 回傳 data 只含 member role
- [x] 3.3 [整合測試] `test_admin_lists_providers_only`：GET /api/admin/providers 回傳 data 只含 provider role
- [x] 3.4 [整合測試] `test_admin_toggles_member_active_status`：目標 is_active=true → POST toggle-active → DB is_active=false
- [x] 3.5 [整合測試] `test_admin_toggles_provider_verified_status`：目標 is_verified=false → POST toggle-verified → DB is_verified=true（不重複 DivingOfferVisibilityTest 的快取副作用驗證，只斷言 DB 欄位與 HTTP 200）
- [x] 3.6 [整合測試] `test_non_admin_is_forbidden`：role=member actingAs，GET /api/admin/members 回傳 403

## 4. 通知直接觸發路徑測試

收件者依實際 controller 實作（已查閱原始碼 + app/Notifications/ 確認 class 存在）：
- `BookingCreatedNotification`：`MemberBookingController::store` → `$provider->notify(new BookingCreatedNotification($booking))` → **教練**收到
- `BookingConfirmedNotification`：`ProviderBookingController::confirm` → `$booking->member->notify(...)` → **學員**收到
- `BookingRejectedNotification`：`ProviderBookingController::reject` → `$booking->member->notify(...)` → **學員**收到
- `BookingCancelledNotification`（cancelledBy: 'member'）：`MemberBookingController::destroy` → `$provider->notify(...)` → **教練**收到
- `BookingCancelledNotification`（cancelledBy: 'provider'）：`ProviderBookingController::cancel` → `$booking->member->notify(...)` → **學員**收到
- 注意：member/provider cancel 使用**同一個 class** `BookingCancelledNotification`，`assertSentTo` 只需確認 class 名稱與收件者即可

- [x] 4.1 [整合測試] 建立 `tests/Feature/NotificationTriggerTest.php`，setUp 加 `Notification::fake()`；加入 helper 建立 member + provider（含 ProviderProfile） + offer + schedule（open, 未來日期） + pending booking
- [x] 4.2 [整合測試] `test_booking_created_notifies_provider`：Member `POST /api/member/bookings` → `assertSentTo($provider, BookingCreatedNotification::class)`
- [x] 4.3 [整合測試] `test_booking_confirmed_notifies_member`：Provider `PUT /api/provider/bookings/{id}/confirm` → `assertSentTo($member, BookingConfirmedNotification::class)`
- [x] 4.4 [整合測試] `test_booking_rejected_notifies_member`：Provider `PUT /api/provider/bookings/{id}/reject` → `assertSentTo($member, BookingRejectedNotification::class)`
- [x] 4.5 [整合測試] `test_member_cancel_notifies_provider`：Member `DELETE /api/member/bookings/{id}`（pending, 遠未來日期） → `assertSentTo($provider, BookingCancelledNotification::class)`
- [x] 4.6 [整合測試] `test_provider_cancel_notifies_member`：Provider `PUT /api/provider/bookings/{id}/cancel`（先 confirm 再 cancel） → `assertSentTo($member, BookingCancelledNotification::class)`

## 5. 預約列表端點測試

路由對應（已查閱 routes/api.php + EnsureAdmin middleware 確認）：
- `GET /api/member/bookings` → middleware: `auth:sanctum`（無 role 檢查）→ `MemberBookingController::index`，隔離靠 `where member_id = auth()->id()`
- `GET /api/provider/bookings` → middleware: `auth:sanctum`（無 role 檢查）→ `ProviderBookingController::index`，隔離靠 `whereHas schedule.provider_id`
- `GET /api/admin/bookings` → middleware: `auth:sanctum` + `admin`（`EnsureAdmin` 強制 403）→ `AdminBookingController::index`

- [x] 5.1 [整合測試] 建立 `tests/Feature/BookingListTest.php`，加入 helpers：
  - `makeProviderWithSchedule(): array`（回傳 [provider, schedule]，含 ProviderProfile + DivingOffer + CourseSchedule）
  - `makePendingBooking(User $member, CourseSchedule $schedule): Booking`
- [x] 5.2 [整合測試] `test_member_sees_only_own_bookings`：MemberA 和 MemberB 各建一筆 pending booking（同一 schedule），actingAs MemberA → `GET /api/member/bookings`，斷言 data count=1 且 data[0].id = MemberA booking id
- [x] 5.3 [整合測試] `test_provider_sees_only_own_course_bookings`：ProviderA 和 ProviderB 各有一筆 booking，actingAs ProviderA → `GET /api/provider/bookings`，斷言 data 中只含 ProviderA schedule 的 booking id
- [x] 5.4 [整合測試] `test_admin_sees_all_bookings`：建立 2 筆不同 member 的 booking，actingAs admin → `GET /api/admin/bookings`，斷言兩個 booking id 均在 data 中
- [x] 5.5 [整合測試] `test_unauthenticated_request_returns_401`：無 token `GET /api/member/bookings`，401

## 6. 驗收

- [x] 6.1 容器內 `php artisan test` 全綠（基準 187 passed，預估完成後 ≥ 230 passed）
- [x] 6.2 確認 5 個新測試類別的案例方法數與 spec scenarios 對應（可多不可少）
- [x] 6.3 確認 `phpunit.xml` 仍為 `CACHE_STORE=array`，未被意外改動
- [x] 6.4 將本 change 的 specs 增量套用至主規格 `openspec/specs/feature-test-coverage/spec.md`
