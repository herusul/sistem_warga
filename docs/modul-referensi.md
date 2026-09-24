# Modul Referensi (Lookup Master)

> Kembali ke [SISTEM_WARGA](SISTEM_WARGA.md)

## 1. Tujuan

Kelola semua dropdown sistem: agama, jenis kelamin, golongan darah, pendidikan, pekerjaan, status kawin, hubungan keluarga, status aktif, status rumah, gang.

## 2. Akses

- Backend: `check_auth(['admin','superadmin','operator'])`.
- UI (`sidebar.php`): menu hanya tampil untuk `superadmin`. Ini inkonsistensi yang disengaja didokumentasikan — jangan ubah tanpa keputusan.

## 3. File

- `modules/referensi/referensi.php`: list per `?kategori=` (default `agama`) + hitung jumlah per kategori + form tambah kategori.
- `referensi_tambah.php`, `referensi_edit.php`, `referensi_hapus.php`: CRUD item.
- `referensi_kategori_tambah.php`, `referensi_kategori_hapus.php`: tambah/hapus satu kategori sekaligus.

## 4. Tabel

`referensi(ref_id, ref_kategori, ref_nama, is_aktif 1/0, ref_urut)`. Dropdown di modul lain filter `is_aktif=1`.

## 5. Alur Kerja

Tambah item:

1. Pilih kategori → isi nama → cek duplikat `(kategori,nama)` → `INSERT`.

Edit item:

1. Via `?id=encrypt` → cek duplikat → `UPDATE`.

Hapus item:

1. Hard `DELETE WHERE ref_id`. Tanpa cek FK.

Tambah kategori:

1. Isi nama kategori baru → insert dummy `('Contoh Isi',0)`. Blacklist nama: `id, admin, user, sistem, login`, dsb.

Hapus kategori:

1. Hard `DELETE WHERE ref_kategori`. Tanpa cek FK. Berisiko orphan.

## 6. Validasi dan Risiko

- ID sakral dilarang diubah/hapus: `34, 49, 50, 51, 161-164, 169-171, 20`. Mengubahnya merusak modul Warga, Mutasi, Perumahan, Dashboard, Grafik, Laporan.
- Hapus kategori/item tidak memeriksa pemakaian di `warga/mutasi/rumah`. Wajib cek manual sebelum hapus.
- Tambah kategori baru belum otomatis muncul di form. Harus coding form yang memakai kategori tersebut.

## 7. Catatan untuk Perubahan

- Setiap tambah kategori baru wajib tulis: nama kategori, modul yang memakai, default `is_aktif`, butuh migrasi atau tidak.
- Usulan pengaman hapus (cek FK + tolak jika dipakai) harus ditulis dulu di template usulan fitur.
