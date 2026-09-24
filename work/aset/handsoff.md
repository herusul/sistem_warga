# Handsoff — aset

| Tanggal | Yang dikerjakan | File diubah | Status | Tindak lanjut |
|---|---|---|---|---|
| 2026-09-23 | Brainstorming + PRD final modul aset (7+4 pertanyaan, semantik stok) | — (diskusi) | Selesai | Susun dokumen |
| 2026-09-23 | Tahap dokumen: usulan terisi, decision tabel baru, `modul-aset.md`, 3 skema ddl, `work/aset/`, update AGENTS/SISTEM_WARGA/CHANGELOG | `docs/usulan-modul-aset.md`, `docs/modul-aset.md`, `decisions/2026-09-23-modul-aset-tabel-baru.md`, `ddl-database/tabel-aset-*.md`, `work/aset/`, `AGENTS.md`, `docs/SISTEM_WARGA.md`, `docs/CHANGELOG.md` | Selesai | Tahap kode |
| 2026-09-23 | Tahap kode MVP: 11 file `modules/aset/`, menu sidebar, `php -l` lolos, migrasi + 3 skenario stok terverifikasi di DB tes (dibersihkan); file migrasi final untuk user | `modules/aset/*.php`, `views/sidebar.php`, `ddl-database/migrasi-2026-09-23-aset.sql` | Selesai | Migrasi tabel oleh user |
| 2026-09-23 | Perbaikan pengembalian sebagian: kolom `jml_normal/rusak/hilang`, form kembali 3 angka, badge `badge_hasil_pinjam()`, ALTER disediakan; skenario 10→8+2 terverifikasi di DB tes | `modules/aset/peminjaman_kembali.php`, `peminjaman.php`, `peminjaman_detail.php`, `aset_detail.php`, `includes/functions.php`, `ddl-database/migrasi-2026-09-23-aset.sql`, `tabel-aset-peminjaman.md`, `docs/modul-aset.md`, `decisions/2026-09-23-modul-aset-tabel-baru.md` | Selesai | ALTER oleh user (DB masih v1) |
