# CLAUDE.MD

## 語言設定

所有回應、說明、註解一律使用**繁體中文**。
程式碼內的變數名稱、函式名稱維持英文。

---

## 技術棧預設

- **後端**：PHP 8.x / Laravel 11.44.7（主力）
- **前端**：Vue 3 / React
- **DB**：MySQL 8.0（port 3306，透過 Docker 容器運行）
- **IDE**：Windsurf

---

## 工具分工

- **Claude Code**：主要的實作工具（OpenSpec 流程、寫程式、提交）。專案的 skill 與指令放在 `.claude/`
- **Pi agent**：目前用於審查（review）與環境、工具調整；不負責主要實作
- `CLAUDE.md` 是兩個工具共同讀取的規則來源；`.claude/skills/`、`.claude/commands/` 僅 Claude Code 讀取，Pi 不會載入
- 因此跨工具都必須遵守的規則一律寫在本檔，不要只寫在 skill 裡

---

## 禁止行為

- 禁止在未確認 spec 對應關係的情況下開始實作
- 禁止使用 raw SQL query（除非有明確說明理由）
- 禁止在前端直接使用 `innerHTML` 渲染未經處理的使用者輸入
- 禁止在 Acceptance Criteria 未對應測試任務的情況下開始實作
- 禁止直接 push 或 force push `master`（push master 會觸發自動部署，一律走 PR）
- 禁止在未檢查呼叫者角色的情況下新增 member / provider 路由（見「授權規範」）

---

## 安全強制規則（涉及以下情境時必須執行）

**觸發條件**：task 涉及以下任一項時，實作前必須先回答所有問題，未回答完畢不得開始實作。

### 觸發條件清單

- 接受外部輸入（表單、API 參數、URL query）
- 資料庫讀寫操作
- 身份驗證 / 授權邏輯
- 檔案上傳或處理
- 呼叫外部 API / Webhook

### 安全確認問題（觸發後必答）

**輸入驗證**
- 外部輸入有沒有經過 Validation？在哪一層？
- 有沒有直接拼接進 SQL 或 shell 指令？

**資料庫**
- 是否使用 Query Builder 或 Eloquent ORM？（禁止使用 raw query，除非有明確理由）
- 有沒有 N+1 查詢風險？

**權限**
- 這個操作需要什麼權限？權限檢查在哪一層執行？
- 有沒有可能被未授權使用者觸發？

**前端（Vue/React）**
- 有沒有直接將使用者輸入渲染進 DOM？（XSS 風險）
- API 呼叫有沒有帶正確的 Auth Header？

### 機敏資訊管理
- 密碼、API Key 等一律放 `.env`，不進版本控制
- 設定檔使用 `${VAR}` 引用環境變數，實際值由 `.env` 注入

---
## 命名原則

- Function 名必須是動詞片語，且能完整描述其行為（`getUserById` 而非 `getUser`）
- Boolean 變數 / 屬性以 `is` / `has` / `can` 開頭（`isActive`、`hasPermission`）
- 禁止使用縮寫（`usr`、`btn`、`tmp`），除非是業界通用（`id`、`url`、`api`）
- Class 名是名詞，不帶 `Manager` / `Helper` / `Utils` 等空洞字尾——若需要，代表職責未釐清

---

## Function 設計原則

- 單一職責：一個 function 只做一件事，能用一句話描述
- 長度警戒線：超過 20 行必須說明理由，或主動提示拆分
- 參數上限：超過 3 個參數時，考慮封裝為 DTO（Data Transfer Object）

---

## 註解原則

- 好的命名取代大部分的註解——若需要大量解釋，先重構命名
- 禁止「描述行為」的註解（`// 遍歷陣列`），只允許「解釋為什麼」的註解（`// 此處使用 X 而非 Y，因為...`）
- TODO 必須帶 issue 連結或日期，裸露的 TODO 視為技術債

---

## SOLID 原則

| 原則 | 實踐方式 |
|------|---------|
| **S** 單一職責 | 每個 Controller 只處理一個資源；業務邏輯抽到 Service |
| **O** 開放封閉 | 新增功能透過擴充（新 Class / 新方法），不修改既有邏輯 |
| **L** 里氏替換 | 子類別可完全替換父類別，不破壞預期行為 |
| **I** 介面隔離 | 不強迫實作用不到的方法；介面依使用情境拆細 |
| **D** 依賴反轉 | 依賴抽象（介面 / Contract）而非具體實作；透過 Laravel DI Container 注入 |

### OOP 規範
- **封裝**：內部狀態不直接暴露，透過方法存取
- **繼承**：優先組合（Trait / Composition）而非繼承；繼承層數不超過 2 層
- **多型**：相同介面的不同實作透過 binding 切換，不用 `if/switch` 判斷型別

---

## OpenSpec 工作流規則

### Task 執行前

每次執行 task 前，先確認：

1. 這個 task 對應哪一條 spec？（必須能指向具體文件與段落）
2. Acceptance Criteria 是否已定義且可測量？每一條是否已對應至少一項測試任務（單元測試或 Feature 測試）？未對應者禁止進入實作階段。
3. 這個 task 完成後，哪些 spec 文件需要同步更新？

### Task 執行後

完成任何 task 後，主動告知：

- 實作結果是否與 spec 描述一致
- 如有偏差，列出偏差點並詢問是否更新 spec
- **必須告知 spec 同步狀態**：這個改動有沒有影響現有 spec 描述的行為？需要更新哪個文件？
- 測試是否涵蓋本次變更的 Acceptance Criteria？未涵蓋者需說明原因

---

## 維護性規則（所有 task 適用，非強制阻擋，但必須提醒）

每次 task 完成後，主動告知以下項目的狀態：

- **重複邏輯**：這段邏輯是否已存在於 codebase 中，應該抽共用？
- **命名一致性**：變數、函式、API endpoint 命名是否與現有規範一致？

---

## 專案慣例

- API 回應格式：成功 `{ status: true, message?, data }`；失敗 `{ status: false, message, errors? }`
- Controller 位於 `app/Http/Controllers/API/`；路由集中於 `routes/api.php`
- Swagger 註解集中於 `app/Docs/`，新增或修改端點時同步更新
- OpenSpec 的專案背景與撰寫規則以 `openspec/config.yaml` 為準，本檔不重複維護
- 目前尚無 `app/Services/`、`app/Http/Requests/`；新增業務邏輯時優先抽成 Service，不要再擴大 Controller（既有 `AuthController` 的重構見 `docs/analysis/2026-10-14-project-review-todo.md` M1）

---

## 授權規範

- 已知缺口：`routes/api.php` 的 `member`、`provider` 路由群組只有 `auth:sanctum`，**沒有角色 middleware**；只有 `admin` 群組有 `EnsureAdmin`（待辦見 `docs/analysis/2026-10-14-project-review-todo.md` H1）
- 在 middleware 補上之前：新增或修改 member / provider 路由時，Controller 必須明確檢查呼叫者角色與資源所有權，不可假設路由群組已有保護
- middleware 補上之後：新路由必須掛對應角色 middleware，並保留 Controller 內的資源所有權檢查
- 每個涉及權限的路由都必須有跨角色測試（member / provider / admin / 未登入各自的預期結果，參考 `tests/Feature/AdminEndpointAuthTest.php`）

---

## 驗證指令

- 測試：`php artisan test`（`phpunit.xml` 強制使用 SQLite `:memory:`，因此 `lockForUpdate` 等行鎖行為在測試中不具驗證力，不可據此宣稱已驗證併發安全）
- 格式：`./vendor/bin/pint --test <變更的檔案>`；只針對本次變更的檔案，避免全專案格式化造成無關 diff
- OpenSpec：`openspec validate --specs`、`openspec status --change <name>`
- 完成 task 後的回報格式見 `.claude/skills/verify-and-report/SKILL.md`

---

## 審查流程（subagent）

審查角色有兩套定義，內容相同，分別給兩個工具載入；兩者都是**唯讀**（只讀取與執行白名單內的驗證命令，不修改檔案、不 commit、不 push）：

| 工具 | 位置 | 模型 | 備註 |
|------|------|------|------|
| Pi agent | `.pi/agents/` | `github-copilot/gpt-5.6-terra` | 與實作端的 Claude 屬不同模型家族，跨家族審查，**優先使用** |
| Claude Code | `.claude/agents/` | `sonnet` | 與實作端同家族，只是 context 隔離的審查，不是跨家族獨立審查；適合快速自檢 |

兩套內容需保持同步：修改其中一套時，必須同步更新另一套（差異僅有 frontmatter 的 `tools`、`model`，以及工具名稱的寫法）。

| 時機 | 角色 | 輸入 | 用途 |
|------|------|------|------|
| OpenSpec 文件（proposal、design、tasks、specs）寫完、**實作之前** | `spec-reviewer` | change 名稱或 `openspec/changes/<name>/` | 檢查需求完整性、安全與權限、AC 與測試任務的雙向對應、與既有規格一致性 |
| `spec-reviewer` 通過後、**實作之前**，且 change 符合觸發條件 | `design-hardener` | change 名稱或 `openspec/changes/<name>/` | 條件式設計加固：安全原語、Docker 與部署 runtime、Composer／npm 相依、migration 與自動部署、失效模式（不符合觸發條件時直接 PASS，可略過） |
| 程式碼變更**全部完成**、提 PR 之前 | `strict-reviewer` | 分支（`git diff HEAD`）或指定範圍 | 檢查分層、Eloquent、安全與授權、測試與專案慣例，並執行 `php artisan test`、`pint` |

- 結果為 `FAIL` 或 `BLOCKED` 時，必須先修正或解除限制再重新審查，不可直接進入下一階段
- 審查結果是參考意見，不取代使用者判斷；審查者與實作者意見不同時，由使用者決定
- `strict-reviewer` 的測試使用 SQLite，不能驗證行鎖併發行為，這類變更需另外人工確認
- 審查使用的分支、提交與 PR 仍依下方「Git 與 PR 流程」進行

---

## Git 與 PR 流程

- 遠端：`vps`（自架 Gitea，PR 與部署）為主；`origin`（GitHub）為鏡像，非必要不需推送
- 分支前綴：`feat/`、`fix/`、`chore/`、`docs/`、`security/`、`test/`、`perf/`；kebab-case 英文
- Commit：`type(scope): 繁體中文描述`，type 同分支前綴（另有 `ci`、`refactor`）
- OpenSpec 變更分成獨立 PR：change 規劃、實作、歸檔各自一個 PR，不混在一起
- 功能分支推送到 `vps` 後由使用者在 Gitea 開 PR 並合併；合併後再 `git checkout master && git pull vps master`，並刪除本機分支
- 不要在輸出或 commit 中顯示 remote URL（`git remote -v` 的輸出可能含有憑證）

---

## 溝通風格

- 發現問題直接說，不要繞彎
- 有不確定的地方，明確標注「我不確定，需要你確認」
- 不要重複確認已經決定的技術選擇
