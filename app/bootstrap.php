<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
date_default_timezone_set(APP_TIMEZONE);
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    // Session di database: otomatis di Vercel, atau paksa dengan SESSION_DRIVER=database.
    $driver = simrs_env('SESSION_DRIVER', getenv('VERCEL') ? 'database' : 'files');
    if ($driver === 'database') {
        require_once __DIR__ . '/session.php';
        session_set_save_handler(new SimrsDbSessionHandler($pdo), true);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
    }

    // HTTPS langsung, atau di belakang proxy (Vercel, Cloudflare tunnel).
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    // '/simrs/public' -> cookie untuk '/simrs/'; '' (root domain) -> '/'
    $cookiePath = APP_BASE_URL === '' ? '/' : rtrim(dirname(APP_BASE_URL), '/') . '/';
    session_name('SIMRSSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
