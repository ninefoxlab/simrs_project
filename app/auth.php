<?php
require_once __DIR__ . '/helpers.php';
function current_user(PDO $pdo): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $stmt = $pdo->prepare("SELECT id_user, id_pegawai, username, nama_tampilan, status_aktif, last_login FROM users WHERE id_user = ? LIMIT 1");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || (int)$user['status_aktif'] !== 1) {
        $_SESSION = [];
        return null;
    }
    return $user;
}
function require_login(PDO $pdo): array {
    $user = current_user($pdo);
    if (!$user) redirect_to(APP_BASE_URL . '/login.php');
    return $user;
}
function login_user(PDO $pdo, string $username, string $password): bool {
    $stmt = $pdo->prepare("SELECT id_user, username, password_hash, nama_tampilan, status_aktif FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row || (int)$row['status_aktif'] !== 1 || !password_verify($password, $row['password_hash'])) return false;
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$row['id_user'];
    $up = $pdo->prepare("UPDATE users SET last_login = NOW(), updated_at = NOW() WHERE id_user = ?");
    $up->execute([(int)$row['id_user']]);
    audit_log($pdo, (int)$row['id_user'], 'LOGIN', 'users', 'Pengguna masuk ke sistem');
    return true;
}
