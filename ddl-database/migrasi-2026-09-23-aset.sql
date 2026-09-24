-- Migrasi modul aset — SISTEM WARGA (wahanapraja)
-- Tanggal: 2026-09-23 | Decision: decisions/2026-09-23-modul-aset-tabel-baru.md
-- Cara pakai (pilih salah satu):
--   1. phpMyAdmin: buka database `wahanapraja` > Import > pilih file ini > Go.
--   2. CLI:  mysql -u root wahanapraja < ddl-database/migrasi-2026-09-23-aset.sql
-- Hanya CREATE TABLE baru, tidak mengubah tabel lama.
--
-- Jika migrasi versi awal (tanpa kolom jml_normal/jml_rusak/jml_hilang) sudah
-- terlanjur dijalankan, jalankan ALTER berikut, bukan ulang CREATE:
--   ALTER TABLE `aset_peminjaman`
--     ADD COLUMN `jml_normal` int(11) NOT NULL DEFAULT 0 COMMENT 'rincian kembali baik' AFTER `hasil`,
--     ADD COLUMN `jml_rusak` int(11) NOT NULL DEFAULT 0 COMMENT 'rincian kembali rusak (mengurangi total)' AFTER `jml_normal`,
--     ADD COLUMN `jml_hilang` int(11) NOT NULL DEFAULT 0 COMMENT 'rincian hilang (mengurangi total)' AFTER `jml_rusak`;

CREATE TABLE IF NOT EXISTS `aset_barang` (
  `aset_id` int(11) NOT NULL AUTO_INCREMENT,
  `aset_nama` varchar(150) NOT NULL,
  `aset_kategori` enum('tetap','habis_pakai') NOT NULL DEFAULT 'tetap'
    COMMENT 'tetap: dipinjam-kembali; habis_pakai: catat pakai, berkurang permanen',
  `aset_jumlah_total` int(11) NOT NULL DEFAULT 0,
  `aset_kondisi` enum('Baik','Rusak ringan','Rusak berat') NOT NULL DEFAULT 'Baik',
  `aset_lokasi` varchar(150) DEFAULT NULL COMMENT 'lokasi penyimpanan',
  `aset_keterangan` text DEFAULT NULL,
  `is_aktif` tinyint(1) DEFAULT 1 COMMENT '1: aktif, null: tidak aktif',
  `created_by` varchar(100) DEFAULT NULL,
  `created_time` datetime DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL,
  `updated_time` datetime DEFAULT NULL,
  PRIMARY KEY (`aset_id`),
  KEY `aset_kategori` (`aset_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='master barang/aset RT (tetap dan habis pakai)';

CREATE TABLE IF NOT EXISTS `aset_peminjaman` (
  `aset_pinjam_id` int(11) NOT NULL AUTO_INCREMENT,
  `aset_id` int(11) DEFAULT NULL,
  `warga_id` int(11) DEFAULT NULL COMMENT 'peminjam warga WP; NULL jika manual luar WP',
  `peminjam_nama` varchar(100) DEFAULT NULL COMMENT 'wajib jika warga_id NULL',
  `peminjam_no_hp` varchar(50) DEFAULT NULL,
  `jumlah` int(11) NOT NULL DEFAULT 1,
  `tgl_pinjam` date NOT NULL,
  `tgl_rencana_kembali` date DEFAULT NULL,
  `tgl_kembali` date DEFAULT NULL,
  `hasil` enum('normal','rusak','hilang') DEFAULT NULL COMMENT 'ringkasan: hilang jika ada hilang, rusak jika ada rusak, normal jika semua baik',
  `jml_normal` int(11) NOT NULL DEFAULT 0 COMMENT 'rincian kembali baik',
  `jml_rusak` int(11) NOT NULL DEFAULT 0 COMMENT 'rincian kembali rusak (mengurangi total)',
  `jml_hilang` int(11) NOT NULL DEFAULT 0 COMMENT 'rincian hilang (mengurangi total)',
  `keterangan` text DEFAULT NULL,
  `is_aktif` tinyint(1) DEFAULT NULL COMMENT '1: sedang dipinjam; null: selesai',
  `created_by` varchar(100) DEFAULT NULL,
  `created_time` datetime DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL,
  `updated_time` datetime DEFAULT NULL,
  PRIMARY KEY (`aset_pinjam_id`),
  KEY `aset_id` (`aset_id`),
  KEY `warga_id` (`warga_id`),
  KEY `is_aktif` (`is_aktif`),
  CONSTRAINT `aset_peminjaman_ibfk_1` FOREIGN KEY (`aset_id`) REFERENCES `aset_barang` (`aset_id`) ON DELETE NO ACTION ON UPDATE CASCADE,
  CONSTRAINT `aset_peminjaman_ibfk_2` FOREIGN KEY (`warga_id`) REFERENCES `warga` (`warga_id`) ON DELETE NO ACTION ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='riwayat peminjaman barang tetap: dipinjam (aktif) -> kembali (normal/rusak/hilang)';

CREATE TABLE IF NOT EXISTS `aset_pemakaian` (
  `aset_pakai_id` int(11) NOT NULL AUTO_INCREMENT,
  `aset_id` int(11) DEFAULT NULL,
  `jumlah` int(11) NOT NULL,
  `keperluan` varchar(255) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `dicatat_oleh` varchar(100) DEFAULT NULL,
  `created_time` datetime DEFAULT NULL,
  PRIMARY KEY (`aset_pakai_id`),
  KEY `aset_id` (`aset_id`),
  CONSTRAINT `aset_pemakaian_ibfk_1` FOREIGN KEY (`aset_id`) REFERENCES `aset_barang` (`aset_id`) ON DELETE NO ACTION ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='pencatatan pakai barang habis pakai; mengurangi jumlah_total permanen';
