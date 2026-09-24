<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['superadmin', 'operator', 'admin']);

// Konfigurasi paging
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start = ($page - 1) * $limit;

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$search_param = "%$q%";

$where = " WHERE (b.aset_nama LIKE ? OR w.warga_nama LIKE ? OR m.peminjam_nama LIKE ?) ";
$params = [$search_param, $search_param, $search_param];
$types = "sss";

if ($status === 'aktif') {
    $where .= " AND m.is_aktif = 1 ";
} elseif ($status === 'selesai') {
    $where .= " AND m.is_aktif IS NULL ";
}

$sql_count = "SELECT COUNT(*) AS total
FROM aset_peminjaman m
JOIN aset_barang b ON b.aset_id = m.aset_id
LEFT JOIN warga w ON w.warga_id = m.warga_id $where";
$stmt_count = mysqli_prepare($conn, $sql_count);
mysqli_stmt_bind_param($stmt_count, $types, ...$params);
mysqli_stmt_execute($stmt_count);
$total_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
$total_pages = ceil($total_data / $limit);

$sql_data = "SELECT m.aset_pinjam_id, m.jumlah, m.tgl_pinjam, m.tgl_rencana_kembali,
    m.tgl_kembali, m.hasil, m.jml_normal, m.jml_rusak, m.jml_hilang,
    m.is_aktif, m.peminjam_nama,
    b.aset_nama, w.warga_nama
FROM aset_peminjaman m
JOIN aset_barang b ON b.aset_id = m.aset_id
LEFT JOIN warga w ON w.warga_id = m.warga_id
$where
ORDER BY m.is_aktif DESC, m.tgl_pinjam DESC
LIMIT ?, ?";

$params[] = $start;
$params[] = $limit;
$types .= "ii";

$stmt_data = mysqli_prepare($conn, $sql_data);
mysqli_stmt_bind_param($stmt_data, $types, ...$params);
mysqli_stmt_execute($stmt_data);
$result = mysqli_stmt_get_result($stmt_data);
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0 text-gray-800 fw-bold"><i class="bi bi-arrow-left-right me-2"></i>Peminjaman Barang</h1>
        <a href="aset.php" class="btn btn-sm btn-secondary px-3 py-2">🔙 Master Barang</a>
    </div>

    <!-- Search Card -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body py-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control border-start-0" placeholder="Cari barang / peminjam..." value="<?= e($q) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Sedang Dipinjam</option>
                        <option value="selesai" <?= $status === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                    </select>
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-primary px-4">Cari</button>
                    <a href="peminjaman.php" class="btn btn-light border">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary">Daftar Peminjaman</h6>
            <span class="badge bg-light text-primary border border-primary px-3"><?= number_format($total_data) ?> Transaksi</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-center">
                            <th style="width: 60px;">No.</th>
                            <th class="text-start">Barang</th>
                            <th class="text-start">Peminjam</th>
                            <th>Jumlah</th>
                            <th>Tgl Pinjam</th>
                            <th>Rencana Kembali</th>
                            <th>Status / Hasil</th>
                            <th style="width: 130px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($total_data > 0) : ?>
                            <?php $no = $start + 1; while ($row = mysqli_fetch_assoc($result)) :
                                $nama_peminjam = $row['warga_nama'] ?: $row['peminjam_nama'];
                                $badge = badge_hasil_pinjam($row);
                            ?>
                            <tr>
                                <td class="text-center text-muted small"><?= $no++ ?></td>
                                <td><?= e($row['aset_nama']) ?></td>
                                <td><?= e($nama_peminjam) ?></td>
                                <td class="text-center"><?= number_format($row['jumlah']) ?></td>
                                <td class="text-center small"><?= e($row['tgl_pinjam']) ?></td>
                                <td class="text-center small"><?= e($row['tgl_rencana_kembali']) ?: '-' ?></td>
                                <td class="text-center"><?= $badge ?></td>
                                <td class="text-center">
                                    <a href="peminjaman_detail.php?id=<?= encrypt_id($row['aset_pinjam_id']) ?>" class="btn btn-sm btn-outline-info" title="Detail"><i class="bi bi-eye"></i></a>
                                    <?php if ($row['is_aktif'] == 1) : ?>
                                        <a href="peminjaman_kembali.php?id=<?= encrypt_id($row['aset_pinjam_id']) ?>" class="btn btn-sm btn-success" title="Catat Kembali"><i class="bi bi-check-lg"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">⚠️ Tidak ada data ditemukan</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white py-3">
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link px-3" href="?page=<?= $page - 1 ?>&q=<?= urlencode($q) ?>&status=<?= urlencode($status) ?>">Prev</a>
                    </li>
                    <?php
                    $adjacents = 2;
                    $startPage = max(1, $page - $adjacents);
                    $endPage = min($total_pages, $page + $adjacents);
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link px-3" href="?page=<?= $i ?>&q=<?= urlencode($q) ?>&status=<?= urlencode($status) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link px-3" href="?page=<?= $page + 1 ?>&q=<?= urlencode($q) ?>&status=<?= urlencode($status) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../views/footer.php'; ?>
