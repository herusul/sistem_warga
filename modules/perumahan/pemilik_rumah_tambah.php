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

<div class="container mt-4">
    <h4>➕ Tambah Pemilik Rumah Nomor : <?= e($rumah_info['rumah_nomor']) ?></h4>
    
    <?php if (isset($error)) : ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <?= csrf_input() ?>
        <div class="col-md-6">
            <label class="form-label font-weight-bold">Opsi 1: Jika Pemilik adalah Warga Terdaftar</label>
            <select name="warga_id" class="form-select">
                <option value="">-- Pilih Warga --</option>
                <?php while ($w = mysqli_fetch_assoc($warga_query)) : ?>
                    <option value="<?= e($w['warga_id']) ?>"><?= e($w['warga_nama']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label font-weight-bold">Opsi 2: Jika Pemilik Bukan Warga (Input Manual)</label>
            <input type="text" name="rumah_pemilik_nama" class="form-control" placeholder="Nama Pemilik Luar">
        </div>

        <div class="col-md-6">
            <label class="form-label">No. HP Pemilik Luar</label>
            <input type="text" name="rumah_pemilik_no_hp" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Alamat Pemilik Luar</label>
            <input type="text" name="rumah_pemilik_alamat" class="form-control">
        </div>

        <div class="col-md-4">
            <label class="form-label">Tanggal Mulai Hak Milik</label>
            <input type="date" name="rumah_pemilik_tanggal" class="form-control" required>
        </div>

        <div class="col-md-8">
            <label class="form-label">Keterangan Tambahan</label>
            <input type="text" name="rumah_pemilik_keterangan" class="form-control">
        </div>

        <div class="col-12 mt-4">
            <button type="submit" class="btn btn-primary px-4">💾 Simpan Data Pemilik</button>
            <a href="pemilik_rumah.php" class="btn btn-secondary px-4">❌ Batal</a>
        </div>
    </form>
</div>

<?php include '../../views/footer.php'; ?>
