# View & Function

> Sumber: `skema_sistem_warga.sql` baris 283-364.

## View `v_warga_usia`

Dipakai `modules/warga/warga_detail.php` untuk usia + format tanggal lahir.

```sql
CREATE VIEW `v_warga_usia` AS
SELECT
  `warga`.`warga_id`, `warga`.`warga_nama`, `warga`.`warga_tgl_lahir`,
  `fn_tgl_indo`(`warga`.`warga_tgl_lahir`) AS `tgl_lahir_pjg`,
  DATE_FORMAT(`warga`.`warga_tgl_lahir`,'%d-%m-%Y') AS `tgl_lahir_pnd`,
  TIMESTAMPDIFF(YEAR,`warga`.`warga_tgl_lahir`,CURDATE()) AS `usia_tahun`,
  TIMESTAMPDIFF(MONTH,`warga`.`warga_tgl_lahir`,CURDATE()) MOD 12 AS `usia_bulan`,
  CONCAT(TIMESTAMPDIFF(YEAR,...),' tahun ',TIMESTAMPDIFF(MONTH,...) MOD 12,' bulan') AS `usia`
FROM `warga` ORDER BY `usia_tahun` DESC, `usia_bulan` DESC;
```

## Function `fn_tgl_indo(tanggal DATE)`

Mengembalikan `D Bulan YYYY` Indonesia (Januari..Desember). Dipakai view di atas.

## Function `fn_tgl_indo_dgn_hari(tanggal DATE)`

Mengembalikan `Hari, D Bulan YYYY` (Minggu..Sabtu + `fn_tgl_indo`). Tidak dipakai modul aktif — tersedia untuk laporan masa depan.
