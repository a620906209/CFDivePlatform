## ADDED Requirements

### Requirement: OAuth 授權流程帶 state 參數防 CSRF
後端 SHALL 在 OAuth redirect 前產生隨機 state 字串（至少 32 bytes hex），存入 `session('oauth_state')`，並附加於 OAuth redirect URL 的 `state` query parameter。`stateless()` 呼叫 SHALL 被移除；Socialite 在 `redirect()` 內會自行寫入 `session('state')`，實作 SHALL 在 `redirect()` 呼叫後以同一 state 值覆蓋 `session('state')`，確保 Socialite 內建驗證與手動驗證使用相同值。callback 時 SHALL 從 request 讀取 `state` 並與 `session('oauth_state')` 比對（`hash_equals`）；比對成功後 SHALL 立即清除；不符或缺少時 SHALL redirect 至 `/login?error=oauth_failed`。

#### Scenario: 正常 OAuth 登入流程
- **WHEN** 使用者點擊「Google 登入」，後端發出 redirect
- **THEN** redirect URL 包含 `state=<random>` 參數，且相同 random 值存入 session；使用者在 Google 完成授權後，callback 帶回相同 state，後端驗證通過，繼續正常登入流程

#### Scenario: state 缺失（直接訪問 callback）
- **WHEN** 攻擊者或使用者直接訪問 `/auth/google/callback` 而不帶 `state` 參數
- **THEN** 後端 redirect 瀏覽器至 `{frontend_url}/login?error=oauth_failed`，不繼續登入流程

#### Scenario: state 不符（CSRF 攻擊）
- **WHEN** callback 帶的 `state` 與 `session('oauth_state')` 中的值不一致
- **THEN** 後端 redirect 瀏覽器至 `{frontend_url}/login?error=oauth_failed`，不繼續登入流程

#### Scenario: state 只能使用一次
- **WHEN** callback 驗證 state 成功後（`session()->pull('oauth_state')` 已清除），再次以相同 state 訪問 callback
- **THEN** session 中已無 `oauth_state`，視為 state 缺失，同樣 redirect 至 `/login?error=oauth_failed`

#### Scenario: 登入頁顯示 OAuth 失敗訊息
- **WHEN** 瀏覽器被 redirect 至 `/login?error=oauth_failed`
- **THEN** `LoginView.vue` 偵測到 `route.query.error === 'oauth_failed'`，顯示「OAuth 授權失敗，請重新嘗試」提示；`AuthCallbackView.vue` 不涉及此錯誤流程

#### Scenario: 多分頁並發 OAuth flow — 只有最後啟動的成功（預期行為）
- **WHEN** 使用者在同一瀏覽器開兩個 tab，Tab A 先啟動 OAuth（session 寫入 state_A），Tab B 後啟動 OAuth（session 覆寫為 state_B），Tab A 的 callback 先到達後端
- **THEN** Tab A 的 callback 帶 state_A，與 session 中的 state_B 不符，後端 redirect 至 `/login?error=oauth_failed`；Tab B 的 callback 帶 state_B，比對成功，正常完成登入

### Requirement: OAuth session driver 正常運作
後端 SHALL 確保 session driver 在 Docker 環境中可寫入（`storage/framework/sessions` 目錄存在且有寫入權限），以支援 state 存取。

#### Scenario: Session 寫入成功
- **WHEN** OAuth redirect 觸發時
- **THEN** state 值成功寫入 session，無 500 錯誤
