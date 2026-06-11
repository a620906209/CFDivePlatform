# provider-verification Specification

## Purpose

定義 `provider_profiles.is_verified` 的最小業務語意：未通過平台驗證的教練，其課程不對公開端點曝光。在完整教練審核流程（證照上傳、審核佇列、駁回原因）實作前，先以此約束堵住「未審核教練可公開曝光」的風險。

## ADDED Requirements

### Requirement: 未驗證教練的課程不對公開端點曝光
公開課程端點（`GET /api/diving-offers`、`GET /api/diving-offers/{id}`）SHALL 僅回傳符合以下任一條件的課程：(a) `provider_id` 為 null（平台自有資料）；(b) 課程所屬 Provider 的 `provider_profiles.is_verified = true`。未驗證教練的課程在列表中 SHALL 被排除，在詳情端點 SHALL 回傳 404。

#### Scenario: 已驗證教練的課程正常曝光
- **WHEN** 匿名使用者請求 `GET /api/diving-offers`
- **THEN** 已驗證教練（is_verified=true）的課程出現在結果中

#### Scenario: 未驗證教練的課程從列表排除
- **WHEN** 匿名使用者請求 `GET /api/diving-offers`
- **THEN** 未驗證教練（is_verified=false 或無 ProviderProfile）的課程不出現在結果中

#### Scenario: 未驗證教練的課程詳情回 404
- **WHEN** 匿名使用者請求 `GET /api/diving-offers/{id}`，該課程屬於未驗證教練
- **THEN** 回傳 HTTP 404

#### Scenario: provider_id 為 null 的課程不受限
- **WHEN** 匿名使用者請求公開課程端點，課程的 `provider_id` 為 null
- **THEN** 課程正常曝光

---

### Requirement: 驗證狀態切換立即生效
管理員透過 `PUT /api/admin/providers/{id}/toggle-verified` 切換驗證狀態後，公開課程列表的快取（`diving_offers` cache tag）SHALL 立即失效，下次請求反映最新可見性。

#### Scenario: 取消驗證後課程立即從公開列表消失
- **WHEN** 管理員將教練 is_verified 由 true 切為 false
- **THEN** 下一次 `GET /api/diving-offers` 請求不包含該教練的課程（不受 180 秒快取影響）

---

### Requirement: 教練自有管理端點不受可見性限制
Provider 對自己課程的管理端點（`/api/provider/offers*`）與 Admin 管理端點（`/api/admin/offers*`）SHALL 不受公開可見性過濾影響，未驗證教練仍可登入、編輯與管理自己的課程。

#### Scenario: 未驗證教練仍可管理自己的課程
- **WHEN** 未驗證教練以有效 token 請求 `GET /api/provider/offers`
- **THEN** 回傳該教練的全部課程

## Notes

已知限制（留待完整教練審核流程處理）：`GET /api/diving-offers/{id}/schedules`、`GET /api/diving-offers/{id}/reviews` 與預約建立流程尚未套用相同過濾，知道課程 id 的使用者仍可間接互動。完整審核流程（verification_status enum、證照上傳、審核佇列）見 `docs/analysis/2026-06-11-future-roadmap-feasibility.md` §2.1。
