## ADDED Requirements

### Requirement: provider_id 所有權不變式
對單一課程操作端點（show / update / destroy），系統 MUST 依序執行：先以 id 查找課程（不存在回 404），再比對 provider_id（不符回 403）。兩步驟不可合併為單一 WHERE 查詢。`store()` MUST 強制將 `provider_id` 設為 `auth()->id()`，忽略 request body 傳入值。

#### Scenario: 課程不存在回傳 404
- **WHEN** 指定 id 的課程不存在於資料庫
- **THEN** 回傳 HTTP 404，`{ status: false, message: "課程不存在" }`

#### Scenario: 課程存在但非本人回傳 403
- **WHEN** 課程存在（id 有效）但 `offer.provider_id !== auth()->id()`
- **THEN** 回傳 HTTP 403，`{ status: false, message: "無權限…" }`

#### Scenario: store 強制設定 provider_id
- **WHEN** 教練送出新增課程請求，body 中包含任意 provider_id 值
- **THEN** 系統忽略該值，`offer.provider_id` 固定為 `auth()->id()`

---

### Requirement: 教練課程列表
後端 SHALL 提供 `GET /api/provider/offers`（需 Bearer token，role=provider），回傳當前登入教練自己建立的課程，支援分頁。

#### Scenario: 取得自己的課程列表
- **WHEN** 已登入教練送出 GET 請求
- **THEN** 回傳 HTTP 200，只包含 `provider_id = auth()->id()` 的課程，含分頁 meta

#### Scenario: 無課程時回傳空陣列
- **WHEN** 教練尚未建立任何課程
- **THEN** 回傳 HTTP 200，`{ status: true, data: [], meta: { total: 0, ... } }`

---

### Requirement: 教練課程詳情
後端 SHALL 提供 `GET /api/provider/offers/{id}`（需 Bearer token，role=provider），回傳單一課程完整資料，只允許查看自己建立的課程。

#### Scenario: 取得自己的課程詳情
- **WHEN** 已登入教練送出 `GET /api/provider/offers/1`，且該課程 `provider_id = auth()->id()`
- **THEN** 回傳 HTTP 200，`{ status: true, data: { ...offer } }`

#### Scenario: 查看他人課程
- **WHEN** 課程存在但 `provider_id !== auth()->id()`
- **THEN** 回傳 HTTP 403，`{ status: false, message: "無權限查看此課程" }`

#### Scenario: 課程不存在
- **WHEN** 指定 id 的課程不存在
- **THEN** 回傳 HTTP 404，`{ status: false, message: "課程不存在" }`

---

### Requirement: 教練新增課程
後端 SHALL 提供 `POST /api/provider/offers`（需 Bearer token），建立新課程並自動設定 `provider_id` 為當前登入教練。

#### Scenario: 新增課程成功
- **WHEN** 教練送出包含 title / location / spot / price / region 的合法資料
- **THEN** 回傳 HTTP 201，`{ status: true, data: { ...offer, provider_id: <coach_id> } }`

#### Scenario: 缺少必填欄位
- **WHEN** 教練送出缺少 title 或 price 的資料
- **THEN** 回傳 HTTP 422，`{ status: false, message: "...", errors: { field: [...] } }`

---

### Requirement: 教練更新課程
後端 SHALL 提供 `PUT /api/provider/offers/{id}`（需 Bearer token），更新指定課程，只允許修改自己建立的課程。

#### Scenario: 更新自己的課程
- **WHEN** 教練送出合法更新資料且 offer.provider_id === auth()->id()
- **THEN** 回傳 HTTP 200，`{ status: true, data: { ...updated_offer } }`

#### Scenario: 嘗試更新他人課程
- **WHEN** offer.provider_id !== auth()->id()
- **THEN** 回傳 HTTP 403，`{ status: false, message: "無權限修改此課程" }`

#### Scenario: 課程不存在
- **WHEN** 指定 id 的課程不存在
- **THEN** 回傳 HTTP 404，`{ status: false, message: "課程不存在" }`

---

### Requirement: 教練刪除課程
後端 SHALL 提供 `DELETE /api/provider/offers/{id}`（需 Bearer token），刪除指定課程，只允許刪除自己建立的課程。

#### Scenario: 刪除自己的課程
- **WHEN** offer.provider_id === auth()->id()
- **THEN** 回傳 HTTP 200，`{ status: true, message: "課程已刪除" }`，資料庫記錄移除

#### Scenario: 嘗試刪除他人課程
- **WHEN** offer.provider_id !== auth()->id()
- **THEN** 回傳 HTTP 403，`{ status: false, message: "無權限刪除此課程" }`
