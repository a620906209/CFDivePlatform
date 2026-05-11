## ADDED Requirements

### Requirement: Provider 上傳課程封面
Provider SHALL 能為自己的課程上傳一張封面圖片，新上傳會覆蓋舊封面並刪除舊實體檔案。

#### Scenario: 成功上傳封面
- **WHEN** Provider 送出 `POST /api/provider/offers/{id}/cover`，包含 `image` 檔案（jpeg/png/webp，≤2MB）
- **THEN** 系統儲存檔案至 `public` disk 的 `offers/{offer_id}/cover/` 目錄，更新 `diving_offers.cover_image`，回傳 `cover_image_url`

#### Scenario: 覆蓋封面時刪除舊檔
- **WHEN** Provider 上傳新封面，且原本已有封面
- **THEN** 系統先刪除舊實體檔案，再儲存新檔案

#### Scenario: 檔案格式驗證
- **WHEN** 上傳的檔案不是 jpeg/jpg/png/webp
- **THEN** 系統回傳 422，message：「只支援 jpeg、png、webp 格式」

#### Scenario: 檔案大小驗證
- **WHEN** 上傳檔案超過 2MB（2048KB）
- **THEN** 系統回傳 422，message：「圖片大小不可超過 2MB」

#### Scenario: 不可上傳他人課程的封面
- **WHEN** Provider 嘗試上傳不屬於自己課程的封面
- **THEN** 系統回傳 403

### Requirement: Provider 刪除課程封面
Provider SHALL 能刪除自己課程的封面，同步移除實體檔案。

#### Scenario: 成功刪除封面
- **WHEN** Provider 送出 `DELETE /api/provider/offers/{id}/cover`
- **THEN** 系統刪除實體檔案，將 `diving_offers.cover_image` 設為 null，回傳 200

#### Scenario: 無封面時刪除不報錯
- **WHEN** Provider 刪除封面，但 `cover_image` 本來就是 null
- **THEN** 系統直接回傳 200，不報錯

### Requirement: Provider 上傳課程相簿圖片
Provider SHALL 能為課程上傳最多 3 張相簿圖片。

#### Scenario: 成功上傳相簿圖片
- **WHEN** Provider 送出 `POST /api/provider/offers/{id}/images`，包含 `image` 檔案（格式與大小同封面限制），且目前相簿圖片數 < 3
- **THEN** 系統儲存檔案至 `offers/{offer_id}/gallery/` 目錄，建立 `course_images` 紀錄，`sort_order` 為目前最大值 + 1，回傳新圖片資訊

#### Scenario: 超過 3 張上限
- **WHEN** 課程已有 3 張相簿圖片，Provider 再次上傳
- **THEN** 系統回傳 422，message：「相簿最多 3 張圖片」

#### Scenario: 不可上傳他人課程的相簿
- **WHEN** Provider 嘗試上傳不屬於自己課程的相簿
- **THEN** 系統回傳 403

### Requirement: Provider 刪除相簿圖片
Provider SHALL 能刪除自己課程的特定相簿圖片，同步移除實體檔案。

#### Scenario: 成功刪除相簿圖片
- **WHEN** Provider 送出 `DELETE /api/provider/images/{image_id}`
- **THEN** 系統刪除實體檔案，刪除 `course_images` 紀錄，回傳 200

#### Scenario: 不可刪除他人相簿圖片
- **WHEN** Provider 嘗試刪除不屬於自己課程的相簿圖片
- **THEN** 系統回傳 403

### Requirement: 圖片 URL 隨課程資料一同回傳
公開課程 API SHALL 在回傳課程資料時包含 `cover_image_url`（完整可存取 URL）與 `images`（相簿陣列）。

#### Scenario: 有封面時回傳完整 URL
- **WHEN** 任何人取得課程資料（`GET /api/diving-offers/{id}` 或列表）
- **THEN** `cover_image_url` 回傳可直接用於 `<img src>` 的完整 URL；無封面時回傳 null

#### Scenario: 相簿陣列回傳
- **WHEN** 取得課程詳情 `GET /api/diving-offers/{id}`
- **THEN** `images` 回傳相簿圖片陣列，每筆含 `id`、`url`、`sort_order`；無相簿時回傳空陣列

### Requirement: 圖片儲存持久化
圖片 SHALL 儲存於 Docker named volume，跨容器重建後仍可存取。

#### Scenario: 容器重建後圖片保留
- **WHEN** 執行 `docker compose build` 並重新啟動容器
- **THEN** 先前上傳的圖片仍可正常存取（URL 不變）
