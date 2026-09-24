# Tabel `rumah_pemilik`

> Sumber: `skema_sistem_warga.sql` baris 123-147. `AUTO_INCREMENT=158`.

```sql
CREATE TABLE `rumah_pemilik` (
  `rumah_pemilik_id` int(11) NOT NULL AUTO_INCREMENT,
  `rumah_id` int(11) DEFAULT NULL,
  `warga_id` int(11) DEFAULT NULL COMMENT 'id kepala keluarga pemilik rumah tsb',
  `rumah_pemilik_nama` varchar(100) DEFAULT NULL COMMENT 'diisi jika warga_id NULL',
  `rumah_pemilik_no_hp` varchar(50) DEFAULT NULL COMMENT 'diisi jika warga_id NULL',
  `rumah_pemilik_alamat` varchar(255) DEFAULT NULL COMMENT 'diisi jika warga_id NULL',
  `rumah_pemilik_tanggal` date DEFAULT NULL COMMENT 'tanggal rumah dimiliki',
  `rumah_pemilik_keterangan` text DEFAULT NULL COMMENT 'informasi lain tentang kepemilikan rumah',
  `is_aktif` tinyint(1) DEFAULT NULL COMMENT '1: aktif, null: tidak aktif',
  `created_by` varchar(100) DEFAULT NULL,
  `created_time` datetime DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL,
  `updated_time` datetime DEFAULT NULL,
  PRIMARY KEY (`rumah_pemilik_id`),
  UNIQUE KEY `rumah_id` (`rumah_id`,`warga_id`,`is_aktif`),
  KEY (`warga_id`,`rumah_pemilik_nama`),
  CONSTRAINT FOREIGN KEY (`rumah_id`) REFERENCES `rumah` (`rumah_id`),
  CONSTRAINT FOREIGN KEY (`warga_id`) REFERENCES `warga` (`warga_id`)
) COMMENT='data pemilik rumah';
```

## Catatan

- Dua mode: `warga_id` terisi (pemilik warga WP) atau NULL + kolom manual `rumah_pemilik_nama/no_hp/alamat` (luar WP).
- Ganti pemilik: `UPDATE ... SET is_aktif=NULL WHERE rumah_id=?` lalu `INSERT is_aktif=1`.
