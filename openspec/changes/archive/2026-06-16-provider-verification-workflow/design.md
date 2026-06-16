# Design — provider-verification-workflow

## D1：四狀態而非三狀態

路線圖原列 pending/approved/rejected 三態，但「註冊後補件送審」決策（Hank 2026-06-11 拍板）使「註冊完還沒送審」與「已送審待審」必須區分——Admin 佇列只該出現後者。故：

```
unsubmitted ──submit──> pending ──approve──> approved
     ↑                    │                     │
     │                  reject               reject（撤銷，原因必填）
     │                    ↓                     ↓
     └──（重新上傳）── rejected ──submit──> pending
```

- 合法轉移：`unsubmitted→pending`、`pending→approved|rejected`、`rejected→pending`、`approved→rejected`（撤銷）
- `rejected→pending` 重送時清空 `rejection_reason`
- 欄位用 string + 應用層常數（與 BookingStatus enum 同模式，建 `App\Enums\VerificationStatus`）

## D2：取代而非並存 boolean

`is_verified` 欄位**移除**（migration: true→approved、false→unsubmitted），不留同步欄位——兩個真實來源必然漂移（Rule 7）。API 相容由 `ProviderProfile::$appends` 的 `is_verified` accessor 提供（`= status === approved`），前端/Swagger 既有讀取點不破壞；查詢端（`visibleToPublic`、預約入口）改查 `verification_status`。

## D3：證照獨立資料表

`provider_certifications`（id, user_id, image_path, created_at）。不塞 JSON 欄位：要逐張刪除、要 FK 清理、上限 3 張需 count 查詢。圖片複用 `CompressesImages::compressToJpeg`（O3.1 的 trait，第二個使用者，驗證抽共用的價值），存 `providers/{user_id}/certifications/`。刪除政策：`unsubmitted`/`rejected` 可增刪；`pending`/`approved` 鎖定（審核依據不可變動）。

## D4：端點設計

**Provider（auth:sanctum + provider prefix）**
- `GET /api/provider/verification`——status、rejection_reason、certifications[]
- `POST /api/provider/verification/certifications`——上傳（≤3 張、≤10MB、壓縮）；僅 unsubmitted/rejected
- `DELETE /api/provider/verification/certifications/{id}`——僅 unsubmitted/rejected、僅本人
- `POST /api/provider/verification/submit`——至少 1 張證照；unsubmitted/rejected → pending

**Admin（auth:sanctum + admin）**
- `GET /api/admin/verifications?status=pending`——佇列（預設 pending，可查全部狀態），含教練資料與證照 URL
- `PUT /api/admin/verifications/{userId}/approve`——pending → approved
- `PUT /api/admin/verifications/{userId}/reject`——pending/approved → rejected，`reason` 必填（max 500）

approve/reject 皆 flush `diving_offers` 快取 tag（可見性立即生效，沿用 toggle 的既有做法）。`toggle-verified` 端點與 `AdminUserController::toggleProviderVerified` 移除——保留會提供繞過狀態機的後門。

控制器：新建 `ProviderVerificationController` 與 `AdminVerificationController`（單一職責），不塞進 AuthController/AdminUserController。

## D5：通知

`ProviderVerificationApprovedNotification`、`ProviderVerificationRejectedNotification`（含原因），照 Booking* 模式（database + mail channel，try/catch 包裹不阻斷主流程）。送審時**不**通知 Admin（Admin 有佇列頁；避免通知氾濫），列為未來選項。

## D6：前端

- **教練端** `views/coach/VerificationView.vue`（route `/coach/verification`）：狀態卡（四態 + 駁回原因）、證照上傳/刪除（沿用 OfferFormView 的圖片上傳模式）、送審按鈕；CoachLayout 側欄加入口與狀態 badge
- **Admin 端** `views/admin/ProvidersView.vue` 擴充：狀態 badge 四態化、「待審核」filter、展開查看證照圖、通過/駁回操作（駁回原因用 inline 輸入，沿用該頁既有操作風格）；不另開新頁（教練列表即審核入口，避免兩頁資料重複）

## D7：Demo 資料與測試遷移

- DemoSeeder：3 位已驗證 → `approved`；1 位未驗證 → 給 1 張證照 + `pending`（展示審核佇列）
- 既有測試 helper（DivingOfferVisibility / BookingLifecycle / BookingOversell / ReviewTest）：`is_verified => true/false` 改 `verification_status => approved/unsubmitted`
- 新測試：送審狀態機（含非法轉移）、證照上限與鎖定、Admin 審核權限邊界、通知發送、approve/reject 後可見性立即生效
