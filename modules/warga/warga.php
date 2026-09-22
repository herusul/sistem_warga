<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['superadmin', 'operator', 'admin']);

$user = $_SESSION['user'];
$role = $user['role'];

// Konfigurasi pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start = ($page - 1) * $limit;

// Ambil keyword & filter status & gang
$keyword = isset($_GET['q']) ? $_GET['q'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$gang = isset($_GET['gang']) ? $_GET['gang'] : '';

// Buat query pencarian
$where = " AND 1=1 ";
$params = [];
$types = "";

if ($keyword !== '') {
    $where .= " AND (a.warga_nama LIKE ? OR h.nama_kk LIKE ?)";
    $search_key = "%$keyword%";
    $params[] = $search_key;
    $params[] = $search_key;
    $types .= "ss";
}
if ($status !== '') {
    $where .= " AND c.ref_nama = ?";
    $params[] = $status;
    $types .= "s";
}
if ($gang !== '') {
    $where .= " AND (e.ref_nama = ? OR h.gang = ?)";
    $params[] = $gang;
    $params[] = $gang;
    $types .= "ss";
}

// Hitung total hasil
$count_query = "SELECT COUNT(*) AS total
FROM warga a
LEFT JOIN warga_mutasi b ON a.warga_id=b.warga_id AND b.is_aktif=1
LEFT JOIN referensi c ON c.ref_id=b.ref_id_status_aktif AND c.ref_kategori='status_aktif'
LEFT JOIN (
    SELECT c.`warga_id`, a.warga_nama AS nama_kk, e.ref_nama AS gang
    FROM warga a
    JOIN warga c ON c.`warga_parent`=a.`warga_id`
    JOIN `warga_rumah` f ON a.warga_id=f.`warga_id` AND f.`is_aktif`=1
    JOIN `rumah` d ON d.`rumah_id`=f.`rumah_id` AND d.`is_aktif`=1
    JOIN `referensi` e ON e.`ref_id`=d.`ref_id_gang` AND e.`ref_kategori`='gang'
) h ON h.warga_id=a.warga_id
LEFT JOIN `warga_rumah` f ON a.warga_id=f.`warga_id` AND f.`is_aktif`=1
LEFT JOIN `rumah` d ON d.`rumah_id`=f.`rumah_id`
LEFT JOIN `referensi` e ON e.`ref_id`=d.`ref_id_gang` AND e.`ref_kategori`='gang'
WHERE a.is_delete IS NULL $where ";

$stmt_count = mysqli_prepare($conn, $count_query);
if ($types !== "") mysqli_stmt_bind_param($stmt_count, $types, ...$params);
mysqli_stmt_execute($stmt_count);
$total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
$pages = ceil($total / $limit);

// Ambil data
$data_query = "SELECT a.warga_id, a.`warga_nama`, IF(h.warga_id IS NULL, g.`ref_nama`, h.status_keluarga) AS hubungan_keluarga, h.nama_kk,
IF(h.`warga_parent` IS NULL, a.warga_id, h.`warga_parent`) AS warga_parent,
IF(h.warga_id IS NULL, d.rumah_nomor_tampil, h.rumah_nomor_tampil) AS rumah_nomor_tampil,
IF(h.warga_id IS NULL, d.rumah_nomor, h.rumah_nomor) AS rumah_nomor,
IF(h.warga_id IS NULL, IF(e.ref_id=20, e.ref_nama, CONCAT('Gg.', e.ref_nama)), h.gang_tampil) AS gang_tampil,
a.`warga_no_hp`, c.`ref_nama` AS status_domisili,
a.`warga_foto`
FROM warga a
LEFT JOIN warga_mutasi b ON a.warga_id=b.warga_id AND b.is_aktif=1
LEFT JOIN referensi c ON c.ref_id=b.ref_id_status_aktif AND c.ref_kategori='status_aktif'
LEFT JOIN `warga_rumah` f ON a.warga_id=f.`warga_id` AND f.`is_aktif`=1
LEFT JOIN `rumah` d ON d.`rumah_id`=f.`rumah_id`
LEFT JOIN `referensi` e ON e.`ref_id`=d.`ref_id_gang` AND e.`ref_kategori`='gang'
LEFT JOIN `referensi` g ON g.`ref_id`=a.`ref_id_hubungan_keluarga` AND g.`ref_kategori`='hubungan_keluarga'
LEFT JOIN (
    SELECT c.`warga_id`, c.`warga_nama`,
    CASE WHEN c.`ref_id_hubungan_keluarga`=49 THEN CONCAT(c.`warga_hubungan_keluarga`,' (KK : ',a.warga_nama,')')
    WHEN a.`ref_id_hubungan_keluarga`<>49 THEN CONCAT(h.`ref_nama`,' (KK : ',a.warga_nama,')')
    ELSE '' END AS status_keluarga, a.warga_nama AS nama_kk, c.`warga_parent`,
    d.rumah_nomor_tampil, d.rumah_nomor,
    IF(e.`ref_id`=20, e.`ref_nama`, CONCAT('Gg.', e.`ref_nama`)) AS gang_tampil, e.ref_nama AS gang
    FROM warga a
    JOIN `referensi` g ON g.`ref_id`=a.`ref_id_hubungan_keluarga` AND g.`ref_kategori`='hubungan_keluarga'
    JOIN warga c ON c.`warga_parent`=a.`warga_id`
    JOIN `referensi` h ON h.`ref_id`=c.`ref_id_hubungan_keluarga` AND h.`ref_kategori`='hubungan_keluarga'
    JOIN `warga_rumah` f ON a.warga_id=f.`warga_id` AND f.`is_aktif`=1
    JOIN `rumah` d ON d.`rumah_id`=f.`rumah_id` AND d.`is_aktif`=1
    JOIN `referensi` e ON e.`ref_id`=d.`ref_id_gang` AND e.`ref_kategori`='gang'
) h ON h.warga_id=a.warga_id
WHERE a.is_delete IS NULL $where
ORDER BY rumah_nomor, IF(h.`warga_parent` IS NULL, a.warga_id, h.`warga_parent`), g.`ref_id`, hubungan_keluarga, a.warga_nama ASC LIMIT ?, ?";

$stmt_data = mysqli_prepare($conn, $data_query);
$types_data = $types . "ii";
$params_data = array_merge($params, [$start, $limit]);
mysqli_stmt_bind_param($stmt_data, $types_data, ...$params_data);
mysqli_stmt_execute($stmt_data);
$result = mysqli_stmt_get_result($stmt_data);
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800 fw-bold">👥 Data Warga</h1>
        <?php if ($role != 'pengguna') : ?>
            <a href="warga_tambah.php" class="btn btn-sm btn-success px-3 py-2"><i class="bi bi-plus-lg me-1"></i> Tambah Warga</a>
        <?php endif; ?>
    </div>

    <!-- Filter & Search Card -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-uppercase">Pencarian Nama</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control border-start-0" placeholder="Cari nama warga/KK..." value="<?= e($keyword) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-uppercase">Status Domisili</label>
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="Menetap di WP" <?= $status === 'Menetap di WP' ? 'selected' : '' ?>>Menetap di WP</option>
                        <option value="Pindah dari WP" <?= $status === 'Pindah dari WP' ? 'selected' : '' ?>>Pindah dari WP</option>
                        <option value="Meninggal Dunia" <?= $status === 'Meninggal Dunia' ? 'selected' : '' ?>>Meninggal Dunia</option>
                        <option value="Tidak Tinggal di WP" <?= $status === 'Tidak Tinggal di WP' ? 'selected' : '' ?>>Tidak Tinggal di WP</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-uppercase">Gang / Jalan</label>
                    <select name="gang" class="form-select">
                        <option value="">Semua Gang</option>
                        <?php
                        $gang_query = mysqli_query($conn, "SELECT ref_nama FROM referensi WHERE ref_kategori='gang' ORDER BY ref_nama ASC");
                        while ($g = mysqli_fetch_assoc($gang_query)) :
                        ?>
                            <option value="<?= e($g['ref_nama']) ?>" <?= $gang === $g['ref_nama'] ? 'selected' : '' ?>><?= e($g['ref_nama']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button class="btn btn-primary w-100 py-2">Filter</button>
                    <a href="warga.php" class="btn btn-outline-secondary py-2" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold">Tabel Data Warga</h6>
            <span class="badge bg-primary px-3"><?= number_format($total) ?> Total Data</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center px-3" style="width: 50px;">No.</th>
                            <th>Nama Lengkap</th>
                            <th>Status Keluarga</th>
                            <th class="text-center">No. Rumah</th>
                            <th>Gang / Jalan</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($total > 0): ?>
                            <?php $no = $start + 1; while ($row = mysqli_fetch_assoc($result)) : 
                                $status_class = match($row['status_domisili']) {
                                    'Menetap di WP' => 'bg-success',
                                    'Pindah dari WP' => 'bg-warning text-dark',
                                    'Meninggal Dunia' => 'bg-danger',
                                    default => 'bg-secondary'
                                };
                            ?>
                                <tr>
                                    <td class="text-center text-muted small"><?= $no++ ?></td>
                                    <td>
                                        <div class="fw-bold text-primary"><?= e($row['warga_nama']) ?></div>
                                        <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= e($row['warga_no_hp']) ?: '-' ?></div>
                                    </td>
                                    <td class="small"><?= e($row['hubungan_keluarga']) ?></td>
                                    <td class="text-center fw-bold text-dark"><?= e($row['rumah_nomor_tampil']) ?></td>
                                    <td><?= e($row['gang_tampil']) ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= $status_class ?> small"><?= e($row['status_domisili']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <a href="warga_detail.php?id=<?= encrypt_id($row['warga_id']) ?>" class="btn btn-sm btn-info" title="Detail"><i class="bi bi-eye"></i></a>
                                            <?php if ($role != 'pengguna') : ?>
                                                <a href="warga_edit.php?id=<?= encrypt_id($row['warga_id']) ?>" class="btn btn-sm btn-primary" title="Edit"><i class="bi bi-pencil-square"></i></a>
                                                <form action="warga_hapus.php" method="post" class="d-inline" onsubmit="return confirm('Yakin hapus data ini?')">
                                                    <?= csrf_input() ?>
                                                    <input type="hidden" name="id" value="<?= encrypt_id($row['warga_id']) ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">⚠️ Tidak ada data yang ditemukan</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination inside Card Footer -->
        <?php if ($pages > 1): ?>
        <div class="card-footer bg-white py-3">
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link px-3" href="?page=<?= $page - 1 ?>&q=<?= urlencode($keyword) ?>&status=<?= urlencode($status) ?>&gang=<?= urlencode($gang) ?>">Prev</a>
                    </li>
                    <?php
                    $adjacents = 2;
                    $startPage = max(1, $page - $adjacents);
                    $endPage = min($pages, $page + $adjacents);
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link px-3" href="?page=<?= $i ?>&q=<?= urlencode($keyword) ?>&status=<?= urlencode($status) ?>&gang=<?= urlencode($gang) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                        <a class="page-link px-3" href="?page=<?= $page + 1 ?>&q=<?= urlencode($keyword) ?>&status=<?= urlencode($status) ?>&gang=<?= urlencode($gang) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../views/footer.php'; ?>
