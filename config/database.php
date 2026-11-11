<?php
// Konfigurasi default XAMPP. Jika root MySQL memakai password, isi $dbPass.
$dbHost = '127.0.0.1';
$dbName = 'simrs_db';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('SIMRS DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Koneksi database gagal. Periksa config/database.php dan status MySQL XAMPP.');
}
