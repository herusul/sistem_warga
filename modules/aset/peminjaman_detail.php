<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$encrypted_id = isset($_GET['id']) ? $_GET['id'] : '';
$id = decrypt_id($encrypted_id);

if ($id === false || !is_numeric($id) || (int)$id <= 0) {
    header("Location: peminjaman.php");
    exit;
}
$id = (int)$id;

$sql = "SELECT m.*, b.aset_nama, w.warga_nama
FROM aset_peminjaman m
JOIN aset_barang b ON b.aset_id = m.aset_id
LEFT JOIN warga w ON w.warga_id = m.warga_id
WHERE m.aset_pinjam_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$data) {
    header("Location: peminjaman.php");
    exit;
}

$nama_peminjam = $data['warga_nama'] ?: $data['peminjam_nama'];
$badge = badge_hasil_pinjam($data);
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>📄 Detail Peminjaman</h4>
        <a href="peminjaman.php" class="btn btn-secondary btn-sm">🔙 Kembali</a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <table class="table table-borderless mb-0">
                <tr><th style="width: 220px;">Barang</th><td><a href="aset_detail.php?id=<?= encrypt_id($data['aset_id']) ?>"><?= e($data['aset_nama']) ?></a></td></tr>
                <tr><th>Peminjam</th><td><?= e($nama_peminjam) ?>
                    <?= $data['warga_id'] ? '<span class="badge bg-light text-dark border small">WARGA WP</span>' : '<span class="badge bg-light text-dark border small">LUAR WP</span>' ?>
                    <?= $data['peminjam_no_hp'] ? '<span class="text-muted small">(' . e($data['peminjam_no_hp']) . ')</span>' : '' ?></td></tr>
                <tr><th>Jumlah</th><td><?= number_format($data['jumlah']) ?> unit</td></tr>
                <tr><th>Tanggal Pinjam</th><td><?= e($data['tgl_pinjam']) ?></td></tr>
                <tr><th>Rencana Kembali</th><td><?= e($data['tgl_rencana_kembali']) ?: '-' ?></td></tr>
                <tr><th>Tanggal Kembali</th><td><?= e($data['tgl_kembali']) ?: '-' ?></td></tr>
                <tr><th>Status / Hasil</th><td><?= $badge ?></td></tr>
                <?php if ($data['is_aktif'] != 1 && ((int)$data['jml_normal'] + (int)$data['jml_rusak'] + (int)$data['jml_hilang'] > 0)): ?>
                <tr><th>Rincian Kembali</th><td><?= number_format($data['jml_normal']) ?> baik + <?= number_format($data['jml_rusak']) ?> rusak + <?= number_format($data['jml_hilang']) ?> hilang</td></tr>
                <?php endif; ?>
                <tr><th>Keterangan</th><td><?= nl2br(e($data['keterangan'])) ?: '-' ?></td></tr>
                <tr><th>Dicatat Oleh</th><td class="small text-muted"><?= e($data['created_by']) ?> • <?= e($data['created_time']) ?></td></tr>
            </table>
            <?php if ($data['is_aktif'] == 1): ?>
                <a href="peminjaman_kembali.php?id=<?= encrypt_id($id) ?>" class="btn btn-sm btn-success mt-2"><i class="bi bi-check-lg"></i> Catat Kembali</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../views/footer.php'; ?>
