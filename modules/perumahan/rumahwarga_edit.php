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
    header("Location: rumahwarga.php");
    exit;
}

$id = decrypt_id($_GET['id']);
$warga_id = decrypt_id($_GET['warga_id']);

if ($id === false || !is_numeric($id) || (int)$id <= 0 || $warga_id === false || !is_numeric($warga_id) || (int)$warga_id <= 0) {
    header("Location: rumahwarga.php");
    exit;
}
$id = (int)$id;
$warga_id = (int)$warga_id;

// Ambil data dengan prepared statement
$sql_rumah = "SELECT * FROM warga_rumah WHERE warga_rumah_id = ? AND warga_id = ?";
$stmt_rumah = mysqli_prepare($conn, $sql_rumah);
mysqli_stmt_bind_param($stmt_rumah, "ii", $id, $warga_id);
mysqli_stmt_execute($stmt_rumah);
$rumah = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_rumah));

if (!$rumah) {
    header("Location: rumahwarga_detail.php?id=" . encrypt_id($warga_id));
    exit;
}

$ref_query = mysqli_query($conn, "SELECT * FROM referensi WHERE ref_kategori='status_rumah' AND is_aktif=1 ORDER BY ref_id");
$nomor_rumah_query = mysqli_query($conn, "SELECT * FROM rumah WHERE is_aktif=1 ORDER BY rumah_nomor");

if (isset($_POST['simpan'])) {
    verify_csrf_token(); // Proteksi CSRF
    
    $status = $_POST['ref_id_status_rumah'];
    $tanggal = !empty($_POST['warga_rumah_tanggal']) ? $_POST['warga_rumah_tanggal'] : null;
    $rumah_id_post = $_POST['rumah_id'];
    $is_aktif = ($_POST['is_aktif'] == '1') ? 1 : null;

    // Jika di-set Aktif, maka nonaktifkan semua mutasi lainnya dengan prepared statement
    if ($is_aktif == 1) {
        $sql_deactivate = "UPDATE warga_rumah SET is_aktif=NULL WHERE warga_id=?";
        $stmt_deactivate = mysqli_prepare($conn, $sql_deactivate);
        mysqli_stmt_bind_param($stmt_deactivate, "i", $warga_id);
        mysqli_stmt_execute($stmt_deactivate);
    }

    // Update mutasi yang diedit dengan prepared statement
    $sql_update = "UPDATE warga_rumah SET 
        ref_id_status_rumah=?,
        warga_rumah_tanggal=?,
        rumah_id=?,
        is_aktif=?,
        updated_by=?,
		updated_time=NOW()
        WHERE warga_rumah_id=?";
    
    $stmt_update = mysqli_prepare($conn, $sql_update);
    mysqli_stmt_bind_param($stmt_update, "isiisi", $status, $tanggal, $rumah_id_post, $is_aktif, $username, $id);

    if (mysqli_stmt_execute($stmt_update)) {
        header("Location: rumahwarga_detail.php?id=" . encrypt_id($warga_id));
        exit;
    } else {
        $error = "Gagal mengubah data.";
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>
<div class="container mt-4">
    <h4>✏️ Edit Rumah Warga</h4>
    <?php if (!empty($error)) : ?>
        <div class="alert alert-danger">❌ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_input() ?>
        <div class="mb-3">
            <label>Nomor Rumah</label>
            <select name="rumah_id" class="form-control" required>
                <option value="">- Pilih Nomor Rumah -</option>
                <?php while ($row_r = mysqli_fetch_assoc($nomor_rumah_query)) : ?>
                    <option value="<?= e($row_r['rumah_id']) ?>" <?= $rumah['rumah_id'] == $row_r['rumah_id'] ? 'selected' : '' ?>>
                        <?= e($row_r['rumah_nomor']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>		
		
        <div class="mb-3">
            <label>Status Rumah</label>
            <select name="ref_id_status_rumah" class="form-control" required>
                <option value="">- Pilih Status -</option>
                <?php while ($row_ref = mysqli_fetch_assoc($ref_query)) : ?>
                    <option value="<?= e($row_ref['ref_id']) ?>" <?= $rumah['ref_id_status_rumah'] == $row_ref['ref_id'] ? 'selected' : '' ?>>
                        <?= e($row_ref['ref_nama']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label>Tanggal Mulai Tinggal</label>
            <input type="date" name="warga_rumah_tanggal" value="<?= e($rumah['warga_rumah_tanggal']) ?>" class="form-control" >
        </div>

        <div class="mb-3">
            <label>Status Data</label><br>
            <div class="form-check form-check-inline">
                <input type="radio" name="is_aktif" value="1" class="form-check-input" id="aktif" <?= $rumah['is_aktif'] == 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="aktif">Aktif</label>
            </div>
            <div class="form-check form-check-inline">
                <input type="radio" name="is_aktif" value="0" class="form-check-input" id="tidak_aktif" <?= is_null($rumah['is_aktif']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="tidak_aktif">Tidak Aktif</label>
            </div>
        </div>

        <button class="btn btn-primary" name="simpan">💾 Simpan Perubahan</button>
        <a href="rumahwarga_detail.php?id=<?= encrypt_id($warga_id) ?>" class="btn btn-secondary">🔙 Kembali</a>
    </form>
</div>
<?php include '../../views/footer.php'; ?>
