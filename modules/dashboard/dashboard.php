<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');

check_auth();

$user = $_SESSION['user'];
$nama_pengguna = $user['nama'];
$role = $user['role'];

// Ambil filter status dari URL
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'semua';

// Mapping kondisi filter
$where_status = '';
switch ($status_filter) {
    case 'menetap':
        $where_status = "AND warga_id IN (SELECT warga_id FROM warga_mutasi WHERE ref_id_status_aktif = 161 and is_aktif=1)";
        break;
    case 'pindah':
        $where_status = "AND warga_id IN (SELECT warga_id FROM warga_mutasi WHERE ref_id_status_aktif = 162 and is_aktif=1)";
        break;
    case 'meninggal':
        $where_status = "AND warga_id IN (SELECT warga_id FROM warga_mutasi WHERE ref_id_status_aktif = 163 and is_aktif=1)";
        break;
    case 'tidak_tinggal':
        $where_status = "AND warga_id IN (SELECT warga_id FROM warga_mutasi WHERE ref_id_status_aktif = 164 and is_aktif=1)";
        break;
}

// Helper untuk hitung data
function getDashboardCount($conn, $query) {
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['total'];
}

$laki = getDashboardCount($conn, "SELECT COUNT(*) AS total FROM warga WHERE ref_id_jenis_kelamin = '50' AND is_delete IS NULL $where_status");
$perempuan = getDashboardCount($conn, "SELECT COUNT(*) AS total FROM warga WHERE ref_id_jenis_kelamin = '51' AND is_delete IS NULL $where_status");
$total = getDashboardCount($conn, "SELECT COUNT(*) AS total FROM warga WHERE is_delete IS NULL $where_status");
$kepala_keluarga = getDashboardCount($conn, "SELECT COUNT(*) AS total FROM warga WHERE ref_id_hubungan_keluarga = '34' AND is_delete IS NULL $where_status");
$anggota_keluarga = getDashboardCount($conn, "SELECT COUNT(*) AS total FROM warga WHERE (ref_id_hubungan_keluarga IS NULL OR ref_id_hubungan_keluarga <> '34') AND is_delete IS NULL $where_status");

// Waktu saat ini
date_default_timezone_set("Asia/Jakarta");
$nama_hari = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$nama_bulan = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'];
$hari_ini = $nama_hari[date("l")] . ", " . date("d") . " " . $nama_bulan[date("m")] . " " . date("Y");
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>


<style>
    .stat-card {
        transition: all 0.3s ease-in-out;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2) !important;
    }
</style>

<div class="container-fluid">
    <!-- Header Page -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800 fw-bold">🧭 Dashboard Utama</h1>
        <div class="text-muted small"><i class="bi bi-calendar3 me-1"></i> <?= $hari_ini ?></div>
    </div>

    <!-- Welcome Section -->
    <div class="card bg-gradient-primary text-white mb-4 border-0">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h4 class="fw-bold">Selamat Datang 👋</h4>
                    <p class="mb-0 opacity-75">Anda login sebagai <strong><?= e(ucfirst($role)) ?></strong>. Berikut adalah ringkasan data warga RT 03/14 hari ini.</p>
                </div>
                <div class="col-lg-4 text-end d-none d-lg-block">
                    <i class="bi bi-person-workspace" style="font-size: 4rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Info Section -->
    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <h5 class="fw-bold text-dark mb-1">📊 Statistik Kependudukan</h5>
            <p class="text-muted small">Data dinamis berdasarkan status warga</p>
        </div>
        <div class="col-md-6 text-md-end">
            <form method="get" class="d-inline-block">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-funnel"></i></span>
                    <select name="status" class="form-select border-start-0" onchange="this.form.submit()" style="min-width: 200px;">
                        <option value="semua" <?= $status_filter == 'semua' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="menetap" <?= $status_filter == 'menetap' ? 'selected' : '' ?>>Menetap di WP</option>
                        <option value="pindah" <?= $status_filter == 'pindah' ? 'selected' : '' ?>>Pindah dari WP</option>
                        <option value="meninggal" <?= $status_filter == 'meninggal' ? 'selected' : '' ?>>Meninggal Dunia</option>
                        <option value="tidak_tinggal" <?= $status_filter == 'tidak_tinggal' ? 'selected' : '' ?>>Tidak Tinggal di WP</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row">
        <!-- Kepala Keluarga -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card h-100 border-start border-success border-4 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col me-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1 small fw-bold">Kepala Keluarga</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800 fw-bold"><?= number_format($kepala_keluarga) ?> <span class="fs-6 fw-normal text-muted">KK</span></div>
                        </div>
                        <div class="col-auto">
                            <div class="icon-circle bg-success text-white"><i class="bi bi-house-door"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Anggota Keluarga -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card h-100 border-start border-info border-4 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col me-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1 small fw-bold">Anggota Keluarga</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800 fw-bold"><?= number_format($anggota_keluarga) ?> <span class="fs-6 fw-normal text-muted">Jiwa</span></div>
                        </div>
                        <div class="col-auto">
                            <div class="icon-circle bg-info text-white"><i class="bi bi-people"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Laki-laki -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card h-100 border-start border-primary border-4 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col me-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1 small fw-bold">Laki-laki</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800 fw-bold"><?= number_format($laki) ?> <span class="fs-6 fw-normal text-muted">Jiwa</span></div>
                        </div>
                        <div class="col-auto">
                            <div class="icon-circle bg-primary text-white"><i class="bi bi-gender-male"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Perempuan -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card h-100 border-start border-danger border-4 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col me-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1 small fw-bold">Perempuan</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800 fw-bold"><?= number_format($perempuan) ?> <span class="fs-6 fw-normal text-muted">Jiwa</span></div>
                        </div>
                        <div class="col-auto">
                            <div class="icon-circle bg-danger text-white"><i class="bi bi-gender-female"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Row -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card stat-card bg-gradient-info text-white border-0 py-3">
                <div class="card-body text-center">
                    <h2 class="fw-bold mb-0"><?= number_format($total) ?> <span class="fs-4 fw-normal">Total Jiwa Terdaftar</span></h2>
                    <p class="mb-0 opacity-75">Statistik berdasarkan filter yang dipilih</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../views/footer.php'; ?>
