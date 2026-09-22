<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

// Otorisasi: Hanya superadmin yang boleh mengakses
check_auth(['superadmin']);

$data = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");

// Notifikasi
$status_msg = '';
if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'tambah_sukses': $status_msg = '<div class="alert alert-success">Pengguna baru berhasil ditambahkan.</div>'; break;
        case 'edit_sukses': $status_msg = '<div class="alert alert-success">Data pengguna berhasil diperbarui.</div>'; break;
        case 'hapus_sukses': $status_msg = '<div class="alert alert-success">Pengguna berhasil dihapus.</div>'; break;
        case 'hapus_diri_gagal': $status_msg = '<div class="alert alert-danger">Anda tidak dapat menghapus akun Anda sendiri.</div>'; break;
        case 'id_error': $status_msg = '<div class="alert alert-danger">Terjadi kesalahan: ID pengguna tidak valid.</div>'; break;
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <h4>👤 Manajemen Pengguna</h4>
        <a href="user_tambah.php" class="btn btn-success">➕ Tambah Pengguna</a>
    </div>
    <hr>
    
    <?= $status_msg ?>

    <table class="table table-bordered table-striped table-hover">
        <thead>
            <tr class="text-center">
                <th>No</th>
                <th>Username</th>
                <th>Nama Lengkap</th>
                <th>Role</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php $no=1; while($u = mysqli_fetch_assoc($data)) : ?>
        <tr>
            <td class="text-center"><?= $no++ ?></td>
            <td><?= e($u['username']) ?></td>
            <td><?= e($u['nama']) ?></td>
            <td><?= e(ucfirst($u['role'])) ?></td>
            <td class="text-center">
                <a href="user_edit.php?id=<?= encrypt_id($u['id']) ?>" class="btn btn-sm btn-primary">✏️ Edit</a>
                <?php if ($u['id'] != $_SESSION['user']['id']) : // Tombol hapus tidak muncul untuk user sendiri ?>
                    <form action="user_hapus.php" method="post" style="display:inline-block;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?')">
                        <?= csrf_input() ?>
                        <input type="hidden" name="id" value="<?= encrypt_id($u['id']) ?>">
                        <button class="btn btn-sm btn-danger">🗑️ Hapus</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include '../../views/footer.php'; ?>
