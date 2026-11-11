<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
date_default_timezone_set(APP_TIMEZONE);
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('SIMRSSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/simrs/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
