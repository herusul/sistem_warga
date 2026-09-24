# Decision: Struktur kerja AGENTS.md + docs + ddl-database + work + decisions

## Context

Proyek `sistem_warga` sebelumnya hanya punya dokumentasi `docs/` (umum + 8 modul). Belum ada aturan kerja agen, skema DB terdokumentasi, tempat keputusan tahan lama, dan pelacakan kerja per modul. Skema asli tersedia sebagai `skema_sistem_warga.sql` (MariaDB 10.4.32, DB `wahanapraja`).

## Decision

1. Tambahkan `AGENTS.md` di root sebagai aturan kerja wajib (tanya modul dulu, cek `work/`, baca `docs/`, tulis usulan dulu baru coding).
2. Konversi `skema_sistem_warga.sql` ke `ddl-database/*.md` (satu file per tabel + view/function).
3. Tambahkan `decisions/` untuk keputusan tahan lama dengan format `YYYY-MM-DD-nama.md`.
4. Tambahkan `work/<8-modul>/current.md + handsoff.md + history/` untuk lacak kerja berjalan vs selesai.
5. Skills ditunda (keputusan user 2026-09-23).

## Alternatives Considered

- Satu file dokumentasi besar: ditolak, sulit dirawat dan konflik merge.
- Skema DB hanya sebagai `.sql` mentah: ditolak, agen butuh penjelasan kolom + pemakaian di kode dalam `.md`.
- Satu `work/current.md` global: ditolak, tidak skalabel untuk 8 modul paralel.

## Consequences

- Setiap sesi kerja wajib update `work/<modul>/current.md` + `handsoff.md`; arsip usang ke `history/`.
- Perubahan skema wajib update `ddl-database/` + `docs/CHANGELOG.md`.
- Keputusan struktural wajib jadi file `decisions/`.

## Related Modules

Semua modul (dashboard-publik, auth-user, warga, keluarga, mutasi, perumahan, laporan, referensi).

## Related Files

- `AGENTS.md`
- `docs/SISTEM_WARGA.md`
- `ddl-database/README.md`
- `work/*/current.md`, `work/*/handsoff.md`

## Date

2026-09-23
