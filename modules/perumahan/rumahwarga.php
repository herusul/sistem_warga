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

// Query total
$count_sql = "SELECT COUNT(*) AS total
FROM warga a
LEFT JOIN warga_rumah b ON a.warga_id=b.warga_id #AND b.is_aktif=1
LEFT JOIN rumah c ON c.rumah_id=b.rumah_id
WHERE a.is_delete IS NULL AND a.ref_id_hubungan_keluarga=34";

if ($keyword !== '') {
    $count_sql .= " AND (a.warga_nama LIKE ? OR c.rumah_nomor LIKE ?)";
}

$stmt_count = mysqli_prepare($conn, $count_sql);
if ($keyword !== '') {
    mysqli_stmt_bind_param($stmt_count, "ss", $search_param, $search_param);
}
mysqli_stmt_execute($stmt_count);
$total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
$pages = ceil($total / $limit);

// Query data
$mutasi_sql = "SELECT a.warga_id, a.warga_nama, c.rumah_nomor, c.rumah_nomor_tampil, d.ref_nama AS status_rumah, b.warga_rumah_tanggal, IF(b.is_aktif=1, 'Aktif', 'Tidak Aktif') AS is_aktif, f.ref_nama AS status_aktif
    FROM warga a
    LEFT JOIN warga_rumah b ON a.warga_id=b.warga_id #AND b.is_aktif=1
    LEFT JOIN rumah c ON c.rumah_id=b.rumah_id
    LEFT JOIN referensi d ON d.ref_id=b.ref_id_status_rumah AND d.ref_kategori='status_rumah'
    LEFT JOIN warga_mutasi e ON e.warga_id=a.warga_id AND e.is_aktif=1
    LEFT JOIN referensi f ON f.ref_id=e.ref_id_status_aktif AND f.ref_kategori='status_aktif'
    WHERE a.is_delete IS NULL AND a.ref_id_hubungan_keluarga=34";

if ($keyword !== '') {
    $mutasi_sql .= " AND (a.warga_nama LIKE ? OR c.rumah_nomor LIKE ?)";
}

$mutasi_sql .= " ORDER BY c.rumah_nomor, f.ref_id, a.warga_nama ASC LIMIT ?, ?";

$stmt_data = mysqli_prepare($conn, $mutasi_sql);
if ($keyword !== '') {
    mysqli_stmt_bind_param($stmt_data, "ssii", $search_param, $search_param, $start, $limit);
} else {
    mysqli_stmt_bind_param($stmt_data, "ii", $start, $limit);
}
mysqli_stmt_execute($stmt_data);
$mutasi_query = mysqli_stmt_get_result($stmt_data);
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0 text-gray-800 fw-bold"><i class="bi bi-person-workspace me-2"></i>Daftar Rumah Warga</h1>
        <div class="text-muted small italic">Khusus untuk Kepala Keluarga</div>
    </div>

    <!-- Search Card -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body py-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" value="<?= e($keyword) ?>" class="form-control border-start-0" placeholder="Cari nama warga / nomor rumah...">
                    </div>
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-primary px-4">Cari</button>
                    <a href="rumahwarga.php" class="btn btn-light border">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary">Pemetaan Hunian Warga</h6>
            <span class="badge bg-light text-primary border border-primary px-3"><?= number_format($total) ?> data ditemukan</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-center">
                            <th style="width: 60px;">No.</th>
                            <th class="text-start">Nama Kepala Keluarga</th>
                            <th>Nomor Rumah</th>
                            <th>Status Kepemilikan</th>
                            <th>Mulai Tinggal</th>
                            <th>Status Domisili</th>
                            <th>Status Data</th>							
                            <th style="width: 100px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($mutasi_query) > 0) : ?>
                            <?php $no = $start + 1; while ($row = mysqli_fetch_assoc($mutasi_query)) : 
                                $status_badge = match($row['status_aktif']) {
                                    'Menetap di WP' => 'bg-success',
                                    'Pindah dari WP' => 'bg-warning text-dark',
                                    default => 'bg-secondary'
                                };
                            ?>
                            <tr>
                                <td class="text-center text-muted small"><?= $no++ ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($row['warga_nama']) ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold text-primary">No. <?= e($row['rumah_nomor_tampil'] ?: '-') ?></span>
                                </td>
                                <td class="text-center small">
                                    <span class="badge bg-light text-dark border"><?= e($row['status_rumah']) ?: '-' ?></span>
                                </td>
                                <td class="text-center small"><?= e($row['warga_rumah_tanggal'] ?: '-') ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $status_badge ?> small"><?= e($row['status_aktif']) ?></span>
                                </td>
								<td class="text-center">
                                    <span class="badge bg-light text-dark border"><?= e($row['is_aktif']) ?: '-' ?></span>
                                </td>
                                <td class="text-center">
                                    <a href="rumahwarga_detail.php?id=<?= encrypt_id($row['warga_id']) ?>" class="btn btn-sm btn-outline-info" title="Detail"><i class="bi bi-eye"></i> Detail</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">⚠️ Tidak ada data ditemukan</td></tr>
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
