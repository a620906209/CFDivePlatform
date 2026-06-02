## ADDED Requirements

### Requirement: Cache Key Namespace
後端 SHALL 使用以下格式的 Cache key，`{role}` 僅允許 `member` 或 `provider` 兩個值，對應各自的登入端點；`{email}` 為 `strtolower(trim($request->email))` 正規化後的結果。

| Key 用途 | 格式 |
|---------|------|
| 失敗計數 | `login_failures:{role}:{email}` |
| 鎖定到期時間 | `login_expires_at:{role}:{email}` |

#### Scenario: role 值與端點對應
- **WHEN** Member 登入失敗
- **THEN** 使用 key `login_failures:member:{email}` 與 `login_expires_at:member:{email}`

#### Scenario: Provider 與 Member 計數互相隔離
- **WHEN** 同一個 email 在 Member 端點失敗 4 次，再到 Provider 端點失敗 4 次
- **THEN** 兩個 role namespace 各自獨立，均未達閾值，均不觸發鎖定

### Requirement: 帳號層登入失敗鎖定
後端 SHALL 追蹤每個帳號（以正規化 email 為 key）的登入失敗次數。計數採用 **Fixed Window**：第一次失敗時建立 Cache key 並設 TTL = `decay_minutes * 60` 秒，後續失敗只遞增計數而**不重設 TTL**（window 不延長）。當失敗次數達到閾值（預設 5 次）時，系統 SHALL 鎖定該帳號，鎖定期間任何密碼登入 SHALL 被拒絕並回傳 HTTP 423（含 `locked_until` ISO 8601 欄位），直到 Cache key 自然過期。`locked_until` 的值來源為第一次失敗時同步寫入的 companion key `login_expires_at:{role}:{email}`。

**登入端點完整 Response Semantics**（按後端處理順序）：

| 情境 | 檢查順序 | HTTP | Response Body 重點欄位 |
|------|---------|------|----------------------|
| 帳號已鎖定（Cache 計數 ≥ max_attempts） | 1st | 423 | `message: "帳號已暫時鎖定..."`, `locked_until: "<ISO8601>"` |
| 帳號不存在（DB 查無 email） | 2nd | 401 | `message: "電子郵件或密碼錯誤"` |
| 帳號存在，密碼錯，失敗 1–4 次 | 3rd | 401 | `message: "電子郵件或密碼錯誤"` |
| 帳號存在，密碼錯，**第 5 次**（當場觸發鎖定） | 3rd | 423 | `message: "帳號已暫時鎖定..."`, `locked_until: "<ISO8601>"` |
| 帳號存在，密碼正確 | 3rd | 200 | `token`, `user` |

「帳號不存在」與「密碼錯誤 1–4 次」的 HTTP status 和 message SHALL 完全相同，不得有任何可區分的差異（防帳號枚舉）。

#### Scenario: 未達閾值的登入失敗
- **WHEN** 帳號在 15 分鐘內累計失敗次數少於 5 次
- **THEN** 系統正常回傳 HTTP 401，`failed_attempts` 計數遞增，帳號不鎖定

#### Scenario: 第 5 次失敗觸發鎖定
- **WHEN** 帳號在 15 分鐘內第 5 次登入失敗
- **THEN** 系統回傳 HTTP 423，body 包含 `{ status: false, message: "帳號已暫時鎖定，請於 15 分鐘後再試", locked_until: "<ISO8601 timestamp>" }`，後續任何密碼登入均被拒絕直到時間到期

#### Scenario: 鎖定期間嘗試登入
- **WHEN** 帳號處於鎖定狀態，使用者送出任何 email/password 組合
- **THEN** 系統不驗證密碼，直接回傳 HTTP 423 並附帶 `locked_until` 欄位

#### Scenario: 登入成功後清除失敗計數
- **WHEN** 使用者在未達閾值前成功登入
- **THEN** 系統清除該帳號的失敗計數，下次失敗從 0 重新累積

#### Scenario: 鎖定時間到期自動解鎖
- **WHEN** 帳號鎖定後超過 15 分鐘未有新登入嘗試
- **THEN** 鎖定計數自動過期（Cache TTL），帳號恢復可登入狀態

#### Scenario: 鎖定機制覆蓋 Member 與 Provider
- **WHEN** Member（`/api/member/login`）或 Provider（`/api/provider/login`）觸發連續失敗
- **THEN** 各自以 email 為 key 獨立計數，互不干擾

#### Scenario: Fixed window — 失敗不延長鎖定時間
- **WHEN** 帳號在 window 內第 3 次失敗（尚未達閾值），之後又再失敗 1 次
- **THEN** TTL **不重設**；`locked_until` 仍為第一次失敗時計算的到期時間

#### Scenario: `locked_until` 來自 companion key
- **WHEN** 系統回傳 HTTP 423
- **THEN** response body 的 `locked_until` 欄位值等於 `login_expires_at:{role}:{email}` Cache key 的儲存值（ISO 8601），即第一次失敗時計算的 `now + decay_minutes`

### Requirement: 不存在帳號不累計失敗計數
後端 SHALL 在遞增失敗計數**前**先查詢 DB 確認帳號是否存在。若查無此 email，SHALL 直接回傳 HTTP 401，訊息與密碼錯誤**相同**（`"電子郵件或密碼錯誤"`），**不**遞增任何 Cache 計數。

#### Scenario: 不存在帳號登入失敗
- **WHEN** 使用者以一個 DB 中不存在的 email 嘗試登入
- **THEN** 系統回傳 HTTP 401，body `{ status: false, message: "電子郵件或密碼錯誤" }`，Cache 中**不建立也不遞增**任何此 email 的失敗計數

#### Scenario: 錯誤訊息無法區分帳號不存在與密碼錯誤
- **WHEN** 帳號不存在 vs. 帳號存在但密碼錯誤，兩種情境均發生
- **THEN** 兩者 HTTP status 相同（401），response body 訊息相同，攻擊者無法透過回應差異做帳號枚舉

### Requirement: Email 正規化
後端 SHALL 在所有涉及鎖定計數的操作前，對 email 執行 `strtolower(trim($email))`，確保大小寫變體與前後空白不影響計數的正確性。

#### Scenario: Email 大小寫變體視為同一帳號
- **WHEN** 攻擊者用 `User@Gmail.com`、`user@gmail.com`、`USER@GMAIL.COM` 依序嘗試登入同一帳號
- **THEN** 三次嘗試累計到同一個計數 key，共累計 3 次失敗

### Requirement: 鎖定閾值可由環境變數設定
後端 SHALL 從 `config/auth_lockout.php` 讀取鎖定參數，允許透過 `.env` 覆蓋 `LOCKOUT_MAX_ATTEMPTS`（預設 5）與 `LOCKOUT_DECAY_MINUTES`（預設 15）。

#### Scenario: 使用預設值
- **WHEN** `.env` 未設定 `LOCKOUT_MAX_ATTEMPTS` / `LOCKOUT_DECAY_MINUTES`
- **THEN** 系統使用 5 次 / 15 分鐘作為鎖定參數

#### Scenario: 透過 env 覆蓋閾值
- **WHEN** `.env` 設定 `LOCKOUT_MAX_ATTEMPTS=10`
- **THEN** 系統以 10 次失敗後才鎖定

### Requirement: 前端顯示帳號鎖定訊息
前端 SHALL 在接收到 HTTP 423 時，顯示「帳號已暫時鎖定」的明確提示，而非泛用的「帳密錯誤」訊息。

#### Scenario: Member 登入頁收到 423
- **WHEN** `LoginView.vue` 的登入請求收到 HTTP 423
- **THEN** 顯示 response body 中的 `message` 欄位內容（如「帳號已暫時鎖定，請於 15 分鐘後再試」）

#### Scenario: Provider 登入頁收到 423
- **WHEN** `CoachLoginView.vue` 的登入請求收到 HTTP 423
- **THEN** 同上，顯示鎖定提示訊息
