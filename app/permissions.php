<?php
require_once __DIR__ . '/helpers.php';
function user_roles(PDO $pdo, int $userId): array {
    $s = $pdo->prepare("SELECT r.kode_role FROM user_roles ur JOIN roles r ON r.id_role=ur.id_role WHERE ur.id_user=? AND r.status_aktif=1 ORDER BY ur.is_default DESC, r.kode_role");
    $s->execute([$userId]);
    return $s->fetchAll(PDO::FETCH_COLUMN);
}
function user_permissions(PDO $pdo, int $userId): array {
    static $cache = [];
    if (isset($cache[$userId])) return $cache[$userId];
    $s = $pdo->prepare("SELECT DISTINCT p.kode_permission FROM user_roles ur JOIN roles r ON r.id_role=ur.id_role JOIN role_permissions rp ON rp.id_role=r.id_role JOIN permissions p ON p.id_permission=rp.id_permission WHERE ur.id_user=? AND r.status_aktif=1");
    $s->execute([$userId]);
    return $cache[$userId] = $s->fetchAll(PDO::FETCH_COLUMN);
}
function can(PDO $pdo, int $userId, string $permission): bool {
    return in_array($permission, user_permissions($pdo, $userId), true);
}
function require_permission(PDO $pdo, int $userId, string $permission): void {
    if (!can($pdo, $userId, $permission)) { http_response_code(403); exit('Akses ditolak: permission ' . e($permission) . ' tidak tersedia.'); }
}
