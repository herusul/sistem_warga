# Modul Aset (Barang, Stok, Peminjaman) — MVP

> Kembali ke [SISTEM_WARGA](SISTEM_WARGA.md) | Usulan: [usulan-modul-aset](usulan-modul-aset.md) | Decision: `decisions/2026-09-23-modul-aset-tabel-baru.md`
> Status: **selesai tahap kode MVP 2026-09-23** (11 file `modules/aset/` + menu sidebar; migrasi tabel oleh user via `ddl-database/migrasi-2026-09-23-aset.sql`).

## 1. Tujuan

Mencatat barang/aset RT, jumlah tercatat, kondisi/lokasi, peminjaman barang tetap (dipinjam → kembali), dan pemakaian barang habis pakai. Tanpa approval, tanpa denda, tanpa foto (MVP).

## 2. Akses

- Semua file: `check_auth(['superadmin','operator','admin'])` — pengurus saja.
- Warga (`user/pengguna`) tidak punya akses. Tidak ada halaman publik.

## 3. File (`modules/aset/`, 11 file)

- `aset.php`, `aset_tambah.php`, `aset_edit.php`, `aset_hapus.php`, `aset_detail.php` — master barang.
- `peminjaman.php`, `peminjaman_tambah.php`, `peminjaman_kembali.php`, `peminjaman_detail.php` — pinjam & kembali (barang tetap).
- `pemakaian.php`, `pemakaian_tambah.php` — catat pakai (barang habis pakai).
- Menu sidebar: grup `Aset & Inventaris` di `views/sidebar.php`.

## 4. Tabel

- `aset_barang(aset_id, nama, kategori ENUM tetap/habis_pakai, jumlah_total, kondisi ENUM Baik/Rusak ringan/Rusak berat, lokasi, keterangan, is_aktif, created_by/time, updated_by/time)`. Detail: `ddl-database/tabel-aset-barang.md`.
- `aset_peminjaman(id, aset_id, warga_id NULLABLE, nama_manual/no_hp_manual, jumlah, tgl_pinjam, tgl_rencana_kembali, tgl_kembali NULL, hasil ENUM normal/rusak/hilang NULL + rincian jml_normal/jml_rusak/jml_hilang, is_aktif 1/NULL, audit)`. Hanya barang `tetap`. Detail: `ddl-database/tabel-aset-peminjaman.md`.
- `aset_pemakaian(id, aset_id, jumlah, keperluan, tanggal, dicatat_oleh, audit)`. Hanya barang `habis_pakai`. Detail: `ddl-database/tabel-aset-pemakaian.md`.

## 5. Alur Kerja (untuk diimplementasikan)

Master barang:

1. Tambah: nama, kategori, jumlah awal, kondisi, lokasi.
2. Edit: ubah data master. Jumlah total hanya berubah via kembali rusak/hilang atau catat pakai (bukan edit manual sembarangan).
3. Hapus: hard `DELETE` ditolak jika ada pinjam aktif untuk barang tersebut.

Peminjaman (barang tetap):

1. Tambah: pilih barang `tetap` → jumlah ≤ tersedia (total − pinjam aktif) → peminjam warga terdaftar atau manual (nama/HP wajib jika manual) → tgl pinjam + rencana kembali → `INSERT is_aktif=1`.
2. Kembali: wajib isi tgl kembali + rincian (`jml_normal + jml_rusak + jml_hilang` harus `=` jumlah dipinjam) + keterangan → `UPDATE is_aktif=NULL`.
3. Yang kembali baik otomatis menambah ketersediaan. Hanya `rusak + hilang` yang mengurangi `jumlah_total`. Contoh: pinjam 10 → kembali 8 baik + 2 rusak → total 8, tersedia 8.

Pemakaian (barang habis pakai):

1. Tambah: pilih barang `habis_pakai` → jumlah ≤ total → `INSERT` + `jumlah_total` berkurang permanen. Tidak ada kembali.

## 6. Validasi

- Pinjam/pakai tidak boleh melebihi stok (tersedia/total). Validasi di aplikasi + cek ulang saat submit (antisipasi race).
- Peminjaman hanya untuk kategori `tetap`; pemakaian hanya untuk `habis_pakai` (cek di server, bukan hanya UI).
- Manual luar WP wajib isi nama (+ HP dianjurkan). `warga_id` dan manual tidak boleh terisi dua-duanya.
- Semua `id` URL terenkripsi (`encrypt_id`/`decrypt_id`).

## 7. Non-goals (ditunda)

Approval, denda/ganti rugi otomatis, kartu stok/opname, laporan PDF, foto aset, pengajuan mandiri warga, kategori/kondisi dinamis via `referensi`.

## 8. Catatan implementasi (2026-09-23)

- Hapus barang: ditolak jika ada pinjam aktif; jika tidak, riwayat peminjaman + pemakaian ikut terhapus dalam transaksi (FK `NO ACTION`).
- Stok dihitung di aplikasi (`total − pinjam aktif`); pengurangan total (`rusak/hilang`, catat pakai) memakai transaksi + guard `GREATEST(...)/affected_rows`.
- Migrasi tabel (`CREATE TABLE`) dijalankan user sendiri via `ddl-database/migrasi-2026-09-23-aset.sql`.
- Uji yang dilakukan: `php -l` 12 file lolos; migrasi + 3 skenario stok terverifikasi di DB tes sementara (lalu dihapus).
- Perbaikan 2026-09-23 (pengembalian sebagian): form kembali dipecah jadi 3 angka (baik/rusak/hilang, total wajib = jumlah dipinjam); kolom baru `jml_normal/jml_rusak/jml_hilang`; badge via `badge_hasil_pinjam()` di `includes/functions.php`; migrasi + ALTER disediakan di file migrasi.
