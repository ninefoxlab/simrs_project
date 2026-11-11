-- Skema SIMRS RSUD NTB (disusun dari query yang dipakai aplikasi).
-- Jalankan: mysql -uroot < database/schema.sql
CREATE DATABASE IF NOT EXISTS simrs_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE simrs_db;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- Akses: pegawai, users, roles, permissions
-- =========================================================
CREATE TABLE IF NOT EXISTS pegawai (
  id_pegawai INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(255) NOT NULL,
  peran VARCHAR(100) NOT NULL,
  spesialisasi VARCHAR(100) NULL,
  username VARCHAR(50) NULL,
  nip VARCHAR(50) NULL,
  no_telp VARCHAR(30) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id_user INT AUTO_INCREMENT PRIMARY KEY,
  id_pegawai INT NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  nama_tampilan VARCHAR(255) NOT NULL,
  status_aktif TINYINT(1) NOT NULL DEFAULT 1,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  CONSTRAINT fk_users_pegawai FOREIGN KEY (id_pegawai) REFERENCES pegawai(id_pegawai)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roles (
  id_role INT AUTO_INCREMENT PRIMARY KEY,
  kode_role VARCHAR(50) NOT NULL UNIQUE,
  nama_role VARCHAR(100) NOT NULL,
  status_aktif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS permissions (
  id_permission INT AUTO_INCREMENT PRIMARY KEY,
  kode_permission VARCHAR(100) NOT NULL UNIQUE,
  nama_permission VARCHAR(150) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS role_permissions (
  id_role INT NOT NULL,
  id_permission INT NOT NULL,
  PRIMARY KEY (id_role, id_permission),
  CONSTRAINT fk_rp_role FOREIGN KEY (id_role) REFERENCES roles(id_role),
  CONSTRAINT fk_rp_perm FOREIGN KEY (id_permission) REFERENCES permissions(id_permission)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_roles (
  id_user INT NOT NULL,
  id_role INT NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_user, id_role),
  CONSTRAINT fk_ur_user FOREIGN KEY (id_user) REFERENCES users(id_user),
  CONSTRAINT fk_ur_role FOREIGN KEY (id_role) REFERENCES roles(id_role)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS simrs_sessions (
  id VARCHAR(128) NOT NULL PRIMARY KEY,
  data MEDIUMBLOB NOT NULL,
  last_activity INT UNSIGNED NOT NULL,
  KEY idx_sessions_activity (last_activity)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_trail (
  id_audit BIGINT AUTO_INCREMENT PRIMARY KEY,
  id_pegawai INT NULL,
  aksi VARCHAR(100) NOT NULL,
  tabel VARCHAR(100) NULL,
  detail TEXT NULL,
  waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- Master data
-- =========================================================
CREATE TABLE IF NOT EXISTS unit (
  id_unit INT AUTO_INCREMENT PRIMARY KEY,
  kode_unit VARCHAR(30) NOT NULL UNIQUE,
  nama_unit VARCHAR(150) NOT NULL,
  jenis_unit VARCHAR(50) NOT NULL,
  lokasi VARCHAR(150) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Aktif'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS penjamin (
  id_penjamin INT AUTO_INCREMENT PRIMARY KEY,
  kode_penjamin VARCHAR(30) NOT NULL UNIQUE,
  nama_penjamin VARCHAR(150) NOT NULL,
  jenis_penjamin VARCHAR(50) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Aktif'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kamar (
  id_kamar INT AUTO_INCREMENT PRIMARY KEY,
  id_unit INT NULL,
  kode_kamar VARCHAR(30) NOT NULL UNIQUE,
  nama_kamar VARCHAR(100) NOT NULL,
  kelas VARCHAR(20) NOT NULL,
  kapasitas INT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Tersedia',
  keterangan VARCHAR(255) NULL,
  CONSTRAINT fk_kamar_unit FOREIGN KEY (id_unit) REFERENCES unit(id_unit)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS supplier (
  id_supplier INT AUTO_INCREMENT PRIMARY KEY,
  kode_supplier VARCHAR(30) NOT NULL UNIQUE,
  nama_supplier VARCHAR(200) NOT NULL,
  alamat VARCHAR(255) NULL,
  no_telp VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Aktif'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS obat (
  id_obat INT AUTO_INCREMENT PRIMARY KEY,
  id_supplier INT NULL,
  kode_obat VARCHAR(30) NOT NULL UNIQUE,
  nama_obat VARCHAR(200) NOT NULL,
  kategori VARCHAR(100) NOT NULL,
  satuan VARCHAR(30) NOT NULL,
  harga DECIMAL(15,2) NOT NULL,
  stok_saat_ini DECIMAL(15,2) NOT NULL DEFAULT 0,
  stok_minimum DECIMAL(15,2) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'Aktif',
  CONSTRAINT fk_obat_supplier FOREIGN KEY (id_supplier) REFERENCES supplier(id_supplier)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS parameter_lab (
  id_parameter INT AUTO_INCREMENT PRIMARY KEY,
  kode_parameter VARCHAR(30) NOT NULL UNIQUE,
  nama_pemeriksaan VARCHAR(150) NOT NULL,
  kategori VARCHAR(100) NOT NULL,
  satuan VARCHAR(30) NULL,
  nilai_rujukan VARCHAR(255) NULL,
  jenis_spesimen VARCHAR(80) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Aktif'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gudang (
  id_gudang INT AUTO_INCREMENT PRIMARY KEY,
  kode_gudang VARCHAR(30) NOT NULL UNIQUE,
  nama_gudang VARCHAR(150) NOT NULL,
  jenis_gudang VARCHAR(50) NOT NULL,
  lokasi VARCHAR(150) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Aktif'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS barang_persediaan (
  id_barang INT AUTO_INCREMENT PRIMARY KEY,
  id_gudang INT NULL,
  kode_barang VARCHAR(30) NOT NULL UNIQUE,
  nama_barang VARCHAR(200) NOT NULL,
  kategori VARCHAR(100) NOT NULL,
  satuan VARCHAR(30) NOT NULL,
  stok_saat_ini DECIMAL(15,2) NOT NULL DEFAULT 0,
  stok_minimum DECIMAL(15,2) NOT NULL DEFAULT 0,
  harga_satuan DECIMAL(15,2) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Aktif',
  CONSTRAINT fk_barang_gudang FOREIGN KEY (id_gudang) REFERENCES gudang(id_gudang)
) ENGINE=InnoDB;

-- =========================================================
-- Registrasi & pelayanan
-- =========================================================
CREATE TABLE IF NOT EXISTS pasien (
  id_pasien INT AUTO_INCREMENT PRIMARY KEY,
  no_rm VARCHAR(30) NOT NULL UNIQUE,
  nik VARCHAR(20) NOT NULL,
  nama VARCHAR(255) NOT NULL,
  tanggal_lahir DATE NOT NULL,
  jenis_kelamin ENUM('L','P') NOT NULL,
  alamat VARCHAR(255) NOT NULL,
  no_telp VARCHAR(30) NULL,
  id_penjamin INT NULL,
  no_penjamin VARCHAR(50) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pasien_penjamin FOREIGN KEY (id_penjamin) REFERENCES penjamin(id_penjamin)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS jadwal_praktik (
  id_jadwal INT AUTO_INCREMENT PRIMARY KEY,
  id_unit INT NOT NULL,
  id_dokter INT NOT NULL,
  hari VARCHAR(30) NOT NULL DEFAULT 'Senin-Jumat',
  jam_awal TIME NOT NULL,
  jam_akhir TIME NOT NULL,
  kuota INT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Aktif',
  CONSTRAINT fk_jadwal_unit FOREIGN KEY (id_unit) REFERENCES unit(id_unit),
  CONSTRAINT fk_jadwal_dokter FOREIGN KEY (id_dokter) REFERENCES pegawai(id_pegawai)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kunjungan (
  id_kunjungan INT AUTO_INCREMENT PRIMARY KEY,
  id_pasien INT NOT NULL,
  id_unit INT NOT NULL,
  id_dokter INT NULL,
  id_jadwal INT NULL,
  no_kunjungan VARCHAR(40) NOT NULL UNIQUE,
  tipe_pelayanan ENUM('Rawat Jalan','IGD','Rawat Inap') NOT NULL,
  tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status VARCHAR(30) NOT NULL DEFAULT 'Terdaftar',
  CONSTRAINT fk_kj_pasien FOREIGN KEY (id_pasien) REFERENCES pasien(id_pasien),
  CONSTRAINT fk_kj_unit FOREIGN KEY (id_unit) REFERENCES unit(id_unit),
  CONSTRAINT fk_kj_dokter FOREIGN KEY (id_dokter) REFERENCES pegawai(id_pegawai),
  CONSTRAINT fk_kj_jadwal FOREIGN KEY (id_jadwal) REFERENCES jadwal_praktik(id_jadwal)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS antrean (
  id_antrean INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL UNIQUE,
  no_antrean VARCHAR(10) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Menunggu',
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_antrean_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
) ENGINE=InnoDB;

-- Tabel klinis generik (dipakai editor modul generik)
CREATE TABLE IF NOT EXISTS tanda_vital (
  id_tanda_vital INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL,
  tekanan_darah VARCHAR(20) NULL, nadi INT NULL, suhu DECIMAL(4,1) NULL, respirasi INT NULL, spo2 INT NULL,
  waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tv_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rekam_medis (
  id_rekam_medis INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL,
  anamnesis TEXT NOT NULL, pemeriksaan_fisik TEXT NULL, rencana TEXT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rm_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS diagnosis (
  id_diagnosis INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL,
  kode_icd10 VARCHAR(10) NOT NULL, nama_diagnosis VARCHAR(255) NOT NULL,
  jenis ENUM('Utama','Sekunder') NOT NULL DEFAULT 'Utama',
  CONSTRAINT fk_dx_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tindakan_medis (
  id_tindakan INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL,
  nama_tindakan VARCHAR(255) NOT NULL, keterangan TEXT NULL,
  waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tm_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lampiran (
  id_lampiran INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL,
  nama_file VARCHAR(255) NOT NULL, path_file VARCHAR(255) NOT NULL,
  diunggah_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lmp_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rawat_inap (
  id_rawat_inap INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL, id_kamar INT NOT NULL,
  tanggal_masuk DATETIME NOT NULL, tanggal_keluar DATETIME NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Dirawat',
  CONSTRAINT fk_ri_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan),
  CONSTRAINT fk_ri_kamar FOREIGN KEY (id_kamar) REFERENCES kamar(id_kamar)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS monitoring (
  id_monitoring INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL,
  catatan TEXT NOT NULL, waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mon_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS triase_igd (
  id_triase INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL,
  kategori ENUM('merah','kuning','hijau','hitam') NOT NULL, keluhan TEXT NULL,
  waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tri_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS asesmen_igd (
  id_asesmen INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL,
  asesmen TEXT NOT NULL, waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_asm_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS catatan_keperawatan (
  id_catatan INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL,
  catatan TEXT NOT NULL, waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ck_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_layanan (
  id_order INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL,
  jenis_order VARCHAR(30) NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'ordered',
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ol_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detail_order (
  id_detail_order INT AUTO_INCREMENT PRIMARY KEY,
  id_order INT NOT NULL,
  nama_layanan VARCHAR(255) NOT NULL, jumlah DECIMAL(10,2) NOT NULL DEFAULT 1,
  CONSTRAINT fk_do_order FOREIGN KEY (id_order) REFERENCES order_layanan(id_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS verifikasi_order (
  id_verifikasi INT AUTO_INCREMENT PRIMARY KEY,
  id_order INT NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'diverifikasi', catatan TEXT NULL,
  waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_vo_order FOREIGN KEY (id_order) REFERENCES order_layanan(id_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hasil_lab (
  id_hasil_lab INT AUTO_INCREMENT PRIMARY KEY,
  id_order INT NOT NULL, id_parameter INT NOT NULL,
  nilai VARCHAR(100) NOT NULL, waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hl_order FOREIGN KEY (id_order) REFERENCES order_layanan(id_order),
  CONSTRAINT fk_hl_param FOREIGN KEY (id_parameter) REFERENCES parameter_lab(id_parameter)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pemeriksaan_radiologi (
  id_pemeriksaan INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(30) NOT NULL UNIQUE, nama VARCHAR(150) NOT NULL, modalitas VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hasil_radiologi (
  id_hasil_radiologi INT AUTO_INCREMENT PRIMARY KEY,
  id_order INT NOT NULL, id_pemeriksaan INT NOT NULL,
  temuan TEXT NOT NULL, kesan TEXT NULL, waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hr_order FOREIGN KEY (id_order) REFERENCES order_layanan(id_order),
  CONSTRAINT fk_hr_periksa FOREIGN KEY (id_pemeriksaan) REFERENCES pemeriksaan_radiologi(id_pemeriksaan)
) ENGINE=InnoDB;

-- Farmasi
CREATE TABLE IF NOT EXISTS batch_obat (
  id_batch INT AUTO_INCREMENT PRIMARY KEY,
  id_obat INT NOT NULL, no_batch VARCHAR(50) NOT NULL, tanggal_kedaluwarsa DATE NOT NULL, jumlah DECIMAL(15,2) NOT NULL,
  CONSTRAINT fk_bo_obat FOREIGN KEY (id_obat) REFERENCES obat(id_obat)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS resep (
  id_resep INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL, no_resep VARCHAR(40) NOT NULL UNIQUE,
  status VARCHAR(30) NOT NULL DEFAULT 'Baru', tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_resep_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS resep_item (
  id_resep_item INT AUTO_INCREMENT PRIMARY KEY,
  id_resep INT NOT NULL, id_obat INT NOT NULL, jumlah DECIMAL(10,2) NOT NULL, aturan_pakai VARCHAR(150) NOT NULL,
  CONSTRAINT fk_ri_resep FOREIGN KEY (id_resep) REFERENCES resep(id_resep),
  CONSTRAINT fk_ri_obat FOREIGN KEY (id_obat) REFERENCES obat(id_obat)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mutasi_stok (
  id_mutasi INT AUTO_INCREMENT PRIMARY KEY,
  id_obat INT NOT NULL, jenis ENUM('masuk','keluar') NOT NULL, jumlah DECIMAL(15,2) NOT NULL,
  keterangan VARCHAR(255) NULL, waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ms_obat FOREIGN KEY (id_obat) REFERENCES obat(id_obat)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS retur_obat (
  id_retur INT AUTO_INCREMENT PRIMARY KEY,
  id_obat INT NOT NULL, jumlah DECIMAL(15,2) NOT NULL, alasan VARCHAR(255) NOT NULL,
  waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ro_obat FOREIGN KEY (id_obat) REFERENCES obat(id_obat)
) ENGINE=InnoDB;

-- Billing
CREATE TABLE IF NOT EXISTS tagihan (
  id_tagihan INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL, no_tagihan VARCHAR(40) NOT NULL UNIQUE,
  total DECIMAL(15,2) NOT NULL DEFAULT 0, status VARCHAR(30) NOT NULL DEFAULT 'Belum Lunas',
  tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tag_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detail_tagihan (
  id_detail_tagihan INT AUTO_INCREMENT PRIMARY KEY,
  id_tagihan INT NOT NULL, uraian VARCHAR(255) NOT NULL, jumlah DECIMAL(10,2) NOT NULL, tarif DECIMAL(15,2) NOT NULL,
  CONSTRAINT fk_dt_tag FOREIGN KEY (id_tagihan) REFERENCES tagihan(id_tagihan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pembayaran (
  id_pembayaran INT AUTO_INCREMENT PRIMARY KEY,
  id_tagihan INT NOT NULL, jumlah DECIMAL(15,2) NOT NULL, metode VARCHAR(30) NOT NULL,
  waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pb_tag FOREIGN KEY (id_tagihan) REFERENCES tagihan(id_tagihan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS klaim (
  id_klaim INT AUTO_INCREMENT PRIMARY KEY,
  id_tagihan INT NOT NULL, id_penjamin INT NOT NULL, no_klaim VARCHAR(60) NOT NULL,
  jumlah DECIMAL(15,2) NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'Diajukan',
  CONSTRAINT fk_kl_tag FOREIGN KEY (id_tagihan) REFERENCES tagihan(id_tagihan),
  CONSTRAINT fk_kl_pjm FOREIGN KEY (id_penjamin) REFERENCES penjamin(id_penjamin)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kuitansi (
  id_kuitansi INT AUTO_INCREMENT PRIMARY KEY,
  id_pembayaran INT NOT NULL, no_kuitansi VARCHAR(40) NOT NULL UNIQUE,
  waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_kw_pb FOREIGN KEY (id_pembayaran) REFERENCES pembayaran(id_pembayaran)
) ENGINE=InnoDB;

-- Persediaan
CREATE TABLE IF NOT EXISTS batch_persediaan (
  id_batch INT AUTO_INCREMENT PRIMARY KEY,
  id_barang INT NOT NULL, no_batch VARCHAR(50) NOT NULL, tanggal_kedaluwarsa DATE NULL, jumlah DECIMAL(15,2) NOT NULL,
  CONSTRAINT fk_bp_barang FOREIGN KEY (id_barang) REFERENCES barang_persediaan(id_barang)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS penerimaan_persediaan (
  id_penerimaan INT AUTO_INCREMENT PRIMARY KEY,
  id_supplier INT NOT NULL, id_gudang INT NOT NULL, no_dokumen VARCHAR(50) NOT NULL,
  tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pp_sup FOREIGN KEY (id_supplier) REFERENCES supplier(id_supplier),
  CONSTRAINT fk_pp_gdg FOREIGN KEY (id_gudang) REFERENCES gudang(id_gudang)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detail_penerimaan_persediaan (
  id_detail INT AUTO_INCREMENT PRIMARY KEY,
  id_penerimaan INT NOT NULL, id_barang INT NOT NULL, jumlah DECIMAL(15,2) NOT NULL, harga DECIMAL(15,2) NOT NULL,
  CONSTRAINT fk_dpp_pen FOREIGN KEY (id_penerimaan) REFERENCES penerimaan_persediaan(id_penerimaan),
  CONSTRAINT fk_dpp_brg FOREIGN KEY (id_barang) REFERENCES barang_persediaan(id_barang)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS permintaan_persediaan (
  id_permintaan INT AUTO_INCREMENT PRIMARY KEY,
  id_unit INT NOT NULL, no_dokumen VARCHAR(50) NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'Diajukan',
  tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prm_unit FOREIGN KEY (id_unit) REFERENCES unit(id_unit)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detail_permintaan_persediaan (
  id_detail INT AUTO_INCREMENT PRIMARY KEY,
  id_permintaan INT NOT NULL, id_barang INT NOT NULL, jumlah DECIMAL(15,2) NOT NULL,
  CONSTRAINT fk_dprm_prm FOREIGN KEY (id_permintaan) REFERENCES permintaan_persediaan(id_permintaan),
  CONSTRAINT fk_dprm_brg FOREIGN KEY (id_barang) REFERENCES barang_persediaan(id_barang)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mutasi_persediaan (
  id_mutasi INT AUTO_INCREMENT PRIMARY KEY,
  id_barang INT NOT NULL, jenis ENUM('masuk','keluar') NOT NULL, jumlah DECIMAL(15,2) NOT NULL,
  keterangan VARCHAR(255) NULL, waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mp_brg FOREIGN KEY (id_barang) REFERENCES barang_persediaan(id_barang)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stok_opname (
  id_stok_opname INT AUTO_INCREMENT PRIMARY KEY,
  id_gudang INT NOT NULL, tanggal DATE NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'Draft',
  CONSTRAINT fk_so_gdg FOREIGN KEY (id_gudang) REFERENCES gudang(id_gudang)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detail_stok_opname (
  id_detail INT AUTO_INCREMENT PRIMARY KEY,
  id_stok_opname INT NOT NULL, id_barang INT NOT NULL, stok_sistem DECIMAL(15,2) NOT NULL, stok_fisik DECIMAL(15,2) NOT NULL,
  CONSTRAINT fk_dso_so FOREIGN KEY (id_stok_opname) REFERENCES stok_opname(id_stok_opname),
  CONSTRAINT fk_dso_brg FOREIGN KEY (id_barang) REFERENCES barang_persediaan(id_barang)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS aset_tetap (
  id_aset INT AUTO_INCREMENT PRIMARY KEY,
  kode_aset VARCHAR(40) NOT NULL UNIQUE, nama VARCHAR(200) NOT NULL, id_unit INT NULL,
  tanggal_perolehan DATE NOT NULL, nilai_perolehan DECIMAL(15,2) NOT NULL, kondisi VARCHAR(30) NOT NULL DEFAULT 'Baik',
  CONSTRAINT fk_aset_unit FOREIGN KEY (id_unit) REFERENCES unit(id_unit)
) ENGINE=InnoDB;

-- =========================================================
-- Workflow V2 (dipakai app/pages/workflow.php & public/workflow_action.php)
-- =========================================================
CREATE TABLE IF NOT EXISTS simrs_wf_emr (
  id_emr INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL UNIQUE,
  id_dokter INT NULL, id_user INT NULL,
  keluhan_utama TEXT NULL, onset TEXT NULL, lokasi_keluhan TEXT NULL, karakter_keluhan TEXT NULL,
  durasi_keluhan TEXT NULL, skala_keluhan TEXT NULL, penjalaran TEXT NULL, faktor_pemberat TEXT NULL,
  faktor_peringan TEXT NULL, riwayat_penyakit_sekarang TEXT NULL, riwayat_penyakit_dahulu TEXT NULL,
  riwayat_penyakit_keluarga TEXT NULL, riwayat_sosial TEXT NULL, riwayat_alergi TEXT NULL,
  obat_digunakan TEXT NULL, review_sistem TEXT NULL, pemeriksaan_umum TEXT NULL,
  pemeriksaan_kepala_leher TEXT NULL, pemeriksaan_thoraks TEXT NULL, pemeriksaan_jantung TEXT NULL,
  pemeriksaan_abdomen TEXT NULL, pemeriksaan_ekstremitas TEXT NULL, pemeriksaan_neurologis TEXT NULL,
  pemeriksaan_kulit TEXT NULL, diagnosis_utama TEXT NULL, kode_icd10 VARCHAR(255) NULL,
  diagnosis_sekunder TEXT NULL, diagnosis_banding TEXT NULL, assessment TEXT NULL,
  rencana_terapi TEXT NULL, edukasi_pasien TEXT NULL, rencana_kontrol TEXT NULL,
  status_emr ENUM('draft','signed') NOT NULL DEFAULT 'draft',
  signed_by_user INT NULL, signed_at DATETIME NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_wfemr_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS simrs_wf_emr_addenda (
  id_addendum INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL, id_emr INT NOT NULL,
  alasan_koreksi TEXT NOT NULL, isi_addendum TEXT NOT NULL, dibuat_oleh_user INT NOT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wfadd_emr FOREIGN KEY (id_emr) REFERENCES simrs_wf_emr(id_emr)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS simrs_wf_vitals (
  id_vital INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL, dicatat_oleh_user INT NOT NULL,
  tekanan_sistolik DECIMAL(6,1) NULL, tekanan_diastolik DECIMAL(6,1) NULL,
  nadi_per_menit DECIMAL(6,1) NULL, napas_per_menit DECIMAL(6,1) NULL,
  suhu_celsius DECIMAL(4,1) NULL, spo2 DECIMAL(5,1) NULL,
  berat_kg DECIMAL(6,2) NULL, tinggi_cm DECIMAL(6,2) NULL, skala_nyeri DECIMAL(4,1) NULL,
  gcs_e TINYINT NULL, gcs_v TINYINT NULL, gcs_m TINYINT NULL,
  kesadaran VARCHAR(500) NULL, catatan TEXT NULL,
  dicatat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wfvit_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS simrs_wf_nursing (
  id_nursing INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL, id_perawat_user INT NOT NULL,
  subjektif TEXT NULL, objektif TEXT NULL, asesmen TEXT NULL, diagnosis_keperawatan TEXT NULL,
  intervensi TEXT NULL, evaluasi TEXT NULL, risiko_jatuh TEXT NULL, status_nutrisi TEXT NULL, mobilitas TEXT NULL,
  intake_ml DECIMAL(10,2) NULL, output_ml DECIMAL(10,2) NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wfnur_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS simrs_wf_igd_triage (
  id_triage INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL, id_petugas_user INT NOT NULL,
  kategori_triase ENUM('merah','kuning','hijau','hitam') NOT NULL DEFAULT 'kuning',
  nyeri DECIMAL(4,1) NULL, gcs VARCHAR(80) NULL,
  keluhan_utama TEXT NULL, jalan_napas TEXT NULL, pernapasan TEXT NULL, sirkulasi TEXT NULL,
  kesadaran TEXT NULL, alergi TEXT NULL, tindakan_awal TEXT NULL,
  status_triase VARCHAR(40) NOT NULL DEFAULT 'menunggu_dokter',
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, diperbarui_pada DATETIME NULL,
  CONSTRAINT fk_wftri_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS simrs_wf_orders (
  id_order INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL, no_order VARCHAR(60) NOT NULL UNIQUE,
  jenis_order ENUM('LAB','RADIOLOGI','FARMASI','TINDAKAN','KONSULTASI') NOT NULL,
  kode_layanan VARCHAR(200) NULL, nama_layanan VARCHAR(1020) NOT NULL, instruksi TEXT NULL,
  prioritas ENUM('Normal','Segera','Cito') NOT NULL DEFAULT 'Normal',
  dosis VARCHAR(480) NULL, rute VARCHAR(320) NULL, frekuensi VARCHAR(480) NULL, durasi VARCHAR(480) NULL,
  jumlah DECIMAL(15,2) NULL, satuan_jumlah VARCHAR(200) NULL, jumlah_diserahkan DECIMAL(15,2) NULL DEFAULT NULL,
  id_pembuat_user INT NOT NULL, id_unit_tujuan INT NULL,
  status_order VARCHAR(40) NOT NULL DEFAULT 'ordered', catatan_pelaksana TEXT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, diperbarui_pada DATETIME NULL,
  CONSTRAINT fk_wford_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS simrs_wf_results (
  id_result INT AUTO_INCREMENT PRIMARY KEY,
  id_order INT NOT NULL UNIQUE,
  hasil_teks TEXT NULL, hasil_angka VARCHAR(400) NULL, satuan VARCHAR(320) NULL,
  nilai_rujukan VARCHAR(640) NULL, penanda_abnormal VARCHAR(160) NULL,
  teknik_pemeriksaan TEXT NULL, temuan TEXT NULL, kesan TEXT NULL,
  catatan_reviewer TEXT NULL, catatan_validator TEXT NULL,
  status_hasil VARCHAR(40) NOT NULL DEFAULT 'result_entered',
  diinput_oleh_user INT NULL, waktu_input DATETIME NULL,
  direview_oleh_user INT NULL, waktu_review DATETIME NULL,
  divalidasi_oleh_user INT NULL, waktu_validasi DATETIME NULL,
  CONSTRAINT fk_wfres_order FOREIGN KEY (id_order) REFERENCES simrs_wf_orders(id_order)
) ENGINE=InnoDB;

-- Modul Laboratorium sesuai SOP/SIMRS/001/2026
-- Rincian pemeriksaan per order (satu order bisa berisi beberapa pemeriksaan).
CREATE TABLE IF NOT EXISTS simrs_wf_lab_items (
  id_lab_item INT AUTO_INCREMENT PRIMARY KEY,
  id_order INT NOT NULL,
  id_parameter INT NULL,
  kode_parameter VARCHAR(30) NULL,
  nama_pemeriksaan VARCHAR(150) NOT NULL,
  jenis_spesimen VARCHAR(80) NULL,
  nilai_hasil VARCHAR(255) NULL,
  satuan VARCHAR(30) NULL,
  nilai_rujukan VARCHAR(255) NULL,
  penanda VARCHAR(20) NULL,
  waktu_pemeriksaan DATETIME NULL,
  diinput_oleh_user INT NULL,
  diperbarui_pada DATETIME NULL,
  KEY idx_labitem_order (id_order),
  CONSTRAINT fk_labitem_order FOREIGN KEY (id_order) REFERENCES simrs_wf_orders(id_order)
) ENGINE=InnoDB;

-- Riwayat verifikasi/penerimaan spesimen (langkah 4 SOP). Penolakan tetap tersimpan sebagai riwayat.
CREATE TABLE IF NOT EXISTS simrs_wf_lab_specimens (
  id_specimen INT AUTO_INCREMENT PRIMARY KEY,
  id_order INT NOT NULL,
  jenis_spesimen VARCHAR(80) NOT NULL,
  no_label VARCHAR(80) NULL,
  cek_identitas TINYINT(1) NOT NULL DEFAULT 0,
  cek_label TINYINT(1) NOT NULL DEFAULT 0,
  cek_jenis TINYINT(1) NOT NULL DEFAULT 0,
  cek_kesesuaian TINYINT(1) NOT NULL DEFAULT 0,
  status_spesimen ENUM('accepted','rejected') NOT NULL,
  alasan_penolakan TEXT NULL,
  tindak_lanjut TEXT NULL,
  diverifikasi_oleh_user INT NOT NULL,
  waktu_verifikasi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_specimen_order (id_order),
  CONSTRAINT fk_specimen_order FOREIGN KEY (id_order) REFERENCES simrs_wf_orders(id_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS simrs_wf_dispenses (
  id_dispense INT AUTO_INCREMENT PRIMARY KEY,
  id_order INT NOT NULL, jumlah_diserahkan DECIMAL(15,2) NOT NULL, catatan TEXT NULL,
  diserahkan_oleh_user INT NOT NULL, diserahkan_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wfdisp_order FOREIGN KEY (id_order) REFERENCES simrs_wf_orders(id_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS simrs_wf_med_admin (
  id_med_admin INT AUTO_INCREMENT PRIMARY KEY,
  id_order INT NOT NULL, id_kunjungan INT NOT NULL,
  dosis_diberikan VARCHAR(480) NULL, rute VARCHAR(320) NULL, waktu_pemberian DATETIME NOT NULL,
  status_pemberian ENUM('diberikan','ditunda','ditolak','tidak_diberikan') NOT NULL,
  alasan_tidak_diberikan TEXT NULL, catatan TEXT NULL, dicatat_oleh_user INT NOT NULL,
  dicatat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wfmar_order FOREIGN KEY (id_order) REFERENCES simrs_wf_orders(id_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS simrs_wf_bills (
  id_bill INT AUTO_INCREMENT PRIMARY KEY,
  id_kunjungan INT NOT NULL UNIQUE, no_tagihan VARCHAR(60) NOT NULL UNIQUE,
  subtotal DECIMAL(15,2) NOT NULL DEFAULT 0, total DECIMAL(15,2) NOT NULL DEFAULT 0,
  status_tagihan VARCHAR(40) NOT NULL DEFAULT 'needs_tariff', dibuat_oleh_user INT NOT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, diperbarui_pada DATETIME NULL,
  CONSTRAINT fk_wfbill_kj FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS simrs_wf_bill_items (
  id_bill_item INT AUTO_INCREMENT PRIMARY KEY,
  id_bill INT NOT NULL, id_order INT NULL, kategori VARCHAR(40) NOT NULL, uraian VARCHAR(1200) NOT NULL,
  jumlah DECIMAL(15,2) NOT NULL DEFAULT 1, tarif_satuan DECIMAL(15,2) NULL, total_item DECIMAL(15,2) NULL,
  CONSTRAINT fk_wfbi_bill FOREIGN KEY (id_bill) REFERENCES simrs_wf_bills(id_bill)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS simrs_wf_payments (
  id_payment INT AUTO_INCREMENT PRIMARY KEY,
  id_bill INT NOT NULL, jumlah_bayar DECIMAL(15,2) NOT NULL, metode_pembayaran VARCHAR(30) NOT NULL,
  referensi VARCHAR(480) NULL, diterima_oleh_user INT NOT NULL,
  dibayar_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wfpay_bill FOREIGN KEY (id_bill) REFERENCES simrs_wf_bills(id_bill)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS simrs_wf_claims (
  id_claim INT AUTO_INCREMENT PRIMARY KEY,
  id_bill INT NOT NULL UNIQUE, nama_penjamin VARCHAR(640) NOT NULL, no_peserta VARCHAR(480) NOT NULL,
  no_klaim VARCHAR(480) NOT NULL, jumlah_diajukan DECIMAL(15,2) NOT NULL, jumlah_disetujui DECIMAL(15,2) NULL,
  status_klaim VARCHAR(40) NOT NULL DEFAULT 'submitted', catatan_verifikasi TEXT NULL,
  diajukan_pada DATETIME NULL, diverifikasi_oleh_user INT NULL, diverifikasi_pada DATETIME NULL,
  CONSTRAINT fk_wfclaim_bill FOREIGN KEY (id_bill) REFERENCES simrs_wf_bills(id_bill)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS simrs_wf_stock_items (
  id_item INT AUTO_INCREMENT PRIMARY KEY,
  kode_item VARCHAR(240) NOT NULL UNIQUE, nama_item VARCHAR(800) NOT NULL, kategori VARCHAR(320) NOT NULL,
  satuan VARCHAR(160) NOT NULL, stok_minimum DECIMAL(15,2) NOT NULL DEFAULT 0,
  aktif TINYINT(1) NOT NULL DEFAULT 1, dibuat_oleh_user INT NOT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS simrs_wf_stock_moves (
  id_move INT AUTO_INCREMENT PRIMARY KEY,
  id_item INT NOT NULL, jenis_mutasi ENUM('IN','OUT') NOT NULL, jumlah DECIMAL(15,2) NOT NULL,
  no_dokumen VARCHAR(400) NULL, catatan TEXT NULL, diminta_oleh_user INT NOT NULL,
  status_mutasi VARCHAR(40) NOT NULL DEFAULT 'menunggu_persetujuan',
  diputuskan_oleh_user INT NULL, diputuskan_pada DATETIME NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wfmove_item FOREIGN KEY (id_item) REFERENCES simrs_wf_stock_items(id_item)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- Data awal: roles & permissions
-- =========================================================
INSERT IGNORE INTO roles (kode_role, nama_role) VALUES
 ('IT_ADMIN','IT Administrator'),('DOKTER','Dokter'),('ADMISI','Petugas Admisi'),('PERAWAT','Perawat'),
 ('APOTEKER','Apoteker'),('ATLM','Petugas Laboratorium (ATLM)'),('DOKTER_LAB','Dokter Laboratorium'),
 ('VALIDATOR_LAB','Validator Laboratorium'),('RADIOGRAFER','Radiografer'),('DOKTER_RAD','Dokter Radiologi'),
 ('KASIR','Kasir'),('ASURANSI','Petugas Asuransi'),('PETUGAS_GUDANG','Petugas Gudang'),
 ('KEPALA_GUDANG','Kepala Gudang'),('PIMPINAN','Pimpinan');

-- Permission per modul: <modul>.access / .create / .update / .view
INSERT IGNORE INTO permissions (kode_permission, nama_permission)
SELECT CONCAT(m.modul,'.',a.aksi), CONCAT(a.aksi,' ',m.modul)
FROM (SELECT 'registration' modul UNION ALL SELECT 'scheduling' UNION ALL SELECT 'outpatient' UNION ALL SELECT 'inpatient'
      UNION ALL SELECT 'igd' UNION ALL SELECT 'emr' UNION ALL SELECT 'nursing' UNION ALL SELECT 'cpoe'
      UNION ALL SELECT 'laboratory' UNION ALL SELECT 'radiology' UNION ALL SELECT 'pharmacy' UNION ALL SELECT 'billing'
      UNION ALL SELECT 'inventory' UNION ALL SELECT 'reporting' UNION ALL SELECT 'master_data') m
CROSS JOIN (SELECT 'access' aksi UNION ALL SELECT 'create' UNION ALL SELECT 'update' UNION ALL SELECT 'view') a;

INSERT IGNORE INTO permissions (kode_permission, nama_permission) VALUES
 ('user_management.access','Akses manajemen pengguna'),('user_management.create','Membuat pengguna'),
 ('user_management.update','Mengubah pengguna'),('user_management.activate','Aktif/nonaktif pengguna'),
 ('user_management.assign_role','Menetapkan role');

-- IT_ADMIN: semua permission
INSERT IGNORE INTO role_permissions (id_role, id_permission)
SELECT r.id_role, p.id_permission FROM roles r CROSS JOIN permissions p WHERE r.kode_role='IT_ADMIN';

-- Role lain: modul sesuai menu (app/menu.php)
INSERT IGNORE INTO role_permissions (id_role, id_permission)
SELECT r.id_role, p.id_permission
FROM (
  SELECT 'DOKTER' kode, 'outpatient' modul UNION ALL SELECT 'DOKTER','inpatient' UNION ALL SELECT 'DOKTER','igd'
  UNION ALL SELECT 'DOKTER','emr' UNION ALL SELECT 'DOKTER','cpoe'
  UNION ALL SELECT 'ADMISI','registration' UNION ALL SELECT 'ADMISI','scheduling'
  UNION ALL SELECT 'PERAWAT','inpatient' UNION ALL SELECT 'PERAWAT','igd' UNION ALL SELECT 'PERAWAT','nursing' UNION ALL SELECT 'PERAWAT','cpoe'
  UNION ALL SELECT 'APOTEKER','pharmacy'
  UNION ALL SELECT 'ATLM','laboratory' UNION ALL SELECT 'DOKTER_LAB','laboratory' UNION ALL SELECT 'VALIDATOR_LAB','laboratory'
  UNION ALL SELECT 'RADIOGRAFER','radiology' UNION ALL SELECT 'DOKTER_RAD','radiology'
  UNION ALL SELECT 'KASIR','billing' UNION ALL SELECT 'ASURANSI','billing'
  UNION ALL SELECT 'PETUGAS_GUDANG','inventory' UNION ALL SELECT 'KEPALA_GUDANG','inventory' UNION ALL SELECT 'KEPALA_GUDANG','reporting'
  UNION ALL SELECT 'PIMPINAN','reporting'
) m
JOIN roles r ON r.kode_role = m.kode
JOIN permissions p ON p.kode_permission LIKE CONCAT(m.modul, '.%');
