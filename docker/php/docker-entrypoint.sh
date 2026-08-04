#!/bin/bash
set -e

echo "=== CFDivePlatform 容器初始化開始 ==="

# 確保目錄與權限（php-fpm 啟動前必須完成）
# storage-app-public 具名 volume 掛載時會覆蓋 image 內建的權限，每次啟動都需要重新確保
[ ! -d "/var/www/storage" ] && mkdir -p /var/www/storage
[ ! -d "/var/www/bootstrap/cache" ] && mkdir -p /var/www/bootstrap/cache
chown -R www-data:www-data /var/www
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

echo "🚀 啟動 php-fpm..."
exec "$@"
