<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');

check_auth(['admin', 'superadmin', 'operator']);

$kategori = isset($_POST['kategori']) ? $_POST['kategori'] : (isset($_GET['kategori']) ? $_GET['kategori'] : '');

if ($kategori === '') {
    header("Location: referensi.php");
    exit;
}

// Cek apakah kategori ada di database dengan prepared statement
$sql_cek = "SELECT COUNT(*) as jml FROM referensi WHERE ref_kategori = ?";
$stmt_cek = mysqli_prepare($conn, $sql_cek);
mysqli_stmt_bind_param($stmt_cek, "s", $kategori);
mysqli_stmt_execute($stmt_cek);
$jumlah = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek))['jml'];

if ($jumlah == 0) {
    $_SESSION['error'] = "Kategori tidak ditemukan atau kosong.";
    header("Location: referensi.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    verify_csrf_token(); // Proteksi CSRF
    
    // Lakukan delete dengan prepared statement
    $sql_delete = "DELETE FROM referensi WHERE ref_kategori = ?";
    $stmt_delete = mysqli_prepare($conn, $sql_delete);
    mysqli_stmt_bind_param($stmt_delete, "s", $kategori);
    
    if (mysqli_stmt_execute($stmt_delete)) {
        $_SESSION['success'] = "✅ Kategori '<strong>" . e($kategori) . "</strong>' berhasil dihapus.";
        header("Location: referensi.php");
        exit;
    } else {
        $error = "❌ Gagal menghapus kategori. Silakan coba lagi.";
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <h4>🗑️ Konfirmasi Hapus Kategori Referensi</h4>

    <?php if (!empty($error)) : ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="alert alert-warning">
        <strong>⚠️ Apakah Anda yakin ingin menghapus kategori berikut beserta seluruh isinya?</strong>
        <ul>
            <li><strong>Kategori:</strong> <?= e($kategori) ?></li>
            <li><strong>Jumlah Data:</strong> <?= e($jumlah) ?> item</li>
        </ul>
    </div>

    <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="kategori" value="<?= e($kategori) ?>">
        <button name="hapus" class="btn btn-danger">🗑️ Ya, Hapus Semua</button>
        <a href="referensi.php?kategori=<?= urlencode($kategori) ?>" class="btn btn-secondary">❌ Batal</a>
    </form>
</div>

<?php include '../../views/footer.php'; ?>
