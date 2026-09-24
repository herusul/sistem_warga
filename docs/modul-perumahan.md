# Modul Perumahan

> Kembali ke [SISTEM_WARGA](SISTEM_WARGA.md)

## 1. Tujuan

Empat sub-modul dalam satu folder `modules/perumahan/`:

- `rumah`: kavling fisik.
- `pemilik_rumah`: pemilik legal (bisa warga WP atau luar WP/manual).
- `rumahwarga`: hunian KK saat ini.
- `koordinator_gang`: ketua/korling per gang (1 aktif per gang).

## 2. Akses

Semua file: `check_auth(['superadmin','operator','admin'])`. Total 19 file CRUD + detail.

## 3. File

- `rumah.php`, `rumah_tambah.php`, `rumah_edit.php`, `rumah_hapus.php`.
- `pemilik_rumah.php`, `pemilik_rumah_detail.php`, `pemilik_rumah_tambah.php`, `pemilik_rumah_edit.php`, `pemilik_rumah_hapus.php`.
- `rumahwarga.php`, `rumahwarga_detail.php`, `rumahwarga_tambah.php`, `rumahwarga_edit.php`, `rumahwarga_hapus.php`.
- `koordinator_gang.php`, `koordinator_gang_detail.php`, `koordinator_gang_tambah.php`, `koordinator_gang_edit.php`, `koordinator_gang_hapus.php`.

## 4. Tabel

- `rumah(rumah_id, rumah_nomor, rumah_nomor_tampil, ref_id_gang, luas_tanah, luas_bangunan, keterangan, rumah_status=Dihuni/Kosong/Dijual, is_aktif)`.
- `rumah_pemilik(rumah_pemilik_id, rumah_id, warga_id NULLABLE, nama/no_hp/alamat/keterangan/tanggal manual, is_aktif 1/NULL)`.
- `warga_rumah(warga_rumah_id, warga_id, rumah_id, ref_id_status_rumah, tanggal, is_aktif 1/NULL)`. `warga_id` hanya KK (`ref=34`). Status: `169 Milik Sendiri, 170 Sewa, 171 Mendiami`.
- `koordinator_gang(koordinator_gang_id, ref_id_gang, warga_id, is_aktif 1/NULL)`.
- `referensi kategori=gang` untuk daftar gang. `koordinator_gang.php` memakai `LEFT JOIN` sehingga gang tanpa korling tetap tampil `Kosong`.

## 5. Alur Kerja

Rumah:

1. Tambah/edit: isi nomor, gang, luas, status.
2. Hapus: hard `DELETE`, tapi dicegah jika `COUNT warga_rumah is_aktif=1 > 0`.

Pemilik:

1. Tambah: pilih `rumah_id` → `UPDATE rumah_pemilik SET is_aktif=NULL WHERE rumah_id=?` → `INSERT is_aktif=1`.
2. Dua opsi: `warga_id` terdaftar (badge `WARGA WP`) atau manual luar WP (badge `LUAR WP`, isi nama/HP/alamat).
3. Hapus: hard `DELETE`.

Hunian (`rumahwarga`):

1. Filter list hanya KK (`ref_hubungan=34`).
2. Tambah: `UPDATE warga_rumah SET is_aktif=NULL WHERE warga_id=?` → `INSERT is_aktif=1` dengan status `169/170/171`.
3. Hapus: hard `DELETE`.

Koordinator:

1. Tambah: `UPDATE ... is_aktif=NULL` per warga lalu `INSERT is_aktif=1`.
2. Edit: `UPDATE ... SET is_aktif=NULL WHERE ref_id_gang=? AND id!=?` agar 1 aktif per gang.
3. Hapus: hard `DELETE`.

## 6. Validasi

- Rumah tidak bisa dihapus jika masih dihuni aktif.
- Hunian hanya untuk KK, bukan anggota.
- Koordinator 1 aktif per gang, dijaga di query edit/tambah.
- Semua `id` URL terenkripsi.

## 7. Catatan untuk Perubahan

- Tambah field rumah/pemilik: update list + detail + form tambah/edit + laporan KK sekaligus.
- Opsi manual vs warga di pemilik sensitif. Perubahan wajib ditulis: mana yang wajib isi, format HP, kebutuhan KTP.
- Jangan ubah arti `169-171` dan `20` tanpa migrasi.
