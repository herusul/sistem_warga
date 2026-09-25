<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$user = $_SESSION['user'];
$role = $user['role'];
$username = $_SESSION['user']['username'];

// Ambil ID
if (!isset($_GET['id']) || !isset($_GET['warga_id'])) {
    header("Location: mutasi.php");
    exit;
}

$id = decrypt_id($_GET['id']);
$warga_id = decrypt_id($_GET['warga_id']);

if ($id === false || !is_numeric($id) || (int)$id <= 0 || $warga_id === false || !is_numeric($warga_id) || (int)$warga_id <= 0) {
    header("Location: mutasi.php");
    exit;
}
$id = (int)$id;
$warga_id = (int)$warga_id;

// Ambil data dengan prepared statement
$sql_mutasi = "SELECT * FROM warga_mutasi WHERE warga_mutasi_id = ? AND warga_id = ?";
$stmt_mutasi = mysqli_prepare($conn, $sql_mutasi);
mysqli_stmt_bind_param($stmt_mutasi, "ii", $id, $warga_id);
mysqli_stmt_execute($stmt_mutasi);
$mutasi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_mutasi));

if (!$mutasi) {
    header("Location: mutasi_detail.php?id=" . encrypt_id($warga_id));
    exit;
}

$ref_query = mysqli_query($conn, "SELECT * FROM referensi WHERE ref_kategori='status_aktif' AND is_aktif=1 ORDER BY ref_nama");

if (isset($_POST['simpan'])) {
    verify_csrf_token(); // Proteksi CSRF
    
    $status = $_POST['ref_id_status_aktif'];
    $tanggal = $_POST['warga_mutasi_tanggal'];
    $keterangan = trim($_POST['warga_mutasi_keterangan']);
    $is_aktif = ($_POST['is_aktif'] == '1') ? 1 : null;

    // Jika di-set Aktif, maka nonaktifkan semua mutasi lainnya dengan prepared statement
    if ($is_aktif == 1) {
        $sql_deactivate = "UPDATE warga_mutasi SET is_aktif=NULL WHERE warga_id=?";
        $stmt_deactivate = mysqli_prepare($conn, $sql_deactivate);
        mysqli_stmt_bind_param($stmt_deactivate, "i", $warga_id);
        mysqli_stmt_execute($stmt_deactivate);
    }

    // Update mutasi yang diedit dengan prepared statement
    $sql_update = "UPDATE warga_mutasi SET 
        ref_id_status_aktif=?,
        warga_mutasi_tanggal=?,
        warga_mutasi_keterangan=?,
        is_aktif=?,
        updated_by=?,
		updated_time=NOW()
        WHERE warga_mutasi_id=?";
    
    $stmt_update = mysqli_prepare($conn, $sql_update);
    mysqli_stmt_bind_param($stmt_update, "issisi", $status, $tanggal, $keterangan, $is_aktif, $username, $id);

    if (mysqli_stmt_execute($stmt_update)) {
        header("Location: mutasi_detail.php?id=" . encrypt_id($warga_id));
        exit;
    } else {
        $error = "Gagal mengubah data.";
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0 text-gray-800 fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Mutasi</h1>
        <a href="mutasi_detail.php?id=<?= encrypt_id($warga_id) ?>" class="btn btn-sm btn-outline-secondary px-3"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
    </div>

    <?php if (!empty($error)) : ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form id="formEditMutasi" method="post">
        <?= csrf_input() ?>
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="bi bi-geo-alt me-2"></i>Status Domisili</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Status Domisili <span class="text-danger">*</span></label>
                                <select name="ref_id_status_aktif" class="form-select" required>
                                    <option value="">- Pilih Status -</option>
                                    <?php while ($row = mysqli_fetch_assoc($ref_query)) : ?>
                                        <option value="<?= e($row['ref_id']) ?>" <?= $mutasi['ref_id_status_aktif'] == $row['ref_id'] ? 'selected' : '' ?>>
                                            <?= e($row['ref_nama']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Tanggal Status <span class="text-danger">*</span></label>
                                <input type="date" name="warga_mutasi_tanggal" value="<?= e($mutasi['warga_mutasi_tanggal']) ?>" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Keterangan</label>
                                <textarea name="warga_mutasi_keterangan" class="form-control" rows="3"><?= e($mutasi['warga_mutasi_keterangan']) ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Status Data</label><br>
                                <div class="form-check form-check-inline">
                                    <input type="radio" name="is_aktif" value="1" class="form-check-input" id="aktif" <?= $mutasi['is_aktif'] == 1 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="aktif">Aktif</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input type="radio" name="is_aktif" value="0" class="form-check-input" id="tidak_aktif" <?= is_null($mutasi['is_aktif']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="tidak_aktif">Tidak Aktif</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 mb-5">
                    <button type="submit" name="simpan" id="btnSimpan" class="btn btn-primary py-3 fw-bold"><i class="bi bi-save me-2"></i> SIMPAN PERUBAHAN</button>
                    <a href="mutasi_detail.php?id=<?= encrypt_id($warga_id) ?>" class="btn btn-light py-2">Kembali</a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Prevent double submission
    const form = document.getElementById('formEditMutasi');
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
