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
if ($page < 1) $page = 1;
$start = ($page - 1) * $limit;

// Filter pencarian & kategori
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$kategori = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';
$search_param = "%$q%";

$where = " WHERE (a.aset_nama LIKE ? OR a.aset_lokasi LIKE ?) ";
$params = [$search_param, $search_param];
$types = "ss";

if ($kategori === 'tetap' || $kategori === 'habis_pakai') {
    $where .= " AND a.aset_kategori = ? ";
    $params[] = $kategori;
    $types .= "s";
}

// Hitung total (tersedia = total - sedang dipinjam)
$sql_count = "SELECT COUNT(*) AS total FROM aset_barang a $where";
$stmt_count = mysqli_prepare($conn, $sql_count);
mysqli_stmt_bind_param($stmt_count, $types, ...$params);
mysqli_stmt_execute($stmt_count);
$total_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
$total_pages = ceil($total_data / $limit);

$sql_data = "SELECT a.aset_id, a.aset_nama, a.aset_kategori, a.aset_jumlah_total,
    a.aset_kondisi, a.aset_lokasi,
    IFNULL(p.dipinjam, 0) AS dipinjam,
    (a.aset_jumlah_total - IFNULL(p.dipinjam, 0)) AS tersedia
FROM aset_barang a
LEFT JOIN (
    SELECT aset_id, SUM(jumlah) AS dipinjam
    FROM aset_peminjaman WHERE is_aktif = 1 GROUP BY aset_id
) p ON p.aset_id = a.aset_id
$where
ORDER BY a.aset_nama ASC
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
        <h1 class="h4 mb-0 text-gray-800 fw-bold"><i class="bi bi-box-seam me-2"></i>Data Aset & Barang</h1>
        <div>
            <a href="peminjaman.php" class="btn btn-sm btn-info px-3 py-2"><i class="bi bi-arrow-left-right me-1"></i> Peminjaman</a>
            <a href="pemakaian.php" class="btn btn-sm btn-warning px-3 py-2"><i class="bi bi-dash-circle me-1"></i> Pemakaian</a>
            <a href="aset_tambah.php" class="btn btn-sm btn-success px-3 py-2"><i class="bi bi-plus-lg me-1"></i> Tambah Barang</a>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'hapus_sukses'): ?>
            <div class="alert alert-success">✅ Data barang berhasil dihapus.</div>
        <?php elseif ($_GET['msg'] === 'hapus_gagal_aktif'): ?>
            <div class="alert alert-danger">❌ Gagal: barang masih memiliki peminjaman aktif. Selesaikan pengembalian terlebih dahulu.</div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Search Card -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body py-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control border-start-0" placeholder="Cari nama barang / lokasi..." value="<?= e($q) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="kategori" class="form-select">
                        <option value="">Semua Kategori</option>
                        <option value="tetap" <?= $kategori === 'tetap' ? 'selected' : '' ?>>Tetap (dipinjam-kembali)</option>
                        <option value="habis_pakai" <?= $kategori === 'habis_pakai' ? 'selected' : '' ?>>Habis Pakai</option>
                    </select>
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-primary px-4">Cari</button>
                    <a href="aset.php" class="btn btn-light border">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary">Daftar Barang</h6>
            <span class="badge bg-light text-primary border border-primary px-3"><?= number_format($total_data) ?> Jenis Barang</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-center">
                            <th style="width: 60px;">No.</th>
                            <th class="text-start">Nama Barang</th>
                            <th>Kategori</th>
                            <th>Total</th>
                            <th>Dipinjam</th>
                            <th>Tersedia</th>
                            <th>Kondisi</th>
                            <th class="text-start">Lokasi</th>
                            <th style="width: 200px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($total_data > 0) : ?>
                            <?php $no = $start + 1; while ($row = mysqli_fetch_assoc($result)) :
                                $kat_badge = $row['aset_kategori'] === 'tetap' ? 'bg-primary' : 'bg-warning text-dark';
                                $kat_label = $row['aset_kategori'] === 'tetap' ? 'Tetap' : 'Habis Pakai';
                                $kond_badge = match($row['aset_kondisi']) {
                                    'Baik' => 'bg-success',
                                    'Rusak ringan' => 'bg-warning text-dark',
                                    'Rusak berat' => 'bg-danger',
                                    default => 'bg-secondary'
                                };
                            ?>
                            <tr>
                                <td class="text-center text-muted small"><?= $no++ ?></td>
                                <td class="fw-bold"><?= e($row['aset_nama']) ?></td>
                                <td class="text-center"><span class="badge <?= $kat_badge ?> small"><?= $kat_label ?></span></td>
                                <td class="text-center fw-bold"><?= number_format($row['aset_jumlah_total']) ?></td>
                                <td class="text-center"><?= number_format($row['dipinjam']) ?></td>
                                <td class="text-center fw-bold text-success"><?= number_format($row['tersedia']) ?></td>
                                <td class="text-center"><span class="badge <?= $kond_badge ?> small"><?= e($row['aset_kondisi']) ?></span></td>
                                <td class="small"><?= e($row['aset_lokasi']) ?: '-' ?></td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="aset_detail.php?id=<?= encrypt_id($row['aset_id']) ?>" class="btn btn-sm btn-outline-info" title="Detail"><i class="bi bi-eye"></i></a>
                                        <a href="aset_edit.php?id=<?= encrypt_id($row['aset_id']) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <a href="aset_hapus.php?id=<?= encrypt_id($row['aset_id']) ?>" onclick="return confirm('Hapus data barang ini beserta riwayatnya?')" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></a>
                                    </div>
                                    <?php if ($row['aset_kategori'] === 'tetap' && $row['tersedia'] > 0) : ?>
                                        <a href="peminjaman_tambah.php?aset_id=<?= encrypt_id($row['aset_id']) ?>" class="btn btn-sm btn-info mt-1" title="Pinjam"><i class="bi bi-arrow-left-right"></i></a>
                                    <?php elseif ($row['aset_kategori'] === 'habis_pakai' && $row['aset_jumlah_total'] > 0) : ?>
                                        <a href="pemakaian_tambah.php?aset_id=<?= encrypt_id($row['aset_id']) ?>" class="btn btn-sm btn-warning mt-1" title="Catat Pakai"><i class="bi bi-dash-circle"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr><td colspan="9" class="text-center py-5 text-muted">⚠️ Tidak ada data ditemukan</td></tr>
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
                        <a class="page-link px-3" href="?page=<?= $page - 1 ?>&q=<?= urlencode($q) ?>&kategori=<?= urlencode($kategori) ?>">Prev</a>
                    </li>
                    <?php
                    $adjacents = 2;
                    $startPage = max(1, $page - $adjacents);
                    $endPage = min($total_pages, $page + $adjacents);
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link px-3" href="?page=<?= $i ?>&q=<?= urlencode($q) ?>&kategori=<?= urlencode($kategori) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link px-3" href="?page=<?= $page + 1 ?>&q=<?= urlencode($q) ?>&kategori=<?= urlencode($kategori) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../views/footer.php'; ?>
