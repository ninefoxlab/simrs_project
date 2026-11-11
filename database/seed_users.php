<?php
// Membuat satu akun demo per role. Jalankan via CLI: php database/seed_users.php
// Password dibuat acak dan dicatat di database/AKUN_DEMO.md (lokal) atau AKUN_DEMO_CLOUD.md (DB_HOST lain).
// Keduanya tidak ikut di-commit.
if (PHP_SAPI !== 'cli') exit('CLI only');
require dirname(__DIR__) . '/config/database.php';

$accounts = [
 ['admin','Administrator SIMRS','IT Administrator',null,'IT_ADMIN'],
 ['dokter','dr. Andi Pratama','Dokter','Penyakit Dalam','DOKTER'],
 ['admisi','Siti Admisi','Petugas Admisi',null,'ADMISI'],
 ['perawat','Ns. Rina Perawat','Perawat',null,'PERAWAT'],
 ['apoteker','apt. Budi Farmasi','Apoteker',null,'APOTEKER'],
 ['atlm','Dewi Analis','ATLM',null,'ATLM'],
 ['dokterlab','dr. Lab Patologi','Dokter Laboratorium','Patologi Klinik','DOKTER_LAB'],
 ['validatorlab','Validator Lab','Validator Laboratorium',null,'VALIDATOR_LAB'],
 ['radiografer','Agus Radiografer','Radiografer',null,'RADIOGRAFER'],
 ['dokterrad','dr. Radiologi','Dokter Radiologi','Radiologi','DOKTER_RAD'],
 ['kasir','Kasir Utama','Kasir',null,'KASIR'],
 ['asuransi','Petugas Asuransi','Petugas Asuransi',null,'ASURANSI'],
 ['gudang','Petugas Gudang','Petugas Gudang',null,'PETUGAS_GUDANG'],
 ['kepalagudang','Kepala Gudang','Kepala Gudang',null,'KEPALA_GUDANG'],
 ['pimpinan','Direktur RSUD','Pimpinan',null,'PIMPINAN'],
];

$password = 'Simrs-' . bin2hex(random_bytes(4)) . '!';
$hash = password_hash($password, PASSWORD_DEFAULT);
$made = [];
foreach ($accounts as [$username, $nama, $peran, $spes, $role]) {
    $q = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username=?'); $q->execute([$username]);
    if ((int)$q->fetchColumn()) { echo "skip {$username} (sudah ada)\n"; continue; }
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO pegawai (nama,peran,spesialisasi,username) VALUES (?,?,?,?)')->execute([$nama,$peran,$spes,$username]);
    $pid = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO users (id_pegawai,username,password_hash,nama_tampilan,status_aktif,created_at,updated_at) VALUES (?,?,?,?,1,NOW(),NOW())')->execute([$pid,$username,$hash,$nama]);
    $uid = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO user_roles (id_user,id_role,is_default,assigned_at) SELECT ?,id_role,1,NOW() FROM roles WHERE kode_role=?')->execute([$uid,$role]);
    $pdo->commit();
    $made[] = "| {$username} | {$role} | {$nama} |";
    echo "ok {$username}\n";
}
if ($made) {
    $md = "# Akun demo SIMRS (lokal)\n\nPassword semua akun di bawah: `{$password}`\n\n| Username | Role | Nama |\n|---|---|---|\n" . implode("\n", $made) . "\n";
    $local = in_array(simrs_env('DB_HOST', '127.0.0.1'), ['127.0.0.1', 'localhost'], true);
    $file = $local ? 'AKUN_DEMO.md' : 'AKUN_DEMO_CLOUD.md'; // jangan timpa catatan akun lokal
    file_put_contents(__DIR__ . '/' . $file, $md);
    echo "Disimpan ke database/{$file}\n";
}
