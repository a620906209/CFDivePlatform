## MODIFIED Requirements

### Requirement: Auth 登入／註冊／登出測試覆蓋（三角色）
（僅列出本次變更的 scenario；其餘 scenario 不變）

原「Admin 註冊成功」與「Admin 重複 Email 註冊失敗」兩個 scenario 移除（端點已刪），改為：

#### Scenario: Admin 公開註冊端點保持關閉
- **WHEN** 任何人送出 `POST /api/admin/register`（該端點已於 2026-06-11 因 P0 漏洞移除，見 admin-auth 規格「管理員帳號建立途徑」）
- **THEN** 回傳 HTTP 404，且不建立任何帳號；帳號建立改由 `app:create-admin` command 覆蓋測試

另補主規格歸檔時遺留的 TBD Purpose：

> 驗證三角色（member / provider / admin）認證流程的測試覆蓋契約：登入／註冊／登出、帳號鎖定（P2）、OAuth state 驗證、token refresh 與登入頻率限制。此規格定義測試套件必須覆蓋的場景，任何認證行為變更時，對應測試必須同步失敗以攔截回歸。
