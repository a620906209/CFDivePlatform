## ADDED Requirements

### Requirement: Auth 補全端點 Swagger 文件

`app/Docs/AuthSupplementDoc.php` SHALL 補全未文件化的 Auth 相關端點（Google OAuth、change-password）。

#### Scenario: Google OAuth 端點文件化

- **WHEN** 開啟 Swagger UI
- **THEN** 以下端點均有文件：
  - `GET /auth/google/redirect`（response: redirect_url，說明用途為取得 Google OAuth redirect URL）
  - `GET /auth/google/callback`（response: token + user，說明此為 OAuth callback，通常由瀏覽器自動呼叫）

#### Scenario: change-password 端點文件化（三個 role）

- **WHEN** 開啟 Swagger UI
- **THEN** 以下端點均有文件，各自標示對應 role 的 `security: bearerAuth`：
  - `PUT /member/change-password`（request: current_password、password、password_confirmation）
  - `PUT /provider/change-password`（同上）
  - `PUT /admin/change-password`（同上）
  - 422 驗證失敗 response 亦有定義
