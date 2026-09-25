# SISTEM WARGA — Dokumentasi Acuan Kerja

> Lokasi: `sistem_warga/docs/`
> Sifat dokumen: **acuan wajib**. Setiap perubahan atau penambahan fitur **ditulis dulu di sini (via `template-usulan-fitur.md`)**, disetujui, baru dikerjakan. Setelah selesai, update `CHANGELOG.md`.

## 1. Tujuan Dokumen

1. Menyamakan pemahaman tentang fungsi tiap modul.
2. Mencegah perubahan yang merusak relasi data (`warga`, `mutasi`, `rumah`, `referensi`).
3. Menjadi checklist keamanan sebelum coding (auth, CSRF, validasi, upload).

## 2. Gambaran Umum

Sistem Informasi Warga RT 03 RW 14 Wahana Praja I. Mengelola data kependudukan (KK dan anggota), status tinggal (mutasi), hunian dan properti rumah, koordinator gang, serta laporan PDF.

Halaman publik (`index.php`) menampilkan statistik tanpa login. Default filter `menetap`. Halaman dalam memakai layout `views/header.php`, `views/sidebar.php`, `views/footer.php`.

## 3. Tech Stack

- PHP native + `mysqli` (prepared statement), charset `utf8mb4`, timezone `Asia/Jakarta` (`includes/db.php`).
- Bootstrap 5.3 + Bootstrap Icons + jQuery 3.6 (sidebar).
- Chart.js di `modules/dashboard/grafik.php`.
- `libs/tfpdf` untuk ekspor PDF (font DejaVu, mendukung karakter Indonesia).
- Google Identity Services untuk login warga (`includes/google_auth_config.php`, `google_auth.php`).
- Captcha gambar sendiri (`captcha_image.php`) untuk login pengurus.
- Database MySQL `wahanapraja`.

## 4. Struktur Folder

```
sistem_warga/
  index.php                  # dashboard publik, tanpa login
  login.php / logout.php / google_auth.php / captcha_image.php
  session_keepalive.php
  includes/                  # db.php, auth.php, functions.php, encryption.php, google_auth_config.php
  views/                     # header, sidebar, footer, session_timeout_modal
  modules/
    dashboard/               # dashboard.php, grafik.php, grafik_awal.php (legacy), about.php
    warga/                   # warga.php, tambah/edit/hapus/detail, pencarian.php
    keluarga/                # index.php, anggota_tambah.php, anggota_edit.php
    mutasi/                  # mutasi.php, detail, tambah, edit, hapus
    perumahan/               # rumah*, pemilik_rumah*, rumahwarga*, koordinator_gang*
    laporan/                 # daftar_laporan.php + 3 export_pdf_*.php
    referensi/               # referensi.php + tambah/edit/hapus + kategori tambah/hapus
    user/                    # user.php + tambah/edit/hapus
  assets/uploads/            # foto (jpg/png), dok KTP/KK (pdf), maks 5MB
  libs/tfpdf, libs/fpdf, libs/tcpdf
  docs/                      # dokumentasi ini
```

## 5. Role dan Akses

Role yang ada di session `$_SESSION['user']['role']`:

- `superadmin`: akses penuh, satu-satunya yang bisa buka `modules/user/` dan melihat menu Referensi + User di sidebar.
- `operator`: sama seperti superadmin kecuali manajemen user. Bisa impersonasi profil keluarga via `?warga_id=`.
- `admin`: role legacy. Masih diterima di `check_auth(['superadmin','operator','admin'])` untuk modul warga, mutasi, perumahan, laporan, grafik, referensi. Tapi **tidak ada di pilihan role form user** (hanya `superadmin/operator/user`).
- `user` / `pengguna`: dianggap sama di `check_auth()` (alias). Login via Google (`warga_email`). Hanya bisa buka Dashboard, Profil Saya & Keluarga, Pencarian Cepat, Tentang.

Ringkasan akses (detail per modul ada di doc masing-masing):

- Publik tanpa login: `index.php` saja.
- Semua yang login: `dashboard.php`, `about.php`, `keluarga/index.php`, `warga/pencarian.php`.
- Pengurus (`superadmin, operator, admin`): `warga.php`, `mutasi`, `perumahan`, `grafik.php`, `laporan`, `referensi`.
- Khusus `superadmin`: `user.php`.

Catatan inkonsistensi (jangan diubah tanpa diskusi):
- Menu Referensi hanya tampil untuk `superadmin` di `sidebar.php`, padahal `referensi.php` membolehkan `admin/operator`.

## 6. Auth dan Keamanan

- `includes/auth.php`: `check_auth($allowed_roles=[])`. Tanpa argumen = semua login boleh. Timeout **300 detik** (`last_activity`), respons 401 + redirect `login.php?timeout=1` untuk page biasa, JSON 401 untuk AJAX.
- Cookie sesi OWASP: `use_strict_mode`, `use_only_cookies`, `httponly=true`, `samesite=Strict`, `secure` otomatis jika HTTPS.
- Login pengurus (`login.php`): username + password (`password_hash` / `password_verify`) + captcha (`$_SESSION['captcha_text']`) + CSRF. Berhasil: `session_regenerate_id(true)`.
- Login warga: tombol Google GIS → `google_auth.php` verifikasi JWT ke `oauth2/tokeninfo`, cek `email_verified`, cocokkan `LOWER(warga_email)` yang `is_delete IS NULL` → session `role=user`, `warga_id` terisi. Gagal: `?error=google_not_registered|google_token_invalid|google_empty_email`. Dev mode bisa simulasi via `dev_google_email` jika `is_local_dev_environment()`.
- Logout: `invalidate_session()` + hapus cookie + clear `localStorage`.
- Helper (`includes/functions.php`): `e()` untuk escape output, `csrf_input()` / `verify_csrf_token()` / `generate_csrf_token()`.
- ID di URL selalu terenkripsi (`includes/encryption.php`, AES-256-CBC): `encrypt_id()` saat buat link, `decrypt_id()` saat baca. Gagal decrypt = redirect ke halaman list.
- Upload: foto hanya `jpg/jpeg/png`, dokumen KTP/KK hanya `pdf`, nama file acak (`random_bytes` + timestamp) di `assets/uploads/`.

## 7. Konvensi Database

- Soft-delete warga: `warga.is_delete IS NULL` = aktif, `=1` = terhapus. Semua list wajib filter `is_delete IS NULL`.
- Status aktif riwayat: `is_aktif=1` = aktif, `is_aktif IS NULL` = nonaktif. Berlaku untuk `warga_mutasi`, `warga_rumah`, `rumah_pemilik`, `koordinator_gang`. Bukan `0`.
- Ganti status/hunian/pemilik/koordinator: pola `UPDATE ... SET is_aktif=NULL ...` lalu `INSERT ... is_aktif=1`. Jangan update baris lama menjadi aktif ganda.
- `warga.warga_parent` = FK ke `warga.warga_id` milik KK. `warga.nomor_kk` disalin ke semua anggota satu KK.
- Hapus keras (`DELETE`) hanya untuk: `warga_mutasi`, `rumah` (dengan cek), `rumah_pemilik`, `warga_rumah`, `koordinator_gang`, `referensi`, `users`. Warga memakai soft-delete.
- Semua query tulis/baca baru wajib prepared statement + `e()` saat tampilkan.

## 8. Tabel Utama dan Relasi

- `warga(warga_id, warga_nama, nik, nomor_kk, warga_email, ref_id_agama, ref_id_jenis_kelamin, ref_id_gol_darah, ref_id_pendidikan, ref_id_pekerjaan, ref_id_status_kawin, ref_id_hubungan_keluarga, warga_parent, warga_hubungan_keluarga, foto, dok_ktp, dok_kk, is_ktp_wp, is_delete, created_by/time, updated_by/time)`.
- `warga_mutasi(warga_mutasi_id, warga_id, ref_id_status_aktif, warga_mutasi_tanggal, warga_mutasi_keterangan, is_aktif)`.
- `warga_rumah(warga_rumah_id, warga_id, rumah_id, ref_id_status_rumah, tanggal, is_aktif)`. Hanya untuk KK.
- `rumah(rumah_id, rumah_nomor, rumah_nomor_tampil, ref_id_gang, luas_tanah, luas_bangunan, keterangan, rumah_status, is_aktif)`.
- `rumah_pemilik(rumah_pemilik_id, rumah_id, warga_id NULLABLE, nama/no_hp/alamat/keterangan/tanggal manual, is_aktif)`.
- `koordinator_gang(koordinator_gang_id, ref_id_gang, warga_id, is_aktif)`.
- `referensi(ref_id, ref_kategori, ref_nama, is_aktif, ref_urut)`.
- `users(id, username, password, nama, role)`.
- View `v_warga_usia` untuk hitung usia di detail warga.

Relasi inti: `warga` 1-N `warga_mutasi`; `warga (KK)` 1-N `warga_rumah` N-1 `rumah`; `rumah` 1-N `rumah_pemilik`; `referensi` dipakai sebagai lookup `ref_id_*`; `users` terpisah dari `warga` (tidak ada FK).

## 9. ID Referensi Sakral (Jangan Diubah/Hapus Sembarangan)

- `hubungan_keluarga`: `34` = Kepala Keluarga, `49` = Lainnya.
- `jenis_kelamin`: `50` = Laki-laki, `51` = Perempuan.
- `status_aktif` (mutasi): `161` = Menetap, `162` = Pindah, `163` = Meninggal, `164` = Tidak Tinggal di WP.
- `status_rumah` (hunian): `169` = Milik Sendiri, `170` = Sewa, `171` = Mendiami.
- `gang`: `20` = gang utama tanpa prefix `Gg.`.

Filter status di `index.php`, `dashboard.php`, `grafik.php` memakai subquery `warga_mutasi` dengan ID di atas.

## 10. Daftar Modul

- [Dashboard & Publik](modul-dashboard-publik.md)
- [Auth & User](modul-auth-user.md)
- [Warga](modul-warga.md)
- [Keluarga (Self-service)](modul-keluarga.md)
- [Mutasi](modul-mutasi.md)
- [Perumahan](modul-perumahan.md)
- [Laporan PDF](modul-laporan.md)
- [Referensi](modul-referensi.md)
- [Aset](modul-aset.md)

## 11. Temuan / Utang Teknis (Acuan Perbaikan)

1. `modules/dashboard/grafik_awal.php` legacy: masih `mysqli_real_escape`, path include salah. Jangan dipakai, pakai `grafik.php`.
2. Hapus kategori/item referensi memakai `DELETE` tanpa cek FK — berisiko orphan `ref_id_*`. Perlu konfirmasi + cek pemakaian sebelum hapus.
3. Role `admin` tidak konsisten (ada di `check_auth`, tidak ada di form user).
4. Menu Referensi disembunyikan untuk `operator` padahal backend membolehkan.

## 12. Aturan Kerja Wajib

1. Isi `template-usulan-fitur.md` dulu (latar, modul terdampak, perubahan DB/UI, keamanan).
2. Diskusi dan tandai `Disetujui`.
3. Kerjakan sesuai doc modul terkait.
4. Update doc modul jika perilaku berubah + catat di `CHANGELOG.md`.
