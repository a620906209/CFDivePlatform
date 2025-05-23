#!/bin/bash
set -e

echo "=== CFDivePlatform 容器初始化開始 ==="

# 檢查目錄結構
if [ ! -d "/var/www/storage" ]; then
  echo "創建 storage 目錄..."
  mkdir -p /var/www/storage
fi

if [ ! -d "/var/www/bootstrap/cache" ]; then
  echo "創建 bootstrap/cache 目錄..."
  mkdir -p /var/www/bootstrap/cache
fi

# 設置權限
echo "設置目錄權限..."
chown -R www-data:www-data /var/www
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# 等待 MySQL 服務啟動
echo "等待 MySQL 服務啟動..."

# 使用更穩定的方法檢查 MySQL 連接
MAX_TRIES=60
COUNT=0

wait_for_mysql() {
    while [ $COUNT -lt $MAX_TRIES ]; do
        if mysqladmin ping -h"db" -u"cfdiveuser" -p"**REMOVED**" --silent 2>/dev/null; then
            echo "✅ MySQL 服務已準備就緒"
            return 0
        fi
        
        # 備用檢查方法
        if php -r "
            try {
                \$pdo = new PDO('mysql:host=db;port=3306', 'cfdiveuser', '**REMOVED**');
                echo 'PHP-PDO-OK';
                exit(0);
            } catch(Exception \$e) {
                exit(1);
            }
        " 2>/dev/null; then
            echo "✅ MySQL 連接成功 (通過 PHP PDO)"
            break
        fi
        
        echo "⏳ 等待 MySQL... ($((COUNT+1))/$MAX_TRIES)"
        sleep 2
        COUNT=$((COUNT+1))
    done

    if [ $COUNT -eq $MAX_TRIES ]; then
        echo "⚠️  無法連接到 MySQL，但將繼續啟動服務"
    fi
}

wait_for_mysql

# 檢查並安裝 Composer 依賴
echo "📦 檢查 Composer 依賴..."
if [ -f "composer.json" ]; then
    if [ ! -d "vendor" ] || [ "composer.json" -nt "vendor/autoload.php" ]; then
        echo "安裝 Composer 依賴..."
        composer install --no-scripts --no-autoloader --optimize-autoloader
        composer dump-autoload --optimize
    else
        echo "✅ Composer 依賴已是最新"
    fi
fi

# 設置 Laravel 環境
if [ ! -f .env ]; then
    echo "🔧 創建 .env 檔案..."
    cp .env.example .env
    php artisan key:generate
else
    echo "✅ .env 檔案已存在"
fi

# 更新環境變數以確保正確配置
echo "🔧 更新資料庫配置..."
sed -i "s/DB_HOST=.*/DB_HOST=db/g" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=**REMOVED**/g" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=cfdiveuser/g" .env
sed -i "s/DB_DATABASE=.*/DB_DATABASE=CFDivePlatform/g" .env

# 執行遷移（如果數據庫已準備好）
echo "🗄️  執行數據庫遷移..."
if php artisan migrate:status 2>/dev/null; then
    php artisan migrate --force || echo "⚠️  遷移執行遇到問題，但繼續執行"
else
    echo "⚠️  無法檢查遷移狀態，跳過遷移"
fi

# 清除與優化 Laravel 緩存
echo "🧹 清除 Laravel 緩存..."
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

# 生成 Swagger 文檔（如果可能）
if php -r "echo class_exists('L5Swagger\\L5SwaggerServiceProvider') ? 'yes' : 'no';" 2>/dev/null | grep -q 'yes'; then
    echo "📖 生成 API 文檔..."
    php artisan l5-swagger:generate || echo "⚠️  API 文檔生成失敗"
fi

echo "✅ CFDivePlatform 初始化完成！"

# 執行傳入的命令
exec "$@"
