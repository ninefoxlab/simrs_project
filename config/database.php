<?php
// Koneksi database. Nilai dibaca dari environment variable (dipakai di Vercel/TiDB);
// jika tidak ada, memakai default lokal (MySQL root tanpa password).
//
//   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD
//   DB_SSL=1          aktifkan TLS (wajib untuk TiDB Cloud)
//   DB_SSL_CA=/path   opsional; default memakai CA bundle sistem

if (!function_exists('simrs_env')) {
    function simrs_env(string $key, ?string $default = null): ?string {
        $v = getenv($key);
        if ($v === false || $v === '') $v = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        return ($v === null || $v === '') ? $default : (string)$v;
    }

    function simrs_db_connect(?string $dbName): PDO {
        $host = simrs_env('DB_HOST', '127.0.0.1');
        $port = (int)simrs_env('DB_PORT', '3306');
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4" . ($dbName ? ";dbname={$dbName}" : '');
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10,
        ];
        if (simrs_env('DB_SSL') === '1') {
            $ca = simrs_env('DB_SSL_CA');
            if (!$ca) {
                foreach (['/etc/pki/tls/certs/ca-bundle.crt', '/etc/ssl/certs/ca-certificates.crt', '/etc/ssl/cert.pem'] as $candidate) {
                    if (is_file($candidate)) { $ca = $candidate; break; }
                }
            }
            // PHP 8.4+ memakai konstanta Pdo\Mysql; versi lama memakai PDO::MYSQL_*
            $caAttr = defined('Pdo\Mysql::ATTR_SSL_CA') ? constant('Pdo\Mysql::ATTR_SSL_CA') : PDO::MYSQL_ATTR_SSL_CA;
            $verifyAttr = defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT') ? constant('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT') : PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT;
            if ($ca) $options[$caAttr] = $ca;
            $options[$verifyAttr] = true;
        }
        $pdo = new PDO($dsn, simrs_env('DB_USER', 'root'), simrs_env('DB_PASSWORD', ''), $options);
        // Samakan zona waktu NOW() dengan aplikasi (WIB), juga di server cloud yang memakai UTC.
        $pdo->exec("SET time_zone = '+07:00'");
        return $pdo;
    }
}

$dbName = simrs_env('DB_NAME', 'simrs_db');

if (!defined('SIMRS_NO_AUTOCONNECT')) {
    try {
        $pdo = simrs_db_connect($dbName);
    } catch (PDOException $e) {
        error_log('SIMRS DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Koneksi database gagal. Periksa pengaturan database (config/database.php atau environment variable DB_*).');
    }
}
