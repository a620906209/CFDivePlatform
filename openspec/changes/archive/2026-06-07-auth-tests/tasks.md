## 1. AuthLoginTest — 三角色登入／註冊／登出

- [x] 1.1 [整合測試] 建立 `tests/Feature/AuthLoginTest.php`，加入 `RefreshDatabase` trait 與 helper methods（`createMember`、`createProvider`、`createAdmin`）
- [x] 1.2 [整合測試] 實作 `test_member_register_success`：POST `/api/member/register` 回傳 201 + DB 有對應 member 用戶
- [x] 1.3 [整合測試] 實作 `test_member_register_duplicate_email_returns_422`：重複 email 回傳 422
- [x] 1.4 [整合測試] 實作 `test_member_login_success`：正確帳密回傳 200 + token
- [x] 1.5 [整合測試] 實作 `test_member_login_wrong_password_returns_401`：錯誤密碼回傳 401
- [x] 1.6 [整合測試] 實作 `test_member_login_inactive_account_returns_403`：is_active=false 回傳 403
- [x] 1.7 [整合測試] 實作 `test_provider_account_cannot_use_member_login`：role=provider 帳號打 member login 回傳 401（查詢以 role 過濾，跨角色視同帳號不存在）
- [x] 1.8 [整合測試] 實作 `test_member_logout_revokes_token`：登出後 token 失效（DB 確認 token 已刪除），且以同一 token 再打 `/api/member/profile` 得 401（需先 `Auth::forgetGuards()` 清除 `RequestGuard` 在同一測試內快取的已解析 user，否則會讀到快取而誤判為 200）
- [x] 1.9 [整合測試] 實作 `test_provider_logout_revokes_token`：Provider 登出後 token 失效，且以同一 token 再打 `/api/provider/profile` 得 401（同樣需 `Auth::forgetGuards()`）
- [x] 1.10 [整合測試] 實作 `test_admin_logout_revokes_token`：Admin 登出後 token 失效，且以同一 token 再打 `/api/admin/profile` 得 401（同樣需 `Auth::forgetGuards()`）
- [x] 1.12 [整合測試] 實作 `test_provider_login_success`：Provider 正確帳密回傳 200 + token
- [x] 1.13 [整合測試] 實作 `test_provider_login_inactive_account_returns_403`：is_active=false 回傳 403
- [x] 1.14 [整合測試] 實作 `test_member_account_cannot_use_provider_login`：role=member 帳號打 provider login 回傳 401（查詢以 role 過濾，跨角色視同帳號不存在）
- [x] 1.15 [整合測試] 實作 `test_admin_login_success`：Admin 正確帳密回傳 200 + token
- [x] 1.16 [整合測試] 實作 `test_member_cannot_use_admin_login`：role=member 帳號打 admin login 回傳 401（查詢以 role 過濾，跨角色視同帳號不存在）
- [x] 1.17 [整合測試] 實作 `test_provider_cannot_use_admin_login`：role=provider 帳號打 admin login 回傳 401（查詢以 role 過濾，跨角色視同帳號不存在）
- [x] 1.18 [整合測試] 實作 `test_provider_register_success`：POST `/api/provider/register` 回傳 201 + DB 有對應 provider 用戶（design.md 載明三角色註冊均須覆蓋，原 1.x 僅含 member 註冊測試，此處補齊）
- [x] 1.19 [整合測試] 實作 `test_provider_register_duplicate_email_returns_422`：重複 email 回傳 422
- [x] 1.20 [整合測試] 實作 `test_admin_register_success`：POST `/api/admin/register` 回傳 201 + DB 有對應 admin 用戶
- [x] 1.21 [整合測試] 實作 `test_admin_register_duplicate_email_returns_422`：重複 email 回傳 422

## 2. AuthLockoutTest — P2 帳號鎖定

- [x] 2.1 [整合測試] 建立 `tests/Feature/AuthLockoutTest.php`，`setUp()` 加入 `Cache::flush()` 確保測試間隔離
- [x] 2.2 [整合測試] 實作 `test_four_failures_do_not_lock_account`：同一帳號失敗 4 次均回傳 401，帳號未鎖定
- [x] 2.3 [整合測試] 實作 `test_fifth_failure_triggers_423_with_locked_until`：第 5 次失敗回傳 423，body 含 `locked_until` ISO 8601 欄位
- [x] 2.4 [整合測試] 實作 `test_locked_account_rejects_correct_password`：鎖定後送出正確密碼仍回傳 423
- [x] 2.5 [整合測試] 實作 `test_nonexistent_email_does_not_increment_counter`：不存在 email 失敗 10 次後 Cache 無計數 key，不觸發鎖定
- [x] 2.6 [整合測試] 實作 `test_successful_login_clears_failure_counter`：失敗 3 次後成功登入，再失敗 4 次仍回 401（計數從 0 重算）
- [x] 2.7 [整合測試] 實作 `test_email_case_normalization_counts_same_account`：大小寫變體 email 累計失敗到同一 key，第 5 次觸發鎖定
- [x] 2.8 [整合測試] 實作 `test_email_trim_normalization_counts_same_account`：前後空白 email 累計失敗到同一 key，第 5 次觸發鎖定
- [x] 2.9 [整合測試] 實作 `test_member_login_attempts_do_not_affect_provider_lockout_for_same_email`：僅存在 provider 帳號的 email，對 member login 失敗 4 次（不增加計數）+ 對 provider login 失敗 4 次（計數=4，未鎖定，仍可正常登入）。原情境因 `users.email` 全域 unique 無法同時擁有 member+provider 帳號，已改寫並同步更新 spec.md
- [x] 2.10 [整合測試] 實作 `test_account_can_login_after_lockout_entry_removed`：鎖定後 `Cache::forget()` 移除 entry，帳號恢復可登入（回傳 200）
- [x] 2.11 [整合測試] 實作 `test_locked_until_comes_from_companion_cache_key`：423 response 的 `locked_until` 等於 `login_expires_at:member:{email}` cache key 的值
- [x] 2.12 [整合測試] 實作 `test_repeated_failures_do_not_extend_lockout_window`：對應 spec.md「Fixed Window — 失敗不延長 TTL」情境（spec 已定義但原 tasks 未列出，此處補上）。第 1 次失敗寫入 `login_expires_at` 後，第 2~4 次失敗不應更動該值（驗證 `recordLoginFailure` 只在計數 key 不存在時才寫入 companion key，不隨後續失敗重設視窗）

## 3. AuthOAuthTest — P2 OAuth State 驗證

- [x] 3.1 [整合測試] 建立 `tests/Feature/AuthOAuthTest.php`，加入 `RefreshDatabase` trait；引入 `Laravel\Socialite\Facades\Socialite`
- [x] 3.2 [整合測試] 實作 `test_oauth_callback_without_state_redirects_to_error`：不帶 state 參數、session 無 `oauth_state`，斷言 redirect 含 `error=oauth_failed`，且 Socialite `user()` 不被呼叫（使用 `shouldNotReceive`）
- [x] 3.3 [整合測試] 實作 `test_oauth_callback_with_wrong_state_redirects_to_error`：session 設 `oauth_state=correct`，帶 `state=wrong`，斷言 redirect 含 `error=oauth_failed`，且 Socialite `user()` 不被呼叫
- [x] 3.4 [整合測試] 實作 `test_oauth_callback_with_correct_state_completes_login`：session 設 `oauth_state=abc`，帶 `state=abc`，Socialite mock 回傳假 Google user，斷言 redirect URL 含 `#token=`
- [x] 3.5 [整合測試] 實作 `test_oauth_state_is_consumed_after_successful_callback`：正確 state 完成一次登入後，再以相同 state 呼叫 callback，斷言第二次 redirect 含 `error=oauth_failed`（state 一次性消耗）

## 4. 驗收確認

- [x] 4.1 [整合測試] 執行 `php artisan test --filter AuthLoginTest` 全數通過，無 skip（19 passed / 60 assertions）
- [x] 4.2 [整合測試] 執行 `php artisan test --filter AuthLockoutTest` 全數通過，無 skip（11 passed / 44 assertions）
- [x] 4.3 [整合測試] 執行 `php artisan test --filter AuthOAuthTest` 全數通過，無 skip（4 passed / 12 assertions）
- [x] 4.4 [整合測試] 在容器內執行 `docker exec cfdive-app php artisan test`（host PHP 缺 GD 擴充套件，須在容器內跑才能涵蓋 `CourseImageTest`），結果：**98 passed / 256 assertions，全數通過，無回歸**。本次新增的 Auth 測試均已通過：AuthLoginTest 19、AuthLockoutTest 11、AuthOAuthTest 4（合計 34，OAuth 部分已於 4.3 單獨驗證）

> **過程中排除的兩類既存失敗（均已解決，非本次改動引入）**：
> - `CourseImageTest`（11 個）：原本在 host PHP 跑會因缺 GD 擴充套件報 `LogicException: GD extension is not installed.`；確認專案 `Dockerfile` 已內建 GD，改用 `docker exec cfdive-app php artisan test` 在容器內執行即全數通過 —— 純屬執行環境差異，非程式或測試邏輯問題，往後請統一在容器內跑測試
> - `AuthRateLimitTest`（2 個）：`member/provider login exceeds limit returns 429` 原本預期 429 實得 401。根因是過期測試——commit `2a0e9255`（P2 帳號鎖定上線時）刻意將 member/provider 的 IP throttle 從 `5,1` 調整為 `10,1`（避免 IP throttle 搶在帳號鎖定前觸發），但測試的迴圈次數與註解仍停留在舊版 `5,1`。已同步修正測試的迴圈次數（5→10）與區塊註解，使其對齊現行 `throttle:10,1` 設定，修正後全數通過
