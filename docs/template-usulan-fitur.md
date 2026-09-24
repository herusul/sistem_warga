# Template Usulan Fitur / Perubahan

> Wajib diisi sebelum coding. Salin blok di bawah ke issue / catatan kerja, isi, tandai Disetujui, baru kerjakan.

## Judul

`[Modul] ...`

## 1. Latar dan Tujuan

- Masalah:
- Tujuan:

## 2. Modul Terdampak

- [ ] Dashboard/Publik
- [ ] Auth/User
- [ ] Warga
- [ ] Keluarga
- [ ] Mutasi
- [ ] Perumahan (rumah / pemilik / hunian / koordinator)
- [ ] Laporan
- [ ] Referensi
- [ ] Lainnya: ...

Doc terkait yang harus dibaca: ...

## 3. Perubahan Data (DB)

- Tabel:
- Kolom baru/ubah:
- Migrasi:
- Pengaruh ke `is_delete` / `is_aktif` / `ref_id` sakral:

## 4. Perubahan Alur dan UI

- File diubah:
- Alur tambah/edit/hapus:
- Validasi:
- Role yang boleh akses:

## 5. Keamanan

- [ ] `check_auth()` sesuai role
- [ ] CSRF (`csrf_input` / `verify_csrf_token`)
- [ ] Escape output `e()`
- [ ] Prepared statement
- [ ] `encrypt_id` untuk ID di URL
- [ ] Validasi upload (tipe/ukuran) jika ada file

## 6. Uji Coba

- Skenario 1:
- Skenario 2:
- Akun uji (superadmin/operator/user):

## 7. Status

- [ ] Draf
- [ ] Disetujui
- [ ] Dikerjakan
- [ ] Doc modul diupdate
- [ ] CHANGELOG diupdate
