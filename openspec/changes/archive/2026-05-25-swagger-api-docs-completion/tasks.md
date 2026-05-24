## 1. 修正 AuthApiDoc.php 既有錯誤

- [x] 1.1 [後端] `AuthApiDoc.php` 修正所有路徑錯位：`/register/member` → `/member/register`、`/login/member` → `/member/login`、`/logout/member` → `/member/logout`、`/profile/member` → `/member/profile`（provider、admin 同樣修正）
- [x] 1.2 [後端] `AuthApiDoc.php` 修正 change-password 路徑：`/password/member` → `/member/change-password`、`/password/provider` → `/provider/change-password`、`/password/admin` → `/admin/change-password`
- [x] 1.3 [後端] `AuthApiDoc.php` 補上 `POST /logout`（通用登出）與 `GET /user`（取得當前使用者）

## 2. AuthSupplementDoc（Google OAuth 專用）

- [x] 2.1 [後端] 建立 `app/Docs/AuthSupplementDoc.php`，補上 `GET /auth/google/redirect`（response: redirect_url）與 `GET /auth/google/callback`（response: token + user）兩個端點

## 3. 共用 Schema 與 PublicApiDoc

- [x] 3.1 [後端] 建立 `app/Docs/PublicApiDoc.php`，定義共用 Schema：`DivingOffer`、`Review`、`CourseSchedule`、`Booking`、`PaginationMeta`、`ApiErrorResponse`
- [x] 3.2 [後端] `PublicApiDoc.php` 補上 `GET /diving-offers`（query: q、region、tag、per_page、page；response: DivingOffer 分頁）
- [x] 3.3 [後端] `PublicApiDoc.php` 補上 `GET /diving-offers/{id}`（response: DivingOffer 含 cover_image_url、images 陣列；404）
- [x] 3.4 [後端] `PublicApiDoc.php` 補上 `GET /diving-offers/{id}/reviews`（query: sort、page、per_page；response: summary + reviews 分頁 + meta）
- [x] 3.5 [後端] `PublicApiDoc.php` 補上 `GET /diving-offers/{id}/schedules`（response: CourseSchedule 陣列）

## 4. MemberApiDoc

- [x] 4.1 [後端] 建立 `app/Docs/MemberApiDoc.php`，補上 Member bookings：`POST /member/bookings`（201）、`GET /member/bookings`（分頁）、`GET /member/bookings/{id}`、`DELETE /member/bookings/{id}`
- [x] 4.2 [後端] `MemberApiDoc.php` 補上 Member reviews：`POST /member/reviews`（403/422）、`PUT /member/reviews/{id}`（403）、`DELETE /member/reviews/{id}`（403）
- [x] 4.3 [後端] `MemberApiDoc.php` 補上 `POST /reviews/{id}/helpful`（response: helpful_count、has_voted）
- [x] 4.4 [後端] `MemberApiDoc.php` 補上 notifications：`GET /notifications`（分頁）、`GET /notifications/unread-count`、`PATCH /notifications/{id}/read`、`PATCH /notifications/read-all`、`DELETE /notifications/{id}`

## 5. ProviderApiDoc

- [x] 5.1 [後端] 建立 `app/Docs/ProviderApiDoc.php`，補上 Provider offers CRUD：`GET /provider/offers`、`POST /provider/offers`、`GET /provider/offers/{id}`（403）、`PUT /provider/offers/{id}`、`DELETE /provider/offers/{id}`
- [x] 5.2 [後端] `ProviderApiDoc.php` 補上圖片管理：`POST /provider/offers/{id}/cover`（multipart）、`DELETE /provider/offers/{id}/cover`、`POST /provider/offers/{id}/images`、`DELETE /provider/images/{id}`
- [x] 5.3 [後端] `ProviderApiDoc.php` 補上 schedules：`GET /provider/schedules`（query: offer_id?）、`POST /provider/schedules`、`PUT /provider/schedules/{id}`、`DELETE /provider/schedules/{id}`
- [x] 5.4 [後端] `ProviderApiDoc.php` 補上 bookings 管理：`GET /provider/bookings`、`PUT /provider/bookings/{id}/confirm`、`PUT /provider/bookings/{id}/reject`、`PUT /provider/bookings/{id}/complete`、`PUT /provider/bookings/{id}/cancel`

## 6. AdminApiDoc

- [x] 6.1 [後端] 建立 `app/Docs/AdminApiDoc.php`，補上 `GET /admin/stats`（response: total_members/providers/offers；403）
- [x] 6.2 [後端] `AdminApiDoc.php` 補上會員管理：`GET /admin/members`（分頁）、`GET /admin/members/{id}`、`PUT /admin/members/{id}/toggle-active`、`GET /admin/check-member/{id}`
- [x] 6.3 [後端] `AdminApiDoc.php` 補上教練管理：`GET /admin/providers`（分頁）、`GET /admin/providers/{id}`、`PUT /admin/providers/{id}/toggle-active`、`PUT /admin/providers/{id}/toggle-verified`、`GET /admin/check-provider/{id}`
- [x] 6.4 [後端] `AdminApiDoc.php` 補上課程/預約/評價管理：`GET /admin/offers`、`DELETE /admin/offers/{id}`、`GET /admin/bookings`、`PUT /admin/bookings/{id}/complete`、`GET /admin/reviews`（per_page 最大 100）、`DELETE /admin/reviews/{id}`

## 7. 驗證

- [x] 7.1 [整合測試] 執行 `docker exec cfdive-app php artisan l5-swagger:generate`，確認無 parse error
- [x] 7.2 [整合測試] 開啟 `http://localhost:8080/api/documentation`，展開各 tag 確認路徑正確（`/member/register` 而非 `/register/member`）
- [x] 7.3 [整合測試] 確認端點總數 73（原始估計 76 因 AuthApiDoc 實際有 18 個端點非 15 個），`GET /diving-offers/{id}/reviews` 顯示分頁 meta schema
