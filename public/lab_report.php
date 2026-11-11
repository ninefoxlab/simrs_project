<?php
// Laporan Hasil Pemeriksaan Laboratorium (SOP/SIMRS/001/2026 langkah 12).
// Hanya untuk hasil VALIDATED/FINAL dan pengguna yang berwenang. Setiap pencetakan dicatat di audit trail.
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/permissions.php';

$user = require_login($pdo);
$uid = (int)$user['id_user'];
$roles = user_roles($pdo, $uid);
$id = (int)($_GET['id_order'] ?? 0);

$q = $pdo->prepare("SELECT o.*, k.no_kunjungan, k.tipe_pelayanan, k.id_dokter, k.tanggal AS tanggal_kunjungan,
        p.nama AS nama_pasien, p.no_rm, p.nik, p.tanggal_lahir, p.jenis_kelamin, p.alamat,
        u.nama_unit AS unit_asal, d.nama AS dokter_pengirim,
        r.status_hasil, r.hasil_teks, r.catatan_validator, r.diinput_oleh_user, r.waktu_input, r.divalidasi_oleh_user, r.waktu_validasi
    FROM simrs_wf_orders o
    JOIN kunjungan k ON k.id_kunjungan = o.id_kunjungan
    JOIN pasien p ON p.id_pasien = k.id_pasien
    LEFT JOIN unit u ON u.id_unit = k.id_unit
    LEFT JOIN pegawai d ON d.id_pegawai = k.id_dokter
    LEFT JOIN simrs_wf_results r ON r.id_order = o.id_order
    WHERE o.id_order = ? AND o.jenis_order = 'LAB' LIMIT 1");
$q->execute([$id]);
$o = $q->fetch();

// Hak akses: petugas lab, validator, IT, atau dokter penanggung jawab pasien.
$allowed = (bool)array_intersect($roles, ['ATLM', 'VALIDATOR_LAB', 'DOKTER_LAB', 'IT_ADMIN']);
if (!$allowed && in_array('DOKTER', $roles, true) && $o) $allowed = !empty($user['id_pegawai']) && (int)$o['id_dokter'] === (int)$user['id_pegawai'];
if (!$o) { http_response_code(404); exit('Order laboratorium tidak ditemukan.'); }
if (!$allowed) { http_response_code(403); exit('Akses ditolak: Anda tidak berwenang melihat hasil laboratorium pasien ini.'); }
if ($o['status_order'] !== 'completed' || $o['status_hasil'] !== 'validated') { http_response_code(409); exit('Laporan hanya dapat dicetak setelah hasil berstatus VALIDATED/FINAL.'); }

$items = $pdo->prepare('SELECT * FROM simrs_wf_lab_items WHERE id_order=? ORDER BY id_lab_item');
$items->execute([$id]); $items = $items->fetchAll();
$spec = $pdo->prepare("SELECT * FROM simrs_wf_lab_specimens WHERE id_order=? AND status_spesimen='accepted' ORDER BY id_specimen DESC LIMIT 1");
$spec->execute([$id]); $spec = $spec->fetch();
$name = function ($userId) use ($pdo) { if (!$userId) return '—'; $s = $pdo->prepare('SELECT nama_tampilan FROM users WHERE id_user=?'); $s->execute([(int)$userId]); return (string)($s->fetchColumn() ?: '—'); };
$age = ''; if ($o['tanggal_lahir']) { try { $age = (new DateTime($o['tanggal_lahir']))->diff(new DateTime())->y . ' tahun'; } catch (Throwable $e) {} }
$flagLabel = ['normal' => 'Normal', 'low' => 'L', 'high' => 'H', 'critical' => 'KRITIS'];

audit_log($pdo, $uid, 'PRINT_LAB_REPORT', 'simrs_wf_orders', 'Cetak/lihat laporan hasil ' . $o['no_order']);
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Hasil Laboratorium <?= e($o['no_order']) ?> | <?= e($o['nama_pasien']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap">
<style>
  *{box-sizing:border-box} body{margin:0;background:#E9EFEE;color:#18302F;font:13px/1.5 "Plus Jakarta Sans",system-ui,Arial,sans-serif}
  .sheet{width:210mm;min-height:297mm;margin:24px auto;background:#fff;padding:16mm 16mm 14mm;box-shadow:0 10px 30px rgba(14,42,47,.15)}
  .kop{display:flex;align-items:center;gap:14px;border-bottom:3px double #0E2A2F;padding-bottom:10px}
  .logo{width:46px;height:46px;border-radius:12px;background:#0F6E66;color:#fff;display:grid;place-items:center;font-size:24px;font-weight:800}
  .kop h1{margin:0;font-size:17px;letter-spacing:-.01em} .kop p{margin:2px 0 0;color:#5E7472;font-size:11.5px}
  h2{text-align:center;font-size:15px;margin:16px 0 12px;letter-spacing:.02em}
  .meta{display:grid;grid-template-columns:1fr 1fr;gap:2px 24px;margin-bottom:14px}
  .meta div{display:grid;grid-template-columns:120px 1fr;gap:6px} .meta span{color:#5E7472}
  table{width:100%;border-collapse:collapse;margin-top:6px} th,td{border:1px solid #CAD7D4;padding:7px 9px;text-align:left;vertical-align:top}
  th{background:#F4F8F7;font-size:12px} td.val{font-weight:700} .flag{font-weight:800;color:#C2412D}
  .note{margin-top:12px;font-size:12px} .sign{display:flex;justify-content:space-between;margin-top:30px;gap:20px}
  .sign div{width:45%;text-align:center} .sign .line{margin-top:52px;border-top:1px solid #18302F;padding-top:4px;font-weight:700}
  .stamp{display:inline-block;border:2px solid #2F855A;color:#2F855A;font-weight:800;padding:2px 10px;border-radius:6px;font-size:12px}
  .foot{margin-top:24px;font-size:10.5px;color:#5E7472;border-top:1px solid #DCE5E3;padding-top:6px}
  .toolbar{position:sticky;top:0;background:#0E2A2F;color:#fff;padding:10px 16px;display:flex;gap:10px;justify-content:center;align-items:center}
  .toolbar button,.toolbar a{font:inherit;font-weight:700;border:0;border-radius:10px;padding:9px 16px;background:#0F6E66;color:#fff;cursor:pointer;text-decoration:none}
  .toolbar a{background:#ffffff1a}
  @media print{body{background:#fff}.toolbar{display:none}.sheet{margin:0;box-shadow:none;width:auto;min-height:0;padding:0}@page{size:A4;margin:14mm}}
  @media (max-width:820px){.sheet{width:auto;margin:0;padding:18px}.meta{grid-template-columns:1fr}}
</style></head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">Cetak / Simpan PDF</button><a href="javascript:window.close()">Tutup</a></div>
<main class="sheet">
  <header class="kop"><div class="logo">✚</div><div><h1>RSUD Provinsi Nusa Tenggara Barat</h1><p>Instalasi Laboratorium Klinik · SIMRS RSUD NTB</p></div></header>
  <h2>LAPORAN HASIL PEMERIKSAAN LABORATORIUM</h2>
  <section class="meta">
    <div><span>Nama pasien</span><b><?= e($o['nama_pasien']) ?></b></div>
    <div><span>No. order</span><b><?= e($o['no_order']) ?></b></div>
    <div><span>No. rekam medis</span><b><?= e($o['no_rm']) ?></b></div>
    <div><span>No. kunjungan</span><?= e($o['no_kunjungan']) ?></div>
    <div><span>Jenis kelamin / umur</span><?= e($o['jenis_kelamin'] === 'P' ? 'Perempuan' : ($o['jenis_kelamin'] === 'L' ? 'Laki-laki' : '—')) ?> / <?= e($age ?: '—') ?></div>
    <div><span>Waktu order</span><?= e(format_datetime($o['dibuat_pada'])) ?></div>
    <div><span>Dokter pengirim</span><?= e($o['dokter_pengirim'] ?? '—') ?></div>
    <div><span>Unit asal</span><?= e(($o['unit_asal'] ?? '—') . ' (' . $o['tipe_pelayanan'] . ')') ?></div>
    <div><span>Spesimen</span><?= e($spec['jenis_spesimen'] ?? '—') ?><?= !empty($spec['no_label']) ? ' · ' . e($spec['no_label']) : '' ?></div>
    <div><span>Diterima lab</span><?= e($spec ? format_datetime($spec['waktu_verifikasi']) : '—') ?></div>
    <div style="grid-column:1/-1"><span>Informasi klinis</span><?= e($o['instruksi'] ?: '—') ?></div>
  </section>
  <table>
    <thead><tr><th>Pemeriksaan</th><th>Hasil</th><th>Flag</th><th>Satuan</th><th>Nilai rujukan</th></tr></thead>
    <tbody>
    <?php if (!$items): ?><tr><td colspan="5"><?= nl2br(e($o['hasil_teks'] ?? '—')) ?></td></tr><?php endif; ?>
    <?php foreach ($items as $it): ?>
      <tr><td><?= e($it['nama_pemeriksaan']) ?></td><td class="val"><?= e($it['nilai_hasil'] ?? '—') ?></td><td class="flag"><?= e(in_array($it['penanda'], ['low', 'high', 'critical'], true) ? $flagLabel[$it['penanda']] : '') ?></td><td><?= e($it['satuan']) ?></td><td><?= e($it['nilai_rujukan']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($o['hasil_teks'] && $items): ?><p class="note"><b>Catatan:</b> <?= nl2br(e($o['hasil_teks'])) ?></p><?php endif; ?>
  <?php if ($o['catatan_validator']): ?><p class="note"><b>Catatan validator:</b> <?= nl2br(e($o['catatan_validator'])) ?></p><?php endif; ?>
  <p class="note"><span class="stamp">VALIDATED / FINAL</span> &nbsp;Divalidasi <?= e(format_datetime($o['waktu_validasi'])) ?></p>
  <section class="sign">
    <div>Pemeriksa (ATLM)<div class="line"><?= e($name($o['diinput_oleh_user'])) ?></div><small><?= e(format_datetime($o['waktu_input'])) ?></small></div>
    <div>Validator<div class="line"><?= e($name($o['divalidasi_oleh_user'])) ?></div><small><?= e(format_datetime($o['waktu_validasi'])) ?></small></div>
  </section>
  <p class="foot">Dokumen elektronik SIMRS RSUD Provinsi NTB · dicetak <?= e(date('d-m-Y H:i')) ?> oleh <?= e($user['nama_tampilan']) ?> · Hasil berlaku untuk spesimen yang diperiksa. Bersifat rahasia (UU No. 27/2022 tentang Pelindungan Data Pribadi).</p>
</main>
</body></html>
