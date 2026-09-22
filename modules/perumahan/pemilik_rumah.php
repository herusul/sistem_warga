<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['superadmin', 'operator', 'admin']);

$user = $_SESSION['user'];
$role = $user['role'];

// Pencarian & Pagination
$keyword = isset($_GET['q']) ? $_GET['q'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$start = ($page - 1) * $limit;

$search_param = "%$keyword%";

// Hitung total data
$count_sql = "SELECT COUNT(*) as total FROM rumah_pemilik p
    JOIN rumah r ON p.rumah_id = r.rumah_id
    LEFT JOIN warga w ON p.warga_id = w.warga_id
    WHERE p.is_aktif = 1";
if ($keyword !== '') {
    $count_sql .= " AND (r.rumah_nomor LIKE ? OR w.warga_nama LIKE ? OR p.rumah_pemilik_nama LIKE ?)";
}
$stmt_count = mysqli_prepare($conn, $count_sql);
if ($keyword !== '') {
    mysqli_stmt_bind_param($stmt_count, "sss", $search_param, $search_param, $search_param);
}
mysqli_stmt_execute($stmt_count);
$total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
$pages = ceil($total / $limit);

// Query data
$data_sql = "SELECT p.*, r.rumah_nomor, w.warga_nama, w.warga_no_hp, CONCAT('WP ', wr_inner.rumah_nomor) AS alamat_warga_wp
	FROM rumah_pemilik p
    JOIN rumah r ON p.rumah_id = r.rumah_id
    LEFT JOIN warga w ON p.warga_id = w.warga_id
    LEFT JOIN (SELECT wr.warga_id, r.rumah_nomor
               FROM warga_rumah wr
               JOIN rumah r ON r.rumah_id = wr.rumah_id
               WHERE wr.is_aktif = 1
    ) wr_inner ON wr_inner.warga_id = w.warga_id
    WHERE p.is_aktif = 1";
if ($keyword !== '') {
    $data_sql .= " AND (r.rumah_nomor LIKE ? OR w.warga_nama LIKE ? OR p.rumah_pemilik_nama LIKE ?)";
}
$data_sql .= " ORDER BY r.rumah_nomor ASC LIMIT ?, ?";

$stmt_data = mysqli_prepare($conn, $data_sql);
if ($keyword !== '') {
    mysqli_stmt_bind_param($stmt_data, "sssii", $search_param, $search_param, $search_param, $start, $limit);
} else {
    mysqli_stmt_bind_param($stmt_data, "ii", $start, $limit);
}
mysqli_stmt_execute($stmt_data);
$data_query = mysqli_stmt_get_result($stmt_data);
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0 text-gray-800 fw-bold"><i class="bi bi-houses me-2"></i>Daftar Pemilik Rumah</h1>
    </div>

    <!-- Search Card -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body py-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" value="<?= e($keyword) ?>" class="form-control border-start-0" placeholder="Cari nama pemilik / nomor rumah...">
                    </div>
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-primary px-4">Cari</button>
                    <a href="pemilik_rumah.php" class="btn btn-light border">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary">Informasi Kepemilikan Unit</h6>
            <span class="badge bg-light text-primary border border-primary px-3"><?= number_format($total) ?> Pemilik Terdaftar</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-center">
                            <th style="width: 60px;">No.</th>
                            <th>No. Rumah</th>
                            <th class="text-start">Nama Pemilik</th>
                            <th>Kontak</th>
                            <th class="text-start">Alamat/Keterangan</th>
                            <th>Tgl Dimiliki</th>
                            <th style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($data_query) > 0) : ?>
                            <?php $no = $start + 1; while ($row = mysqli_fetch_assoc($data_query)) : ?>
                                <tr>
                                    <td class="text-center text-muted small"><?= $no++ ?></td>
                                    <td class="text-center fw-bold text-primary"><?= e($row['rumah_nomor']) ?></td>
                                    <td>
                                        <div class="fw-bold"><?= e($row['warga_nama'] ?: $row['rumah_pemilik_nama'] ?: '-') ?></div>
                                        <?php if ($row['warga_nama']): ?>
                                            <span class="badge bg-light text-success border border-success extra-small" style="font-size: 10px;">WARGA WP</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border extra-small" style="font-size: 10px;">LUAR WP</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center small"><?= e($row['warga_no_hp'] ?: $row['rumah_pemilik_no_hp'] ?: '-') ?></td>
                                    <td class="small">
                                        <div class="text-truncate" style="max-width: 200px;">
                                            <i class="bi bi-geo-alt me-1 text-muted"></i><?= e($row['alamat_warga_wp'] ?: $row['rumah_pemilik_alamat'] ?: '-') ?>
                                        </div>
                                        <div class="extra-small text-muted fst-italic mt-1"><?= e($row['rumah_pemilik_keterangan']) ?></div>
                                    </td>
                                    <td class="text-center small"><?= e($row['rumah_pemilik_tanggal'] ?: '-') ?></td>
                                    <td class="text-center">
                                        <a href="pemilik_rumah_detail.php?id=<?= encrypt_id($row['rumah_pemilik_id']) ?>" class="btn btn-sm btn-outline-info px-3" title="Detail">
                                            <i class="bi bi-file-earmark-text"></i> Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted italic">⚠️ Tidak ada data ditemukan</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <div class="card-footer bg-white py-3">
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link px-3" href="?page=<?= $page - 1 ?>&q=<?= urlencode($keyword) ?>">Prev</a>
                    </li>
                    <?php
                    $adjacents = 2;
                    $startPage = max(1, $page - $adjacents);
                    $endPage = min($pages, $page + $adjacents);
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link px-3" href="?page=<?= $i ?>&q=<?= urlencode($keyword) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                        <a class="page-link px-3" href="?page=<?= $page + 1 ?>&q=<?= urlencode($keyword) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../views/footer.php'; ?>
