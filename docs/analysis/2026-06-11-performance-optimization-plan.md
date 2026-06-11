# CFDivePlatform 操作體驗優化計畫

> 撰寫日期：2026-06-11
> 背景：使用者反映本機操作體驗差、回應慢。本文以實測數據定位根因，並依投資報酬率排序優化項目。

---

## 一、實測基準（2026-06-11，本機 Docker 環境）

| 量測項目 | 數值 | 備註 |
|---------|------|------|
| `GET /api/diving-offers`（冷） | **7.98 秒** | 經 nginx:8080 → php-fpm |
| 同端點第 2 次 | 6.07 秒 | |
| 同端點第 3 次（Redis 快取命中） | **2.49 秒** | 快取命中仍要 2.5 秒 → 瓶頸不在查詢 |
| 容器內全測試套件 | 64.4 秒 | 同套件本機 PHP 僅 4.5 秒（**14 倍差距**） |

**判讀**：Redis 快取命中後仍需 2.5 秒，代表時間幾乎都花在「PHP 啟動 + 載入框架程式碼」，而非業務邏輯或資料庫。瓶頸在環境層，不在程式碼。

## 二、根因分析（已驗證）

1. **OPcache 已載入但被停用**（容器內實測 `opcache_get_status` 回報 disabled；`docker/php/local.ini` 無任何 opcache 設定）。每個 HTTP 請求都重新編譯 Laravel 全框架數千個 PHP 檔。
2. **Windows bind mount `./:/var/www`**（docker-compose.yml:16）。每次檔案讀取都跨越 Windows↔Linux 檔案系統邊界（Docker Desktop 的檔案橋接是已知效能黑洞），與根因 1 疊加：每請求數千次跨界 I/O。
3. 次要因素：
   - `cfdive-nginx` healthcheck 持續失敗（`wget http://localhost/` connection refused），雖不影響服務但遮蔽真正的健康狀態。
   - `SESSION_DRIVER=database`、`QUEUE_CONNECTION=database`：queue worker 輪詢 MySQL；API 多為 stateless 影響小，列為觀察項。
   - `APP_DEBUG=true`：開發環境正常設定，影響相對小。

## 三、優化計畫

### Phase 1 — 立即（0.5 天內，預期把 API 壓到 0.5 秒以下）

**O1.1 啟用 OPcache（投報率最高的一項）**

在 `docker/php/local.ini` 加入開發友善設定後 rebuild app 容器：

```ini
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=192
opcache.max_accelerated_files=20000
; 開發環境：仍偵測檔案變更，存檔即生效
opcache.validate_timestamps=1
opcache.revalidate_freq=0
```

驗收：`GET /api/diving-offers` 快取命中請求 < 500ms（對照基準 2.49s）。

**O1.2 修復 nginx healthcheck**

診斷 `wget http://localhost/` 為何 connection refused（常見原因：nginx 只 listen 特定 server_name、或 healthcheck 應改打 `127.0.0.1`），修正 docker-compose.yml:63 的 healthcheck。驗收：`docker compose ps` 顯示 nginx healthy。

**O1.3 `composer dump-autoload -o`**

寫入 Dockerfile / entrypoint，減少 autoload 時的檔案系統探測。

### Phase 2 — 結構性（若 Phase 1 後仍 > 1 秒才執行，0.5~1 天）

**O2.1 消除 Windows bind mount I/O**

選項（擇一，建議 a 先試）：
- (a) **vendor/ 改用 named volume**：`- vendor-data:/var/www/vendor`，框架程式碼（I/O 大宗）留在 Linux 原生檔案系統，原始碼仍可即時編輯。需在容器內跑 `composer install`。
- (b) **整個 repo 搬入 WSL2 檔案系統**（`\\wsl$\Ubuntu\...`），IDE 透過 WSL remote 開啟。I/O 改善最徹底（10~50 倍），但改變現有 laragon 工作流程，遷移成本較高。

**O2.2 SESSION/QUEUE driver 評估**

Redis 已在跑，`SESSION_DRIVER=redis`、`QUEUE_CONNECTION=redis` 可移除 queue worker 對 MySQL 的輪詢壓力。屬順手改，不是主瓶頸。

### Phase 3 — 應用層體感（與環境無關，部署後使用者也受益，1~2 天）

**O3.1 課程封面圖未壓縮**

聊天圖片已有 `scaleDown(2048) + toJpeg(85)` 處理（`BookingMessageController.php:115`），但 `CourseImageController` 上傳的封面/圖庫**原檔直存**。手機拍攝的 5~10MB 原圖會直接進課程列表頁。建議：套用與聊天圖片相同的縮圖管線，列表用圖另出 ~600px 縮圖；前端 `<img>` 加 `loading="lazy"`。

**O3.2 無分頁的列表端點**

`GET /api/member/bookings`、`/api/provider/bookings`、`/api/admin/bookings` 皆為 `->get()` 全量撈取。目前資料量小無感，預約數成長後會線性變慢。建議在金流上線前（資料開始累積）補分頁。

**O3.3 前端載入體感**

- 路由已全面 lazy-load（已驗證，不需處理）。
- 列表頁加 skeleton 載入佔位，消除「白屏等待」體感。
- 搜尋輸入加 debounce（300ms），減少連發請求。

### 生產環境註記（部署時順手做）

- `php artisan optimize`（config / route / view cache）
- 生產 OPcache 改 `validate_timestamps=0`（部署時重啟 php-fpm 刷新）

## 四、執行順序與驗收

```
第 1 步  O1.1 OPcache → 重測基準（預期 2.49s → <0.5s）
第 2 步  O1.2 + O1.3 順手修
第 3 步  視重測結果決定是否做 O2.1（>1s 才做）
第 4 步  O3.1 圖片壓縮（開 openspec change，影響 course-image-upload 規格）
第 5 步  O3.2 / O3.3 排入一般 backlog
```

每步驗收一律以第一節的 curl 量測方式對照，數據寫回本文件。
