<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/permissions.php';
require_once dirname(__DIR__) . '/app/schema.php';

$user = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? null)) {
    http_response_code(403); exit('Permintaan tidak valid.');
}

$page = $_POST['page'] ?? '';
$table = $_POST['table'] ?? '';
$action = $_POST['action'] ?? '';
$uid = (int)$user['id_user'];
$config = require dirname(__DIR__) . '/app/modules_config.php';
if (!isset($config[$page]) || !in_array($table, $config[$page]['tables'], true)) {
    http_response_code(400); exit('Modul atau tabel tidak valid.');
}
if (!in_array($action, ['create', 'edit', 'create_visit'], true)) {
    http_response_code(400); exit('Aksi tidak valid.');
}
if ($action === 'create_visit' && !($page === 'registration' && $table === 'pasien')) {
    http_response_code(400); exit('Aksi kunjungan hanya tersedia dari modul registrasi pasien.');
}

$permission = $config[$page]['permission'];
require_permission($pdo, $uid, $permission . '.' . ($action === 'edit' ? 'update' : 'create'));

/** Membuat satu kunjungan dan satu antrean untuk pasien yang sudah ada. Harus dipanggil di dalam transaksi. */
function simrs_create_visit_and_queue(PDO $pdo, int $patientId, array $visit): array {
    $unitId = filter_var($visit['id_unit'] ?? null, FILTER_VALIDATE_INT);
    if (!$unitId || $unitId < 1) throw new RuntimeException('Unit/poli wajib dipilih.');
    $serviceType = trim((string)($visit['tipe_pelayanan'] ?? ''));
    $allowedTypes = ['Rawat Jalan', 'IGD', 'Rawat Inap'];
    if (!in_array($serviceType, $allowedTypes, true)) throw new RuntimeException('Tipe pelayanan tidak valid.');

    $patientCheck = $pdo->prepare('SELECT COUNT(*) FROM pasien WHERE id_pasien = ?');
    $patientCheck->execute([$patientId]);
    if (!(int)$patientCheck->fetchColumn()) throw new RuntimeException('Pasien tidak ditemukan.');

    $unitCheck = $pdo->prepare('SELECT COUNT(*) FROM unit WHERE id_unit = ?');
    $unitCheck->execute([$unitId]);
    if (!(int)$unitCheck->fetchColumn()) throw new RuntimeException('Unit/poli yang dipilih tidak ditemukan.');

    $doctorId = null;
    if (isset($visit['id_dokter']) && trim((string)$visit['id_dokter']) !== '') {
        $doctorId = filter_var($visit['id_dokter'], FILTER_VALIDATE_INT);
        if (!$doctorId || $doctorId < 1) throw new RuntimeException('Pilihan dokter tidak valid.');
        $check = $pdo->prepare('SELECT COUNT(*) FROM pegawai WHERE id_pegawai = ?');
        $check->execute([$doctorId]);
        if (!(int)$check->fetchColumn()) throw new RuntimeException('Dokter yang dipilih tidak ditemukan.');
    }

    $scheduleId = null;
    if (isset($visit['id_jadwal']) && trim((string)$visit['id_jadwal']) !== '') {
        $scheduleId = filter_var($visit['id_jadwal'], FILTER_VALIDATE_INT);
        if (!$scheduleId || $scheduleId < 1) throw new RuntimeException('Pilihan jadwal tidak valid.');
        $check = $pdo->prepare('SELECT COUNT(*) FROM jadwal_praktik WHERE id_jadwal = ?');
        $check->execute([$scheduleId]);
        if (!(int)$check->fetchColumn()) throw new RuntimeException('Jadwal praktik yang dipilih tidak ditemukan.');
    }

    // Format nomor kunjungan unik: KJ-tanggal-acak. Nomor tetap mudah dibaca dan tidak bergantung pada ID pasien.
    $noKunjungan = null;
    $checkNo = $pdo->prepare('SELECT COUNT(*) FROM kunjungan WHERE no_kunjungan = ?');
    for ($i = 0; $i < 30; $i++) {
        $candidate = 'KJ-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $checkNo->execute([$candidate]);
        if (!(int)$checkNo->fetchColumn()) { $noKunjungan = $candidate; break; }
    }
    if ($noKunjungan === null) throw new RuntimeException('Tidak dapat membuat nomor kunjungan unik. Silakan ulangi.');

    $stmt = $pdo->prepare("INSERT INTO kunjungan (id_pasien, id_unit, id_dokter, id_jadwal, no_kunjungan, tipe_pelayanan) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$patientId, $unitId, $doctorId, $scheduleId, $noKunjungan, $serviceType]);
    $visitId = (int)$pdo->lastInsertId();

    // Nomor antrean berurutan per unit per tanggal, misalnya A001, A002, dan seterusnya.
    $queueCount = $pdo->prepare("SELECT COUNT(*) FROM antrean a JOIN kunjungan k ON k.id_kunjungan = a.id_kunjungan WHERE k.id_unit = ? AND DATE(k.tanggal) = CURDATE()");
    $queueCount->execute([$unitId]);
    $nextQueue = (int)$queueCount->fetchColumn() + 1;
    $queueNumber = 'A' . str_pad((string)$nextQueue, 3, '0', STR_PAD_LEFT);

    $queue = $pdo->prepare("INSERT INTO antrean (id_kunjungan, no_antrean, status) VALUES (?, ?, 'Menunggu')");
    $queue->execute([$visitId, $queueNumber]);

    return ['id_kunjungan' => $visitId, 'no_kunjungan' => $noKunjungan, 'no_antrean' => $queueNumber];
}

$redirectBase = APP_BASE_URL . '/index.php?page=' . rawurlencode($page) . '&table=' . rawurlencode($table);

// Membuat kunjungan dan antrean bagi pasien lama yang sebelumnya baru tersimpan di tabel pasien.
if ($action === 'create_visit') {
    $patientId = filter_var($_POST['record_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$patientId || $patientId < 1) { flash_set('error', 'ID pasien tidak valid.'); redirect_to($redirectBase); }
    try {
        $pdo->beginTransaction();
        $result = simrs_create_visit_and_queue($pdo, $patientId, is_array($_POST['visit'] ?? null) ? $_POST['visit'] : []);
        audit_log($pdo, $uid, 'CREATE_VISIT', 'kunjungan', 'Pasien ID ' . $patientId . ', kunjungan ' . $result['no_kunjungan'] . ', antrean ' . $result['no_antrean']);
        $pdo->commit();
        flash_set('success', 'Kunjungan berhasil dibuat. No. kunjungan: ' . $result['no_kunjungan'] . ' · No. antrean: ' . $result['no_antrean']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('SIMRS create visit: ' . $e->getMessage());
        flash_set('error', 'Kunjungan gagal dibuat: ' . $e->getMessage());
    }
    redirect_to($redirectBase);
}

$columns = table_columns($pdo, $table);
$columnMap = [];
foreach ($columns as $c) $columnMap[$c['Field']] = $c;
$pk = table_primary($pdo, $table);
if (!$pk) { http_response_code(400); exit('Tabel tidak mempunyai primary key tunggal; pengeditan generik tidak tersedia.'); }
$fields = $_POST['fields'] ?? [];
if (!is_array($fields)) $fields = [];
$foreign = table_foreign_keys($pdo, $table);
$data = [];
$errors = [];

foreach ($columnMap as $name => $col) {
    if ($name === $pk || str_contains(strtolower($col['Extra'] ?? ''), 'auto_increment') || in_array(strtolower($name), ['password_hash', 'token', 'secret'], true)) continue;
    if (!array_key_exists($name, $fields)) continue;
    $value = is_array($fields[$name]) ? null : trim((string)$fields[$name]);
    if ($value === '' && $col['Null'] === 'YES') { $data[$name] = null; continue; }
    if ($value === '' && $col['Null'] === 'NO' && $col['Default'] === null) { $errors[] = 'Kolom ' . $name . ' wajib diisi.'; continue; }
    if ($value === '') continue;
    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|float|double)/i', $col['Type']) && !is_numeric($value)) { $errors[] = 'Kolom ' . $name . ' harus angka.'; continue; }
    if (isset($foreign[$name])) {
        $fk = $foreign[$name];
        $s = $pdo->prepare('SELECT COUNT(*) FROM `' . $fk['REFERENCED_TABLE_NAME'] . '` WHERE `' . $fk['REFERENCED_COLUMN_NAME'] . '` = ?');
        $s->execute([$value]);
        if (!(int)$s->fetchColumn()) { $errors[] = 'Referensi untuk ' . $name . ' tidak ditemukan.'; continue; }
    }
    $data[$name] = $value;
}

if ($errors) {
    flash_set('error', implode(' ', $errors));
    redirect_to($redirectBase . '&mode=' . ($action === 'create' ? 'create' : 'edit') . ($action === 'edit' ? '&id=' . rawurlencode((string)($_POST['record_id'] ?? '')) : ''));
}

try {
    if ($action === 'create') {
        if (!$data) throw new RuntimeException('Tidak ada data yang dapat disimpan.');
        $withVisit = ($page === 'registration' && $table === 'pasien' && ($_POST['with_visit'] ?? '') === '1');
        if ($withVisit) {
            $pdo->beginTransaction();
            $names = array_keys($data);
            $sql = 'INSERT INTO `' . $table . '` (' . implode(',', array_map(fn($n) => '`' . $n . '`', $names)) . ') VALUES (' . implode(',', array_fill(0, count($names), '?')) . ')';
            $s = $pdo->prepare($sql); $s->execute(array_values($data));
            $newPatientId = (int)$pdo->lastInsertId();
            $result = simrs_create_visit_and_queue($pdo, $newPatientId, is_array($_POST['visit'] ?? null) ? $_POST['visit'] : []);
            audit_log($pdo, $uid, 'CREATE_RECORD', 'pasien', 'Membuat pasien baru, ID ' . $newPatientId);
            audit_log($pdo, $uid, 'CREATE_VISIT', 'kunjungan', 'Pasien ID ' . $newPatientId . ', kunjungan ' . $result['no_kunjungan'] . ', antrean ' . $result['no_antrean']);
            $pdo->commit();
            flash_set('success', 'Registrasi berhasil. No. kunjungan: ' . $result['no_kunjungan'] . ' · No. antrean: ' . $result['no_antrean']);
        } else {
            $names = array_keys($data);
            $sql = 'INSERT INTO `' . $table . '` (' . implode(',', array_map(fn($n) => '`' . $n . '`', $names)) . ') VALUES (' . implode(',', array_fill(0, count($names), '?')) . ')';
            $s = $pdo->prepare($sql); $s->execute(array_values($data));
            $newId = (int)$pdo->lastInsertId();
            audit_log($pdo, $uid, 'CREATE_RECORD', $table, 'Membuat data baru, ID ' . $newId);
            flash_set('success', 'Data berhasil ditambahkan.');
        }
    } else {
        $id = $_POST['record_id'] ?? '';
        if ($id === '') throw new RuntimeException('ID data tidak ditemukan.');
        if (!$data) throw new RuntimeException('Tidak ada perubahan data.');
        $set = implode(',', array_map(fn($n) => '`' . $n . '` = ?', array_keys($data)));
        $s = $pdo->prepare('UPDATE `' . $table . '` SET ' . $set . ' WHERE `' . $pk . '` = ?');
        $s->execute([...array_values($data), $id]);
        audit_log($pdo, $uid, 'UPDATE_RECORD', $table, 'Memperbarui data ID ' . $id);
        flash_set('success', 'Data berhasil diperbarui.');
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('SIMRS record action: ' . $e->getMessage());
    $friendly = $e instanceof RuntimeException ? $e->getMessage() : 'Periksa kolom wajib, relasi tabel, dan konfigurasi database.';
    flash_set('error', 'Data gagal disimpan. ' . $friendly);
}

redirect_to($redirectBase);
