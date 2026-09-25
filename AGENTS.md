# AGENTS.md — Aturan Kerja Agen di `sistem_warga`

> Baca file ini dulu sebelum mengerjakan apapun. Bahasa: Indonesia.
> Prinsip utama: **tanya modul dulu, baca acuan, tulis rencana dulu, baru coding.**

## 1. Workflow Wajib (Urutan Kerja)

### Langkah 1 — Tanya modul dulu (sebelum sentuh kode)

Sebelum membuat kode, tanyakan ke user:

1. `Kerja di modul mana?` Pilihan: `dashboard-publik, auth-user, warga, keluarga, mutasi, perumahan, laporan, referensi, aset`.
2. Kalau modul yang diminta **belum ada**, jangan langsung coding. Buatkan dulu **rencana modul**: tujuan, file yang akan dibuat, tabel DB, role akses, alur CRUD. Simpan rencana di `docs/` (usulan fitur) dan `work/<modul-baru>/current.md`, minta persetujuan user.

### Langkah 2 — Lihat folder `work/` sesuai modul

- Baca `work/<modul>/current.md` → apa yang **sedang** dikerjakan, langkah berikut, blocker.
- Baca `work/<modul>/handsoff.md` → apa yang **telah** dikerjakan, file yang diubah, status, tindak lanjut.
- Jika `current.md` kosong dan tidak ada tugas aktif, tanyakan user mau mulai dari mana.
- Setiap selesai sesi kerja: update `current.md` dan tambah baris di `handsoff.md`.
- File `current.md` yang sudah usang/selesai **jangan dihapus**: pindahkan isinya ke `work/<modul>/history/YYYY-MM-DD-topik.md`, lalu tulis `current.md` baru. Format nama: `YYYY-MM-DD-judul-singkat.md`.

### Langkah 3 — Baca acuan (jangan mengarang)

- `docs/SISTEM_WARGA.md` (umum) + doc modul terkait di `docs/modul-*.md`.
- Jika menyentuh database: baca `ddl-database/` (skema per tabel, sumber: `skema_sistem_warga.sql`).
- Jika ada keputusan arsitektur/bisnis: cek `decisions/` (format `YYYY-MM-DD-nama.md`).
- Perubahan atau fitur baru: isi `docs/template-usulan-fitur.md` dulu → status Disetujui → baru kerjakan → update doc modul + `docs/CHANGELOG.md`.

### Langkah 4 — Skills (ditunda)

- Status saat ini: **nanti saja** (keputusan user 2026-09-23). Jangan install skill baru tanpa persetujuan eksplisit.
- Jika butuh skill khusus di kemudian hari, ajukan dulu ke user (nama skill, untuk apa), baru gunakan setelah disetujui.

### Langkah 5 — Standar kode proyek ini

- PHP native + `mysqli` prepared statement. Dilarang query interpolasi langsung.
- Output selalu `e()`. Form POST selalu CSRF (`csrf_input` / `verify_csrf_token`).
- ID di URL selalu `encrypt_id` / `decrypt_id`. Gagal decrypt = redirect ke list.
- Konvensi data: warga soft-delete (`is_delete NULL/1`), riwayat aktif (`is_aktif 1/NULL`, bukan 0).
- ID referensi sakral jangan diubah: `34 KK, 49 Lainnya, 50/51 L/P, 161-164 status tinggal, 169-171 status rumah, 20 gang utama`.
- Upload: foto `jpg/jpeg/png`, dokumen `pdf`, maks 5MB, nama acak ke `assets/uploads/`.
- Timeout sesi 300 detik (`includes/auth.php`). Jangan ubah tanpa decision.

## 2. Struktur Folder Acuan

```
sistem_warga/
  AGENTS.md                  # file ini
  docs/                      # acuan umum + per modul + template usulan + changelog
  ddl-database/              # skema DB per tabel dalam .md (sumber: skema_sistem_warga.sql)
  decisions/                 # keputusan tahan lama, format YYYY-MM-DD-nama.md
  work/<modul>/              # current.md (sedang) + handsoff.md (telah) + history/ (usang)
  modules/                   # kode: dashboard, warga, keluarga, mutasi, perumahan, laporan, referensi, user, aset
  includes/ views/ libs/ assets/
```

Pemetaan modul ↔ kode ↔ docs ↔ work:

| Modul `work/` | Kode `modules/` | Doc `docs/` |
|---|---|---|
| `dashboard-publik` | `dashboard/` + `index.php` | `modul-dashboard-publik.md` |
| `auth-user` | `user/` + `login.php, google_auth.php` | `modul-auth-user.md` |
| `warga` | `warga/` | `modul-warga.md` |
| `keluarga` | `keluarga/` | `modul-keluarga.md` |
| `mutasi` | `mutasi/` | `modul-mutasi.md` |
| `perumahan` | `perumahan/` | `modul-perumahan.md` |
| `laporan` | `laporan/` | `modul-laporan.md` |
| `referensi` | `referensi/` | `modul-referensi.md` |
| `aset` | `aset/` | `modul-aset.md` |

## 3. Aturan `decisions/`

- Hanya untuk keputusan tahan lama (arsitektur, skema, bisnis). Bukan untuk progress harian (itu di `work/`).
- Nama: `YYYY-MM-DD-nama-singkat.md`. Struktur: Context, Decision, Alternatives Considered, Consequences, Related Modules, Related Files, Date (lihat `decisions/README.md`).

## 4. Aturan `ddl-database/`

- Satu file per tabel + satu file view/function. Sumber kebenaran: `C:/Users/V330/Downloads/skema_sistem_warga.sql` (dump `wahanapraja`, MariaDB 10.4.32).
- Jika skema berubah, update file `.md` terkait + catat di `docs/CHANGELOG.md` + tulis decision jika perubahan struktural.
- Tabel `ronda`, `ronda_periode`, `warga_isiform` ada di DB tapi **tidak ada modul kode** — perlakukan sebagai legacy/nonaktif, jangan dipakai fitur baru tanpa decision.

## 5. Aturan Git & Database (Wajib Konfirmasi User)

- Dilarang melakukan operasi git (`pull, commit, push, merge, rebase, checkout, reset, stash`, dll.) tanpa konfirmasi eksplisit dari user. Boleh `status, diff, log` (read-only) untuk inspeksi.
- Dilarang melakukan apapun langsung ke database (query `INSERT/UPDATE/DELETE/ALTER`, migrasi, impor, dsb.) tanpa konfirmasi eksplisit dari user. Boleh membaca skema via `ddl-database/` dan `SELECT` read-only untuk verifikasi bila diperlukan.
- Setiap aksi git/database yang diusulkan harus menyebut perintah/file yang akan dijalankan dan menunggu persetujuan user dulu.

## 6. Checklist Sebelum Selesai Sesi

- [ ] `work/<modul>/current.md` diupdate?
- [ ] `work/<modul>/handsoff.md` ditambah entri sesi?
- [ ] `current.md` usang sudah diarsip ke `history/`?
- [ ] Doc modul + `CHANGELOG.md` diupdate jika perilaku berubah?
- [ ] Decision ditulis jika ada keputusan struktural?
