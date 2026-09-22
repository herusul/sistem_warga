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
<div class="container mt-4">
    <h4>✏️ Edit Mutasi</h4>
    <?php if (!empty($error)) : ?>
        <div class="alert alert-danger">❌ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_input() ?>
        <div class="mb-3">
            <label>Status Domisili</label>
            <select name="ref_id_status_aktif" class="form-control" required>
                <option value="">- Pilih Status -</option>
                <?php while ($row = mysqli_fetch_assoc($ref_query)) : ?>
                    <option value="<?= e($row['ref_id']) ?>" <?= $mutasi['ref_id_status_aktif'] == $row['ref_id'] ? 'selected' : '' ?>>
                        <?= e($row['ref_nama']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label>Tanggal Status</label>
            <input type="date" name="warga_mutasi_tanggal" value="<?= e($mutasi['warga_mutasi_tanggal']) ?>" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Keterangan</label>
            <textarea name="warga_mutasi_keterangan" class="form-control"><?= e($mutasi['warga_mutasi_keterangan']) ?></textarea>
        </div>

        <div class="mb-3">
            <label>Status Data</label><br>
            <div class="form-check form-check-inline">
                <input type="radio" name="is_aktif" value="1" class="form-check-input" id="aktif" <?= $mutasi['is_aktif'] == 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="aktif">Aktif</label>
            </div>
            <div class="form-check form-check-inline">
                <input type="radio" name="is_aktif" value="0" class="form-check-input" id="tidak_aktif" <?= is_null($mutasi['is_aktif']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="tidak_aktif">Tidak Aktif</label>
            </div>
        </div>

        <button class="btn btn-primary" name="simpan">💾 Simpan Perubahan</button>
        <a href="mutasi_detail.php?id=<?= encrypt_id($warga_id) ?>" class="btn btn-secondary">🔙 Kembali</a>
    </form>
</div>
<?php include '../../views/footer.php'; ?>
