<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');

check_auth(['superadmin', 'operator', 'admin']);

$user = $_SESSION['user'];
$role = $user['role'];
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0 text-gray-800 fw-bold"><i class="bi bi-file-earmark-pdf me-2"></i>Pusat Laporan PDF</h1>
    </div>

    <!-- Info Card -->
    <div class="card shadow-sm border-0 mb-4 bg-gradient-info text-white">
        <div class="card-body p-4 text-center">
            <h5 class="fw-bold mb-2">Sistem Pelaporan Mandiri</h5>
            <p class="mb-0 opacity-75">Gunakan menu di bawah ini untuk mencetak data warga secara resmi dalam format PDF yang rapi dan siap cetak.</p>
        </div>
    </div>

    <div class="row">
        <!-- Report 1 -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card h-100 shadow-sm border-0 border-top border-primary border-4">
                <div class="card-body text-center p-4">
                    <div class="icon-circle bg-light text-primary mb-3 mx-auto"><i class="bi bi-person-lines-fill"></i></div>
                    <h5 class="fw-bold mb-2">Daftar Kepala Keluarga</h5>
                    <p class="small text-muted mb-4">Menampilkan daftar seluruh Kepala Keluarga (KK) yang terdaftar di RT 03/14.</p>
                    <a href="export_pdf_laporan_nama_kk.php" class="btn btn-primary w-100 py-2"><i class="bi bi-download me-1"></i> Unduh PDF</a>
                </div>
            </div>
        </div>

        <!-- Report 2 -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card h-100 shadow-sm border-0 border-top border-success border-4">
                <div class="card-body text-center p-4">
                    <div class="icon-circle bg-light text-success mb-3 mx-auto"><i class="bi bi-people-fill"></i></div>
                    <h5 class="fw-bold mb-2">Seluruh Data Warga</h5>
                    <p class="small text-muted mb-4">Data lengkap seluruh warga (KK & Anggota) yang tercatat dalam sistem.</p>
                    <a href="export_pdf_laporan_nama_warga.php" class="btn btn-success w-100 py-2"><i class="bi bi-download me-1"></i> Unduh PDF</a>
                </div>
            </div>
        </div>

        <!-- Report 3 -->
        <div class="col-xl-4 col-md-12 mb-4">
            <div class="card h-100 shadow-sm border-0 border-top border-warning border-4">
                <div class="card-body text-center p-4">
                    <div class="icon-circle bg-light text-warning mb-3 mx-auto"><i class="bi bi-house-check"></i></div>
                    <h5 class="fw-bold mb-2">Statistik Penghuni</h5>
                    <p class="small text-muted mb-4">Data Kepala Keluarga beserta jumlah jiwa/anggota di setiap unit rumah.</p>
                    <a href="export_pdf_laporan_nama_kk_jumlah_penghuni.php" class="btn btn-warning text-white w-100 py-2"><i class="bi bi-download me-1"></i> Unduh PDF</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Future Placeholder -->
    <div class="card border-0 bg-light py-4 text-center">
        <div class="card-body">
            <i class="bi bi-plus-circle text-muted" style="font-size: 2rem;"></i>
            <p class="text-muted mt-2 mb-0">Laporan tambahan akan ditambahkan secara berkala.</p>
        </div>
    </div>
</div>

<?php include '../../views/footer.php'; ?>
