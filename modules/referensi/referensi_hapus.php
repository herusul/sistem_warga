<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$id = isset($_POST['id']) ? decrypt_id($_POST['id']) : (isset($_GET['id']) ? decrypt_id($_GET['id']) : 0);
$kategori = isset($_POST['kategori']) ? $_POST['kategori'] : (isset($_GET['kategori']) ? $_GET['kategori'] : '');

if ($id === false || !is_numeric($id) || (int)$id <= 0 || $kategori === '') {
    header("Location: referensi.php?kategori=" . urlencode($kategori));
    exit;
}
$id = (int)$id;

// Ambil data referensi dengan prepared statement
$sql_check = "SELECT * FROM referensi WHERE ref_id = ? AND ref_kategori = ?";
$stmt_check = mysqli_prepare($conn, $sql_check);
mysqli_stmt_bind_param($stmt_check, "is", $id, $kategori);
mysqli_stmt_execute($stmt_check);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));

if (!$data) {
    $_SESSION['error'] = "Data tidak ditemukan.";
    header("Location: referensi.php?kategori=" . urlencode($kategori));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    verify_csrf_token(); // Proteksi CSRF
    
    // Lakukan delete dengan prepared statement
    $sql_delete = "DELETE FROM referensi WHERE ref_id = ?";
    $stmt_delete = mysqli_prepare($conn, $sql_delete);
    mysqli_stmt_bind_param($stmt_delete, "i", $id);
    
    if (mysqli_stmt_execute($stmt_delete)) {
        $_SESSION['success'] = "✅ Referensi berhasil dihapus.";
        header("Location: referensi.php?kategori=" . urlencode($kategori));
        exit;
    } else {
        $error = "❌ Gagal menghapus data. Silakan coba lagi.";
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <h4>🗑️ Konfirmasi Hapus Referensi</h4>

    <?php if (!empty($error)) : ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="alert alert-warning">
        <strong>⚠️ Apakah Anda yakin ingin menghapus referensi berikut?</strong>
        <ul>
            <li><strong>Kategori:</strong> <?= e($data['ref_kategori']) ?></li>
            <li><strong>Nama Referensi:</strong> <?= e($data['ref_nama']) ?></li>
        </ul>
    </div>

    <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="id" value="<?= encrypt_id($id) ?>">
        <input type="hidden" name="kategori" value="<?= e($kategori) ?>">
        <button name="hapus" class="btn btn-danger">🗑️ Ya, Hapus</button>
        <a href="referensi.php?kategori=<?= urlencode($kategori) ?>" class="btn btn-secondary">❌ Batal</a>
    </form>
</div>

<?php include '../../views/footer.php'; ?>
