## Context

現有防護分兩層：
- **IP throttle**（P0）：middleware `throttle:5,1` / `throttle:3,1`，防同一 IP 在短時間大量嘗試
- **Session Token**（P1）：token 7 天過期，sessionStorage 存放，refresh interceptor

缺口一：攻擊者用多 IP 對同一帳號暴破，IP throttle 完全無效。  
缺口二：OAuth redirect URL 若遭竄改（CSRF），惡意方可替換 authorization code。

## Goals / Non-Goals

**Goals:**
- 帳號連續失敗 5 次 → 鎖定 15 分鐘，鎖定期間任何密碼登入均拒絕
- 鎖定計數以 Cache 存放（key 包含 `email`），TTL = 鎖定時間
- 登入成功後清除失敗計數
- OAuth redirect 帶 state，callback 驗 state，不符回傳 400
- 前端顯示 423 對應的帳號鎖定訊息

**Non-Goals:**
- Admin 帳號鎖定（Admin Panel 尚未完成）
- 電子郵件通知（鎖定時通知帳號持有人）
- Admin 手動解鎖端點（此 change 暫不實作；時間到自動解鎖即可）
- Lockout 事件 audit log

## Decisions

### D1：失敗計數存 Cache（not DB）

**選擇**：`Cache::increment()` / `Cache::put()`，key = `login_failures:{email}`，TTL = 鎖定時間（15 min）。

**理由**：
- 不需要永久儲存，TTL = 鎖定時間，到期自動解鎖，零額外維護
- DB 方案需 migration + GC 排程，複雜度高 3 倍
- Cache driver 目前為 file（dev）/ Redis（prod），兩者皆可用

**替代方案**：在 `users` 資料表新增 `failed_login_count` / `locked_until` 欄位 → 否決，因為要 migration，且鎖定狀態不需跨重啟持久化。

### D2：鎖定參數放 config

```php
// config/auth_lockout.php
return [
    'max_attempts' => env('LOCKOUT_MAX_ATTEMPTS', 5),
    'decay_minutes' => env('LOCKOUT_DECAY_MINUTES', 15),
];
```

**理由**：不同環境（測試 = 100 次）可靠 env 覆蓋，不用改程式碼。

### D3：HTTP 423 Locked（帳號鎖定）

**選擇**：回傳 HTTP 423，body `{ status: false, message: "帳號已暫時鎖定，請於 X 分鐘後再試", locked_until: "<ISO8601>" }`

**理由**：
- 422 / 401 語義不清，423 在 WebDAV 語境下即「資源被鎖定」
- 前端依 423 顯示靜態鎖定提示（`message` 欄位），不實作倒數；`locked_until` timestamp 保留在 response 供未來擴充

### D4：OAuth state 存 Laravel Session（不存 Cache）

**選擇**：`session()->put('oauth_state', $state)` 於 redirect；callback 讀取並 `session()->pull('oauth_state')` 後以 `hash_equals` 比對。

**實作細節**：Socialite 的 `redirect()` 內部會自行產生 random state 並寫入 `session('state')`，但 URL 裡已被 `.with(['state' => $state])` 換成我們的 state。若不覆蓋，Socialite 的 `user()` 做內建驗證時會因 session 值與 URL 值不符拋出 `InvalidStateException`。解法：在 `redirect()` 呼叫之後、return 之前，再呼叫 `session()->put('state', $state)` 覆蓋，讓兩層驗證（手動 + Socialite 內建）使用同一 state 值。`stateless()` 因此可完全移除。

**理由**：
- state 是 per-request 一次性值，Session 語義完全對應
- 移除 `stateless()` 讓 Socialite 內建驗證也生效，雙重保護
- Cache 方案需自行管理 TTL 與 key 命名，複雜度無謂提升

**替代方案**：PKCE flow → 需要前端配合改 OAuth 啟動方式，改動面太大，此 change 不採用。

### D5：不移除現有 IP throttle

帳號鎖定與 IP throttle 並行：IP throttle 先觸發（429），之後才會累積到帳號鎖定（423）。兩者互不衝突，都保留。

### D6：Email 正規化規則

**選擇**：所有 Cache key 使用前，先對 email 執行 `strtolower(trim($email))`。

**理由**：`User@Gmail.com` 與 `user@gmail.com` 在 RFC 5321 語境下是同一信箱，不正規化會導致攻擊者對同一帳號用大小寫變體繞過計數上限。Trim 防止前後空白導致 key 差異。

**範圍**：同規則套用到計數 key、expires_at key、以及查詢 DB 前的 email 比對。Laravel Eloquent `where('email', ...)` 若 DB collation 為 `utf8mb4_unicode_ci` 則大小寫不敏感，但 Cache key 必須額外正規化。

### D7：不存在帳號不累計失敗計數（已確認）

**選擇**：若 DB 查無此 email，**不**遞增 Cache 計數，但仍回傳 HTTP 401 並使用與密碼錯誤**相同**的泛用訊息（`"電子郵件或密碼錯誤"`），不揭露帳號是否存在。

**理由**：
- 鎖定機制目的是保護真實帳號；不存在的帳號無帳可鎖
- 為不存在帳號累計計數會造成 Cache 污染，且攻擊者可製造大量假 email 讓 Cache 暴增
- 統一 401 訊息確保攻擊者無法透過錯誤回應的差異做帳號枚舉（account enumeration）
- 已知殘留風險：攻擊者對真實帳號觸發 5 次失敗後可從 423 推斷帳號存在，但此操作本身已受 IP throttle 限速，實際攻擊成本高

**邊界對照**：帳號不存在 → 401（不遞增）；帳號存在但密碼錯 → 401（遞增）；帳號已鎖定 → 423；兩種 401 的 response body 訊息相同。

### D8：TTL / Window 語義 — Fixed Window from First Failure

**選擇**：採用 **Fixed window**，起算點為「**第一次失敗**」。

具體規則：
1. 查 Cache key `login_failures:{role}:{email}`（email 已正規化）
2. 若 key **不存在**（count = 0，或 window 已過期）：
   - `Cache::put('login_failures:{role}:{email}', 1, $decay_minutes * 60)`
   - 同時寫入 `login_expires_at:{role}:{email}` = `now()->addMinutes($decay_minutes)->toIso8601String()`，TTL 相同
3. 若 key **存在**（count ≥ 1）：
   - `Cache::increment('login_failures:{role}:{email}')`（**不重設 TTL**，window 不延長）
4. 若遞增後 count ≥ max_attempts：回傳 HTTP 423

**理由**：Sliding window（每次失敗延長 TTL）對合法使用者懲罰過重——誤輸密碼後，每一次重試都重置等待時間，實際上永遠解不了鎖。Fixed window 可預測：「最多等 decay_minutes 分鐘」，對使用者友善。

**替代方案**：Sliding window → 否決，原因同上。

### D9：`locked_until` 的資料來源 — Companion Key

**選擇**：第一次失敗時同步寫入 companion key `login_expires_at:{role}:{email}`，值為 ISO 8601 字串，TTL 與計數 key 相同。每次回傳 HTTP 423 時讀取此 key 作為 `locked_until` 欄位值。

**理由**：
- Laravel Cache 不保證所有 driver 提供 `getExpiry()` API（file driver 無此方法）
- Companion key 方案不依賴 driver 實作細節，跨 file / Redis driver 行為一致
- 兩個 key 的 TTL 相同，到期時同步消失，無需額外清理

**Fallback**：若 `login_expires_at` key 因 driver 不一致或 race condition 缺失，fallback 為 `now()->addMinutes($decay_minutes)->toIso8601String()` 並寫 `Log::warning`。

### D10：Cache Key Namespace 正式定義

**完整 key 格式**：

| Key 用途 | 格式 | 有效 role 值 |
|---------|------|------------|
| 失敗計數 | `login_failures:{role}:{normalized_email}` | `member`、`provider` |
| 鎖定到期時間 | `login_expires_at:{role}:{normalized_email}` | `member`、`provider` |

`{role}` 的值與 API 路徑對應：`/api/member/login` → `member`；`/api/provider/login` → `provider`。`admin` 明確不在此 change 範圍，不建立任何相關 key。

`{normalized_email}` = `strtolower(trim($request->email))`，於 controller 頂部完成正規化，後續所有 Cache 操作一律使用正規化後的值。

### D11：登入端點 Response Semantics 完整對照表

以下為 Member / Provider 登入端點所有可能回應的**完整定義**，用於消除實作端的歧義：

| 情境 | 檢查順序 | HTTP | Response Body |
|------|---------|------|---------------|
| 帳號已在目前 lockout window 內（lockout active） | 1st | 423 | `{ status: false, message: "帳號已暫時鎖定，請於 N 分鐘後再試", locked_until: "<ISO8601>" }` |
| 帳號不存在（DB 查無 email） | 2nd | 401 | `{ status: false, message: "電子郵件或密碼錯誤" }` |
| 帳號存在，密碼錯誤，失敗 1–4 次（未達閾值） | 3rd | 401 | `{ status: false, message: "電子郵件或密碼錯誤" }` |
| 帳號存在，密碼錯誤，**第 5 次**（觸發鎖定） | 3rd | 423 | `{ status: false, message: "帳號已暫時鎖定，請於 N 分鐘後再試", locked_until: "<ISO8601>" }` |
| 帳號存在，密碼正確 | 3rd | 200 | `{ status: true, data: { token: "...", user: {...} } }` |

**檢查順序說明**：
1. 先查 Cache 判斷是否已鎖定（最快路徑，不進 DB）
2. 再查 DB 確認帳號存在（帳號不存在 → 401，不遞增計數）
3. 最後驗證密碼（錯誤 → 遞增計數 → 若達閾值回 423，否則 401；正確 → 200 + 清計數）

`message` 中的 `N` 為靜態設定值（`decay_minutes`），不是即時計算的剩餘分鐘數。前端**不實作**剩餘時間倒數；`locked_until` 欄位保留於 response 以備未來若需精確倒數時使用。

### D12：OAuth 多分頁並發 Flow 的預期行為

**選擇**：同一瀏覽器同一 session 下，**只有最後一個啟動的 OAuth flow 能成功**；先啟動的 tab 的 callback 因 state 不符而收到 HTTP 400，前端引導使用者重新操作。此行為為**設計預期**，不視為 bug。

**理由**：
- Laravel session 在同一瀏覽器共用（相同 session cookie），後寫的 `oauth_state` 覆蓋先寫的
- 支援多個並發 state 需在 session 中維護 state 陣列（push/find/remove），複雜度不符現階段需求
- 使用者實際上幾乎不會在兩個 tab 同時進行 Google OAuth；若發生，400 → redirect `/login?error=oauth_failed` 是合理的 UX 引導

**替代方案**：Session 存 state 陣列（每次 redirect 前 push，callback 時 find & remove）→ 否決，目前無真實使用場景，過度設計。

## Risks / Trade-offs

- **[DoS 風險] 攻擊者刻意鎖定合法帳號** → 攻擊者只要知道 email 就能讓帳號鎖 15 分鐘。緩解：IP throttle 已先擋，15 分鐘鎖定時間短，影響有限；金流上線前再評估是否加 CAPTCHA。
- **[Account Enumeration Trade-off] 不存在帳號不累計失敗計數** → 可避免攻擊者以大量假 email 汙染 Cache，但攻擊者理論上仍可透過 repeated attempts 是否最終進入 423，間接推測帳號存在與否。本 change 接受此風險，先以統一 401 訊息與既有 IP throttle 緩解；若未來威脅模型提高，再評估改為對不存在帳號也累計或加入 CAPTCHA。
- **[Cache 遺失] file cache 重啟後失效** → 鎖定計數不持久，重啟即解鎖。生產環境預設使用 Redis（`.env` `CACHE_DRIVER=redis`），計數跨容器共享且重啟不失效；file cache 可運作但效果較差（計數不跨容器、重啟清零），僅適用於 dev 環境。
- **[Session 相容] 移除 stateless() 後需 session driver 正常運作** → Docker 環境 session driver 使用 file，確認 storage/framework/sessions 有寫入權限即可。

## Migration Plan

1. 部署時無 DB migration（Cache 方案不需 migration）
2. **生產環境** `.env` 確認 `CACHE_DRIVER=redis`（Redis 為生產預設，確保計數跨容器共享）
3. `.env` 可選加：`LOCKOUT_MAX_ATTEMPTS=5`、`LOCKOUT_DECAY_MINUTES=15`（有預設值，不加也可）
4. OAuth state 改動：session driver 已在 dev 正常使用，無需額外設定
5. Rollback：移除兩個 feature 的 controller 邏輯，恢復 `stateless()`；不需 DB rollback

## Open Questions

~~是否在前端 LoginView 顯示「剩餘鎖定時間」倒數？~~ → **已決定**：不實作倒數，只顯示靜態 `message`；`locked_until` 保留於 response 供未來擴充。

~~生產環境 Cache driver 是否確定為 Redis？~~ → **已決定**：生產環境預設 Redis（`CACHE_DRIVER=redis`）；file cache 可運作但效果較差，不建議用於生產。
