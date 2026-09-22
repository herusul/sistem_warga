<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['superadmin', 'operator', 'admin']);

$user = $_SESSION['user'];
$role = $user['role'];

if (!isset($_GET['id'])) {
    header("Location: pemilik_rumah.php");
    exit;
}

$id = decrypt_id($_GET['id']);

if ($id === false || !is_numeric($id) || (int)$id <= 0) {
    header("Location: pemilik_rumah.php");
    exit;
}
$id = (int)$id;

// Ambil info dasar pemilik rumah dengan prepared statement
$sql_info = "SELECT rp.rumah_id, r.rumah_nomor,
    IF(rp.rumah_pemilik_nama IS NULL, w.warga_nama, rp.rumah_pemilik_nama) AS pemilik_nama
    FROM rumah_pemilik rp
    JOIN rumah r ON r.rumah_id = rp.rumah_id
    LEFT JOIN warga w ON w.warga_id = rp.warga_id
    WHERE rp.rumah_pemilik_id = ?";
$stmt_info = mysqli_prepare($conn, $sql_info);
mysqli_stmt_bind_param($stmt_info, "i", $id);
mysqli_stmt_execute($stmt_info);
$rumah_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_info));

if (!$rumah_info) {
    header("Location: pemilik_rumah.php");
    exit;
}

$rumah_id_real = $rumah_info['rumah_id'];

// Ambil histori kepemilikan rumah dengan prepared statement
$sql_history = "SELECT rp.*, 
        IF(rp.rumah_pemilik_nama IS NULL, w.warga_nama, rp.rumah_pemilik_nama) AS nama,
        IF(rp.rumah_pemilik_nama IS NULL, w.warga_no_hp, rp.rumah_pemilik_no_hp) AS no_hp,
        IF(rp.rumah_pemilik_nama IS NULL, CONCAT('WP ', wr_inner.rumah_nomor), rp.rumah_pemilik_alamat) AS alamat,
        IF(rp.is_aktif = 1, 'Aktif', 'Tidak Aktif') AS status_data
    FROM rumah_pemilik rp
    JOIN rumah r ON r.rumah_id = rp.rumah_id
    LEFT JOIN warga w ON w.warga_id = rp.warga_id
    LEFT JOIN (SELECT wr.warga_id, r.rumah_nomor
               FROM warga_rumah wr
               JOIN rumah r ON r.rumah_id = wr.rumah_id
               WHERE wr.is_aktif = 1
    ) wr_inner ON wr_inner.warga_id = w.warga_id
    WHERE rp.rumah_id = ?
    ORDER BY rp.is_aktif DESC, rp.rumah_pemilik_tanggal DESC";

$stmt_history = mysqli_prepare($conn, $sql_history);
mysqli_stmt_bind_param($stmt_history, "i", $rumah_id_real);
mysqli_stmt_execute($stmt_history);
$history_query = mysqli_stmt_get_result($stmt_history);
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <h4>🏠 Riwayat Pemilik Rumah Nomor : <?= e($rumah_info['rumah_nomor']) ?></h4>

    <div class="mb-3">
        <?php if ($role != 'pengguna') : ?>
            <a href="pemilik_rumah_tambah.php?rumah_id=<?= encrypt_id($rumah_id_real) ?>" class="btn btn-success">➕ Tambah Data</a>
         <?php endif; ?>
        <a href="pemilik_rumah.php" class="btn btn-secondary">🔙 Kembali</a>
    </div>

    <table class="table table-bordered table-striped">
        <thead class="text-center">
            <tr class="align-middle text-center">
                <th>No.</th>
                <th>Nama Pemilik</th>
                <th>No. HP</th>
                <th>Alamat</th>
                <th>Tanggal Dimiliki</th>
                <th>Keterangan</th>
                <th>Status Data</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; while ($d = mysqli_fetch_assoc($history_query)) : ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= e($d['nama']) ?></td>
                    <td><?= e($d['no_hp']) ?></td>
                    <td><?= e($d['alamat']) ?></td>
                    <td><?= e($d['rumah_pemilik_tanggal']) ?></td>
                    <td><?= e($d['rumah_pemilik_keterangan']) ?></td>
                    <td><?= e($d['status_data']) ?></td>
                    <td class="text-center">
                        <?php if ($role != 'pengguna') : ?>
                            <a href="pemilik_rumah_edit.php?id=<?= encrypt_id($d['rumah_pemilik_id']) ?>&rumah_id=<?= encrypt_id($d['rumah_id']) ?>" class="btn btn-sm btn-primary">✏️ Edit</a>
                            <a href="pemilik_rumah_hapus.php?id=<?= encrypt_id($d['rumah_pemilik_id']) ?>&rumah_id=<?= encrypt_id($d['rumah_id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus data ini?')">🗑️ Hapus</a>
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
