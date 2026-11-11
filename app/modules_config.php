<?php
return [
 'registration'=>['label'=>'Registrasi & Admisi','permission'=>'registration','description'=>'Pendaftaran pasien, kunjungan, antrean, penjamin, unit, dan jadwal.','tables'=>['pasien','kunjungan','antrean','penjamin','unit','jadwal_praktik']],
 'scheduling'=>['label'=>'Jadwal Praktik','permission'=>'scheduling','description'=>'Jadwal dokter dan unit pelayanan.','tables'=>['jadwal_praktik','pegawai','unit']],
 'outpatient'=>['label'=>'Rawat Jalan','permission'=>'outpatient','description'=>'Kunjungan rawat jalan, tanda vital, rekam medis, diagnosis, tindakan, dan lampiran.','tables'=>['kunjungan','tanda_vital','rekam_medis','diagnosis','tindakan_medis','lampiran']],
 'inpatient'=>['label'=>'Rawat Inap','permission'=>'inpatient','description'=>'Kamar, episode rawat inap, dan monitoring pasien.','tables'=>['kamar','rawat_inap','monitoring','tanda_vital']],
 'igd'=>['label'=>'Instalasi Gawat Darurat','permission'=>'igd','description'=>'Triase, asesmen IGD, dan kunjungan darurat.','tables'=>['triase_igd','asesmen_igd','kunjungan']],
 'emr'=>['label'=>'Rekam Medis Elektronik','permission'=>'emr','description'=>'Rekam medis, diagnosis, tindakan medis, dan lampiran.','tables'=>['rekam_medis','diagnosis','tindakan_medis','lampiran']],
 'nursing'=>['label'=>'Keperawatan','permission'=>'nursing','description'=>'Catatan keperawatan, tanda vital, dan monitoring.','tables'=>['catatan_keperawatan','tanda_vital','monitoring']],
 'cpoe'=>['label'=>'Order Medis (CPOE)','permission'=>'cpoe','description'=>'Order layanan, detail, dan verifikasi.','tables'=>['order_layanan','detail_order','verifikasi_order']],
 'laboratory'=>['label'=>'Laboratorium','permission'=>'laboratory','description'=>'Parameter dan hasil pemeriksaan laboratorium.','tables'=>['parameter_lab','hasil_lab','verifikasi_order']],
 'radiology'=>['label'=>'Radiologi','permission'=>'radiology','description'=>'Pemeriksaan dan hasil radiologi.','tables'=>['pemeriksaan_radiologi','hasil_radiologi']],
 'pharmacy'=>['label'=>'Farmasi','permission'=>'pharmacy','description'=>'Supplier, obat, batch obat, resep, mutasi, dan retur.','tables'=>['supplier','obat','batch_obat','resep','resep_item','mutasi_stok','retur_obat']],
 'billing'=>['label'=>'Billing & Kasir','permission'=>'billing','description'=>'Tagihan, detail tagihan, pembayaran, klaim, dan kuitansi.','tables'=>['tagihan','detail_tagihan','pembayaran','klaim','kuitansi']],
 'inventory'=>['label'=>'Persediaan & Gudang','permission'=>'inventory','description'=>'Gudang, barang, batch, penerimaan, permintaan, mutasi, stok opname, dan aset.','tables'=>['gudang','barang_persediaan','batch_persediaan','penerimaan_persediaan','detail_penerimaan_persediaan','permintaan_persediaan','detail_permintaan_persediaan','mutasi_persediaan','stok_opname','detail_stok_opname','aset_tetap']],
 'reporting'=>['label'=>'Laporan Rumah Sakit','permission'=>'reporting','description'=>'Ringkasan dan tabel sumber laporan.','tables'=>['kunjungan','tagihan','pembayaran','mutasi_persediaan']],
 'master'=>['label'=>'Master Data','permission'=>'master_data','description'=>'Data referensi unit, pegawai, penjamin, kamar, supplier, obat, dan gudang.','tables'=>['unit','pegawai','penjamin','kamar','supplier','obat','parameter_lab','gudang','barang_persediaan']],
 'audit'=>['label'=>'Audit Trail','permission'=>'reporting','description'=>'Riwayat aktivitas aplikasi.','tables'=>['audit_trail']]
];
