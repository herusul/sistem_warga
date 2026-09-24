<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$user = $_SESSION['user'];
$username = $user['username'] ?? $user['nama'] ?? 'system';

$encrypted_id = isset($_GET['id']) ? $_GET['id'] : '';
$id = decrypt_id($encrypted_id);

if ($id === false || !is_numeric($id) || (int)$id <= 0) {
    header("Location: aset.php");
    exit;
}
$id = (int)$id;

$sql_data = "SELECT * FROM aset_barang WHERE aset_id = ?";
$stmt_data = mysqli_prepare($conn, $sql_data);
mysqli_stmt_bind_param($stmt_data, "i", $id);
mysqli_stmt_execute($stmt_data);
$barang = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_data));

if (!$barang) {
    header("Location: aset.php");
    exit;
}

$error = '';

if (isset($_POST['simpan'])) {
    verify_csrf_token(); // Proteksi CSRF

    $nama = trim($_POST['aset_nama'] ?? '');
    $kondisi = $_POST['aset_kondisi'] ?? 'Baik';
    $lokasi = trim($_POST['aset_lokasi'] ?? '');
    $keterangan = trim($_POST['aset_keterangan'] ?? '');

    $kondisi_valid = ['Baik', 'Rusak ringan', 'Rusak berat'];

    if ($nama === '') {
        $error = "Nama barang wajib diisi.";
    } elseif (!in_array($kondisi, $kondisi_valid, true)) {
        $error = "Kondisi tidak valid.";
    } else {
        // Jumlah total & kategori tidak diubah lewat edit (lihat doc modul-aset).
        // Jumlah berubah hanya via pengembalian rusak/hilang atau pencatatan pakai.
        $sql = "UPDATE aset_barang
                SET aset_nama = ?, aset_kondisi = ?, aset_lokasi = ?, aset_keterangan = ?,
                    updated_by = ?, updated_time = NOW()
                WHERE aset_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssssi", $nama, $kondisi, $lokasi, $keterangan, $username, $id);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: aset_detail.php?id=" . encrypt_id($id));
            exit;
        } else {
            $error = "Gagal menyimpan data: " . mysqli_stmt_error($stmt);
        }
    }
    // Refresh tampilan jika gagal
    $barang['aset_nama'] = $nama ?? $barang['aset_nama'];
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <h4>✏️ Edit Data Barang: <?= e($barang['aset_nama']) ?></h4>
    <p class="text-muted small">
        Kategori (<?= e($barang['aset_kategori'] === 'tetap' ? 'Tetap' : 'Habis Pakai') ?>)
        dan jumlah total (<?= number_format($barang['aset_jumlah_total']) ?>) tidak diubah di sini —
        jumlah berubah otomatis via pengembalian rusak/hilang atau pencatatan pakai.
    </p>

    <?php if ($error): ?>
        <div class="alert alert-danger">❌ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_input() ?>
        <div class="mb-3">
            <label>Nama Barang <span class="text-danger">*</span></label>
            <input type="text" name="aset_nama" class="form-control" required maxlength="150" value="<?= e($barang['aset_nama']) ?>">
        </div>

        <div class="mb-3">
            <label>Kondisi</label>
            <select name="aset_kondisi" class="form-select">
                <?php foreach (['Baik', 'Rusak ringan', 'Rusak berat'] as $k): ?>
                    <option value="<?= $k ?>" <?= $barang['aset_kondisi'] === $k ? 'selected' : '' ?>><?= $k ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label>Lokasi Penyimpanan</label>
            <input type="text" name="aset_lokasi" class="form-control" maxlength="150" value="<?= e($barang['aset_lokasi']) ?>">
        </div>

        <div class="mb-3">
            <label>Keterangan</label>
            <textarea name="aset_keterangan" class="form-control" rows="3"><?= e($barang['aset_keterangan']) ?></textarea>
        </div>

        <button type="submit" name="simpan" class="btn btn-primary">💾 Simpan</button>
        <a href="aset_detail.php?id=<?= encrypt_id($id) ?>" class="btn btn-secondary">🔙 Batal</a>
    </form>
</div>

<?php include '../../views/footer.php'; ?>
