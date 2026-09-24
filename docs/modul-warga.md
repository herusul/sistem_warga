# Modul Warga (Master Data)

> Kembali ke [SISTEM_WARGA](SISTEM_WARGA.md)

## 1. Tujuan

Master biodata warga untuk pengurus. Termasuk pencarian, filter gang/status, CRUD lengkap, upload foto dan dokumen, serta direktori bersih untuk semua role.

## 2. Akses

- `modules/warga/warga.php`, `warga_detail.php`: `check_auth(['superadmin','operator','admin'])`.
- `warga_tambah.php`, `warga_edit.php`, `warga_hapus.php`: sama, pengurus saja.
- `modules/warga/pencarian.php`: `check_auth()` — semua login termasuk `user/pengguna`. Tampilan bersih: nama, status keluarga `(KK : ...)`, `WP {nomor}`, gang, status. Tidak ada tombol edit/hapus. Default kosong sampai klik Cari. Filter `q_nama, q_rumah, gang, status`.

## 3. File

- `warga.php`: list + `?q=&status=&gang=&page=`, limit 10, join mutasi + KK + rumah.
- `pencarian.php`: filter `q_nama, q_rumah, gang, status`, limit 15, kolom Status Keluarga + order grouping KK persis `warga.php` (`rumah_nomor, parent, ref_id, hubungan, nama`). Filter nama hanya `a.warga_nama` (tidak cari nama KK).
- `warga_tambah.php`: form biodata + upload + pilihan KK (`?kk_id=`) dan rumah jika SHDK=KK.
- `warga_edit.php`: update biodata saja, tidak menyentuh mutasi/rumah.
- `warga_hapus.php`: soft-delete via POST + CSRF.
- `warga_detail.php`: biodata + usia (`v_warga_usia`) + riwayat mutasi + hunian.
- `pencarian.php`: filter `q_nama, q_rumah, gang`, limit 15.

## 4. Tabel dan Kolom Kunci

- `warga`: `warga_id, warga_nama, nik, nomor_kk, warga_parent (FK ke KK), ref_id_agama/jenis_kelamin/gol_darah/pendidikan/pekerjaan/status_kawin/hubungan_keluarga, warga_hubungan_keluarga (teks bebas jika 49), foto, dok_ktp, dok_kk, is_ktp_wp (1=Ya,0=Tidak,2=Belum), is_delete, created_by/time, updated_by/time`.
- `warga_mutasi`: dipakai untuk status aktif (`161-164`, `is_aktif=1`).
- `warga_rumah` + `rumah` + `referensi(gang)`: dipakai untuk kolom gang dan nomor rumah.
- `referensi`: semua dropdown (`is_aktif=1`).

## 5. Alur Kerja

Tambah:

1. Cek duplikat NIK.
2. `INSERT warga`.
3. `INSERT warga_mutasi is_aktif=1` (default Menetap 161 jika tidak diisi).
4. Jika SHDK `34` (KK) + `rumah_id` dipilih → `INSERT warga_rumah is_aktif=1`.
5. Upload: foto `jpg/jpeg/png`, KTP/KK `pdf`, maks 5MB ke `assets/uploads/`.

Edit:

1. Buka via `?id=encrypt`. Validasi NIK tidak dipakai warga lain (`!=warga_id`).
2. `UPDATE warga` saja. Ganti status tinggal lewat modul Mutasi, ganti rumah lewat modul Perumahan.
3. JS: jika SHDK=KK sembunyikan pilih KK, tampilkan pilih rumah (perilaku tambah vs edit sedikit beda, perhatikan saat ubah form).

Hapus:

1. Konfirmasi → POST + CSRF → `UPDATE warga SET is_delete=1`.
2. List selalu `WHERE is_delete IS NULL`.

Pencarian cepat:

1. Default kosong: isi filter lalu klik Cari → hasil read-only dengan pagination.
2. Kolom Status Keluarga persis `warga.php` termasuk `(KK : Nama KK)`.

## 6. Validasi

- NIK unik (kecuali milik sendiri saat edit).
- SHDK `34` tidak boleh punya `warga_parent` (dia KK). Anggota wajib punya parent KK.
- File di luar tipe/ukuran ditolak.
- `decrypt_id()` gagal → kembali ke list.

## 7. Catatan untuk Perubahan

- Tambah kolom warga: update `warga_tambah.php`, `warga_edit.php`, `warga_detail.php`, `pencarian.php` sekaligus + tulis di usulan fitur.
- Jangan ubah arti `34/49/50/51` tanpa migrasi data.
- Upload baru wajib pakai nama acak + validasi MIME, bukan hanya ekstensi.
