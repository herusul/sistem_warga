<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$user = $_SESSION['user'];
$username = $user['username'] ?? $user['nama'] ?? 'system';

// Barang wajib dipilih dari master (khusus kategori tetap)
$encrypted_aset = isset($_GET['aset_id']) ? $_GET['aset_id'] : '';
$aset_id = decrypt_id($encrypted_aset);

if ($aset_id === false || !is_numeric($aset_id) || (int)$aset_id <= 0) {
    header("Location: aset.php");
    exit;
}
$aset_id = (int)$aset_id;

$sql_barang = "SELECT a.*,
    IFNULL(p.dipinjam, 0) AS dipinjam,
    (a.aset_jumlah_total - IFNULL(p.dipinjam, 0)) AS tersedia
FROM aset_barang a
LEFT JOIN (
    SELECT aset_id, SUM(jumlah) AS dipinjam
    FROM aset_peminjaman WHERE is_aktif = 1 GROUP BY aset_id
) p ON p.aset_id = a.aset_id
WHERE a.aset_id = ?";
$stmt_barang = mysqli_prepare($conn, $sql_barang);
mysqli_stmt_bind_param($stmt_barang, "i", $aset_id);
mysqli_stmt_execute($stmt_barang);
$barang = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_barang));

// Hanya barang kategori tetap yang bisa dipinjam
if (!$barang || $barang['aset_kategori'] !== 'tetap') {
    header("Location: aset.php");
    exit;
}

// Daftar warga untuk dropdown
$warga_query = mysqli_query($conn, "SELECT warga_id, warga_nama FROM warga WHERE is_delete IS NULL ORDER BY warga_nama ASC");

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token(); // Proteksi CSRF

    $jumlah = (int)($_POST['jumlah'] ?? 0);
    $warga_id = !empty($_POST['warga_id']) ? (int)$_POST['warga_id'] : null;
    $nama_manual = !empty($_POST['peminjam_nama']) ? trim($_POST['peminjam_nama']) : null;
    $no_hp_manual = !empty($_POST['peminjam_no_hp']) ? trim($_POST['peminjam_no_hp']) : null;
    $tgl_pinjam = $_POST['tgl_pinjam'] ?? '';
    $tgl_rencana = !empty($_POST['tgl_rencana_kembali']) ? $_POST['tgl_rencana_kembali'] : null;
    $keterangan = trim($_POST['keterangan'] ?? '');

    // Hitung ulang ketersediaan di server (antisipasi race)
    $stmt_av = mysqli_prepare($conn, "SELECT aset_jumlah_total - IFNULL((SELECT SUM(jumlah) FROM aset_peminjaman WHERE aset_id = ? AND is_aktif = 1), 0) AS tersedia FROM aset_barang WHERE aset_id = ?");
    mysqli_stmt_bind_param($stmt_av, "ii", $aset_id, $aset_id);
    mysqli_stmt_execute($stmt_av);
    $tersedia = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_av))['tersedia'];

    if ($jumlah < 1) {
        $error = "Jumlah pinjam minimal 1.";
    } elseif ($jumlah > $tersedia) {
        $error = "Stok tidak mencukupi. Tersedia: $tersedia.";
    } elseif ($warga_id !== null && $nama_manual !== null) {
        $error = "Pilih salah satu: warga terdaftar ATAU input manual luar WP.";
    } elseif ($warga_id === null && $nama_manual === null) {
        $error = "Peminjam wajib diisi (pilih warga atau input manual).";
    } elseif ($tgl_pinjam === '') {
        $error = "Tanggal pinjam wajib diisi.";
    } else {
        $sql = "INSERT INTO aset_peminjaman (aset_id, warga_id, peminjam_nama, peminjam_no_hp, jumlah, tgl_pinjam, tgl_rencana_kembali, keterangan, is_aktif, created_by, created_time)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iississss", $aset_id, $warga_id, $nama_manual, $no_hp_manual, $jumlah, $tgl_pinjam, $tgl_rencana, $keterangan, $username);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: peminjaman.php?status=aktif");
            exit;
        } else {
            $error = "Gagal menyimpan data.";
        }
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <h4>➕ Catat Peminjaman: <?= e($barang['aset_nama']) ?></h4>
    <p class="text-muted small">Tersedia saat ini: <strong class="text-success"><?= number_format($barang['tersedia']) ?></strong> dari <?= number_format($barang['aset_jumlah_total']) ?> total.</p>

    <?php if (isset($error) && $error): ?>
        <div class="alert alert-danger">❌ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <?= csrf_input() ?>
        <div class="col-md-4">
            <label class="form-label">Jumlah Pinjam <span class="text-danger">*</span></label>
            <input type="number" name="jumlah" class="form-control" min="1" max="<?= (int)$barang['tersedia'] ?>" value="1" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Tanggal Pinjam <span class="text-danger">*</span></label>
            <input type="date" name="tgl_pinjam" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Rencana Kembali</label>
            <input type="date" name="tgl_rencana_kembali" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label font-weight-bold">Opsi 1: Peminjam Warga Terdaftar</label>
            <select name="warga_id" class="form-select">
                <option value="">-- Pilih Warga --</option>
                <?php while ($w = mysqli_fetch_assoc($warga_query)) : ?>
                    <option value="<?= e($w['warga_id']) ?>"><?= e($w['warga_nama']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label font-weight-bold">Opsi 2: Peminjam Luar WP (Manual)</label>
            <input type="text" name="peminjam_nama" class="form-control mb-2" placeholder="Nama peminjam luar" maxlength="100">
            <input type="text" name="peminjam_no_hp" class="form-control" placeholder="No. HP" maxlength="50">
        </div>

        <div class="col-12">
            <label class="form-label">Keterangan</label>
            <input type="text" name="keterangan" class="form-control" placeholder="cth: untuk hajatan, keperluan RT">
        </div>

        <div class="col-12 mt-3">
            <button type="submit" class="btn btn-primary px-4">💾 Simpan Peminjaman</button>
            <a href="aset_detail.php?id=<?= encrypt_id($aset_id) ?>" class="btn btn-secondary px-4">❌ Batal</a>
        </div>
    </form>
</div>

<?php include '../../views/footer.php'; ?>
