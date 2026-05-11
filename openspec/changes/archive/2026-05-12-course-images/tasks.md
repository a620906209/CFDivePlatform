## 1. 資料庫層

- [x] 1.1 [後端] 建立 Migration `add_cover_image_to_diving_offers_table`：新增 `cover_image varchar(500) nullable`
- [x] 1.2 [後端] 建立 Migration `create_course_images_table`：欄位含 `diving_offer_id`（FK cascade）、`image_path`（varchar 500）、`sort_order`（unsignedSmallInt DEFAULT 0）、`$table->timestamp('created_at')->useCurrent()`（DB 自動填入，無 updated_at）；加索引 `(diving_offer_id, sort_order)`
- [x] 1.3 [後端] 執行 Migration，確認欄位與索引正確

## 2. Model 層

- [x] 2.1 [後端] 建立 `app/Models/CourseImage.php`：fillable（diving_offer_id、image_path、sort_order）、`public $timestamps = false`、`const CREATED_AT = 'created_at'`（讓 Eloquent 知道此欄存在）、belongsTo DivingOffer、`url` accessor（`Storage::disk('public')->url($this->image_path)`）
- [x] 2.2 [後端] 更新 `app/Models/DivingOffer.php`：新增 `hasMany CourseImage` 關聯、`cover_image_url` accessor（`Storage::disk('public')->url($this->cover_image)` 或 null）、新增 `static::deleting()` observer：`Storage::disk('public')->deleteDirectory("offers/{$offer->id}")`（刪除整個課程目錄，防孤兒檔）
- [x] 2.3 [後端] 更新 `DivingOfferController` 的 `show` 與 `index` 回傳：加入 `cover_image_url` 與 `images`（含 id、url、sort_order，依 sort_order ASC 排序）

## 3. 圖片上傳 API

- [x] 3.1 [後端] 建立 `app/Http/Controllers/API/CourseImageController.php`：
  - 所有上傳方法使用統一 validate：`'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048'`
  - `uploadCover`：所有權驗證 → validate → 刪除舊封面實體檔（若有）→ `store("offers/{id}/cover", 'public')` → 更新 `cover_image` → 回傳 `cover_image_url`
  - `deleteCover`：所有權驗證 → 刪除實體檔 → `cover_image = null` → 回傳 200（無封面時不報錯）
  - `uploadImage`：所有權驗證 → validate → 相簿數量檢查（COUNT < 3，否則 422）→ store 至 `offers/{id}/gallery` → 建立 CourseImage（`sort_order = (MAX(sort_order) ?? 0) + 1`，不連續為預期行為）→ 回傳圖片資訊
  - `deleteImage`：CourseImage 所有權驗證（`$image->divingOffer->provider_id !== auth()->id()` → 403）→ 刪除實體檔 → 刪除 DB 紀錄 → 回傳 200
- [x] 3.2 [後端] 在 `routes/api.php` Provider 群組新增四個路由（POST/DELETE cover、POST images、DELETE images/{id}）

## 4. Docker 設定

- [x] 4.1 [基礎設施] 在 `docker-compose.yml` 的 `app` service 新增 volume `storage-data:/var/www/storage/app/public`，並在底部 `volumes:` 區塊宣告 `storage-data:`
- [x] 4.2 [基礎設施] 在 `docker/php/docker-entrypoint.sh` 的初始化段落加入 `php artisan storage:link --force || true`
- [x] 4.3 [基礎設施] 重新 build 並啟動容器，確認 `/var/www/public/storage` symlink 存在且可存取

## 5. 前端 API 封裝

- [x] 5.1 [前端] 建立 `frontend/src/api/courseImageApi.js`：`uploadCover(offerId, file)`、`deleteCover(offerId)`、`uploadImage(offerId, file)`、`deleteImage(imageId)`（皆使用 coachAxios，Content-Type: multipart/form-data）

## 6. 前端：課程卡封面顯示

- [x] 6.1 [前端] 更新 `frontend/src/components/CourseCard.vue`：有 `cover_image_url` 時顯示 `<img>`，無時顯示漸層佔位（保留 🤿 emoji 或 ocean 漸層背景）

## 7. 前端：課程詳情頁圖片展示

- [x] 7.1 [前端] 更新 `frontend/src/views/CourseDetailView.vue`：頂部大圖改為封面（有封面顯示圖片，無封面顯示漸層佔位）
- [x] 7.2 [前端] 相簿縮圖列：`images.length > 0` 時在封面下方顯示最多 3 張縮圖橫列，點擊放大（用 `<img>` 原始連結即可，不需 lightbox）

## 8. 前端：教練圖片管理 UI

- [x] 8.1 [前端] 更新 `frontend/src/views/coach/OfferFormView.vue`（或新建 `OfferImageManager.vue`）：編輯模式下在表單下方加入圖片管理區塊
  - 封面區：顯示目前封面縮圖 + 「更換封面」按鈕（file input）+ 「刪除封面」按鈕
  - 相簿區：顯示目前 0–3 張縮圖 + 「新增圖片」按鈕（達 3 張時隱藏）+ 每張縮圖右上角「✕」刪除

## 9. 整合驗證（手動）

- [x] 9.1 [整合測試] 上傳封面：上傳後確認 `cover_image_url` 在 API 回傳，且 URL 可直接 GET 存取（HTTP 200）
- [x] 9.2 [整合測試] 覆蓋封面：二次上傳後確認舊實體檔案已從 storage 刪除
- [x] 9.3 [整合測試] 相簿上限：上傳第 4 張應回傳 422
- [x] 9.4 [整合測試] Docker 持久化：`docker compose build app && docker compose up -d app` 後，先前上傳的圖片 URL 仍可存取
- [x] 9.5 [整合測試] 所有權驗證：Provider A 不可上傳到 Provider B 的課程（應回傳 403）

## 10. Feature Test（自動化）

- [x] 10.1 [測試] 建立 `tests/Feature/CourseImageTest.php`：使用 `Storage::fake('public')` + `UploadedFile::fake()->image()`，不寫真實檔案
- [x] 10.2 [測試] 測試 `uploadCover`：成功上傳（201）、格式錯誤（422）、超過 2MB（422）、他人課程（403）
- [x] 10.3 [測試] 測試 `deleteCover`：成功刪除（200）、無封面時不報錯（200）、他人課程（403）、確認 Storage::fake 內舊檔已刪
- [x] 10.4 [測試] 測試 `uploadImage`：成功上傳（201）、第 4 張回傳 422、他人課程（403）、確認 sort_order = MAX + 1
- [x] 10.5 [測試] 測試 `deleteImage`：成功刪除（200）、他人圖片（403）、確認實體檔已從 Storage::fake 刪除
- [x] 10.6 [測試] 測試課程刪除孤兒清理：刪除 DivingOffer 後確認 `offers/{id}/` 目錄從 Storage::fake 消失
