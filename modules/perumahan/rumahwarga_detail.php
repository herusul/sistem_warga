<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['superadmin', 'operator', 'admin']);

$role = $_SESSION['user']['role'];

if (!isset($_GET['id'])) {
    header("Location: rumahwarga.php");
    exit;
}

$id = decrypt_id($_GET['id']);

if ($id === false || !is_numeric($id) || (int)$id <= 0) {
    header("Location: rumahwarga.php");
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
    header("Location: rumahwarga.php");
    exit;
}

// Ambil riwayat rumah dengan prepared statement
$sql_mutasi = "SELECT m.*, c.rumah_nomor, r.ref_nama, IF(m.is_aktif=1, 'Aktif', 'Tidak Aktif') AS status_data FROM warga_rumah m 
    LEFT JOIN referensi r ON r.ref_id = m.ref_id_status_rumah AND r.ref_kategori='status_rumah'
    LEFT JOIN rumah c ON c.rumah_id=m.rumah_id
    WHERE m.warga_id = ? ORDER BY status_data, m.warga_rumah_tanggal DESC";

$stmt_mutasi = mysqli_prepare($conn, $sql_mutasi);
mysqli_stmt_bind_param($stmt_mutasi, "i", $id);
mysqli_stmt_execute($stmt_mutasi);
$mutasi_query = mysqli_stmt_get_result($stmt_mutasi);
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>
<div class="container mt-4">
    <h4>🧾 Riwayat Rumah Warga : <?= e($warga['warga_nama']) ?></h4>

    <div class="mb-3">
		<?php if ($role != 'pengguna') : ?>
			<a href="rumahwarga_tambah.php?warga_id=<?= encrypt_id($id) ?>" class="btn btn-success">➕ Tambah Data</a>
        <?php endif; ?>     
			<a href="rumahwarga.php" class="btn btn-secondary">🔙 Kembali</a>
    </div>

    <table class="table table-bordered table-striped">
        <thead>
            <tr class="text-center">
                <th>No.</th>
                <th>Nomor Rumah</th>
                <th>Status Rumah</th>
                <th>Tanggal Mulai Tinggal</th>
                <th>Status Data</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; while ($m = mysqli_fetch_assoc($mutasi_query)) : ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td class="text-center"><?= e($m['rumah_nomor']) ?></td>
                    <td><?= e($m['ref_nama']) ?></td>
                    <td><?= e($m['warga_rumah_tanggal']) ?></td>
					<td><?= e($m['status_data']) ?></td>

                    <td class="text-center">
						<?php if ($role != 'pengguna') : ?>
							<a href="rumahwarga_edit.php?id=<?= encrypt_id($m['warga_rumah_id']) ?>&warga_id=<?= encrypt_id($id) ?>" class="btn btn-sm btn-primary">✏️ Edit</a>
							<a href="rumahwarga_hapus.php?id=<?= encrypt_id($m['warga_rumah_id']) ?>&warga_id=<?= encrypt_id($id) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus data ini?')">🗑️ Hapus</a>
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
