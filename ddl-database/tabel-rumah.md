# Tabel `rumah`

> Sumber: `skema_sistem_warga.sql` baris 97-121. `AUTO_INCREMENT=155`.

```sql
CREATE TABLE `rumah` (
  `rumah_id` int(11) NOT NULL AUTO_INCREMENT,
  `rumah_nomor` int(5) DEFAULT NULL,
  `rumah_nomor_tampil` varchar(15) DEFAULT NULL,
  `ref_id_gang` int(11) DEFAULT NULL,
  `rumah_luas_tanah` decimal(10,2) DEFAULT NULL COMMENT 'dalam satuan meter persegi',
  `rumah_luas_bangunan` decimal(10,2) DEFAULT NULL COMMENT 'dalam satuan meter persegi',
  `rumah_keterangan` text DEFAULT NULL COMMENT 'informasi lain tentang rumah',
  `rumah_status` enum('Dihuni','Kosong','Dijual') DEFAULT NULL COMMENT 'status rumah: terisi ataukah kosong',
  `is_aktif` tinyint(1) DEFAULT 1 COMMENT '1: aktif, null: tidak aktif',
  `created_by` varchar(100) DEFAULT NULL,
  `created_time` datetime DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL,
  `updated_time` datetime DEFAULT NULL,
  PRIMARY KEY (`rumah_id`),
  UNIQUE KEY `nomor_rumah_2` (`rumah_nomor`,`is_aktif`),
  KEY (`ref_id_gang`,`rumah_nomor_tampil`,`rumah_status`),
  CONSTRAINT FOREIGN KEY (`ref_id_gang`) REFERENCES `referensi` (`ref_id`)
) COMMENT='informasi rumah seperti nomor rumah, nama gang, luas tanah, luas bangunan dll.';
```

## Catatan

- `rumah_nomor` (int) vs `rumah_nomor_tampil` (varchar, misal `12A`). Tampilan pakai `rumah_nomor_tampil`.
- Hapus di kode hard `DELETE` tapi dicegah jika masih ada `warga_rumah is_aktif=1`.
