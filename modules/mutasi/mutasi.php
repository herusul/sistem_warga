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
$count_sql = "SELECT COUNT(*) as total FROM warga WHERE is_delete IS NULL";
if ($keyword !== '') {
    $count_sql .= " AND warga_nama LIKE ?";
}
$stmt_count = mysqli_prepare($conn, $count_sql);
if ($keyword !== '') {
    mysqli_stmt_bind_param($stmt_count, "s", $search_param);
}
mysqli_stmt_execute($stmt_count);
$total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
$pages = ceil($total / $limit);

// Query data mutasi
$mutasi_sql = "SELECT a.warga_id, a.warga_nama, r.ref_nama AS status_mutasi, m.warga_mutasi_tanggal, m.warga_mutasi_keterangan,
    IF(h.warga_id IS NULL, g.`ref_nama`, h.status_keluarga) AS hubungan_keluarga,
    IF(h.warga_id IS NULL, CAST(rm.rumah_nomor_tampil AS INT), h.nomor_rumah) AS nomor_rumah_tampil
    FROM warga a
    LEFT JOIN warga_mutasi m ON a.warga_id = m.warga_id AND m.is_aktif = 1
    LEFT JOIN referensi r ON r.ref_id = m.ref_id_status_aktif AND r.ref_kategori = 'status_aktif'
    LEFT JOIN referensi g ON g.`ref_id`=a.`ref_id_hubungan_keluarga` AND g.`ref_kategori`='hubungan_keluarga'
    LEFT JOIN warga_rumah wr ON wr.`warga_id`=a.`warga_id` AND wr.`is_aktif`=1
    LEFT JOIN rumah rm ON rm.`rumah_id`=wr.`rumah_id`
    LEFT JOIN (
        SELECT c.`warga_id`, c.`warga_nama`,
        CASE WHEN c.`ref_id_hubungan_keluarga`=49 THEN CONCAT(c.`warga_hubungan_keluarga`,' (KK : ',a.warga_nama,')')
        WHEN a.`ref_id_hubungan_keluarga`<>49 THEN CONCAT(h.`ref_nama`,' (KK : ',a.warga_nama,')')
        ELSE '' END AS status_keluarga, 
        CAST(d.rumah_nomor_tampil AS INT) nomor_rumah
        FROM warga a
        JOIN warga c ON c.`warga_parent`=a.`warga_id`
        JOIN referensi h ON h.`ref_id`=c.`ref_id_hubungan_keluarga` AND h.`ref_kategori`='hubungan_keluarga'
        JOIN warga_rumah f ON a.warga_id=f.`warga_id` AND f.`is_aktif`=1
        JOIN rumah d ON d.`rumah_id`=f.`rumah_id` AND d.`is_aktif`=1
    ) h ON h.warga_id=a.warga_id
    WHERE a.is_delete IS NULL";

if ($keyword !== '') {
    $mutasi_sql .= " AND a.warga_nama LIKE ?";
}

$mutasi_sql .= " ORDER BY nomor_rumah_tampil, g.`ref_id`, hubungan_keluarga, a.warga_nama ASC LIMIT ?, ?";

$stmt_data = mysqli_prepare($conn, $mutasi_sql);
if ($keyword !== '') {
    mysqli_stmt_bind_param($stmt_data, "sii", $search_param, $start, $limit);
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
        <h1 class="h4 mb-0 text-gray-800 fw-bold"><i class="bi bi-clock-history me-2"></i>Daftar Mutasi Warga</h1>
    </div>

    <!-- Search Card -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body py-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" value="<?= e($keyword) ?>" class="form-control border-start-0" placeholder="Cari nama warga...">
                    </div>
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-primary px-4">Cari</button>
                    <a href="mutasi.php" class="btn btn-light border">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary">Log Mutasi Kependudukan</h6>
            <span class="badge bg-light text-primary border border-primary px-3"><?= number_format($total) ?> Total Data</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-center">
                            <th style="width: 60px;">No.</th>
                            <th class="text-start">Nama Warga</th>
                            <th>Status Domisili</th>
                            <th>Tanggal Status</th>
                            <th class="text-start">Keterangan</th>
                            <th style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($mutasi_query) > 0) : ?>
                            <?php $no = $start + 1; while ($row = mysqli_fetch_assoc($mutasi_query)) : 
                                $status_badge = match($row['status_mutasi']) {
                                    'Menetap di WP' => 'bg-success',
                                    'Pindah dari WP' => 'bg-warning text-dark',
                                    'Meninggal Dunia' => 'bg-danger',
                                    default => 'bg-secondary'
                                };
                            ?>
                                <tr>
                                    <td class="text-center text-muted small"><?= $no++ ?></td>
                                    <td>
                                        <div class="fw-bold"><?= e($row['warga_nama']) ?></div>
                                        <div class="small text-muted">No. Rumah: <?= e($row['nomor_rumah_tampil'] ?: '-') ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $status_badge ?> small"><?= e($row['status_mutasi'] ?: 'Belum ada') ?></span>
                                    </td>
                                    <td class="text-center small"><?= e($row['warga_mutasi_tanggal'] ?: '-') ?></td>
                                    <td class="small"><?= e($row['warga_mutasi_keterangan'] ?: '-') ?></td>
                                    <td class="text-center">
                                        <a href="mutasi_detail.php?id=<?= encrypt_id($row['warga_id']) ?>" class="btn btn-sm btn-outline-info" title="Detail"><i class="bi bi-file-earmark-text"></i> Detail</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted italic">⚠️ Tidak ada data ditemukan</td></tr>
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
