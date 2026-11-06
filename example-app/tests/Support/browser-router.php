<?php

use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;

if (getenv('APP_ENV') !== 'testing' || ! getenv('TASK5_STORAGE')) {
    http_response_code(403);
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file(__DIR__.'/../../public'.$path)) {
    return false;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->useStoragePath(getenv('TASK5_STORAGE'));
$app->booted(fn () => app(Vite::class)->useHotFile(getenv('TASK5_STORAGE').'/unused-hot'));
$app->handleRequest(Request::capture());
