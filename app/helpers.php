<?php
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
function redirect_to(string $url): never {
    header('Location: ' . $url);
    exit;
}
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function csrf_check($token): bool {
    return is_string($token) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
}
function flash_set(string $type, string $message): void {
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}
function flash_get(): ?array {
    $f = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return is_array($f) ? $f : null;
}
function audit_log(PDO $pdo, int $userId, string $action, string $entity, string $detail = ''): void {
    // audit_trail adalah tabel TRD; pemetaan kolom dibuat adaptif karena detail kolom dapat berbeda antar revisi.
    try {
        $q = $pdo->prepare('SELECT id_pegawai FROM users WHERE id_user = ?');
        $q->execute([$userId]);
        $pegawaiId = $q->fetchColumn();
        $cols = $pdo->query('SHOW COLUMNS FROM audit_trail')->fetchAll();
        $available = array_column($cols, 'Field');
        $data = [];
        $candidates = [
            'id_pegawai' => $pegawaiId,
            'aksi' => $action, 'aktivitas' => $action, 'action' => $action,
            'tabel' => $entity, 'nama_tabel' => $entity, 'entitas' => $entity,
            'detail' => $detail, 'deskripsi' => $detail, 'keterangan' => $detail,
            'waktu' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s'), 'tanggal' => date('Y-m-d H:i:s')
        ];
        foreach ($candidates as $col => $val) if (in_array($col, $available, true)) $data[$col] = $val;
        if (!$data) return;
        $names = array_keys($data);
        $sql = 'INSERT INTO audit_trail (' . implode(',', array_map(fn($n) => '`'.$n.'`', $names)) . ') VALUES (' . implode(',', array_fill(0, count($names), '?')) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($data));
    } catch (Throwable $e) {
        error_log('SIMRS audit log: ' . $e->getMessage());
    }
}
function format_datetime($value): string {
    if (!$value) return '—';
    try { return (new DateTime((string)$value))->format('d-m-Y H:i'); }
    catch (Throwable $e) { return (string)$value; }
}
