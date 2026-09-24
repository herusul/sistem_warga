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
$search_param = "%$q%";

$where = " WHERE (b.aset_nama LIKE ? OR p.keperluan LIKE ? OR p.dicatat_oleh LIKE ?) ";
$params = [$search_param, $search_param, $search_param];
$types = "sss";

$sql_count = "SELECT COUNT(*) AS total
FROM aset_pemakaian p
JOIN aset_barang b ON b.aset_id = p.aset_id $where";
$stmt_count = mysqli_prepare($conn, $sql_count);
mysqli_stmt_bind_param($stmt_count, $types, ...$params);
mysqli_stmt_execute($stmt_count);
$total_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
$total_pages = ceil($total_data / $limit);

$sql_data = "SELECT p.*, b.aset_nama
FROM aset_pemakaian p
JOIN aset_barang b ON b.aset_id = p.aset_id
$where
ORDER BY p.tanggal DESC, p.aset_pakai_id DESC
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
        <h1 class="h4 mb-0 text-gray-800 fw-bold"><i class="bi bi-dash-circle me-2"></i>Pemakaian Barang Habis Pakai</h1>
        <a href="aset.php?kategori=habis_pakai" class="btn btn-sm btn-secondary px-3 py-2">🔙 Barang Habis Pakai</a>
    </div>

    <div class="alert alert-info border-0 small">
        ℹ️ Pencatatan pakai <strong>mengurangi jumlah total secara permanen</strong> (tidak ada pengembalian). Khusus barang kategori <strong>Habis Pakai</strong>.
    </div>

    <!-- Search Card -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body py-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control border-start-0" placeholder="Cari barang / keperluan / pencatat..." value="<?= e($q) ?>">
                    </div>
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-primary px-4">Cari</button>
                    <a href="pemakaian.php" class="btn btn-light border">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary">Riwayat Pemakaian</h6>
            <span class="badge bg-light text-primary border border-primary px-3"><?= number_format($total_data) ?> Transaksi</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-center">
                            <th style="width: 60px;">No.</th>
                            <th class="text-start">Barang</th>
                            <th>Tanggal</th>
                            <th class="text-start">Keperluan</th>
                            <th>Jumlah</th>
                            <th>Dicatat Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($total_data > 0) : ?>
                            <?php $no = $start + 1; while ($row = mysqli_fetch_assoc($result)) : ?>
                            <tr>
                                <td class="text-center text-muted small"><?= $no++ ?></td>
                                <td><a href="aset_detail.php?id=<?= encrypt_id($row['aset_id']) ?>"><?= e($row['aset_nama']) ?></a></td>
                                <td class="text-center small"><?= e($row['tanggal']) ?></td>
                                <td><?= e($row['keperluan']) ?: '-' ?></td>
                                <td class="text-center fw-bold"><?= number_format($row['jumlah']) ?></td>
                                <td class="text-center small"><?= e($row['dicatat_oleh']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">⚠️ Tidak ada data ditemukan</td></tr>
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
                        <a class="page-link px-3" href="?page=<?= $page - 1 ?>&q=<?= urlencode($q) ?>">Prev</a>
                    </li>
                    <?php
                    $adjacents = 2;
                    $startPage = max(1, $page - $adjacents);
                    $endPage = min($total_pages, $page + $adjacents);
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link px-3" href="?page=<?= $i ?>&q=<?= urlencode($q) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link px-3" href="?page=<?= $page + 1 ?>&q=<?= urlencode($q) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../views/footer.php'; ?>
