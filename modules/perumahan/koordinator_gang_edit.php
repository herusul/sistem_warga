<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$user = $_SESSION['user'];
$role = $user['role'];
$username = $user['username'];

// Ambil ID
$encrypted_id = isset($_GET['id']) ? $_GET['id'] : '';
$id = decrypt_id($encrypted_id);

if ($id === false || !is_numeric($id) || (int)$id <= 0) {
    header("Location: koordinator_gang.php");
    exit;
}
$id = (int)$id;

// Ambil data lama dengan prepared statement
$sql = "SELECT kg.*, r.ref_nama AS nama_gang 
        FROM koordinator_gang kg 
        LEFT JOIN referensi r ON r.ref_id = kg.ref_id_gang AND r.ref_kategori = 'gang' 
        WHERE koordinator_gang_id = ?";
$stmt_data = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt_data, "i", $id);
mysqli_stmt_execute($stmt_data);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_data));

if (!$data) {
    echo "<div class='alert alert-danger'>Data tidak ditemukan.</div>";
    exit;
}

$ref_id_gang = $data['ref_id_gang'];

// Ambil daftar warga
$warga_query = mysqli_query($conn, "SELECT warga_id, warga_nama FROM warga WHERE is_delete IS NULL ORDER BY warga_nama ASC");

// Proses submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token(); // Proteksi CSRF
    
    $warga_id = (int)$_POST['warga_id'];
    $is_aktif = ($_POST['is_aktif'] === '1') ? 1 : null;

    // Jika memilih status Aktif, nonaktifkan semua koordinator lain di gang yang sama dengan prepared statement
    if ($is_aktif === 1) {
        $sql_deactivate = "UPDATE koordinator_gang SET is_aktif = NULL WHERE ref_id_gang = ? AND koordinator_gang_id != ?";
        $stmt_deactivate = mysqli_prepare($conn, $sql_deactivate);
        mysqli_stmt_bind_param($stmt_deactivate, "ii", $ref_id_gang, $id);
        mysqli_stmt_execute($stmt_deactivate);
    }

    // Update data yang diedit dengan prepared statement
    $sql_update = "UPDATE koordinator_gang SET 
        warga_id = ?, is_aktif = ?, updated_by = ?, updated_time = NOW() 
        WHERE koordinator_gang_id = ?";
    $stmt_update = mysqli_prepare($conn, $sql_update);
    mysqli_stmt_bind_param($stmt_update, 'iisi', $warga_id, $is_aktif, $username, $id);

    if (mysqli_stmt_execute($stmt_update)) {
        header("Location: koordinator_gang_detail.php?id=" . encrypt_id($ref_id_gang));
        exit;
    } else {
        $error = "❌ Gagal menyimpan perubahan.";
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <h4>✏️ Edit Koordinator Gang: <?= e($data['nama_gang']) ?></h4>

    <?php if (isset($error)) : ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <?= csrf_input() ?>
        <div class="col-md-6">
            <label class="form-label">Nama Gang</label>
            <input type="text" class="form-control" value="<?= e($data['nama_gang']) ?>" disabled>
        </div>

        <div class="col-md-6">
            <label class="form-label">Nama Warga / Koordinator</label>
            <select name="warga_id" class="form-select" required>
                <option value="">-- Pilih Warga --</option>
                <?php while ($w = mysqli_fetch_assoc($warga_query)) : ?>
                    <option value="<?= e($w['warga_id']) ?>" <?= $w['warga_id'] == $data['warga_id'] ? 'selected' : '' ?>>
                        <?= e($w['warga_nama']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Status Koordinator</label><br>
            <div class="form-check form-check-inline">
                <input type="radio" name="is_aktif" value="1" class="form-check-input" id="aktif" <?= $data['is_aktif'] == 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="aktif">Aktif</label>
            </div>
            <div class="form-check form-check-inline">
                <input type="radio" name="is_aktif" value="" class="form-check-input" id="nonaktif" <?= $data['is_aktif'] != 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="nonaktif">Tidak Aktif</label>
            </div>
        </div>

        <div class="col-12">
            <button class="btn btn-primary">💾 Simpan Perubahan</button>
            <a href="koordinator_gang_detail.php?id=<?= encrypt_id($ref_id_gang) ?>" class="btn btn-secondary">❌ Batal</a>
        </div>
    </form>
</div>

<?php include '../../views/footer.php'; ?>
