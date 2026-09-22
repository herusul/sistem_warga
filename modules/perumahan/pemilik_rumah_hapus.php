<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$user = $_SESSION['user'];
$role = $user['role'];

// Ambil ID
$encrypted_id = isset($_GET['id']) ? $_GET['id'] : '';
$encrypted_rumah_id = isset($_GET['rumah_id']) ? $_GET['rumah_id'] : '';

$id = decrypt_id($encrypted_id);
$rumah_id = decrypt_id($encrypted_rumah_id);

if ($id === false || !is_numeric($id) || (int)$id <= 0 || $rumah_id === false || !is_numeric($rumah_id) || (int)$rumah_id <= 0) {
    header("Location: pemilik_rumah.php");
    exit;
}
$id = (int)$id;
$rumah_id = (int)$rumah_id;

// Ambil data pemilik rumah dengan prepared statement
$query = "SELECT rp.*, 
    IF(rp.rumah_pemilik_nama IS NULL, w.warga_nama, rp.rumah_pemilik_nama) AS nama,
    IF(rp.rumah_pemilik_nama IS NULL, w.warga_no_hp, rp.rumah_pemilik_no_hp) AS no_hp,
    IF(rp.rumah_pemilik_nama IS NULL, CONCAT('WP ', wr_inner.rumah_nomor), rp.rumah_pemilik_alamat) AS alamat,
    r.rumah_nomor
    FROM rumah_pemilik rp
    JOIN rumah r ON r.rumah_id = rp.rumah_id
    LEFT JOIN warga w ON w.warga_id = rp.warga_id
    LEFT JOIN (SELECT wr.warga_id, r.rumah_nomor
               FROM warga_rumah wr
               JOIN rumah r ON r.rumah_id = wr.rumah_id
               WHERE wr.is_aktif = 1
    ) wr_inner ON wr_inner.warga_id = w.warga_id
    WHERE rp.rumah_pemilik_id = ? AND rp.rumah_id = ?";

$stmt_check = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt_check, "ii", $id, $rumah_id);
mysqli_stmt_execute($stmt_check);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));

if (!$data) {
    header("Location: pemilik_rumah.php");
    exit;
}

// Proses penghapusan
if (isset($_POST['hapus'])) {
    verify_csrf_token(); // Proteksi CSRF
    
    // Lakukan delete dengan prepared statement
    $sql_delete = "DELETE FROM rumah_pemilik WHERE rumah_pemilik_id = ?";
    $stmt_delete = mysqli_prepare($conn, $sql_delete);
    mysqli_stmt_bind_param($stmt_delete, "i", $id);
    
    if (mysqli_stmt_execute($stmt_delete)) {
        // Cari record pemilik lain untuk rumah yang sama sebagai tujuan redirect
        $sql_next = "SELECT rumah_pemilik_id FROM rumah_pemilik WHERE rumah_id = ? LIMIT 1";
        $stmt_next = mysqli_prepare($conn, $sql_next);
        mysqli_stmt_bind_param($stmt_next, "i", $rumah_id);
        mysqli_stmt_execute($stmt_next);
        $next_res = mysqli_stmt_get_result($stmt_next);
        
        if (mysqli_num_rows($next_res) > 0) {
            $next_owner = mysqli_fetch_assoc($next_res);
            header("Location: pemilik_rumah_detail.php?id=" . encrypt_id($next_owner['rumah_pemilik_id']));
        } else {
            header("Location: pemilik_rumah.php");
        }
        exit;
    } else {
        $error = "❌ Gagal menghapus data. Silakan coba lagi.";
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <h4>🗑️ Konfirmasi Hapus Pemilik Rumah</h4>

    <?php if (!empty($error)) : ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="alert alert-warning">
        <strong>⚠️ Apakah Anda yakin ingin menghapus data pemilik rumah berikut?</strong><br>
        <ul>
            <li><strong>Rumah Nomor:</strong> <?= e($data['rumah_nomor']) ?></li>
            <li><strong>Nama Pemilik:</strong> <?= e($data['nama']) ?></li>
            <li><strong>No. HP:</strong> <?= e($data['no_hp']) ?></li>
            <li><strong>Alamat:</strong> <?= e($data['alamat']) ?></li>
            <li><strong>Tanggal Dimiliki:</strong> <?= e($data['rumah_pemilik_tanggal']) ?></li>
            <li><strong>Keterangan:</strong> <?= e($data['rumah_pemilik_keterangan']) ?></li>
        </ul>
    </div>

    <form method="post">
        <?= csrf_input() ?>
        <button name="hapus" class="btn btn-danger">🗑️ Ya, Hapus</button>
        <a href="pemilik_rumah_detail.php?id=<?= encrypt_id($id) ?>" class="btn btn-secondary">🔙 Batal</a>
    </form>
</div>

<?php include '../../views/footer.php'; ?>
