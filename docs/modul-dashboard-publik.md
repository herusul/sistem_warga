# Modul Dashboard & Publik

> Kembali ke [SISTEM_WARGA](SISTEM_WARGA.md)

## 1. Tujuan

Menampilkan ringkasan kependudukan dan statistik grafik. Versi publik (`index.php`) bisa dibuka tanpa login. Versi dalam (`dashboard.php`, `grafik.php`) khusus yang sudah login.

## 2. Akses

- `index.php`: tanpa login. Default filter `menetap`.
- `modules/dashboard/dashboard.php`: `check_auth()` — semua role.
- `modules/dashboard/grafik.php`: `check_auth(['superadmin','operator','admin'])`.
- `modules/dashboard/about.php`: `check_auth()` — semua role, halaman statis profil.
- `modules/dashboard/grafik_awal.php`: legacy, jangan dipakai.

## 3. File

- `index.php`: hero + 5 kartu (total, KK, anggota, laki, perempuan) + filter `?status=semua/menetap/pindah/meninggal/tidak_tinggal`.
- `modules/dashboard/dashboard.php`: sama seperti publik tapi default `semua`, plus sapaan hari Bahasa Indonesia dan tombol shortcut.
- `modules/dashboard/grafik.php`: 9 grafik Chart.js + filter status + gang + drill-down AJAX `?action=get_warga_data&chart_type=&label=`.
- `modules/dashboard/about.php`: profil sistem.

## 4. Tabel dan Logika Hitung

Tabel: `warga`, `warga_mutasi`, `referensi`, `rumah`, `warga_rumah`.

Rumus hitung (contoh `dashboard.php:39-43`):

- Laki: `ref_id_jenis_kelamin='50' AND is_delete IS NULL` + filter status.
- Perempuan: `ref_id_jenis_kelamin='51'`.
- KK: `ref_id_hubungan_keluarga='34'`.
- Anggota: `IS NULL OR <> '34'`.

Filter status memakai subquery:

- `menetap`: `warga_mutasi WHERE ref_id_status_aktif=161 AND is_aktif=1`
- `pindah`: `162`, `meninggal`: `163`, `tidak_tinggal`: `164`.

`grafik.php` memakai 9 query agregasi: jenis kelamin, kelompok usia (0-5, 6-12, 13-17, 18-30, 31-45, 46-60, 61+), agama, pendidikan, pekerjaan, status kawin, hubungan keluarga, `is_ktp_wp` (1=Ya, 0=Tidak, 2=Belum), status rumah hunian.

## 5. Alur Kerja

1. Buka publik: pilih filter status → angka dihitung ulang via `getPublicCount()`.
2. Buka dashboard dalam: sama, plus klik kartu untuk lompat ke data warga.
3. Buka grafik: pilih status/gang → 9 chart render → klik batang untuk drill-down tabel warga via AJAX.
4. Tentang: baca info, tidak ada aksi tulis.

## 6. Validasi dan Batasan

- `status` hanya 5 nilai di atas, selain itu dianggap `semua` (tanpa filter).
- Semua hitung selalu menyertakan `is_delete IS NULL`.
- Ekspor grafik tidak ada, hanya tampil. Untuk cetak pakai modul Laporan.

## 7. Catatan untuk Perubahan

- Tambah grafik baru: tambah query agregasi di `grafik.php` + kanvas Chart.js + handler `get_warga_data`. Tulis dulu di usulan fitur: query, label, filter yang didukung.
- `grafik_awal.php` jangan direferensikan ke menu. Jika dibersihkan, hapus file dan pastikan tidak ada link tersisa.
- Perubahan ID sakral (34/50/51/161-164) akan merusak semua angka — dilarang tanpa migrasi.
