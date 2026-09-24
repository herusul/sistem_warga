# Modul Mutasi (Riwayat Domisili)

> Kembali ke [SISTEM_WARGA](SISTEM_WARGA.md)

## 1. Tujuan

Mencatat riwayat status tinggal per warga. Satu warga hanya punya satu baris aktif.

## 2. Akses

Semua file: `check_auth(['superadmin','operator','admin'])`.

## 3. File

- `modules/mutasi/mutasi.php`: list per warga yang aktif (`is_aktif=1`) + cari nama + pagination 10.
- `mutasi_detail.php`: riwayat `ORDER BY tanggal DESC` + badge Aktif/Tidak Aktif.
- `mutasi_tambah.php?warga_id=`: tambah status baru.
- `mutasi_edit.php?id=&warga_id=`: ubah tanggal/keterangan/status aktif.
- `mutasi_hapus.php`: hard `DELETE`, konfirmasi GET + eksekusi POST + CSRF.

## 4. Tabel

- `warga_mutasi(warga_mutasi_id, warga_id, ref_id_status_aktif, warga_mutasi_tanggal, warga_mutasi_keterangan, is_aktif)`.
- `referensi kategori=status_aktif`: `161 Menetap, 162 Pindah, 163 Meninggal, 164 Tidak Tinggal`.
- `warga`: untuk nama dan filter `is_delete IS NULL`.

Penting: `is_aktif` memakai `1 vs NULL`, bukan `0`.

## 5. Alur Kerja

Tambah:

1. Dari list warga/mutasi dengan `warga_id` terenkripsi.
2. `UPDATE warga_mutasi SET is_aktif=NULL WHERE warga_id=?` → `INSERT ... is_aktif=1`.

Edit:

1. Pilih radio Aktif/Tidak Aktif. Jika set Aktif → nonaktifkan baris lain dulu → `UPDATE` baris ini `is_aktif=1`.
2. Jika set nonaktif → `UPDATE ... is_aktif=NULL`. Hati-hati: warga bisa tanpa status aktif.

Hapus:

1. Satu-satunya hard-delete di area warga. Pastikan benar-benar duplikat/salah input sebelum hapus.

## 6. Validasi

- Tambah/edit wajib `warga_id` valid dan warga belum soft-delete.
- Tanggal tidak boleh kosong. Keterangan opsional.
- List utama hanya tampil yang `is_aktif=1`.

## 7. Catatan untuk Perubahan

- Filter status dashboard/publik/grafik bergantung pada tabel ini. Perubahan arti `161-164` wajib migrasi + update 3 modul tersebut.
- Jika ingin cegah warga tanpa status aktif, tambah validasi di edit (saat ini boleh).
- Usulan fitur baru (misal mutasi massal) wajib jelaskan cara menjaga 1 aktif per warga.
