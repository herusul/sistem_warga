# Tabel `aset_pemakaian` (BARU — belum ada di DB, dibuat tahap kode)

> Status: skema disetujui 2026-09-23. Khusus barang kategori `habis_pakai`. Tanpa kembali.

```sql
CREATE TABLE `aset_pemakaian` (
  `aset_pakai_id` int(11) NOT NULL AUTO_INCREMENT,
  `aset_id` int(11) DEFAULT NULL,
  `jumlah` int(11) NOT NULL,
  `keperluan` varchar(255) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `dicatat_oleh` varchar(100) DEFAULT NULL,
  `created_time` datetime DEFAULT NULL,
  PRIMARY KEY (`aset_pakai_id`),
  KEY (`aset_id`),
  CONSTRAINT FOREIGN KEY (`aset_id`) REFERENCES `aset_barang` (`aset_id`)
    ON DELETE NO ACTION ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='pencatatan pakai barang habis pakai; mengurangi jumlah_total permanen';
```

## Catatan

- Setiap `INSERT` wajib disertai pengurangan `aset_barang.aset_jumlah_total` dalam transaksi yang sama di kode.
- Tolak jika `jumlah > aset_jumlah_total` saat itu.
