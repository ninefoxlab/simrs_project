<?php
// Modul Laboratorium sesuai SOP/SIMRS/001/2026 (RSUD Provinsi NTB).
// Dipanggil dari workflow.php untuk page 'laboratory' (ATLM & validator) dan 'lab_results' (Dokter/DPJP).
// Variabel dari workflow.php: $pdo, $page, $roles, $user, $uid.

if (!function_exists('lab_status')) {
    function lab_status(?string $s): string {
        $map = [
            'ordered' => 'Order baru',
            'rejected' => 'REJECTED',
            'in_progress' => 'IN_PROGRESS',
            'waiting_validation' => 'Menunggu validasi',
            'completed' => 'VALIDATED / FINAL',
        ];
        return '<span class="wfx-status">' . e($map[$s] ?? str_replace('_', ' ', (string)$s)) . '</span>';
    }
    function lab_flag(?string $f): string {
        $map = ['normal' => ['Normal', 'ok'], 'low' => ['Rendah', 'wait'], 'high' => ['Tinggi', 'wait'], 'critical' => ['Kritis', 'bad']];
        if (!$f || !isset($map[$f])) return '<span class="muted">—</span>';
        return '<span class="pill ' . $map[$f][1] . '">' . e($map[$f][0]) . '</span>';
    }
    function lab_user(PDO $pdo, $id): string {
        if (!$id) return '—';
        static $cache = [];
        if (!isset($cache[$id])) { $q = $pdo->prepare('SELECT nama_tampilan FROM users WHERE id_user=?'); $q->execute([(int)$id]); $cache[$id] = (string)($q->fetchColumn() ?: '—'); }
        return $cache[$id];
    }
    // Daftar order lab beserta identitas pasien, dokter pengirim, dan unit asal.
    function lab_orders(PDO $pdo, array $where, array $params, int $limit = 150): array {
        $sql = "SELECT o.*, k.no_kunjungan, k.tipe_pelayanan, k.id_dokter, p.nama AS nama_pasien, p.no_rm, p.jenis_kelamin, p.tanggal_lahir,
                       u.nama_unit AS unit_asal, d.nama AS dokter_pengirim,
                       r.status_hasil, r.hasil_teks, r.catatan_validator, r.diinput_oleh_user, r.waktu_input, r.divalidasi_oleh_user, r.waktu_validasi
                FROM simrs_wf_orders o
                JOIN kunjungan k ON k.id_kunjungan = o.id_kunjungan
                JOIN pasien p ON p.id_pasien = k.id_pasien
                LEFT JOIN unit u ON u.id_unit = k.id_unit
                LEFT JOIN pegawai d ON d.id_pegawai = k.id_dokter
                LEFT JOIN simrs_wf_results r ON r.id_order = o.id_order
                WHERE o.jenis_order = 'LAB'" . ($where ? ' AND ' . implode(' AND ', $where) : '') . "
                ORDER BY FIELD(o.prioritas,'Cito','Segera','Normal'), o.dibuat_pada DESC LIMIT " . max(1, min(300, $limit));
        $q = $pdo->prepare($sql); $q->execute($params);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    function lab_items(PDO $pdo, int $orderId): array {
        $q = $pdo->prepare('SELECT * FROM simrs_wf_lab_items WHERE id_order=? ORDER BY id_lab_item'); $q->execute([$orderId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    // Tabel hasil read-only. Dipakai validator, dokter, dan laporan.
    function lab_result_table(array $items, ?string $legacyText = null): void {
        if (!$items) { echo '<div class="wfx-subpanel"><span class="wfx-report">' . nl2br(e($legacyText ?? 'Belum ada rincian hasil.')) . '</span></div>'; return; }
        echo '<div class="wfx-table-wrap"><table class="wfx-table"><thead><tr><th>Pemeriksaan</th><th>Hasil</th><th>Satuan</th><th>Nilai rujukan</th><th>Penanda</th></tr></thead><tbody>';
        foreach ($items as $it) {
            echo '<tr><td><b>' . e($it['nama_pemeriksaan']) . '</b><br><small>' . e($it['kode_parameter']) . ' · ' . e($it['jenis_spesimen'] ?? '') . '</small></td><td><b>' . e($it['nilai_hasil'] ?? '—') . '</b></td><td>' . e($it['satuan']) . '</td><td>' . e($it['nilai_rujukan']) . '</td><td>' . lab_flag($it['penanda']) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

$isATLM = wfx_has($roles, ['ATLM']);
$isValidator = wfx_has($roles, ['VALIDATOR_LAB', 'DOKTER_LAB']);
$isDoctor = wfx_has($roles, ['DOKTER']);
$base = APP_BASE_URL;

/* =====================================================================
   HASIL LABORATORIUM — Dokter/DPJP (SOP langkah 11): hanya hasil FINAL.
   ===================================================================== */
if ($page === 'lab_results'):
    $where = ["o.status_order = 'completed'", "r.status_hasil = 'validated'"]; $params = [];
    if ($isDoctor && !wfx_has($roles, ['IT_ADMIN'])) {
        if (empty($user['id_pegawai'])) { $where[] = '1=0'; }
        else { $where[] = 'k.id_dokter = ?'; $params[] = (int)$user['id_pegawai']; }
    }
    $search = trim((string)($_GET['q'] ?? ''));
    if ($search !== '') { $where[] = '(o.no_order LIKE ? OR p.no_rm LIKE ? OR p.nama LIKE ?)'; array_push($params, "%$search%", "%$search%", "%$search%"); }
    $rows = lab_orders($pdo, $where, $params, 100);
?>
  <p class="wfx-intro">Hasil pemeriksaan laboratorium pasien Anda yang sudah berstatus <b>VALIDATED/FINAL</b>. Hasil yang masih diproses atau menunggu validasi tidak ditampilkan.</p>
  <section class="wfx-panel">
    <form method="get" class="row" style="margin-bottom:6px">
      <input type="hidden" name="page" value="lab_results">
      <input name="q" value="<?= e($search) ?>" class="lab-search" placeholder="Cari no. order, no. RM, atau nama pasien">
      <button class="wfx-btn" type="submit">Cari</button>
      <?php if ($search !== ''): ?><a class="wfx-btn" href="index.php?page=lab_results">Reset</a><?php endif; ?>
      <span class="spacer"></span><span class="wfx-count"><?= count($rows) ?> hasil final</span>
    </form>
    <?php if (!$rows): ?><div class="wfx-empty">Belum ada hasil laboratorium final untuk pasien Anda.</div><?php endif; ?>
    <?php foreach ($rows as $o): $items = lab_items($pdo, (int)$o['id_order']); ?>
      <div class="wfx-subpanel">
        <div class="wfx-title-row">
          <div><b><?= e($o['nama_pasien']) ?></b> · RM <?= e($o['no_rm']) ?><br><small class="muted"><?= e($o['no_order']) ?> · <?= e($o['no_kunjungan']) ?> · divalidasi <?= e(format_datetime($o['waktu_validasi'])) ?> oleh <?= e(lab_user($pdo, $o['divalidasi_oleh_user'])) ?></small></div>
          <a class="wfx-btn" href="<?= e($base) ?>/lab_report.php?id_order=<?= (int)$o['id_order'] ?>" target="_blank" rel="noopener">Cetak laporan</a>
        </div>
        <?php lab_result_table($items, $o['hasil_teks']); ?>
        <?php if ($o['hasil_teks']): ?><p class="wfx-help"><b>Catatan ATLM:</b> <?= nl2br(e($o['hasil_teks'])) ?></p><?php endif; ?>
        <?php if ($o['catatan_validator']): ?><p class="wfx-help"><b>Catatan validator:</b> <?= nl2br(e($o['catatan_validator'])) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>
<?php
    return;
endif;

/* =====================================================================
   MODUL LABORATORY — ATLM & Validator.
   ===================================================================== */
$orderId = (int)($_GET['id_order'] ?? 0);

if (!$orderId):
    // Langkah 3 & 8: daftar order, cari berdasarkan nomor order / nomor RM.
    $statusTabs = [
        'aktif' => ['Perlu diproses', ["o.status_order IN ('ordered','rejected','in_progress')"]],
        'ordered' => ['Order baru', ["o.status_order = 'ordered'"]],
        'rejected' => ['Spesimen ditolak', ["o.status_order = 'rejected'"]],
        'in_progress' => ['Dalam pemeriksaan', ["o.status_order = 'in_progress'"]],
        'waiting_validation' => ['Menunggu validasi', ["o.status_order = 'waiting_validation'"]],
        'completed' => ['Final', ["o.status_order = 'completed'"]],
        'semua' => ['Semua', []],
    ];
    $defaultTab = $isValidator && !$isATLM ? 'waiting_validation' : 'aktif';
    $tab = $_GET['status'] ?? $defaultTab; if (!isset($statusTabs[$tab])) $tab = $defaultTab;
    $search = trim((string)($_GET['q'] ?? ''));
    $where = $statusTabs[$tab][1]; $params = [];
    if ($search !== '') { $where[] = '(o.no_order LIKE ? OR p.no_rm LIKE ? OR p.nama LIKE ?)'; array_push($params, "%$search%", "%$search%", "%$search%"); }
    $rows = lab_orders($pdo, $where, $params);
    $counts = [];
    foreach ($pdo->query("SELECT status_order, COUNT(*) n FROM simrs_wf_orders WHERE jenis_order='LAB' GROUP BY status_order")->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $n) $counts[$k] = (int)$n;
    $counts['aktif'] = ($counts['ordered'] ?? 0) + ($counts['rejected'] ?? 0) + ($counts['in_progress'] ?? 0);
    $counts['semua'] = array_sum(array_intersect_key($counts, array_flip(['ordered','rejected','in_progress','waiting_validation','completed'])));
?>
  <p class="wfx-intro">Alur sesuai SOP Laboratorium: <b>order dokter</b> → <b>verifikasi spesimen</b> (diterima / REJECTED) → <b>pemeriksaan &amp; input hasil</b> oleh ATLM → <b>validasi</b> (VALIDATED/FINAL atau dikembalikan) → hasil masuk Rekam Medis Elektronik dan dapat dicetak.</p>
  <section class="wfx-panel">
    <div class="actions-inline" style="margin-bottom:12px">
      <?php foreach ($statusTabs as $key => [$label]): ?>
        <a class="wfx-btn <?= $tab === $key ? 'wfx-btn-primary' : '' ?>" href="index.php?page=laboratory&amp;status=<?= e($key) ?><?= $search !== '' ? '&amp;q=' . e(rawurlencode($search)) : '' ?>"><?= e($label) ?> <span class="wfx-count" style="margin-left:4px;padding:1px 7px"><?= (int)($counts[$key] ?? 0) ?></span></a>
      <?php endforeach; ?>
    </div>
    <form method="get" class="row">
      <input type="hidden" name="page" value="laboratory"><input type="hidden" name="status" value="<?= e($tab) ?>">
      <input name="q" value="<?= e($search) ?>" class="lab-search" placeholder="Cari no. order, no. rekam medis, atau nama pasien" autofocus>
      <button class="wfx-btn" type="submit">Cari</button>
      <?php if ($search !== ''): ?><a class="wfx-btn" href="index.php?page=laboratory&amp;status=<?= e($tab) ?>">Reset</a><?php endif; ?>
    </form>
    <?php if (!$rows): ?><div class="wfx-empty" style="margin-top:12px">Tidak ada order pada daftar ini.</div><?php else: ?>
    <div class="wfx-table-wrap"><table class="wfx-table">
      <thead><tr><th>No. order</th><th>Pasien / No. RM</th><th>Jenis pemeriksaan</th><th>Dokter pengirim / unit asal</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $o): ?>
        <tr>
          <td><b><?= e($o['no_order']) ?></b><br><small><?= e(format_datetime($o['dibuat_pada'])) ?></small><?= $o['prioritas'] === 'Cito' ? '<br><span class="pill bad">Cito</span>' : ($o['prioritas'] === 'Segera' ? '<br><span class="pill wait">Segera</span>' : '') ?></td>
          <td><b><?= e($o['nama_pasien']) ?></b><br><small>RM <?= e($o['no_rm']) ?></small></td>
          <td><?= e($o['nama_layanan']) ?></td>
          <td><?= e($o['dokter_pengirim'] ?? lab_user($pdo, $o['id_pembuat_user'])) ?><br><small><?= e(($o['unit_asal'] ?? '—') . ' · ' . $o['tipe_pelayanan']) ?></small></td>
          <td><?= lab_status($o['status_order']) ?></td>
          <td><a class="wfx-btn wfx-btn-primary" href="index.php?page=laboratory&amp;id_order=<?= (int)$o['id_order'] ?>">Buka</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </section>
<?php
    return;
endif;

// ===== Detail satu order =====
$rows = lab_orders($pdo, ['o.id_order = ?'], [$orderId], 1);
$o = $rows[0] ?? null;
if (!$o): ?>
  <div class="wfx-panel"><div class="wfx-empty">Order laboratorium tidak ditemukan. <a class="wfx-btn" href="index.php?page=laboratory">Kembali ke daftar</a></div></div>
<?php return; endif;
$visit = wfx_visit_detail($pdo, (int)$o['id_kunjungan']);
$items = lab_items($pdo, $orderId);
$q = $pdo->prepare('SELECT * FROM simrs_wf_lab_specimens WHERE id_order=? ORDER BY id_specimen DESC'); $q->execute([$orderId]); $specimens = $q->fetchAll(PDO::FETCH_ASSOC);
$st = $o['status_order'];
$specimenTypes = implode(', ', array_unique(array_filter(array_column($items, 'jenis_spesimen'))));
?>
  <div class="wfx-title-row"><a class="wfx-btn" href="index.php?page=laboratory">← Daftar order</a><?= lab_status($st) ?></div>
  <?php if ($visit) wfx_patient_card($visit); ?>

  <section class="wfx-panel">
    <div class="wfx-panel-head"><div><h2>Order <?= e($o['no_order']) ?></h2><p>Dikirim <?= e(format_datetime($o['dibuat_pada'])) ?> oleh <?= e($o['dokter_pengirim'] ?? lab_user($pdo, $o['id_pembuat_user'])) ?> dari <?= e($o['unit_asal'] ?? '—') ?></p></div>
      <?= $o['prioritas'] === 'Cito' ? '<span class="pill bad">Cito</span>' : ($o['prioritas'] === 'Segera' ? '<span class="pill wait">Segera</span>' : '<span class="pill">Prioritas normal</span>') ?></div>
    <div class="wfx-grid">
      <div class="wfx-subpanel"><b>Jenis pemeriksaan</b><p><?= e($o['nama_layanan']) ?></p><small class="muted">Spesimen yang diperlukan: <?= e($specimenTypes ?: 'lihat instruksi') ?></small></div>
      <div class="wfx-subpanel"><b>Informasi klinis</b><p class="wfx-report"><?= nl2br(e($o['instruksi'] ?: '—')) ?></p></div>
    </div>
  </section>

  <!-- Langkah 4: verifikasi spesimen -->
  <section class="wfx-panel">
    <h2>1. Penerimaan &amp; verifikasi spesimen</h2>
    <?php if ($specimens): ?>
      <div class="wfx-table-wrap"><table class="wfx-table"><thead><tr><th>Waktu</th><th>Spesimen / label</th><th>Identitas</th><th>Label</th><th>Jenis</th><th>Kesesuaian</th><th>Keputusan</th><th>Petugas</th></tr></thead><tbody>
      <?php foreach ($specimens as $sp): $ck = fn($v) => $v ? '<span class="pill ok">Sesuai</span>' : '<span class="pill bad">Tidak</span>'; ?>
        <tr><td><?= e(format_datetime($sp['waktu_verifikasi'])) ?></td><td><?= e($sp['jenis_spesimen']) ?><br><small><?= e($sp['no_label']) ?></small></td><td><?= $ck($sp['cek_identitas']) ?></td><td><?= $ck($sp['cek_label']) ?></td><td><?= $ck($sp['cek_jenis']) ?></td><td><?= $ck($sp['cek_kesesuaian']) ?></td>
          <td><?= $sp['status_spesimen'] === 'accepted' ? '<span class="pill ok">Diterima</span>' : '<span class="pill bad">REJECTED</span><br><small>' . e($sp['alasan_penolakan']) . '</small>' . ($sp['tindak_lanjut'] ? '<br><small>Tindak lanjut: ' . e($sp['tindak_lanjut']) . '</small>' : '') ?></td>
          <td><?= e(lab_user($pdo, $sp['diverifikasi_oleh_user'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php elseif (!in_array($st, ['ordered', 'rejected'], true)): ?>
      <p class="wfx-help">Order dibuat sebelum verifikasi spesimen diterapkan.</p>
    <?php endif; ?>

    <?php if (in_array($st, ['ordered', 'rejected'], true)): ?>
      <?php if (!$isATLM): ?><div class="wfx-empty">Menunggu Petugas Laboratorium (ATLM) memverifikasi spesimen.</div>
      <?php else: wfx_form_open('verify_specimen', (int)$o['id_kunjungan'], 'laboratory'); ?>
        <input type="hidden" name="id_order" value="<?= $orderId ?>">
        <?php if ($st === 'rejected'): ?><div class="wfx-alert">Spesimen sebelumnya ditolak. Setelah spesimen pengganti diterima, lakukan verifikasi ulang.</div><?php endif; ?>
        <div class="wfx-grid">
          <?php wfx_input('jenis_spesimen', 'Jenis spesimen *', $specimenTypes); ?>
          <?php wfx_input('no_label', 'Nomor label / barcode spesimen', $o['no_order']); ?>
        </div>
        <div class="wfx-subpanel">
          <b>Checklist verifikasi</b>
          <?php foreach (['cek_identitas' => 'Identitas pasien pada spesimen sesuai (nama, No. RM, tanggal lahir)', 'cek_label' => 'Label spesimen lengkap dan terbaca', 'cek_jenis' => 'Jenis spesimen sesuai dengan jenis pemeriksaan', 'cek_kesesuaian' => 'Kondisi & volume spesimen layak diperiksa (tidak lisis, beku, kurang, atau kedaluwarsa)'] as $k => $label): ?>
            <label class="row" style="gap:8px;margin:8px 0;font-weight:500"><input type="checkbox" name="<?= $k ?>" value="1" style="width:18px;height:18px;min-height:0"> <?= e($label) ?></label>
          <?php endforeach; ?>
        </div>
        <div class="wfx-grid">
          <?php wfx_select('keputusan', 'Keputusan', ['' => 'Pilih keputusan', 'accepted' => 'Spesimen sesuai — terima & mulai pemeriksaan', 'rejected' => 'Spesimen tidak sesuai — tolak (REJECTED)']); ?>
          <div></div>
          <?php wfx_textarea('alasan_penolakan', 'Alasan penolakan (wajib bila ditolak)', '', 2); ?>
          <?php wfx_textarea('tindak_lanjut', 'Tindak lanjut (mis. minta pengambilan ulang)', '', 2); ?>
        </div>
        <div class="wfx-form-actions"><button class="wfx-btn wfx-btn-primary" type="submit">Simpan Verifikasi Spesimen</button></div>
      </form><?php endif; ?>
    <?php endif; ?>
  </section>

  <!-- Langkah 5–7: pemeriksaan & input hasil -->
  <section class="wfx-panel">
    <h2>2. Pemeriksaan &amp; input hasil</h2>
    <?php if (in_array($st, ['ordered', 'rejected'], true)): ?>
      <div class="wfx-empty">Hasil dapat diinput setelah spesimen diterima.</div>
    <?php elseif ($st === 'in_progress' && $isATLM): ?>
      <?php if (($o['status_hasil'] ?? '') === 'returned'): ?><div class="wfx-alert"><b>Dikembalikan oleh validator.</b> <?= nl2br(e($o['catatan_validator'])) ?></div><?php endif; ?>
      <?php wfx_form_open('save_lab_results', (int)$o['id_kunjungan'], 'laboratory'); ?>
        <input type="hidden" name="id_order" value="<?= $orderId ?>">
        <div class="wfx-table-wrap"><table class="wfx-table"><thead><tr><th>Pemeriksaan</th><th style="min-width:160px">Hasil</th><th>Satuan</th><th>Nilai rujukan</th><th style="min-width:150px">Penanda</th></tr></thead><tbody>
        <?php foreach ($items as $it): $iid = (int)$it['id_lab_item']; ?>
          <tr><td><b><?= e($it['nama_pemeriksaan']) ?></b><br><small><?= e($it['kode_parameter']) ?> · <?= e($it['jenis_spesimen']) ?></small></td>
            <td><input name="nilai[<?= $iid ?>]" value="<?= e($it['nilai_hasil']) ?>" aria-label="Hasil <?= e($it['nama_pemeriksaan']) ?>"></td>
            <td><?= e($it['satuan']) ?></td><td><small><?= e($it['nilai_rujukan']) ?></small></td>
            <td><select name="penanda[<?= $iid ?>]" aria-label="Penanda <?= e($it['nama_pemeriksaan']) ?>"><?php foreach (['' => '—', 'normal' => 'Normal', 'low' => 'Rendah', 'high' => 'Tinggi', 'critical' => 'Kritis'] as $v => $l): ?><option value="<?= $v ?>" <?= ($it['penanda'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <div class="wfx-grid" style="margin-top:12px">
          <?php wfx_input('waktu_pemeriksaan', 'Waktu pemeriksaan', date('Y-m-d\TH:i', strtotime($items[0]['waktu_pemeriksaan'] ?? 'now')), 'datetime-local'); ?>
          <?php wfx_textarea('catatan_hasil', 'Catatan hasil / metode / alat', $o['hasil_teks'] ?? '', 2); ?>
        </div>
        <div class="wfx-form-actions"><button class="wfx-btn wfx-btn-primary" type="submit">Simpan Hasil (Input Hasil)</button></div>
      </form>
      <?php if (($o['status_hasil'] ?? '') === 'result_entered'): ?>
        <div class="wfx-subpanel" style="margin-top:16px">
          <h3>Periksa kelengkapan &amp; kewajaran hasil</h3>
          <p class="wfx-help">Jika hasil perlu diperiksa ulang, ulangi pemeriksaan lalu perbarui nilai di atas. Kirim ke validator hanya jika hasil sudah lengkap dan wajar.</p>
          <?php wfx_form_open('submit_lab_validation', (int)$o['id_kunjungan'], 'laboratory'); ?>
            <input type="hidden" name="id_order" value="<?= $orderId ?>">
            <label class="row" style="gap:8px;margin:8px 0;font-weight:500"><input type="checkbox" name="cek_kelengkapan" value="1" style="width:18px;height:18px;min-height:0"> Semua pemeriksaan sudah ada hasilnya (lengkap)</label>
            <label class="row" style="gap:8px;margin:8px 0;font-weight:500"><input type="checkbox" name="cek_kewajaran" value="1" style="width:18px;height:18px;min-height:0"> Hasil wajar secara klinis/analitik atau nilai kritis sudah dikonfirmasi ulang</label>
            <div class="wfx-form-actions"><button class="wfx-btn wfx-btn-success" type="submit">Kirim untuk Validasi</button></div>
          </form>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <?php lab_result_table($items, $o['hasil_teks']); ?>
      <?php if ($o['waktu_input']): ?><p class="wfx-help">Diinput oleh <?= e(lab_user($pdo, $o['diinput_oleh_user'])) ?> · <?= e(format_datetime($o['waktu_input'])) ?><?= $o['hasil_teks'] ? ' · Catatan: ' . e($o['hasil_teks']) : '' ?></p><?php endif; ?>
      <?php if ($st === 'in_progress'): ?><div class="wfx-empty">ATLM sedang melakukan pemeriksaan.</div><?php endif; ?>
    <?php endif; ?>
  </section>

  <!-- Langkah 8–12: validasi & laporan -->
  <section class="wfx-panel">
    <h2>3. Validasi hasil</h2>
    <?php if ($st === 'waiting_validation'): ?>
      <?php if ($isValidator): ?>
        <p class="wfx-help">Periksa hasil di atas beserta identitas pasien, jenis pemeriksaan, nilai, satuan, dan nilai rujukan.</p>
        <div class="wfx-grid">
          <?php wfx_form_open('validate_lab_result', (int)$o['id_kunjungan'], 'laboratory'); ?><input type="hidden" name="id_order" value="<?= $orderId ?>"><?php wfx_textarea('catatan_validator', 'Catatan validator (opsional)', '', 2); ?><div class="wfx-form-actions"><button class="wfx-btn wfx-btn-success" type="submit" onclick="return confirm('Validasi hasil ini menjadi VALIDATED/FINAL?')">Validasi</button></div></form>
          <?php wfx_form_open('return_lab_result', (int)$o['id_kunjungan'], 'laboratory'); ?><input type="hidden" name="id_order" value="<?= $orderId ?>"><?php wfx_textarea('catatan_validator', 'Alasan dikembalikan ke ATLM *', '', 2); ?><div class="wfx-form-actions"><button class="wfx-btn wfx-btn-danger" type="submit">Kembalikan ke ATLM</button></div></form>
        </div>
      <?php else: ?><div class="wfx-empty">Menunggu validasi oleh Validator / Dokter Laboratorium.</div><?php endif; ?>
    <?php elseif ($st === 'completed'): ?>
      <div class="notice success"><b>VALIDATED / FINAL</b> — divalidasi oleh <?= e(lab_user($pdo, $o['divalidasi_oleh_user'])) ?> pada <?= e(format_datetime($o['waktu_validasi'])) ?>. Hasil sudah tersimpan di Rekam Medis Elektronik pasien (kunjungan <?= e($o['no_kunjungan']) ?>).<?= $o['catatan_validator'] ? '<br>Catatan: ' . e($o['catatan_validator']) : '' ?></div>
      <a class="wfx-btn wfx-btn-primary" href="<?= e($base) ?>/lab_report.php?id_order=<?= $orderId ?>" target="_blank" rel="noopener">Cetak Laporan Hasil</a>
    <?php else: ?>
      <div class="wfx-empty">Validasi tersedia setelah ATLM mengirim hasil.</div>
    <?php endif; ?>
  </section>
