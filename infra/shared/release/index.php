<?php

/*
 * RT05 TAKEDA — front controller WEBSITE di public_html (paket shared, M16.3). Aplikasi ada di ~/rt05/web, di luar
 * document root; folder ini menjadi public path Laravel (Vite manifest build/, images/, unduh/versi.json).
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$base = dirname(__DIR__).'/rt05/web';

if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $base.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $base.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
