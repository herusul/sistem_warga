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
if (!isset($_GET['id']) || !isset($_GET['rumah_id'])) {
    header("Location: pemilik_rumah.php");
    exit;
}

$id = decrypt_id($_GET['id']);
$rumah_id = decrypt_id($_GET['rumah_id']);

if ($id === false || !is_numeric($id) || (int)$id <= 0 || $rumah_id === false || !is_numeric($rumah_id) || (int)$rumah_id <= 0) {
    header("Location: pemilik_rumah.php");
    exit;
}
$id = (int)$id;
$rumah_id = (int)$rumah_id;

// Ambil data lama dengan prepared statement
$sql = "SELECT * FROM rumah_pemilik WHERE rumah_pemilik_id = ? AND rumah_id = ?";
$stmt_data = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt_data, "ii", $id, $rumah_id);
mysqli_stmt_execute($stmt_data);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_data));

if (!$data) {
    echo "<div class='alert alert-danger'>Data tidak ditemukan.</div>";
    exit;
}

// Ambil daftar warga
$warga_query = mysqli_query($conn, "SELECT warga_id, warga_nama FROM warga WHERE is_delete IS NULL ORDER BY warga_nama ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token(); // Proteksi CSRF
    
    $warga_id = !empty($_POST['warga_id']) ? (int)$_POST['warga_id'] : null;
    $nama = !empty($_POST['rumah_pemilik_nama']) ? trim($_POST['rumah_pemilik_nama']) : null;
    $no_hp = !empty($_POST['rumah_pemilik_no_hp']) ? trim($_POST['rumah_pemilik_no_hp']) : null;
    $alamat = !empty($_POST['rumah_pemilik_alamat']) ? trim($_POST['rumah_pemilik_alamat']) : null;
    $tanggal = $_POST['rumah_pemilik_tanggal'];
    $keterangan = trim($_POST['rumah_pemilik_keterangan']);
    $is_aktif = ($_POST['is_aktif'] == '1') ? 1 : null;

    $sql_update = "UPDATE rumah_pemilik SET 
        warga_id = ?, 
        rumah_pemilik_nama = ?, 
        rumah_pemilik_no_hp = ?, 
        rumah_pemilik_alamat = ?, 
        rumah_pemilik_tanggal = ?, 
        rumah_pemilik_keterangan = ?, 
        updated_by = ?, 
        updated_time = NOW(),
        is_aktif = ?
        WHERE rumah_pemilik_id = ?";

    $stmt_update = mysqli_prepare($conn, $sql_update);
    mysqli_stmt_bind_param($stmt_update, 'issssssii', 
        $warga_id, $nama, $no_hp, $alamat, $tanggal, $keterangan, $username, $is_aktif, $id);

    if (mysqli_stmt_execute($stmt_update)) {
        header("Location: pemilik_rumah_detail.php?id=" . encrypt_id($id));
        exit;
    } else {
        $error = "Gagal menyimpan perubahan.";
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <h4>✏️ Edit Data Pemilik Rumah</h4>

    <?php if (isset($error)) : ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <?= csrf_input() ?>
        <input type="hidden" name="rumah_id" value="<?= e($rumah_id) ?>">

        <div class="col-md-6">
            <label class="form-label">Warga (pilih jika pemilik adalah warga)</label>
            <select name="warga_id" class="form-select">
                <option value="">-- Pilih Warga --</option>
                <?php while ($w = mysqli_fetch_assoc($warga_query)) : ?>
                    <option value="<?= e($w['warga_id']) ?>" <?= $data['warga_id'] == $w['warga_id'] ? 'selected' : '' ?>>
                        <?= e($w['warga_nama']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Nama Pemilik (jika bukan warga)</label>
            <input type="text" name="rumah_pemilik_nama" class="form-control" value="<?= e($data['rumah_pemilik_nama']) ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label">No. HP Pemilik (jika bukan warga)</label>
            <input type="text" name="rumah_pemilik_no_hp" class="form-control" value="<?= e($data['rumah_pemilik_no_hp']) ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label">Alamat Pemilik (jika bukan warga)</label>
            <input type="text" name="rumah_pemilik_alamat" class="form-control" value="<?= e($data['rumah_pemilik_alamat']) ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label">Tanggal Dimiliki</label>
            <input type="date" name="rumah_pemilik_tanggal" class="form-control" value="<?= e($data['rumah_pemilik_tanggal']) ?>" >
        </div>

        <div class="col-md-8">
            <label class="form-label">Keterangan</label>
            <input type="text" name="rumah_pemilik_keterangan" class="form-control" value="<?= e($data['rumah_pemilik_keterangan']) ?>">
        </div>

        <div class="mb-3">
            <label>Status Data</label><br>
            <div class="form-check form-check-inline">
                <input type="radio" name="is_aktif" value="1" class="form-check-input" id="aktif" <?= $data['is_aktif'] == 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="aktif">Aktif</label>
            </div>
            <div class="form-check form-check-inline">
                <input type="radio" name="is_aktif" value="0" class="form-check-input" id="tidak_aktif" <?= is_null($data['is_aktif']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="tidak_aktif">Tidak Aktif</label>
            </div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
            <a href="pemilik_rumah_detail.php?id=<?= encrypt_id($id) ?>" class="btn btn-secondary">❌ Batal</a>
        </div>
    </form>
</div>

<?php include '../../views/footer.php'; ?>
