<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['superadmin', 'operator', 'admin']);

$user = $_SESSION['user'];
$role = $user['role'];

if (!isset($_GET['id'])) {
    header("Location: koordinator_gang.php");
    exit;
}

$id = decrypt_id($_GET['id']);

if ($id === false || !is_numeric($id) || (int)$id <= 0) {
    header("Location: koordinator_gang.php");
    exit;
}
$id = (int)$id;

// Ambil data nama gang dengan prepared statement
$sql_gang = "SELECT DISTINCT r.ref_nama AS nama_gang, r.ref_id
             FROM referensi r
             WHERE r.ref_kategori='gang' AND r.ref_id = ?";
$stmt_gang = mysqli_prepare($conn, $sql_gang);
mysqli_stmt_bind_param($stmt_gang, "i", $id);
mysqli_stmt_execute($stmt_gang);
$gang = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_gang));

if (!$gang) {
    header("Location: koordinator_gang.php");
    exit;
}

// Ambil histori koordinator gang dengan prepared statement
$sql_histori = "SELECT kg.*, w.warga_nama,
        IF(kg.is_aktif = 1, 'Aktif', 'Tidak Aktif') AS status_data
    FROM koordinator_gang kg
    LEFT JOIN warga w ON w.warga_id = kg.warga_id
    WHERE kg.ref_id_gang = ?
    ORDER BY kg.is_aktif DESC, kg.created_time DESC";

$stmt_histori = mysqli_prepare($conn, $sql_histori);
mysqli_stmt_bind_param($stmt_histori, "i", $id);
mysqli_stmt_execute($stmt_histori);
$query = mysqli_stmt_get_result($stmt_histori);
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <h4>🧾 Riwayat Koordinator Gang: <?= e($gang['nama_gang']) ?></h4>

    <div class="mb-3">
        <?php if ($role != 'pengguna') : ?>
            <a href="koordinator_gang_tambah.php?ref_id_gang=<?= encrypt_id($gang['ref_id']) ?>" class="btn btn-success">➕ Tambah Koordinator</a>
        <?php endif; ?>
        <a href="koordinator_gang.php" class="btn btn-secondary">🔙 Kembali</a>
    </div>

    <table class="table table-bordered table-striped">
        <thead class="text-center">
            <tr>
                <th>No.</th>
                <th>Nama Koordinator</th>
                <th>Status</th>
                <th>Dibuat Oleh</th>
                <th>Diubah Oleh</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($query) > 0) : ?>
                <?php $no = 1; while ($row = mysqli_fetch_assoc($query)) : ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td><?= e($row['warga_nama']) ?></td>
                        <td class="text-center">
                            <?= $row['is_aktif'] == 1 ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Tidak Aktif</span>' ?>
                        </td>
                        <td><small><?= e($row['created_by']) ?><br><?= e($row['created_time']) ?></small></td>
                        <td><small><?= e($row['updated_by']) ?><br><?= e($row['updated_time']) ?></small></td>
                        <td class="text-center">
                            <?php if ($role != 'pengguna') : ?>
                                <a href="koordinator_gang_edit.php?id=<?= encrypt_id($row['koordinator_gang_id']) ?>" class="btn btn-sm btn-primary">✏️ Edit</a>
                                <a href="koordinator_gang_hapus.php?id=<?= encrypt_id($row['koordinator_gang_id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus data ini?')">🗑️ Hapus</a>
                            <?php else : ?>
                                <span class="text-muted">🔒 Tidak bisa edit</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else : ?>
                <tr><td colspan="6" class="text-center text-muted">Tidak ada data.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../../views/footer.php'; ?>
