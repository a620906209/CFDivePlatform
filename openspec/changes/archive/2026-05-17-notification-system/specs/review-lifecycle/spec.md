## MODIFIED Requirements

### Requirement: 評價建立後觸發 Provider 通知

評價系統 SHALL 在 Member 成功建立評價後，通知課程所屬 Provider（僅站內通知，不寄 Email）。`ReviewService::create()` MUST 在評價資料儲存成功後觸發通知，以 try/catch 包裹確保主業務不受影響。

#### Scenario: 評價成功送出

- **WHEN** `ReviewService::create()` 建立新評價，`reviews` 資料表寫入成功
- **THEN** `$provider->notify(new ReviewReceivedNotification($review))` 被呼叫，Provider 站內通知新增一筆

#### Scenario: 通知失敗不影響評價建立

- **WHEN** notify 呼叫失敗（例：DB 寫入通知失敗）
- **THEN** 評價資料已正確儲存，HTTP response 成功回傳，錯誤記錄至 log
