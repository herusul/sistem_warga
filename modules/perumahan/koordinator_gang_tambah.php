<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$user = $_SESSION['user'];
$role = $user['role'];
$username = $user['username'];

// Ambil ID gang
$encrypted_ref_id_gang = isset($_GET['ref_id_gang']) ? $_GET['ref_id_gang'] : '';
$ref_id_gang = decrypt_id($encrypted_ref_id_gang);

if ($ref_id_gang === false || !is_numeric($ref_id_gang) || (int)$ref_id_gang <= 0) {
    header("Location: koordinator_gang.php");
    exit;
}
$ref_id_gang = (int)$ref_id_gang;

// Ambil nama gang dengan prepared statement
$sql_gang = "SELECT IF(ref_id=20, ref_nama, CONCAT('Gg.', ref_nama)) AS nama_gang 
             FROM referensi 
             WHERE ref_id = ? AND ref_kategori = 'gang'";
$stmt_gang = mysqli_prepare($conn, $sql_gang);
mysqli_stmt_bind_param($stmt_gang, "i", $ref_id_gang);
mysqli_stmt_execute($stmt_gang);
$gang = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_gang));

if (!$gang) {
    echo "<div class='alert alert-danger'>Gang tidak ditemukan.</div>";
    exit;
}

// Ambil daftar warga
$warga_query = mysqli_query($conn, "SELECT warga_id, warga_nama FROM warga WHERE is_delete IS NULL ORDER BY warga_nama ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token(); // Proteksi CSRF
    
    $warga_id = (int)$_POST['warga_id'];
    $is_aktif = ($_POST['is_aktif'] === '1') ? 1 : null;

    $sql_insert = "INSERT INTO koordinator_gang (ref_id_gang, warga_id, is_aktif, created_by, created_time) 
                   VALUES (?, ?, ?, ?, NOW())";
    $stmt_insert = mysqli_prepare($conn, $sql_insert);
    mysqli_stmt_bind_param($stmt_insert, 'iiis', $ref_id_gang, $warga_id, $is_aktif, $username);

    if (mysqli_stmt_execute($stmt_insert)) {
        header("Location: koordinator_gang_detail.php?id=" . encrypt_id($ref_id_gang));
        exit;
    } else {
        $error = "❌ Gagal menyimpan data. Silakan coba lagi.";
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0 text-gray-800 fw-bold"><i class="bi bi-pin-map me-2"></i>Tambah Koordinator <?= e($gang['nama_gang']) ?></h1>
        <a href="koordinator_gang_detail.php?id=<?= encrypt_id($ref_id_gang) ?>" class="btn btn-sm btn-outline-secondary px-3"><i class="bi bi-arrow-left me-1"></i> Batal</a>
    </div>

    <?php if (isset($error)) : ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form id="formTambahKoordinator" method="post">
        <?= csrf_input() ?>
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="bi bi-person-badge me-2"></i>Data Koordinator</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Koordinator (Warga) <span class="text-danger">*</span></label>
                                <select name="warga_id" class="form-select" required>
                                    <option value="">-- Pilih Warga --</option>
                                    <?php while ($w = mysqli_fetch_assoc($warga_query)) : ?>
                                        <option value="<?= e($w['warga_id']) ?>"><?= e($w['warga_nama']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Status</label><br>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="is_aktif" value="1" id="aktif" checked>
                                    <label class="form-check-label" for="aktif">Aktif</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="is_aktif" value="" id="tidak_aktif">
                                    <label class="form-check-label" for="tidak_aktif">Tidak Aktif</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 mb-5">
                    <button type="submit" id="btnSimpan" class="btn btn-primary py-3 fw-bold"><i class="bi bi-save me-2"></i> SIMPAN DATA KOORDINATOR</button>
                    <a href="koordinator_gang_detail.php?id=<?= encrypt_id($ref_id_gang) ?>" class="btn btn-light py-2">Batal</a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Prevent double submission
    const form = document.getElementById('formTambahKoordinator');
    const btnSimpan = document.getElementById('btnSimpan');

    form.addEventListener('submit', function() {
        btnSimpan.disabled = true;
        btnSimpan.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Menyimpan...';
    });
});
</script>

<?php include '../../views/footer.php'; ?>
