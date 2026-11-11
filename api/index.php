<?php
// Satu pintu masuk untuk Vercel (vercel-php). Semua request diarahkan ke sini oleh vercel.json,
// lalu diteruskan ke file di public/. Folder app/, config/, database/ tidak bisa diakses langsung.
// Uji lokal: APP_BASE_URL= SESSION_DRIVER=database php -S 127.0.0.1:8090 api/index.php

$root = dirname(__DIR__);
$base = getenv('APP_BASE_URL');
if ($base === false) $base = getenv('VERCEL') ? '' : '/simrs/public';
$base = rtrim($base, '/');

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if ($base !== '' && str_starts_with($path, $base)) $path = substr($path, strlen($base)) ?: '/';

if ($path === '/' || $path === '/index') {
    // Beranda: langsung ke dashboard (yang otomatis mengarah ke login jika belum masuk).
    $path = '/index.php';
}

// Aset statis (CSS/JS/gambar) dengan cache di edge.
if (preg_match('#^/assets/([A-Za-z0-9._-]+)$#', $path, $m)) {
    $file = $root . '/public/assets/' . $m[1];
    $types = ['css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'ico' => 'image/x-icon', 'webp' => 'image/webp'];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (is_file($file) && isset($types[$ext])) {
        header('Content-Type: ' . $types[$ext]);
        header('Cache-Control: public, max-age=3600, s-maxage=86400');
        readfile($file);
        exit;
    }
}

// Halaman PHP di public/ (hanya nama file sederhana, tanpa subfolder).
if (preg_match('#^/([A-Za-z0-9_]+\.php)$#', $path, $m) && is_file($root . '/public/' . $m[1])) {
    $script = $root . '/public/' . $m[1];
    $_SERVER['SCRIPT_FILENAME'] = $script;
    $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $base . '/' . $m[1];
    chdir($root . '/public');
    require $script;
    exit;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Halaman tidak ditemukan.';
