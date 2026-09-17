<?php

use Illuminate\Http\Request;

$public = realpath(__DIR__.'/../../../public');
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$resolved = realpath($public.$path);

if ($path !== '/' && $resolved !== false && str_starts_with($resolved, $public.DIRECTORY_SEPARATOR) && is_file($resolved)) {
    return false;
}

$app = require __DIR__.'/bootstrap.php';
$app->handleRequest(Request::capture());
