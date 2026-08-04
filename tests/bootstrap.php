<?php

// docker-compose.yml 改用 env_file: 注入 .env 後，.env 內所有變數（不只 DB_*）
// 都變成真實環境變數（存在於 $_SERVER），PHPUnit 的 <env force="true"> 只覆蓋
// $_ENV / putenv，而 Laravel env() 透過 phpdotenv 優先讀 $_SERVER——導致容器內
// 跑測試時實際使用 .env 的 production-like 值（如 CACHE_STORE=redis、
// SESSION_DRIVER=database、QUEUE_CONNECTION=database、MAIL_MAILER=smtp），
// 而非 phpunit.xml 宣告的 testing 值，曾造成 RefreshDatabase 清空開發用 MySQL
// （2026-06-12）、以及後續拿掉 bind mount 改用 env_file 後測試互相污染快取／
// 對外寄信等問題（2026-08-04）。必須在 bootstrap 階段將 phpunit.xml 宣告的
// 每一個 testing 值同步強制寫入 $_SERVER / $_ENV / putenv 三處。
foreach ([
    'APP_ENV'                 => 'testing',
    'APP_MAINTENANCE_DRIVER'  => 'file',
    'BCRYPT_ROUNDS'           => '4',
    'CACHE_STORE'             => 'array',
    'DB_CONNECTION'           => 'sqlite',
    'DB_DATABASE'             => ':memory:',
    'MAIL_MAILER'             => 'array',
    'PULSE_ENABLED'           => 'false',
    'QUEUE_CONNECTION'        => 'sync',
    'SESSION_DRIVER'          => 'array',
    'TELESCOPE_ENABLED'       => 'false',
] as $key => $value) {
    $_SERVER[$key] = $value;
    $_ENV[$key]    = $value;
    putenv("{$key}={$value}");
}

require __DIR__ . '/../vendor/autoload.php';
