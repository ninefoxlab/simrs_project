<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/permissions.php';
require_once dirname(__DIR__) . '/app/helpers.php';

$user = require_login($pdo);
$uid = (int)$user['id_user'];
$roles = user_roles($pdo, $uid);

function wfa_has(array $roles, array $allowed): bool { return (bool)array_intersect($roles, $allowed); }
function wfa_need(array $roles, array $allowed): void {
    if (!wfa_has($roles, $allowed)) { http_response_code(403); exit('Akses ditolak: role tidak memiliki kewenangan untuk tindakan ini.'); }
}
function wfa_redirect(string $page, string $message, string $type='success'): never {
    flash_set($type, $message);
    $url = APP_BASE_URL . '/index.php?page=' . rawurlencode($page);
    $visitId = (int)($_POST['id_kunjungan'] ?? 0);
    if ($visitId > 0) $url .= '&id_kunjungan=' . $visitId;
    redirect_to($url);
}
function wfa_text(string $key, int $max=20000): string {
    $value = trim((string)($_POST[$key] ?? ''));
    if (strlen($value) > ($max * 4)) throw new RuntimeException('Isian ' . $key . ' terlalu panjang.');
    return $value;
}
function wfa_number(string $key, bool $required=false, float $min=0, ?float $max=null): ?float {
    $raw = trim((string)($_POST[$key] ?? ''));
    if ($raw === '') { if ($required) throw new RuntimeException('Kolom ' . $key . ' wajib diisi.'); return null; }
    if (!is_numeric($raw)) throw new RuntimeException('Kolom ' . $key . ' harus berupa angka.');
    $n=(float)$raw;
    if ($n < $min || ($max !== null && $n > $max)) throw new RuntimeException('Nilai ' . $key . ' di luar batas yang diperbolehkan.');
    return $n;
}
function wfa_visit(PDO $pdo, int $id, array $roles, array $user): array {
    if ($id < 1) throw new RuntimeException('Kunjungan belum dipilih.');
    $q=$pdo->prepare('SELECT * FROM kunjungan WHERE id_kunjungan=? LIMIT 1'); $q->execute([$id]);
    $v=$q->fetch(PDO::FETCH_ASSOC);
    if (!$v) throw new RuntimeException('Kunjungan tidak ditemukan.');
    if (in_array('DOKTER',$roles,true)) {
        if (empty($user['id_pegawai'])) throw new RuntimeException('Akun dokter belum terhubung dengan data pegawai. Hubungi IT Administrator.');
        if (empty($v['id_dokter'])) throw new RuntimeException('Kunjungan belum ditugaskan kepada dokter. Minta Admisi memperbarui dokter penanggung jawab.');
        if ((int)$v['id_dokter'] !== (int)$user['id_pegawai']) throw new RuntimeException('Kunjungan ini ditugaskan kepada dokter lain.');
    }
    return $v;
}
function wfa_order(PDO $pdo, int $id, int $visitId=0): array {
    if ($id < 1) throw new RuntimeException('Order tidak ditemukan.');
    $sql='SELECT * FROM simrs_wf_orders WHERE id_order=?'; $params=[$id];
    if ($visitId > 0) { $sql.=' AND id_kunjungan=?'; $params[]=$visitId; }
    $sql.=' LIMIT 1'; $q=$pdo->prepare($sql); $q->execute($params); $row=$q->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Order tidak ditemukan atau tidak cocok dengan kunjungan.');
    return $row;
}
function wfa_bill(PDO $pdo, int $id): array {
    $q=$pdo->prepare('SELECT * FROM simrs_wf_bills WHERE id_bill=? LIMIT 1'); $q->execute([$id]); $b=$q->fetch(PDO::FETCH_ASSOC);
    if (!$b) throw new RuntimeException('Tagihan tidak ditemukan.'); return $b;
}
function wfa_audit(PDO $pdo, int $uid, string $action, string $table, string $detail): void {
    audit_log($pdo,$uid,$action,$table,$detail);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? null)) {
    http_response_code(403); exit('Permintaan tidak valid atau token keamanan kedaluwarsa.');
}
$action=(string)($_POST['action']??'');
$idVisit=(int)($_POST['id_kunjungan']??0);
$page=(string)($_POST['return_page']??'emr');
$allowedPages=['emr','outpatient','inpatient','igd','nursing','cpoe','laboratory','radiology','pharmacy','billing','inventory','reporting'];
if (!in_array($page,$allowedPages,true)) $page='emr';

try {
    if ($action === 'save_emr') {
        wfa_need($roles,['DOKTER']);
        $visit=wfa_visit($pdo,$idVisit,$roles,$user);
        $q=$pdo->prepare('SELECT id_emr,status_emr FROM simrs_wf_emr WHERE id_kunjungan=?'); $q->execute([$idVisit]); $old=$q->fetch(PDO::FETCH_ASSOC);
        if ($old && $old['status_emr']==='signed') throw new RuntimeException('EMR sudah ditandatangani. Gunakan fitur Addendum untuk koreksi.');
        $fields=['keluhan_utama','onset','lokasi_keluhan','karakter_keluhan','durasi_keluhan','skala_keluhan','penjalaran','faktor_pemberat','faktor_peringan','riwayat_penyakit_sekarang','riwayat_penyakit_dahulu','riwayat_penyakit_keluarga','riwayat_sosial','riwayat_alergi','obat_digunakan','review_sistem','pemeriksaan_umum','pemeriksaan_kepala_leher','pemeriksaan_thoraks','pemeriksaan_jantung','pemeriksaan_abdomen','pemeriksaan_ekstremitas','pemeriksaan_neurologis','pemeriksaan_kulit','diagnosis_utama','kode_icd10','diagnosis_sekunder','diagnosis_banding','assessment','rencana_terapi','edukasi_pasien','rencana_kontrol'];
        $values=[]; foreach($fields as $f) $values[$f]=wfa_text($f,20000);
        if ($values['keluhan_utama']==='' || $values['diagnosis_utama']==='') throw new RuntimeException('Keluhan utama dan diagnosis utama wajib diisi.');
        $values['id_kunjungan']=$idVisit; $values['id_dokter']=$user['id_pegawai'] ?: null; $values['id_user']=$uid;
        $names=array_keys($values); $update=[]; foreach($fields as $f) $update[]="`{$f}`=VALUES(`{$f}`)"; $update[]='id_dokter=VALUES(id_dokter)'; $update[]='id_user=VALUES(id_user)'; $update[]="status_emr='draft'";
        $sql='INSERT INTO simrs_wf_emr (`'.implode('`,`',$names).'`) VALUES ('.implode(',',array_fill(0,count($names),'?')).') ON DUPLICATE KEY UPDATE '.implode(',',$update);
        $pdo->prepare($sql)->execute(array_values($values));
        wfa_audit($pdo,$uid,'SAVE_EMR_DRAFT','simrs_wf_emr','Draft EMR kunjungan '.$idVisit);
        wfa_redirect('emr','Draft rekam medis berhasil disimpan.');
    }

    if ($action === 'sign_emr') {
        wfa_need($roles,['DOKTER']); wfa_visit($pdo,$idVisit,$roles,$user);
        $q=$pdo->prepare("UPDATE simrs_wf_emr SET status_emr='signed',signed_by_user=?,signed_at=NOW() WHERE id_kunjungan=? AND status_emr='draft' AND keluhan_utama IS NOT NULL AND diagnosis_utama IS NOT NULL");
        $q->execute([$uid,$idVisit]);
        if (!$q->rowCount()) throw new RuntimeException('Draft EMR belum tersedia atau belum memenuhi isian wajib. Simpan draft terlebih dahulu.');
        wfa_audit($pdo,$uid,'SIGN_EMR','simrs_wf_emr','Menandatangani EMR kunjungan '.$idVisit);
        wfa_redirect('emr','EMR berhasil ditandatangani dan dikunci.');
    }

    if ($action === 'create_addendum') {
        wfa_need($roles,['DOKTER']); wfa_visit($pdo,$idVisit,$roles,$user);
        $q=$pdo->prepare("SELECT id_emr FROM simrs_wf_emr WHERE id_kunjungan=? AND status_emr='signed'"); $q->execute([$idVisit]); $emrId=(int)$q->fetchColumn();
        if (!$emrId) throw new RuntimeException('Addendum hanya dapat dibuat setelah EMR ditandatangani.');
        $reason=wfa_text('alasan_koreksi',2000); $content=wfa_text('isi_addendum',12000);
        if ($reason==='' || $content==='') throw new RuntimeException('Alasan koreksi dan isi addendum wajib diisi.');
        $pdo->prepare('INSERT INTO simrs_wf_emr_addenda (id_kunjungan,id_emr,alasan_koreksi,isi_addendum,dibuat_oleh_user) VALUES (?,?,?,?,?)')->execute([$idVisit,$emrId,$reason,$content,$uid]);
        wfa_audit($pdo,$uid,'CREATE_EMR_ADDENDUM','simrs_wf_emr_addenda','Addendum kunjungan '.$idVisit.': '.$reason);
        wfa_redirect('emr','Addendum tersimpan tanpa mengubah catatan yang sudah ditandatangani.');
    }

    if ($action === 'record_vitals') {
        wfa_need($roles,['PERAWAT','DOKTER']); wfa_visit($pdo,$idVisit,$roles,$user);
        $sys=wfa_number('tekanan_sistolik',false,0,300); $dia=wfa_number('tekanan_diastolik',false,0,200);
        $hr=wfa_number('nadi_per_menit',false,0,300); $rr=wfa_number('napas_per_menit',false,0,100);
        $temp=wfa_number('suhu_celsius',false,25,45); $spo=wfa_number('spo2',false,0,100);
        $weight=wfa_number('berat_kg',false,0,500); $height=wfa_number('tinggi_cm',false,0,250);
        $pain=wfa_number('skala_nyeri',false,0,10); $ge=wfa_number('gcs_e',false,1,4); $gv=wfa_number('gcs_v',false,1,5); $gm=wfa_number('gcs_m',false,1,6);
        $conscious=wfa_text('kesadaran',120); $note=wfa_text('catatan',3000);
        $pdo->prepare('INSERT INTO simrs_wf_vitals (id_kunjungan,dicatat_oleh_user,tekanan_sistolik,tekanan_diastolik,nadi_per_menit,napas_per_menit,suhu_celsius,spo2,berat_kg,tinggi_cm,skala_nyeri,gcs_e,gcs_v,gcs_m,kesadaran,catatan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$idVisit,$uid,$sys,$dia,$hr,$rr,$temp,$spo,$weight,$height,$pain,$ge,$gv,$gm,$conscious,$note]);
        wfa_audit($pdo,$uid,'RECORD_VITALS','simrs_wf_vitals','Mencatat tanda vital kunjungan '.$idVisit);
        wfa_redirect($page,'Tanda vital berhasil dicatat dan dapat dilihat dokter.');
    }

    if ($action === 'save_nursing') {
        wfa_need($roles,['PERAWAT']); wfa_visit($pdo,$idVisit,$roles,$user);
        $fields=['subjektif','objektif','asesmen','diagnosis_keperawatan','intervensi','evaluasi','risiko_jatuh','status_nutrisi','mobilitas'];
        $vals=[]; foreach($fields as $f) $vals[$f]=wfa_text($f,12000);
        $intake=wfa_number('intake_ml',false,0,100000); $output=wfa_number('output_ml',false,0,100000);
        $names=array_merge(['id_kunjungan','id_perawat_user'],$fields,['intake_ml','output_ml']);
        $values=array_merge([$idVisit,$uid],array_values($vals),[$intake,$output]);
        $sql='INSERT INTO simrs_wf_nursing (`'.implode('`,`',$names).'`) VALUES ('.implode(',',array_fill(0,count($names),'?')).')';
        $pdo->prepare($sql)->execute($values);
        wfa_audit($pdo,$uid,'SAVE_NURSING_NOTE','simrs_wf_nursing','Catatan keperawatan kunjungan '.$idVisit);
        wfa_redirect('nursing','Asesmen dan catatan keperawatan berhasil disimpan.');
    }

    if ($action === 'save_igd_triage') {
        wfa_need($roles,['PERAWAT','DOKTER']); $visit=wfa_visit($pdo,$idVisit,$roles,$user);
        if (($visit['tipe_pelayanan']??'')!=='IGD') throw new RuntimeException('Triase hanya untuk kunjungan IGD.');
        $category=(string)($_POST['kategori_triase']??'kuning');
        if (!in_array($category,['merah','kuning','hijau','hitam'],true)) throw new RuntimeException('Kategori triase tidak valid.');
        $pain=wfa_number('nyeri',false,0,10); $gcs=wfa_text('gcs',20);
        $fields=['keluhan_utama','jalan_napas','pernapasan','sirkulasi','kesadaran','alergi','tindakan_awal']; $vals=[]; foreach($fields as $f)$vals[$f]=wfa_text($f,12000);
        $q=$pdo->prepare('SELECT id_triage FROM simrs_wf_igd_triage WHERE id_kunjungan=? ORDER BY id_triage DESC LIMIT 1');$q->execute([$idVisit]);$id=(int)$q->fetchColumn();
        if($id){$set='kategori_triase=?,nyeri=?,gcs=?,'.implode(',',array_map(fn($f)=>"`{$f}`=?",$fields)).",status_triase='menunggu_dokter',diperbarui_pada=NOW()";$pdo->prepare("UPDATE simrs_wf_igd_triage SET {$set} WHERE id_triage=?")->execute(array_merge([$category,$pain,$gcs],array_values($vals),[$id]));}
        else{$names=array_merge(['id_kunjungan','id_petugas_user','kategori_triase','nyeri','gcs'],$fields);$values=array_merge([$idVisit,$uid,$category,$pain,$gcs],array_values($vals));$pdo->prepare('INSERT INTO simrs_wf_igd_triage (`'.implode('`,`',$names).'`) VALUES ('.implode(',',array_fill(0,count($names),'?')).')')->execute($values);}
        wfa_audit($pdo,$uid,'SAVE_IGD_TRIAGE','simrs_wf_igd_triage','Triase '.$category.' kunjungan '.$idVisit);
        wfa_redirect('igd','Triase IGD berhasil disimpan. Pastikan pasien dengan prioritas merah segera ditangani sesuai SOP kegawatdaruratan.');
    }

    if ($action === 'create_order') {
        wfa_need($roles,['DOKTER']); $visit=wfa_visit($pdo,$idVisit,$roles,$user);
        $emrQ=$pdo->prepare('SELECT status_emr FROM simrs_wf_emr WHERE id_kunjungan=?'); $emrQ->execute([$idVisit]); $emrStatus=$emrQ->fetchColumn();
        if($emrStatus===false) throw new RuntimeException('Isi dan simpan draft asesmen EMR terlebih dahulu sebelum membuat order.');
        if($emrStatus==='signed') throw new RuntimeException('EMR sudah ditandatangani. Permintaan layanan baru harus melalui prosedur addendum/perintah lanjutan yang ditetapkan RS.');
        $type=(string)($_POST['jenis_order']??''); $allowed=['LAB','RADIOLOGI','FARMASI','TINDAKAN','KONSULTASI'];
        if(!in_array($type,$allowed,true)) throw new RuntimeException('Jenis order tidak valid.');
        $name=wfa_text('nama_layanan',255); if($name==='')throw new RuntimeException('Nama pemeriksaan, obat, atau tindakan wajib diisi.');
        $priority=(string)($_POST['prioritas']??'Normal');if(!in_array($priority,['Normal','Segera','Cito'],true))throw new RuntimeException('Prioritas tidak valid.');
        $qty=wfa_number('jumlah', $type==='FARMASI', 0.01, 100000);
        if($type==='FARMASI' && (wfa_text('dosis',120)==='' || wfa_text('frekuensi',120)==='' || wfa_text('rute',80)==='')) throw new RuntimeException('Resep wajib memuat dosis, rute, frekuensi, dan jumlah.');
        $no='WF-'.date('Ymd').'-'.str_pad((string)$idVisit,6,'0',STR_PAD_LEFT).'-'.strtoupper(bin2hex(random_bytes(3)));
        $targetUnit=(int)($visit['id_unit']??0) ?: null;
        $targetTerms=['LAB'=>'laboratorium','RADIOLOGI'=>'radiologi','FARMASI'=>'farmasi'];
        if(isset($targetTerms[$type])) {
            try { $uq=$pdo->prepare('SELECT id_unit FROM unit WHERE LOWER(nama_unit) LIKE ? ORDER BY id_unit LIMIT 1'); $uq->execute(['%'.$targetTerms[$type].'%']); $found=$uq->fetchColumn(); if($found!==false)$targetUnit=(int)$found; } catch(Throwable $ignore) {}
        }
        $s=$pdo->prepare('INSERT INTO simrs_wf_orders (id_kunjungan,no_order,jenis_order,kode_layanan,nama_layanan,instruksi,prioritas,dosis,rute,frekuensi,durasi,jumlah,satuan_jumlah,id_pembuat_user,id_unit_tujuan,status_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $s->execute([$idVisit,$no,$type,wfa_text('kode_layanan',50),$name,wfa_text('instruksi',12000),$priority,wfa_text('dosis',120),wfa_text('rute',80),wfa_text('frekuensi',120),wfa_text('durasi',120),$qty,wfa_text('satuan_jumlah',50),$uid,$targetUnit,'ordered']);
        wfa_audit($pdo,$uid,'CREATE_SERVICE_ORDER','simrs_wf_orders','Order '.$no.' ('.$type.') untuk kunjungan '.$idVisit);
        wfa_redirect('cpoe','Order berhasil dikirim ke antrean unit terkait.');
    }

    if ($action === 'accept_order') {
        $order=wfa_order($pdo,(int)($_POST['id_order']??0),$idVisit);
        $type=$order['jenis_order'];
        if($type==='LAB') wfa_need($roles,['ATLM']);
        elseif($type==='RADIOLOGI') wfa_need($roles,['RADIOGRAFER']);
        elseif($type==='FARMASI') wfa_need($roles,['APOTEKER']);
        elseif(in_array($type,['TINDAKAN','KONSULTASI'],true)) wfa_need($roles,['PERAWAT','DOKTER']);
        else throw new RuntimeException('Jenis order tidak dikenali.');
        if($order['status_order']!=='ordered')throw new RuntimeException('Order sudah diproses atau bukan lagi status menunggu.');
        $pdo->prepare("UPDATE simrs_wf_orders SET status_order='in_progress',diperbarui_pada=NOW() WHERE id_order=?")->execute([(int)$order['id_order']]);
        wfa_audit($pdo,$uid,'ACCEPT_SERVICE_ORDER','simrs_wf_orders','Menerima order '.$order['no_order']);
        wfa_redirect($type==='LAB'?'laboratory':($type==='RADIOLOGI'?'radiology':($type==='FARMASI'?'pharmacy':'nursing')),'Order diterima dan masuk status dalam proses.');
    }

    if ($action === 'submit_lab_result') {
        wfa_need($roles,['ATLM']); $order=wfa_order($pdo,(int)($_POST['id_order']??0),$idVisit);
        if($order['jenis_order']!=='LAB'||$order['status_order']!=='in_progress')throw new RuntimeException('Order laboratorium harus diterima ATLM sebelum hasil diinput.');
        $text=wfa_text('hasil_teks',30000);if($text==='')throw new RuntimeException('Hasil laboratorium wajib diisi.');
        $q=$pdo->prepare("INSERT INTO simrs_wf_results (id_order,hasil_teks,hasil_angka,satuan,nilai_rujukan,penanda_abnormal,catatan_validator,status_hasil,diinput_oleh_user,waktu_input) VALUES (?,?,?,?,?,?,NULL,'result_entered',?,NOW()) ON DUPLICATE KEY UPDATE hasil_teks=VALUES(hasil_teks),hasil_angka=VALUES(hasil_angka),satuan=VALUES(satuan),nilai_rujukan=VALUES(nilai_rujukan),penanda_abnormal=VALUES(penanda_abnormal),status_hasil='result_entered',diinput_oleh_user=VALUES(diinput_oleh_user),waktu_input=NOW(),direview_oleh_user=NULL,divalidasi_oleh_user=NULL,waktu_review=NULL,waktu_validasi=NULL");
        $q->execute([$order['id_order'],$text,wfa_text('hasil_angka',100),wfa_text('satuan',80),wfa_text('nilai_rujukan',160),wfa_text('penanda_abnormal',40),$uid]);
        $pdo->prepare("UPDATE simrs_wf_orders SET status_order='result_entered',diperbarui_pada=NOW() WHERE id_order=?")->execute([$order['id_order']]);
        wfa_audit($pdo,$uid,'ENTER_LAB_RESULT','simrs_wf_results','Input hasil LAB '.$order['no_order']);
        wfa_redirect('laboratory','Hasil laboratorium tersimpan dan masuk antrean review Dokter Laboratorium.');
    }

    if ($action === 'review_lab_result') {
        wfa_need($roles,['DOKTER_LAB']); $order=wfa_order($pdo,(int)($_POST['id_order']??0),$idVisit);
        if($order['jenis_order']!=='LAB')throw new RuntimeException('Order bukan laboratorium.');
        $comment=wfa_text('catatan_reviewer',6000);
        $q=$pdo->prepare("UPDATE simrs_wf_results SET status_hasil='reviewed',catatan_reviewer=?,direview_oleh_user=?,waktu_review=NOW() WHERE id_order=? AND status_hasil='result_entered'");$q->execute([$comment,$uid,$order['id_order']]);
        if(!$q->rowCount())throw new RuntimeException('Hasil belum diinput oleh ATLM atau sudah direview.');
        wfa_audit($pdo,$uid,'REVIEW_LAB_RESULT','simrs_wf_results','Review hasil LAB '.$order['no_order']);
        wfa_redirect('laboratory','Hasil selesai direview dan menunggu validasi final.');
    }

    if ($action === 'request_lab_revision') {
        wfa_need($roles,['DOKTER_LAB']);
        $order=wfa_order($pdo,(int)($_POST['id_order']??0),$idVisit);
        if($order['jenis_order']!=='LAB')throw new RuntimeException('Order bukan laboratorium.');
        $note=wfa_text('catatan_reviewer',6000);
        if($note==='')throw new RuntimeException('Alasan pengembalian hasil kepada ATLM wajib diisi.');
        $q=$pdo->prepare("UPDATE simrs_wf_results SET status_hasil='revision_requested',catatan_reviewer=?,direview_oleh_user=?,waktu_review=NOW() WHERE id_order=? AND status_hasil='result_entered'");
        $q->execute([$note,$uid,$order['id_order']]);
        if(!$q->rowCount())throw new RuntimeException('Hasil belum tersedia untuk dikembalikan atau sudah diproses.');
        $pdo->prepare("UPDATE simrs_wf_orders SET status_order='in_progress',diperbarui_pada=NOW() WHERE id_order=?")->execute([$order['id_order']]);
        wfa_audit($pdo,$uid,'REQUEST_LAB_RESULT_REVISION','simrs_wf_results','Meminta koreksi hasil LAB '.$order['no_order']);
        wfa_redirect('laboratory','Hasil dikembalikan kepada ATLM untuk koreksi.');
    }

    if ($action === 'validate_lab_result') {
        wfa_need($roles,['VALIDATOR_LAB']); $order=wfa_order($pdo,(int)($_POST['id_order']??0),$idVisit);
        if($order['jenis_order']!=='LAB')throw new RuntimeException('Order bukan laboratorium.');
        $comment=wfa_text('catatan_validator',6000);
        $q=$pdo->prepare("UPDATE simrs_wf_results SET status_hasil='validated',catatan_validator=?,divalidasi_oleh_user=?,waktu_validasi=NOW() WHERE id_order=? AND status_hasil='reviewed'");$q->execute([$comment,$uid,$order['id_order']]);
        if(!$q->rowCount())throw new RuntimeException('Hasil harus direview Dokter Laboratorium terlebih dahulu.');
        $pdo->prepare("UPDATE simrs_wf_orders SET status_order='completed',diperbarui_pada=NOW() WHERE id_order=?")->execute([$order['id_order']]);
        wfa_audit($pdo,$uid,'VALIDATE_LAB_RESULT','simrs_wf_results','Validasi final LAB '.$order['no_order']);
        wfa_redirect('laboratory','Hasil laboratorium divalidasi final dan tersedia bagi dokter.');
    }

    if ($action === 'submit_radiology_result') {
        wfa_need($roles,['RADIOGRAFER']); $order=wfa_order($pdo,(int)($_POST['id_order']??0),$idVisit);
        if($order['jenis_order']!=='RADIOLOGI'||$order['status_order']!=='in_progress')throw new RuntimeException('Order radiologi harus diterima Radiografer sebelum hasil diinput.');
        $tech=wfa_text('teknik_pemeriksaan',12000);$find=wfa_text('temuan',20000);$text=wfa_text('hasil_teks',20000);
        if($tech===''||($find===''&&$text===''))throw new RuntimeException('Teknik dan temuan pemeriksaan harus diisi.');
        $pdo->prepare("INSERT INTO simrs_wf_results (id_order,hasil_teks,teknik_pemeriksaan,temuan,status_hasil,diinput_oleh_user,waktu_input) VALUES (?,?,?,?, 'result_entered',?,NOW()) ON DUPLICATE KEY UPDATE hasil_teks=VALUES(hasil_teks),teknik_pemeriksaan=VALUES(teknik_pemeriksaan),temuan=VALUES(temuan),status_hasil='result_entered',diinput_oleh_user=VALUES(diinput_oleh_user),waktu_input=NOW(),direview_oleh_user=NULL,divalidasi_oleh_user=NULL,waktu_review=NULL,waktu_validasi=NULL")->execute([$order['id_order'],$text,$tech,$find,$uid]);
        $pdo->prepare("UPDATE simrs_wf_orders SET status_order='result_entered',diperbarui_pada=NOW() WHERE id_order=?")->execute([$order['id_order']]);
        wfa_audit($pdo,$uid,'ENTER_RADIOLOGY_RESULT','simrs_wf_results','Input hasil radiologi '.$order['no_order']);
        wfa_redirect('radiology','Hasil teknis radiologi tersimpan dan menunggu pembacaan Dokter Radiologi.');
    }

    if ($action === 'request_radiology_revision') {
        wfa_need($roles,['DOKTER_RAD']);
        $order=wfa_order($pdo,(int)($_POST['id_order']??0),$idVisit);
        if($order['jenis_order']!=='RADIOLOGI')throw new RuntimeException('Order bukan radiologi.');
        $note=wfa_text('catatan_reviewer',6000);
        if($note==='')throw new RuntimeException('Alasan pengembalian hasil kepada Radiografer wajib diisi.');
        $q=$pdo->prepare("UPDATE simrs_wf_results SET status_hasil='revision_requested',catatan_reviewer=?,direview_oleh_user=?,waktu_review=NOW() WHERE id_order=? AND status_hasil='result_entered'");
        $q->execute([$note,$uid,$order['id_order']]);
        if(!$q->rowCount())throw new RuntimeException('Temuan radiologi belum tersedia untuk dikembalikan atau sudah diproses.');
        $pdo->prepare("UPDATE simrs_wf_orders SET status_order='in_progress',diperbarui_pada=NOW() WHERE id_order=?")->execute([$order['id_order']]);
        wfa_audit($pdo,$uid,'REQUEST_RADIOLOGY_REVISION','simrs_wf_results','Meminta koreksi radiologi '.$order['no_order']);
        wfa_redirect('radiology','Temuan dikembalikan kepada Radiografer untuk koreksi.');
    }

    if ($action === 'finalize_radiology_result') {
        wfa_need($roles,['DOKTER_RAD']); $order=wfa_order($pdo,(int)($_POST['id_order']??0),$idVisit);
        if($order['jenis_order']!=='RADIOLOGI')throw new RuntimeException('Order bukan radiologi.');
        $impression=wfa_text('kesan',12000);$comment=wfa_text('catatan_reviewer',6000);
        if($impression==='')throw new RuntimeException('Kesan radiologi wajib diisi.');
        $q=$pdo->prepare("UPDATE simrs_wf_results SET kesan=?,catatan_reviewer=?,status_hasil='validated',direview_oleh_user=?,divalidasi_oleh_user=?,waktu_review=NOW(),waktu_validasi=NOW() WHERE id_order=? AND status_hasil='result_entered'");
        $q->execute([$impression,$comment,$uid,$uid,$order['id_order']]);if(!$q->rowCount())throw new RuntimeException('Hasil radiologi belum diinput oleh Radiografer.');
        $pdo->prepare("UPDATE simrs_wf_orders SET status_order='completed',diperbarui_pada=NOW() WHERE id_order=?")->execute([$order['id_order']]);
        wfa_audit($pdo,$uid,'FINALIZE_RADIOLOGY_REPORT','simrs_wf_results','Finalisasi laporan radiologi '.$order['no_order']);
        wfa_redirect('radiology','Laporan radiologi telah ditandatangani dan kembali tersedia untuk dokter perujuk.');
    }

    if ($action === 'verify_prescription') {
        wfa_need($roles,['APOTEKER']); $order=wfa_order($pdo,(int)($_POST['id_order']??0),$idVisit);
        if($order['jenis_order']!=='FARMASI'||$order['status_order']!=='in_progress')throw new RuntimeException('Resep harus diterima Apoteker sebelum diverifikasi.');
        $note=wfa_text('catatan_farmasi',6000);
        $pdo->prepare("UPDATE simrs_wf_orders SET status_order='verified',catatan_pelaksana=?,diperbarui_pada=NOW() WHERE id_order=?")->execute([$note,$order['id_order']]);
        wfa_audit($pdo,$uid,'VERIFY_PRESCRIPTION','simrs_wf_orders','Verifikasi resep '.$order['no_order']);
        wfa_redirect('pharmacy','Resep sudah diverifikasi. Apoteker dapat mencatat penyerahan obat.');
    }

    if ($action === 'dispense_prescription') {
        wfa_need($roles,['APOTEKER']); $order=wfa_order($pdo,(int)($_POST['id_order']??0),$idVisit);
        if($order['jenis_order']!=='FARMASI'||!in_array($order['status_order'],['verified','partially_dispensed'],true))throw new RuntimeException('Resep harus diverifikasi sebelum diserahkan.');
        $qty=wfa_number('jumlah_diserahkan',true,0.01,100000);$requested=(float)($order['jumlah']??0);$already=(float)($order['jumlah_diserahkan']??0);
        if($requested>0 && $already+$qty>$requested+0.0001)throw new RuntimeException('Jumlah penyerahan melebihi jumlah resep.');
        $note=wfa_text('catatan_farmasi',6000);$new=$already+$qty;$status=($requested<=0||$new+0.0001 >= $requested)?'completed':'partially_dispensed';
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO simrs_wf_dispenses (id_order,jumlah_diserahkan,catatan,diserahkan_oleh_user) VALUES (?,?,?,?)')->execute([$order['id_order'],$qty,$note,$uid]);
        $pdo->prepare('UPDATE simrs_wf_orders SET jumlah_diserahkan=?,catatan_pelaksana=?,status_order=?,diperbarui_pada=NOW() WHERE id_order=?')->execute([$new,$note,$status,$order['id_order']]);
        $pdo->commit();
        wfa_audit($pdo,$uid,'DISPENSE_PRESCRIPTION','simrs_wf_dispenses','Penyerahan resep '.$order['no_order'].' qty '.$qty);
        wfa_redirect('pharmacy',$status==='completed'?'Penyerahan resep lengkap tercatat.':'Penyerahan sebagian tercatat; sisa resep masih harus dipenuhi.');
    }

    if ($action === 'record_med_admin') {
        wfa_need($roles,['PERAWAT']); $order=wfa_order($pdo,(int)($_POST['id_order']??0),$idVisit);
        if($order['jenis_order']!=='FARMASI'||!in_array($order['status_order'],['completed','partially_dispensed'],true))throw new RuntimeException('Obat harus sudah diserahkan Apoteker sebelum dicatat pemberiannya.');
        $status=(string)($_POST['status_pemberian']??'diberikan');if(!in_array($status,['diberikan','ditunda','ditolak','tidak_diberikan'],true))throw new RuntimeException('Status pemberian tidak valid.');
        $reason=wfa_text('alasan_tidak_diberikan',4000);if($status!=='diberikan'&&$reason==='')throw new RuntimeException('Alasan wajib dicatat untuk obat yang tidak diberikan/ditunda.');
        $time=wfa_text('waktu_pemberian',30);if($time==='')$time=date('Y-m-d H:i:s');else $time=date('Y-m-d H:i:s',strtotime($time));
        $pdo->prepare('INSERT INTO simrs_wf_med_admin (id_order,id_kunjungan,dosis_diberikan,rute,waktu_pemberian,status_pemberian,alasan_tidak_diberikan,catatan,dicatat_oleh_user) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$order['id_order'],$idVisit,wfa_text('dosis_diberikan',120),wfa_text('rute',80),$time,$status,$reason,wfa_text('catatan',3000),$uid]);
        wfa_audit($pdo,$uid,'RECORD_MEDICATION_ADMINISTRATION','simrs_wf_med_admin','Pencatatan pemberian obat order '.$order['no_order']);
        wfa_redirect('nursing','Pemberian obat tercatat pada e-MAR dan dapat dilihat dokter.');
    }

    if ($action === 'complete_procedure') {
        wfa_need($roles,['PERAWAT','DOKTER']); $order=wfa_order($pdo,(int)($_POST['id_order']??0),$idVisit);
        if(!in_array($order['jenis_order'],['TINDAKAN','KONSULTASI'],true))throw new RuntimeException('Order bukan tindakan/konsultasi.');
        if($order['status_order']!=='in_progress')throw new RuntimeException('Tindakan harus diterima petugas sebelum dinyatakan selesai.');
        $note=wfa_text('catatan_pelaksana',8000);if($note==='')throw new RuntimeException('Catatan pelaksanaan wajib diisi.');
        $pdo->prepare("UPDATE simrs_wf_orders SET status_order='completed',catatan_pelaksana=?,diperbarui_pada=NOW() WHERE id_order=?")->execute([$note,$order['id_order']]);
        wfa_audit($pdo,$uid,'COMPLETE_PROCEDURE','simrs_wf_orders','Pelaksanaan selesai '.$order['no_order']);
        wfa_redirect('nursing','Pelaksanaan tindakan berhasil dicatat.');
    }

    if ($action === 'send_to_billing') {
        wfa_need($roles,['DOKTER']); $visit=wfa_visit($pdo,$idVisit,$roles,$user);
        $q=$pdo->prepare("SELECT id_emr FROM simrs_wf_emr WHERE id_kunjungan=? AND status_emr='signed'");$q->execute([$idVisit]);if(!(int)$q->fetchColumn())throw new RuntimeException('EMR harus ditandatangani dokter sebelum dikirim ke Billing.');
        $q=$pdo->prepare("SELECT COUNT(*) FROM simrs_wf_orders WHERE id_kunjungan=? AND status_order NOT IN ('completed','cancelled')");$q->execute([$idVisit]);if((int)$q->fetchColumn()>0)throw new RuntimeException('Masih ada order yang belum selesai. Periksa CPOE, laboratorium, radiologi, farmasi, dan tindakan.');
        $q=$pdo->prepare('SELECT id_bill FROM simrs_wf_bills WHERE id_kunjungan=?');$q->execute([$idVisit]);if($q->fetchColumn())throw new RuntimeException('Kunjungan sudah memiliki tagihan.');
        $pdo->beginTransaction();
        $no='INV-'.date('Ymd').'-'.str_pad((string)$idVisit,6,'0',STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO simrs_wf_bills (id_kunjungan,no_tagihan,status_tagihan,dibuat_oleh_user) VALUES (?,?,'needs_tariff',?)")->execute([$idVisit,$no,$uid]);$bill=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO simrs_wf_bill_items (id_bill,kategori,uraian,jumlah) VALUES (?,'jasa','Jasa konsultasi / pelayanan dokter',1)")->execute([$bill]);
        $q=$pdo->prepare('SELECT id_order,jenis_order,nama_layanan,jumlah,jumlah_diserahkan FROM simrs_wf_orders WHERE id_kunjungan=? ORDER BY id_order');$q->execute([$idVisit]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $o){$label=['LAB'=>'Laboratorium','RADIOLOGI'=>'Radiologi','FARMASI'=>'Farmasi','TINDAKAN'=>'Tindakan medis','KONSULTASI'=>'Konsultasi'][$o['jenis_order']]??$o['jenis_order'];$qty=(float)($o['jumlah_diserahkan']?:$o['jumlah']?:1);$pdo->prepare('INSERT INTO simrs_wf_bill_items (id_bill,id_order,kategori,uraian,jumlah) VALUES (?,?,?,?,?)')->execute([$bill,$o['id_order'],strtolower($o['jenis_order']),$label.': '.$o['nama_layanan'],$qty]);}
        $pdo->commit();wfa_audit($pdo,$uid,'SEND_TO_BILLING','simrs_wf_bills','Kunjungan '.$idVisit.' dikirim ke Billing '.$no);
        wfa_redirect('emr','Kunjungan dikirim ke Billing. Kasir perlu mengisi tarif sesuai tarif resmi rumah sakit.');
    }

    if ($action === 'set_bill_tariffs') {
        wfa_need($roles,['KASIR']);$billId=(int)($_POST['id_bill']??0);$bill=wfa_bill($pdo,$billId);
        if($bill['status_tagihan']!=='needs_tariff')throw new RuntimeException('Tarif untuk tagihan ini sudah dikonfirmasi atau status berubah.');
        $tariffs=$_POST['tarif']??[];if(!is_array($tariffs)||!$tariffs)throw new RuntimeException('Tarif belum dimasukkan.');
        $q=$pdo->prepare('SELECT id_bill_item FROM simrs_wf_bill_items WHERE id_bill=?');$q->execute([$billId]);$valid=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        if(count($tariffs)!==count($valid))throw new RuntimeException('Seluruh item tagihan harus diberi tarif.');
        $pdo->beginTransaction();$up=$pdo->prepare('UPDATE simrs_wf_bill_items SET tarif_satuan=?,total_item=jumlah*? WHERE id_bill_item=? AND id_bill=?');
        foreach($tariffs as $item=>$price){if(!ctype_digit((string)$item)||!in_array((int)$item,$valid,true)||!is_numeric($price)||(float)$price<0)throw new RuntimeException('Data tarif tidak valid.');$up->execute([(float)$price,(float)$price,(int)$item,$billId]);}
        $q=$pdo->prepare('SELECT COALESCE(SUM(total_item),0) FROM simrs_wf_bill_items WHERE id_bill=?');$q->execute([$billId]);$total=(float)$q->fetchColumn();if($total<=0)throw new RuntimeException('Total tagihan harus lebih dari nol.');
        $pdo->prepare("UPDATE simrs_wf_bills SET subtotal=?,total=?,status_tagihan='await_payment',diperbarui_pada=NOW() WHERE id_bill=?")->execute([$total,$total,$billId]);$pdo->commit();
        wfa_audit($pdo,$uid,'CONFIRM_BILL_TARIFF','simrs_wf_bills','Konfirmasi tarif '.$bill['no_tagihan']);wfa_redirect('billing','Tarif tersimpan. Tagihan siap menerima pembayaran.');
    }

    if ($action === 'record_payment') {
        wfa_need($roles,['KASIR']);$billId=(int)($_POST['id_bill']??0);$amount=wfa_number('jumlah_bayar',true,0.01,100000000000);
        $method=(string)($_POST['metode_pembayaran']??'Tunai');if(!in_array($method,['Tunai','Transfer','Debit','Kartu Kredit','Penjamin','QRIS'],true))throw new RuntimeException('Metode pembayaran tidak valid.');
        $pdo->beginTransaction();$q=$pdo->prepare('SELECT * FROM simrs_wf_bills WHERE id_bill=? FOR UPDATE');$q->execute([$billId]);$bill=$q->fetch(PDO::FETCH_ASSOC);if(!$bill||!in_array($bill['status_tagihan'],['await_payment','partially_paid'],true))throw new RuntimeException('Tagihan belum siap menerima pembayaran.');
        $q=$pdo->prepare('SELECT COALESCE(SUM(jumlah_bayar),0) FROM simrs_wf_payments WHERE id_bill=?');$q->execute([$billId]);$paid=(float)$q->fetchColumn();$remain=(float)$bill['total']-$paid;if($amount>$remain+0.0001)throw new RuntimeException('Pembayaran melebihi sisa tagihan.');
        $pdo->prepare('INSERT INTO simrs_wf_payments (id_bill,jumlah_bayar,metode_pembayaran,referensi,diterima_oleh_user) VALUES (?,?,?,?,?)')->execute([$billId,$amount,$method,wfa_text('referensi',120),$uid]);
        $status=($paid+$amount+0.0001 >= (float)$bill['total'])?'paid':'partially_paid';$pdo->prepare('UPDATE simrs_wf_bills SET status_tagihan=?,diperbarui_pada=NOW() WHERE id_bill=?')->execute([$status,$billId]);$pdo->commit();
        wfa_audit($pdo,$uid,'RECORD_BILL_PAYMENT','simrs_wf_payments','Pembayaran '.$bill['no_tagihan'].' Rp '.$amount);wfa_redirect('billing',$status==='paid'?'Pembayaran lunas berhasil dicatat.':'Pembayaran sebagian berhasil dicatat.');
    }

    if ($action === 'submit_claim') {
        wfa_need($roles,['ASURANSI']);$billId=(int)($_POST['id_bill']??0);$bill=wfa_bill($pdo,$billId);
        if(!in_array($bill['status_tagihan'],['await_payment','partially_paid','paid'],true))throw new RuntimeException('Tagihan belum siap diajukan ke penjamin.');
        $name=wfa_text('nama_penjamin',160);$participant=wfa_text('no_peserta',120);$claimNo=wfa_text('no_klaim',120);$amount=wfa_number('jumlah_diajukan',true,0,100000000000);
        if($name===''||$participant===''||$claimNo==='')throw new RuntimeException('Nama penjamin, nomor peserta, dan nomor klaim wajib diisi.');
        if($amount>(float)$bill['total'])throw new RuntimeException('Jumlah klaim melebihi total tagihan.');
        $pdo->prepare("INSERT INTO simrs_wf_claims (id_bill,nama_penjamin,no_peserta,no_klaim,jumlah_diajukan,status_klaim,diajukan_pada) VALUES (?,?,?,?,?,'submitted',NOW()) ON DUPLICATE KEY UPDATE nama_penjamin=VALUES(nama_penjamin),no_peserta=VALUES(no_peserta),no_klaim=VALUES(no_klaim),jumlah_diajukan=VALUES(jumlah_diajukan),status_klaim='submitted',diajukan_pada=NOW(),catatan_verifikasi=NULL")->execute([$billId,$name,$participant,$claimNo,$amount]);
        wfa_audit($pdo,$uid,'SUBMIT_INSURANCE_CLAIM','simrs_wf_claims','Pengajuan klaim '.$claimNo);wfa_redirect('billing','Pengajuan klaim dicatat dan menunggu verifikasi.');
    }

    if ($action === 'review_claim') {
        wfa_need($roles,['ASURANSI']);$claimId=(int)($_POST['id_claim']??0);$q=$pdo->prepare("SELECT * FROM simrs_wf_claims WHERE id_claim=? AND status_klaim='submitted'");$q->execute([$claimId]);$claim=$q->fetch(PDO::FETCH_ASSOC);if(!$claim)throw new RuntimeException('Klaim tidak ditemukan atau sudah diproses.');
        $status=(string)($_POST['status_klaim']??'');if(!in_array($status,['approved','partially_approved','rejected','need_revision'],true))throw new RuntimeException('Status klaim tidak valid.');
        $approved=wfa_number('jumlah_disetujui',false,0,(float)$claim['jumlah_diajukan'])??0;$note=wfa_text('catatan_verifikasi',8000);
        if($status==='approved')$approved=(float)$claim['jumlah_diajukan'];if($status==='rejected')$approved=0;
        $pdo->prepare('UPDATE simrs_wf_claims SET status_klaim=?,jumlah_disetujui=?,catatan_verifikasi=?,diverifikasi_oleh_user=?,diverifikasi_pada=NOW() WHERE id_claim=?')->execute([$status,$approved,$note,$uid,$claimId]);
        wfa_audit($pdo,$uid,'REVIEW_INSURANCE_CLAIM','simrs_wf_claims','Review klaim '.$claim['no_klaim'].' status '.$status);wfa_redirect('billing','Status klaim berhasil diperbarui.');
    }

    if ($action === 'create_stock_item') {
        wfa_need($roles,['KEPALA_GUDANG']);$code=wfa_text('kode_item',60);$name=wfa_text('nama_item',200);$category=wfa_text('kategori',80);$unit=wfa_text('satuan',40);$min=wfa_number('stok_minimum',false,0,100000000)??0;
        if($code===''||$name===''||$category===''||$unit==='')throw new RuntimeException('Kode, nama barang, kategori, dan satuan wajib diisi.');
        $pdo->prepare('INSERT INTO simrs_wf_stock_items (kode_item,nama_item,kategori,satuan,stok_minimum,dibuat_oleh_user) VALUES (?,?,?,?,?,?)')->execute([$code,$name,$category,$unit,$min,$uid]);
        wfa_audit($pdo,$uid,'CREATE_STOCK_ITEM','simrs_wf_stock_items','Membuat master barang '.$code);wfa_redirect('inventory','Master barang berhasil ditambahkan.');
    }

    if ($action === 'request_stock_move') {
        wfa_need($roles,['PETUGAS_GUDANG']);$item=(int)($_POST['id_item']??0);$type=(string)($_POST['jenis_mutasi']??'');$qty=wfa_number('jumlah',true,0.01,100000000);if(!in_array($type,['IN','OUT'],true))throw new RuntimeException('Jenis mutasi harus masuk atau keluar.');
        $q=$pdo->prepare('SELECT id_item FROM simrs_wf_stock_items WHERE id_item=? AND aktif=1');$q->execute([$item]);if(!$q->fetchColumn())throw new RuntimeException('Barang tidak ditemukan/aktif.');
        $pdo->prepare("INSERT INTO simrs_wf_stock_moves (id_item,jenis_mutasi,jumlah,no_dokumen,catatan,diminta_oleh_user) VALUES (?,?,?,?,?,?)")->execute([$item,$type,$qty,wfa_text('no_dokumen',100),wfa_text('catatan',8000),$uid]);
        wfa_audit($pdo,$uid,'REQUEST_STOCK_MOVE','simrs_wf_stock_moves','Permintaan mutasi '.$type.' item '.$item.' qty '.$qty);wfa_redirect('inventory','Permintaan mutasi stok dikirim ke Kepala Gudang untuk persetujuan.');
    }

    if ($action === 'decide_stock_move') {
        wfa_need($roles,['KEPALA_GUDANG']);$moveId=(int)($_POST['id_move']??0);$decision=(string)($_POST['decision']??'');if(!in_array($decision,['approve','reject'],true))throw new RuntimeException('Keputusan tidak valid.');
        $pdo->beginTransaction();$q=$pdo->prepare("SELECT m.*,i.nama_item FROM simrs_wf_stock_moves m JOIN simrs_wf_stock_items i ON i.id_item=m.id_item WHERE m.id_move=? AND m.status_mutasi='menunggu_persetujuan' FOR UPDATE");$q->execute([$moveId]);$move=$q->fetch(PDO::FETCH_ASSOC);if(!$move)throw new RuntimeException('Permintaan stok tidak ada atau sudah diputuskan.');
        $status=$decision==='approve'?'approved':'rejected';
        if($decision==='approve'&&$move['jenis_mutasi']==='OUT'){$q=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN jenis_mutasi='IN' THEN jumlah ELSE -jumlah END),0) FROM simrs_wf_stock_moves WHERE id_item=? AND status_mutasi='approved'");$q->execute([$move['id_item']]);$balance=(float)$q->fetchColumn();if($balance<(float)$move['jumlah'])throw new RuntimeException('Stok tidak mencukupi untuk menyetujui pengeluaran.');}
        $pdo->prepare('UPDATE simrs_wf_stock_moves SET status_mutasi=?,diputuskan_oleh_user=?,diputuskan_pada=NOW() WHERE id_move=?')->execute([$status,$uid,$moveId]);$pdo->commit();
        wfa_audit($pdo,$uid,'DECIDE_STOCK_MOVE','simrs_wf_stock_moves','Keputusan '.$status.' untuk '.$move['nama_item']);wfa_redirect('inventory',$status==='approved'?'Mutasi stok disetujui dan saldo diperbarui.':'Permintaan mutasi ditolak.');
    }

    throw new RuntimeException('Aksi tidak dikenal.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('SIMRS workflow v2: '.$e->getMessage());
    $message = $e instanceof RuntimeException ? $e->getMessage() : 'Terjadi kesalahan penyimpanan. Periksa log PHP dan struktur database, lalu ulangi.';
    wfa_redirect($page,$message,'error');
}
