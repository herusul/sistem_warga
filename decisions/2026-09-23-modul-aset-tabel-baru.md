# Decision: Tabel baru modul aset (`aset_barang`, `aset_peminjaman`, `aset_pemakaian`)

## Context

Modul aset adalah modul baru (PRD final 2026-09-23, lihat `docs/usulan-modul-aset.md`). Tahap MVP: master barang (tetap + habis pakai), peminjaman barang tetap (dipinjam → kembali dengan status normal/rusak/hilang), pencatatan pakai barang habis pakai. Tidak ada tabel lama yang cocok (pola `rumah_pemilik` hanya untuk rumah). Perlu tabel baru tanpa mengubah skema lama.

## Decision

Buat 3 tabel baru (detail di `ddl-database/tabel-aset-barang.md`, `tabel-aset-peminjaman.md`, `tabel-aset-pemakaian.md`):

1. `aset_barang`: master (nama, kategori `tetap/habis_pakai`, jumlah_total, kondisi, lokasi, keterangan, `is_aktif`, audit).
2. `aset_peminjaman`: riwayat pinjam barang tetap; `warga_id` NULLABLE + kolom manual (nama/HP) untuk luar WP; `hasil` (`normal/rusak/hilang`); `is_aktif 1/NULL` (1 = sedang dipinjam).
3. `aset_pemakaian`: catat pakai barang habis pakai (mengurangi `jumlah_total` permanen).

Semantik stok: tersedia = total − pinjam aktif; kembali normal mengembalikan ketersediaan; kembali rusak/hilang dan catat pakai mengurangi `jumlah_total`. Hapus barang ditolak jika ada pinjam aktif.

## Alternatives Considered

- Satu tabel generik dengan kolom `tipe_transaksi`: ditolak, karena peminjaman (ada kembali) dan pemakaian (tanpa kembali) punya kolom dan validasi berbeda; tabel terpisah lebih jelas dan ikut pola proyek (`warga_mutasi` vs `warga_rumah` terpisah).
- Pakai tabel `referensi` untuk kondisi/kategori: ditunda ke tahap berikut; MVP memakai ENUM di DB agar modul langsung jalan tanpa seeding referensi.

## Consequences

- Migrasi hanya `CREATE TABLE`, tanpa sentuh tabel lama; risiko regresi nol ke modul lain.
- Jika nanti butuh kategori/kondisi dinamis, migrasi ENUM → `referensi` memerlukan decision baru.
- Kode wajib validasi stok di aplikasi (tidak ada constraint CHECK lintas tabel di MariaDB 10.4 yang diandalkan).

## Related Modules

aset (baru). Tidak mengubah dashboard-publik, auth-user, warga, keluarga, mutasi, perumahan, laporan, referensi.

## Related Files

- `docs/usulan-modul-aset.md`, `docs/modul-aset.md`
- `ddl-database/tabel-aset-barang.md`, `tabel-aset-peminjaman.md`, `tabel-aset-pemakaian.md`
- `modules/aset/` (tahap kode, menyusul)

## Date

2026-09-23

## Amandemen 2026-09-23: rincian pengembalian sebagian

Konteks: form kembali awal hanya menerima satu status untuk seluruh jumlah pinjaman
(pinjam 10, 2 rusak → 8 yang baik tidak kembali ke stok). Keputusan: tambah kolom
`jml_normal`, `jml_rusak`, `jml_hilang` (total wajib = jumlah dipinjam); hanya
rusak+hilang yang mengurangi `jumlah_total`; `hasil` menjadi ringkasan
(hilang > rusak > normal). Migrasi awal belum dijalankan user sehingga CREATE
diperbarui langsung + disediakan ALTER untuk yang sudah terlanjur migrasi v1.
