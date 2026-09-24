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
    header("Location: peminjaman.php");
    exit;
}
$id = (int)$id;

// Hanya peminjaman yang masih aktif yang bisa dikembalikan
$sql_data = "SELECT m.*, b.aset_nama, w.warga_nama
FROM aset_peminjaman m
JOIN aset_barang b ON b.aset_id = m.aset_id
LEFT JOIN warga w ON w.warga_id = m.warga_id
WHERE m.aset_pinjam_id = ? AND m.is_aktif = 1";
$stmt_data = mysqli_prepare($conn, $sql_data);
mysqli_stmt_bind_param($stmt_data, "i", $id);
mysqli_stmt_execute($stmt_data);
$pinjam = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_data));

if (!$pinjam) {
    header("Location: peminjaman.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token(); // Proteksi CSRF

    $tgl_kembali = $_POST['tgl_kembali'] ?? '';
    $jml_normal = (int)($_POST['jml_normal'] ?? -1);
    $jml_rusak = (int)($_POST['jml_rusak'] ?? -1);
    $jml_hilang = (int)($_POST['jml_hilang'] ?? -1);
    $keterangan = trim($_POST['keterangan'] ?? '');
    $dipinjam = (int)$pinjam['jumlah'];

    if ($tgl_kembali === '') {
        $error = "Tanggal kembali wajib diisi.";
    } elseif ($jml_normal < 0 || $jml_rusak < 0 || $jml_hilang < 0) {
        $error = "Rincian jumlah tidak boleh negatif.";
    } elseif ($jml_normal + $jml_rusak + $jml_hilang !== $dipinjam) {
        $error = "Jumlah rincian (baik + rusak + hilang) harus sama dengan jumlah dipinjam ($dipinjam).";
    } else {
        // Ringkasan: hilang > rusak > normal. Stok berkurang hanya untuk rusak+hilang.
        $hasil = $jml_hilang > 0 ? 'hilang' : ($jml_rusak > 0 ? 'rusak' : 'normal');
        $kurang = $jml_rusak + $jml_hilang;

        // Transaksi: tutup peminjaman + kurangi total jika ada rusak/hilang
        mysqli_begin_transaction($conn);
        try {
            $stmt_close = mysqli_prepare($conn, "UPDATE aset_peminjaman
                SET tgl_kembali = ?, hasil = ?, jml_normal = ?, jml_rusak = ?, jml_hilang = ?,
                    keterangan = CONCAT(IFNULL(keterangan, ''), ?),
                    is_aktif = NULL, updated_by = ?, updated_time = NOW()
                WHERE aset_pinjam_id = ? AND is_aktif = 1");
            $tambahan = $keterangan !== '' ? "\n[Kembali] " . $keterangan : '';
            mysqli_stmt_bind_param($stmt_close, "ssiiissi", $tgl_kembali, $hasil, $jml_normal, $jml_rusak, $jml_hilang, $tambahan, $username, $id);
            mysqli_stmt_execute($stmt_close);

            if (mysqli_stmt_affected_rows($stmt_close) !== 1) {
                throw new Exception("Data sudah dikembalikan / tidak ditemukan.");
            }

            // Rusak/hilang: kurangi jumlah total barang sebesar rusak+hilang saja.
            // Yang kembali baik otomatis menambah ketersediaan (pinjam aktif tertutup).
            if ($kurang > 0) {
                $stmt_stock = mysqli_prepare($conn, "UPDATE aset_barang
                    SET aset_jumlah_total = GREATEST(aset_jumlah_total - ?, 0),
                        updated_by = ?, updated_time = NOW()
                    WHERE aset_id = ?");
                mysqli_stmt_bind_param($stmt_stock, "isi", $kurang, $username, $pinjam['aset_id']);
                mysqli_stmt_execute($stmt_stock);
            }

            mysqli_commit($conn);
            header("Location: peminjaman_detail.php?id=" . encrypt_id($id));
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
    <h4>✅ Catat Pengembalian</h4>
    <p>
        <strong><?= e($pinjam['aset_nama']) ?></strong> (<?= number_format($pinjam['jumlah']) ?> unit)<br>
        <span class="text-muted small">Peminjam: <?= e($pinjam['warga_nama'] ?: $pinjam['peminjam_nama']) ?> • Pinjam: <?= e($pinjam['tgl_pinjam']) ?></span>
    </p>

    <?php if ($error): ?>
        <div class="alert alert-danger">❌ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_input() ?>
        <div class="mb-3">
            <label>Tanggal Kembali <span class="text-danger">*</span></label>
            <input type="date" name="tgl_kembali" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Rincian Pengembalian <span class="text-danger">*</span> <span class="text-muted fw-normal">(total harus <?= number_format($pinjam['jumlah']) ?>)</span></label>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="small">Kembali Baik</label>
                    <input type="number" name="jml_normal" class="form-control" min="0" max="<?= (int)$pinjam['jumlah'] ?>" value="<?= (int)$pinjam['jumlah'] ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="small">Rusak <span class="text-muted">(kurangi stok)</span></label>
                    <input type="number" name="jml_rusak" class="form-control" min="0" max="<?= (int)$pinjam['jumlah'] ?>" value="0" required>
                </div>
                <div class="col-md-4">
                    <label class="small">Hilang <span class="text-muted">(kurangi stok)</span></label>
                    <input type="number" name="jml_hilang" class="form-control" min="0" max="<?= (int)$pinjam['jumlah'] ?>" value="0" required>
                </div>
            </div>
            <div class="form-text">Contoh: pinjam 10, kembali 8 baik + 2 rusak → stok total berkurang 2, yang 8 kembali tersedia.</div>
        </div>

        <div class="mb-3">
            <label>Keterangan Kembali</label>
            <textarea name="keterangan" class="form-control" rows="2" placeholder="cth: 1 unit lecet, handle patah"></textarea>
        </div>

        <button type="submit" class="btn btn-success">✅ Simpan Pengembalian</button>
        <a href="peminjaman_detail.php?id=<?= encrypt_id($id) ?>" class="btn btn-secondary">🔙 Batal</a>
    </form>
</div>

<?php include '../../views/footer.php'; ?>
