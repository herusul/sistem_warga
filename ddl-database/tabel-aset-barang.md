# Tabel `aset_barang` (BARU — belum ada di DB, dibuat tahap kode)

> Status: skema disetujui 2026-09-23, tabel fisik dibuat saat tahap kode (migrasi `CREATE TABLE`).
> Decision: `decisions/2026-09-23-modul-aset-tabel-baru.md`.

```sql
CREATE TABLE `aset_barang` (
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
```

## Catatan

- `aset_jumlah_total` berubah hanya via: kembali `rusak/hilang` (berkurang) atau catat pakai (berkurang). Edit manual jumlah dibatasi di aplikasi.
- Ketersediaan dihitung di aplikasi: total − pinjam aktif (lihat `tabel-aset-peminjaman.md`).
