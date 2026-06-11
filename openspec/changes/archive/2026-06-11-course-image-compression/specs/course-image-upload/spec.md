## ADDED Requirements

### Requirement: 伺服器端圖片壓縮
系統 SHALL 在儲存課程封面與相簿圖片前進行壓縮：長邊超過 2048px 時等比縮小至 2048px 內（僅縮不放大），一律轉存 JPEG（quality 85，副檔名 `.jpg`，uuid 檔名）。與聊天圖片管線（`scaleDown(2048) + toJpeg(85)`）參數一致。

#### Scenario: 大圖縮小
- **WHEN** Provider 上傳長邊 > 2048px 的圖片
- **THEN** 儲存的檔案長邊 ≤ 2048px，格式為 JPEG

#### Scenario: 小圖不放大
- **WHEN** Provider 上傳長邊 ≤ 2048px 的圖片
- **THEN** 儲存的檔案維持原尺寸，格式轉為 JPEG

#### Scenario: PNG/WebP 轉存 JPEG
- **WHEN** Provider 上傳 png 或 webp 格式圖片
- **THEN** 儲存的檔案為 `.jpg`，回傳的 URL 指向轉存後檔案

## MODIFIED Requirements

### Requirement: Provider 上傳課程封面
Provider SHALL 能為自己的課程上傳一張封面圖片，新上傳會覆蓋舊封面並刪除舊實體檔案。上傳大小上限為 10MB（原 2MB；伺服器端會壓縮，放寬以容納手機原圖）。

#### Scenario: 成功上傳封面
- **WHEN** Provider 送出 `POST /api/provider/offers/{id}/cover`，包含 `image` 檔案（jpeg/png/webp，≤10MB）
- **THEN** 系統壓縮後儲存至 `public` disk 的 `offers/{offer_id}/cover/` 目錄（`.jpg`），更新 `diving_offers.cover_image`，回傳 `cover_image_url`

#### Scenario: 檔案大小驗證
- **WHEN** 上傳檔案超過 10MB（10240KB）
- **THEN** 系統回傳 422

#### Scenario: 10MB 以內的手機原圖可直接上傳
- **WHEN** 上傳 2MB~10MB 之間的圖片（原規格會拒絕）
- **THEN** 系統接受並壓縮儲存

### Requirement: Provider 上傳課程相簿圖片
Provider SHALL 能為課程上傳最多 3 張相簿圖片（格式與大小限制同封面：jpeg/png/webp、≤10MB，壓縮後存為 `.jpg`），sort_order 不連續為預期行為（刪除後重新上傳序號接續最大值）。

#### Scenario: 成功上傳相簿圖片
- **WHEN** Provider 送出 `POST /api/provider/offers/{id}/images`，包含 `image` 檔案，且目前相簿圖片數 < 3
- **THEN** 系統壓縮後儲存至 `offers/{offer_id}/gallery/` 目錄（`.jpg`），建立 `course_images` 紀錄，`sort_order = MAX(sort_order) + 1`，回傳新圖片資訊
