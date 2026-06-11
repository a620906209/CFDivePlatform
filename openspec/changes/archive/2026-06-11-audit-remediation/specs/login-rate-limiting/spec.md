## MODIFIED Requirements

### Requirement: 登入頻率限制
後端 SHALL 對所有登入端點套用 IP-based 頻率限制，超過限制時回傳 HTTP 429。Member 與 Provider 每 IP 每分鐘最多 10 次（原定 5 次；帳號鎖定機制上線後已涵蓋暴力破解防護，放寬以容納共享 IP 場景，與實作 `throttle:10,1` 一致）；Admin 因影響範圍更廣，限制為每 IP 每分鐘最多 3 次。

#### Scenario: Member / Provider 正常登入不受影響
- **WHEN** 同一 IP 在 1 分鐘內對 `/api/member/login` 或 `/api/provider/login` 送出 10 次以內的請求
- **THEN** 請求正常處理，回傳對應的登入結果（200 成功或 401 失敗）

#### Scenario: Member / Provider 超過頻率限制
- **WHEN** 同一 IP 在 1 分鐘內送出第 11 次 member 或 provider 登入請求
- **THEN** 回傳 HTTP 429，並帶有 `Retry-After` header 指示等待時間
