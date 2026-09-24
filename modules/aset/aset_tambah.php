<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$user = $_SESSION['user'];
$username = $user['username'] ?? $user['nama'] ?? 'system';

$error = '';

// Handle form submit
if (isset($_POST['simpan'])) {
    verify_csrf_token(); // Proteksi CSRF

    $nama = trim($_POST['aset_nama'] ?? '');
    $kategori = $_POST['aset_kategori'] ?? '';
    $jumlah = (int)($_POST['aset_jumlah_total'] ?? 0);
    $kondisi = $_POST['aset_kondisi'] ?? 'Baik';
    $lokasi = trim($_POST['aset_lokasi'] ?? '');
    $keterangan = trim($_POST['aset_keterangan'] ?? '');

    $kategori_valid = ['tetap', 'habis_pakai'];
    $kondisi_valid = ['Baik', 'Rusak ringan', 'Rusak berat'];

    if ($nama === '' || !in_array($kategori, $kategori_valid, true)) {
        $error = "Nama barang dan kategori wajib diisi dengan benar.";
    } elseif ($jumlah < 0) {
        $error = "Jumlah total tidak boleh negatif.";
    } elseif (!in_array($kondisi, $kondisi_valid, true)) {
        $error = "Kondisi tidak valid.";
    } else {
        $sql = "INSERT INTO aset_barang (aset_nama, aset_kategori, aset_jumlah_total, aset_kondisi, aset_lokasi, aset_keterangan, is_aktif, created_by, created_time)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssissss", $nama, $kategori, $jumlah, $kondisi, $lokasi, $keterangan, $username);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: aset.php");
            exit;
        } else {
            $error = "Gagal menyimpan data: " . mysqli_stmt_error($stmt);
        }
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <h4>➕ Tambah Data Barang / Aset</h4>

    <?php if ($error): ?>
        <div class="alert alert-danger">❌ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_input() ?>
        <div class="mb-3">
            <label>Nama Barang <span class="text-danger">*</span></label>
            <input type="text" name="aset_nama" class="form-control" required maxlength="150" placeholder="cth: Tenda 3x3, Kursi Plastik, Cat Tembok">
        </div>

        <div class="mb-3">
            <label>Kategori <span class="text-danger">*</span></label>
            <select name="aset_kategori" class="form-select" required>
                <option value="">- Pilih Kategori -</option>
                <option value="tetap">Tetap (dipinjam → kembali)</option>
                <option value="habis_pakai">Habis Pakai (catat pakai, berkurang permanen)</option>
            </select>
        </div>

        <div class="mb-3">
            <label>Jumlah Total <span class="text-danger">*</span></label>
            <input type="number" name="aset_jumlah_total" class="form-control" min="0" value="0" required>
        </div>

        <div class="mb-3">
            <label>Kondisi</label>
            <select name="aset_kondisi" class="form-select">
                <option value="Baik">Baik</option>
                <option value="Rusak ringan">Rusak ringan</option>
                <option value="Rusak berat">Rusak berat</option>
            </select>
        </div>

        <div class="mb-3">
            <label>Lokasi Penyimpanan</label>
            <input type="text" name="aset_lokasi" class="form-control" maxlength="150" placeholder="cth: Gudang RT, Pos Ronda">
        </div>

        <div class="mb-3">
            <label>Keterangan</label>
            <textarea name="aset_keterangan" class="form-control" rows="3"></textarea>
        </div>

        <button type="submit" name="simpan" class="btn btn-primary">💾 Simpan</button>
        <a href="aset.php" class="btn btn-secondary">🔙 Batal</a>
    </form>
</div>

<?php include '../../views/footer.php'; ?>
