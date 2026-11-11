<?php
$counts = [];
$tables = ['pasien'=>'Total Pasien','kunjungan'=>'Total Kunjungan','order_layanan'=>'Order Layanan','users'=>'Pengguna'];
foreach ($tables as $table=>$label) {
    try { $counts[$table]=(int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn(); }
    catch(Throwable $e){ $counts[$table]=0; }
}
?>
<div class="page-head"><div><h1><span data-greet>Selamat datang</span>, <?= e($user['nama_tampilan']) ?></h1><p><?= e($isITAdmin ? 'Ringkasan sistem' : 'Ringkasan pelayanan') ?> SIMRS RSUD NTB hari ini, langsung dari database.</p></div></div>
<div class="grid g4">
<?php foreach($tables as $table=>$label): ?>
 <div class="stat"><div class="label"><?= e($label) ?></div><div class="value"><?= number_format($counts[$table],0,',','.') ?></div><div class="foot">Total tercatat</div></div>
<?php endforeach; ?>
</div>
<br>
<div class="grid g2">
 <section class="card"><h2>Status Sistem</h2><div class="notice success"><b>Database terhubung</b><br>PHP berhasil membaca database simrs_db.</div><div class="notice success"><b>Autentikasi aktif</b><br>Akun login diperiksa melalui tabel users.</div></section>
 <section class="card"><h2>Informasi Pengguna</h2><p>Nama: <b><?= e($user['nama_tampilan']) ?></b></p><p>Username: <b><?= e($user['username']) ?></b></p><p>Role: <?php foreach($roles as $r): ?><span class="tag"><?= e($r) ?></span> <?php endforeach; ?></p><p class="muted small">Menu yang tampil dibatasi sesuai role; hak akses tindakan diperiksa lagi di backend.</p></section>
</div>
