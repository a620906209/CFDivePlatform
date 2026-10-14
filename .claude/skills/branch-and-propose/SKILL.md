---
name: branch-and-propose
description: 開始規劃新功能時觸發，自動從 master 同步並建立新分支，接著進入 OpenSpec 提案流程。當使用者說「規劃新功能」「開新的 spec」「開始做 XXX 功能」「幫我開一個新分支做 XXX」時使用。
argument-hint: [功能名稱]
---

## Instructions

1. 檢查目前狀態：
   ```
   git status --short
   git branch --show-current
   ```
   - 若有未提交的變更，先詢問使用者要 stash 還是先提交，不可直接覆蓋。
   - 若目前**不在 master**，先詢問上一個分支的 PR 是否已合併，不可在未合併的分支上直接疊新功能。

2. 同步 master（遠端名稱為小寫 `vps`，是自架 Gitea；不要用 `origin`，那是 GitHub 鏡像）：
   ```
   git checkout master
   git pull vps master
   ```
   - 若 `git pull` 出現分歧或無法 fast-forward，停止並回報，不要自行 reset 或 force。

3. 決定分支名稱（英文、kebab-case）。若使用者沒有給功能名稱，先詢問。依性質選前綴：

   | 性質 | 前綴 | 範例 |
   |------|------|------|
   | 新功能 | `feat/` | `feat/dive-log-export` |
   | 修正 | `fix/` | `fix/booking-oversell` |
   | 安全 | `security/` | `security/add-role-middleware` |
   | 文件、OpenSpec 規劃與歸檔、工具設定 | `docs/`、`chore/` | `chore/update-openspec-config` |
   | 測試 | `test/` | `test/ci-mysql-job` |
   | 效能 | `perf/` | `perf/cache-offer-list` |

4. 建立並切換新分支：
   ```
   git checkout -b <前綴>/<功能名稱>
   ```

5. 分支建立完成後，向使用者確認分支已就緒，接著**必須**執行 `/opsx:propose <功能名稱>`（`<功能名稱>` 沿用步驟 3 的名稱，不含前綴）建立本次功能的 spec 提案草稿，不要自行用其他方式產生 proposal。

6. spec 草稿完成後，依照 CLAUDE.md 的「OpenSpec 工作流規則 → Task 執行前」清單，確認每條 Acceptance Criteria（Scenario）是否都對應測試任務，未對應者提醒使用者補上，不進入實作階段。

## Notes

- 這個 skill 只負責「開分支 → 交接給 OpenSpec 流程」，不涉及 spec 內容本身的產出邏輯，避免與 CLAUDE.md 與 `openspec/config.yaml` 的規則重複維護。
- OpenSpec 的規劃、實作、歸檔各自是獨立的 PR（見 CLAUDE.md「Git 與 PR 流程」）；本 skill 開的分支通常先用於「規劃」PR。
- 不要在任何輸出中顯示 remote URL（可能含有憑證）。
- 若專案的預設分支不是 `master`，使用前請調整步驟 2。
