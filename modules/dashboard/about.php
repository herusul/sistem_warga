<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');

check_auth();

$user = $_SESSION['user'];
$role = $user['role'];
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container-fluid mb-5">
    <!-- Header Section -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0 text-gray-800 fw-bold"><i class="bi bi-info-circle me-2"></i>Tentang Sistem</h1>
    </div>

    <div class="row">
        <!-- System Description -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4 overflow-hidden">
                <div class="bg-gradient-primary p-4 text-white">
                    <h4 class="fw-bold mb-0">SI-WARGA WP</h4>
                    <p class="mb-0 opacity-75 small">Sistem Informasi Warga Perumahan Wahana Praja I RT 03/14</p>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary"><i class="bi bi-bullseye me-2"></i>Maksud & Tujuan</h6>
                            <p class="small text-muted">Membantu pengurus RT dalam mengelola data kependudukan secara efektif, efisien, serta memudahkan proses pencarian dan pelaporan data warga secara real-time.</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary"><i class="bi bi-hdd-network me-2"></i>Definisi Sistem</h6>
                            <p class="small text-muted">Aplikasi berbasis web yang menyimpan dan mengelola data kependudukan menggunakan database MySQL.</p>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-stars me-2"></i>Manfaat & Kegunaan</h6>
                    <div class="row row-cols-1 row-cols-md-2 g-3">
                        <div class="col d-flex">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <span class="small">Digitalisasi data warga secara terpusat</span>
                        </div>
                        <div class="col d-flex">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <span class="small">Pencarian & filter data warga</span>
                        </div>
                        <div class="col d-flex">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <span class="small">Ekspor laporan berupa PDF</span>
                        </div>
                        <div class="col d-flex">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <span class="small">Grafik & statistik data warga</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Source Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-database-down me-2"></i>Sumber Data Kependudukan</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">Data pada sistem ini dikompilasi dari partisipasi aktif warga melalui formulir kependudukan berkala:</p>
                    <div class="list-group list-group-flush small">
                        <a href="https://forms.gle/e847hXEnXxU4phNH7" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div><i class="bi bi-ui-checks me-2 text-primary"></i> Formulir Input Kependudukan</div>
                            <span class="badge bg-primary rounded-pill">Isi Data</span>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div><i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i> Arsip Data (2020, 2022, 2025)</div>
                            <i class="bi bi-box-arrow-up-right text-muted"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Version / Changelog -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4 h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-git me-2"></i>Riwayat Pembaruan</h6>
                </div>
                <div class="card-body px-0">
                    <div class="px-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-gradient-primary px-2">v1.2</span>
                            <span class="text-muted extra-small">Mei 2026</span>
                        </div>
                        <ul class="small text-muted ps-3 mb-0">
                            <li>Modern UI/UX Refactoring</li>
                        </ul>
                    </div>
                <div class="px-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-gradient-primary px-2">v1.1</span>
                            <span class="text-muted extra-small">Sept 2025</span>
                        </div>
                        <ul class="small text-muted ps-3 mb-0">
                            <li>Statistik & Grafik Interaktif</li>
                            <li>Enkripsi ID & Security hardening</li>
                        </ul>
                    </div>
                    <div class="px-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-secondary px-2">v1.0</span>
                            <span class="text-muted extra-small">Maret 2025</span>
                        </div>
                        <ul class="small text-muted ps-3 mb-0">
                            <li>Manajemen Pengguna & Role</li>
                            <li>CRUD Data Warga & Rumah</li>
                            <li>Manajemen Referensi</li>
                            <li>Ekspor PDF</li>
                        </ul>
                    </div>
                </div>
                <div class="card-footer bg-light border-0 py-3 text-center">
                    <div class="small text-muted fw-bold">Ketentuan Penggunaan</div>
                    <p class="extra-small text-muted mb-0 mt-1">Seluruh data dijaga kerahasiaannya sesuai regulasi privasi internal RT 03/14.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .extra-small { font-size: 0.75rem; }
</style>

<?php include '../../views/footer.php'; ?>
