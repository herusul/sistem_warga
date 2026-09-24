# Usulan: Modul Aset (Barang, Stok, Peminjaman) — MVP

> Status: **Disetujui** (PRD final disepakati 2026-09-23, eksekusi tahap dokumen dulu, kode menyusul).
> Decision terkait: `decisions/2026-09-23-modul-aset-tabel-baru.md`.

## Judul

`[Modul Baru] Aset — master barang, peminjaman, pencatatan pakai`

## 1. Latar dan Tujuan

- Masalah: barang/aset RT (tenda, kursi, sound, cat, dll) belum tercatat. Tidak tahu jumlah, kondisi, lokasi, siapa meminjam, dan kapan kembali.
- Tujuan: pengurus mencatat barang, jumlah tercatat, kondisi/lokasi, peminjaman barang tetap (dipinjam → kembali, termasuk status rusak/hilang), dan pemakaian barang habis pakai (mengurangi stok permanen).

## 2. Modul Terdampak

- [ ] Dashboard/Publik
- [ ] Auth/User
- [ ] Warga
- [ ] Keluarga
- [ ] Mutasi
- [ ] Perumahan (rumah / pemilik / hunian / koordinator)
- [ ] Laporan
- [x] Referensi (kemungkinan tambah kategori baru, misal `kondisi_aset`, `kategori_aset` — opsional, bisa enum di kode dulu)
- [x] Lainnya: **modul baru `aset`** + menu sidebar baru.

Doc terkait yang harus dibaca: `docs/SISTEM_WARGA.md`, `docs/modul-aset.md` (baru), `ddl-database/tabel-aset-*.md` (baru).

## 3. Perubahan Data (DB)

- Tabel baru: `aset_barang`, `aset_peminjaman`, `aset_pemakaian` (lihat decision + `ddl-database/`).
- Kolom baru/ubah: tidak ada perubahan tabel lama.
- Migrasi: `CREATE TABLE` 3 tabel baru saja, tanpa migrasi data lama.
- Pengaruh ke `is_delete` / `is_aktif` / `ref_id` sakral: memakai pola `is_aktif 1/NULL` untuk pinjam aktif; tidak menyentuh ID sakral yang ada.

## 4. Perubahan Alur dan UI (tahap kode, menyusul)

- File baru: `modules/aset/` (`aset.php/tambah/edit/hapus/detail`, `peminjaman.php/tambah/kembali/detail`, `pemakaian.php/tambah`) + entri sidebar.
- Alur tambah/edit/hapus: master barang CRUD (hapus ditolak jika ada pinjam aktif); pinjam langsung catat; kembali wajib isi hasil (`normal/rusak/hilang`); pakai khusus habis pakai.
- Validasi: pinjam ≤ tersedia (total − dipinjam aktif); pakai ≤ total; peminjam warga terdaftar atau manual (nama/HP wajib jika manual).
- Role yang boleh akses: `superadmin, operator, admin` (pengurus saja).

## 5. Keamanan

- [ ] `check_auth()` sesuai role (pengurus)
- [ ] CSRF (`csrf_input` / `verify_csrf_token`)
- [ ] Escape output `e()`
- [ ] Prepared statement
- [ ] `encrypt_id` untuk ID di URL
- [ ] Validasi upload — tidak relevan MVP (tanpa foto)

## 6. Uji Coba (tahap kode)

- Skenario 1: tambah barang tetap (10 kursi) → pinjam 4 oleh warga → tersedia 6 → kembali normal → tersedia 10.
- Skenario 2: pinjam 3 → kembali 1 rusak → total berkurang 1, tersedia bertambah 2.
- Skenario 3: barang habis pakai (cat 5 kaleng) → catat pakai 2 → total 3; coba pakai 4 → ditolak.
- Akun uji: superadmin + operator.

## 7. Status

- [x] Draf
- [x] Disetujui (dokumen dulu, kode menyusul)
- [x] Dikerjakan (kode MVP selesai 2026-09-23; migrasi tabel oleh user)
- [x] Doc modul diupdate (`docs/modul-aset.md`)
- [x] CHANGELOG diupdate
