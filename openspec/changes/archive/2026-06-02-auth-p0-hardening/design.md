## Context

CFDivePlatform 使用 Sanctum Bearer Token 認證，token 儲存於前端 localStorage。安全評估發現三個在當前架構下可直接修復的高危漏洞，不需要切換認證機制。此 change 針對性修補，範圍刻意控制在最小改動。

## Goals / Non-Goals

**Goals:**
- Token 設定有限期，降低洩漏後的持續暴露時間
- 消除 Google OAuth redirect URL 中的 token 洩漏路徑
- 對登入端點加入基本的暴力破解防護

**Non-Goals:**
- 不切換至 HttpOnly Cookie（需要 HTTPS、CSRF token、OAuth 重構，成本過高）
- 不修改既有 token 的儲存位置（localStorage → sessionStorage 留 P1）
- 不實作 token refresh / silent renew 機制（留 P1）
- 不清除資料庫中現存的永不過期 token（`expires_at = null` 的舊記錄接受自然淘汰；`prune-expired` 只刪 `expires_at` 已逾期的記錄，兩者不衝突）

## Decisions

### 1. Token 過期時間設為 7 天

**選擇**：`sanctum.expiration = 10080`（分鐘）

**原因**：7 天符合「使用者每週至少登入一次」的合理假設，過短（如 1 天）會顯著提高重新登入頻率影響 UX，過長則縮減不了多少攻擊視窗。

**替代方案考慮**：
- 1 天：洩漏視窗最小，但 UX 衝擊大
- 30 天：常見電商慣例，但本平台含金流元素不適用
- 無限：現狀，排除

**副作用**：`sanctum.expiration` 只影響新建立的 token，既有 `personal_access_tokens` 中 `expires_at = null` 的記錄不受影響。可接受——舊 token 在使用者下次登入後自然被新 token 取代。

**清理機制**：啟用 expiration 後過期 token 不會自動從資料庫刪除，需搭配 `sanctum:prune-expired --hours=168` 排程（`routes/console.php`）每日清理，避免 `personal_access_tokens` 表持續膨脹。

---

### 2. Google OAuth redirect 改用 URL Fragment

**選擇**：`redirect(config('app.frontend_url') . '/auth/callback#token=' . $token)`

**原因**：
- URL fragment（`#` 後的部分）依 HTTP 規範不會被瀏覽器送到 server，因此**不會出現在 Nginx/server log**
- Fragment 不會包含在 `Referer` header 中
- Fragment 不會出現在瀏覽器歷史的完整記錄中（部分瀏覽器歷史仍記錄 fragment，但不在伺服器端）
- 前端改動僅一行：`route.query.token` → `new URLSearchParams(window.location.hash.substring(1)).get('token')`

**替代方案考慮**：
- **後端 POST 回 token**：OAuth provider 只能 redirect，無法直接 POST
- **server-side session 中繼**：後端 redirect 到 `/auth/callback?code=<random>` 再由前端換 token——更安全但需要 server-side session store，架構複雜度提高
- **保持 query string**：現狀，洩漏路徑未消除，排除

**選擇 fragment 而非 server-side session 的原因**：fragment 方案完全消除 server log 洩漏問題，且改動最小，對此平台規模足夠。

---

### 3. Rate Limiting 使用 Laravel 內建 throttle middleware

**選擇**：
- Member / Provider 登入：throttle:5,1
- Admin 登入：throttle:3,1（帳號權限較高，適當收緊）

**原因**：
- Laravel 11 throttle middleware 基於 IP，不需要額外套件
- 觸發時自動回傳 HTTP 429 並帶 `Retry-After` header
- 5次/分鐘對正常使用者幾乎無感，但讓暴力破解的時間成本從秒級提高到分鐘級

**替代方案考慮**：
- `throttle:10,1`：太寬鬆，對所有角色排除
- `throttle:3,1` for member/provider：可能誤傷快速填錯密碼的正常使用者，故 member/provider 維持 5 次；admin 因帳號影響範圍大，3 次的誤傷成本可接受
- Progressive delay（每次失敗增加等待時間）：需要額外實作，P0 不引入複雜度

**僅套用登入端點**：register 端點不加（新帳號建立的攻擊面不同，留 P1）

## Risks / Trade-offs

- **既有 token 不過期**：`expiration` 改動不回溯，需接受現有洩漏的舊 token 持續有效直到使用者重新登入。風險可接受，因為：(1) 此為首次設定，無歷史洩漏事件；(2) 使用者正常使用就會刷新。
- **`personal_access_tokens` 表膨脹**：啟用 expiration 後過期記錄不自動清除，需排程 `sanctum:prune-expired` → 已列入 tasks。
- **`env()` 在 config cache 下失效**：`SocialAuthController` 原本使用 `env('FRONTEND_URL')`，執行 `php artisan config:cache` 後（production 必做）`env()` 回傳 null，導致 redirect 到錯誤 URL。此 change 一併修正為 `config('app.frontend_url')`（`config/app.php` 已有此鍵）。
- **fragment 在部分瀏覽器歷史仍可見**：Chrome DevTools Application → Session History 仍記錄完整 URL 含 fragment，但這是本地攻擊面（攻擊者需存取使用者設備），遠低於 server log 洩漏的遠端攻擊面。
- **IP-based throttle 可被繞過**：攻擊者可換 IP 或使用代理。此方案擋的是基本掃描攻擊，不是針對性攻擊。P1 可加 account-level lockout。

## Migration Plan

1. 套用 `config/sanctum.php` 修改，重啟 PHP-FPM（或 Docker container）
2. 在 `routes/console.php` 新增 `Schedule::command('sanctum:prune-expired --hours=168')->daily();`（無需重啟，下次排程週期生效）
3. 套用 `routes/api.php` 修改，無需重啟（路由快取需清除：`php artisan route:clear`）
4. 套用後端 `SocialAuthController.php` 修改
5. 套用前端 `AuthCallbackView.vue` 修改，重新 build frontend（`npm run build`）
6. 驗證：手動測試 Google OAuth 登入流程、登入頻率限制觸發、手動執行 `php artisan sanctum:prune-expired --hours=168` 確認排程指令可正常執行

**Rollback**：各項變更相互獨立，可逐項還原。Token 過期設定還原後，新建 token 重新改為不過期（舊的已過期的 token 無法恢復）。

## Open Questions

- **Coach 登入端點 `/api/provider/login` 是否也需要 rate limiting？**（已包含在此 change 中，與 member 同風險級別，統一採用 `throttle:5,1`）
- **Admin 登入是否需要更嚴格的限制？** → **決策：改為 `throttle:3,1`，在此 change 實作。** 管理員帳號一旦被暴力破解，影響範圍遠大於一般使用者（可存取所有用戶資料、審核功能），更嚴格的限制可接受，正常管理員操作不太可能在 1 分鐘內連續嘗試 3 次以上。
- **是否需要對 Google OAuth callback 加入 state parameter 驗證（CSRF for OAuth）？** → **確認留 P1。** 風險評估：`stateless()` 模式下缺少 state 驗證，理論上存在 CSRF-for-OAuth 攻擊面（攻擊者偽造 callback 讓受害者綁定攻擊者的 Google 帳號）。緩解因素：(1) Google OAuth 本身要求使用者主動授權，不能靜默觸發；(2) 攻擊者需控制受害者瀏覽器的 callback 請求，難度高；(3) 平台目前無高價值資產（金流未上線）。P1 實作時加入 `state` parameter 並在 session 中驗證。
