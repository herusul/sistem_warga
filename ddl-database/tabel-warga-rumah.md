# Tabel `warga_rumah` (hunian KK)

> Sumber: `skema_sistem_warga.sql` baris 258-281. `AUTO_INCREMENT=230`.

```sql
CREATE TABLE `warga_rumah` (
  `warga_rumah_id` int(11) NOT NULL AUTO_INCREMENT,
  `warga_id` int(11) DEFAULT NULL COMMENT 'id kepala keluarga yg tinggal di rumah tsb',
  `rumah_id` int(11) DEFAULT NULL,
  `ref_id_status_rumah` int(11) DEFAULT NULL,
  `warga_rumah_tanggal` date DEFAULT NULL COMMENT 'tanggal mulai tinggal di rumah tersebut',
  `is_aktif` tinyint(1) DEFAULT NULL COMMENT '1: aktif, null: tidak aktif',
  `created_by` varchar(100) DEFAULT NULL,
  `created_time` datetime DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL,
  `updated_time` datetime DEFAULT NULL,
  PRIMARY KEY (`warga_rumah_id`),
  UNIQUE KEY `rumah_id` (`rumah_id`,`is_aktif`,`warga_id`),
  KEY (`warga_id`,`ref_id_status_rumah`,`rumah_id`),
  CONSTRAINT FOREIGN KEY (`warga_id`) REFERENCES `warga` (`warga_id`),
  CONSTRAINT FOREIGN KEY (`rumah_id`) REFERENCES `rumah` (`rumah_id`),
  CONSTRAINT FOREIGN KEY (`ref_id_status_rumah`) REFERENCES `referensi` (`ref_id`)
) COMMENT='data warga (kepala keluarga) yang menempati rumah, status kepemilikan rumah, kapan menempati rumah dan ref rumah';
```

## Catatan

- `warga_id` hanya untuk KK (`ref_id_hubungan_keluarga=34`). Anggota ikut rumah KK-nya via `warga_parent`.
- `ref_id_status_rumah`: `169 Milik Sendiri, 170 Sewa, 171 Mendiami`.
- Pola ganti hunian: `UPDATE ... SET is_aktif=NULL WHERE warga_id=?` lalu `INSERT is_aktif=1`.
