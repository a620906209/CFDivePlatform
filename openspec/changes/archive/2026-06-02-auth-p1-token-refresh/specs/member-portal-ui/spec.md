## MODIFIED Requirements

### Requirement: 認證狀態管理
前端 SHALL 使用 Pinia store 管理認證狀態，token 存於 sessionStorage（非 localStorage），並在所有需認證的 API 請求自動附加 Bearer token。收到 401 時先嘗試 refresh，成功後 retry；refresh 失敗才清除 session 並導向登入頁。

#### Scenario: 頁面刷新後保持登入狀態
- **WHEN** 已登入使用者在同一分頁重新整理頁面
- **THEN** 從 sessionStorage 還原 token，使用者仍為登入狀態

#### Scenario: 分頁關閉後 token 自動清除
- **WHEN** 使用者關閉瀏覽器分頁或瀏覽器
- **THEN** sessionStorage 自動清除，重新開啟需重新登入

#### Scenario: Token 過期後自動 refresh
- **WHEN** API 請求因 token 過期回傳 401
- **THEN** 自動呼叫 `POST /api/member/refresh`，成功後 retry 原始請求，使用者無感知

#### Scenario: 登出
- **WHEN** 使用者點擊登出
- **THEN** 呼叫 `POST /api/member/logout`，清除 sessionStorage token，導向 `/login`
