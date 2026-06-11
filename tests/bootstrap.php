<?php

// docker compose 將 DB_CONNECTION=mysql 注入為真實環境變數（存在於 $_SERVER），
// PHPUnit 的 <env force="true"> 只覆蓋 $_ENV / putenv，而 Laravel env() 透過
// phpdotenv 優先讀 $_SERVER——導致容器內跑測試時 RefreshDatabase 直接清空
// 開發用 MySQL（2026-06-12 實際發生三次）。必須在 bootstrap 階段三者同步強制。
foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'] as $key => $value) {
    $_SERVER[$key] = $value;
    $_ENV[$key]    = $value;
    putenv("{$key}={$value}");
}

require __DIR__ . '/../vendor/autoload.php';
