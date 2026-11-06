<?php

/*
 * RT05 TAKEDA — front controller API di public_html (paket shared, M16.3). .htaccess mengirim /api, /health,
 * /sanctum/csrf-cookie, dan /reset-password ke sini (setara gateway.Caddyfile profil VPS). Aplikasi di ~/rt05/api.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$base = dirname(__DIR__).'/rt05/api';

if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $base.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $base.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
