# 使用官方 PHP 8.2 FPM 鏡像作為基礎
FROM php:8.2-fpm

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
    grep \
    cron

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

# 複製自定義的 PHP 配置文件
# 這個文件包含 PHP 運行時的配置選項
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# 複製並設置入口點腳本
# 這個腳本將在容器啟動時執行
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 加入 Laravel Scheduler cron job
RUN echo "* * * * * www-data php /var/www/artisan schedule:run >> /var/log/laravel-scheduler.log 2>&1" \
    > /etc/cron.d/laravel-scheduler \
    && chmod 0644 /etc/cron.d/laravel-scheduler \
    && crontab /etc/cron.d/laravel-scheduler

# 設置容器啟動時執行的入口點
# 這將在 CMD 指令之前執行
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

# 設置默認的容器命令
# 這將在 ENTRYPOINT 執行後運行
CMD ["php-fpm"]
