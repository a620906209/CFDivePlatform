<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    $checks = ['db' => false, 'redis' => false, 'cache' => false];

    try {
        DB::connection()->getPdo();
        $checks['db'] = true;
    } catch (\Throwable) {}

    try {
        Redis::ping();
        $checks['redis'] = true;
    } catch (\Throwable) {}

    try {
        cache()->put('health_check', 'ok', 5);
        $checks['cache'] = cache()->get('health_check') === 'ok';
    } catch (\Throwable) {}

    $healthy = $checks['db'] && $checks['redis'] && $checks['cache'];

    return response()->json([
        'status'      => $healthy ? 'ok' : 'degraded',
        'timestamp'   => now()->toIso8601String(),
        'environment' => app()->environment(),
        'php_version' => PHP_VERSION,
        'laravel'     => app()->version(),
        'memory_mb'   => round(memory_get_usage(true) / 1024 / 1024, 2),
        'checks'      => $checks,
    ], $healthy ? 200 : 503);
});

Route::get('/', function () {
    return view('welcome');
});
