<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$user = $_SESSION['user'];
$role = $user['role'];
$username = $user['username'];

// Ambil ID rumah dari URL
$encrypted_rumah_id = isset($_GET['rumah_id']) ? $_GET['rumah_id'] : '';
$rumah_id_real = decrypt_id($encrypted_rumah_id);

if ($rumah_id_real === false || !is_numeric($rumah_id_real) || (int)$rumah_id_real <= 0) {
    header("Location: pemilik_rumah.php");
    exit;
}
$rumah_id_real = (int)$rumah_id_real;

// Ambil info dasar rumah dengan prepared statement
$rumah_sql = "SELECT r.rumah_id, r.rumah_nomor FROM rumah r WHERE r.rumah_id = ?";
$stmt_r = mysqli_prepare($conn, $rumah_sql);
mysqli_stmt_bind_param($stmt_r, "i", $rumah_id_real);
mysqli_stmt_execute($stmt_r);
$rumah_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_r));

if (!$rumah_info) {
    header("Location: pemilik_rumah.php");
    exit;
}

// Ambil daftar warga untuk dropdown
$warga_query = mysqli_query($conn, "SELECT warga_id, warga_nama FROM warga WHERE is_delete IS NULL ORDER BY warga_nama ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token(); // Proteksi CSRF
    
    $warga_id = !empty($_POST['warga_id']) ? (int)$_POST['warga_id'] : null;
    $nama = !empty($_POST['rumah_pemilik_nama']) ? trim($_POST['rumah_pemilik_nama']) : null;
    $no_hp = !empty($_POST['rumah_pemilik_no_hp']) ? trim($_POST['rumah_pemilik_no_hp']) : null;
    $alamat = !empty($_POST['rumah_pemilik_alamat']) ? trim($_POST['rumah_pemilik_alamat']) : null;
    $tanggal = $_POST['rumah_pemilik_tanggal'];
    $keterangan = trim($_POST['rumah_pemilik_keterangan']);

    // Nonaktifkan pemilik sebelumnya (is_aktif = NULL)
    $sql_deactivate = "UPDATE rumah_pemilik SET is_aktif = NULL WHERE rumah_id = ?";
    $stmt_deactivate = mysqli_prepare($conn, $sql_deactivate);
    mysqli_stmt_bind_param($stmt_deactivate, "i", $rumah_id_real);
    mysqli_stmt_execute($stmt_deactivate);

    // Tambahkan pemilik baru
    $sql_insert = "INSERT INTO rumah_pemilik (rumah_id, warga_id, rumah_pemilik_nama, rumah_pemilik_no_hp, rumah_pemilik_alamat, rumah_pemilik_tanggal, rumah_pemilik_keterangan, created_by, created_time, is_aktif) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)";
    $stmt_insert = mysqli_prepare($conn, $sql_insert);
    mysqli_stmt_bind_param($stmt_insert, 'iissssss', $rumah_id_real, $warga_id, $nama, $no_hp, $alamat, $tanggal, $keterangan, $username);

    if (mysqli_stmt_execute($stmt_insert)) {
        header("Location: pemilik_rumah_detail.php?id=" . encrypt_id(mysqli_insert_id($conn)));
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
        <h1 class="h4 mb-0 text-gray-800 fw-bold"><i class="bi bi-person-plus me-2"></i>Tambah Pemilik Rumah WP <?= e($rumah_info['rumah_nomor']) ?></h1>
        <a href="pemilik_rumah.php" class="btn btn-sm btn-outline-secondary px-3"><i class="bi bi-arrow-left me-1"></i> Batal</a>
    </div>

    <?php if (isset($error)) : ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form id="formTambahPemilik" method="post">
        <?= csrf_input() ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="bi bi-person-badge me-2"></i>Identitas Pemilik</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Opsi 1: Jika Pemilik adalah Warga Terdaftar</label>
                                <select name="warga_id" class="form-select">
                                    <option value="">-- Pilih Warga --</option>
                                    <?php while ($w = mysqli_fetch_assoc($warga_query)) : ?>
                                        <option value="<?= e($w['warga_id']) ?>"><?= e($w['warga_nama']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Opsi 2: Jika Pemilik Bukan Warga (Input Manual)</label>
                                <input type="text" name="rumah_pemilik_nama" class="form-control" placeholder="Nama Pemilik Luar">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">No. HP Pemilik Luar</label>
                                <input type="text" name="rumah_pemilik_no_hp" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Alamat Pemilik Luar</label>
                                <input type="text" name="rumah_pemilik_alamat" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Tanggal Mulai Hak Milik <span class="text-danger">*</span></label>
                                <input type="date" name="rumah_pemilik_tanggal" class="form-control" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-bold">Keterangan Tambahan</label>
                                <input type="text" name="rumah_pemilik_keterangan" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 mb-5">
                    <button type="submit" id="btnSimpan" class="btn btn-primary py-3 fw-bold"><i class="bi bi-save me-2"></i> SIMPAN DATA PEMILIK</button>
                    <a href="pemilik_rumah.php" class="btn btn-light py-2">Batal</a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Prevent double submission
    const form = document.getElementById('formTambahPemilik');
    const btnSimpan = document.getElementById('btnSimpan');

    form.addEventListener('submit', function() {
        btnSimpan.disabled = true;
        btnSimpan.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Menyimpan...';
    });
});
</script>

<?php include '../../views/footer.php'; ?>
