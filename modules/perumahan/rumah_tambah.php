<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$user = $_SESSION['user'];
$role = $user['role'];
$username = $_SESSION['user']['username'];

// Ambil referensi gang
$gangs = mysqli_query($conn, "SELECT * FROM referensi WHERE ref_kategori='gang' AND is_aktif=1 ORDER BY ref_nama");

$error = '';

// Handle form submit
if (isset($_POST['simpan'])) {
    verify_csrf_token(); // Proteksi CSRF
    
    $nomor = trim($_POST['rumah_nomor']);
    $ref_id_gang = $_POST['ref_id_gang'];
    $luas_tanah = $_POST['rumah_luas_tanah'];
    $luas_bangunan = $_POST['rumah_luas_bangunan'];
    $keterangan = trim($_POST['rumah_keterangan']);
    $status = trim($_POST['rumah_status']);

    if ($nomor && $ref_id_gang) {
        $nomor_tampil = $nomor;

        $sql = "INSERT INTO rumah (rumah_nomor, rumah_nomor_tampil, ref_id_gang, rumah_luas_tanah, rumah_luas_bangunan, rumah_keterangan, rumah_status, created_by, created_time)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssisssss", $nomor, $nomor_tampil, $ref_id_gang, $luas_tanah, $luas_bangunan, $keterangan, $status, $username);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: rumah.php?msg=sukses");
            exit;
        } else {
            $error = "Gagal menyimpan data: " . mysqli_stmt_error($stmt);
        }
    } else {
        $error = "Nomor rumah dan gang harus diisi.";
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0 text-gray-800 fw-bold"><i class="bi bi-house-add me-2"></i>Tambah Data Rumah</h1>
        <a href="rumah.php" class="btn btn-sm btn-outline-secondary px-3"><i class="bi bi-arrow-left me-1"></i> Batal</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form id="formTambahRumah" method="post">
        <?= csrf_input() ?>
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="bi bi-house-door me-2"></i>Data Rumah</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Nomor Rumah <span class="text-danger">*</span></label>
                                <input type="text" name="rumah_nomor" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Gang / Jalan <span class="text-danger">*</span></label>
                                <select name="ref_id_gang" class="form-select" required>
                                    <option value="">- Pilih Gang -</option>
                                    <?php while ($row = mysqli_fetch_assoc($gangs)) : ?>
                                        <option value="<?= e($row['ref_id']) ?>"><?= e($row['ref_nama']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Luas Tanah (m<sup>2</sup>)</label>
                                <input type="number" name="rumah_luas_tanah" class="form-control" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Luas Bangunan (m<sup>2</sup>)</label>
                                <input type="number" name="rumah_luas_bangunan" class="form-control" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Status Rumah <span class="text-danger">*</span></label>
                                <select name="rumah_status" class="form-select" required>
                                    <option value="">- Pilih Status -</option>
                                    <option value="Dihuni">Dihuni</option>
                                    <option value="Kosong">Kosong</option>
                                    <option value="Dijual">Dijual</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Keterangan</label>
                                <textarea name="rumah_keterangan" class="form-control" rows="1"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 mb-5">
                    <button type="submit" name="simpan" id="btnSimpan" class="btn btn-primary py-3 fw-bold"><i class="bi bi-save me-2"></i> SIMPAN DATA RUMAH</button>
                    <a href="rumah.php" class="btn btn-light py-2">Batal</a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Prevent double submission
    const form = document.getElementById('formTambahRumah');
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
