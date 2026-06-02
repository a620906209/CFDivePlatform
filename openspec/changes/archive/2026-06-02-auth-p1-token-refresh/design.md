## Context

P0 完成後，Bearer Token 有 7 天過期保護，但缺少自動續期機制，且 token 仍存於 localStorage（XSS 可讀）。此 change 引入 sliding window refresh 搭配 sessionStorage，兩者互相依賴：sessionStorage 在分頁/瀏覽器關閉後清除 token；refresh 機制確保 token 在主動使用期間不過期，兩者缺一則安全性或 UX 有所折損。

## Goals / Non-Goals

**Goals:**
- Token 在主動使用期間無限 sliding，不需手動重新登入
- Token 改存 sessionStorage，關閉分頁/瀏覽器後自動清除
- 多個並發 401 只觸發一次 refresh，其他請求排隊等待結果
- member、provider、admin 三個角色統一行為

**Non-Goals:**
- 不引入獨立的 refresh token（避免 token 儲存複雜度倍增）
- 不實作 token rotation 以外的安全機制（如 device binding）
- 不處理 admin panel（尚未實作）的 refresh 前端邏輯
- 不改變 7 天的 token 有效期設定

## Decisions

### 1. 使用現有 Bearer Token 做 refresh（無獨立 refresh token）

**選擇**：`POST /api/{role}/refresh` 接受現有 Bearer token → revoke → 發新 token

**原因**：
- 不需新增 DB 欄位或 token 類型
- 實作最簡單：後端 3 個端點各約 5 行
- 安全性足夠：refresh 需要有效 token，無法憑空 refresh
- 缺點：token 只要還有效就能 refresh（不區分「剛登入」vs「長期 refresh」），可接受

**替代方案**：獨立 refresh token（長效 + 短效 access token 組合）——更安全但需儲存兩個 token、多一層 DB 查詢，複雜度不值得在此階段引入。

---

### 2. Refresh 在 axios response interceptor 觸發（401 時）

**選擇**：interceptor 攔截 401 → 呼叫 refresh → 成功則以新 token retry 原請求 → 失敗才登出

**原因**：
- 對所有 API 呼叫自動生效，不需每個頁面個別處理
- 延遲 refresh 到實際需要時（lazy），不需 proactive timer

**並發問題**：多個 API 同時 401 時，只允許一個 refresh 進行中。實作方式：module-level `refreshing` flag + `pendingRequests` queue。refresh 進行中的其他 401 請求排入 queue，refresh 完成後統一 retry。

**替代方案**：Proactive refresh（token 快過期前主動 refresh）——需要 timer 或每次請求檢查過期時間，複雜度較高，且 sessionStorage 無法跨分頁共享計時狀態。

#### Refresh 觸發條件

下列情況回傳 401 時**觸發** refresh：
- 任何需要認證的 API 請求（`/api/member/*`、`/api/provider/*`、`/api/admin/*`）

下列情況回傳 401 時**不觸發** refresh（直接傳遞錯誤給呼叫方）：
- `/login`、`/register` 端點（帳密錯誤，非 token 問題）
- `/refresh` 端點本身（防止無限遞迴）

#### Refresh 端點接受的 token 狀態

- **接受**：`expires_at` 尚未過期、`personal_access_tokens` 中記錄存在且未被 revoke
- **拒絕**：`expires_at` 已過期、token 已被 revoke（前次 refresh 或登出時）、token 格式不合法

#### Refresh 失敗後的前端清理流程

1. 清除 `sessionStorage` 中的 `token`（與 `user`）
2. 將 `pendingRequests` queue 中所有等待的 request 以 error reject
3. 重設 `isRefreshing = false`
4. 導向 `/login`（或 `/coach/login`）

#### Interceptor 實作約束（Invariants）

| 約束 | 說明 |
|---|---|
| 同時間只允許一個 refresh | `isRefreshing` flag 確保並發 401 不重複發 refresh |
| Refresh request 不進入 refresh 流程 | 偵測 `config.url.includes('/refresh')` 跳過 |
| 每個原始 request 最多 retry 一次 | `config._retry` flag，已 retry 的請求不再觸發 refresh |
| Refresh 失敗時清除 token + 全部登出 | 清 sessionStorage → reject all pending → redirect |

---

### 3. sessionStorage 取代 localStorage

**選擇**：`sessionStorage.setItem('token', ...)` 取代 `localStorage.setItem('token', ...)`

**原因**：
- 分頁關閉後自動清除，攻擊視窗縮短
- 重整頁面（F5）sessionStorage 仍保留，token 不會消失，不需 refresh
- Refresh 機制是為了處理 token **過期**（7 天未使用）回傳 401 的情況，與頁面重整無關

**副作用**：多個分頁不共享登入狀態（在一個分頁登入，另一個分頁需重新登入）。可接受——per-tab session 對安全性更好。

---

### 4. AuthCallbackView 改用 sessionStorage

Google OAuth callback 目前直接呼叫 `localStorage.setItem('token', token)`（在 `auth.setAuth` 之前）。需同步改為 sessionStorage，否則 callback 存的位置與 axios interceptor 讀取的位置不一致。

## Risks / Trade-offs

- **多分頁體驗退化**：使用者在 Tab A 登入，Tab B 不會自動登入。對大部分使用情境可接受，但若使用者習慣多分頁操作會需要適應。
- **Refresh 端點被濫用**：攻擊者持有有效 token 時可持續呼叫 refresh。refresh 端點目前**沒有 rate limiting**，這是本次 change 的明確決策：先以較低複雜度完成 sliding refresh 流程；若後續觀察到濫用或異常流量，再於 P2 補上 rate limiting。
- **Refresh 失敗的 edge case**：網路短暫中斷時 refresh 失敗 → 強制登出。比起讓使用者停在壞狀態，主動登出更安全，可接受。
- **Queue 實作複雜度**：`pendingRequests` queue 需正確處理 promise resolve/reject，實作錯誤會導致請求永久 pending。需要仔細測試。

## Migration Plan

1. 後端：新增 `refreshMember`、`refreshProvider`、`refreshAdmin` 方法至 `AuthController.php`，新增對應路由至 `routes/api.php`
2. 前端：改寫 `axios.js` 和 `coachAxios.js` 的 interceptor（refresh-then-retry + queue）
3. 前端：更新 `auth.js`、`coachAuth.js`、`AuthCallbackView.vue` 的 storage 讀寫改為 sessionStorage
4. 驗證：手動測試 token 過期後自動 refresh、多並發 401、分頁關閉後 token 清除

**Rollback**：各項獨立可回滾。storage 改回 localStorage 不影響功能，只影響安全性。

## Open Questions

- **Refresh 端點是否需要 rate limiting？** → **決策：此 change 不加。** 持有有效 token 才能 refresh，攻擊面有限；P0 已對登入端點加 throttle。若未來觀察到濫用再加 `throttle:10,1`。
- **Admin refresh 前端邏輯是否需要？** → **決策：此 change 只建後端端點（`POST /api/admin/refresh`），不實作前端 interceptor。** Admin panel 尚未實作，前端邏輯待 admin panel 開發時一併處理。
