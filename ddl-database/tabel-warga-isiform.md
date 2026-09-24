# Tabel Legacy: `warga_isiform`

> Sumber: `skema_sistem_warga.sql` baris 217-233. `AUTO_INCREMENT=290`.
> Status: **NONAKTIF** — tidak ada modul kode. Jejak pendataan via Google Form.

```sql
CREATE TABLE `warga_isiform` (
  `warga_isiform_id` int(11) NOT NULL AUTO_INCREMENT,
  `warga_id` int(11) DEFAULT NULL,
  `warga_isiform_tahun` year(4) DEFAULT NULL COMMENT 'tahun isian google form',
  `warga_isiform_waktu` datetime DEFAULT NULL COMMENT 'waktu saat mengisi google form',
  `created_by` varchar(100) DEFAULT NULL, `created_time` datetime DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL, `updated_time` datetime DEFAULT NULL,
  PRIMARY KEY (`warga_isiform_id`),
  CONSTRAINT FOREIGN KEY (`warga_id`) REFERENCES `warga` (`warga_id`)
) COMMENT='mencatat data warga (KK) yang sudah mengisi pendataan warga melalui google form';
```
