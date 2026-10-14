---
name: verify-and-report
description: 完成一個 task 或整個 change 的實作後，執行測試與格式檢查，並依 CLAUDE.md 要求輸出固定格式的完工報告（spec 同步狀態、偏差點、AC 測試涵蓋、重複邏輯、命名一致性）。當使用者說「驗證」「檢查一下」「幫我出完工報告」，或 openspec-apply-change 全部 task 完成時使用。
argument-hint: [change 名稱，可省略]
---

## Instructions

1. **確認範圍**
   ```
   git branch --show-current
   git diff master...HEAD --stat
   git status --short
   ```
   - 若在 `master` 上，停止並提醒使用者應該在功能分支上
   - 若有 change 名稱（或可從分支與 `openspec list` 推斷），讀取 `openspec/changes/<name>/` 的 proposal、specs、design、tasks

2. **執行驗證（失敗就如實回報，不要隱藏或重跑到過為止）**
   - `php artisan test`
   - 對本次變更的 PHP 檔執行 `./vendor/bin/pint --test <檔案>`（只檢查變更的檔案，不要全專案格式化）
   - `openspec validate --specs`
   - 若有前端變更：在 `frontend/` 執行 `npm run build` 確認可建置
   - 注意：`phpunit.xml` 強制使用 SQLite `:memory:`，`lockForUpdate` 等行鎖行為無法在此驗證，報告中不可宣稱「已驗證併發安全」

3. **AC 與測試涵蓋**
   - 逐條列出 spec 的 Scenario，指出對應的測試檔與方法（用 `rg` 找實際存在的測試，不要憑印象）
   - 找不到對應測試者標示「未涵蓋」並說明原因

4. **Spec 同步檢查**
   - 本次改動是否改變了既有 spec 描述的行為？列出需要更新的 `openspec/specs/<capability>/spec.md`
   - 實作與 spec 是否有偏差？列出偏差點，詢問是否更新 spec，不要自行改 spec
   - 若為新增或修改 API 端點，Swagger（`app/Docs/`）是否同步更新

5. **安全確認（僅在觸及觸發條件時）**
   若變更涉及外部輸入、資料庫讀寫、身份驗證/授權、檔案上傳、外部 API，依 CLAUDE.md「安全確認問題」逐項回答：驗證在哪一層、是否使用 Eloquent/Query Builder、權限檢查在哪一層、是否有 N+1、是否有 XSS 風險。
   新增的 member / provider 路由要特別確認角色檢查（CLAUDE.md「授權規範」）。

6. **維護性檢查**
   - 重複邏輯：本次新增的邏輯是否已存在於 codebase？用 `rg` 搜尋相似方法再判斷
   - 命名一致性：變數、函式、API endpoint 是否與現有命名一致

7. **輸出報告**，使用下列格式，全程使用繁體中文。

## 報告格式

```
## 完工報告：<change 或 task 名稱>

### 驗證結果
| 項目 | 結果 | 備註 |
|------|------|------|
| php artisan test | ✅ / ❌ N 個失敗 | |
| pint | ✅ / ❌ | |
| openspec validate | ✅ / ❌ | |

### AC 測試涵蓋
| Scenario | 對應測試 | 狀態 |
|----------|----------|------|

### Spec 同步狀態
- 與 spec 一致 / 偏差點：...
- 需更新的 spec 文件：...

### 安全確認（若適用）
...

### 維護性
- 重複邏輯：...
- 命名一致性：...

### 未解決事項 / 我不確定的地方
...
```

## Notes

- 不要為了讓測試通過而修改測試的預期結果；測試失敗要回報原因。
- 本 skill 只負責檢查與回報，不修改程式、不 commit、不 push。要提交請接著使用 `finish-and-pr`。
- 不要在輸出中顯示 remote URL（可能含有憑證）。
