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
			FROM referensi r
			LEFT JOIN koordinator_gang kg ON kg.ref_id_gang = r.ref_id
			LEFT JOIN warga w ON w.warga_id = kg.warga_id
			WHERE r.ref_kategori = 'gang'";
if ($keyword !== '') {
    $count_sql .= " AND (w.warga_nama LIKE ? OR r.ref_nama LIKE ?)";
}
$stmt_count = mysqli_prepare($conn, $count_sql);
if ($keyword !== '') {
    mysqli_stmt_bind_param($stmt_count, "ss", $search_param, $search_param);
}
mysqli_stmt_execute($stmt_count);
$total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
$pages = ceil($total / $limit);

// Query data
$sql = "SELECT 
		kg.*, w.warga_nama, r.ref_id, IF(r.ref_id=20, r.ref_nama, CONCAT('Gg.', r.ref_nama)) AS nama_gang,
		CONCAT('WP ', rm.rumah_nomor_tampil) AS alamat, w.warga_no_hp AS no_hp
		FROM referensi r
		LEFT JOIN koordinator_gang kg ON kg.ref_id_gang = r.ref_id
		LEFT JOIN warga w ON w.warga_id = kg.warga_id
		LEFT JOIN warga_rumah wr ON wr.warga_id=w.warga_id AND wr.is_aktif=1
		LEFT JOIN rumah rm ON rm.rumah_id=wr.rumah_id AND rm.is_aktif=1
		WHERE r.ref_kategori = 'gang'";

if ($keyword !== '') {
    $sql .= " AND (w.warga_nama LIKE ? OR r.ref_nama LIKE ?)";
}

$sql .= " ORDER BY r.ref_nama ASC, w.warga_nama ASC LIMIT ?, ?";

$stmt_data = mysqli_prepare($conn, $sql);
if ($keyword !== '') {
    mysqli_stmt_bind_param($stmt_data, "ssii", $search_param, $search_param, $start, $limit);
} else {
    mysqli_stmt_bind_param($stmt_data, "ii", $start, $limit);
}
mysqli_stmt_execute($stmt_data);
$query = mysqli_stmt_get_result($stmt_data);
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0 text-gray-800 fw-bold"><i class="bi bi-pin-map me-2"></i>Koordinator Gang & Jalan</h1>
    </div>

    <!-- Search Card -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body py-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" value="<?= e($keyword) ?>" class="form-control border-start-0" placeholder="Cari nama koordinator / gang...">
                    </div>
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-primary px-4">Cari</button>
                    <a href="koordinator_gang.php" class="btn btn-light border">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary">Struktur Koordinasi Lingkungan</h6>
            <span class="badge bg-light text-primary border border-primary px-3"><?= number_format($total) ?> Total Jalur</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-center">
                            <th style="width: 60px;">No.</th>
                            <th class="text-start">Nama Gang / Jalan</th>
                            <th class="text-start">Nama Koordinator</th>
                            <th>No. HP</th>
                            <th class="text-start">Alamat di WP</th>
                            <th>Status</th>
                            <th style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($query) > 0) : ?>
                            <?php $no = $start + 1; while ($row = mysqli_fetch_assoc($query)) : ?>
                                <tr>
                                    <td class="text-center text-muted small"><?= $no++ ?></td>
                                    <td class="fw-bold text-dark"><?= e($row['nama_gang']) ?: '-' ?></td>
                                    <td>
                                        <div class="fw-bold text-primary"><?= e($row['warga_nama']) ?: '<em class="text-muted fw-normal">Belum ditentukan</em>' ?></div>
                                    </td>
                                    <td class="text-center small"><?= e($row['no_hp']) ?: '-' ?></td>
                                    <td class="small"><?= e($row['alamat']) ?: '-' ?></td>
                                    <td class="text-center">
                                        <?php if ($row['is_aktif'] == 1): ?>
                                            <span class="badge bg-success small px-3">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border small px-2">Kosong</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="koordinator_gang_detail.php?id=<?= encrypt_id($row['ref_id']) ?>" class="btn btn-sm btn-outline-info px-3" title="Detail">
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
                    <?php for ($i = 1; $i <= $pages; $i++) : ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link px-3" href="?page=<?= e($i) ?>&q=<?= urlencode($keyword) ?>"><?= e($i) ?></a>
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
