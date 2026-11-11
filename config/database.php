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

    // Untuk skrip CLI: muat file env (mis. hasil `vercel env pull .env.cloud`) tanpa menimpa env yang sudah ada.
    function simrs_load_env_file(string $file): void {
        if (!is_file($file)) return;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (!preg_match('/^\s*(?:export\s+)?([A-Z0-9_]+)\s*=\s*(.*)\s*$/', $line, $m)) continue;
            $val = trim($m[2]);
            if (strlen($val) >= 2 && ($val[0] === '"' || $val[0] === "'") && $val[-1] === $val[0]) $val = substr($val, 1, -1);
            if (getenv($m[1]) === false) putenv($m[1] . '=' . $val);
        }
    }

    // Petakan variabel dari integrasi TiDB (TIDB_* atau DATABASE_URL) ke DB_* bila DB_* belum diisi.
    function simrs_map_db_env(): void {
        $map = ['DB_HOST' => 'TIDB_HOST', 'DB_PORT' => 'TIDB_PORT', 'DB_USER' => 'TIDB_USER', 'DB_PASSWORD' => 'TIDB_PASSWORD', 'DB_NAME' => 'TIDB_DATABASE'];
        $usedTidb = false;
        foreach ($map as $db => $tidb) {
            if (simrs_env($db) === null && ($v = simrs_env($tidb)) !== null) { putenv("{$db}={$v}"); $usedTidb = true; }
        }
        $url = simrs_env('DATABASE_URL');
        if ($url && simrs_env('DB_HOST') === null && preg_match('#^mysql://#i', $url)) {
            $u = parse_url($url);
            if (!empty($u['host'])) putenv('DB_HOST=' . $u['host']);
            if (!empty($u['port'])) putenv('DB_PORT=' . $u['port']);
            if (isset($u['user'])) putenv('DB_USER=' . rawurldecode($u['user']));
            if (isset($u['pass'])) putenv('DB_PASSWORD=' . rawurldecode($u['pass']));
            $path = trim($u['path'] ?? '', '/');
            if ($path !== '' && simrs_env('DB_NAME') === null) putenv('DB_NAME=' . $path);
            $usedTidb = $usedTidb || str_contains($u['host'] ?? '', 'tidbcloud.com');
        }
        // TiDB Cloud wajib TLS.
        if (($usedTidb || str_contains((string)simrs_env('DB_HOST'), 'tidbcloud.com')) && simrs_env('DB_SSL') === null) putenv('DB_SSL=1');
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

if (PHP_SAPI === 'cli') {
    $envFile = getenv('SIMRS_ENV_FILE');
    if ($envFile) simrs_load_env_file($envFile);
}
simrs_map_db_env();
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
