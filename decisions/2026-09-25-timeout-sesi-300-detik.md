# Decision: Timeout Sesi 300 Detik (5 Menit)

## Context

Timeout inaktivitas sesi sebelumnya 180 detik (3 menit) dinilai terlalu singkat oleh user/pengurus — sesi sering kedaluwarsa saat mengisi form panjang (tambah/edit warga, laporan). Permintaan perubahan datang langsung dari user pada 2026-09-25.

## Decision

Timeout inaktivitas sesi diubah dari **180 detik → 300 detik (5 menit)**, seragam di tiga titik:

- `includes/auth.php` (`$timeout_duration`, pesan JSON 401).
- `session_keepalive.php` (`$timeout_limit`, pesan JSON 401).
- `views/session_timeout_modal.php` (`CONFIG.TOTAL_TIMEOUT`, komentar detik ke-270).

Peringatan modal tetap 30 detik sebelum habis; keep-alive otomatis tiap 90 detik tidak berubah (tetap < 300 sehingga sesi server tidak kedaluwarsa saat pengguna aktif).

## Alternatives Considered

- Tetap 180 detik + andalkan tombol "Perpanjang Sesi": ditolak user, terlalu mengganggu alur kerja.
- 600 detik (10 menit): ditolak, memperbesar jendela risiko sesi terbengkalai di komputer bersama.

## Consequences

- Pengguna idle hingga 5 menit sebelum dipaksa login ulang; risiko sesi terbengkalai sedikit naik, masih wajar untuk aplikasi RT internal.
- Pesan error menyebut "5 menit"; doc acuan (`SISTEM_WARGA.md`, `modul-auth-user.md`, `AGENTS.md`) ikut diperbarui.
- Perubahan lanjutan pada angka ini wajib decision baru.

## Related Modules

- `auth-user`

## Related Files

- `includes/auth.php`
- `session_keepalive.php`
- `views/session_timeout_modal.php`

## Date

- 2026-09-25
