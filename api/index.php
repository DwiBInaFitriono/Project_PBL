<?php

// ==============================================================================
// Rebung Pintar — Vercel Serverless Entrypoint
// Mengarahkan request serverless Vercel ke public/index.php dengan /tmp storage
// ==============================================================================

// 1. Siapkan struktur direktori writable di /tmp untuk views, cache, dan logs
$directories = [
    '/tmp/storage/app',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/views',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/logs',
];

foreach ($directories as $dir) {
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// 2. Siapkan database SQLite lokal di /tmp jika driver sqlite digunakan dan belum ada MySQL
$sqliteSource = __DIR__.'/../database/database.sqlite';
$sqliteDest = '/tmp/database.sqlite';

if (getenv('DB_HOST')) {
    putenv('DB_CONNECTION=mysql');
    $_ENV['DB_CONNECTION'] = 'mysql';
    $_SERVER['DB_CONNECTION'] = 'mysql';
} elseif (getenv('DB_CONNECTION') === 'sqlite' || ! getenv('DB_CONNECTION')) {
    if (! file_exists($sqliteDest)) {
        if (file_exists($sqliteSource)) {
            copy($sqliteSource, $sqliteDest);
        } else {
            touch($sqliteDest);
        }
    }
    if (! getenv('DB_DATABASE')) {
        putenv("DB_DATABASE={$sqliteDest}");
        $_ENV['DB_DATABASE'] = $sqliteDest;
        $_SERVER['DB_DATABASE'] = $sqliteDest;
    }
}

// 3. Set environment variable runtime serverless Vercel
$serverlessEnv = [
    'APP_STORAGE' => '/tmp/storage',
    'VIEW_COMPILED_PATH' => '/tmp/storage/framework/views',
    'SESSION_DRIVER' => 'cookie',
    'CACHE_STORE' => 'array',
    'LOG_CHANNEL' => 'stderr',
];

foreach ($serverlessEnv as $key => $val) {
    putenv("{$key}={$val}");
    $_ENV[$key] = $val;
    $_SERVER[$key] = $val;
}

if (! getenv('APP_KEY') && empty($_ENV['APP_KEY']) && empty($_SERVER['APP_KEY'])) {
    $fallbackKey = 'base64:HsRNMGIZaxiGotCaQXu3mHHuin5vRhDyG5XrOqWPrno=';
    putenv("APP_KEY={$fallbackKey}");
    $_ENV['APP_KEY'] = $fallbackKey;
    $_SERVER['APP_KEY'] = $fallbackKey;
}

// 4. Eksekusi router aplikasi Laravel
require __DIR__.'/../public/index.php';
