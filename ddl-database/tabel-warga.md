# Tabel `warga`

> Sumber: `skema_sistem_warga.sql` baris 163-215. Engine InnoDB, `AUTO_INCREMENT=656`.

## DDL (ringkas, komentar asli dipertahankan)

```sql
CREATE TABLE `warga` (
  `warga_id` int(11) NOT NULL AUTO_INCREMENT,
  `warga_nama` varchar(100) NOT NULL COMMENT 'nama lengkap tanpa gelar',
  `warga_nama_gelar` varchar(200) DEFAULT NULL COMMENT 'nama lengkap dengan gelar',
  `warga_nama_tampil` varchar(100) DEFAULT NULL COMMENT 'nama yang tampil di cetakan / nama panggilan',
  `warga_tempat_lahir` varchar(100) DEFAULT NULL,
  `warga_tgl_lahir` date DEFAULT NULL,
  `warga_email` varchar(100) DEFAULT NULL,
  `warga_no_hp` varchar(50) DEFAULT NULL,
  `ref_id_pendidikan` int(11) DEFAULT NULL,
  `ref_id_pekerjaan` int(11) DEFAULT NULL,
  `warga_pekerjaan` varchar(100) DEFAULT '' COMMENT 'diisi ketika kolom ref_id_pekerjaan berisi Lainnya (id 150)',
  `warga_parent` int(11) DEFAULT NULL COMMENT 'parent anggota keluarga ke kepala keluarga ; NULL = kepala keluarga',
  `ref_id_hubungan_keluarga` int(11) DEFAULT 34,
  `warga_hubungan_keluarga` varchar(100) DEFAULT NULL COMMENT 'diisi ketika kolom ref_id_hubungan_keluarga berisi Lainnya (id 49)',
  `ref_id_jenis_kelamin` int(11) NOT NULL,
  `warga_nik` varchar(16) DEFAULT NULL,
  `warga_nomor_kk` varchar(16) DEFAULT NULL,
  `ref_id_agama` int(11) NOT NULL,
  `ref_id_golongan_darah` int(11) NOT NULL,
  `ref_id_status_kawin` int(11) DEFAULT NULL,
  `warga_foto` varchar(255) DEFAULT 'profile_blank.jpg',
  `warga_dokumen_ktp` varchar(255) DEFAULT NULL,
  `is_ktp_wp` tinyint(1) DEFAULT 1 COMMENT '1:KTP Wahana Praja, 0:KTP bukan Wahana Praja, 2:Belum memiliki KTP',
  `warga_dokumen_kk` varchar(255) DEFAULT NULL,
  `warga_negara` varchar(70) DEFAULT 'WNI',
  `is_delete` tinyint(1) DEFAULT NULL COMMENT 'soft delete (1:delete, NULL tidak)',
  `created_by` varchar(100) DEFAULT NULL,
  `created_time` datetime DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL,
  `updated_time` datetime DEFAULT NULL,
  PRIMARY KEY (`warga_id`),
  KEY (`warga_parent`,`ref_id_pendidikan`,`ref_id_pekerjaan`,`ref_id_hubungan_keluarga`,
       `ref_id_jenis_kelamin`,`ref_id_agama`,`ref_id_golongan_darah`,`ref_id_status_kawin`),
  CONSTRAINT `warga_ibfk_1..8` FOREIGN KEY (`ref_id_*`) REFERENCES `referensi` (`ref_id`),
  CONSTRAINT `warga_ibfk_3` FOREIGN KEY (`warga_parent`) REFERENCES `warga` (`warga_id`)
) COMMENT='data master seluruh warga yang tinggal di WP';
```

## Catatan pemakaian di kode

- `warga_parent` NULL = KK. Anggota menunjuk `warga_id` KK-nya.
- Default `ref_id_hubungan_keluarga=34` (KK).
- `warga_email` = kunci login Google (`google_auth.php`, cocok `LOWER(email)` + `is_delete IS NULL`).
- `is_delete IS NULL` wajib di semua list. Hapus = `UPDATE ... SET is_delete=1`.
- `is_ktp_wp`: `1` Ya, `0` Tidak, `2` Belum — dipakai grafik.
- Upload di kode: `warga_foto` (jpg/png), `warga_dokumen_ktp`/`warga_dokumen_kk` (pdf). Nama kolom DB memakai prefix `warga_` (`warga_foto` dst), di kode kadang disebut `foto/dok_ktp/dok_kk` — itu alias form, bukan nama kolom.
