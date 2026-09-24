# Tabel Legacy: `ronda` + `ronda_periode`

> Sumber: `skema_sistem_warga.sql` baris 56-95.
> Status: **NONAKTIF** — ada di DB, **tidak ada modul kode** di `modules/`. Jangan dipakai fitur baru tanpa decision.

```sql
CREATE TABLE `ronda_periode` (
  `ronda_periode_id` int(11) NOT NULL AUTO_INCREMENT,
  `ronda_periode_nama` varchar(100) DEFAULT NULL,
  `ronda_periode_mulai` date DEFAULT NULL,
  `ronda_periode_selesai` date DEFAULT NULL,
  `is_aktif` tinyint(1) DEFAULT NULL COMMENT '1:aktif ; 0:tidak aktif',
  `created_by` varchar(50) DEFAULT NULL, `created_time` datetime DEFAULT NULL,
  `updated_by` varchar(50) DEFAULT NULL, `updated_time` datetime DEFAULT NULL,
  PRIMARY KEY (`ronda_periode_id`)
);

CREATE TABLE `ronda` (
  `ronda_id` int(11) NOT NULL AUTO_INCREMENT,
  `ronda_periode_id` int(11) DEFAULT NULL,
  `ref_id_hari` int(11) DEFAULT NULL,
  `warga_id` int(11) DEFAULT NULL,
  `ronda_urut` tinyint(2) DEFAULT NULL COMMENT 'urutan nama warga dalam kelompok ronda ; 1:koordinator grup ronda, 2:wakil',
  `is_aktif` tinyint(1) DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL, `created_time` datetime DEFAULT NULL,
  `updated_by` varchar(50) DEFAULT NULL, `updated_time` datetime DEFAULT NULL,
  PRIMARY KEY (`ronda_id`),
  CONSTRAINT FOREIGN KEY (`ronda_periode_id`) REFERENCES `ronda_periode` (`ronda_periode_id`),
  CONSTRAINT FOREIGN KEY (`ref_id_hari`) REFERENCES `referensi` (`ref_id`),
  CONSTRAINT FOREIGN KEY (`warga_id`) REFERENCES `warga` (`warga_id`)
) COMMENT='daftar ronda warga WP';
```

## Catatan

- Perhatikan: `ronda_periode.is_aktif` memakai `1/0` (bukan `1/NULL` seperti tabel aktif lain). Beda konvensi — salah satu alasan modul ini tidak dilanjutkan.
- Memakai kategori `referensi` tambahan: `hari`.
