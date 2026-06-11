# auth-test-coverage Specification

## Purpose
驗證三角色（member / provider / admin）認證流程的測試覆蓋契約：登入／註冊／登出、帳號鎖定（P2）、OAuth state 驗證、token refresh 與登入頻率限制。此規格定義測試套件必須覆蓋的場景，任何認證行為變更時，對應測試必須同步失敗以攔截回歸。
## Requirements
### Requirement: Auth 登入／註冊／登出測試覆蓋（三角色）
測試套件 SHALL 對 member、provider、admin 三個角色各自驗證以下場景，確保回歸時能即時偵測。

#### Scenario: Member 註冊成功
- **WHEN** 送出有效的 name / email / password / password_confirmation 至 `POST /api/member/register`
- **THEN** 回傳 HTTP 201，body 包含 `{ status: true, data: { user } }`，DB 存在對應 member 用戶

#### Scenario: Member 重複 Email 註冊失敗
- **WHEN** 送出已存在的 email 至 `POST /api/member/register`
- **THEN** 回傳 HTTP 422

#### Scenario: Member 登入成功
- **WHEN** 送出正確的 email / password 至 `POST /api/member/login`
- **THEN** 回傳 HTTP 200，body 包含 `{ status: true, data: { token, token_type: "Bearer", user } }`

#### Scenario: Member 登入密碼錯誤
- **WHEN** 送出存在的 email 但錯誤的 password
- **THEN** 回傳 HTTP 401，`{ status: false, message: "電子郵件或密碼錯誤" }`

#### Scenario: Member 非活躍帳號拒絕登入
- **WHEN** 送出 is_active=false 帳號的正確帳密
- **THEN** 回傳 HTTP 403

#### Scenario: Provider 帳號無法呼叫 Member 登入
- **WHEN** role=provider 的帳號嘗試 `POST /api/member/login`
- **THEN** 回傳 HTTP 401，`{ status: false, message: "電子郵件或密碼錯誤" }`（查詢以 role 過濾，跨角色帳號視同不存在，不洩漏帳號實際所屬角色）

#### Scenario: Member 登出成功
- **WHEN** 已登入 Member 送出 `POST /api/member/logout`（帶有效 Bearer token）
- **THEN** 回傳 HTTP 200，token 被撤銷（後續以此 token 請求受保護資源應得 401）

#### Scenario: Provider 登出成功
- **WHEN** 已登入 Provider 送出 `POST /api/provider/logout`（帶有效 Bearer token）
- **THEN** 回傳 HTTP 200，token 被撤銷（後續以此 token 請求受保護資源應得 401）

#### Scenario: Admin 登出成功
- **WHEN** 已登入 Admin 送出 `POST /api/admin/logout`（帶有效 Bearer token）
- **THEN** 回傳 HTTP 200，token 被撤銷（後續以此 token 請求受保護資源應得 401）

#### Scenario: Provider 帳號無法呼叫 Admin 登入
- **WHEN** role=provider 的帳號嘗試 `POST /api/admin/login`
- **THEN** 回傳 HTTP 401，`{ status: false, message: "電子郵件或密碼錯誤" }`（查詢以 role 過濾，跨角色帳號視同不存在）

#### Scenario: Provider 註冊成功
- **WHEN** 送出有效的 name / email / password / password_confirmation 至 `POST /api/provider/register`
- **THEN** 回傳 HTTP 201，body 包含 `{ status: true, data: { user } }`，DB 存在對應 provider 用戶

#### Scenario: Provider 重複 Email 註冊失敗
- **WHEN** 送出已存在的 email 至 `POST /api/provider/register`
- **THEN** 回傳 HTTP 422

#### Scenario: Provider 登入成功
- **WHEN** 送出正確的 email / password 至 `POST /api/provider/login`
- **THEN** 回傳 HTTP 200，body 包含 `{ status: true, data: { token, token_type: "Bearer", user } }`

#### Scenario: Provider 非活躍帳號拒絕登入
- **WHEN** 送出 is_active=false 的 provider 帳號正確帳密
- **THEN** 回傳 HTTP 403

#### Scenario: Member 帳號無法呼叫 Provider 登入
- **WHEN** role=member 的帳號嘗試 `POST /api/provider/login`
- **THEN** 回傳 HTTP 401，`{ status: false, message: "電子郵件或密碼錯誤" }`（查詢以 role 過濾，跨角色帳號視同不存在）

#### Scenario: Admin 公開註冊端點保持關閉
- **WHEN** 任何人送出 `POST /api/admin/register`（該端點已於 2026-06-11 因 P0 漏洞移除，見 admin-auth 規格「管理員帳號建立途徑」）
- **THEN** 回傳 HTTP 404，且不建立任何帳號；帳號建立改由 `app:create-admin` command 覆蓋測試

#### Scenario: Admin 登入成功
- **WHEN** 送出正確的 email / password 至 `POST /api/admin/login`
- **THEN** 回傳 HTTP 200，body 包含 `{ status: true, data: { token, token_type: "Bearer", user } }`

#### Scenario: Admin 登入非 admin 帳號失敗
- **WHEN** role=member 帳號嘗試 `POST /api/admin/login`
- **THEN** 回傳 HTTP 401，`{ status: false, message: "電子郵件或密碼錯誤" }`（查詢以 role 過濾，跨角色帳號視同不存在）

---

### Requirement: 帳號鎖定測試覆蓋（P2 Fixed Window）
測試套件 SHALL 驗證帳號鎖定邏輯的全部關鍵路徑，以防止回歸破壞安全機制。

#### Scenario: 失敗次數未達閾值不鎖定
- **WHEN** 同一帳號連續登入失敗 4 次
- **THEN** 每次均回傳 HTTP 401，帳號未鎖定

#### Scenario: 第 5 次失敗當場觸發鎖定
- **WHEN** 同一帳號連續登入失敗第 5 次
- **THEN** 回傳 HTTP 423，body 包含 `{ status: false, locked_until: "<ISO8601>" }`

#### Scenario: 鎖定期間任何登入均被拒絕
- **WHEN** 帳號已鎖定，使用者送出（含正確密碼的）登入請求
- **THEN** 回傳 HTTP 423，不驗證密碼

#### Scenario: 不存在帳號不累計失敗計數
- **WHEN** 以不存在的 email 連續嘗試登入 10 次
- **THEN** 每次均回傳 HTTP 401，Cache 中不建立計數 key，第 10 次後帳號不鎖定

#### Scenario: 登入成功後清除失敗計數
- **WHEN** 帳號失敗 3 次後成功登入，再失敗 4 次
- **THEN** 失敗計數從成功後重新累積，第 4 次仍回傳 401（不觸發鎖定）

#### Scenario: Email 大小寫視為同一帳號
- **WHEN** 同一帳號分別用 `user@example.com` 失敗 3 次、`User@Example.COM` 失敗 2 次
- **THEN** 第 5 次失敗（累計）觸發鎖定，回傳 HTTP 423

#### Scenario: Email 前後空白視為同一帳號
- **WHEN** 同一帳號分別用 `user@example.com` 失敗 3 次、`  user@example.com  `（含前後空白）失敗 2 次
- **THEN** 第 5 次失敗（累計）觸發鎖定，回傳 HTTP 423

#### Scenario: 鎖定 cache entry 不存在時帳號恢復可登入
- **WHEN** 帳號已鎖定，鎖定的 cache entry 被移除（模擬 TTL 自然過期）
- **THEN** 帳號可正常登入，回傳 HTTP 200

#### Scenario: 跨角色嘗試不會汙染或鎖定正確角色帳號
> 註：原情境假設「同一 email 同時擁有 member 與 provider 帳號」，但 `users.email` 在 DB 層級為全域 unique（`database/migrations/2025_05_06_065906_create_orgin_table.php:18`），此前提不可能成立，故改寫為下列可達成、且驗證相同安全性質（角色間鎖定計數互相隔離）的版本。
- **WHEN** 一個僅存在 provider 帳號的 email，先對 `/api/member/login` 送出 4 次失敗嘗試，再對 `/api/provider/login` 送出 4 次失敗嘗試
- **THEN** member namespace 不建立計數 key（帳號不存在於該 namespace）；provider namespace 計數累積為 4、未達閾值不鎖定；provider 帳號仍可用正確密碼正常登入

#### Scenario: Fixed Window — 失敗不延長 TTL
- **WHEN** 帳號失敗 4 次後，第 4 次失敗發生於 TTL 將到期前
- **THEN** Cache TTL 不被重設，`locked_until` 仍為第一次失敗時寫入的時間

#### Scenario: `locked_until` 來自 companion key
- **WHEN** 帳號觸發鎖定，回傳 HTTP 423
- **THEN** response body 的 `locked_until` 等於 `login_expires_at:{role}:{email}` cache key 的值

---

### Requirement: OAuth State 驗證測試覆蓋（P2）
測試套件 SHALL 驗證 OAuth callback 的 state 比對邏輯，涵蓋正常、缺失、不符三種情境。

#### Scenario: State 缺失時 redirect 至錯誤頁，且不呼叫 Socialite
- **WHEN** 直接呼叫 `GET /auth/google/callback` 而不帶 `state` 參數（session 無 `oauth_state`）
- **THEN** 後端 redirect 至包含 `error=oauth_failed` 的 URL，不繼續登入流程；Socialite 的 `user()` 不被呼叫

#### Scenario: State 不符時 redirect 至錯誤頁，且不呼叫 Socialite
- **WHEN** callback 帶 `state=wrong_value`，但 session 中 `oauth_state` 為不同值
- **THEN** 後端 redirect 至包含 `error=oauth_failed` 的 URL；Socialite 的 `user()` 不被呼叫

#### Scenario: State 正確時完成 OAuth 登入
- **WHEN** callback 帶的 `state` 與 session 中 `oauth_state` 一致，且 Socialite 回傳有效 Google user
- **THEN** 後端完成登入，redirect 至前端並附帶 token（URL fragment `#token=...`）

#### Scenario: State 成功驗證後從 session 消耗
- **WHEN** callback 帶正確 `state` 完成一次登入後，再次以相同 `state` 呼叫 callback
- **THEN** session 中已無 `oauth_state`，視為 state 缺失，redirect 至 `error=oauth_failed`

