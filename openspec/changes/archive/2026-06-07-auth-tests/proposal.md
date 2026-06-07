## Why

Auth 模組（登入/註冊/登出、P2 帳號鎖定、P2 OAuth state 驗證）已實作並上線，但目前完全沒有測試覆蓋登入主流程與安全邏輯，任何回歸都會靜默失效。

## What Changes

- 新增 `tests/Feature/AuthLoginTest.php`：覆蓋 member / provider / admin 三角色的登入、註冊、登出、非活躍帳號拒絕、角色隔離等場景
- 新增 `tests/Feature/AuthLockoutTest.php`：覆蓋 P2 帳號鎖定的全部 scenario（Fixed Window 計數、第 5 次觸發、鎖定期間拒絕、成功後清除、Email 正規化、role 隔離）
- 新增 `tests/Feature/AuthOAuthTest.php`：覆蓋 P2 OAuth state 驗證（state 不符回 `/login?error=oauth_failed`、state 正確完成登入）

## Capabilities

### New Capabilities

- `auth-test-coverage`：定義 Auth 模組所有必要測試場景的規格，作為實作三支測試檔案的依據

### Modified Capabilities

（無，所有需求已定義於既有 spec；本 change 只新增驗證覆蓋）

## Impact

- 僅新增測試檔案，不修改任何 production 程式碼
- 依賴：`phpunit.xml` 已設定 `DB_CONNECTION=sqlite`、`DB_DATABASE=:memory:`，可直接使用
- 影響範圍：`tests/Feature/`（新增三個檔案）
