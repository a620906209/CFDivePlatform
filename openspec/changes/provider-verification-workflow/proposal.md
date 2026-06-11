## Why

目前教練驗證只有一顆 Admin 的 `is_verified` 開關（2026-06-11 修補後具備可見性約束力），但**沒有審核「流程」**：教練無從提交資質證明、Admin 沒有審核依據與佇列、駁回無原因可言、結果無通知。路線圖（`docs/analysis/2026-06-11-future-roadmap-feasibility.md` §2.1）將此列為金流前的信任基礎建設。

## What Changes

- **資料模型**：`provider_profiles.is_verified`（boolean）升級為 `verification_status` 狀態機——`unsubmitted`（註冊預設）→ `pending`（已送審）→ `approved` / `rejected`（含 `rejection_reason`）；新增 `provider_certifications` 資料表存證照圖片
- **教練端**：後台新增「驗證申請」頁——上傳證照（1~3 張，複用 `CompressesImages` 壓縮管線）、送審、查看駁回原因、重新送審（產品決策：**註冊後補件送審**，不擋註冊流程）
- **Admin 端**：審核佇列（pending 優先）、查看證照、通過/駁回（駁回原因必填）；移除舊 `toggle-verified` 開關端點（與狀態機衝突）
- **通知**：審核通過/駁回通知教練（站內 + Email，複用 notification 管線）
- **相容性**：`ProviderProfile` 以 accessor 保留 API 輸出的 `is_verified` 欄位（= status 是否為 approved）；`visibleToPublic` scope 與預約入口檢查改查 `verification_status = 'approved'`，語意不變

## Capabilities

### Modified Capabilities

- `provider-verification`：boolean 語意升級為四狀態機；新增教練送審端點、Admin 審核端點、通知 requirements；可見性規則改以 `approved` 判定（行為等價）
- `admin-user-management`：`toggle-verified` 端點移除，由 approve / reject 取代

## Impact

- **資料庫**：migration 轉換既有資料（`is_verified=true → approved`、`false → unsubmitted`）、新表 `provider_certifications`
- **後端**：新增 `ProviderVerificationController`、`AdminVerificationController`、2 個 Notification class；修改 `DivingOffer::visibleToPublic`、`MemberBookingController`、`AdminUserController`（移除 toggle）、`ProviderProfile`、兩個 Seeder
- **前端**：新增 `coach/VerificationView.vue`；改 `admin/ProvidersView.vue`（狀態 badge、審核操作）
- **測試**：既有用到 `is_verified` 的 4 個測試檔改用 `verification_status`；新增送審流程與審核權限測試
- **行為變更**：Admin 不再能單鍵切換驗證，必須走審核流（撤銷 = 駁回既有 approved 教練並附原因）；既有預約不受狀態變動影響（沿用 provider-verification 規格既定政策）
