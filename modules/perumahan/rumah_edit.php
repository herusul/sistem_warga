<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$user = $_SESSION['user'];
$role = $user['role'];
$username = $_SESSION['user']['username'];

// Ambil ID rumah
$encrypted_id = isset($_GET['id']) ? $_GET['id'] : '';
$id = decrypt_id($encrypted_id);

if ($id === false || !is_numeric($id) || (int)$id <= 0) {
    header("Location: rumah.php");
    exit;
}
$id = (int)$id;

// Ambil data rumah dengan prepared statement
$sql_rumah = "SELECT * FROM rumah WHERE rumah_id = ? LIMIT 1";
$stmt_rumah = mysqli_prepare($conn, $sql_rumah);
mysqli_stmt_bind_param($stmt_rumah, "i", $id);
mysqli_stmt_execute($stmt_rumah);
$rumah = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_rumah));

if (!$rumah) {
    header("Location: rumah.php");
    exit;
}

// Ambil data referensi gang
$gang_query = mysqli_query($conn, "SELECT * FROM referensi WHERE ref_kategori='gang' AND is_aktif=1 ORDER BY ref_nama");

$error = '';
if (isset($_POST['simpan'])) {
    verify_csrf_token(); // Proteksi CSRF
    
    $nomor = trim($_POST['rumah_nomor']);
    $nomor_tampil = trim($_POST['rumah_nomor_tampil']);
    $gang_id = (int)$_POST['ref_id_gang'];
    $luas_tanah = (float)$_POST['rumah_luas_tanah'];
    $luas_bangunan = (float)$_POST['rumah_luas_bangunan'];
    $keterangan = trim($_POST['rumah_keterangan']);
    $status = trim($_POST['rumah_status']);

    $sql_update = "UPDATE rumah SET rumah_nomor=?, rumah_nomor_tampil=?, ref_id_gang=?,
        rumah_luas_tanah=?, rumah_luas_bangunan=?, rumah_keterangan=?, rumah_status=?,
        updated_by=?, updated_time=NOW() 
        WHERE rumah_id=?";
    
    $stmt_update = mysqli_prepare($conn, $sql_update);
    mysqli_stmt_bind_param($stmt_update, "ssidssssi", $nomor, $nomor_tampil, $gang_id, $luas_tanah, $luas_bangunan, $keterangan, $status, $username, $id);

    if (mysqli_stmt_execute($stmt_update)) {
        header("Location: rumah.php?msg=edit_sukses");
        exit;
    } else {
        $error = "Gagal menyimpan perubahan: " . mysqli_stmt_error($stmt_update);
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <h4>✏️ Edit Data Rumah</h4>
    <?php if (!empty($error)) : ?>
        <div class="alert alert-danger">❌ <?= e($error) ?></div>
    <?php endif; ?>
    
    <form method="post">
        <?= csrf_input() ?>
        <div class="mb-3">
            <label>Nomor Rumah <span class="text-danger">*</span></label>
            <input type="text" name="rumah_nomor" class="form-control" value="<?= e($rumah['rumah_nomor']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Nomor Rumah Tampil</label>
            <input type="text" name="rumah_nomor_tampil" class="form-control" value="<?= e($rumah['rumah_nomor_tampil']) ?>">
        </div>
        <div class="mb-3">
            <label>Gang / Jalan <span class="text-danger">*</span></label>
            <select name="ref_id_gang" class="form-control" required>
                <option value="">- Pilih Gang -</option>
                <?php while ($g = mysqli_fetch_assoc($gang_query)) : ?>
                    <option value="<?= e($g['ref_id']) ?>" <?= $rumah['ref_id_gang'] == $g['ref_id'] ? 'selected' : '' ?>><?= e($g['ref_nama']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-3">
            <label>Luas Tanah (m<sup>2</sup>)</label>
            <input type="number" step="0.1" name="rumah_luas_tanah" class="form-control" value="<?= e($rumah['rumah_luas_tanah']) ?>">
        </div>
        <div class="mb-3">
            <label>Luas Bangunan (m<sup>2</sup>)</label>
            <input type="number" step="0.1" name="rumah_luas_bangunan" class="form-control" value="<?= e($rumah['rumah_luas_bangunan']) ?>">
        </div>
        <div class="mb-3">
            <label>Keterangan</label>
            <textarea name="rumah_keterangan" class="form-control"><?= e($rumah['rumah_keterangan']) ?></textarea>
        </div>
        <div class="mb-3">
            <label>Status Rumah <span class="text-danger">*</span></label>
            <select name="rumah_status" class="form-control" required>
                <option value="">- Pilih Status -</option>
                <option value="Dihuni" <?= $rumah['rumah_status'] == 'Dihuni' ? 'selected' : '' ?>>Dihuni</option>
                <option value="Kosong" <?= $rumah['rumah_status'] == 'Kosong' ? 'selected' : '' ?>>Kosong</option>
                <option value="Dijual" <?= $rumah['rumah_status'] == 'Dijual' ? 'selected' : '' ?>>Dijual</option>
            </select>
        </div>
        <button class="btn btn-primary" name="simpan">💾 Simpan Perubahan</button>
        <a href="rumah.php" class="btn btn-secondary">🔙 Kembali</a>
    </form>
</div>

<?php include '../../views/footer.php'; ?>
