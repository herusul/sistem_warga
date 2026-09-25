# Current — warga

> Modul: `warga` | Kode: `modules/warga/` | Doc: `docs/modul-warga.md`

## Status

Selesai 2026-09-25: layout warga_edit.php disamakan ke warga_tambah.php.

## Sedang dikerjakan

- (kosong)

## Langkah berikut

- Uji manual sebagai role user: buka pencarian (harus kosong + pesan filter), isi nama/rumah/gang/status → Cari → cek kolom Status Keluarga `(KK : ...)`, order grouping KK, pagination bawa `&status=`.
- Tunggu instruksi user.

## Blocker

- Tidak ada.

## Catatan

- Hapus = soft-delete `is_delete=1`. NIK unik. Ganti status/rumah lewat modul mutasi/perumahan, bukan edit warga.
