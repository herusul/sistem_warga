<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$user = $_SESSION['user'];
$role = $user['role'];
$username = $_SESSION['user']['username'];

// Ambil ID warga
$encrypted_warga_id = isset($_GET['warga_id']) ? $_GET['warga_id'] : '';
$warga_id = decrypt_id($encrypted_warga_id);

if ($warga_id === false || !is_numeric($warga_id) || (int)$warga_id <= 0) {
    header("Location: rumahwarga.php");
    exit;
}
$warga_id = (int)$warga_id;

// Ambil nama warga dengan prepared statement
$sql_warga = "SELECT warga_nama FROM warga WHERE warga_id = ?";
$stmt_warga = mysqli_prepare($conn, $sql_warga);
mysqli_stmt_bind_param($stmt_warga, "i", $warga_id);
mysqli_stmt_execute($stmt_warga);
$warga = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_warga));

if (!$warga) {
    header("Location: rumahwarga.php");
    exit;
}

$status_query = mysqli_query($conn, "SELECT ref_id, ref_nama FROM referensi WHERE ref_kategori='status_rumah' AND is_aktif=1 ORDER BY ref_nama");
$nomor_rumah_query = mysqli_query($conn, "SELECT rumah_id, rumah_nomor, rumah_nomor_tampil FROM rumah WHERE is_aktif=1 ORDER BY rumah_nomor");

if (isset($_POST['simpan'])) {
    verify_csrf_token(); // Proteksi CSRF
    
    $rumah_id = (int)$_POST['rumah_id'];
    $ref_id_status_rumah = (int)$_POST['ref_id_status_rumah'];
    $tanggal = $_POST['warga_rumah_tanggal'];

    // Nonaktifkan histori sebelumnya dengan prepared statement
    $sql_deactivate = "UPDATE warga_rumah SET is_aktif=NULL WHERE warga_id=?";
    $stmt_deactivate = mysqli_prepare($conn, $sql_deactivate);
    mysqli_stmt_bind_param($stmt_deactivate, "i", $warga_id);
    mysqli_stmt_execute($stmt_deactivate);

    // Insert histori baru dengan prepared statement
    $sql_insert = "INSERT INTO warga_rumah (warga_id, rumah_id, ref_id_status_rumah, warga_rumah_tanggal, is_aktif, created_by, created_time)
        VALUES (?, ?, ?, ?, 1, ?, NOW())";
    $stmt_insert = mysqli_prepare($conn, $sql_insert);
    mysqli_stmt_bind_param($stmt_insert, "iiiss", $warga_id, $rumah_id, $ref_id_status_rumah, $tanggal, $username);

    if (mysqli_stmt_execute($stmt_insert)) {
        header("Location: rumahwarga_detail.php?id=" . encrypt_id($warga_id));
        exit;
    } else {
        $error = "Gagal menyimpan data.";
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0 text-gray-800 fw-bold"><i class="bi bi-house-add me-2"></i>Tambah Hunian untuk <?= e($warga['warga_nama']) ?></h1>
        <a href="rumahwarga_detail.php?id=<?= encrypt_id($warga_id) ?>" class="btn btn-sm btn-outline-secondary px-3"><i class="bi bi-arrow-left me-1"></i> Batal</a>
    </div>

    <?php if (isset($error)) : ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form id="formTambahHunian" method="post">
        <?= csrf_input() ?>
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="bi bi-house-door me-2"></i>Data Hunian</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Nomor Rumah <span class="text-danger">*</span></label>
                                <select name="rumah_id" class="form-select" required>
                                    <option value="">- Pilih Nomor Rumah -</option>
                                    <?php while ($s = mysqli_fetch_assoc($nomor_rumah_query)) : ?>
                                        <option value="<?= e($s['rumah_id']) ?>"><?= e($s['rumah_nomor']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Status Rumah <span class="text-danger">*</span></label>
                                <select name="ref_id_status_rumah" class="form-select" required>
                                    <option value="">- Pilih Status -</option>
                                    <?php while ($s = mysqli_fetch_assoc($status_query)) : ?>
                                        <option value="<?= e($s['ref_id']) ?>"><?= e($s['ref_nama']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Tanggal Mulai Tinggal</label>
                                <input type="date" name="warga_rumah_tanggal" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 mb-5">
                    <button type="submit" name="simpan" id="btnSimpan" class="btn btn-primary py-3 fw-bold"><i class="bi bi-save me-2"></i> SIMPAN DATA HUNIAN</button>
                    <a href="rumahwarga_detail.php?id=<?= encrypt_id($warga_id) ?>" class="btn btn-light py-2">Batal</a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Prevent double submission
    const form = document.getElementById('formTambahHunian');
    const btnSimpan = document.getElementById('btnSimpan');

    form.addEventListener('submit', function() {
        btnSimpan.disabled = true;
        btnSimpan.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Menyimpan...';

        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'simpan';
        hiddenInput.value = '1';
        form.appendChild(hiddenInput);
    });
});
</script>
<?php include '../../views/footer.php'; ?>
