<?php
// APP_BASE_URL: awalan URL aplikasi.
//  - Lokal/XAMPP: '/simrs/public' (default)
//  - Vercel: kosong (aplikasi di root domain), atur env APP_BASE_URL='' atau biarkan VERCEL=1 terdeteksi.
define('APP_NAME', 'SIMRS RSUD NTB');
$__base = getenv('APP_BASE_URL');
if ($__base === false) $__base = getenv('VERCEL') ? '' : '/simrs/public';
define('APP_BASE_URL', rtrim($__base, '/'));
unset($__base);
define('APP_TIMEZONE', 'Asia/Jakarta');
