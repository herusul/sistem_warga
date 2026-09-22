<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['superadmin', 'operator', 'admin']);

$user = $_SESSION['user'];
$role = $user['role'];

// Konfigurasi paging
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Pencarian & Pagination
$q = isset($_GET['q']) ? $_GET['q'] : '';
$search_param = "%$q%";

$sql_count = "SELECT COUNT(*) AS total
FROM rumah a
JOIN referensi b ON a.ref_id_gang=b.ref_id AND b.ref_kategori='gang'
WHERE a.rumah_nomor LIKE ? OR b.ref_nama LIKE ?";

$stmt_count = mysqli_prepare($conn, $sql_count);
mysqli_stmt_bind_param($stmt_count, "ss", $search_param, $search_param);
mysqli_stmt_execute($stmt_count);
$total_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
$total_pages = ceil($total_data / $limit);

$sql_data = "SELECT a.rumah_id, a.rumah_nomor, a.rumah_nomor_tampil, IF(b.ref_id=20, b.ref_nama, CONCAT('Gg.', b.ref_nama)) AS gang,
a.rumah_luas_tanah, a.rumah_luas_bangunan,a.rumah_keterangan, a.rumah_status 
FROM rumah a
JOIN referensi b ON a.ref_id_gang=b.ref_id AND b.ref_kategori='gang'
WHERE a.rumah_nomor LIKE ? OR b.ref_nama LIKE ?
ORDER BY a.rumah_nomor ASC
LIMIT ?, ?";

$stmt_data = mysqli_prepare($conn, $sql_data);
mysqli_stmt_bind_param($stmt_data, "ssii", $search_param, $search_param, $start, $limit);
mysqli_stmt_execute($stmt_data);
$result = mysqli_stmt_get_result($stmt_data);
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0 text-gray-800 fw-bold"><i class="bi bi-house-door me-2"></i>Data Rumah</h1>
        <?php if ($role != 'pengguna') : ?>
            <a href="rumah_tambah.php" class="btn btn-sm btn-success px-3 py-2"><i class="bi bi-plus-lg me-1"></i> Tambah Rumah</a>
        <?php endif; ?>
    </div>

    <!-- Search Card -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body py-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control border-start-0" placeholder="Cari nomor rumah / gang..." value="<?= e($q) ?>">
                    </div>
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-primary px-4">Cari</button>
                    <a href="rumah.php" class="btn btn-light border">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary">Daftar Kavling & Bangunan</h6>
            <span class="badge bg-light text-primary border border-primary px-3"><?= number_format($total_data) ?> Total Unit</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-center">
                            <th style="width: 60px;">No.</th>
                            <th>Nomor Rumah</th>
                            <th>Gang / Jalan</th>
                            <th>Luas (T/B)</th>
                            <th class="text-start">Keterangan</th>
                            <th>Status</th>
                            <th style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($total_data > 0) : ?>
                            <?php $no = $start + 1; while ($row = mysqli_fetch_assoc($result)) : 
                                $status_badge = match($row['rumah_status']) {
                                    'Dihuni' => 'bg-success',
                                    'Kosong' => 'bg-warning text-dark',
                                    default => 'bg-secondary'
                                };
                            ?>
                            <tr>
                                <td class="text-center text-muted small"><?= $no++ ?></td>
                                <td class="text-center fw-bold text-primary"><?= e($row['rumah_nomor']) ?></td>
                                <td><?= e($row['gang']) ?></td>
                                <td class="text-center small">
                                    <span class="fw-bold"><?= e($row['rumah_luas_tanah']) ?></span> / <?= e($row['rumah_luas_bangunan']) ?> m²
                                </td>
                                <td class="small"><?= e($row['rumah_keterangan']) ?: '-' ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $status_badge ?> small"><?= e($row['rumah_status']) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($role != 'pengguna') : ?>
                                        <div class="btn-group">
                                            <a href="rumah_edit.php?id=<?= encrypt_id($row['rumah_id']) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                            <a href="rumah_hapus.php?id=<?= encrypt_id($row['rumah_id']) ?>" onclick="return confirm('Hapus data rumah ini?')" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></a>
                                        </div>
                                    <?php else : ?>
                                        <span class="text-muted small italic">🔒 Terkunci</span>
                                    <?php endif; ?>
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
