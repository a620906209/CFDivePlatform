# Design — provider-verification-gaps

## D1：預約入口擋在哪一層

候選：
- (a) `MemberBookingController::store` 的 Layer 1 快速失敗區（採用）——載入 `$schedule->divingOffer.provider.providerProfile` 後檢查，與既有「時段不開放」「名額不足」的 422 錯誤同層、同風格
- (b) `CourseSchedule` 加 scope——過度設計，時段本身沒有可見性概念，可見性屬於課程

回應訊息建議與既有風格一致：`['status' => false, 'message' => '此課程目前不開放預約']`，HTTP 422。不用 404：schedule id 是真實存在的資源，422 表達「請求合法但業務上不可行」。

## D2：既有預約不受影響（關鍵政策決定）

教練被取消驗證（toggle off）時：
- **新預約**：擋（本 change 範圍）
- **既有 pending**：教練仍可 confirm／reject——合約協商已開始，平台不單方面撕毀；若要更嚴格（pending 也凍結），屬產品決策，本 change 不做
- **既有 confirmed / completed**：聊天、完課、評價全部照常——會員已付出時間成本，懲罰應落在教練（不能接新單）而非會員

此政策需寫入規格 scenario，避免未來誤解為漏洞。

## D3：visibleToPublic 的查詢成本

`schedules` / `reviews` publicList 由 `findOrFail($offerId)` 改為 `visibleToPublic()->findOrFail($offerId)`，多一個 `whereExists` 子查詢（provider→providerProfile），單筆 by-id 查詢成本可忽略；兩端點目前無快取，無一致性問題。`store` 的檢查在 transaction 之外的 Layer 1 即可（教練驗證狀態變動頻率極低，不需要鎖）。

## D4：測試歸屬

擴充既有 `DivingOfferVisibilityTest`（同一保護目標：可見性），不另開檔案；預約入口的測試放 `BookingLifecycleTest`（屬建立預約的前置條件），各加 3~4 案例。
