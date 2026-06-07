## Context

Auth 模組已實作三角色（member / provider / admin）的登入、註冊、登出，以及 P2 安全強化（帳號鎖定、OAuth state 驗證）。目前 `tests/Feature/` 只有 `AuthRateLimitTest`（throttle）和 `TokenRefreshTest`（refresh token），主流程與安全邏輯完全無覆蓋。

測試環境：`phpunit.xml` 已設定 `DB_CONNECTION=sqlite`、`DB_DATABASE=:memory:`，Cache 使用 `array` driver，Queue 使用 `sync`。

## Goals / Non-Goals

**Goals:**
- 補齊登入／註冊／登出三角色的 happy path 與 guard 測試
- 覆蓋 P2 帳號鎖定邏輯（Fixed Window、Email 正規化、role 隔離）
- 覆蓋 P2 OAuth state 驗證（state 缺失 / 不符 / 正確）
- 每個測試檔案獨立、可單獨執行

**Non-Goals:**
- 不測試 Token Refresh（已有 `TokenRefreshTest`）
- 不測試 Rate Limiting（已有 `AuthRateLimitTest`）
- 不測試前端 Vue 元件
- 不測試 Admin CRUD（屬於別的 change 範疇）

## Decisions

### 決策 1：三支測試檔案，各自對應一個關注點
- `AuthLoginTest.php`：登入 / 註冊 / 登出三角色的 happy path + guard
- `AuthLockoutTest.php`：P2 帳號鎖定邏輯
- `AuthOAuthTest.php`：P2 OAuth state 驗證

**理由**：單一檔案職責清晰，出錯時可快速定位；不同測試的 setUp 需求差異大（Lockout 需 Cache::flush、OAuth 需 Socialite mock）。

### 決策 2：Lockout 測試使用 Cache facade，不 mock
phpunit.xml 已設 `CACHE_STORE=array`，Cache 行為完全真實。
`setUp()` 呼叫 `Cache::flush()` 確保測試間隔離。

**理由**：Lockout 邏輯的 bug 通常出在 Cache key 命名或 TTL 操作，用真實 array cache 才能抓到；mock 只會讓測試通過而 bug 繼續存在。

### 決策 3：OAuth 測試使用 Socialite mock，並以 shouldNotReceive 驗證安全不變式
OAuth callback 依賴 Google 回應，測試環境無法真實呼叫，使用 Socialite Facade mock。

Mock 在 OAuth 測試中有兩個用途：
- **state 正確時**：`shouldReceive('driver->user')` 回傳假 Google user，讓登入流程走完
- **state 缺失 / 不符時**：`shouldNotReceive('driver->user')` 確認 Socialite 不被呼叫

第二個用途是這組測試的核心安全不變式：**CSRF 檢查失敗時，系統不得向 OAuth provider 取 user**。如果只斷言 redirect URL 含 `error=oauth_failed` 而不驗證 Socialite 未被呼叫，測試無法偵測「state 檢查失敗但仍繼續執行 OAuth 流程」這類回歸。

**理由**：OAuth state 的設計意圖不只是「最後回錯誤」，而是在 CSRF 檢查點提前中止整個流程；`shouldNotReceive` 是唯一能驗證這個 invariant 的手段。

### 決策 4：共用 helper 方法直接定義在各測試 class 內
三支測試各自有 `createMember()`、`createProvider()`、`createAdmin()` 等 helper。

**理由**：測試共 3 個檔案，規模不大，不值得抽 BaseTestCase。一旦需要改 helper 邏輯，在各自檔案改更直觀，不會影響其他測試。

## Risks / Trade-offs

- **OAuth redirect 比對**：`handleGoogleCallback` 最終用 `redirect()` 回前端 URL，測試需用 `assertRedirect()` 而非 `assertStatus()`，URL 中含 `error=oauth_failed` 做斷言。→ 使用 `assertRedirectContains('error=oauth_failed')` 避免硬寫前端 URL。
- **Lockout TTL 在測試中**：array cache 不適合可靠驗證時間流逝後的自動過期，因此不測 cache driver 的 TTL 行為本身。測試只驗證「當 lockout cache entry 不存在時，登入流程恢復允許」；清除狀態以 `Cache::forget()` 模擬，不等待真實時間。這不等同於「TTL 到期自動解鎖」的驗證，兩者不可混淆。
- **Admin 帳號在鎖定測試的範圍**：AuthController 的 `loginAdmin` 沒有帳號鎖定邏輯（P2 只加了 member/provider），不在 AuthLockoutTest 測試範圍。→ 在 AuthLoginTest 只測 Admin 的 happy path 與角色 guard。

## Open Questions

- 無（設計已足夠完整開始實作）
