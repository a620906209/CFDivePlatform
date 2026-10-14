---
name: finish-and-pr
description: 實作或文件變更完成後的收尾流程：確認驗證通過、依 Conventional Commits 提交、推送功能分支到 vps 並提示開 PR；PR 合併後協助清理本機分支。當使用者說「收尾」「提交並推上去」「準備開 PR」「PR 合併了幫我清理」時使用。
argument-hint: [commit 描述，可省略]
---

## Instructions

### A. 提交並推送

1. **確認分支與狀態**
   ```
   git branch --show-current
   git status --short
   ```
   - 目前在 `master`：**停止**，說明禁止直接提交到 master（push master 會觸發自動部署），詢問是否要先建立分支
   - 沒有任何變更：回報並結束

2. **確認驗證已完成**
   - 若本次對話尚未執行過 `verify-and-report`，先詢問使用者是否要先驗證；程式碼變更預設應該先驗證
   - 若驗證有失敗，**不要提交**，回報失敗內容

3. **確認提交內容**
   - 用 `git diff --stat` 檢視，檢查是否混入無關檔案（`.env`、`.phpunit.result.cache`、`storage/`、`node_modules/`、除錯用的暫存檔）
   - OpenSpec 的規劃、實作、歸檔應是各自獨立的 PR；若同一個分支混了不同階段，提醒使用者拆開
   - 逐一 `git add <檔案或目錄>`，避免 `git add -A` 把無關檔案帶進去

4. **提交**
   - 格式：`type(scope): 繁體中文描述`，type 與分支前綴一致（`feat`、`fix`、`chore`、`docs`、`security`、`test`、`perf`，另有 `ci`、`refactor`）
   - 使用者有提供描述就用，沒有就依 diff 擬一個並讓使用者確認
   - 不要使用 `--no-verify`、`--amend` 已推送的 commit

5. **推送功能分支（絕不推 master）**
   ```
   git push -u vps <目前分支>
   ```
   - 不要使用 `--force`；若被拒絕，回報原因，不要自行強推
   - 從 push 輸出中找 `Create a new pull request` 的連結，原樣提供給使用者；**不要自己組合或輸出 remote URL**（可能含憑證）

6. **提醒後續**
   - PR 由使用者在 Gitea 開立並合併（master 可能有分支保護）
   - 若是 OpenSpec change 的實作 PR，合併後下一步是另開 PR 執行歸檔（`/opsx:archive`）
   - 若本次變更值得再審查，可請 Pi agent 針對 `git diff master...HEAD` 做審查後再開 PR

### B. PR 合併後清理（使用者說已合併時）

1. 確認沒有未提交變更，然後：
   ```
   git checkout master
   git pull vps master
   git fetch --prune vps
   ```
2. 確認該分支確實已合併：`git branch --merged master`
3. 用 `git branch -d <分支>` 刪除本機分支；若 Git 拒絕（顯示未合併），**停止並回報**，不要改用 `-D`，除非使用者明確同意
4. 回報目前 `master` 的最新 commit，以及是否與 `vps/master` 一致

## Notes

- 本 skill 不 merge、不 rebase、不 force push、不改寫已推送的歷史。
- 禁止直接 push master，這是 CLAUDE.md 的明文規則。
- 若工作區有與本次無關的未提交變更（例如使用者另外改的檔案），只提交與本次相關的檔案，並提醒其餘檔案未納入。
