<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$user = $_SESSION['user'];
$username = $user['username'] ?? $user['nama'] ?? 'system';

// Barang wajib dipilih dari master (khusus kategori habis_pakai)
$encrypted_aset = isset($_GET['aset_id']) ? $_GET['aset_id'] : '';
$aset_id = decrypt_id($encrypted_aset);

if ($aset_id === false || !is_numeric($aset_id) || (int)$aset_id <= 0) {
    header("Location: aset.php?kategori=habis_pakai");
    exit;
}
$aset_id = (int)$aset_id;

$sql_barang = "SELECT * FROM aset_barang WHERE aset_id = ?";
$stmt_barang = mysqli_prepare($conn, $sql_barang);
mysqli_stmt_bind_param($stmt_barang, "i", $aset_id);
mysqli_stmt_execute($stmt_barang);
$barang = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_barang));

// Hanya barang kategori habis_pakai yang bisa dicatat pakai
if (!$barang || $barang['aset_kategori'] !== 'habis_pakai') {
    header("Location: aset.php?kategori=habis_pakai");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token(); // Proteksi CSRF

    $jumlah = (int)($_POST['jumlah'] ?? 0);
    $tanggal = $_POST['tanggal'] ?? '';
    $keperluan = trim($_POST['keperluan'] ?? '');

    if ($jumlah < 1) {
        $error = "Jumlah pakai minimal 1.";
    } elseif ($jumlah > (int)$barang['aset_jumlah_total']) {
        $error = "Jumlah melebihi stok. Sisa: " . number_format($barang['aset_jumlah_total']) . ".";
    } elseif ($tanggal === '') {
        $error = "Tanggal pakai wajib diisi.";
    } else {
        // Transaksi: catat pemakaian + kurangi total permanen
        mysqli_begin_transaction($conn);
        try {
            $stmt_ins = mysqli_prepare($conn, "INSERT INTO aset_pemakaian (aset_id, jumlah, keperluan, tanggal, dicatat_oleh, created_time)
                VALUES (?, ?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt_ins, "iisss", $aset_id, $jumlah, $keperluan, $tanggal, $username);
            mysqli_stmt_execute($stmt_ins);

            $stmt_stock = mysqli_prepare($conn, "UPDATE aset_barang
                SET aset_jumlah_total = GREATEST(aset_jumlah_total - ?, 0),
                    updated_by = ?, updated_time = NOW()
                WHERE aset_id = ? AND aset_jumlah_total >= ?");
            mysqli_stmt_bind_param($stmt_stock, "isii", $jumlah, $username, $aset_id, $jumlah);
            mysqli_stmt_execute($stmt_stock);

            if (mysqli_stmt_affected_rows($stmt_stock) !== 1) {
                throw new Exception("Stok berubah / tidak mencukupi. Ulangi pencatatan.");
            }

            mysqli_commit($conn);
            header("Location: pemakaian.php");
            exit;
        } catch (Throwable $ex) {
            mysqli_rollback($conn);
            $error = "Gagal menyimpan: " . $ex->getMessage();
        }
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <h4>📉 Catat Pemakaian: <?= e($barang['aset_nama']) ?></h4>
    <div class="alert alert-warning border-0 small">
        ⚠️ Sisa stok: <strong><?= number_format($barang['aset_jumlah_total']) ?></strong>.
        Pencatatan ini <strong>mengurangi total secara permanen</strong> dan tidak bisa dibatalkan.
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">❌ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_input() ?>
        <div class="mb-3">
            <label>Jumlah Pakai <span class="text-danger">*</span></label>
            <input type="number" name="jumlah" class="form-control" min="1" max="<?= (int)$barang['aset_jumlah_total'] ?>" value="1" required>
        </div>

        <div class="mb-3">
            <label>Tanggal Pakai <span class="text-danger">*</span></label>
            <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="mb-3">
            <label>Keperluan</label>
            <input type="text" name="keperluan" class="form-control" maxlength="255" placeholder="cth: pengecatan pos ronda">
        </div>

        <button type="submit" class="btn btn-warning" onclick="return confirm('Pencatatan pakai mengurangi stok permanen. Lanjutkan?')">💾 Simpan Pemakaian</button>
        <a href="aset_detail.php?id=<?= encrypt_id($aset_id) ?>" class="btn btn-secondary">🔙 Batal</a>
    </form>
</div>

<?php include '../../views/footer.php'; ?>
