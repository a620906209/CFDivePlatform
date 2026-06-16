## 1. 資料模型

- [x] 1.1 新增 `App\Enums\VerificationStatus`（Unsubmitted / Pending / Approved / Rejected，含 `VALID_TRANSITIONS` 與 `canTransitionTo()`，仿 BookingStatus 模式）
- [x] 1.2 Migration：`provider_profiles` 加 `verification_status`（string, default 'unsubmitted'）與 `rejection_reason`（text nullable）；資料轉換 `is_verified=true→approved`、`false→unsubmitted`；drop `is_verified`
- [x] 1.3 Migration：新表 `provider_certifications`（id, user_id FK cascade, image_path, timestamps）
- [x] 1.4 `ProviderProfile`：fillable/casts 更新、`is_verified` accessor（appends，= approved）、`certifications` 由 User 關聯
- [x] 1.5 新 Model `ProviderCertification`（belongsTo User、url accessor，仿 CourseImage）

## 2. 教練端 API

- [x] 2.1 `ProviderVerificationController`：`show`（status + reason + certifications）、`uploadCertification`（≤3 張、複用 CompressesImages、僅 unsubmitted/rejected）、`deleteCertification`（僅本人 + 僅 unsubmitted/rejected）、`submit`（≥1 張證照、unsubmitted/rejected→pending、清 rejection_reason）
- [x] 2.2 routes：`/api/provider/verification*` 四端點（auth:sanctum + provider group）

## 3. Admin 端 API

- [x] 3.1 `AdminVerificationController`：`index`（?status= 預設 pending，含教練資料與證照）、`approve`（pending→approved）、`reject`（pending|approved→rejected，reason 必填 max 500）；approve/reject flush diving_offers 快取 + 通知教練
- [x] 3.2 routes：`/api/admin/verifications*` 三端點；移除 toggle-verified route 與 `AdminUserController::toggleProviderVerified`
- [x] 3.3 `AdminUserController::providers` 列表回應含 `verification_status`

## 4. 可見性判定切換

- [x] 4.1 `DivingOffer::visibleToPublic`：`is_verified=true` → `verification_status='approved'`
- [x] 4.2 `MemberBookingController::store` 可預約檢查同步改判 approved

## 5. 通知

- [x] 5.1 `ProviderVerificationApprovedNotification` + `ProviderVerificationRejectedNotification`（含原因；database + mail，仿 Booking* 模式）

## 6. Seeder

- [x] 6.1 DemoSeeder / DevelopmentSeeder：`is_verified` 改 `verification_status`；demo 第 4 位教練給 1 張證照 + pending（展示佇列）

## 7. 前端

- [x] 7.1 `views/coach/VerificationView.vue` + route `/coach/verification`：狀態卡（四態 + 駁回原因）、證照上傳/刪除、送審；CoachLayout 側欄入口
- [x] 7.2 `views/admin/ProvidersView.vue`：badge 四態化、待審 filter、查看證照、通過/駁回（原因輸入）；移除 toggle 呼叫

## 8. 測試

- [x] 8.1 既有測試遷移：DivingOfferVisibilityTest / BookingLifecycleTest / BookingOversellTest / ReviewTest 的 helper 改 `verification_status`
- [x] 8.2 `ProviderVerificationTest`（新）：狀態機合法/非法轉移、證照上限/鎖定/本人限定、送審前置條件、重送清空原因
- [x] 8.3 `AdminVerificationTest`（新）：佇列過濾、approve/reject 權限（非 admin 403）、reject 無原因 422、撤銷 approved、裁決後可見性立即生效（快取）、通知發送斷言、toggle 端點 404
- [x] 8.4 容器內全套件綠（基準 155 passed）

## 9. 規格同步

- [x] 9.1 specs 增量套用至主規格 `provider-verification` 與 `admin-user-management`
