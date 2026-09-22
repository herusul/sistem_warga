<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$user = $_SESSION['user'];
$role = $user['role'];

// Ambil ID
$encrypted_id = isset($_GET['id']) ? $_GET['id'] : '';
$encrypted_warga_id = isset($_GET['warga_id']) ? $_GET['warga_id'] : '';

$id = decrypt_id($encrypted_id);
$warga_id = decrypt_id($encrypted_warga_id);

if ($id === false || !is_numeric($id) || (int)$id <= 0 || $warga_id === false || !is_numeric($warga_id) || (int)$warga_id <= 0) {
    header("Location: mutasi.php");
    exit;
}
$id = (int)$id;
$warga_id = (int)$warga_id;

// Ambil data dengan prepared statement
$sql_check = "SELECT m.*, r.ref_nama FROM warga_mutasi m 
    LEFT JOIN referensi r ON r.ref_id = m.ref_id_status_aktif AND r.ref_kategori='status_aktif'
    WHERE m.warga_mutasi_id = ? AND m.warga_id = ?";
$stmt_check = mysqli_prepare($conn, $sql_check);
mysqli_stmt_bind_param($stmt_check, "ii", $id, $warga_id);
mysqli_stmt_execute($stmt_check);
$mutasi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));

if (!$mutasi) {
    header("Location: mutasi_detail.php?id=" . encrypt_id($warga_id)); 
    exit;
}

if (isset($_POST['hapus'])) {
    verify_csrf_token(); // Proteksi CSRF
    
    // Lakukan delete dengan prepared statement
    $sql_delete = "DELETE FROM warga_mutasi WHERE warga_mutasi_id = ?";
    $stmt_delete = mysqli_prepare($conn, $sql_delete);
    mysqli_stmt_bind_param($stmt_delete, "i", $id);
    
    if (mysqli_stmt_execute($stmt_delete)) {
        header("Location: mutasi_detail.php?id=" . encrypt_id($warga_id));
        exit;
    } else {
        $error = "❌ Gagal menghapus data. Silakan coba lagi.";
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <h4>🗑️ Konfirmasi Hapus Mutasi</h4>

    <?php if (!empty($error)) : ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="alert alert-warning">
        <strong>⚠️ Apakah Anda yakin ingin menghapus data mutasi berikut?</strong><br>
        <ul>
            <li><strong>Status:</strong> <?= e($mutasi['ref_nama']) ?></li>
            <li><strong>Tanggal:</strong> <?= e($mutasi['warga_mutasi_tanggal']) ?></li>
            <li><strong>Keterangan:</strong> <?= e($mutasi['warga_mutasi_keterangan']) ?></li>
        </ul>
    </div>

    <form method="post">
        <?= csrf_input() ?>
        <button name="hapus" class="btn btn-danger">🗑️ Ya, Hapus</button>
        <a href="mutasi_detail.php?id=<?= encrypt_id($warga_id) ?>" class="btn btn-secondary">🔙 Batal</a>
    </form>
</div>

<?php include '../../views/footer.php'; ?>
