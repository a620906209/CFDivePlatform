# Design — 2026-06-11 稽核修補

## D1：admin register 封鎖方式——artisan command 而非 middleware 防護

候選方案：
- (a) 移除公開路由，改 `php artisan app:create-admin`（採用）
- (b) 端點移入 `auth:sanctum + admin` middleware group（僅既有 admin 可開新 admin）

選 (a) 的理由：(b) 有「第一位管理員」雞生蛋問題，且保留 HTTP 入口就保留攻擊面；管理員建立頻率極低，不值得為其維護一個網路端點。command 密碼門檻設 min:8（高於一般使用者 min:6），因管理權限影響全平台。Seeder 直接以 Model 建立 admin，不受影響。

## D2：rate-limit 漂移以實作（10/min）為基準

測試已在 commit 0dabc4e 有意配合實作改為 10 次，且 P2 帳號鎖定上線後已涵蓋暴力破解防護（per-email 計數），IP-based throttle 的角色退為防濫用，放寬至 10/min 可容納共享 IP（公司/學校 NAT）場景。故更新規格而非改回實作。Admin 維持 3/min 不變。

## D3：is_verified 最小語意——列表過濾而非禁止上架

候選方案：
- (a) 未驗證教練課程從公開 index/show 排除（採用）
- (b) 未驗證教練 `store()` 回 403（禁止建立課程）

選 (a) 的理由：對教練的破壞較小（仍可登入、預先準備課程內容，通過驗證即曝光），可逆性高；(b) 會讓新教練在審核期間完全無事可做。實作為 `DivingOffer::scopeVisibleToPublic`（單一來源，index/show 共用）：`provider_id` 為 null（平台自有資料）不受限，否則要求 `provider.providerProfile.is_verified = true`（無 profile 視同未驗證）。

快取一致性：公開列表有 180 秒 Redis tag 快取，`toggleProviderVerified` 後必須 `Cache::tags(['diving_offers'])->flush()`，否則切換最長延遲 3 分鐘生效。測試以「先打列表進快取 → toggle → 再打列表」驗證立即生效。

## D4：預約測試的保護目標（Rule 9——測試編碼 WHY）

- **狀態機**：終態（completed/rejected/expired/cancelled）不可再轉移——保護名額帳務與評價資格（只有 completed 能評價）
- **防超賣**：pending 不佔名額是刻意設計（教練未承諾前不鎖位），防線在 confirm 時 `lockForUpdate` 二次驗證；不變式為「confirmed 總人數 ≤ 容量」
- **Scheduler**：48h 過期與日期完成的邊界——過早 expire 砍掉教練來得及確認的單、過早 complete 讓未上課預約取得評價資格
- **聊天授權**：HTTP 端點與 presence channel 雙防線都必須擋非參與方與非 confirmed 狀態
- **Admin 邊界**：非 admin 一律 403；Admin 也不可繞過狀態機（rejected 不可 complete）

## D5：分支基底

`fix/audit-remediation` 基底為 `test/auth-tests-coverage`（而非 master），因 PR #27 的 auth 測試實際尚未併入 master，而本次修補需在其測試之上修改 `AuthLoginTest`。merge 本 change 的 PR #28 會一併帶入 PR #27 內容。
