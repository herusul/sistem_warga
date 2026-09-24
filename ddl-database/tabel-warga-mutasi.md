# Tabel `warga_mutasi`

> Sumber: `skema_sistem_warga.sql` baris 235-256. `AUTO_INCREMENT=892`.

```sql
CREATE TABLE `warga_mutasi` (
  `warga_mutasi_id` int(11) NOT NULL AUTO_INCREMENT,
  `warga_id` int(11) DEFAULT NULL,
  `ref_id_status_aktif` int(11) DEFAULT NULL,
  `warga_mutasi_tanggal` date DEFAULT NULL COMMENT 'tanggal dari status mutasi warga',
  `warga_mutasi_keterangan` text DEFAULT NULL,
  `is_aktif` tinyint(1) DEFAULT NULL COMMENT '1:aktif ; null:tidak aktif',
  `created_by` varchar(100) DEFAULT NULL,
  `created_time` datetime DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL,
  `updated_time` datetime DEFAULT NULL,
  PRIMARY KEY (`warga_mutasi_id`),
  UNIQUE KEY `warga_id` (`warga_id`,`is_aktif`),
  KEY (`warga_id`,`ref_id_status_aktif`),
  CONSTRAINT FOREIGN KEY (`warga_id`) REFERENCES `warga` (`warga_id`),
  CONSTRAINT FOREIGN KEY (`ref_id_status_aktif`) REFERENCES `referensi` (`ref_id`)
) COMMENT='mencatat mutasi masuk/keluar warga kapan mulai menetap di WP, pindah/keluar dari WP, dan meninggal dunia';
```

## Catatan

- `UNIQUE(warga_id, is_aktif)`: MySQL menganggap banyak NULL tidak duplikat, jadi hanya 1 baris `is_aktif=1` per warga. Pola kode: nonaktifkan dulu (`SET is_aktif=NULL`) lalu `INSERT is_aktif=1`.
- `ref_id_status_aktif`: `161 Menetap, 162 Pindah, 163 Meninggal, 164 Tidak Tinggal`.
- Dipakai filter status di `index.php`, `dashboard.php`, `grafik.php`, laporan.
- Hapus di kode memakai hard `DELETE` (satu-satunya di area warga).
