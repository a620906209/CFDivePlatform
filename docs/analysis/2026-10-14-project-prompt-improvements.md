# Project Prompt／Skill／Subagent 改善計畫

> 日期：2026-10-14
> 範圍：`CLAUDE.md`、`openspec/config.yaml`、`.windsurf/rules/`、`.claude/skills/`、`.claude/commands/opsx/`、subagent 定義。
> 性質：工作流與文件調整，不涉及應用程式行為，不需走 OpenSpec change，以 `chore/` 分支提 PR 即可。
> 搭配閱讀：《2026-10-14-project-review-todo.md》。

---

## 一、Project Prompt

### 發現的問題

| # | 問題 | 影響 | 優先 |
|---|------|------|------|
| P1 | `openspec/config.yaml` 的 context 過期：寫「Admin Panel、預約/訂單系統、課程圖片上傳、教練審核流程」尚未實作，實際皆已完成並歸檔 | 每次 `/opsx:propose` 注入錯誤的專案狀態，AI 可能重做或誤判 | 高 |
| P2 | `config.yaml` 寫 localStorage key `token`、`coach_token`；實際 member 為 `sessionStorage` | 同上 | 高 |
| P3 | `.windsurf/rules/ponytail-lazy-senior-dev-mode.md` 要求「No abstractions、fewest files」，與 CLAUDE.md 的 SOLID、Service 層、DTO、依賴反轉直接衝突 | Windsurf 與 Claude Code 產出風格相反 | 高 |
| P4 | CLAUDE.md 未寫專案實際慣例（API 回應格式、目錄結構、Swagger 放 `app/Docs/`） | AI 需自行猜測 | 中 |
| P5 | 未寫驗證指令與格式化要求（`php artisan test`、`./vendor/bin/pint`） | 實作後沒有標準驗證步驟 | 中 |
| P6 | 未寫 Git／PR 流程（分支命名、commit 格式、PR 目標 remote、禁止直接 push master） | 目前只存在於 skill，一般對話不會觸發；push master 會直接觸發部署 | 高 |
| P7 | `config.yaml` 只有 `rules.tasks`，沒有 `rules.proposal`、`rules.specs`，無法在 spec 階段強制 AC 可測量 | CLAUDE.md 的「AC 必須對應測試」只靠 AI 自覺 | 中 |
| P8 | 沒有授權規範：新路由必須掛角色 middleware（見《project-review-todo》H1） | 漏洞的根因 | 高 |
| P9 | 安全確認清單未說明「答案要記錄在哪裡」 | 無法稽核 | 低 |
| P10 | CLAUDE.md 與 `config.yaml` 都有 OpenSpec 規則，且各寫各的 | 重複維護、容易不一致 | 低 |

### 建議修改

#### 1. `openspec/config.yaml`
- [x] 更新「已完成模組」：Admin Panel、預約與時段、即時訊息、評價與投票、通知、課程圖片、教練審核、S3 儲存、鎖定與限流、Token Refresh
- [x] 更新「尚未實作」：金流整合、訂閱方案（`subscriptions`／`plans` 尚無 API）
- [x] 修正前端儲存描述（以實際程式為準，改前先重新確認 coach 端）
- [x] 加入 `rules.proposal`：必須列出受影響的主規格
- [x] 加入 `rules.specs`：每個 Requirement 至少一個 `#### Scenario`；Scenario 需可轉為測試
- [x] 加入 `rules.tasks`：每個 Scenario 至少對應一個 `[整合測試]` task，否則不得標記 apply-ready
- [x] 加入 `rules.design`：觸及安全觸發條件時，必須附上「安全確認」段落（回應 P9）

#### 2. `CLAUDE.md`（新增區塊，避免與 config.yaml 重複規則）
- [x] **專案慣例**：API 回應 `{ status, message?, data }`、Controller 位於 `app/Http/Controllers/API/`、Swagger 註解位於 `app/Docs/`
- [x] **驗證指令**：`php artisan test`、`./vendor/bin/pint`；說明測試預設使用 SQLite，涉及行鎖的測試需另行確認
- [x] **Git 流程**：分支前綴 `feat/`、`fix/`、`chore/`、`docs/`；commit 使用 Conventional Commits 加繁中描述；一律走 PR；**禁止直接 push master（會觸發自動部署）**；推送 remote 為 `vps`
- [x] **授權規範**：新增路由必須掛角色 middleware，並附跨角色 403 測試
- [x] 以一句話指向 `openspec/config.yaml`，OpenSpec 細節規則只維護一份

#### 3. `.windsurf/rules/`
- [x] 三選一（已決定：刪除 ponytail 規則；Pi 與 Claude Code 皆讀取 CLAUDE.md，不需另外相容）：
  - 刪除 ponytail 規則，改為指向 CLAUDE.md（建議，單一事實來源）
  - 保留但改寫，移除與 SOLID 衝突的條款
  - 保留，但明確標註「僅用於快速原型，不用於本專案正式程式」
- [ ] 確認 `docs/STARTUP.md` 內容是否與 CLAUDE.md 重複

---

## 二、Skills

### 現況
- 5 個 skill：`openspec-explore`、`openspec-propose`、`openspec-apply-change`、`openspec-archive-change`（OpenSpec 官方產出，版本 1.3.1）及自訂的 `branch-and-propose`
- `.claude/commands/opsx/*` 與對應 skill 內容幾乎重複，僅差少數文句

### 發現的問題

| # | 問題 | 優先 |
|---|------|------|
| S1 | `openspec-apply-change` 沒有任何測試相關指令，與新增的「AC 必須對應測試」規則沒有連動 | 高 |
| S2 | `branch-and-propose` 使用 `git pull VPS master`，實際 remote 名稱為小寫 `vps`（Linux 區分大小寫） | 中 |
| S3 | `branch-and-propose` 未處理「目前不在 master」的情況，也未說明分支命名前綴規則（只舉 `feature/`，實際慣例有 `feat/`、`fix/`、`chore/`） | 中 |
| S4 | 沒有「實作完成 → 驗證 → 提 PR」的收尾 skill | 中 |
| S5 | `CLAUDE.md` 要求完成後回報 spec 同步狀態、重複邏輯、命名一致性、AC 測試涵蓋，目前沒有固定模板 | 中 |
| S6 | 安全確認清單未做成可載入的 skill | 低 |
| S7 | 新增 API 端點的固定步驟（路由、角色 middleware、驗證、Swagger、測試）每次重複 | 低 |
| S8 | `commands/opsx` 與 `skills/openspec-*` 重複，OpenSpec 升級時可能只更新其中一邊 | 低 |

### 建議修改

- [x] **修改 `openspec-apply-change`**：開始前檢查 tasks.md 中每個 Scenario／AC 是否有對應測試 task，缺漏則停止並回報
- [x] **修正 `branch-and-propose`**：`vps` 小寫、處理非 master 起點、說明分支前綴規則
- [x] **新增 `verify-and-report`**：執行 `php artisan test`、`pint`，並依 CLAUDE.md 輸出固定格式報告（spec 同步狀態、偏差點、重複邏輯、命名一致性、AC 測試涵蓋）
- [x] **新增 `finish-and-pr`**：確認測試通過、依 Conventional Commits 提交、推送分支（不推 master）、提示開 PR；歸檔另走 `openspec-archive-change`
- [ ] **新增 `security-checklist`**：從 CLAUDE.md 抽出必答問題，碰到五類觸發條件時載入
- [ ] **新增 `add-endpoint`**：新增 API 端點的檢核流程
- [ ] 決定 `commands/opsx` 與 `skills/openspec-*` 是否保留雙份（由 OpenSpec CLI 產生，建議不手動合併，只記錄於升級流程）

---

## 三、Subagent

### 現況
專案層級沒有任何 subagent 定義（`.pi/agents`、`.claude/agents` 皆不存在）。

### 建議角色

| 角色 | 模式 | 建議模型層級／思考 | 用途 |
|------|------|--------------------|------|
| `spec-auditor` | 唯讀 | 中階／medium | 檢查 proposal、tasks 的 AC 是否對應測試，是否與主規格衝突 |
| `security-reviewer` | 唯讀 | 頂階／high | 依 CLAUDE.md 安全清單審查 diff（輸入驗證、授權、N+1、XSS） |
| `test-runner` | 執行 | 快速／low | 跑測試並整理失敗原因，註明 SQLite 限制 |
| `backend-implementer` | 寫入 | 中階／medium | 依 tasks.md 的 `[後端]` 項目實作 |
| `frontend-implementer` | 寫入 | 中階／medium | 依 `[前端]` 項目實作 |
| `spec-syncer` | 寫入 | 中階／low | 歸檔時把 delta 同步回 `openspec/specs/` |

### 並行規則
- 唯讀角色可隨時並行
- `backend-implementer` 與 `frontend-implementer` 僅在 design.md 已定義 API 契約且檔案不重疊時並行，各用獨立 worktree
- `test-runner` 必須在實作完成後序列執行
- 所有寫入型 child 預設**不 push、不 merge**，由主 agent 驗證 diff 後整合

### 待辦
- [ ] 決定 subagent 定義的存放位置（專案 `.pi/agents/` 或 `.claude/agents/`）；**需你確認**目前主要使用哪個工具鏈
- [ ] 先建立兩個唯讀角色：`spec-auditor`、`security-reviewer`
- [ ] 試用一輪後再決定是否建立寫入型角色

---

## 四、建議執行順序

1. 更新 `openspec/config.yaml` context 與 rules（P1、P2、P7）
2. 決定並處理 `.windsurf` 衝突（P3）
3. 補 CLAUDE.md：Git 流程、授權規範、驗證指令、專案慣例（P4、P5、P6、P8）
4. 修 `branch-and-propose`、`openspec-apply-change`（S1、S2、S3）
5. 新增 `verify-and-report`、`finish-and-pr`（S4、S5）
6. 建立唯讀 subagent（spec-auditor、security-reviewer）
7. 視需要補 `security-checklist`、`add-endpoint`

## 進度紀錄（2026-10-14）

- 已完成：config.yaml 更新、CLAUDE.md 補充、移除 `.windsurf` ponytail 規則、修正 `branch-and-propose`、`openspec-apply-change` 加入測試對應檢查、新增 `verify-and-report` 與 `finish-and-pr`
- 工具分工：Claude Code 為主要實作工具，Pi agent 目前用於審查與環境調整；`.claude/` 保留
- 注意：`openspec-apply-change`（skill 與 `commands/opsx/apply.md`）為 OpenSpec 產生的檔案，已加入標註「CFDive 自訂區塊」，執行 `openspec update` 後需確認並重新加回
- 尚待處理：subagent 定義、`security-checklist`、`add-endpoint`

## 需要你決定的事項（歷史紀錄，下列 1 已決定）

1. `.windsurf` ponytail 規則：刪除、改寫、還是標註用途？
2. subagent 存放位置與主要工具鏈（Claude Code、Pi、Windsurf）
3. `config.yaml` 中前端 token 儲存的描述：以現況為準，還是等 M4 統一後再寫？
