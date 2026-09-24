# Modul Laporan PDF

> Kembali ke [SISTEM_WARGA](SISTEM_WARGA.md)

## 1. Tujuan

Cetak resmi data warga dalam PDF siap cetak. Tidak ada tambah/edit/hapus, hanya unduh.

## 2. Akses

- `modules/laporan/daftar_laporan.php`: `check_auth(['superadmin','operator','admin'])`.
- Tiga file ekspor sama, langsung `Output('D', ...)` (download).

## 3. File

- `daftar_laporan.php`: 3 kartu: Daftar KK, Seluruh Warga, Statistik Penghuni.
- `export_pdf_laporan_nama_kk.php`: daftar KK per rumah (`nomor, gang, nama KK, HP`).
- `export_pdf_laporan_nama_warga.php`: seluruh warga (KK + anggota).
- `export_pdf_laporan_nama_kk_jumlah_penghuni.php`: KK + `COUNT` penghuni + baris `JUMLAH TOTAL`.

Teknis: `libs/tfpdf/tfpdf.php` (font DejaVu), `ob_start/ob_end_clean`, `set_time_limit(300)`.

## 4. Tabel

`rumah` + `referensi.gang` + `warga` + `warga_rumah` + `warga_mutasi(ref=161 Menetap)`. Laporan umumnya hanya data menetap aktif dan `is_delete IS NULL`.

## 5. Alur Kerja

1. Buka `daftar_laporan.php`.
2. Klik `Unduh PDF` sesuai kebutuhan.
3. File langsung terunduh, tidak tersimpan di server.

## 6. Validasi dan Batasan

- Data besar memakai `set_time_limit(300)`. Jangan hilangkan `ob_end_clean` agar PDF tidak rusak.
- Pastikan font DejaVu tersedia di `libs/tfpdf/font/unifont/`.

## 7. Catatan untuk Perubahan

- Laporan baru: duplikasi salah satu file ekspor + tambah kartu di `daftar_laporan.php`. Tulis dulu: sumber tabel, filter (status/gang), kolom, kop surat.
- Perubahan kop/header/footer harus diterapkan ke ketiga file agar konsisten.
