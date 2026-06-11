## ADDED Requirements

### Requirement: 管理員帳號建立途徑
管理員帳號 SHALL 僅能透過主機端 `php artisan app:create-admin` command 或資料庫 seeder 建立。系統 MUST NOT 提供任何公開的管理員註冊 HTTP 端點（原 `POST /api/admin/register` 已於 2026-06-11 因 P0 安全漏洞移除）。command 建立的密碼門檻為至少 8 碼，高於一般使用者。

#### Scenario: 公開註冊端點保持關閉
- **WHEN** 任何人（含未認證請求）送出 `POST /api/admin/register`
- **THEN** 回傳 HTTP 404，且不建立任何帳號

#### Scenario: 主機端建立管理員成功
- **WHEN** 操作者於主機執行 `php artisan app:create-admin {name} {email} --password={password}` 且資料合法
- **THEN** 建立 role=admin 的 User 與對應 AdminProfile

#### Scenario: 密碼過弱或 email 重複
- **WHEN** command 收到少於 8 碼的密碼或已存在的 email
- **THEN** command 以失敗結束，不建立任何帳號

---

### Requirement: 管理員查詢指定用戶資料
後端 SHALL 提供 `GET /api/admin/check-member/{id}` 與 `GET /api/admin/check-provider/{id}`（需 Bearer token，role=admin），依角色查詢指定用戶的基本資料與對應 profile。

#### Scenario: 查詢存在的用戶
- **WHEN** 管理員以有效 id 查詢對應角色的用戶
- **THEN** 回傳 HTTP 200 與該用戶資料

#### Scenario: id 不存在或角色不符
- **WHEN** 查詢的 id 不存在，或該用戶角色與端點不符（如以 check-member 查 provider）
- **THEN** 回傳 HTTP 404
