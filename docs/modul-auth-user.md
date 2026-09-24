# Modul Auth & User

> Kembali ke [SISTEM_WARGA](SISTEM_WARGA.md)

## 1. Tujuan

Dua jalur login yang terpisah: pengurus (username/password di tabel `users`) dan warga (Google OAuth, kunci `warga_email`). Modul `user/` hanya untuk kelola akun pengurus.

## 2. Akses

- `login.php`, `google_auth.php`, `captcha_image.php`: tanpa login.
- `logout.php`: memutus sesi.
- `modules/user/user.php`, `user_tambah.php`, `user_edit.php`, `user_hapus.php`: `check_auth(['superadmin'])` saja.

## 3. File

- `login.php`: form ganda. Atas: tombol Google GIS (jika `is_google_auth_configured()`), bawah: username/password/captcha. Jika sudah `$_SESSION['user']` langsung ke dashboard.
- `includes/google_auth_config.php`: `GOOGLE_CLIENT_ID`, helper `is_google_auth_configured()`, `is_local_dev_environment()`.
- `google_auth.php`: terima credential JWT → verifikasi `oauth2/tokeninfo` → cek `email_verified` → cari `LOWER(warga_email)` aktif → isi session `id, warga_id, role=user, nama, email`. Gagal → redirect `login.php?error=...`.
- `captcha_image.php`: gambar kode, simpan `$_SESSION['captcha_text']`.
- `includes/auth.php`: `check_auth()`, timeout 180 detik, cookie OWASP, alias `user/pengguna`.
- `modules/user/user.php`: tabel `users` + status `tambah_sukses/edit_sukses/hapus_sukses/hapus_diri_gagal/id_error`.

## 4. Tabel

- `users(id, username UNIQUE, password HASH, nama, role)`. Pilihan role di form: `superadmin/operator/user`.
- `warga(warga_id, warga_nama, warga_email, is_delete)`: sumber login Google. Email harus unik dan terisi oleh pengurus dulu.

## 5. Alur Kerja

Login pengurus:

1. Isi username, password, captcha → POST → `verify_csrf_token()`.
2. Cek captcha sama dengan session → cari username via prepared → `password_verify()`.
3. Berhasil: `session_regenerate_id(true)`, isi `$_SESSION['user']`, `last_activity`, `login_time` → dashboard.

Login warga:

1. Klik Google → `google_auth.php` terima token.
2. Verifikasi ke Google, ambil email.
3. Cari warga aktif dengan email tersebut. Ketemu → session `role=user` + `warga_id`. Tidak ketemu → `google_not_registered`.
4. Dev mode lokal: pilih email warga dari dropdown `dev_google_email` (maks 6 contoh).

Kelola user (superadmin):

1. Tambah: cek username belum dipakai, password min 6, `password_hash(PASSWORD_DEFAULT)`.
2. Edit: password opsional, kosong = tidak diubah. Username tetap unik.
3. Hapus: POST + CSRF, hard `DELETE`, dilarang hapus diri sendiri.

## 6. Validasi

- Username/password wajib isi, captcha case-sensitive harus sama persis.
- Password min 6 karakter saat tambah user.
- Email Google harus `email_verified` dan sudah terdaftar di `warga`.

## 7. Catatan untuk Perubahan

- Role `admin` masih lolos `check_auth` di modul lain tapi tidak bisa dibuat dari form. Jika ingin hapus `admin`, audit dulu semua `check_auth` yang menyebutnya.
- Menambah metode login baru wajib pakai `session_regenerate_id`, set `last_activity`, dan patuhi cookie OWASP di `auth.php`.
- Jangan gabungkan `users` dan `warga` tanpa migrasi: saat ini keduanya terpisah dan session mengandalkan `warga_id` untuk self-service keluarga.
