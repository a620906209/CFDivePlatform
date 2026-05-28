## Why

預約成立後，會員與教練之間沒有直接溝通管道，只能透過平台外的通訊工具協調集合地點、裝備確認等細節，造成使用流程斷裂。加入即時訊息功能可以將溝通閉環留在平台內，提升黏著度與信任感。

## What Changes

- 新增 `booking_messages` 資料表，儲存文字與圖片訊息，與 `bookings` 一對多關聯
- 引入 **Laravel Reverb** 作為自架 WebSocket 伺服器（docker-compose 新增 `reverb` service）
- 每個 confirmed 預約建立一條 **Presence Channel**（`presence-booking.{id}`），同時承載訊息推播與在線狀態
- 已讀回執：對方在頻道內且讀取訊息時觸發 `MessageRead` event
- 圖片訊息：透過 HTTP POST 上傳至 Laravel Storage，WebSocket 廣播包含圖片 URL
- 訊息視窗隨預約狀態開關：`confirmed` → 可讀寫；`completed` → 封存唯讀；其餘狀態不開放
- 新增 DNS subdomain `ws.hank-space.com`，透過 Nginx Proxy Manager 獨立 Proxy Host 接入（B 方案）
- 前端（Vue 3）新增訊息面板，嵌入 Member 的預約詳情頁與 Coach 的預約管理頁

## Capabilities

### New Capabilities

- `booking-chat`: 預約確認後的即時文字與圖片訊息，含訊息歷史、在線狀態、已讀回執，課程結束後封存
- `user-presence`: 基於 Presence Channel 的每預約在線狀態追蹤（誰在線、加入/離開事件）

### Modified Capabilities

- `booking-lifecycle`: 預約狀態機新增「訊息視窗開關」語義——`confirmed` 開啟可讀寫頻道，`completed` 切換為封存唯讀，其餘終態（rejected、expired、cancelled）無訊息頻道

## Impact

- **新增套件**：`laravel/reverb`（PHP）、`intervention/image`（PHP）、`laravel-echo`、`pusher-js`（npm）
- **新增 Docker service**：`reverb`（連接 `cfdive-network` 與 `proxy_net`，port 8080 僅內網）
- **新增 API 端點**：訊息列表、發送文字、上傳圖片、標記已讀
- **修改**：`docker-compose.yml`、`config/broadcasting.php`（connection 改為 `reverb`）
- **Infrastructure**：NPM 新增 `ws.hank-space.com` Proxy Host（WebSocket 啟用）、DNS A Record
- **不影響**：現有預約 API、評價系統、所有已完成模組
