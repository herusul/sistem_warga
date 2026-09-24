# Tabel `aset_peminjaman` (BARU — belum ada di DB, dibuat tahap kode)

> Status: skema disetujui 2026-09-23. Khusus barang kategori `tetap`.
> Pola dual peminjam mengikuti `rumah_pemilik` (warga WP atau manual luar WP).

```sql
CREATE TABLE `aset_peminjaman` (
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
  KEY (`aset_id`,`warga_id`,`is_aktif`),
  CONSTRAINT FOREIGN KEY (`aset_id`) REFERENCES `aset_barang` (`aset_id`)
    ON DELETE NO ACTION ON UPDATE CASCADE,
  CONSTRAINT FOREIGN KEY (`warga_id`) REFERENCES `warga` (`warga_id`)
    ON DELETE NO ACTION ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='riwayat peminjaman barang tetap: dipinjam (aktif) -> kembali (normal/rusak/hilang)';
```

## Catatan

- Pinjam: `INSERT is_aktif=1` setelah cek `jumlah ≤ tersedia`.
- Kembali: rincian `jml_normal + jml_rusak + jml_hilang` wajib `= jumlah` (satu kali pengembalian penuh).
  `UPDATE ... SET tgl_kembali, hasil, jml_*, is_aktif=NULL`. Hanya `jml_rusak + jml_hilang` yang mengurangi
  `aset_barang.aset_jumlah_total`; yang baik otomatis menambah ketersediaan (pinjam aktif tertutup).
- `hasil` hanya ringkasan untuk badge (`hilang` > `rusak` > `normal`); angka resmi ada di `jml_*`.
