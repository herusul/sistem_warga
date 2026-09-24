<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');

// Dapat diakses oleh semua pengguna yang sudah login (user, operator, superadmin)
check_auth();

$user = $_SESSION['user'];
$role = $user['role'];

// Konfigurasi pagination
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start = ($page - 1) * $limit;

// Ambil filter pencarian
$q_nama = isset($_GET['q_nama']) ? trim($_GET['q_nama']) : '';
$q_rumah = isset($_GET['q_rumah']) ? trim($_GET['q_rumah']) : '';
$q_gang = isset($_GET['gang']) ? trim($_GET['gang']) : '';
$q_status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Default kosong: data baru muncul setelah klik Cari (minimal satu filter terisi)
$has_filter = ($q_nama !== '' || $q_rumah !== '' || $q_gang !== '' || $q_status !== '');

// Bangun klausul WHERE
$where = " AND a.is_delete IS NULL ";
$params = [];
$types = "";

if ($q_nama !== '') {
    $where .= " AND a.warga_nama LIKE ? ";
    $search_nama = "%$q_nama%";
    $params[] = $search_nama;
    $types .= "s";
}

if ($q_rumah !== '') {
    $where .= " AND (d.rumah_nomor LIKE ? OR d.rumah_nomor_tampil LIKE ? OR h.rumah_nomor LIKE ? OR h.rumah_nomor_tampil LIKE ?) ";
    $search_rumah = "%$q_rumah%";
    $params[] = $search_rumah;
    $params[] = $search_rumah;
    $params[] = $search_rumah;
    $params[] = $search_rumah;
    $types .= "ssss";
}

if ($q_gang !== '') {
    $where .= " AND (e.ref_nama = ? OR h.gang = ?) ";
    $params[] = $q_gang;
    $params[] = $q_gang;
    $types .= "ss";
}

if ($q_status !== '') {
    $where .= " AND c.ref_nama = ? ";
    $params[] = $q_status;
    $types .= "s";
}

// Subquery informasi hunian + status keluarga (persis warga.php, format Gg. dibiarkan pakai spasi)
$subquery_keluarga = "
    SELECT c.warga_id, c.warga_nama,
    CASE WHEN c.ref_id_hubungan_keluarga=49 THEN CONCAT(c.warga_hubungan_keluarga,' (KK : ',a.warga_nama,')')
    WHEN a.ref_id_hubungan_keluarga<>49 THEN CONCAT(h.ref_nama,' (KK : ',a.warga_nama,')')
    ELSE '' END AS status_keluarga, a.warga_nama AS nama_kk, c.warga_parent,
    d.rumah_nomor_tampil, d.rumah_nomor,
    IF(e.ref_id=20, e.ref_nama, CONCAT('Gg. ', e.ref_nama)) AS gang_tampil, e.ref_nama AS gang
    FROM warga a
    JOIN referensi g ON g.ref_id=a.ref_id_hubungan_keluarga AND g.ref_kategori='hubungan_keluarga'
    JOIN warga c ON c.warga_parent = a.warga_id
    JOIN referensi h ON h.ref_id=c.ref_id_hubungan_keluarga AND h.ref_kategori='hubungan_keluarga'
    JOIN warga_rumah f ON a.warga_id = f.warga_id AND f.is_aktif = 1
    JOIN rumah d ON d.rumah_id = f.rumah_id AND d.is_aktif = 1
    JOIN referensi e ON e.ref_id = d.ref_id_gang AND e.ref_kategori = 'gang'
";

// Hitung total hasil (default kosong: skip query sampai ada filter)
$total = 0;
$pages = 0;
$result = null;

if ($has_filter) {
// Hitung total hasil
$count_sql = "
    SELECT COUNT(*) AS total
    FROM warga a
    LEFT JOIN warga_mutasi b ON a.warga_id = b.warga_id AND b.is_aktif = 1
    LEFT JOIN referensi c ON c.ref_id = b.ref_id_status_aktif AND c.ref_kategori = 'status_aktif'
    LEFT JOIN ($subquery_keluarga) h ON h.warga_id = a.warga_id
    LEFT JOIN warga_rumah f ON a.warga_id = f.warga_id AND f.is_aktif = 1
    LEFT JOIN rumah d ON d.rumah_id = f.rumah_id
    LEFT JOIN referensi e ON e.ref_id = d.ref_id_gang AND e.ref_kategori = 'gang'
    WHERE 1=1 $where
";

$stmt_count = mysqli_prepare($conn, $count_sql);
if ($types !== "") {
    mysqli_stmt_bind_param($stmt_count, $types, ...$params);
}
mysqli_stmt_execute($stmt_count);
$total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'] ?? 0;
$pages = ceil($total / $limit);

// Ambil data direktori bersih (Clean Table) + Status Keluarga persis warga.php
$data_sql = "
    SELECT a.warga_id, a.warga_nama,
    IF(h.warga_id IS NULL, g.ref_nama, h.status_keluarga) AS hubungan_keluarga,
    IF(h.warga_id IS NULL, d.rumah_nomor_tampil, h.rumah_nomor_tampil) AS rumah_nomor_tampil,
    IF(h.warga_id IS NULL, d.rumah_nomor, h.rumah_nomor) AS rumah_nomor,
    IF(h.warga_id IS NULL, IF(e.ref_id=20, e.ref_nama, CONCAT('Gg. ', e.ref_nama)), h.gang_tampil) AS gang_tampil,
    IFNULL(c.ref_nama, 'Menetap di WP') AS status_domisili
    FROM warga a
    LEFT JOIN warga_mutasi b ON a.warga_id = b.warga_id AND b.is_aktif = 1
    LEFT JOIN referensi c ON c.ref_id = b.ref_id_status_aktif AND c.ref_kategori = 'status_aktif'
    LEFT JOIN ($subquery_keluarga) h ON h.warga_id = a.warga_id
    LEFT JOIN warga_rumah f ON a.warga_id = f.warga_id AND f.is_aktif = 1
    LEFT JOIN rumah d ON d.rumah_id = f.rumah_id
    LEFT JOIN referensi e ON e.ref_id = d.ref_id_gang AND e.ref_kategori = 'gang'
    LEFT JOIN referensi g ON g.ref_id = a.ref_id_hubungan_keluarga AND g.ref_kategori = 'hubungan_keluarga'
    WHERE 1=1 $where
    ORDER BY rumah_nomor, IF(h.warga_parent IS NULL, a.warga_id, h.warga_parent), g.ref_id, hubungan_keluarga, a.warga_nama ASC
    LIMIT ?, ?
";

$stmt_data = mysqli_prepare($conn, $data_sql);
$types_data = $types . "ii";
$params_data = array_merge($params, [$start, $limit]);
mysqli_stmt_bind_param($stmt_data, $types_data, ...$params_data);
mysqli_stmt_execute($stmt_data);
$result = mysqli_stmt_get_result($stmt_data);
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container-fluid">
    <!-- Header Page -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">🔍 Pencarian Data Warga</h1>
            <p class="text-muted small mb-0">Direktori alamat dan domisili warga RT 03/14 Wahana Praja I</p>
        </div>
        <span class="badge bg-primary px-3 py-2 fs-6">
            <i class="bi bi-people me-1"></i> <?= number_format($total) ?> Warga Ditemukan
        </span>
    </div>

    <!-- Form Pencarian Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 fw-bold text-primary"><i class="bi bi-funnel me-1"></i> Filter Pencarian</h6>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">Nama Warga</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-person"></i></span>
                        <input type="text" name="q_nama" class="form-control border-start-0" placeholder="Ketik nama warga..." value="<?= e($q_nama) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-uppercase text-muted">Nomor Rumah</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-house-door"></i></span>
                        <input type="text" name="q_rumah" class="form-control border-start-0" placeholder="Contoh: 12 atau WP 12" value="<?= e($q_rumah) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-uppercase text-muted">Gang / Jalan</label>
                    <select name="gang" class="form-select">
                        <option value="">Semua Gang</option>
                        <?php
                        $gang_res = mysqli_query($conn, "SELECT ref_nama FROM referensi WHERE ref_kategori='gang' AND is_aktif=1 ORDER BY ref_nama ASC");
                        while ($g = mysqli_fetch_assoc($gang_res)) :
                        ?>
                            <option value="<?= e($g['ref_nama']) ?>" <?= $q_gang === $g['ref_nama'] ? 'selected' : '' ?>><?= e($g['ref_nama']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">Status Domisili</label>
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="Menetap di WP" <?= $q_status === 'Menetap di WP' ? 'selected' : '' ?>>Menetap di WP</option>
                        <option value="Pindah dari WP" <?= $q_status === 'Pindah dari WP' ? 'selected' : '' ?>>Pindah dari WP</option>
                        <option value="Meninggal Dunia" <?= $q_status === 'Meninggal Dunia' ? 'selected' : '' ?>>Meninggal Dunia</option>
                        <option value="Tidak Tinggal di WP" <?= $q_status === 'Tidak Tinggal di WP' ? 'selected' : '' ?>>Tidak Tinggal di WP</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-search me-1"></i> Cari</button>
                    <a href="pencarian.php" class="btn btn-outline-secondary py-2" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Bersih (Clean Table) -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-table me-2"></i>Daftar Hasil Pencarian</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 60px;">No.</th>
                            <th>Nama Warga</th>
                            <th>Status Keluarga</th>
                            <th class="text-center" style="width: 160px;">Nomor Rumah</th>
                            <th>Nama Gang / Jalan</th>
                            <th class="text-center" style="width: 180px;">Status Tinggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$has_filter): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-funnel fs-1 d-block mb-2 text-muted opacity-50"></i>
                                    Gunakan filter lalu klik Cari untuk menampilkan data.
                                </td>
                            </tr>
                        <?php elseif ($total > 0): ?>
                            <?php 
                            $no = $start + 1; 
                            while ($row = mysqli_fetch_assoc($result)) : 
                                $status_badge = match($row['status_domisili']) {
                                    'Menetap di WP'     => 'bg-success',
                                    'Pindah dari WP'    => 'bg-warning text-dark',
                                    'Meninggal Dunia'   => 'bg-danger',
                                    'Tidak Tinggal di WP' => 'bg-secondary',
                                    default             => 'bg-info'
                                };
                                $no_rumah_str = !empty($row['rumah_nomor_tampil']) ? 'WP ' . e($row['rumah_nomor_tampil']) : '-';
                            ?>
                                <tr>
                                    <td class="text-center text-muted small fw-semibold"><?= $no++ ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($row['warga_nama']) ?></div>
                                    </td>
                                    <td class="small"><?= e($row['hubungan_keluarga'] ?? '') ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border px-3 py-2 fw-bold">
                                            <i class="bi bi-house-door me-1 text-primary"></i> <?= $no_rumah_str ?>
                                        </span>
                                    </td>
                                    <td>
                                        <i class="bi bi-geo-alt text-muted me-1"></i> <?= e($row['gang_tampil'] ?: '-') ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $status_badge ?> px-3 py-2 small">
                                            <?= e($row['status_domisili']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-search fs-1 d-block mb-2 text-muted opacity-50"></i>
                                    Tidak ada data warga yang cocok dengan kriteria pencarian Anda.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <div class="card-footer bg-white py-3">
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link px-3" href="?page=<?= $page - 1 ?>&q_nama=<?= urlencode($q_nama) ?>&q_rumah=<?= urlencode($q_rumah) ?>&gang=<?= urlencode($q_gang) ?>&status=<?= urlencode($q_status) ?>">Prev</a>
                    </li>
                    <?php
                    $start_p = max(1, $page - 2);
                    $end_p = min($pages, $page + 2);
                    for ($i = $start_p; $i <= $end_p; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link px-3" href="?page=<?= $i ?>&q_nama=<?= urlencode($q_nama) ?>&q_rumah=<?= urlencode($q_rumah) ?>&gang=<?= urlencode($q_gang) ?>&status=<?= urlencode($q_status) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                        <a class="page-link px-3" href="?page=<?= $page + 1 ?>&q_nama=<?= urlencode($q_nama) ?>&q_rumah=<?= urlencode($q_rumah) ?>&gang=<?= urlencode($q_gang) ?>&status=<?= urlencode($q_status) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../views/footer.php'; ?>
