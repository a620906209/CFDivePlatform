#!/bin/bash
set -e

echo "=== CFDivePlatform 容器初始化開始 ==="

# 確保目錄與權限（php-fpm 啟動前必須完成）
[ ! -d "/var/www/storage" ] && mkdir -p /var/www/storage
[ ! -d "/var/www/bootstrap/cache" ] && mkdir -p /var/www/bootstrap/cache
chown -R www-data:www-data /var/www
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# 確保 .env 存在
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate
fi

# 強制 DB_HOST=db（Docker service name，不能用 localhost）
php -r "
\$env = file_get_contents('/var/www/.env');
\$env = preg_replace('/^DB_HOST=.*$/m', 'DB_HOST=db', \$env);
file_put_contents('/var/www/.env', \$env);
"

# Composer 依賴（vendor 不存在時才裝，通常已存在於 volume）
if [ -f "composer.json" ] && { [ ! -d "vendor" ] || [ "composer.json" -nt "vendor/autoload.php" ]; }; then
    composer install --no-scripts --optimize-autoloader
fi

# 背景執行：等 MySQL → migration → cache clear → storage link → swagger
# php-fpm 不等這些完成就先啟動，避免重啟時 CORS 502
(
    echo "⏳ [背景] 等待 MySQL..."
    COUNT=0
    until mysqladmin ping -h"db" -u"${DB_USERNAME:-cfdiveuser}" -p"${DB_PASSWORD}" --silent 2>/dev/null || [ $COUNT -ge 30 ]; do
        sleep 2
        COUNT=$((COUNT+1))
    done

    echo "🗄️  [背景] 執行 migration..."
    php artisan migrate --force || echo "⚠️  migration 失敗"

    echo "🧹 [背景] 清除 Laravel 緩存..."
    php artisan config:clear || true
    php artisan cache:clear  || true
    php artisan route:clear  || true
    php artisan view:clear   || true

    echo "🔗 [背景] storage:link..."
    php artisan storage:link --force || true

    if php -r "echo class_exists('L5Swagger\\L5SwaggerServiceProvider') ? 'yes' : 'no';" 2>/dev/null | grep -q 'yes'; then
        php artisan l5-swagger:generate || true
    fi

    echo "✅ [背景] 初始化完成"
) &

# 啟動 cron（Laravel Scheduler）
service cron start || cron || true

echo "🚀 啟動 php-fpm..."
exec "$@"
