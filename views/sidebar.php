<?php
// Pastikan variabel session ada sebelum digunakan
$role = isset($_SESSION['user']['role']) ? $_SESSION['user']['role'] : '';
$username = isset($_SESSION['user']['username']) ? $_SESSION['user']['username'] : 'Guest';
$fullname = isset($_SESSION['user']['nama']) ? $_SESSION['user']['nama'] : $username;
$current_page = basename($_SERVER['SCRIPT_NAME']);
$initials = strtoupper(substr($fullname, 0, 1));
?>

<!-- Backdrop/Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <!-- User Profile Section -->
    <div class="sidebar-profile px-3 py-4 text-center position-relative">
        <!-- Close button for mobile inside sidebar -->
        <button class="btn-close-sidebar d-lg-none" id="sidebarCloseMobile">
            <i class="bi bi-x-lg"></i>
        </button>
        
        <div class="profile-avatar mx-auto mb-2 d-flex align-items-center justify-content-center bg-gradient-primary text-white shadow">
            <?= $initials ?>
        </div>
        <h6 class="mb-0 text-white fw-bold text-truncate"><?= e($fullname) ?></h6>
        <small class="text-muted text-uppercase" style="font-size: 10px; letter-spacing: 1px;"><?= e($role) ?></small>
    </div>

    <div class="sidebar-menu mt-2">
        <div class="menu-header px-4 small text-muted text-uppercase mb-2" style="font-size: 10px; letter-spacing: 1px;">Menu Utama</div>
        
        <a href="../dashboard/dashboard.php" class="nav-link <?= ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2 me-3"></i> <span>Dashboard</span>
        </a>

        <?php if ($role === 'user' || $role === 'pengguna') : ?>
            <a href="../keluarga/index.php" class="nav-link <?= (strpos($_SERVER['SCRIPT_NAME'], '/modules/keluarga/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-people-fill me-3"></i> <span>Profil Saya & Keluarga</span>
            </a>
            <a href="../warga/pencarian.php" class="nav-link <?= ($current_page == 'pencarian.php') ? 'active' : ''; ?>">
                <i class="bi bi-search me-3"></i> <span>Pencarian Cepat</span>
            </a>
        <?php else : ?>

            <div class="menu-item <?= in_array($current_page, ['warga.php', 'mutasi.php', 'rumahwarga.php']) ? 'active' : ''; ?>">
                <a class="nav-link d-flex justify-content-between align-items-center" onclick="toggleSubMenu(this)">
                    <span><i class="bi bi-person-lines-fill me-3"></i> Manajemen Warga</span>
                    <i class="bi bi-chevron-down arrow small"></i>
                </a>
                <div class="submenu">
                    <a href="../warga/warga.php" class="<?= ($current_page == 'warga.php') ? 'active-submenu' : ''; ?>">Data Warga</a>
                    <a href="../mutasi/mutasi.php" class="<?= ($current_page == 'mutasi.php') ? 'active-submenu' : ''; ?>">Log Mutasi</a>
                    <a href="../perumahan/rumahwarga.php" class="<?= ($current_page == 'rumahwarga.php') ? 'active-submenu' : ''; ?>">Hunian Warga</a>
                </div>
            </div>

            <div class="menu-item <?= in_array($current_page, ['rumah.php', 'pemilik_rumah.php']) ? 'active' : ''; ?>">
                <a class="nav-link d-flex justify-content-between align-items-center" onclick="toggleSubMenu(this)">
                    <span><i class="bi bi-houses me-3"></i> Properti & Rumah</span>
                    <i class="bi bi-chevron-down arrow small"></i>
                </a>
                <div class="submenu">
                    <a href="../perumahan/rumah.php" class="<?= ($current_page == 'rumah.php') ? 'active-submenu' : ''; ?>">Daftar Rumah</a>
                    <a href="../perumahan/pemilik_rumah.php" class="<?= ($current_page == 'pemilik_rumah.php') ? 'active-submenu' : ''; ?>">Pemilik Rumah</a>
                </div>
            </div>

            <a href="../perumahan/koordinator_gang.php" class="nav-link <?= ($current_page == 'koordinator_gang.php') ? 'active' : ''; ?>">
                <i class="bi bi-pin-map me-3"></i> <span>Koordinator Gang</span>
            </a>

            <div class="menu-header px-4 small text-muted text-uppercase mt-4 mb-2" style="font-size: 10px; letter-spacing: 1px;">Analisis & Laporan</div>
            
            <a href="../dashboard/grafik.php" class="nav-link <?= ($current_page == 'grafik.php') ? 'active' : ''; ?>">
                <i class="bi bi-bar-chart-line me-3"></i> <span>Grafik Statistik</span>
            </a>
            <a href="../laporan/daftar_laporan.php" class="nav-link <?= ($current_page == 'daftar_laporan.php') ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-pdf me-3"></i> <span>Ekspor Data</span>
            </a>

            <?php if ($role == 'superadmin') : ?>
                <div class="menu-header px-4 small text-muted text-uppercase mt-4 mb-2" style="font-size: 10px; letter-spacing: 1px;">Sistem</div>
                <a href="../referensi/referensi.php" class="nav-link <?= ($current_page == 'referensi.php') ? 'active' : ''; ?>">
                    <i class="bi bi-database-gear me-3"></i> <span>Data Referensi</span>
                </a>
                <a href="../user/user.php" class="nav-link <?= ($current_page == 'user.php') ? 'active' : ''; ?>">
                    <i class="bi bi-shield-lock me-3"></i> <span>Manajemen User</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <hr class="mx-4 my-4 opacity-10">
        
        <a href="../dashboard/about.php" class="nav-link <?= ($current_page == 'about.php') ? 'active' : ''; ?>">
            <i class="bi bi-info-circle me-3"></i> <span>Tentang Sistem</span>
        </a>
        <a href="../../logout.php" class="nav-link text-danger nav-logout mb-5">
            <i class="bi bi-box-arrow-left me-3"></i> <span>Logout</span>
        </a>
        <!-- Bottom Spacer -->
        <div class="py-4"></div>
    </div>
</div>

<div class="content-wrapper" id="contentWrapper">
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand navbar-light bg-white shadow-sm sticky-top px-3 mb-4">
        <div class="d-flex align-items-center">
            <button class="btn btn-light border-0 shadow-none me-2" id="sidebarToggle">
                <i class="bi bi-list fs-4"></i>
            </button>
            <span class="navbar-brand mb-0 h1 fs-6 fw-bold text-gray-800 d-none d-md-inline-block">SI-WARGA RT 03/14</span>
        </div>
        
        <div class="ms-auto d-flex align-items-center">
            <div class="me-3 d-none d-sm-block text-end">
                <div class="small fw-bold text-dark mb-0"><?= e($fullname) ?></div>
                <div class="text-muted extra-small" style="font-size: 10px;"><?= e(ucfirst($role)) ?></div>
            </div>
            <div class="vr me-3 d-none d-sm-block opacity-10"></div>
            <a href="../../logout.php" class="btn btn-sm btn-outline-danger px-3 border-0" title="Keluar dari sistem">
                <i class="bi bi-box-arrow-right me-1"></i> <span class="d-none d-md-inline">Logout</span>
            </a>
        </div>
    </nav>

    <div class="container-fluid">

<style>
    :root {
        --sidebar-width: 260px;
        --sidebar-bg: #1a1d20;
        --sidebar-hover: #2a2e33;
        --primary-gradient: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    }

    /* Sidebar Base Styling */
    .sidebar {
        width: var(--sidebar-width);
        background-color: var(--sidebar-bg);
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        z-index: 1100; /* Ditingkatkan agar di atas overlay */
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow-y: auto;
    }

    /* Backdrop Overlay */
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1050;
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    body.sidebar-toggled-mobile .sidebar-overlay {
        display: block;
        opacity: 1;
    }

    /* Close Button inside Sidebar (Mobile only) */
    .btn-close-sidebar {
        position: absolute;
        top: 15px;
        right: 15px;
        background: none;
        border: none;
        color: rgba(255,255,255,0.5);
        font-size: 1.2rem;
    }
    .btn-close-sidebar:hover { color: white; }

    .profile-avatar {
        width: 60px;
        height: 60px;
        border-radius: 18px;
        font-size: 1.5rem;
        font-weight: 700;
        border: 3px solid rgba(255,255,255,0.1);
    }

    /* Content Wrapper */
    .content-wrapper {
        margin-left: var(--sidebar-width);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        min-height: 100vh;
        background-color: #f8f9fc;
    }

    .navbar { height: 70px; z-index: 1000; }
    #sidebarToggle { color: #4e73df; }

    .sidebar .nav-link {
        padding: 12px 24px;
        color: #949ba2;
        display: flex;
        align-items: center;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s;
        border-left: 4px solid transparent;
        cursor: pointer;
    }
    .sidebar .nav-link:hover {
        background-color: var(--sidebar-hover);
        color: white;
    }

    .sidebar .nav-link.active {
        background: var(--primary-gradient);
        color: white !important;
        border-left-color: #fff;
    }

    .sidebar .nav-link i { font-size: 1.1rem; }

    /* Logout Button in Sidebar */
    .nav-logout {
        margin-top: 10px;
        background-color: rgba(220, 53, 69, 0.1);
        border-radius: 8px;
        margin-left: 15px;
        margin-right: 15px;
        border-left: none !important;
    }
    .nav-logout:hover {
        background-color: #dc3545 !important;
        color: white !important;
    }

    /* Submenu Styling */
    .submenu { display: none; background-color: #121416; }
    .submenu a { padding: 10px 24px 10px 58px; color: #81888f; font-size: 13px; text-decoration: none; display: block; }
    .submenu a:hover, .submenu a.active-submenu { color: white; background-color: rgba(255,255,255,0.05); }
    .submenu a.active-submenu { color: #4e73df; font-weight: 600; }

    .menu-item.active .arrow { transform: rotate(180deg); }
    .arrow { transition: transform 0.3s; }

    /* Collapsed State (Desktop) */
    body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
    body.sidebar-toggled .content-wrapper { margin-left: 0; }

    /* Mobile Handling */
    @media (max-width: 992px) {
        .sidebar { left: calc(-1 * var(--sidebar-width)); }
        .content-wrapper { margin-left: 0; }
        
        /* State saat sidebar terbuka di HP */
        body.sidebar-toggled-mobile .sidebar { left: 0; }
        body.sidebar-toggled-mobile { overflow: hidden; } /* Cegah scroll saat menu buka */
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const body = document.body;
        const toggleBtn = document.getElementById('sidebarToggle');
        const overlay = document.getElementById('sidebarOverlay');
        const closeMobile = document.getElementById('sidebarCloseMobile');

        // Check Desktop state
        if (window.innerWidth > 992 && localStorage.getItem('sidebar-state') === 'collapsed') {
            body.classList.add('sidebar-toggled');
        }

        // Toggle logic
        function toggleSidebar() {
            if (window.innerWidth <= 992) {
                body.classList.toggle('sidebar-toggled-mobile');
            } else {
                body.classList.toggle('sidebar-toggled');
                localStorage.setItem('sidebar-state', body.classList.contains('sidebar-toggled') ? 'collapsed' : 'expanded');
            }
        }

        toggleBtn.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);
        closeMobile.addEventListener('click', toggleSidebar);

        // Submenu handler
        document.querySelectorAll('.menu-item').forEach(item => {
            if (item.querySelector('.active-submenu')) {
                item.classList.add('active');
                item.querySelector('.submenu').style.display = 'block';
                item.querySelector('.arrow').style.transform = 'rotate(180deg)';
            }
        });
    });

    function toggleSubMenu(element) {
        const parent = element.parentElement;
        const submenu = parent.querySelector('.submenu');
        const arrow = parent.querySelector('.arrow');
        const isActive = parent.classList.contains('active');
        
        document.querySelectorAll('.menu-item.active').forEach(item => {
            if (item !== parent) {
                item.classList.remove('active');
                $(item.querySelector('.submenu')).slideUp(200);
                item.querySelector('.arrow').style.transform = 'rotate(0deg)';
            }
        });

        parent.classList.toggle('active');
        if (isActive) {
            $(submenu).slideUp(200);
            arrow.style.transform = 'rotate(0deg)';
        } else {
            $(submenu).slideDown(200);
            arrow.style.transform = 'rotate(180deg)';
        }
    }
</script>
