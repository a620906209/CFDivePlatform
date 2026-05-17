## ADDED Requirements

### Requirement: Laravel Mail 設定

系統 SHALL 支援透過 SMTP 寄送 Email 通知。本地開發環境使用 Mailpit（Docker service）攔截所有寄出信件，不真實發送。`.env` 設定：`MAIL_MAILER=smtp`、`MAIL_HOST=mailpit`（Docker service name）、`MAIL_PORT=1025`。

#### Scenario: 本地環境信件攔截

- **WHEN** 系統觸發 Email 通知
- **THEN** 信件出現在 Mailpit Web UI（`http://localhost:8025`），未真實寄出

---

### Requirement: Queue Worker 處理 Email 投遞

Email 通知 SHALL 透過 Laravel Queue（`QUEUE_CONNECTION=database`）非同步投遞，不阻塞 HTTP response。Queue Worker 在 Docker Compose 中以獨立 service 啟動。

#### Scenario: Email 加入 Queue

- **WHEN** 業務邏輯觸發 notify，`via()` 包含 `'mail'`
- **THEN** Email job 進入 `jobs` 資料表，HTTP response 立即回傳

#### Scenario: Queue Worker 處理後寄出

- **WHEN** queue:work 讀取到 Email job
- **THEN** Mailable 被實際執行，信件送至 SMTP（本地為 Mailpit）

#### Scenario: 失敗重試

- **WHEN** SMTP 連線失敗
- **THEN** Job 重試最多 3 次（`$tries = 3`），超過後寫入 `failed_jobs`

---

### Requirement: Email Markdown 模板

每種通知場景 SHALL 有對應的 Laravel Markdown Mailable 模板，存放於 `resources/views/emails/notifications/`。模板須包含：平台名稱（CFDivePlatform）、通知標題、正文、行動連結按鈕（CTA）、底部免責聲明。

涵蓋場景（共 6 種）：
- `booking-created.blade.php`（給 Provider）
- `booking-confirmed.blade.php`（給 Member）
- `booking-rejected.blade.php`（給 Member）
- `booking-cancelled.blade.php`（給對方）
- `booking-completed.blade.php`（給 Member）
- `review-received.blade.php`（給 Provider）

#### Scenario: Email 內容包含行動連結

- **WHEN** Member 收到「預約已確認」Email
- **THEN** 信件包含「查看預約」按鈕，點擊後導向 `{APP_URL}/my-bookings/{id}`

#### Scenario: Email 主旨語言

- **WHEN** 系統寄出任何通知 Email
- **THEN** 主旨以繁體中文撰寫（例：「你的預約已確認 — CFDivePlatform」）

---

### Requirement: Email 通知觸發條件與收件人

| 事件 | 收件人 | 主旨 |
|------|--------|------|
| 預約建立（pending） | Provider | 你有新的預約申請 |
| 預約確認（confirmed） | Member | 你的預約已確認 |
| 預約拒絕（rejected） | Member | 你的預約申請未通過 |
| 預約取消（任一方） | 對方 | 預約已取消 |
| 預約完成（completed） | Member | 預約完成，歡迎留下評價 |
| 收到新評價 | Provider | 你收到了一則新評價 |

#### Scenario: 預約建立後 Provider 收到 Email

- **WHEN** Member 成功建立預約（status 為 pending）
- **THEN** 課程所屬 Provider 在 Queue 處理後收到「你有新的預約申請」Email
