# Tabel `users`

> Sumber: `skema_sistem_warga.sql` baris 149-161. `AUTO_INCREMENT=6`.

```sql
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','operator','user','pengguna') DEFAULT 'user',
  `nama` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
);
```

## Catatan

- `password` = hash (`password_hash` / `password_verify` di `login.php`, `user_tambah.php`).
- Enum DB memuat `user` dan `pengguna` (keduanya dianggap sama di `check_auth()`).
- Role `admin` **tidak ada** di enum maupun form — tapi masih diterima di `check_auth([...,'admin'])` modul lama. Jangan tambah role baru tanpa ubah enum + audit `check_auth`.
- Terpisah dari `warga` (tidak ada FK). Login warga memakai `warga.warga_email`, bukan tabel ini.
