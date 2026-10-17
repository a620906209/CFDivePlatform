# Tasks

測試集中在新檔 `tests/Feature/RoleAuthorizationTest.php`（結構參考 `tests/Feature/AdminEndpointAuthTest.php`）。每個測試的 docblock 要寫明「為什麼」：跨角色呼叫曾可建立非教練課程、非會員預約。

## 1. Member 角色 middleware

- [x] 1.1 [後端] 新增 `app/Http/Middleware/EnsureMember.php`（結構同 `EnsureAdmin`，改用 `isMember()`），並在 `bootstrap/app.php` 的 `$middleware->alias([...])` 加上 `'member' => \App\Http\Middleware\EnsureMember::class`；驗證：1.3 起的測試通過
- [x] 1.2 [後端] `routes/api.php` 的 member 群組改為 `Route::middleware(['auth:sanctum', 'member'])->prefix('member')`；驗證：`php artisan route:list --path=api/member` 顯示 `member` middleware，且 `register`、`login` 不受影響
- [x] 1.3 [整合測試] `test_member_can_access_member_routes`：member 呼叫 `GET /api/member/profile`、`GET /api/member/bookings` 回 200（對應 Scenario「member 可存取 member 路由」）
- [x] 1.4 [整合測試] `test_provider_is_rejected_on_member_routes`：provider 呼叫 `GET /api/member/profile`、`GET /api/member/bookings`、`POST /api/member/bookings`、`POST /api/member/reviews`，皆回 403，並斷言 body 為 `{ status: false, message: "無權限存取" }`；`POST /api/member/reviews` 另斷言 `reviews` 筆數不變（對應 Scenario「provider 呼叫 member 路由被拒絕」）
- [x] 1.5 [整合測試] `test_admin_is_rejected_on_member_routes`：admin 呼叫 `GET /api/member/profile`、`GET /api/member/bookings`、`POST /api/member/bookings`，皆回 403 與相同 body（對應 Scenario「admin 呼叫 member 路由被拒絕」）
- [x] 1.6 [整合測試] `test_non_member_booking_attempt_creates_no_booking`：建立可預約時段，provider 帶合法 `schedule_id` 呼叫 `POST /api/member/bookings`，回 403，`bookings` 筆數與 `current_participants` 不變（對應 Scenario「非 member 建立預約不會產生資料」）
- [x] 1.7 [整合測試] `test_unauthenticated_request_is_rejected_on_member_routes`：不帶 token 呼叫 `GET /api/member/profile`、`GET /api/member/bookings` 回 401（對應 Scenario「未登入呼叫 member 路由」）

## 2. Provider 角色 middleware

- [x] 2.1 [後端] 新增 `app/Http/Middleware/EnsureProvider.php`（結構同 `EnsureAdmin`，改用 `isProvider()`，**不檢查** `verification_status`），並在 `bootstrap/app.php` 加上 `'provider' => \App\Http\Middleware\EnsureProvider::class`；驗證：2.3 起的測試通過
- [x] 2.2 [後端] `routes/api.php` 的 provider 群組改為 `Route::middleware(['auth:sanctum', 'provider'])->prefix('provider')`；驗證：`php artisan route:list --path=api/provider` 顯示 `provider` middleware，且 `register`、`login` 不受影響
- [x] 2.3 [整合測試] `test_provider_can_access_provider_routes`：provider（含 `providerProfile`）呼叫 `GET /api/provider/profile`、`/offers`、`/schedules`、`/bookings` 回 200（對應 Scenario「provider 可存取 provider 路由」）
- [x] 2.4 [整合測試] `test_unapproved_provider_can_access_verification`：`verification_status` 為 `unsubmitted`、`pending`、`rejected` 的 provider 呼叫 `GET /api/provider/verification` 回 200（對應 Scenario「未通過審核的 provider 仍可進入 provider 路由」）
- [x] 2.5 [整合測試] `test_member_is_rejected_on_provider_routes`：member 呼叫 `GET /api/provider/profile`、`GET /api/provider/offers`、`POST /api/provider/offers`、`POST /api/provider/schedules`、`GET /api/provider/bookings`、`GET /api/provider/verification`，皆回 403 與 `{ status: false, message: "無權限存取" }`；`POST /api/provider/schedules` 另斷言 `course_schedules` 筆數不變（對應 Scenario「member 呼叫 provider 路由被拒絕」）
- [x] 2.6 [整合測試] `test_admin_is_rejected_on_provider_routes`：admin 呼叫 `GET /api/provider/profile`、`GET /api/provider/offers`、`POST /api/provider/offers`、`GET /api/provider/bookings`，皆回 403 與相同 body（對應 Scenario「admin 呼叫 provider 路由被拒絕」）
- [x] 2.7 [整合測試] `test_non_provider_offer_attempt_creates_no_offer`：member 帶通過驗證的完整課程資料呼叫 `POST /api/provider/offers`，回 403，`diving_offers` 筆數不變（對應 Scenario「非 provider 建立課程不會產生資料」）
- [x] 2.8 [整合測試] `test_unauthenticated_request_is_rejected_on_provider_routes`：不帶 token 呼叫 `GET /api/provider/profile`、`GET /api/provider/offers` 回 401（對應 Scenario「未登入呼叫 provider 路由」）

## 3. 共用路由與既有規格的回歸

- [x] 3.1 [整合測試] `test_member_and_provider_can_read_notifications`：member 與 provider 各自呼叫 `GET /api/notifications` 回 200（對應 Scenario「member 與 provider 皆可讀取通知」）
- [x] 3.2 [整合測試] `test_member_and_provider_can_read_unread_message_counts`：member 與 provider 各自呼叫 `GET /api/bookings/messages/unread-counts` 回 200（對應 Scenario「member 與 provider 皆可讀取未讀訊息數」）
- [x] 3.3 [整合測試] 執行 `php artisan test --filter=BookingListTest`，確認 `feature-test-coverage`「預約列表端點測試覆蓋」的四個 Scenario（member 隔離、provider 隔離、admin 全部、未認證 401）在新 middleware 下仍全綠（「非 member／非 provider 於 middleware 層 403」由 1.4、1.5、2.5、2.6 覆蓋）；同時更新 `tests/Feature/BookingListTest.php` 第 19–24 行 docblock 中「無 role check」「不靠 middleware role 強制」的過時描述

## 4. 全套回歸、Swagger 與格式

- [x] 4.1 [整合測試] 執行完整 `php artisan test`（需在容器內，因 `CourseImageTest` 需要 GD）；若有既有測試因角色錯誤改回 403，判斷是測試用錯角色（修正測試並在 commit 說明）還是依賴跨角色行為（停下回報）
- [x] 4.2 [後端] 核對 `app/Docs/MemberApiDoc.php`、`app/Docs/ProviderApiDoc.php` 每個端點的 403 回應描述涵蓋「角色不符」；缺的補上，並執行 `php artisan l5-swagger:generate` 確認無錯誤
- [x] 4.3 [後端] 執行 `./vendor/bin/pint --test app/Http/Middleware/EnsureMember.php app/Http/Middleware/EnsureProvider.php bootstrap/app.php routes/api.php tests/Feature/RoleAuthorizationTest.php`（以及 4.1／4.2 若有修改的檔案），確認無格式問題

## 5. 手動驗證

- [ ] 5.1 [整合測試]（手動，非自動化測試）本機 Docker 環境以 member token 呼叫 `POST /api/provider/offers`，以 provider token 呼叫 `POST /api/member/bookings`，確認兩者皆回 403
- [ ] 5.2 [整合測試]（手動，非自動化測試）本機前端：會員端登入後瀏覽「我的預約」、教練端登入後瀏覽課程／時段／預約／審核頁，確認功能正常、沒有出現 403

## Workflow follow-up

- 實作完成後依 CLAUDE.md 執行 `hardener` skill，再呼叫 `strict-reviewer`
- 執行 `verify-and-report` skill 回報結果
- 實作與歸檔各自獨立 PR；歸檔時同步 `openspec/config.yaml` 後端慣例段落（移除「member／provider 路由群組目前僅有 `auth:sanctum`」的描述）、CLAUDE.md「授權規範」的已知缺口描述，以及 `docs/analysis/2026-10-14-project-review-todo.md` H1 的完成狀態
