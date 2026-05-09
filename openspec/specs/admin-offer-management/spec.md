## ADDED Requirements

### Requirement: 管理員查看全平台課程列表
後端 SHALL 提供 `GET /api/admin/offers`（需 Bearer token，role=admin），回傳所有課程，支援關鍵字搜尋與分頁。

#### Scenario: 取得全部課程列表
- **WHEN** 管理員送出 GET 請求不帶參數
- **THEN** 回傳 HTTP 200，`{ status: true, data: [...offers], meta: { total, per_page, current_page, last_page } }`，預設每頁 15 筆，含 provider_id

#### Scenario: 搜尋課程
- **WHEN** 管理員送出 `?q=墾丁`
- **THEN** 只回傳 title 或 location 包含「墾丁」的課程

---

### Requirement: 管理員刪除課程
後端 SHALL 提供 `DELETE /api/admin/offers/{id}`（需 Bearer token，role=admin），可刪除任意課程，不受 provider_id 限制。

#### Scenario: 刪除存在的課程
- **WHEN** 管理員送出有效 id 的 DELETE 請求
- **THEN** 回傳 HTTP 200，`{ status: true, message: "課程已刪除" }`，資料庫記錄移除

#### Scenario: 課程不存在
- **WHEN** 指定 id 的課程不存在
- **THEN** 回傳 HTTP 404，`{ status: false, message: "課程不存在" }`
