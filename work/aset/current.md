# Current — aset

> Modul: `aset` | Kode: `modules/aset/` (11 file) | Doc: `docs/modul-aset.md` | Usulan: `docs/usulan-modul-aset.md`

## Status

Perbaikan pengembalian sebagian selesai di kode. DB user sudah migrasi v1 (3 tabel ada, tanpa kolom rincian) → user perlu jalankan ALTER sekali (lihat bawah).

## Sedang dikerjakan

- (selesai; tinggal ALTER oleh user + uji ulang manual)

## Langkah berikut

1. User: jalankan ALTER ini di `wahanapraja` (phpMyAdmin > SQL):
   `ALTER TABLE aset_peminjaman ADD COLUMN jml_normal int(11) NOT NULL DEFAULT 0 AFTER hasil, ADD COLUMN jml_rusak int(11) NOT NULL DEFAULT 0 AFTER jml_normal, ADD COLUMN jml_hilang int(11) NOT NULL DEFAULT 0 AFTER jml_rusak;`
2. User: uji pinjam 10 kursi → kembali 8 baik + 2 rusak → total 8, tersedia 8.

## Blocker

- Migrasi tabel di MySQL (oleh user).

## Catatan

- PRD final: barang tetap + habis pakai; peminjam warga/manual; tanpa approval/denda/foto.
- Semantik stok: kembali rusak/hilang dan catat pakai mengurangi `jumlah_total`.
- Insiden validasi: file migrasi awal mengandung `USE wahanapraja` sehingga sempat membuat 3 tabel di DB asli; tabel sudah di-DROP, DB asli bersih (0 tabel `aset%`), file migrasi diperbaiki (tanpa `USE`).
