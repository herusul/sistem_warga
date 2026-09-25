# Current — auth-user

> Modul: `auth-user` | Kode: `modules/user/` + `login.php, google_auth.php, logout.php` | Doc: `docs/modul-auth-user.md`

## Status

Selesai 2026-09-25: timeout sesi 180→300 detik + decision.

## Sedang dikerjakan

- (kosong)

## Langkah berikut

- Uji manual: diam 4,5 menit → modal peringatan muncul; diam 5+ menit → redirect login `?timeout=1`. Tombol Perpanjang Sesi + keep-alive 90 detik tetap jalan.
- Tunggu instruksi user.

## Blocker

- Tidak ada.

## Catatan

- Inkonsistensi role `admin` (ada di `check_auth`, tidak ada di form/enum) — jangan ubah tanpa decision.
