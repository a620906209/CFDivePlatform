## MODIFIED Requirements

### Requirement: 預約狀態機
系統 SHALL 維護七個合法狀態，且只允許以下轉換：
- `pending` → `confirmed`（Provider 確認）
- `pending` → `rejected`（Provider 拒絕）
- `pending` → `member_cancelled`（Member 取消）
- `pending` → `expired`（Scheduler 超時）
- `confirmed` → `completed`（Scheduler 課程後自動）
- `confirmed` → `member_cancelled`（Member 取消）
- `confirmed` → `provider_cancelled`（Provider 取消）

各狀態對應訊息頻道語義：
- `confirmed`：`presence-booking.{id}` 頻道開放，可讀寫訊息
- `completed`：頻道關閉，訊息歷史封存唯讀
- 其餘狀態（`pending`、`rejected`、`expired`、`member_cancelled`、`provider_cancelled`）：無訊息頻道

#### Scenario: 非法狀態轉換被拒絕
- **WHEN** 任何角色嘗試執行上述以外的狀態轉換
- **THEN** 系統回傳 422，說明當前狀態不允許此操作

#### Scenario: confirmed 轉 completed 時封存訊息頻道
- **WHEN** Scheduler 將 `confirmed` 預約轉為 `completed`
- **THEN** 對應 `presence-booking.{id}` 頻道不再授權新連線；現有訊息歷史保留，後續 POST 訊息回傳 403
