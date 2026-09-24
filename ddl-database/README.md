# Skema Database `wahanapraja` (format .md)

> Sumber kebenaran: `C:/Users/V330/Downloads/skema_sistem_warga.sql`
> Dump: SQLyog Ultimate v12.14, MariaDB 10.4.32, charset `utf8mb4`, DB `wahanapraja`.
> Cara pakai: baca file tabel yang disentuh sebelum ubah query. Jika skema berubah, update file terkait + `docs/CHANGELOG.md`.

## Daftar tabel

| File | Tabel | Status di kode |
|---|---|---|
| `tabel-warga.md` | `warga` | Inti, dipakai semua modul |
| `tabel-warga-mutasi.md` | `warga_mutasi` | Inti (mutasi, dashboard, grafik, laporan) |
| `tabel-warga-rumah.md` | `warga_rumah` | Inti (hunian KK) |
| `tabel-rumah.md` | `rumah` | Inti (perumahan, laporan) |
| `tabel-rumah-pemilik.md` | `rumah_pemilik` | Inti (perumahan) |
| `tabel-koordinator-gang.md` | `koordinator_gang` | Inti (perumahan) |
| `tabel-referensi.md` | `referensi` | Inti (semua dropdown) |
| `tabel-users.md` | `users` | Inti (login pengurus) |
| `tabel-ronda.md` | `ronda`, `ronda_periode` | Legacy, tidak ada modul kode |
| `tabel-warga-isiform.md` | `warga_isiform` | Legacy, tidak ada modul kode |
| `tabel-aset-barang.md` | `aset_barang` | Baru modul aset (tabel fisik dibuat tahap kode) |
| `tabel-aset-peminjaman.md` | `aset_peminjaman` | Baru modul aset (tabel fisik dibuat tahap kode) |
| `tabel-aset-pemakaian.md` | `aset_pemakaian` | Baru modul aset (tabel fisik dibuat tahap kode) |
| `view-function.md` | `v_warga_usia`, `fn_tgl_indo`, `fn_tgl_indo_dgn_hari` | View dipakai detail warga |

## Konvensi lintas tabel

- Soft-delete: `warga.is_delete NULL/1`.
- Aktif riwayat: `is_aktif 1/NULL` (`koordinator_gang, warga_mutasi, warga_rumah, rumah_pemilik`).
- Semua FK `ON DELETE NO ACTION ON UPDATE CASCADE`.
- ID sakral di `referensi`: `34 KK, 49 Lainnya, 50/51 L/P, 161-164 status tinggal, 169-171 status rumah, 20 gang utama`.
