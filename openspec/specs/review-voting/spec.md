### Requirement: Member 對評價投「有幫助」票
已登入 **Member**（role = member）SHALL 能對評價投「有幫助」票，可取消，不可重複投票。Provider 與 Admin 不可投票。

#### Scenario: 成功投票
- **WHEN** 已登入 Member 送出 `POST /api/reviews/{id}/helpful`，且尚未對此評價投票
- **THEN** 系統建立 ReviewVote，`reviews.helpful_count + 1`，回傳目前 `helpful_count`

#### Scenario: 取消投票（Toggle）
- **WHEN** 已登入 Member 再次送出 `POST /api/reviews/{id}/helpful`，且已投過票
- **THEN** 系統刪除 ReviewVote，`reviews.helpful_count` 以 `GREATEST(helpful_count - 1, 0)` 原子更新，回傳目前 `helpful_count`

#### Scenario: 未登入不可投票
- **WHEN** 未登入使用者嘗試投票
- **THEN** 系統回傳 401

#### Scenario: 非 Member 角色不可投票
- **WHEN** Provider 或 Admin 嘗試投票
- **THEN** 系統回傳 403，message：「只有會員可以投票」

#### Scenario: 不可對自己的評價投票
- **WHEN** Member 嘗試對自己撰寫的評價投票
- **THEN** 系統回傳 422，告知不可對自己的評價投票

### Requirement: 投票狀態隨評價一同回傳
已登入 Member 查看評價列表時，系統 SHALL 回傳當前用戶對每筆評價的投票狀態。

#### Scenario: 已登入查看列表
- **WHEN** 已登入 Member 送出 `GET /api/diving-offers/{id}/reviews`
- **THEN** 每筆評價包含 `has_voted: true/false`，供前端渲染「有幫助」按鈕狀態

#### Scenario: 未登入查看列表
- **WHEN** 未登入使用者送出 `GET /api/diving-offers/{id}/reviews`
- **THEN** 每筆評價的 `has_voted` 固定為 `false`
