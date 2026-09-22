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
    header("Location: mutasi.php");
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
    header("Location: mutasi.php");
    exit;
}

$status_query = mysqli_query($conn, "SELECT ref_id, ref_nama FROM referensi WHERE ref_kategori='status_aktif' AND is_aktif=1 ORDER BY ref_nama");

if (isset($_POST['simpan'])) {
    verify_csrf_token(); // Proteksi CSRF
    
    $ref_id_status_aktif = (int)$_POST['ref_id_status_aktif'];
    $tanggal = $_POST['warga_mutasi_tanggal'];
    $keterangan = trim($_POST['warga_mutasi_keterangan']);

    // Nonaktifkan histori sebelumnya dengan prepared statement
    $sql_deactivate = "UPDATE warga_mutasi SET is_aktif=NULL WHERE warga_id=?";
    $stmt_deactivate = mysqli_prepare($conn, $sql_deactivate);
    mysqli_stmt_bind_param($stmt_deactivate, "i", $warga_id);
    mysqli_stmt_execute($stmt_deactivate);

    // Insert histori baru dengan prepared statement
    $sql_insert = "INSERT INTO warga_mutasi (warga_id, ref_id_status_aktif, warga_mutasi_tanggal, warga_mutasi_keterangan, is_aktif, created_by, created_time)
        VALUES (?, ?, ?, ?, 1, ?, NOW())";
    $stmt_insert = mysqli_prepare($conn, $sql_insert);
    mysqli_stmt_bind_param($stmt_insert, "iisss", $warga_id, $ref_id_status_aktif, $tanggal, $keterangan, $username);

    if (mysqli_stmt_execute($stmt_insert)) {
        header("Location: mutasi_detail.php?id=" . encrypt_id($warga_id));
        exit;
    } else {
        $error = "Gagal menyimpan data.";
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>
<div class="container mt-4">
    <h4>➕ Tambah Mutasi untuk <?= e($warga['warga_nama']) ?></h4>
    
    <?php if (isset($error)) : ?>
        <div class="alert alert-danger">❌ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_input() ?>
        <label>Status Domisili</label>
        <select name="ref_id_status_aktif" class="form-select mb-2" required>
            <option value="">- Pilih Status -</option>
            <?php while ($s = mysqli_fetch_assoc($status_query)) : ?>
                <option value="<?= e($s['ref_id']) ?>"><?= e($s['ref_nama']) ?></option>
            <?php endwhile; ?>
        </select>

        <label>Tanggal Status</label>
        <input type="date" name="warga_mutasi_tanggal" class="form-control mb-2" >

        <label>Keterangan</label>
        <textarea name="warga_mutasi_keterangan" class="form-control mb-3" rows="3" placeholder="Keterangan tambahan"></textarea>

        <button type="submit" name="simpan" class="btn btn-primary">💾 Simpan</button>
        <a href="mutasi_detail.php?id=<?= encrypt_id($warga_id) ?>" class="btn btn-secondary">❌ Batal</a>
    </form>
</div>
<?php include '../../views/footer.php'; ?>
