<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Ambil filter status dari URL
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'menetap';

// Mapping kondisi filter (Sama dengan dashboard.php)
$where_status = '';
switch ($status_filter) {
    case 'menetap':
        $where_status = "AND warga_id IN (SELECT warga_id FROM warga_mutasi WHERE ref_id_status_aktif = 161 AND is_aktif=1)";
        break;
    case 'pindah':
        $where_status = "AND warga_id IN (SELECT warga_id FROM warga_mutasi WHERE ref_id_status_aktif = 162 AND is_aktif=1)";
        break;
    case 'meninggal':
        $where_status = "AND warga_id IN (SELECT warga_id FROM warga_mutasi WHERE ref_id_status_aktif = 163 AND is_aktif=1)";
        break;
    case 'tidak_tinggal':
        $where_status = "AND warga_id IN (SELECT warga_id FROM warga_mutasi WHERE ref_id_status_aktif = 164 AND is_aktif=1)";
        break;
    default:
        $where_status = ""; // Semua
}

// Fungsi helper untuk mengambil jumlah
function getPublicCount($conn, $query) {
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['total'];
}

$laki = getPublicCount($conn, "SELECT COUNT(*) AS total FROM warga WHERE ref_id_jenis_kelamin = '50' AND is_delete IS NULL $where_status");
$perempuan = getPublicCount($conn, "SELECT COUNT(*) AS total FROM warga WHERE ref_id_jenis_kelamin = '51' AND is_delete IS NULL $where_status");
$total = getPublicCount($conn, "SELECT COUNT(*) AS total FROM warga WHERE is_delete IS NULL $where_status");
$kk = getPublicCount($conn, "SELECT COUNT(*) AS total FROM warga WHERE ref_id_hubungan_keluarga = '34' AND is_delete IS NULL $where_status");
$anggota = getPublicCount($conn, "SELECT COUNT(*) AS total FROM warga WHERE (ref_id_hubungan_keluarga IS NULL OR ref_id_hubungan_keluarga <> '34') AND is_delete IS NULL $where_status");

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Publik - SI Warga WP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="../../assets/img/favicon.ico" type="image/x-icon">    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --primary: #4e73df; --success: #1cc88a; --info: #36b9cc; --warning: #f6c23e; }
        body { background-color: #f4f7fe; font-family: 'Inter', sans-serif; color: #333; }
        .hero-section { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); color: white; padding: 40px 0 50px 0; border-radius: 0 0 50px 50px; margin-bottom: -70px; }
        .stat-card { background: white; border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); transition: transform 0.3s ease; overflow: hidden; height: 100%; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 15px; }
        .bg-gradient-primary { background: linear-gradient(45deg, #4e73df, #6e8efb); }
        .bg-gradient-success { background: linear-gradient(45deg, #1cc88a, #45d8a1); }
        .bg-gradient-info { background: linear-gradient(45deg, #36b9cc, #51daef); }
        .bg-gradient-warning { background: linear-gradient(45deg, #f6c23e, #f8d47b); }
        .navbar-transparent { background: transparent; padding-top: 20px; z-index: 100; }
        .btn-login-header { background: rgba(255, 255, 255, 0.1); color: white; border: 1px solid rgba(255,255,255,0.3); border-radius: 10px; padding: 8px 18px; backdrop-filter: blur(5px); transition: all 0.3s; }
        .btn-login-header:hover { background: white; color: #224abe; }
        
        /* Filter Styling */
        .filter-container { background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); border-radius: 15px; padding: 5px 15px; display: inline-flex; align-items: center; margin-top: 20px; }
        .filter-select { background: transparent; border: none; color: white; font-weight: 500; cursor: pointer; padding: 8px; outline: none; }
        .filter-select option { color: #333; background: white; }
    </style>
</head>
<body>
    <nav class="navbar navbar-transparent position-absolute w-100">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand text-white fw-bold fs-4" href="index.php">📟 SI Warga WP</a>
            <?php if (isset($_SESSION['user'])): ?>
                <a href="modules/dashboard/dashboard.php" class="btn btn-login-header">Login <i class="bi bi-arrow-right"></i></a>
            <?php else: ?>
                <a href="login.php" class="btn btn-login-header">Login <i class="bi bi-box-arrow-in-right"></i></a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="hero-section text-center">
        <div class="container mt-4">
            <h1 class="display-6 fw-bold mb-2">Selamat Datang</h1>
            <p class="opacity-75 mb-0">Statistik Kependudukan RT 03 RW 14 Wahana Praja I</p>
            
            <!-- Elegant Filter -->
            <form method="get" action="index.php">
                <div class="filter-container">
                    <i class="bi bi-funnel-fill text-white-50 me-2"></i>
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="semua" <?= $status_filter == 'semua' ? 'selected' : '' ?>>🔎 Tampilkan: Semua Warga</option>
                        <option value="menetap" <?= $status_filter == 'menetap' ? 'selected' : '' ?>>🏠 Status: Menetap di WP</option>
                        <option value="pindah" <?= $status_filter == 'pindah' ? 'selected' : '' ?>>🚚 Status: Pindah dari WP</option>
                        <option value="meninggal" <?= $status_filter == 'meninggal' ? 'selected' : '' ?>>🕒 Status: Meninggal Dunia</option>
                        <option value="tidak_tinggal" <?= $status_filter == 'tidak_tinggal' ? 'selected' : '' ?>>🌌 Status: Tidak Tinggal di WP</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="container py-5 mt-0">
        <div class="row g-4 justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="stat-card p-4">
                    <div class="stat-icon bg-gradient-primary text-white"><i class="bi bi-people-fill"></i></div>
                    <h6 class="text-muted fw-bold small mb-1">TOTAL WARGA</h6>
                    <h2 class="fw-bold mb-0"><?= number_format($total) ?> <span class="fs-6 text-muted fw-normal">Jiwa</span></h2>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="stat-card p-4">
                    <div class="stat-icon bg-gradient-success text-white"><i class="bi bi-house-heart-fill"></i></div>
                    <h6 class="text-muted fw-bold small mb-1">KEPALA KELUARGA</h6>
                    <h2 class="fw-bold mb-0"><?= number_format($kk) ?> <span class="fs-6 text-muted fw-normal">KK</span></h2>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="stat-card p-4">
                    <div class="stat-icon bg-gradient-info text-white"><i class="bi bi-person-plus-fill"></i></div>
                    <h6 class="text-muted fw-bold small mb-1">ANGGOTA KELUARGA</h6>
                    <h2 class="fw-bold mb-0"><?= number_format($anggota) ?> <span class="fs-6 text-muted fw-normal">Jiwa</span></h2>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-card p-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted fw-bold small mb-0">LAKI-LAKI</h6>
                        <span class="fw-bold"><?= number_format($laki) ?></span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-primary" style="width: <?= ($total > 0) ? ($laki/$total*100) : 0 ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-card p-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted fw-bold small mb-0">PEREMPUAN</h6>
                        <span class="fw-bold"><?= number_format($perempuan) ?></span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-danger" style="width: <?= ($total > 0) ? ($perempuan/$total*100) : 0 ?>%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-5">
            <p class="text-muted small">&copy; <?= date('Y') ?> Sistem Informasi Warga. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
