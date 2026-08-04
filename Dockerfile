# 使用官方 PHP 8.2 FPM 鏡像作為基礎
FROM php:8.2-fpm AS app

# 安裝系統依賴
# 1. 更新套件列表並安裝必要的套件
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    default-mysql-client \
    netcat-openbsd \
    grep

# 清理 apt 快取以減小鏡像大小
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# 安裝 PHP 擴展
# GD 需要在 install 前先 configure，才能帶入 jpeg/webp/freetype 支援
RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# 從官方 Composer 鏡像複製 Composer 執行文件
# 這樣可以確保我們使用最新版本的 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 設置工作目錄
WORKDIR /var/www

# 設置目錄權限
# 將 /var/www 目錄的所有權更改為 www-data 用戶和組
RUN chown -R www-data:www-data /var/www

# 創建必要的目錄並設置權限
# Laravel 需要這些目錄來存儲日誌、緩存等
RUN mkdir -p /var/www/storage /var/www/bootstrap/cache
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# composer manifests 先於全專案 COPY，讓 Docker layer cache 在 composer.json/composer.lock
# 未變動時可以直接命中，不必每次程式碼變動都重跑 composer install
COPY composer.json composer.lock /var/www/
# --no-scripts：此時 app/、artisan 尚未存在，composer 的 post-autoload-dump hook
# （會呼叫 artisan package:discover）此時執行會失敗，故跳過，待 COPY . 之後手動補跑
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# 複製完整專案程式碼
COPY . /var/www

# COPY 進來的檔案預設 owner 是 root，需重新套用權限
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# 補上 --no-scripts 跳過的 composer post-autoload-dump 動作：
# dump-autoload 重新掃描此時才存在的 app/ 目錄以產生完整 classmap，
# package:discover 產生服務提供者自動發現快取
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi

# 對應 config/filesystems.php 的 links 設定，於 build 階段建立符號連結
# （純檔案系統操作，不需要 Laravel 執行期或資料庫連線）
# -n 避免既有符號連結指向目錄時被誤判為目錄而巢狀建立，確保重跑此指令時是冪等取代
RUN ln -sfn ../storage/app/public /var/www/public/storage

# 複製自定義的 PHP 配置文件
# 這個文件包含 PHP 運行時的配置選項
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# 複製並設置入口點腳本
# 這個腳本將在容器啟動時執行
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 設置容器啟動時執行的入口點
# 這將在 CMD 指令之前執行
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

# 設置默認的容器命令
# 這將在 ENTRYPOINT 執行後運行
CMD ["php-fpm"]

# nginx 靜態資源與反向代理階段
# 不依賴任何執行期 volume 或跨容器共享機制取得程式碼——
# public/ 目錄與符號連結都在 build 階段從 app 階段取得／建立
FROM nginx:alpine AS web

COPY --from=app /var/www/public /var/www/public

# 獨立建立符號連結，不依賴上面 COPY --from 隱性帶入 app 階段建立的符號連結
# （兩階段各自明確宣告，避免日後複製方式調整導致符號連結無聲消失）
RUN ln -sfn ../storage/app/public /var/www/public/storage

COPY docker/nginx/conf.d/ /etc/nginx/conf.d/
