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
$id = decrypt_id($encrypted_id);

if ($id === false || !is_numeric($id) || (int)$id <= 0) {
    header("Location: koordinator_gang.php");
    exit;
}
$id = (int)$id;

// Ambil data dengan prepared statement
$sql = "SELECT kg.*, r.ref_nama AS nama_gang, w.warga_nama
        FROM koordinator_gang kg
        LEFT JOIN referensi r ON r.ref_id = kg.ref_id_gang AND r.ref_kategori = 'gang'
        LEFT JOIN warga w ON w.warga_id = kg.warga_id
        WHERE kg.koordinator_gang_id = ?";
$stmt_check = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt_check, "i", $id);
mysqli_stmt_execute($stmt_check);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));

if (!$data) {
    echo "<div class='alert alert-danger'>Data tidak ditemukan.</div>";
    exit;
}

$ref_id_gang = $data['ref_id_gang'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    verify_csrf_token(); // Proteksi CSRF
    
    // Lakukan delete dengan prepared statement
    $sql_delete = "DELETE FROM koordinator_gang WHERE koordinator_gang_id = ?";
    $stmt_delete = mysqli_prepare($conn, $sql_delete);
    mysqli_stmt_bind_param($stmt_delete, "i", $id);
    
    if (mysqli_stmt_execute($stmt_delete)) {
        header("Location: koordinator_gang_detail.php?id=" . encrypt_id($ref_id_gang));
        exit;
    } else {
        $error = "❌ Gagal menghapus data. Silakan coba lagi.";
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <h4>🗑️ Konfirmasi Hapus Koordinator Gang</h4>

    <?php if (!empty($error)) : ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="alert alert-warning">
        <strong>⚠️ Apakah Anda yakin ingin menghapus data berikut?</strong><br>
        <ul>
            <li><strong>Gang:</strong> <?= e($data['nama_gang']) ?></li>
            <li><strong>Koordinator:</strong> <?= e($data['warga_nama']) ?></li>
            <li><strong>Status:</strong>
                <?= $data['is_aktif'] == 1 ? 'Aktif' : 'Tidak Aktif' ?>
            </li>
        </ul>
    </div>

    <form method="post">
        <?= csrf_input() ?>
        <button name="hapus" class="btn btn-danger">🗑️ Ya, Hapus</button>
        <a href="koordinator_gang_detail.php?id=<?= encrypt_id($ref_id_gang) ?>" class="btn btn-secondary">❌ Batal</a>
    </form>
</div>

<?php include '../../views/footer.php'; ?>
