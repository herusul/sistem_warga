<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['superadmin', 'operator', 'admin']);

$role = $_SESSION['user']['role'];

if (!isset($_GET['id'])) {
    header("Location: mutasi.php");
    exit;
}

$id = decrypt_id($_GET['id']);

if ($id === false || !is_numeric($id) || (int)$id <= 0) {
    header("Location: mutasi.php");
    exit;
}
$id = (int)$id;

// Ambil data warga dengan prepared statement
$sql_warga = "SELECT warga_nama FROM warga WHERE warga_id = ?";
$stmt_warga = mysqli_prepare($conn, $sql_warga);
mysqli_stmt_bind_param($stmt_warga, "i", $id);
mysqli_stmt_execute($stmt_warga);
$warga = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_warga));

if (!$warga) {
    header("Location: mutasi.php");
    exit;
}

// Ambil riwayat mutasi dengan prepared statement
$sql_mutasi = "SELECT m.*, r.ref_nama, if(m.is_aktif=1, 'Aktif', 'Tidak Aktif') as status_data FROM warga_mutasi m 
    LEFT JOIN referensi r ON r.ref_id = m.ref_id_status_aktif AND r.ref_kategori='status_aktif'
    WHERE m.warga_id = ? ORDER BY m.warga_mutasi_tanggal DESC";

$stmt_mutasi = mysqli_prepare($conn, $sql_mutasi);
mysqli_stmt_bind_param($stmt_mutasi, "i", $id);
mysqli_stmt_execute($stmt_mutasi);
$mutasi_query = mysqli_stmt_get_result($stmt_mutasi);
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>
<div class="container mt-4">
    <h4>🧾 Riwayat Mutasi: <?= e($warga['warga_nama']) ?></h4>

    <div class="mb-3">
		<?php if ($role != 'pengguna') : ?>
			<a href="mutasi_tambah.php?warga_id=<?= encrypt_id($id) ?>" class="btn btn-success">➕ Tambah Data Riwayat</a>
        <?php endif; ?>     
			<a href="mutasi.php" class="btn btn-secondary">🔙 Kembali</a>
    </div>

    <table class="table table-bordered table-striped">
        <thead>
            <tr class="text-center">
                <th>No.</th>
                <th>Status Domisili</th>
                <th>Tanggal Status</th>
                <th>Keterangan</th>
                <th>Status Data</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; while ($m = mysqli_fetch_assoc($mutasi_query)) : ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= e($m['ref_nama']) ?></td>
                    <td><?= e($m['warga_mutasi_tanggal']) ?></td>
                    <td><?= e($m['warga_mutasi_keterangan']) ?></td>
					<td><?= e($m['status_data']) ?></td>

                    <td class="text-center">
						<?php if ($role != 'pengguna') : ?>
							<a href="mutasi_edit.php?id=<?= encrypt_id($m['warga_mutasi_id']) ?>&warga_id=<?= encrypt_id($id) ?>" class="btn btn-sm btn-primary">✏️ Edit</a>
							<a href="mutasi_hapus.php?id=<?= encrypt_id($m['warga_mutasi_id']) ?>&warga_id=<?= encrypt_id($id) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus data ini?')">🗑️ Hapus</a>
                        <?php else : ?>
                            <span class="text-muted">🔒 Tidak bisa edit</span>
                        <?php endif; ?>                    
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php include '../../views/footer.php'; ?>
