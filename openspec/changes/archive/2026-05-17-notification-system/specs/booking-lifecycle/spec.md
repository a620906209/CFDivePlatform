## MODIFIED Requirements

### Requirement: 預約狀態轉換觸發通知

預約七狀態機（`pending` / `confirmed` / `completed` / `rejected` / `expired` / `member_cancelled` / `provider_cancelled`）的每個轉換點，系統 SHALL 在狀態成功更新後觸發對應通知（詳見 `notification-triggers` spec）。通知觸發 MUST 在主業務 transaction commit 之後執行，且以 try/catch 包裹，不影響主業務結果。

#### Scenario: 狀態轉換後通知觸發

- **WHEN** `BookingService` 中任一狀態轉換方法成功執行
- **THEN** 對應的 Notification class 被觸發，不論通知是否成功主業務均正常回傳

#### Scenario: 通知失敗不影響主業務

- **WHEN** notify 呼叫拋出例外
- **THEN** 預約狀態已正確儲存，HTTP response 成功回傳，錯誤記錄至 Laravel log
