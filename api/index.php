<?php

// ==============================================================================
// Rebung Pintar — Vercel Serverless Entrypoint
// Mengarahkan request serverless Vercel ke public/index.php dengan /tmp storage
// ==============================================================================

// 0. Deteksi dukungan bcrypt di runtime ini.
//    Vercel PHP (Amazon Linux 2) kadang tidak menyertakan libcrypt dengan dukungan
//    Blowfish, sehingga password_hash() dengan PASSWORD_BCRYPT mengembalikan false.
//    Jika itu terjadi, gunakan argon2id agar aplikasi tetap berjalan.
//    Login dengan akun bcrypt lama akan tetap berhasil karena Hash::check()
//    sudah mendeteksi algoritma hash dari prefix string ($2y$ vs $argon2id$).
$_REBUNG_BCRYPT_OK = defined('PASSWORD_BCRYPT')
    && @password_hash('t', PASSWORD_BCRYPT, ['cost' => 4]) !== false;

if (! $_REBUNG_BCRYPT_OK) {
    // bcrypt tidak tersedia di runtime ini — gunakan argon2id sebagai driver
    putenv('HASH_DRIVER=argon2id');
    $_ENV['HASH_DRIVER'] = 'argon2id';
    $_SERVER['HASH_DRIVER'] = 'argon2id';
    error_log('[REBUNG] bcrypt tidak tersedia di runtime ini, fallback ke argon2id');
}
unset($_REBUNG_BCRYPT_OK);

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
    if (! getenv('DB_CONNECTION')) {
        putenv('DB_CONNECTION=mysql');
        $_ENV['DB_CONNECTION'] = 'mysql';
        $_SERVER['DB_CONNECTION'] = 'mysql';
    }

    if (getenv('DB_DATABASE') === 'sys' || ! getenv('DB_DATABASE')) {
        putenv('DB_DATABASE=test');
        $_ENV['DB_DATABASE'] = 'test';
        $_SERVER['DB_DATABASE'] = 'test';
    }
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
    'APP_MAINTENANCE_DRIVER' => 'file',
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

if ((isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (isset($_SERVER['HTTP_HOST']) && str_ends_with($_SERVER['HTTP_HOST'], '.vercel.app'))) {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = '443';
}

$_SERVER['SCRIPT_NAME'] = '/index.php';

// 4. Eksekusi router aplikasi Laravel
require __DIR__.'/../public/index.php';
