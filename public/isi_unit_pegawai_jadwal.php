<?php
/**
 * Seeder aman untuk Unit, Pegawai (cek saja), dan Jadwal Praktik SIMRS.
 * Tidak membuat akun baru, tidak menggandakan pegawai, tidak menghapus/menimpa data.
 * Jalankan sebagai IT_ADMIN dan hapus file ini setelah selesai.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/helpers.php';
require_once dirname(__DIR__) . '/app/permissions.php';

$user = require_login($pdo);
$userId = (int)($user['id_user'] ?? 0);

function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function simrs_is_admin(PDO $pdo, int $id): bool {
    $q = $pdo->prepare("SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id_role=ur.id_role WHERE ur.id_user=? AND r.kode_role='IT_ADMIN' AND r.status_aktif=1");
    $q->execute([$id]);
    return (int)$q->fetchColumn() > 0;
}
if (!simrs_is_admin($pdo, $userId)) {
    http_response_code(403);
    exit('Akses ditolak. Jalankan halaman ini menggunakan akun IT_ADMIN.');
}

function simrs_cols(PDO $pdo, string $table): array {
    if (!preg_match('/^[a-z0-9_]+$/i', $table)) throw new RuntimeException('Nama tabel tidak valid.');
    return $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
}
function simrs_pk(array $cols): ?string {
    foreach ($cols as $c) if (($c['Key'] ?? '') === 'PRI') return $c['Field'];
    return null;
}
function simrs_enum_value(string $type, string $field, string $desired): string {
    if (!preg_match('/^enum\((.*)\)$/i', $type, $m)) return $desired;
    $choices = str_getcsv($m[1], ',', "'", "\\");
    foreach ($choices as $c) if (strcasecmp($c, $desired) === 0) return $c;
    $d = strtolower($desired); $f = strtolower($field);
    foreach ($choices as $c) {
        $x = strtolower($c);
        if (($d === 'aktif' || str_contains($f, 'status')) && (str_contains($x, 'aktif') || str_contains($x, 'active'))) return $c;
        if (str_contains($d, 'igd') && str_contains($x, 'igd')) return $c;
        if (str_contains($d, 'poliklinik') && (str_contains($x, 'poli') || str_contains($x, 'jalan'))) return $c;
        if (str_contains($d, 'penunjang') && str_contains($x, 'penunjang')) return $c;
        if (str_contains($d, 'farmasi') && str_contains($x, 'farmasi')) return $c;
        if (str_contains($d, 'rawat inap') && str_contains($x, 'rawat inap')) return $c;
        if (str_contains($d, 'tersedia') && (str_contains($x, 'tersedia') || str_contains($x, 'available'))) return $c;
    }
    return $choices[0] ?? $desired;
}
function simrs_default_for_required(array $c, string $fallback, int $index) {
    $name = strtolower($c['Field']); $type = strtolower($c['Type']);
    if (preg_match('/^enum\(/i', $type)) return simrs_enum_value($type, $name, str_contains($name, 'status') ? 'Aktif' : $fallback);
    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|float|double)/', $type)) {
        if (str_contains($name, 'aktif') || str_contains($name, 'active')) return 1;
        if (str_contains($name, 'kuota')) return 20;
        return 0;
    }
    if (preg_match('/^time/', $type)) return str_contains($name, 'akhir') || str_contains($name, 'selesai') ? '12:00:00' : '08:00:00';
    if (preg_match('/^(datetime|timestamp)/', $type)) return date('Y-m-d H:i:s');
    if (preg_match('/^date/', $type)) return date('Y-m-d');
    if (str_contains($name, 'status')) return 'Aktif';
    if (str_contains($name, 'kode')) return 'DEMO-' . str_pad((string)$index, 3, '0', STR_PAD_LEFT);
    if (str_contains($name, 'nama')) return $fallback;
    if (str_contains($name, 'jenis') || str_contains($name, 'tipe') || str_contains($name, 'kategori')) return 'Poliklinik';
    if (str_contains($name, 'lokasi') || str_contains($name, 'alamat')) return 'Area pelayanan SIMRS (data demo)';
    if (str_contains($name, 'hari')) return 'Senin-Jumat';
    if (str_contains($name, 'keterangan') || str_contains($name, 'deskripsi')) return 'Data simulasi untuk pengujian aplikasi';
    return $fallback;
}
function simrs_insert_unit(PDO $pdo, string $name, string $code, string $kind, int $index): string {
    $cols = simrs_cols($pdo, 'unit');
    $names = array_column($cols, 'Field');
    $label = in_array('nama_unit', $names, true) ? 'nama_unit' : (in_array('nama', $names, true) ? 'nama' : null);
    if (!$label) throw new RuntimeException('Tabel unit tidak memiliki kolom nama_unit atau nama.');
    $pk = simrs_pk($cols) ?? 'id_unit';
    $check = $pdo->prepare("SELECT `{$pk}` FROM unit WHERE `{$label}`=? LIMIT 1");
    $check->execute([$name]);
    if ($check->fetchColumn()) return 'sudah ada';
    if (in_array('kode_unit', $names, true)) {
        $q = $pdo->prepare('SELECT 1 FROM unit WHERE kode_unit=? LIMIT 1');
        $q->execute([$code]); if ($q->fetchColumn()) return 'sudah ada';
    }
    $explicit = [
        'kode_unit'=>$code, 'kode'=>$code,
        'nama_unit'=>$name, 'nama'=>$name,
        'jenis_unit'=>$kind, 'jenis'=>$kind, 'kategori'=>$kind,
        'lokasi'=>'Gedung pelayanan (data simulasi)', 'alamat'=>'Data simulasi - alamat belum ditetapkan',
        'status'=>'Aktif', 'aktif'=>1, 'is_active'=>1, 'status_aktif'=>1,
        'keterangan'=>'Unit contoh untuk pengujian SIMRS'
    ];
    $data=[];
    foreach ($cols as $c) {
        $field=$c['Field']; $extra=strtolower($c['Extra'] ?? '');
        if (str_contains($extra, 'auto_increment') || str_contains($extra, 'generated')) continue;
        if (array_key_exists($field, $explicit)) {
            $v=$explicit[$field];
            if (str_starts_with(strtolower($c['Type']), 'enum(')) $v=simrs_enum_value($c['Type'], $field, (string)$v);
            $data[$field]=$v;
            continue;
        }
        if ($c['Null'] === 'YES' || $c['Default'] !== null) continue;
        $data[$field]=simrs_default_for_required($c, $name, $index);
    }
    if (!$data) throw new RuntimeException('Tidak ada kolom unit yang dapat diisi.');
    $fields=array_keys($data);
    $sql='INSERT INTO unit ('.implode(',',array_map(fn($f)=>'`'.$f.'`',$fields)).') VALUES ('.implode(',',array_fill(0,count($fields),'?')).')';
    $st=$pdo->prepare($sql); $st->execute(array_values($data));
    return 'ditambahkan';
}
function simrs_get_unit_id(PDO $pdo, string $name): ?int {
    $cols=simrs_cols($pdo,'unit'); $pk=simrs_pk($cols) ?? 'id_unit'; $names=array_column($cols,'Field');
    $label=in_array('nama_unit',$names,true)?'nama_unit':(in_array('nama',$names,true)?'nama':null);
    if(!$label) return null;
    $q=$pdo->prepare("SELECT `{$pk}` FROM unit WHERE `{$label}`=? LIMIT 1"); $q->execute([$name]);
    $v=$q->fetchColumn(); return $v===false?null:(int)$v;
}
function simrs_get_doctor(PDO $pdo, string $username, string $roleCode): ?array {
    $sql="SELECT p.id_pegawai,p.nama,p.peran,p.spesialisasi,u.username,r.kode_role
          FROM pegawai p JOIN users u ON u.id_pegawai=p.id_pegawai
          JOIN user_roles ur ON ur.id_user=u.id_user JOIN roles r ON r.id_role=ur.id_role
          WHERE u.username=? AND r.kode_role=? AND u.status_aktif=1 AND r.status_aktif=1
          ORDER BY ur.is_default DESC LIMIT 1";
    $q=$pdo->prepare($sql); $q->execute([$username,$roleCode]); $r=$q->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}
function simrs_create_schedule(PDO $pdo, int $unitId, int $doctorId, string $start, string $end, int $quota): string {
    $cols=simrs_cols($pdo,'jadwal_praktik'); $names=array_column($cols,'Field');
    foreach(['id_unit','id_dokter','jam_awal','jam_akhir','kuota'] as $required) {
        if(!in_array($required,$names,true)) throw new RuntimeException("Kolom {$required} tidak ditemukan pada jadwal_praktik.");
    }
    $q=$pdo->prepare('SELECT id_jadwal FROM jadwal_praktik WHERE id_unit=? AND id_dokter=? AND jam_awal=? AND jam_akhir=? LIMIT 1');
    $q->execute([$unitId,$doctorId,$start,$end]);
    if($q->fetchColumn()) return 'sudah ada';
    $explicit=['id_unit'=>$unitId,'id_dokter'=>$doctorId,'jam_awal'=>$start,'jam_akhir'=>$end,'kuota'=>$quota,'hari'=>'Senin-Jumat','hari_praktik'=>'Senin-Jumat','status'=>'Aktif','aktif'=>1,'is_active'=>1];
    $data=[];
    foreach($cols as $c) {
        $field=$c['Field']; $extra=strtolower($c['Extra']??'');
        if(str_contains($extra,'auto_increment')||str_contains($extra,'generated')) continue;
        if(array_key_exists($field,$explicit)) {
            $v=$explicit[$field]; if(str_starts_with(strtolower($c['Type']),'enum(')) $v=simrs_enum_value($c['Type'],$field,(string)$v);
            $data[$field]=$v; continue;
        }
        if($c['Null']==='YES'||$c['Default']!==null) continue;
        $data[$field]=simrs_default_for_required($c,'Senin-Jumat',1);
    }
    $fields=array_keys($data);
    $sql='INSERT INTO jadwal_praktik ('.implode(',',array_map(fn($f)=>'`'.$f.'`',$fields)).') VALUES ('.implode(',',array_fill(0,count($fields),'?')).')';
    $st=$pdo->prepare($sql); $st->execute(array_values($data));
    return 'ditambahkan';
}

$units = [
    ['UNIT-001','Poli Umum','Poliklinik'],
    ['UNIT-002','Poli Penyakit Dalam','Poliklinik'],
    ['UNIT-003','Poli Anak','Poliklinik'],
    ['UNIT-004','Poli Bedah','Poliklinik'],
    ['UNIT-005','Poli Kebidanan dan Kandungan','Poliklinik'],
    ['UNIT-006','Instalasi Gawat Darurat','IGD'],
    ['UNIT-007','Laboratorium','Penunjang'],
    ['UNIT-008','Radiologi','Penunjang'],
    ['UNIT-009','Farmasi','Farmasi'],
    ['UNIT-010','Rawat Inap','Rawat Inap'],
    ['UNIT-011','Intensive Care Unit (ICU)','Rawat Inap'],
    ['UNIT-012','Instalasi Bedah Sentral','Penunjang'],
];
$doctorPlans = [
    ['dr.ahmad','DOKTER','Poli Penyakit Dalam','08:00:00','12:00:00',20],
    ['doklab01','DOKTER_LAB','Laboratorium','08:00:00','14:00:00',25],
    ['dokrad01','DOKTER_RAD','Radiologi','09:00:00','15:00:00',15],
];
$results=[]; $employees=[]; $scheduleResults=[]; $errors=[]; $warnings=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $errors[]='Token keamanan tidak valid. Muat ulang halaman.';
    } else {
        try {
            $pdo->beginTransaction();
            $added=0; $skipped=0;
            foreach($units as $i=>$u) {
                $status=simrs_insert_unit($pdo,$u[1],$u[0],$u[2],$i+1);
                if($status==='ditambahkan') $added++; else $skipped++;
            }
            $results=['unit_added'=>$added,'unit_skipped'=>$skipped,'unit_total'=>count($units)];

            // Ambil data pegawai dari akun yang telah dibuat sebelumnya. Tidak menambah pegawai baru agar akun-role tetap konsisten.
            $employees=$pdo->query("SELECT p.id_pegawai,p.nama,p.peran,p.spesialisasi,u.username,GROUP_CONCAT(DISTINCT r.kode_role ORDER BY r.kode_role SEPARATOR ', ') AS roles
                FROM pegawai p LEFT JOIN users u ON u.id_pegawai=p.id_pegawai
                LEFT JOIN user_roles ur ON ur.id_user=u.id_user LEFT JOIN roles r ON r.id_role=ur.id_role
                GROUP BY p.id_pegawai,p.nama,p.peran,p.spesialisasi,u.username ORDER BY p.id_pegawai")->fetchAll(PDO::FETCH_ASSOC);
            $addedSchedules=0; $skippedSchedules=0;
            foreach($doctorPlans as $plan) {
                [$username,$roleCode,$unitName,$start,$end,$quota]=$plan;
                $doctor=simrs_get_doctor($pdo,$username,$roleCode);
                $unitId=simrs_get_unit_id($pdo,$unitName);
                if(!$doctor) { $warnings[]="Pegawai {$username} dengan role {$roleCode} belum ditemukan sebagai akun aktif; jadwalnya dilewati."; continue; }
                if(!$unitId) { $warnings[]="Unit {$unitName} belum ditemukan; jadwal {$username} dilewati."; continue; }
                $status=simrs_create_schedule($pdo,$unitId,(int)$doctor['id_pegawai'],$start,$end,(int)$quota);
                if($status==='ditambahkan') $addedSchedules++; else $skippedSchedules++;
            }
            $scheduleResults=['added'=>$addedSchedules,'skipped'=>$skippedSchedules,'total'=>count($doctorPlans)];
            $pdo->commit();
            audit_log($pdo,$userId,'SEED_UNIT_EMPLOYEE_SCHEDULE','master_data','Mengisi unit dan jadwal praktik demo dengan pegawai yang terhubung ke akun role.');
        } catch(Throwable $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            $errors[]=$e->getMessage();
        }
    }
}

// Preview data pegawai saat halaman pertama dibuka juga tersedia.
if (!$employees) {
    try {
        $employees=$pdo->query("SELECT p.id_pegawai,p.nama,p.peran,p.spesialisasi,u.username,GROUP_CONCAT(DISTINCT r.kode_role ORDER BY r.kode_role SEPARATOR ', ') AS roles
            FROM pegawai p LEFT JOIN users u ON u.id_pegawai=p.id_pegawai
            LEFT JOIN user_roles ur ON ur.id_user=u.id_user LEFT JOIN roles r ON r.id_role=ur.id_role
            GROUP BY p.id_pegawai,p.nama,p.peran,p.spesialisasi,u.username ORDER BY p.id_pegawai")->fetchAll(PDO::FETCH_ASSOC);
    } catch(Throwable $e) { $warnings[]='Daftar pegawai belum dapat dibaca: '.$e->getMessage(); }
}
?><!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Isi Unit, Pegawai, Jadwal Praktik SIMRS</title>
<style>
body{font-family:Arial,sans-serif;background:#f2f6fb;color:#172b45;margin:0;padding:24px}.wrap{max-width:1050px;margin:auto}.card{background:#fff;border:1px solid #d8e3f0;border-radius:14px;padding:22px;margin-bottom:16px;box-shadow:0 5px 18px #18395b0d}h1{margin:0 0 8px}.muted{color:#60758d;font-size:13px;line-height:1.5}.note{background:#fff7df;border:1px solid #f1d78a;padding:13px;border-radius:10px;line-height:1.5}.ok{background:#e3f2ec;border:1px solid #b6dfcb;color:#145c3b;padding:12px;border-radius:9px;margin:10px 0}.err{background:#fff0f0;border:1px solid #efc2c2;color:#9b2828;padding:12px;border-radius:9px;margin:10px 0}.warn{background:#fff7df;border:1px solid #f1d78a;color:#705212;padding:12px;border-radius:9px;margin:10px 0}table{border-collapse:collapse;width:100%;margin-top:12px}th,td{border-bottom:1px solid #e3eaf2;padding:9px;text-align:left;font-size:14px}th{background:#eef4fb}.btn{border:0;border-radius:9px;background:#1d4f91;color:#fff;padding:12px 18px;font-weight:700;cursor:pointer}.btn:hover{background:#153e75}.pill{display:inline-block;background:#e9f2fb;color:#1d4f91;border-radius:20px;padding:3px 8px;font-size:12px;font-weight:700}.actions{margin-top:16px}
</style></head><body><main class="wrap">
<section class="card"><h1>Pengisian Unit, Pegawai, dan Jadwal Praktik</h1><p>File ini menyiapkan unit pelayanan dan membuat jadwal praktik dengan memakai pegawai dokter yang sudah terhubung ke akun role. Data pegawai tidak dibuat ulang agar tidak terjadi duplikasi akun atau pegawai.</p>
<div class="note"><b>Catatan struktur:</b> tabel <code>jadwal_praktik</code> yang terlihat di database berisi <code>id_unit</code>, <code>id_dokter</code>, <code>jam_awal</code>, <code>jam_akhir</code>, dan <code>kuota</code>. Belum terlihat kolom hari praktik, jadi jadwal demo hanya mengatur jam dan kuota, belum membedakan Senin–Minggu.</div></section>
<?php foreach($errors as $m): ?><div class="err"><?=h($m)?></div><?php endforeach; ?>
<?php foreach($warnings as $m): ?><div class="warn"><?=h($m)?></div><?php endforeach; ?>
<?php if($results): ?><section class="card"><h2>Hasil pengisian</h2><div class="ok">Unit ditambahkan: <b><?=h($results['unit_added'])?></b> · Unit sudah ada: <b><?=h($results['unit_skipped'])?></b> · Jadwal ditambahkan: <b><?=h($scheduleResults['added']??0)?></b> · Jadwal sudah ada: <b><?=h($scheduleResults['skipped']??0)?></b></div></section><?php endif; ?>
<section class="card"><h2>Data unit yang disiapkan</h2><table><thead><tr><th>Kode</th><th>Nama Unit</th><th>Jenis</th></tr></thead><tbody><?php foreach($units as $u): ?><tr><td><?=h($u[0])?></td><td><?=h($u[1])?></td><td><?=h($u[2])?></td></tr><?php endforeach; ?></tbody></table></section>
<section class="card"><h2>Data pegawai yang sudah ada</h2><p class="muted">Diambil dari tabel <code>pegawai</code> dan dihubungkan ke <code>users</code> serta role. Data ini tidak diubah.</p><table><thead><tr><th>ID Pegawai</th><th>Nama</th><th>Jabatan</th><th>Spesialisasi</th><th>Username</th><th>Role</th></tr></thead><tbody>
<?php if(!$employees): ?><tr><td colspan="6">Belum ada data pegawai. Jalankan pembuatan akun role terlebih dahulu.</td></tr><?php endif; ?>
<?php foreach($employees as $p): ?><tr><td><?=h($p['id_pegawai'])?></td><td><?=h($p['nama'])?></td><td><?=h($p['peran'])?></td><td><?=h($p['spesialisasi'])?></td><td><?=h($p['username'])?></td><td><span class="pill"><?=h($p['roles'])?></span></td></tr><?php endforeach; ?>
</tbody></table></section>
<section class="card"><h2>Jadwal praktik yang akan dibuat</h2><table><thead><tr><th>Username dokter</th><th>Unit</th><th>Jam mulai</th><th>Jam selesai</th><th>Kuota</th></tr></thead><tbody>
<?php foreach($doctorPlans as $p): ?><tr><td><?=h($p[0])?> <span class="pill"><?=h($p[1])?></span></td><td><?=h($p[2])?></td><td><?=h($p[3])?></td><td><?=h($p[4])?></td><td><?=h($p[5])?></td></tr><?php endforeach; ?>
</tbody></table>
<form method="post" class="actions" onsubmit="return confirm('Masukkan unit demo dan jadwal praktik ke database? Data yang sudah ada tidak akan ditimpa.');"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><button class="btn" type="submit">Masukkan Unit dan Jadwal Praktik</button></form>
<p class="muted">Jalankan sebagai IT_ADMIN. Setelah selesai, periksa tabel <code>unit</code>, <code>pegawai</code>, dan <code>jadwal_praktik</code> di phpMyAdmin. Kemudian hapus file ini dari folder public.</p></section>
</main></body></html>
