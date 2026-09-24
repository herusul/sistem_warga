# Tabel `koordinator_gang`

> Sumber: `skema_sistem_warga.sql` baris 20-38. `AUTO_INCREMENT=12`.

```sql
CREATE TABLE `koordinator_gang` (
  `koordinator_gang_id` tinyint(3) NOT NULL AUTO_INCREMENT,
  `ref_id_gang` int(11) DEFAULT NULL,
  `warga_id` int(11) DEFAULT NULL,
  `is_aktif` tinyint(1) DEFAULT NULL COMMENT '1: aktif, null: tidak aktif',
  `created_by` varchar(100) DEFAULT NULL,
  `created_time` datetime DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL,
  `updated_time` datetime DEFAULT NULL,
  PRIMARY KEY (`koordinator_gang_id`),
  KEY (`warga_id`,`ref_id_gang`),
  CONSTRAINT FOREIGN KEY (`warga_id`) REFERENCES `warga` (`warga_id`),
  CONSTRAINT FOREIGN KEY (`ref_id_gang`) REFERENCES `referensi` (`ref_id`)
) COMMENT='nama koordinator gang';
```

## Catatan

- 1 aktif per gang dijaga di kode (`UPDATE ... SET is_aktif=NULL WHERE ref_id_gang=? AND id!=?`).
- List di kode memakai `LEFT JOIN` dari `referensi gang` sehingga gang tanpa korling tetap tampil `Kosong`.
