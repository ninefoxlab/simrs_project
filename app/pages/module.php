<?php
$moduleConfig = $moduleDefinitions[$page] ?? null;
if (!$moduleConfig) { http_response_code(404); echo '<div class="notice error">Modul tidak ditemukan.</div>'; return; }
require_permission($pdo, (int)$user['id_user'], $moduleConfig['permission'] . '.access');

$tables = array_values(array_filter($moduleConfig['tables'], function ($t) use ($pdo) {
    try { $pdo->query("SHOW COLUMNS FROM `{$t}`"); return true; }
    catch (Throwable $e) { return false; }
}));
if (!$tables) { echo '<div class="notice warning">Tabel untuk modul ini belum ditemukan di database.</div>'; return; }

$selectedTable = $_GET['table'] ?? $tables[0];
if (!in_array($selectedTable, $tables, true)) {
    http_response_code(400); echo '<div class="notice error">Tabel tidak termasuk modul ini.</div>'; return;
}
require_once __DIR__ . '/../schema.php';
$mode = $_GET['mode'] ?? 'list';
$pk = table_primary($pdo, $selectedTable);
$columns = table_columns($pdo, $selectedTable);
$rows = [];
try { $rows = table_rows($pdo, $selectedTable, 30); }
catch (Throwable $e) { error_log($e->getMessage()); }

$record = null;
if ($mode === 'edit' && $pk && isset($_GET['id'])) {
    $q = $pdo->prepare("SELECT * FROM `{$selectedTable}` WHERE `{$pk}` = ? LIMIT 1");
    $q->execute([$_GET['id']]); $record = $q->fetch();
    if (!$record) { $mode = 'list'; flash_set('warning', 'Data yang diminta tidak ditemukan.'); }
}

// Mode khusus untuk membuat kunjungan baru dari pasien yang sudah terdaftar.
$visitPatient = null;
if ($page === 'registration' && $selectedTable === 'pasien' && $mode === 'visit') {
    $patientId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    if ($patientId) {
        $q = $pdo->prepare('SELECT * FROM pasien WHERE id_pasien = ? LIMIT 1');
        $q->execute([$patientId]); $visitPatient = $q->fetch();
    }
    if (!$visitPatient) { $mode = 'list'; flash_set('error', 'Data pasien tidak ditemukan.'); }
}

$canCreate = can($pdo, (int)$user['id_user'], $moduleConfig['permission'] . '.create');
$canUpdate = can($pdo, (int)$user['id_user'], $moduleConfig['permission'] . '.update');
$foreignKeys = table_foreign_keys($pdo, $selectedTable);
$excluded = ['password_hash', 'token', 'secret'];

// Opsi referensi untuk formulir kunjungan. Opsi dibuat dari isi tabel master yang tersedia.
$unitOptions = $doctorOptions = $scheduleOptions = [];
if ($page === 'registration' && $selectedTable === 'pasien') {
    try {
        $unitPk = table_primary($pdo, 'unit');
        $unitLabel = table_label_column($pdo, 'unit');
        if ($unitPk) {
            $unitOptions = $pdo->query("SELECT `{$unitPk}` AS v, `{$unitLabel}` AS label FROM unit ORDER BY `{$unitLabel}` LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) { error_log('SIMRS unit options: ' . $e->getMessage()); }

    try {
        $pegawaiCols = array_column(table_columns($pdo, 'pegawai'), 'Field');
        $pegawaiPk = table_primary($pdo, 'pegawai');
        $pegawaiLabel = table_label_column($pdo, 'pegawai');
        if ($pegawaiPk) {
            $where = in_array('peran', $pegawaiCols, true) ? "WHERE LOWER(COALESCE(`peran`,'')) LIKE '%dokter%'" : '';
            $doctorOptions = $pdo->query("SELECT `{$pegawaiPk}` AS v, `{$pegawaiLabel}` AS label" . (in_array('peran', $pegawaiCols, true) ? ", `peran` AS detail" : '') . " FROM pegawai {$where} ORDER BY `{$pegawaiLabel}` LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) { error_log('SIMRS doctor options: ' . $e->getMessage()); }

    try {
        $schedulePk = table_primary($pdo, 'jadwal_praktik');
        if ($schedulePk) {
            $scheduleRows = $pdo->query('SELECT * FROM jadwal_praktik ORDER BY `' . $schedulePk . '` DESC LIMIT 300')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($scheduleRows as $sr) {
                $parts = [];
                foreach (['hari', 'hari_praktik', 'tanggal', 'jam_mulai', 'waktu_mulai', 'jam_selesai', 'waktu_selesai'] as $field) {
                    if (isset($sr[$field]) && $sr[$field] !== '') $parts[] = (string)$sr[$field];
                }
                $scheduleOptions[] = ['v' => $sr[$schedulePk], 'label' => ($parts ? implode(' · ', $parts) : 'Jadwal') . ' (#' . $sr[$schedulePk] . ')'];
            }
        }
    } catch (Throwable $e) { error_log('SIMRS schedule options: ' . $e->getMessage()); }
}

// Daftar kunjungan menampilkan nama pasien, unit, dan dokter; bukan hanya ID relasi.
$showVisitDetails = ($selectedTable === 'kunjungan' && $mode === 'list');
if ($showVisitDetails) {
    try {
        $pCols = array_column(table_columns($pdo, 'pasien'), 'Field');
        $uCols = array_column(table_columns($pdo, 'unit'), 'Field');
        $dCols = array_column(table_columns($pdo, 'pegawai'), 'Field');
        $pName = table_label_column($pdo, 'pasien');
        $uName = table_label_column($pdo, 'unit');
        $dName = table_label_column($pdo, 'pegawai');
        $select = 'k.*';
        $joins = '';
        if (in_array('id_pasien', $pCols, true) && in_array('id_pasien', array_column($columns, 'Field'), true)) {
            $select .= ", p.`{$pName}` AS _nama_pasien";
            if (in_array('no_rm', $pCols, true)) $select .= ', p.`no_rm` AS _no_rm';
            $joins .= ' LEFT JOIN pasien p ON p.id_pasien = k.id_pasien';
        }
        if (in_array('id_unit', $uCols, true) && in_array('id_unit', array_column($columns, 'Field'), true)) {
            $select .= ", u.`{$uName}` AS _nama_unit";
            $joins .= ' LEFT JOIN unit u ON u.id_unit = k.id_unit';
        }
        if (in_array('id_pegawai', $dCols, true) && in_array('id_dokter', array_column($columns, 'Field'), true)) {
            $select .= ", d.`{$dName}` AS _nama_dokter";
            $joins .= ' LEFT JOIN pegawai d ON d.id_pegawai = k.id_dokter';
        }
        $rows = $pdo->query("SELECT {$select} FROM kunjungan k {$joins} ORDER BY k.tanggal DESC, k.id_kunjungan DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('SIMRS visit list join: ' . $e->getMessage());
        $showVisitDetails = false;
        try { $rows = table_rows($pdo, $selectedTable, 30); } catch (Throwable $ignored) { $rows = []; }
    }
}
?>
<div class="page-head">
    <div><h1><?= e($moduleConfig['label']) ?></h1><p><?= e($moduleConfig['description']) ?></p></div>
</div>
<?php if ($f = flash_get()): ?><div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endif; ?>

<section class="card" style="margin-bottom:15px">
    <div class="row">
        <b>Pilih tabel:</b>
        <form method="get" action="index.php">
            <input type="hidden" name="page" value="<?= e($page) ?>">
            <select name="table" onchange="this.form.submit()">
                <?php foreach ($tables as $t): ?><option value="<?= e($t) ?>" <?= $selectedTable === $t ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $t))) ?></option><?php endforeach; ?>
            </select>
        </form>
        <span class="spacer"></span>
        <?php if ($canCreate && $mode === 'list'): ?>
            <a class="btn primary" href="index.php?page=<?= e($page) ?>&amp;table=<?= e($selectedTable) ?>&amp;mode=create"><?= $page === 'registration' && $selectedTable === 'pasien' ? '＋ Registrasi Pasien Baru' : '＋ Tambah Data' ?></a>
        <?php endif; ?>
    </div>
</section>

<?php if ($mode === 'visit' && $visitPatient): ?>
    <section class="card">
        <h2>Buat Kunjungan Pasien</h2>
        <div class="notice">
            <b><?= e($visitPatient['nama'] ?? ('Pasien #' . ($visitPatient['id_pasien'] ?? ''))) ?></b>
            <p>No. Rekam Medis: <?= e($visitPatient['no_rm'] ?? '—') ?> · NIK: <?= e($visitPatient['nik'] ?? '—') ?></p>
        </div>
        <?php if (!$unitOptions): ?><div class="flash error">Belum ada data unit pelayanan pada tabel unit. Isi Master Data Unit terlebih dahulu.</div><?php endif; ?>
        <form method="post" action="action.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_visit">
            <input type="hidden" name="page" value="registration">
            <input type="hidden" name="table" value="pasien">
            <input type="hidden" name="record_id" value="<?= e($visitPatient['id_pasien']) ?>">
            <div class="form-grid">
                <div class="form-field"><label>Unit / Poli <span class="required">*</span></label><select name="visit[id_unit]" required><option value="">Pilih unit pelayanan</option><?php foreach ($unitOptions as $opt): ?><option value="<?= e($opt['v']) ?>"><?= e($opt['label']) ?></option><?php endforeach; ?></select><small>Unit yang akan menerima pasien.</small></div>
                <div class="form-field"><label>Dokter (opsional)</label><select name="visit[id_dokter]"><option value="">Belum ditentukan</option><?php foreach ($doctorOptions as $opt): ?><option value="<?= e($opt['v']) ?>"><?= e($opt['label']) ?><?= !empty($opt['detail']) ? ' · ' . e($opt['detail']) : '' ?></option><?php endforeach; ?></select></div>
                <div class="form-field"><label>Jadwal Praktik (opsional)</label><select name="visit[id_jadwal]"><option value="">Belum ditentukan</option><?php foreach ($scheduleOptions as $opt): ?><option value="<?= e($opt['v']) ?>"><?= e($opt['label']) ?></option><?php endforeach; ?></select></div>
                <div class="form-field"><label>Tipe Pelayanan <span class="required">*</span></label><select name="visit[tipe_pelayanan]" required><option value="Rawat Jalan">Rawat Jalan</option><option value="IGD">IGD</option><option value="Rawat Inap">Rawat Inap</option></select></div>
            </div>
            <div class="actions-inline"><button class="btn primary" type="submit" <?= !$unitOptions ? 'disabled' : '' ?>>Buat Kunjungan dan Antrean</button><a class="btn" href="index.php?page=registration&amp;table=pasien">Batal</a></div>
        </form>
    </section>

<?php elseif (in_array($mode, ['create', 'edit'], true)): ?>
    <section class="card">
        <h2><?= $mode === 'create' ? 'Tambah' : 'Edit' ?> data: <?= e($selectedTable) ?></h2>
        <form method="post" action="action.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= e($mode) ?>">
            <input type="hidden" name="page" value="<?= e($page) ?>">
            <input type="hidden" name="table" value="<?= e($selectedTable) ?>">
            <?php if ($mode === 'edit' && $pk): ?><input type="hidden" name="record_id" value="<?= e($record[$pk]) ?>"><?php endif; ?>
            <?php if ($page === 'registration' && $selectedTable === 'pasien' && $mode === 'create'): ?><input type="hidden" name="with_visit" value="1"><?php endif; ?>
            <div class="form-grid">
                <?php foreach ($columns as $col):
                    $name = $col['Field']; $type = strtolower($col['Type']); $extra = strtolower($col['Extra'] ?? '');
                    $nullable = $col['Null'] === 'YES'; $default = $col['Default'];
                    if (in_array($name, $excluded, true) || str_contains($extra, 'auto_increment') || ($mode === 'edit' && $name === $pk)) continue;
                    if ($mode === 'create' && $default !== null) continue;
                    if ($mode === 'create' && $nullable && !isset($foreignKeys[$name])) continue;
                    $value = $record[$name] ?? '';
                    $required = (!$nullable && $default === null && !str_contains($extra, 'generated'));
                    $fk = $foreignKeys[$name] ?? null;
                ?>
                    <div class="form-field">
                        <label><?= e(ucwords(str_replace('_', ' ', $name))) ?> <?= $required ? '<span class="required">*</span>' : '' ?></label>
                        <?php if ($fk): $opts = foreign_options($pdo, $fk); ?>
                            <select name="fields[<?= e($name) ?>]" <?= $required ? 'required' : '' ?>><option value="">Pilih referensi</option><?php foreach ($opts as $opt): ?><option value="<?= e($opt['v']) ?>" <?= (string)$value === (string)$opt['v'] ? 'selected' : '' ?>><?= e($opt['label']) ?> (#<?= e($opt['v']) ?>)</option><?php endforeach; ?></select>
                        <?php elseif (preg_match('/^enum\((.*)\)$/', $type, $em)): $choices = str_getcsv(str_replace("'", '', $em[1])); ?>
                            <select name="fields[<?= e($name) ?>]" <?= $required ? 'required' : '' ?>><option value="">Pilih</option><?php foreach ($choices as $choice): ?><option value="<?= e($choice) ?>" <?= (string)$value === $choice ? 'selected' : '' ?>><?= e($choice) ?></option><?php endforeach; ?></select>
                        <?php elseif (str_contains($type, 'text')): ?>
                            <textarea name="fields[<?= e($name) ?>]" <?= $required ? 'required' : '' ?>><?= e($value) ?></textarea>
                        <?php else:
                            $input = 'text'; $step = '';
                            if (str_contains($type, 'int') || str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'double')) { $input = 'number'; $step = str_contains($type, 'int') ? '1' : 'any'; }
                            elseif (str_contains($type, 'date') && !str_contains($type, 'time')) $input = 'date';
                            elseif (str_contains($type, 'datetime') || str_contains($type, 'timestamp')) { $input = 'datetime-local'; if ($value) $value = date('Y-m-d\TH:i', strtotime($value)); }
                            elseif (str_contains($type, 'time')) $input = 'time';
                        ?>
                            <input type="<?= $input ?>" name="fields[<?= e($name) ?>]" value="<?= e($value) ?>" <?= $step !== '' ? 'step="' . $step . '"' : '' ?> <?= $required ? 'required' : '' ?>>
                        <?php endif; ?>
                        <small><?= $nullable ? 'Opsional' : 'Wajib diisi' ?><?= $default !== null ? ' · default: ' . e($default) : '' ?></small>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($page === 'registration' && $selectedTable === 'pasien' && $mode === 'create'): ?>
                <hr>
                <h3>Data Kunjungan dan Antrean</h3>
                <p class="footnote">Setelah identitas pasien disimpan, sistem juga akan membuat kunjungan dan nomor antrean secara otomatis.</p>
                <?php if (!$unitOptions): ?><div class="flash error">Belum ada unit pelayanan pada tabel unit. Tambahkan data unit melalui Master Data sebelum registrasi.</div><?php endif; ?>
                <div class="form-grid">
                    <div class="form-field"><label>Unit / Poli <span class="required">*</span></label><select name="visit[id_unit]" required><option value="">Pilih unit pelayanan</option><?php foreach ($unitOptions as $opt): ?><option value="<?= e($opt['v']) ?>"><?= e($opt['label']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-field"><label>Dokter (opsional)</label><select name="visit[id_dokter]"><option value="">Belum ditentukan</option><?php foreach ($doctorOptions as $opt): ?><option value="<?= e($opt['v']) ?>"><?= e($opt['label']) ?><?= !empty($opt['detail']) ? ' · ' . e($opt['detail']) : '' ?></option><?php endforeach; ?></select></div>
                    <div class="form-field"><label>Jadwal Praktik (opsional)</label><select name="visit[id_jadwal]"><option value="">Belum ditentukan</option><?php foreach ($scheduleOptions as $opt): ?><option value="<?= e($opt['v']) ?>"><?= e($opt['label']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-field"><label>Tipe Pelayanan <span class="required">*</span></label><select name="visit[tipe_pelayanan]" required><option value="Rawat Jalan">Rawat Jalan</option><option value="IGD">IGD</option><option value="Rawat Inap">Rawat Inap</option></select></div>
                </div>
            <?php endif; ?>

            <div class="actions-inline"><button class="btn primary" type="submit" <?= ($page === 'registration' && $selectedTable === 'pasien' && $mode === 'create' && !$unitOptions) ? 'disabled' : '' ?>>Simpan Data<?= $page === 'registration' && $selectedTable === 'pasien' && $mode === 'create' ? ' + Buat Kunjungan' : '' ?></button><a class="btn" href="index.php?page=<?= e($page) ?>&amp;table=<?= e($selectedTable) ?>">Batal</a></div>
        </form>
    </section>

<?php else: ?>
    <section class="card">
        <div class="row"><h2><?= e(ucwords(str_replace('_', ' ', $selectedTable))) ?></h2><span class="spacer"></span><span class="tag"><?= count($rows) ?> baris terbaru</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <?php
                    if ($showVisitDetails) {
                        $visitDisplayFields = ['no_kunjungan', '_nama_pasien', '_no_rm', '_nama_unit', '_nama_dokter', 'tipe_pelayanan', 'tanggal', 'status'];
                        $visitDisplayLabels = ['no_kunjungan'=>'No. Kunjungan', '_nama_pasien'=>'Nama Pasien', '_no_rm'=>'No. RM', '_nama_unit'=>'Unit / Poli', '_nama_dokter'=>'Dokter', 'tipe_pelayanan'=>'Pelayanan', 'tanggal'=>'Tanggal', 'status'=>'Status'];
                        $visibleFields = array_values(array_filter($visitDisplayFields, function ($field) use ($rows) { return $field[0] === '_' ? (!$rows || array_key_exists($field, $rows[0])) : true; }));
                        foreach ($visibleFields as $field): ?><th><?= e($visitDisplayLabels[$field] ?? $field) ?></th><?php endforeach;
                    } else {
                        $visible = array_values(array_filter($columns, fn($c) => !in_array(strtolower($c['Field']), $excluded, true)));
                        $visible = array_slice($visible, 0, 8);
                        foreach ($visible as $col): ?><th><?= e($col['Field']) ?></th><?php endforeach;
                    }
                    ?>
                    <?php if (($canUpdate && $pk) || ($page === 'registration' && $selectedTable === 'pasien' && $canCreate)): ?><th>Aksi</th><?php endif; ?>
                </tr></thead>
                <tbody>
                    <?php if (!$rows): ?><tr><td colspan="10" class="empty">Belum ada data pada tabel ini.</td></tr><?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php if ($showVisitDetails): ?>
                                <?php foreach ($visibleFields as $field): ?><td><?= e($row[$field] ?? '—') ?></td><?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($visible as $col): $val = $row[$col['Field']] ?? '—'; if (is_string($val) && strlen($val) > 90) $val = substr($val, 0, 90) . '…'; ?><td><?= e($val) ?></td><?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (($canUpdate && $pk) || ($page === 'registration' && $selectedTable === 'pasien' && $canCreate)): ?>
                                <td>
                                    <?php if ($canUpdate && $pk): ?><a class="btn smallbtn" href="index.php?page=<?= e($page) ?>&amp;table=<?= e($selectedTable) ?>&amp;mode=edit&amp;id=<?= e($row[$pk]) ?>">Edit</a><?php endif; ?>
                                    <?php if ($page === 'registration' && $selectedTable === 'pasien' && $canCreate): ?><a class="btn smallbtn" href="index.php?page=registration&amp;table=pasien&amp;mode=visit&amp;id=<?= e($row['id_pasien']) ?>">Buat Kunjungan</a><?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="footnote">Menampilkan maksimal 30 baris terbaru. Penghapusan permanen dinonaktifkan untuk menjaga riwayat medis dan transaksi.</p>
    </section>
<?php endif; ?>
