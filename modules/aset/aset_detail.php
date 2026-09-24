<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$encrypted_id = isset($_GET['id']) ? $_GET['id'] : '';
$id = decrypt_id($encrypted_id);

if ($id === false || !is_numeric($id) || (int)$id <= 0) {
    header("Location: aset.php");
    exit;
}
$id = (int)$id;

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
mysqli_stmt_bind_param($stmt_barang, "i", $id);
mysqli_stmt_execute($stmt_barang);
$barang = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_barang));

if (!$barang) {
    header("Location: aset.php");
    exit;
}

// Riwayat peminjaman barang ini (aktif dulu, terbaru dulu)
$sql_pinjam = "SELECT m.*, w.warga_nama
FROM aset_peminjaman m
LEFT JOIN warga w ON w.warga_id = m.warga_id
WHERE m.aset_id = ?
ORDER BY m.is_aktif DESC, m.tgl_pinjam DESC
LIMIT 50";
$stmt_pinjam = mysqli_prepare($conn, $sql_pinjam);
mysqli_stmt_bind_param($stmt_pinjam, "i", $id);
mysqli_stmt_execute($stmt_pinjam);
$riwayat_pinjam = mysqli_stmt_get_result($stmt_pinjam);

// Riwayat pemakaian (khusus habis pakai)
$riwayat_pakai = null;
if ($barang['aset_kategori'] === 'habis_pakai') {
    $sql_pakai = "SELECT * FROM aset_pemakaian WHERE aset_id = ? ORDER BY tanggal DESC LIMIT 50";
    $stmt_pakai = mysqli_prepare($conn, $sql_pakai);
    mysqli_stmt_bind_param($stmt_pakai, "i", $id);
    mysqli_stmt_execute($stmt_pakai);
    $riwayat_pakai = mysqli_stmt_get_result($stmt_pakai);
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>📦 Detail Barang: <?= e($barang['aset_nama']) ?></h4>
        <a href="aset.php" class="btn btn-secondary btn-sm">🔙 Kembali</a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-1 text-muted small">KATEGORI</p>
                    <p><span class="badge <?= $barang['aset_kategori'] === 'tetap' ? 'bg-primary' : 'bg-warning text-dark' ?>">
                        <?= $barang['aset_kategori'] === 'tetap' ? 'Tetap (dipinjam-kembali)' : 'Habis Pakai' ?>
                    </span></p>
                    <p class="mb-1 text-muted small">KONDISI</p>
                    <p><?= e($barang['aset_kondisi']) ?></p>
                    <p class="mb-1 text-muted small">LOKASI</p>
                    <p><?= e($barang['aset_lokasi']) ?: '-' ?></p>
                </div>
                <div class="col-md-6">
                    <p class="mb-1 text-muted small">STOK</p>
                    <h3>
                        <span class="text-success"><?= number_format($barang['tersedia']) ?></span>
                        <span class="fs-6 text-muted">tersedia dari <?= number_format($barang['aset_jumlah_total']) ?> total</span>
                    </h3>
                    <?php if ($barang['aset_kategori'] === 'tetap'): ?>
                        <p class="small text-muted">Sedang dipinjam: <?= number_format($barang['dipinjam']) ?></p>
                    <?php endif; ?>
                    <p class="mb-1 text-muted small">KETERANGAN</p>
                    <p><?= e($barang['aset_keterangan']) ?: '-' ?></p>
                </div>
            </div>
            <div class="mt-2">
                <a href="aset_edit.php?id=<?= encrypt_id($id) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
                <?php if ($barang['aset_kategori'] === 'tetap' && $barang['tersedia'] > 0): ?>
                    <a href="peminjaman_tambah.php?aset_id=<?= encrypt_id($id) ?>" class="btn btn-sm btn-info"><i class="bi bi-arrow-left-right"></i> Catat Pinjam</a>
                <?php endif; ?>
                <?php if ($barang['aset_kategori'] === 'habis_pakai' && $barang['aset_jumlah_total'] > 0): ?>
                    <a href="pemakaian_tambah.php?aset_id=<?= encrypt_id($id) ?>" class="btn btn-sm btn-warning"><i class="bi bi-dash-circle"></i> Catat Pakai</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <h5>📋 Riwayat Peminjaman</h5>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-center">
                            <th>Peminjam</th>
                            <th>Jumlah</th>
                            <th>Tgl Pinjam</th>
                            <th>Rencana Kembali</th>
                            <th>Tgl Kembali</th>
                            <th>Status / Hasil</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($riwayat_pinjam) > 0): ?>
                            <?php while ($r = mysqli_fetch_assoc($riwayat_pinjam)):
                                $nama_peminjam = $r['warga_nama'] ?: $r['peminjam_nama'];
                                $badge = badge_hasil_pinjam($r);
                            ?>
                            <tr>
                                <td><?= e($nama_peminjam) ?> <?= $r['warga_id'] ? '<span class="badge bg-light text-dark border small">WARGA WP</span>' : '<span class="badge bg-light text-dark border small">LUAR WP</span>' ?></td>
                                <td class="text-center"><?= number_format($r['jumlah']) ?></td>
                                <td class="text-center small"><?= e($r['tgl_pinjam']) ?></td>
                                <td class="text-center small"><?= e($r['tgl_rencana_kembali']) ?: '-' ?></td>
                                <td class="text-center small"><?= e($r['tgl_kembali']) ?: '-' ?></td>
                                <td class="text-center"><?= $badge ?></td>
                                <td class="text-center">
                                    <a href="peminjaman_detail.php?id=<?= encrypt_id($r['aset_pinjam_id']) ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i></a>
                                    <?php if ($r['is_aktif'] == 1): ?>
                                        <a href="peminjaman_kembali.php?id=<?= encrypt_id($r['aset_pinjam_id']) ?>" class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Kembali</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada riwayat peminjaman</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($riwayat_pakai !== null): ?>
    <h5>📉 Riwayat Pemakaian</h5>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-center">
                            <th>Tanggal</th>
                            <th class="text-start">Keperluan</th>
                            <th>Jumlah</th>
                            <th>Dicatat Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($riwayat_pakai) > 0): ?>
                            <?php while ($p = mysqli_fetch_assoc($riwayat_pakai)): ?>
                            <tr>
                                <td class="text-center small"><?= e($p['tanggal']) ?></td>
                                <td><?= e($p['keperluan']) ?: '-' ?></td>
                                <td class="text-center"><?= number_format($p['jumlah']) ?></td>
                                <td class="text-center small"><?= e($p['dicatat_oleh']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada riwayat pemakaian</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../../views/footer.php'; ?>
