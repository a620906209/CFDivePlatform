# CFDivePlatform 專案審視：高／中優先待辦

> 審視日期：2026-10-14
> 範圍：後端授權、部署設定、程式結構、資料一致性。
> 低優先項目未列入本文（分頁、範本殘留檔、CHANGELOG 等），需要時另行整理。
> 搭配閱讀：《2026-06-11-spec-implementation-audit.md》。
> 實作原則：以下每一項只要涉及行為變更，都必須先走 OpenSpec（`/opsx:propose`），AC 對應測試後才可實作。
> 更新紀錄：2026-10-14 新增 M7、M8（撰寫 `hardener` skill 時，閱讀預約相關程式碼發現；以程式碼閱讀判斷，**尚未重現驗證**）。

---

## 總覽

| # | 優先 | 項目 | 是否走 OpenSpec | 建議 change 名稱 |
|---|------|------|----------------|------------------|
| H1 | 🔴 高 | member／provider 路由缺角色 middleware | 是 | `add-role-middleware` |
| H2 | 🔴 高 | 正式環境 `APP_DEBUG` 與預設值風險 | 否（設定確認）+ 視情況改 `.env.example` | — |
| M1 | 🟡 中 | AuthController 過大、無 FormRequest／Service 層 | 是 | `refactor-auth-controller` |
| M2 | 🟡 中 | 預約名額邏輯重複 | 是 | `extract-booking-service` |
| M3 | 🟡 中（已下修，見內文） | 通知派送的例外處理與失敗可觀測性 | 視範圍而定 | `harden-notification-dispatch` |
| M4 | 🟡 中 | 前端 token 儲存不一致（admin 用 localStorage） | 是 | `unify-frontend-token-storage` |
| M5 | 🟡 中 | CORS 在正式環境仍允許 localhost | 是（小） | `restrict-cors-by-env` |
| M6 | 🟡 中 | CI 使用 SQLite，鎖定／超賣測試可能假綠 | 是 | `ci-mysql-test-job` |
| M7 | 🟡 中 | 預約流程 `catch (RuntimeException)` 把例外訊息直接回給使用者 | 是（小） | `fix-booking-exception-handling` |
| M8 | 🟡 中（影響資料正確性，需並發才觸發） | 預約取消／確認的狀態在鎖之前讀取，並發下名額可能重複增減 | 是 | `fix-booking-state-race`（建議併入 M2） |

建議順序：H1 → H2 → M7 → M6 → M5 → M8 與 M2 合併處理 → M1 → M4 → M3（M3 已下修，可視驗證結果延後或關閉）。
理由：H1 是安全漏洞；M7 範圍小、可立即降低資訊洩漏；M6 先建立可信的測試基礎，後續 M8、M2、M1 才有安全網；M8 的修法（鎖內重讀狀態）本來就是 M2 抽出 `BookingService` 時要統一的邏輯，建議同一個 change 處理。

---

## H1　member／provider 路由缺角色 middleware

**現況**
`routes/api.php` 中 `member` 與 `provider` 兩個群組只有 `auth:sanctum`，沒有角色檢查；只有 `admin` 群組有 `EnsureAdmin`。

**已確認的影響**
- 任何已登入的 member 或 admin 可呼叫 `POST /api/provider/offers`、`/provider/schedules`、`/provider/offers/{id}/images`，`provider_id` 會被設成呼叫者 id，產生非教練帳號的課程。
- 任何 provider 或 admin 可呼叫 `POST /api/member/bookings` 建立預約。
- 目前只有 `ReviewController`、refresh 端點自己檢查角色，其餘端點無防護。

**待辦**
- [ ] 新增 `EnsureMember`、`EnsureProvider` middleware（仿 `app/Http/Middleware/EnsureAdmin.php`），並在 `bootstrap/app.php` 註冊 alias
- [ ] 套用到 `member`、`provider` 路由群組
- [ ] 通知、即時訊息這類「member + provider 共用」群組維持現狀，但需確認 admin 是否應被排除
- [ ] 新增 Feature 測試（仿 `tests/Feature/AdminEndpointAuthTest.php`）：每個角色 × 每組路由，期望 403
- [ ] 更新 spec：`provider-auth`、`admin-auth`；視需要新增 `role-authorization` spec
- [ ] 更新 Swagger 註解（`app/Docs/*`）的 403 回應

**驗收條件（草案，需轉為 spec Scenario）**
1. member 呼叫任一 `/api/provider/*` 回 403
2. provider 呼叫任一 `/api/member/*` 回 403
3. admin 呼叫 `/api/member/*`、`/api/provider/*` 回 403
4. 既有合法角色的行為不變（既有測試全綠）

**安全確認（CLAUDE.md 必答）**
- 權限檢查層級：middleware（路由層），Controller 內既有所有權檢查保留
- 未授權觸發可能性：修正後不可

---

## H2　正式環境 `APP_DEBUG` 與預設值

**現況**
- 本機 `.env`：`APP_ENV=local`、`APP_DEBUG=true`
- `.env.example`：`APP_DEBUG=true`、`APP_ENV=local`
- `docker-compose.yml` 以 `env_file: .env` 注入容器

**風險**
若 VPS 上 `.env` 沿用相同值，錯誤頁會洩漏堆疊追蹤、環境變數與 SQL。

**待辦**
- [ ] **需要你確認**：登入 VPS 檢查實際 `.env` 的 `APP_DEBUG`、`APP_ENV`（我無法從此環境確認）
- [ ] 確認 `config:cache` 後的實際值：`docker compose exec app php artisan tinker --execute="dump(config('app.debug'));"`
- [ ] `.env.example` 加註解說明正式環境必須 `APP_DEBUG=false`
- [ ] 評估是否在 deploy.yml 加入啟動檢查：`APP_ENV=production` 且 `APP_DEBUG` 為 false，否則部署失敗
- [ ] 本機 `.env` 中的 DB 密碼為弱密碼（`cfdivepass`），確認正式環境未使用

**備註**
這項大多是設定確認，不一定需要 OpenSpec change；若加入部署檢查則屬 `env-cloud-annotations` spec 範圍。

---

## M1　AuthController 過大，缺 FormRequest 與 Service 層

**現況**
- `app/Http/Controllers/API/AuthController.php` 1136 行
- 三種角色的 register／login／logout／profile／change-password／refresh 大量複製
- `app/Http/Requests`、`app/Services` 目錄不存在，驗證與業務邏輯都在 Controller
- 已有 `commonLogout`、`commonChangePassword` 等雛形，但 `refreshMember`／`refreshProvider`／`refreshAdmin` 幾乎相同

**違反的 CLAUDE.md 規則**
單一職責、Function 超過 20 行需說明、Controller 只處理資源、參數過多應封裝 DTO。

**待辦**
- [ ] 先補足現有行為的測試（`AuthLoginTest`、`AuthLockoutTest`、`TokenRefreshTest` 已有基礎），重構前先確認覆蓋率
- [ ] 拆分：依角色或依功能拆成多個 Controller（例如 `MemberAuthController`），共用邏輯抽成 `AuthService`
- [ ] 引入 FormRequest（`RegisterMemberRequest` 等）
- [ ] 合併三個 refresh 方法
- [ ] 重構期間 API 路徑、回應格式、Swagger 輸出**不得改變**
- [ ] 重構完成後 `app/Docs/AuthApiDoc.php`（1261 行）註解位置需一併檢視

**風險**
改動面大，務必在 M6（CI MySQL）之後進行，並分多個小 PR。

---

## M2　預約名額邏輯重複

**現況**
「取消後遞減 `current_participants`，若由 Full 變回 Open」的邏輯同時出現在：
- `MemberBookingController::destroy`
- `ProviderBookingController::cancel`
- `AdminBookingController`（需確認）

同時 `MemberBookingController::store` 約 70 行，包含驗證、鎖定、建立、通知。

**待辦**
- [ ] 抽出 `BookingService`（或 `CourseSchedule` 的 `releaseSpots()` 方法）集中名額增減與狀態轉換
- [ ] 確認所有取消路徑使用同一入口
- [ ] 保留 `BookingOversellTest`、`BookingLifecycleTest` 全綠
- [ ] 補單元測試：名額釋放、Full → Open 的邊界

---

## M3　通知派送的例外處理與失敗可觀測性

> 2026-10-14 更新：初版誤判為「通知同步發送」。實際檢查後，9 個 Notification 中 8 個已實作 `ShouldQueue` 並設有 `public int $tries = 3`，`cfdive-queue` 容器也已在跑。原本的風險大幅降低，本項由「中」下修，並縮小範圍。

**現況**
- `BookingCreated`、`BookingConfirmed`、`BookingRejected`、`BookingCancelled`、`BookingCompleted`、`ProviderVerificationApproved`、`ProviderVerificationRejected`、`ReviewReceived`：皆為 `ShouldQueue`，`tries = 3`
- `NewBookingMessageNotification`：**未**實作 `ShouldQueue`，檔案內註解說明是刻意同步，讓後續的 `NotificationCreated` broadcast 能立刻讓前端拉到正確的未讀數
- `MemberBookingController`、`ProviderBookingController` 仍以 `try { notify() } catch (\Throwable) { Log::error }` 包住派送

**仍存在的風險**
- try/catch 現在只會攔到「派送入列」階段的失敗（例如 Redis 無法連線），這可能是刻意讓預約流程不因通知失敗而中斷；但例外只進 log，不會送 Sentry，也沒有補送機制
- 8 個 Notification 都只設了 `tries`，沒有 `backoff`，重試會立刻連續發生，對暫時性故障（SMTP、Redis 抖動）幫助有限
- 沒有 `failed()` 處理：重試用完後只留在 `failed_jobs`，是否會送到 Sentry **我不確定，需要驗證**
- `NewBookingMessageNotification` 同步發送，聊天頻繁時會增加請求延遲（且有 Redis broadcast 依賴）

**待辦**
- [ ] 先驗證：讓一個 Notification job 失敗，確認 Sentry 是否收到；確認 `failed_jobs` 是否有人監看
- [ ] 為 8 個 queued Notification 補上 `backoff`（例如 `[10, 60, 300]`）
- [ ] 將 Controller 內的 `Log::error` 改為 `report($e)`，讓 Sentry 收到派送失敗，並保留「不中斷主流程」的行為
- [ ] 評估 `NewBookingMessageNotification` 是否能在不破壞未讀數即時性的前提下改為 queued（可能需調整 broadcast 時序），不可行則在 spec 註明為刻意設計
- [ ] 更新 `NotificationTriggerTest` 涵蓋失敗情境
- [ ] 如行為有變更，更新 spec：`notification-core`、`notification-triggers`、`notification-email`

**備註**
若驗證後發現 Sentry 已能收到失敗，且 Redis 故障非實際風險，本項可降為低優先或直接關閉。

---

## M4　前端 token 儲存不一致

**現況**
- member：`sessionStorage`（`frontend/src/api/axios.js`）
- admin：`localStorage`（`frontend/src/api/adminAxios.js`）
- `openspec/config.yaml` 仍寫 localStorage（見另一份 prompt 文件）

**風險**
管理員權限最高，卻用持久儲存，XSS 時外洩影響更大。好消息是前端目前沒有 `v-html` 或 `innerHTML`。

**待辦**
- [ ] 確認 coach 端（`coachAxios.js`）實際使用哪種儲存
- [ ] 決定統一策略（至少 admin 改為 `sessionStorage`）
- [ ] 評估是否改用 HttpOnly Cookie（需搭配 Sanctum SPA 模式，改動大，另案評估）
- [ ] 更新 `member-portal-ui`、`coach-portal-ui`、`admin-panel-ui` spec 中關於儲存的描述

---

## M5　CORS 在正式環境仍允許 localhost

**現況**
`config/cors.php` 的 `allowed_origins` 固定包含 `http://localhost:5173` 與 `http://127.0.0.1:5173`。

**待辦**
- [ ] 改為依 `APP_ENV` 決定：local 才加入 localhost，production 僅允許 `FRONTEND_URL`
- [ ] 補一個 Feature 測試驗證 production 設定下的 CORS 標頭
- [ ] 同步檢查 Reverb（WebSocket）的 allowed origins 設定

---

## M6　CI 使用 SQLite，鎖定／超賣測試可能假綠

**現況**
- `.gitea/workflows/test.yml` 以 `cp .env.example .env` 執行，`.env.example` 為 `DB_CONNECTION=sqlite`
- `BookingOversellTest` 等測試依賴 `lockForUpdate()`，SQLite 不支援行鎖，該方法在 SQLite 上實質無效
- 另外 SQLite 與 MySQL 在 enum、JSON、時間函式上行為不同

**風險**
CI 綠燈不代表正式環境（MySQL）行為正確，尤其是併發情境。

**待辦**
- [ ] 在 CI 增加 MySQL service，至少讓預約、超賣相關測試在 MySQL 上跑一次
- [ ] 檢查 `phpunit.xml` 的 DB 設定，確認 CI 與本機一致
- [ ] 評估 `BookingOversellTest` 是否真的測到併發（單程序測試無法驗證行鎖，可能需要多程序測試或接受其限制並在文件註明）
- [ ] `deploy.yml` 與 `test.yml` 目前互相獨立；評估是否讓部署依賴測試通過
- [ ] 更新 spec：`feature-test-coverage` 或新增 `ci-pipeline` spec

---

## M7　預約流程 `catch (RuntimeException)` 把例外訊息直接回給使用者

> 來源：撰寫 `hardener` skill 時閱讀程式碼發現。以下為程式碼閱讀結果，尚未實際觸發驗證。

**現況**
- `app/Http/Controllers/API/MemberBookingController.php:92`（`store`）與 `app/Http/Controllers/API/ProviderBookingController.php:57`（`confirm`）都是：
  `catch (\RuntimeException $e) { return response()->json(['status' => false, 'message' => $e->getMessage()], 422); }`
- 閉包內刻意拋出的業務訊息（「名額不足」「您已預約此時段」）依賴這個 catch 轉成 422

**風險**
- `Illuminate\Database\QueryException` 繼承 `PDOException`，而 `PDOException` 是 `RuntimeException` 的子類別。交易內的資料庫錯誤（死結、逾時、約束違反、連線中斷）也會被這個 catch 接住
- 結果是資料庫錯誤訊息（可能含 SQL 與資料表結構）被當成 422 回給使用者，屬於資訊洩漏
- 這類錯誤被當成「可預期的業務失敗」處理，不會經過 Laravel 例外處理器，**不會被 Sentry 收到**，營運端看不到
- 狀態碼為 422（客戶端錯誤），實際是伺服器端故障，監控與前端錯誤處理都會誤判

**待辦**
- [ ] 定義單一窄型的業務例外（例如 `BookingRuleViolationException`），交易閉包內改拋它；Controller 只 catch 該型別，其他例外讓它往外傳播
- [ ] 回應內容只使用自己拋出的、已知安全的訊息；不再回傳任意 `$e->getMessage()`
- [ ] 確認資料庫類例外會被 Sentry 收到、回應為 500 且訊息通用
- [ ] 檢查其他 Controller 是否有相同寫法（目前 grep 僅發現這兩處 `RuntimeException`）
- [ ] 補測試：讓交易內拋出 `QueryException`（例如以 `DB::listen` 或 mock 模擬），斷言回應為 500 且 body 不含 SQL 與資料表名稱；業務失敗（名額不足、重複預約）仍回 422 與原訊息
- [ ] 更新 spec：`booking-lifecycle`（錯誤處理行為）；注意該 spec 有一條 Requirement 超過 500 字元，修改時可順便拆分

**驗收條件（草案，需轉為 spec Scenario）**
1. 名額不足、重複預約仍回 422 與既有訊息
2. 交易內發生資料庫例外時回 500，回應不含 SQL、資料表或欄位資訊
3. 上述資料庫例外會被記錄並送到 Sentry

**安全確認（CLAUDE.md 必答）**
- 輸入驗證位置：既有 `$request->validate()`，不變
- 資料庫存取：Eloquent，不變
- 權限：不變；本項只改例外處理與回應內容

---

## M8　預約取消／確認的狀態在鎖之前讀取，並發下名額可能重複增減

> 來源：撰寫 `hardener` skill 時閱讀程式碼發現。以下為程式碼閱讀判斷，**尚未重現驗證**；而且 `phpunit.xml` 使用 SQLite，無法在現有測試中重現行鎖行為（見 M6）。

**現況**（皆為「交易外讀狀態、交易內才鎖 schedule」的模式）
- `MemberBookingController::destroy`：在交易外以 `$booking->status` 判斷可否取消，並記下 `$wasConfirmed`；進入交易後才 `lockForUpdate` 鎖 schedule，`booking` 本身沒有鎖，且仍使用鎖之前讀到的舊狀態
- `ProviderBookingController::cancel`、`confirm`、`reject`、`complete`：同樣先用 `canTransitionTo()` 判斷（交易外、無鎖），交易內才鎖 schedule
- `AdminBookingController::complete`、排程 `app:complete-finished-bookings`、`app:expire-pending-bookings`：以交易外或批次方式直接改狀態，沒有與其他寫入者序列化

**可能的後果（需並發才觸發）**
- 同一筆 `confirmed` 預約被會員與教練（或同一方重複點擊）**同時取消**：兩個請求都通過檢查，各自扣減一次 `current_participants`，名額被**重複釋放**，後續可能超賣；`current_participants` 為 `unsignedInteger`，若剛好扣到負數，在 MySQL 嚴格模式下會報錯變成 500
- 同一筆 `pending` 預約被**同時確認兩次**：各自增加一次 `current_participants`，名額被重複占用
- `confirm` 與 `reject`／`expire` 同時發生：預約可能最後是 `confirmed` 卻已被標成 `expired`，或名額已增加但狀態被覆蓋
- 既有的 `BookingOversellTest` 只涵蓋單程序情境，無法抓到這類問題

**待辦**
- [ ] 先驗證：以兩個程序或 MySQL 上的並發請求重現「同一預約重複取消」與「同一預約重複確認」，確認名額是否真的重複增減（驗證環境需為 MySQL，見 M6）
- [ ] 狀態轉換統一在交易內：先鎖住預約本身（`Booking::lockForUpdate()->find(...)`）或先鎖 schedule 再重讀預約，**鎖之後**再以最新狀態判斷 `canTransitionTo()` 與 `$wasConfirmed`
- [ ] 以條件式更新輔助：例如 `where('status', 'confirmed')->update(...)` 並檢查受影響列數，為 0 代表別人已先處理
- [ ] 取得鎖的順序需一致（例如一律先 schedule 後 booking），避免新增鎖後產生死結
- [ ] 統一抽到單一入口（併入 M2 的 `BookingService`），所有取消與確認路徑共用
- [ ] 評估排程指令（`complete-finished-bookings`、`expire-pending-bookings`）是否也需要用同樣的條件式更新，避免與使用者操作互相覆蓋
- [ ] 補測試：雖然 SQLite 無法驗證行鎖，仍應補「同一預約連續取消兩次，第二次回 422 且名額只釋放一次」等可確定性的測試；真正的並發驗證放在 MySQL CI（M6）或人工驗證
- [ ] 更新 spec：`booking-lifecycle`（狀態轉換與名額一致性）

**驗收條件（草案，需轉為 spec Scenario）**
1. 同一筆預約重複取消，名額只釋放一次，第二次請求回 422
2. 同一筆預約重複確認，名額只占用一次，第二次請求回 422
3. 狀態轉換的判斷使用鎖之後的最新狀態
4. 現有 `BookingLifecycleTest`、`BookingOversellTest` 全綠

**安全確認（CLAUDE.md 必答）**
- 資料庫存取：Eloquent，不使用 raw SQL
- 是否需要悲觀鎖：是；需序列化的資源是「單一預約」與其所屬的 schedule
- 權限檢查：不變，各端點既有的擁有權檢查保留

**備註**
- 此問題的完整驗證依賴 M6（MySQL CI）。在 M6 完成之前，只能以程式碼審查與可確定性測試降低風險，不能宣稱已驗證併發安全

---

## 其他提醒

- Git remote `vps` 的 URL 內含明文 Gitea token（存在 `.git/config`，未進版控）。建議輪換 token，改用 credential helper 或 SSH。
- 本文件為待辦清單，不代表已排入開發；每項啟動前仍需依 CLAUDE.md 完成「Task 執行前」檢查。
