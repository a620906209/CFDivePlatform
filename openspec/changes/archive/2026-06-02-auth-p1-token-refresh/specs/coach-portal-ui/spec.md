## MODIFIED Requirements

### Requirement: Coach 路由守衛
前端 SHALL 對所有 `/coach/*` 路由（login 除外）加上 navigation guard，未登入時導向 `/coach/login`。auth state 以 sessionStorage 為來源，API 請求支援 refresh-then-retry。

#### Scenario: 未登入訪問 Dashboard
- **WHEN** 未登入使用者直接訪問 `/coach/dashboard`
- **THEN** 自動導向 `/coach/login`

#### Scenario: 分頁關閉後重開需重新登入
- **WHEN** 教練關閉分頁後重新開啟 `/coach/dashboard`
- **THEN** sessionStorage 已清除，自動導向 `/coach/login`

#### Scenario: 登出
- **WHEN** 教練點擊登出
- **THEN** 呼叫 `POST /api/provider/logout`，清除 sessionStorage coach_token / coach_user，導向 `/coach/login`
