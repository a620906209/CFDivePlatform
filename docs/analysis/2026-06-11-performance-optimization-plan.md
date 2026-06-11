# CFDivePlatform 操作體驗優化計畫

> 撰寫日期：2026-06-11
> 背景：使用者反映本機操作體驗差、回應慢。本文以實測數據定位根因，並依投資報酬率排序優化項目。
> **狀態更新（2026-06-11）**：Phase 1 已執行完成，穩態延遲 2.5s → **0.2s**（約 10 倍改善）。執行結果見第三節，根因分析已依實測修正。

---

## 一、實測基準（2026-06-11 上午，修復前）

| 量測項目 | 數值 | 備註 |
|---------|------|------|
| `GET /api/diving-offers`（冷） | **7.98 秒** | 經 nginx:8080 → php-fpm |
| 同端點（Redis 快取命中、間隔數秒） | **2.3~2.5 秒** | 快取命中仍要 2.5 秒 → 瓶頸不在查詢 |
| 同端點（2 秒內連發） | 0.23 秒 | 關鍵線索：短窗口內其實很快 |
| 容器內全測試套件 | 64.4 秒 | 同套件本機 PHP 僅 4.5 秒 |

## 二、根因分析（依實測修正）

> ⚠️ 初版文檔判定「OPcache 被停用」是**錯誤診斷**——當時用 `php -r`（CLI）量測，而 `opcache.enable_cli` 預設關閉造成假象。php-fpm 的 OPcache 一直是啟用的（851 個腳本在快取中）。正確根因如下：

1. **`opcache.revalidate_freq=2`（預設值）× Windows bind mount**：每隔 2 秒，下一個請求就要跨 Windows↔Linux 檔案系統邊界重新 stat 全部 ~850 個已快取腳本。實測鐵證：2 秒窗口內 0.23s，窗口過期後 2.3~2.5s。一般操作節奏（點一下、看一下、再點）幾乎每次都踩在窗口外，所以「每個操作都慢」。
2. **Entrypoint 的 composer 重裝條件用 mtime 比對**：`composer.json -nt vendor/autoload.php`——git checkout/pull 會更新 composer.json 的 mtime，導致每次分支操作後的容器啟動都重跑 `composer install`（bind mount 上 autoload 生成耗時數分鐘，**期間 php-fpm 未啟動、全站 502**）。
3. **nginx healthcheck 誤報**：busybox wget 對 `localhost` 先解析 IPv6 `::1`，nginx 只 listen IPv4 → 永遠 connection refused → 永遠 unhealthy。
4. 觀察項（未處理）：cron 每分鐘 `schedule:run` 以 CLI 啟動 PHP（CLI 無 OPcache + bind mount），造成偶發 ~2 秒突波；`SESSION/QUEUE=database`；`APP_DEBUG=true`。

## 三、Phase 1 執行紀錄（2026-06-11 完成，branch perf/phase1-opcache）

| 項目 | 修改 | 結果 |
|------|------|------|
| O1.1 OPcache 調校 | `docker/php/local.ini`：`revalidate_freq` 2→**30**、memory 128→192、max_files 10000→20000 | 穩態 **0.20~0.27s**（前：2.3~2.5s）；每 30 秒閒置後僅一次 ~1.2s revalidate |
| O1.2 healthcheck 修復 | docker-compose.yml：`http://localhost/` → `http://127.0.0.1/` | nginx 顯示 **healthy** |
| O1.3 composer 重裝條件 | entrypoint 改為 composer.lock **內容比對**（`cmp` vs `vendor/.composer.lock.installed` 標記檔） | git 分支操作不再觸發開機重裝與 502 窗口 |

### 修復後量測（同一端點、每 5 秒一次）

```
#1: 1.96s（cron 突波）  #2: 0.22s  #3: 0.21s  #4: 0.20s  #5: 0.27s  #6: 0.26s
閒置 35 秒後：1.19s（revalidate，每 30 秒至多一次）→ 下一次 0.30s
```

### 開發注意事項

- 後端程式碼變更最多 **30 秒**後生效；要立即生效：`docker compose exec app kill -USR2 1`（php-fpm 平滑重載，約 1 秒）
- `local.ini` 與 entrypoint 是 build 進 image 的，改動需 `docker compose build app && docker compose up -d app`
- 此設定對 VPS 同樣適用且有益（Linux 原生 stat 便宜，revalidate=30 純賺）

## 四、後續階段（依新數據重新評估）

### Phase 2 — 結構性（暫緩）

原計畫的 vendor named volume / WSL2 遷移以「Phase 1 後仍 >1s」為觸發條件——實測穩態 0.2s，**未達觸發條件，暫緩**。若未來 cron 突波造成困擾，優先處理觀察項 4（如 schedule:run 改由長駐 worker、或縮減無任務時的 bootstrap）。

### Phase 3 — 應用層體感（與環境無關，部署後使用者也受益，1~2 天）

**O3.1 課程封面圖未壓縮**：聊天圖片已有 `scaleDown(2048) + toJpeg(85)`（`BookingMessageController.php:115`），但 `CourseImageController` 原檔直存。建議套用相同縮圖管線 + 前端 `loading="lazy"`。應開 openspec change（影響 `course-image-upload` 規格）。

**O3.2 無分頁的列表端點**：`/api/member/bookings`、`/api/provider/bookings`、`/api/admin/bookings` 皆 `->get()` 全量。資料量成長後線性變慢，建議金流上線前補分頁。

**O3.3 前端載入體感**：列表頁 skeleton 佔位、搜尋 debounce（300ms）。路由已全面 lazy-load（已驗證，不需處理）。

### 生產環境註記（部署時順手做）

- `php artisan optimize`（config / route / view cache）
- 生產 OPcache 可進一步 `validate_timestamps=0`（部署時 `kill -USR2 1` 重載刷新）

## 五、驗收方式

每步以同一 curl 量測對照（`curl -s -o /dev/null -w "%{time_total}" http://127.0.0.1:8080/api/diving-offers`），數據寫回本文件。
