<?php
require_permission($pdo,(int)$user['id_user'],'user_management.access');
$flash=flash_get();
$editId=isset($_GET['edit'])?(int)$_GET['edit']:0;
$editing=null;
if($editId){
 $s=$pdo->prepare("SELECT u.id_user,u.id_pegawai,u.username,u.nama_tampilan,u.status_aktif,p.nama,p.peran,p.spesialisasi,ur.id_role FROM users u JOIN pegawai p ON p.id_pegawai=u.id_pegawai LEFT JOIN user_roles ur ON ur.id_user=u.id_user AND ur.is_default=1 WHERE u.id_user=?");
 $s->execute([$editId]); $editing=$s->fetch();
}
$rolesList=$pdo->query("SELECT id_role,kode_role,nama_role FROM roles WHERE status_aktif=1 ORDER BY nama_role")->fetchAll();
$users=$pdo->query("SELECT u.id_user,u.username,u.nama_tampilan,u.status_aktif,u.last_login,p.nama,p.peran,r.kode_role,r.nama_role FROM users u JOIN pegawai p ON p.id_pegawai=u.id_pegawai LEFT JOIN user_roles ur ON ur.id_user=u.id_user AND ur.is_default=1 LEFT JOIN roles r ON r.id_role=ur.id_role ORDER BY u.id_user DESC")->fetchAll();
$canCreate=can($pdo,(int)$user['id_user'],'user_management.create');
$canUpdate=can($pdo,(int)$user['id_user'],'user_management.update');
$canActivate=can($pdo,(int)$user['id_user'],'user_management.activate');
?>
<div class="page-head"><div><h1>Manajemen Pengguna</h1><p>Kelola akun, identitas pegawai, dan role SIMRS.</p></div><div class="quick-actions"><span class="tag"><?= count($users) ?> akun</span></div></div>
<?php if($flash): ?><div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if($editing && $canUpdate): ?>
<section class="card" style="margin-bottom:16px"><h2>Edit Pengguna #<?= (int)$editing['id_user'] ?></h2>
<form method="post" action="<?= APP_BASE_URL ?>/user_action.php"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id_user" value="<?= (int)$editing['id_user'] ?>">
<div class="form-grid">
<div class="form-field"><label>Nama lengkap <span class="required">*</span></label><input name="nama" required maxlength="255" value="<?= e($editing['nama']) ?>"></div>
<div class="form-field"><label>Username <span class="required">*</span></label><input name="username" required maxlength="50" pattern="[A-Za-z0-9._-]{3,50}" value="<?= e($editing['username']) ?>"></div>
<div class="form-field"><label>Jabatan / peran <span class="required">*</span></label><input name="peran" required maxlength="100" value="<?= e($editing['peran']) ?>"></div>
<div class="form-field"><label>Spesialisasi</label><input name="spesialisasi" maxlength="100" value="<?= e($editing['spesialisasi']) ?>"></div>
<div class="form-field"><label>Role utama <span class="required">*</span></label><select name="id_role" required><?php foreach($rolesList as $r): ?><option value="<?= (int)$r['id_role'] ?>" <?= (int)$editing['id_role']===(int)$r['id_role']?'selected':'' ?>><?= e($r['nama_role']) ?> (<?= e($r['kode_role']) ?>)</option><?php endforeach; ?></select></div>
<div class="form-field"><label>Password baru</label><input type="password" name="password" minlength="12" autocomplete="new-password"><small>Kosongkan jika tidak ingin mengganti password. Minimal 12 karakter jika diisi.</small></div>
</div><div class="actions-inline"><button class="btn primary" type="submit">Simpan Perubahan</button><a class="btn" href="<?= APP_BASE_URL ?>/index.php?page=users">Batal</a></div></form></section>
<?php endif; ?>
<?php if($canCreate): ?>
<details class="card" style="margin-bottom:16px" <?= isset($_GET['new'])?'open':'' ?>><summary style="cursor:pointer;font-weight:750;color:var(--blue)">＋ Tambah Pengguna Baru</summary>
<form method="post" action="<?= APP_BASE_URL ?>/user_action.php" style="margin-top:16px"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
<div class="form-grid">
<div class="form-field"><label>Nama lengkap <span class="required">*</span></label><input name="nama" required maxlength="255"></div>
<div class="form-field"><label>Username <span class="required">*</span></label><input name="username" required maxlength="50" pattern="[A-Za-z0-9._-]{3,50}"><small>3–50 karakter: huruf, angka, titik, _ atau -.</small></div>
<div class="form-field"><label>Jabatan / peran <span class="required">*</span></label><input name="peran" required maxlength="100" placeholder="Contoh: Dokter"></div>
<div class="form-field"><label>Spesialisasi</label><input name="spesialisasi" maxlength="100"></div>
<div class="form-field"><label>Role utama <span class="required">*</span></label><select name="id_role" required><option value="">Pilih role</option><?php foreach($rolesList as $r): ?><option value="<?= (int)$r['id_role'] ?>"><?= e($r['nama_role']) ?> (<?= e($r['kode_role']) ?>)</option><?php endforeach; ?></select></div>
<div class="form-field"><label>Password awal <span class="required">*</span></label><input type="password" name="password" minlength="12" required autocomplete="new-password"><small>Minimal 12 karakter. Jangan gunakan password bersama.</small></div>
</div><button class="btn primary" type="submit">Simpan Pengguna</button></form></details>
<?php endif; ?>
<section class="card"><h2>Daftar Akun</h2><div class="table-wrap"><table><thead><tr><th>No.</th><th>Username</th><th>Nama</th><th>Jabatan</th><th>Role</th><th>Status</th><th>Login Terakhir</th><th>Aksi</th></tr></thead><tbody>
<?php if(!$users): ?><tr><td colspan="8" class="empty">Belum ada akun.</td></tr><?php endif; ?>
<?php foreach($users as $i=>$u): ?><tr><td><?= $i+1 ?></td><td><b><?= e($u['username']) ?></b></td><td><?= e($u['nama_tampilan']) ?></td><td><?= e($u['peran']) ?></td><td><span class="tag"><?= e($u['kode_role'] ?? 'Belum ada role') ?></span></td><td><?= (int)$u['status_aktif']===1?'<span class="pill ok">Aktif</span>':'<span class="pill bad">Nonaktif</span>' ?></td><td><?= e(format_datetime($u['last_login'])) ?></td><td><div class="actions-inline">
<?php if($canUpdate): ?><a class="btn smallbtn" href="<?= APP_BASE_URL ?>/index.php?page=users&amp;edit=<?= (int)$u['id_user'] ?>">Edit</a><?php endif; ?>
<?php if($canActivate && (int)$u['id_user']!==(int)$user['id_user']): ?><form method="post" action="<?= APP_BASE_URL ?>/user_action.php" onsubmit="return confirm('Ubah status akun ini?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id_user" value="<?= (int)$u['id_user'] ?>"><button class="btn smallbtn <?= (int)$u['status_aktif']===1?'danger':'green' ?>" type="submit"><?= (int)$u['status_aktif']===1?'Nonaktifkan':'Aktifkan' ?></button></form><?php endif; ?>
</div></td></tr><?php endforeach; ?>
</tbody></table></div><p class="footnote">Akun dinonaktifkan tanpa menghapus riwayat pengguna. IT Administrator yang sedang login tidak dapat menonaktifkan dirinya sendiri.</p></section>
