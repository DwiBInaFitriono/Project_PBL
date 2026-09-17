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

// 2. Siapkan database SQLite lokal di /tmp jika diperlukan
$sqliteSource = __DIR__.'/../database/database.sqlite';
$sqliteDest = '/tmp/database.sqlite';

if (! file_exists($sqliteDest)) {
    if (file_exists($sqliteSource)) {
        copy($sqliteSource, $sqliteDest);
    } else {
        touch($sqliteDest);
    }
}

// 3. Set environment variable runtime serverless Vercel
putenv('APP_STORAGE=/tmp/storage');
putenv('VIEW_COMPILED_PATH=/tmp/storage/framework/views');
putenv('SESSION_DRIVER=cookie');
putenv('CACHE_STORE=array');
putenv('LOG_CHANNEL=stderr');

if (! getenv('DB_DATABASE') && file_exists($sqliteDest)) {
    putenv("DB_DATABASE={$sqliteDest}");
}

// 4. Eksekusi router aplikasi Laravel
require __DIR__.'/../public/index.php';
