# Tabel `referensi`

> Sumber: `skema_sistem_warga.sql` baris 40-54. `AUTO_INCREMENT=182`.

```sql
CREATE TABLE `referensi` (
  `ref_id` int(11) NOT NULL AUTO_INCREMENT,
  `ref_kategori` varchar(50) DEFAULT NULL,
  `ref_nama` varchar(200) DEFAULT NULL,
  `ref_urut` tinyint(2) DEFAULT NULL,
  `is_aktif` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`ref_id`),
  UNIQUE KEY `ref_kategori` (`ref_kategori`,`ref_nama`),
  KEY (`ref_kategori`,`ref_urut`)
) COMMENT='data referensi dalam satu tabel dengan kategori sebagai pembeda';
```

## Kategori di kode

`agama, jenis_kelamin, golongan_darah, pendidikan, pekerjaan, status_kawin, hubungan_keluarga, status_aktif, status_rumah, gang` (+ `hari` untuk tabel `ronda` legacy).

## ID sakral (jangan diubah/hapus)

- `hubungan_keluarga`: `34` KK, `49` Lainnya.
- `jenis_kelamin`: `50` L, `51` P.
- `status_aktif`: `161` Menetap, `162` Pindah, `163` Meninggal, `164` Tidak Tinggal.
- `status_rumah`: `169` Milik Sendiri, `170` Sewa, `171` Mendiami.
- `gang`: `20` gang utama.
- `pekerjaan`: `150` Lainnya (mengaktifkan kolom `warga.warga_pekerjaan` teks bebas).

## Catatan

- `UNIQUE(kategori, nama)`. Dropdown kode filter `is_aktif=1`.
- Hapus di kode hard `DELETE` tanpa cek FK — berisiko orphan. Wajib cek pemakaian dulu.
