<?php
/**
 * Seeder master data demo SIMRS RSUD NTB.
 * Jalankan satu kali oleh IT_ADMIN. Tidak menghapus atau menimpa data yang sudah ada.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/helpers.php';
require_once dirname(__DIR__) . '/app/schema.php';

$user = require_login($pdo);
$userId = (int)($user['id_user'] ?? 0);

function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function is_it_admin(PDO $pdo, int $userId): bool {
    $q = $pdo->prepare("SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id_role=ur.id_role WHERE ur.id_user=? AND r.kode_role='IT_ADMIN'");
    $q->execute([$userId]);
    return (int)$q->fetchColumn() > 0;
}
if (!is_it_admin($pdo, $userId)) {
    http_response_code(403);
    exit('Akses ditolak. Halaman ini hanya untuk IT_ADMIN.');
}

function master_columns(PDO $pdo, string $table): array {
    if (!preg_match('/^[a-z0-9_]+$/i', $table)) throw new RuntimeException('Nama tabel tidak valid.');
    return $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
}
function master_foreign_keys(PDO $pdo, string $table): array {
    $q = $pdo->prepare("SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL");
    $q->execute([$table]);
    $out=[];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['COLUMN_NAME']]=$r;
    return $out;
}
function master_label_column(PDO $pdo, string $table): ?string {
    $cols=master_columns($pdo,$table); $names=array_column($cols,'Field');
    foreach (['nama_unit','nama_penjamin','nama_supplier','nama_gudang','nama_barang','nama_obat','nama_pemeriksaan','nama_kamar','nama','nama_tampilan','kode_unit','kode','username'] as $n) {
        if (in_array($n,$names,true)) return $n;
    }
    return null;
}
function foreign_value(PDO $pdo, array $fk, ?string $hint=null) {
    $rt=$fk['REFERENCED_TABLE_NAME']; $rc=$fk['REFERENCED_COLUMN_NAME'];
    if (!preg_match('/^[a-z0-9_]+$/i',$rt) || !preg_match('/^[a-z0-9_]+$/i',$rc)) return null;
    $label=master_label_column($pdo,$rt);
    if ($hint !== null && $label !== null) {
        $q=$pdo->prepare("SELECT `{$rc}` FROM `{$rt}` WHERE `{$label}` LIKE ? ORDER BY `{$rc}` LIMIT 1");
        $q->execute(['%'.$hint.'%']);
        $found=$q->fetchColumn();
        if ($found !== false) return $found;
    }
    $q=$pdo->query("SELECT `{$rc}` FROM `{$rt}` ORDER BY `{$rc}` LIMIT 1");
    $found=$q->fetchColumn();
    return $found === false ? null : $found;
}
function enum_default(string $type, string $field): ?string {
    if (!preg_match('/^enum\((.*)\)$/i', $type, $m)) return null;
    $choices=str_getcsv($m[1], ',', "'", "\\");
    if (!$choices) return null;
    $preferred = strtolower($field)==='status' ? ['aktif','tersedia','menunggu','baik','ready'] : [];
    foreach ($preferred as $p) foreach ($choices as $c) if (strtolower($c)===$p) return $c;
    return $choices[0];
}
function normalize_explicit_value(array $col, $value) {
    $type=strtolower($col['Type']);
    if (preg_match('/^enum\((.*)\)$/i', $type, $m)) {
        $choices=str_getcsv($m[1], ',', "'", "\\");
        foreach ($choices as $choice) if (strcasecmp((string)$choice,(string)$value)===0) return $choice;
        // Jika nilai contoh tidak persis sama dengan pilihan ENUM pada database,
        // gunakan opsi yang paling mendekati atau opsi pertama yang valid.
        $v=strtolower((string)$value);
        foreach ($choices as $choice) {
            $c=strtolower($choice);
            if (($v==='aktif' && (str_contains($c,'aktif') || str_contains($c,'active'))) ||
                (str_contains($v,'igd') && str_contains($c,'igd')) ||
                (str_contains($v,'rawat inap') && str_contains($c,'rawat inap')) ||
                (str_contains($v,'poliklinik') && (str_contains($c,'poli') || str_contains($c,'rawat jalan'))) ||
                (str_contains($v,'tersedia') && (str_contains($c,'tersedia') || str_contains($c,'available')))) return $choice;
        }
        return enum_default($type, strtolower($col['Field'])) ?? ($choices[0] ?? $value);
    }
    return $value;
}
function fallback_value(array $col, int $index) {
    $field=strtolower($col['Field']); $type=strtolower($col['Type']);
    if (str_starts_with($type,'enum(')) return enum_default($type,$field);
    if (preg_match('/(datetime|timestamp)/',$type)) return date('Y-m-d H:i:s');
    if (preg_match('/^date/',$type)) return date('Y-m-d');
    if (preg_match('/^time/',$type)) return '08:00:00';
    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|float|double)/',$type)) {
        if (str_contains($field,'aktif') || str_contains($field,'is_active')) return 1;
        if (str_contains($field,'kapasitas') || str_contains($field,'stok') || str_contains($field,'jumlah')) return 0;
        return 0;
    }
    if (str_contains($field,'status')) return 'Aktif';
    if (str_contains($field,'kode')) return 'DEMO-'.strtoupper((string)$index);
    if (str_contains($field,'nama')) return 'Data Demo '.$index;
    if (str_contains($field,'alamat') || str_contains($field,'lokasi')) return 'Data demo SIMRS';
    if (str_contains($field,'email')) return 'demo@example.invalid';
    if (str_contains($field,'telp') || str_contains($field,'telepon') || str_contains($field,'phone')) return '0000000000';
    if (str_contains($field,'jenis') || str_contains($field,'tipe') || str_contains($field,'kategori')) return 'Umum';
    if (str_contains($field,'satuan')) return 'Unit';
    if (str_contains($field,'keterangan') || str_contains($field,'deskripsi')) return 'Data simulasi untuk pengujian SIMRS';
    return 'Data Demo '.$index;
}
function row_exists(PDO $pdo, string $table, array $data, array $candidateFields): bool {
    foreach ($candidateFields as $field) {
        if (!array_key_exists($field,$data)) continue;
        $q=$pdo->prepare("SELECT 1 FROM `{$table}` WHERE `{$field}`=? LIMIT 1");
        $q->execute([$data[$field]]);
        if ($q->fetchColumn()) return true;
    }
    return false;
}
function insert_demo_row(PDO $pdo, string $table, array $record, int $index): string {
    $columns=master_columns($pdo,$table);
    $fks=master_foreign_keys($pdo,$table);
    $values=$record['values'] ?? [];
    $hints=$record['foreign'] ?? [];
    $data=[];
    foreach ($columns as $col) {
        $name=$col['Field']; $lower=strtolower($name); $extra=strtolower($col['Extra'] ?? '');
        if (str_contains($extra,'auto_increment') || str_contains($extra,'generated')) continue;
        if (array_key_exists($name,$values)) { $data[$name]=normalize_explicit_value($col,$values[$name]); continue; }
        if (isset($fks[$name]) && (array_key_exists($name,$hints) || ($col['Null']==='NO' && $col['Default']===null))) {
            $v=foreign_value($pdo,$fks[$name],$hints[$name] ?? null);
            if ($v !== null) { $data[$name]=$v; continue; }
            if ($col['Null']==='NO' && $col['Default']===null) throw new RuntimeException("Relasi wajib {$name} belum memiliki data induk.");
        }
        if ($col['Null']==='YES' || $col['Default']!==null) continue;
        $data[$name]=fallback_value($col,$index);
    }
    if (!$data) throw new RuntimeException('Tidak ada kolom yang dapat diisi.');
    $labelCandidates=['nama_unit','nama_penjamin','nama_supplier','nama_gudang','nama_barang','nama_obat','nama_pemeriksaan','nama_kamar','nama','kode_unit','kode_penjamin','kode_supplier','kode_gudang','kode_barang','kode_obat','kode_parameter','kode'];
    if (row_exists($pdo,$table,$data,$labelCandidates)) return 'sudah ada';
    $names=array_keys($data);
    $sql='INSERT INTO `'.$table.'` ('.implode(',',array_map(fn($n)=>'`'.$n.'`',$names)).') VALUES ('.implode(',',array_fill(0,count($names),'?')).')';
    $q=$pdo->prepare($sql); $q->execute(array_values($data));
    return 'ditambahkan';
}

// Daftar data di bawah adalah data simulasi, bukan master resmi rumah sakit.
$seed = [
 'unit' => [
  ['values'=>['kode_unit'=>'UNIT-001','nama_unit'=>'Poli Umum','nama'=>'Poli Umum','jenis_unit'=>'Poliklinik','jenis'=>'Poliklinik','kategori'=>'Rawat Jalan','lokasi'=>'Gedung Rawat Jalan','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_unit'=>'UNIT-002','nama_unit'=>'Poli Penyakit Dalam','nama'=>'Poli Penyakit Dalam','jenis_unit'=>'Poliklinik','jenis'=>'Poliklinik','kategori'=>'Rawat Jalan','lokasi'=>'Gedung Rawat Jalan','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_unit'=>'UNIT-003','nama_unit'=>'Poli Anak','nama'=>'Poli Anak','jenis_unit'=>'Poliklinik','jenis'=>'Poliklinik','kategori'=>'Rawat Jalan','lokasi'=>'Gedung Rawat Jalan','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_unit'=>'UNIT-004','nama_unit'=>'Poli Bedah','nama'=>'Poli Bedah','jenis_unit'=>'Poliklinik','jenis'=>'Poliklinik','kategori'=>'Rawat Jalan','lokasi'=>'Gedung Rawat Jalan','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_unit'=>'UNIT-005','nama_unit'=>'Poli Kebidanan dan Kandungan','nama'=>'Poli Kebidanan dan Kandungan','jenis_unit'=>'Poliklinik','jenis'=>'Poliklinik','kategori'=>'Rawat Jalan','lokasi'=>'Gedung Rawat Jalan','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_unit'=>'UNIT-006','nama_unit'=>'Instalasi Gawat Darurat','nama'=>'Instalasi Gawat Darurat','jenis_unit'=>'IGD','jenis'=>'IGD','kategori'=>'Darurat','lokasi'=>'Gedung IGD','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_unit'=>'UNIT-007','nama_unit'=>'Laboratorium','nama'=>'Laboratorium','jenis_unit'=>'Penunjang','jenis'=>'Penunjang','kategori'=>'Laboratorium','lokasi'=>'Gedung Penunjang','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_unit'=>'UNIT-008','nama_unit'=>'Radiologi','nama'=>'Radiologi','jenis_unit'=>'Penunjang','jenis'=>'Penunjang','kategori'=>'Radiologi','lokasi'=>'Gedung Penunjang','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_unit'=>'UNIT-009','nama_unit'=>'Farmasi','nama'=>'Farmasi','jenis_unit'=>'Farmasi','jenis'=>'Farmasi','kategori'=>'Farmasi','lokasi'=>'Gedung Farmasi','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_unit'=>'UNIT-010','nama_unit'=>'Rawat Inap','nama'=>'Rawat Inap','jenis_unit'=>'Rawat Inap','jenis'=>'Rawat Inap','kategori'=>'Rawat Inap','lokasi'=>'Gedung Rawat Inap','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_unit'=>'UNIT-011','nama_unit'=>'Intensive Care Unit (ICU)','nama'=>'Intensive Care Unit (ICU)','jenis_unit'=>'Rawat Inap','jenis'=>'Rawat Inap','kategori'=>'ICU','lokasi'=>'Gedung Rawat Inap','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_unit'=>'UNIT-012','nama_unit'=>'Instalasi Bedah Sentral','nama'=>'Instalasi Bedah Sentral','jenis_unit'=>'Penunjang','jenis'=>'Penunjang','kategori'=>'Tindakan','lokasi'=>'Gedung Operasi','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
 ],
 'penjamin' => [
  ['values'=>['kode_penjamin'=>'PJM-001','nama_penjamin'=>'Umum / Pribadi','nama'=>'Umum / Pribadi','jenis_penjamin'=>'Umum','jenis'=>'Umum','kategori'=>'Umum','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_penjamin'=>'PJM-002','nama_penjamin'=>'BPJS Kesehatan','nama'=>'BPJS Kesehatan','jenis_penjamin'=>'BPJS','jenis'=>'BPJS','kategori'=>'JKN','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_penjamin'=>'PJM-003','nama_penjamin'=>'Jasa Raharja','nama'=>'Jasa Raharja','jenis_penjamin'=>'Asuransi','jenis'=>'Asuransi','kategori'=>'Kecelakaan','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_penjamin'=>'PJM-004','nama_penjamin'=>'Asuransi Swasta Demo','nama'=>'Asuransi Swasta Demo','jenis_penjamin'=>'Asuransi','jenis'=>'Asuransi','kategori'=>'Asuransi Swasta','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_penjamin'=>'PJM-005','nama_penjamin'=>'Jaminan Perusahaan Demo','nama'=>'Jaminan Perusahaan Demo','jenis_penjamin'=>'Perusahaan','jenis'=>'Perusahaan','kategori'=>'Perusahaan','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
 ],
 'supplier' => [
  ['values'=>['kode_supplier'=>'SUP-001','nama_supplier'=>'PT Kimia Farma Trading & Distribution (Demo)','nama'=>'PT Kimia Farma Trading & Distribution (Demo)','nama_perusahaan'=>'PT Kimia Farma Trading & Distribution (Demo)','alamat'=>'Data simulasi - alamat pemasok belum diverifikasi','no_telp'=>'0000000001','telepon'=>'0000000001','email'=>'supplier1@example.invalid','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_supplier'=>'SUP-002','nama_supplier'=>'PT Enseval Putera Megatrading (Demo)','nama'=>'PT Enseval Putera Megatrading (Demo)','nama_perusahaan'=>'PT Enseval Putera Megatrading (Demo)','alamat'=>'Data simulasi - alamat pemasok belum diverifikasi','no_telp'=>'0000000002','telepon'=>'0000000002','email'=>'supplier2@example.invalid','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_supplier'=>'SUP-003','nama_supplier'=>'PT Anugrah Argon Medica (Demo)','nama'=>'PT Anugrah Argon Medica (Demo)','nama_perusahaan'=>'PT Anugrah Argon Medica (Demo)','alamat'=>'Data simulasi - alamat pemasok belum diverifikasi','no_telp'=>'0000000003','telepon'=>'0000000003','email'=>'supplier3@example.invalid','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
 ],
 'gudang' => [
  ['values'=>['kode_gudang'=>'GDG-001','nama_gudang'=>'Gudang Farmasi','nama'=>'Gudang Farmasi','jenis_gudang'=>'Obat','jenis'=>'Obat','lokasi'=>'Area logistik farmasi','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_gudang'=>'GDG-002','nama_gudang'=>'Gudang BMHP','nama'=>'Gudang BMHP','jenis_gudang'=>'BMHP','jenis'=>'BMHP','lokasi'=>'Area logistik medis','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_gudang'=>'GDG-003','nama_gudang'=>'Gudang Alat Kesehatan','nama'=>'Gudang Alat Kesehatan','jenis_gudang'=>'Alat Kesehatan','jenis'=>'Alat Kesehatan','lokasi'=>'Area logistik alat kesehatan','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_gudang'=>'GDG-004','nama_gudang'=>'Gudang Umum dan ATK','nama'=>'Gudang Umum dan ATK','jenis_gudang'=>'Umum','jenis'=>'Umum','lokasi'=>'Area logistik umum','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
 ],
 'obat' => [
  ['values'=>['kode_obat'=>'OBT-001','nama_obat'=>'Paracetamol 500 mg','nama'=>'Paracetamol 500 mg','kategori'=>'Analgesik-antipiretik','satuan'=>'Tablet','harga'=>500,'stok_saat_ini'=>300,'stok_minimum'=>50,'status'=>'Aktif','aktif'=>1,'is_active'=>1],'foreign'=>['id_supplier'=>'Kimia Farma']],
  ['values'=>['kode_obat'=>'OBT-002','nama_obat'=>'Amoxicillin 500 mg','nama'=>'Amoxicillin 500 mg','kategori'=>'Antibiotik','satuan'=>'Kapsul','harga'=>1200,'stok_saat_ini'=>200,'stok_minimum'=>40,'status'=>'Aktif','aktif'=>1,'is_active'=>1],'foreign'=>['id_supplier'=>'Enseval']],
  ['values'=>['kode_obat'=>'OBT-003','nama_obat'=>'Omeprazole 20 mg','nama'=>'Omeprazole 20 mg','kategori'=>'Obat saluran cerna','satuan'=>'Kapsul','harga'=>900,'stok_saat_ini'=>120,'stok_minimum'=>25,'status'=>'Aktif','aktif'=>1,'is_active'=>1],'foreign'=>['id_supplier'=>'Anugrah']],
  ['values'=>['kode_obat'=>'OBT-004','nama_obat'=>'Cetirizine 10 mg','nama'=>'Cetirizine 10 mg','kategori'=>'Antihistamin','satuan'=>'Tablet','harga'=>700,'stok_saat_ini'=>150,'stok_minimum'=>30,'status'=>'Aktif','aktif'=>1,'is_active'=>1],'foreign'=>['id_supplier'=>'Kimia Farma']],
  ['values'=>['kode_obat'=>'OBT-005','nama_obat'=>'Ibuprofen 400 mg','nama'=>'Ibuprofen 400 mg','kategori'=>'Anti-inflamasi','satuan'=>'Tablet','harga'=>800,'stok_saat_ini'=>100,'stok_minimum'=>20,'status'=>'Aktif','aktif'=>1,'is_active'=>1],'foreign'=>['id_supplier'=>'Enseval']],
  ['values'=>['kode_obat'=>'OBT-006','nama_obat'=>'ORS / Oralit','nama'=>'ORS / Oralit','kategori'=>'Rehidrasi','satuan'=>'Sachet','harga'=>1500,'stok_saat_ini'=>180,'stok_minimum'=>30,'status'=>'Aktif','aktif'=>1,'is_active'=>1],'foreign'=>['id_supplier'=>'Anugrah']],
  ['values'=>['kode_obat'=>'OBT-007','nama_obat'=>'Antasida tablet','nama'=>'Antasida tablet','kategori'=>'Antasida','satuan'=>'Tablet','harga'=>400,'stok_saat_ini'=>250,'stok_minimum'=>50,'status'=>'Aktif','aktif'=>1,'is_active'=>1],'foreign'=>['id_supplier'=>'Kimia Farma']],
  ['values'=>['kode_obat'=>'OBT-008','nama_obat'=>'Vitamin B kompleks','nama'=>'Vitamin B kompleks','kategori'=>'Vitamin','satuan'=>'Tablet','harga'=>600,'stok_saat_ini'=>140,'stok_minimum'=>25,'status'=>'Aktif','aktif'=>1,'is_active'=>1],'foreign'=>['id_supplier'=>'Enseval']],
 ],
 'parameter_lab' => [
  ['values'=>['kode_parameter'=>'LAB-001','nama_pemeriksaan'=>'Hemoglobin (Hb)','nama_parameter'=>'Hemoglobin (Hb)','nama'=>'Hemoglobin (Hb)','kategori'=>'Hematologi','satuan'=>'g/dL','nilai_rujukan'=>'Isi sesuai rentang rujukan laboratorium RS','rentang_rujukan'=>'Isi sesuai rentang rujukan laboratorium RS','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_parameter'=>'LAB-002','nama_pemeriksaan'=>'Leukosit','nama_parameter'=>'Leukosit','nama'=>'Leukosit','kategori'=>'Hematologi','satuan'=>'sel/µL','nilai_rujukan'=>'Isi sesuai rentang rujukan laboratorium RS','rentang_rujukan'=>'Isi sesuai rentang rujukan laboratorium RS','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_parameter'=>'LAB-003','nama_pemeriksaan'=>'Trombosit','nama_parameter'=>'Trombosit','nama'=>'Trombosit','kategori'=>'Hematologi','satuan'=>'sel/µL','nilai_rujukan'=>'Isi sesuai rentang rujukan laboratorium RS','rentang_rujukan'=>'Isi sesuai rentang rujukan laboratorium RS','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_parameter'=>'LAB-004','nama_pemeriksaan'=>'Hematokrit (Ht)','nama_parameter'=>'Hematokrit (Ht)','nama'=>'Hematokrit (Ht)','kategori'=>'Hematologi','satuan'=>'%','nilai_rujukan'=>'Isi sesuai rentang rujukan laboratorium RS','rentang_rujukan'=>'Isi sesuai rentang rujukan laboratorium RS','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_parameter'=>'LAB-005','nama_pemeriksaan'=>'Glukosa Darah Sewaktu','nama_parameter'=>'Glukosa Darah Sewaktu','nama'=>'Glukosa Darah Sewaktu','kategori'=>'Kimia Klinik','satuan'=>'mg/dL','nilai_rujukan'=>'Isi sesuai rentang rujukan laboratorium RS','rentang_rujukan'=>'Isi sesuai rentang rujukan laboratorium RS','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_parameter'=>'LAB-006','nama_pemeriksaan'=>'Ureum','nama_parameter'=>'Ureum','nama'=>'Ureum','kategori'=>'Kimia Klinik','satuan'=>'mg/dL','nilai_rujukan'=>'Isi sesuai rentang rujukan laboratorium RS','rentang_rujukan'=>'Isi sesuai rentang rujukan laboratorium RS','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_parameter'=>'LAB-007','nama_pemeriksaan'=>'Kreatinin','nama_parameter'=>'Kreatinin','nama'=>'Kreatinin','kategori'=>'Kimia Klinik','satuan'=>'mg/dL','nilai_rujukan'=>'Isi sesuai rentang rujukan laboratorium RS','rentang_rujukan'=>'Isi sesuai rentang rujukan laboratorium RS','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_parameter'=>'LAB-008','nama_pemeriksaan'=>'SGOT (AST)','nama_parameter'=>'SGOT (AST)','nama'=>'SGOT (AST)','kategori'=>'Kimia Klinik','satuan'=>'U/L','nilai_rujukan'=>'Isi sesuai rentang rujukan laboratorium RS','rentang_rujukan'=>'Isi sesuai rentang rujukan laboratorium RS','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
  ['values'=>['kode_parameter'=>'LAB-009','nama_pemeriksaan'=>'SGPT (ALT)','nama_parameter'=>'SGPT (ALT)','nama'=>'SGPT (ALT)','kategori'=>'Kimia Klinik','satuan'=>'U/L','nilai_rujukan'=>'Isi sesuai rentang rujukan laboratorium RS','rentang_rujukan'=>'Isi sesuai rentang rujukan laboratorium RS','status'=>'Aktif','aktif'=>1,'is_active'=>1]],
 ],
 'kamar' => [
  ['values'=>['kode_kamar'=>'KMR-MEL-101','nomor_kamar'=>'MEL-101','no_kamar'=>'MEL-101','nama_kamar'=>'Melati 101','nama'=>'Melati 101','kelas'=>'III','jenis_kamar'=>'Rawat Inap','kapasitas'=>4,'jumlah_bed'=>4,'status'=>'Tersedia','keterangan'=>'Kamar demo; kapasitas simulasi'],'foreign'=>['id_unit'=>'Rawat Inap']],
  ['values'=>['kode_kamar'=>'KMR-MEL-102','nomor_kamar'=>'MEL-102','no_kamar'=>'MEL-102','nama_kamar'=>'Melati 102','nama'=>'Melati 102','kelas'=>'III','jenis_kamar'=>'Rawat Inap','kapasitas'=>4,'jumlah_bed'=>4,'status'=>'Tersedia','keterangan'=>'Kamar demo; kapasitas simulasi'],'foreign'=>['id_unit'=>'Rawat Inap']],
  ['values'=>['kode_kamar'=>'KMR-CEM-201','nomor_kamar'=>'CEM-201','no_kamar'=>'CEM-201','nama_kamar'=>'Cempaka 201','nama'=>'Cempaka 201','kelas'=>'II','jenis_kamar'=>'Rawat Inap','kapasitas'=>2,'jumlah_bed'=>2,'status'=>'Tersedia','keterangan'=>'Kamar demo; kapasitas simulasi'],'foreign'=>['id_unit'=>'Rawat Inap']],
  ['values'=>['kode_kamar'=>'KMR-VIP-001','nomor_kamar'=>'VIP-001','no_kamar'=>'VIP-001','nama_kamar'=>'Ruang VIP 001','nama'=>'Ruang VIP 001','kelas'=>'VIP','jenis_kamar'=>'Rawat Inap','kapasitas'=>1,'jumlah_bed'=>1,'status'=>'Tersedia','keterangan'=>'Kamar demo; kapasitas simulasi'],'foreign'=>['id_unit'=>'Rawat Inap']],
  ['values'=>['kode_kamar'=>'KMR-ICU-001','nomor_kamar'=>'ICU-001','no_kamar'=>'ICU-001','nama_kamar'=>'ICU 001','nama'=>'ICU 001','kelas'=>'ICU','jenis_kamar'=>'ICU','kapasitas'=>1,'jumlah_bed'=>1,'status'=>'Tersedia','keterangan'=>'Kamar demo; kapasitas simulasi'],'foreign'=>['id_unit'=>'ICU']],
 ],
 'barang_persediaan' => [
  ['values'=>['kode_barang'=>'BRG-001','nama_barang'=>'Masker medis 3-ply','nama'=>'Masker medis 3-ply','kategori'=>'BMHP','jenis_barang'=>'BMHP','satuan'=>'Box','stok_saat_ini'=>100,'stok_minimum'=>20,'harga_satuan'=>25000,'harga'=>25000,'status'=>'Aktif','aktif'=>1,'is_active'=>1],'foreign'=>['id_gudang'=>'Gudang BMHP']],
  ['values'=>['kode_barang'=>'BRG-002','nama_barang'=>'Sarung tangan pemeriksaan','nama'=>'Sarung tangan pemeriksaan','kategori'=>'BMHP','jenis_barang'=>'BMHP','satuan'=>'Box','stok_saat_ini'=>80,'stok_minimum'=>15,'harga_satuan'=>45000,'harga'=>45000,'status'=>'Aktif','aktif'=>1,'is_active'=>1],'foreign'=>['id_gudang'=>'Gudang BMHP']],
  ['values'=>['kode_barang'=>'BRG-003','nama_barang'=>'Spuit 3 mL','nama'=>'Spuit 3 mL','kategori'=>'BMHP','jenis_barang'=>'BMHP','satuan'=>'Box','stok_saat_ini'=>60,'stok_minimum'=>10,'harga_satuan'=>35000,'harga'=>35000,'status'=>'Aktif','aktif'=>1,'is_active'=>1],'foreign'=>['id_gudang'=>'Gudang BMHP']],
  ['values'=>['kode_barang'=>'BRG-004','nama_barang'=>'Infusion set','nama'=>'Infusion set','kategori'=>'BMHP','jenis_barang'=>'BMHP','satuan'=>'Pcs','stok_saat_ini'=>150,'stok_minimum'=>30,'harga_satuan'=>8000,'harga'=>8000,'status'=>'Aktif','aktif'=>1,'is_active'=>1],'foreign'=>['id_gudang'=>'Gudang BMHP']],
  ['values'=>['kode_barang'=>'BRG-005','nama_barang'=>'Kasa steril','nama'=>'Kasa steril','kategori'=>'BMHP','jenis_barang'=>'BMHP','satuan'=>'Pack','stok_saat_ini'=>120,'stok_minimum'=>25,'harga_satuan'=>12000,'harga'=>12000,'status'=>'Aktif','aktif'=>1,'is_active'=>1],'foreign'=>['id_gudang'=>'Gudang BMHP']],
  ['values'=>['kode_barang'=>'BRG-006','nama_barang'=>'Alkohol swab','nama'=>'Alkohol swab','kategori'=>'BMHP','jenis_barang'=>'BMHP','satuan'=>'Box','stok_saat_ini'=>50,'stok_minimum'=>10,'harga_satuan'=>18000,'harga'=>18000,'status'=>'Aktif','aktif'=>1,'is_active'=>1],'foreign'=>['id_gudang'=>'Gudang BMHP']],
  ['values'=>['kode_barang'=>'BRG-007','nama_barang'=>'Cairan disinfektan permukaan','nama'=>'Cairan disinfektan permukaan','kategori'=>'Bahan kebersihan','jenis_barang'=>'Kebersihan','satuan'=>'Liter','stok_saat_ini'=>40,'stok_minimum'=>8,'harga_satuan'=>30000,'harga'=>30000,'status'=>'Aktif','aktif'=>1,'is_active'=>1],'foreign'=>['id_gudang'=>'Gudang Umum']],
  ['values'=>['kode_barang'=>'BRG-008','nama_barang'=>'Kertas A4','nama'=>'Kertas A4','kategori'=>'ATK','jenis_barang'=>'ATK','satuan'=>'Rim','stok_saat_ini'=>75,'stok_minimum'=>15,'harga_satuan'=>55000,'harga'=>55000,'status'=>'Aktif','aktif'=>1,'is_active'=>1],'foreign'=>['id_gudang'=>'Gudang Umum']],
  ['values'=>['kode_barang'=>'BRG-009','nama_barang'=>'Pulpen biru','nama'=>'Pulpen biru','kategori'=>'ATK','jenis_barang'=>'ATK','satuan'=>'Box','stok_saat_ini'=>25,'stok_minimum'=>5,'harga_satuan'=>35000,'harga'=>35000,'status'=>'Aktif','aktif'=>1,'is_active'=>1],'foreign'=>['id_gudang'=>'Gudang Umum']],
  ['values'=>['kode_barang'=>'BRG-010','nama_barang'=>'Kantong sampah medis','nama'=>'Kantong sampah medis','kategori'=>'Kebersihan','jenis_barang'=>'Kebersihan','satuan'=>'Pack','stok_saat_ini'=>45,'stok_minimum'=>10,'harga_satuan'=>22000,'harga'=>22000,'status'=>'Aktif','aktif'=>1,'is_active'=>1],'foreign'=>['id_gudang'=>'Gudang Umum']],
 ],
];

$results=[]; $errors=[]; $employeeCount=0;
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!csrf_check($_POST['csrf'] ?? null)) throw new RuntimeException('Token keamanan tidak valid. Muat ulang halaman dan coba kembali.');
        $pdo->beginTransaction();
        foreach ($seed as $table=>$records) {
            try { master_columns($pdo,$table); }
            catch (Throwable $e) { $errors[]="Tabel {$table} tidak ditemukan: ".$e->getMessage(); continue; }
            $added=0; $skipped=0;
            foreach ($records as $i=>$record) {
                try {
                    $status=insert_demo_row($pdo,$table,$record,$i+1);
                    if ($status==='ditambahkan') $added++; else $skipped++;
                } catch (Throwable $e) {
                    $errors[]="{$table} / data ke-".($i+1).": ".$e->getMessage();
                }
            }
            $results[$table]=['added'=>$added,'skipped'=>$skipped,'total'=>count($records)];
        }
        // Pegawai tidak ditambah otomatis agar tidak membuat pegawai tanpa akun login.
        try { $employeeCount=(int)$pdo->query('SELECT COUNT(*) FROM pegawai')->fetchColumn(); }
        catch (Throwable $e) { $employeeCount=0; $errors[]='Tabel pegawai tidak dapat dibaca: '.$e->getMessage(); }
        $pdo->commit();
        audit_log($pdo,$userId,'SEED_MASTER_DEMO','master_data','Mengisi data master demo SIMRS');
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $errors[]=$e->getMessage();
}

?><!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Isi Master Data Demo SIMRS</title>
<style>
body{font-family:Arial,sans-serif;background:#f2f6fb;color:#172b45;margin:0;padding:24px}.wrap{max-width:1000px;margin:auto}.card{background:#fff;border:1px solid #d8e3f0;border-radius:14px;padding:22px;margin-bottom:16px;box-shadow:0 5px 18px #18395b0d}h1{margin:0 0 8px}p{line-height:1.55}.note{background:#fff7df;border:1px solid #f1d78a;padding:13px;border-radius:10px}.btn{border:0;border-radius:9px;background:#1d4f91;color:white;padding:12px 18px;font-weight:700;cursor:pointer}.btn:hover{background:#153e75}table{border-collapse:collapse;width:100%;margin-top:12px}th,td{border-bottom:1px solid #e3eaf2;padding:10px;text-align:left}th{background:#eef4fb}.ok{color:#14734a}.err{color:#b42318}.muted{color:#60758d;font-size:13px}.actions{margin-top:16px}
</style></head><body><div class="wrap">
<div class="card"><h1>Isi Master Data Demo SIMRS</h1><p>Halaman ini akan menambahkan contoh data ke tabel Master Data yang tersedia. Data yang sudah ada tidak dihapus dan tidak ditimpa. Jalankan satu kali; jika dijalankan kembali, data yang sama akan dilewati.</p>
<div class="note"><b>Penting:</b> Semua isi adalah data simulasi untuk pengujian aplikasi, bukan data resmi RSUD NTB. Harga, stok, supplier, kamar, dan parameter lab harus diverifikasi/diubah sebelum penggunaan nyata. Nilai rujukan laboratorium sengaja diberi placeholder agar tidak dianggap sebagai acuan klinis.</div></div>
<div class="card"><h2>Data yang akan dimasukkan</h2><table><thead><tr><th>Tabel</th><th>Contoh data</th><th>Jumlah</th></tr></thead><tbody>
<tr><td>unit</td><td>Poli Umum, Penyakit Dalam, Anak, Bedah, IGD, Laboratorium, Radiologi, Farmasi, Rawat Inap, ICU, dan lainnya</td><td>12</td></tr>
<tr><td>pegawai</td><td>Menggunakan pegawai yang sudah dibuat bersama akun role. Tidak diduplikasi.</td><td>Dicek dari database</td></tr>
<tr><td>penjamin</td><td>Umum, BPJS Kesehatan, Jasa Raharja, asuransi dan perusahaan demo</td><td>5</td></tr>
<tr><td>kamar</td><td>Kamar kelas III, II, VIP dan ICU</td><td>5</td></tr>
<tr><td>supplier</td><td>3 pemasok obat/perbekalan (nama demo)</td><td>3</td></tr>
<tr><td>obat</td><td>8 contoh item obat dengan harga dan stok simulasi</td><td>8</td></tr>
<tr><td>parameter_lab</td><td>9 parameter laboratorium; rentang rujukan placeholder</td><td>9</td></tr>
<tr><td>gudang</td><td>Farmasi, BMHP, alat kesehatan, gudang umum/ATK</td><td>4</td></tr>
<tr><td>barang_persediaan</td><td>10 barang BMHP, ATK dan kebersihan</td><td>10</td></tr>
</tbody></table>
<form method="post" class="actions"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><button class="btn" type="submit">Masukkan Data Demo ke Database</button></form>
<p class="muted">Akses dibatasi untuk akun dengan role IT_ADMIN.</p></div>
<?php if ($results): ?><div class="card"><h2>Hasil pengisian</h2><table><thead><tr><th>Tabel</th><th>Ditambahkan</th><th>Dilewati (sudah ada)</th><th>Total seed</th></tr></thead><tbody>
<?php foreach ($results as $t=>$r): ?><tr><td><?= h($t) ?></td><td class="ok"><?= (int)$r['added'] ?></td><td><?= (int)$r['skipped'] ?></td><td><?= (int)$r['total'] ?></td></tr><?php endforeach; ?></tbody></table><p><b>Pegawai yang sudah ada:</b> <?= (int)$employeeCount ?> record.</p></div><?php endif; ?>
<?php if ($errors): ?><div class="card"><h2 class="err">Catatan / error</h2><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul><p class="muted">Jika ada error kolom wajib atau relasi, kirim screenshot bagian ini agar bisa disesuaikan dengan skema database milikmu.</p></div><?php endif; ?>
<div class="card"><p><b>Setelah berhasil:</b> hapus file <code>isi_master_data_demo.php</code> dari folder public demi keamanan. Data yang sudah masuk ke database tidak ikut terhapus.</p><p><a href="index.php?page=master">Kembali ke Master Data</a></p></div>
</div></body></html>
