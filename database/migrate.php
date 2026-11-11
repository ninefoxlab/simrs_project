<?php
// Membuat database + semua tabel dari database/schema.sql memakai koneksi PDO
// (tidak butuh klien `mysql`). Aman dijalankan ulang: CREATE TABLE IF NOT EXISTS / INSERT IGNORE.
//
// Lokal:  php database/migrate.php
// TiDB:   DB_HOST=... DB_PORT=4000 DB_USER=... DB_PASSWORD=... DB_NAME=simrs_db DB_SSL=1 php database/migrate.php
if (PHP_SAPI !== 'cli') exit('CLI only');
define('SIMRS_NO_AUTOCONNECT', true);
require dirname(__DIR__) . '/config/database.php';

if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) exit("Nama database tidak valid: {$dbName}\n");

$sql = file_get_contents(__DIR__ . '/schema.sql');
// Buang komentar baris, lalu pecah per pernyataan.
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$statements = array_filter(array_map('trim', preg_split('/;\s*(\r?\n|$)/', $sql)));

$pdo = simrs_db_connect(null);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$dbName}`");

$done = 0;
foreach ($statements as $stmt) {
    // Nama database ditentukan DB_NAME, bukan yang tertulis di schema.sql.
    if (preg_match('/^(CREATE DATABASE|USE)\b/i', $stmt)) continue;
    $pdo->exec($stmt);
    $done++;
}
$tables = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
$perms = (int)$pdo->query('SELECT COUNT(*) FROM permissions')->fetchColumn();
echo "Selesai: {$done} pernyataan dijalankan di `{$dbName}` ({$tables} tabel, {$perms} permission).\n";
