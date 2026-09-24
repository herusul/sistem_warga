# Modul Keluarga (Self-service Warga)

> Kembali ke [SISTEM_WARGA](SISTEM_WARGA.md)

## 1. Tujuan

Halaman `Profil Saya & Keluarga` untuk warga yang login via Google. Pengurus bisa pratinjau via impersonasi. Tidak ada fitur hapus.

## 2. Akses

- `modules/keluarga/index.php`, `anggota_tambah.php`, `anggota_edit.php`: `check_auth()` — semua role.
- Sumber `warga_id`: `$_SESSION['user']['warga_id']`. Jika pengurus tanpa `warga_id`, bisa pakai `?warga_id=encrypt` (hanya `superadmin/operator`).

## 3. File

- `index.php`: tentukan `head_id = is_kk ? self : warga_parent`. Tampilkan KK + daftar `WHERE warga_parent=head_id`. Jika pengurus tanpa ikatan warga, tampilkan mode pratinjau + dropdown 50 warga.
- `anggota_tambah.php`: tambah anggota satu KK. `nomor_kk` readonly dari KK. Blokir SHDK `34` (anti KK ganda).
- `anggota_edit.php`: edit diri/KK/anggota satu KK.

## 4. Tabel

Sama seperti modul Warga: `warga`, `referensi`, `warga_rumah`, `rumah`. Tambahan: `warga_mutasi` hanya untuk insert awal Menetap.

## 5. Alur Kerja

Lihat profil:

1. Login Google → session punya `warga_id` → hitung `head_id`.
2. Tampilkan KK + semua anggota + info rumah.

Tambah anggota:

1. `warga_parent=head_id`, `nomor_kk` ikut KK.
2. Tolak jika `ref_id_hubungan_keluarga=34`.
3. Cek NIK unik → `INSERT warga` → auto `INSERT warga_mutasi ref=161 CURDATE() is_aktif=1`.

Edit anggota:

1. Otorisasi: target harus diri sendiri ATAU `target=head_id` ATAU `parent= head_id`. Selain itu ditolak (kecuali `superadmin/operator`).
2. Jika target adalah KK: SHDK dikunci `34`, `nomor_kk` bisa edit + sinkron ke anak (`UPDATE warga SET nomor_kk, dok_kk WHERE warga_parent=?`).
3. Jika target anggota: `nomor_kk` readonly.

## 6. Validasi

- Email adalah kunci login mandiri, dikelola pengurus di data warga.
- Tidak ada tombol hapus di modul ini. Penghapusan hanya via modul Warga oleh pengurus.
- Impersonasi wajib `decrypt_id()` + cek role.

## 7. Catatan untuk Perubahan

- Izin edit sensitif (KK, NIK, KK number). Setiap perubahan otorisasi harus ditulis di usulan fitur + diuji dengan 3 akun: anggota, KK, pengurus.
- Jika menambah field yang bisa diedit warga, pisahkan mana yang boleh diubah warga vs pengurus.
