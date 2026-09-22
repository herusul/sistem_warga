<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$id = isset($_GET['id']) ? decrypt_id($_GET['id']) : 0;
$kategori = isset($_GET['kategori']) ? $_GET['kategori'] : 'agama';

if ($id === false || !is_numeric($id) || (int)$id <= 0) {
    header("Location: referensi.php?kategori=" . urlencode($kategori));
    exit;
}
$id = (int)$id;

// Ambil data referensi dengan prepared statement
$sql_ref = "SELECT * FROM referensi WHERE ref_id = ?";
$stmt_ref = mysqli_prepare($conn, $sql_ref);
mysqli_stmt_bind_param($stmt_ref, "i", $id);
mysqli_stmt_execute($stmt_ref);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_ref));

if (!$data) {
    header("Location: referensi.php?kategori=" . urlencode($kategori));
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token(); // Proteksi CSRF
    
    $nama = trim($_POST['ref_nama'] ?? '');
    $is_aktif = isset($_POST['is_aktif']) ? (int)$_POST['is_aktif'] : 1;

    // Cek duplikat dengan prepared statement
    $sql_cek = "SELECT 1 FROM referensi WHERE ref_kategori = ? AND ref_nama = ? AND ref_id != ?";
    $stmt_cek = mysqli_prepare($conn, $sql_cek);
    mysqli_stmt_bind_param($stmt_cek, "ssi", $kategori, $nama, $id);
    mysqli_stmt_execute($stmt_cek);
    
    if (mysqli_num_rows(mysqli_stmt_get_result($stmt_cek)) > 0) {
        $error = "❌ Nama referensi '<strong>" . e($nama) . "</strong>' sudah ada di kategori '<strong>" . e($kategori) . "</strong>'.";
    } else {
        $sql_update = "UPDATE referensi SET ref_nama = ?, is_aktif = ? WHERE ref_id = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "sii", $nama, $is_aktif, $id);
        mysqli_stmt_execute($stmt_update);
        
        $_SESSION['success'] = "✅ Perubahan berhasil disimpan.";
        header("Location: referensi.php?kategori=" . urlencode($kategori));
        exit;
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <h4>✏️ Edit Referensi: <?= e($data['ref_nama']) ?></h4>

    <?php if ($error) : ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <?= csrf_input() ?>
        <div class="col-md-6">
            <label class="form-label">Nama Referensi</label>
            <input type="text" name="ref_nama" class="form-control" required value="<?= e($_POST['ref_nama'] ?? $data['ref_nama']) ?>">
        </div>

        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="is_aktif" class="form-select">
                <option value="1" <?= ($data['is_aktif'] ?? 1) == 1 ? 'selected' : '' ?>>Aktif</option>
                <option value="0" <?= ($data['is_aktif'] ?? 1) == 0 ? 'selected' : '' ?>>Tidak Aktif</option>
            </select>
        </div>

        <div class="col-12">
            <button class="btn btn-primary">💾 Simpan Perubahan</button>
            <a href="referensi.php?kategori=<?= urlencode($kategori) ?>" class="btn btn-secondary">❌ Batal</a>
        </div>
    </form>
</div>

<?php include '../../views/footer.php'; ?>
