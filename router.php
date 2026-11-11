<?php
// Router untuk `php -S`: hanya folder /simrs/public/ yang boleh diakses.
// Server bawaan PHP tidak membaca .htaccess, jadi config/, app/, database/ harus diblokir di sini.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if ($path === '/' || $path === '/simrs' || $path === '/simrs/' || $path === '/simrs/public' || $path === '/simrs/public/') {
    header('Location: /simrs/public/login.php');
    return true;
}
if (str_starts_with($path, '/simrs/public/') && !str_contains($path, '..')) {
    return false; // layani file seperti biasa
}
http_response_code(404);
echo 'Not Found';
return true;
