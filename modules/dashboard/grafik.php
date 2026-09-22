<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');

check_auth(['superadmin', 'operator', 'admin']);

$status = isset($_GET['status']) ? $_GET['status'] : '';
$gang   = isset($_GET['gang']) ? $_GET['gang'] : '';

$where_clause = " WHERE w.is_delete IS NULL ";
$params = [];
$types = "";

if ($status !== '') {
    $where_clause .= " AND status_aktif.ref_nama = ?";
    $params[] = $status;
    $types .= "s";
}
if ($gang !== '') {
    $where_clause .= " AND (gang_ref.ref_nama = ? OR h.gang = ?)";
    $params[] = $gang;
    $params[] = $gang;
    $types .= "ss";
}

$from_join_clause = "
    FROM warga w
    LEFT JOIN warga_mutasi mutasi ON w.warga_id = mutasi.warga_id AND mutasi.is_aktif = 1
    LEFT JOIN referensi status_aktif ON status_aktif.ref_id = mutasi.ref_id_status_aktif AND status_aktif.ref_kategori = 'status_aktif'
    LEFT JOIN warga_rumah wr ON w.warga_id = wr.warga_id AND wr.is_aktif = 1
    LEFT JOIN rumah r ON wr.rumah_id = r.rumah_id
    LEFT JOIN referensi gang_ref ON r.ref_id_gang = gang_ref.ref_id AND gang_ref.ref_kategori = 'gang'
    LEFT JOIN referensi hubkel_ref ON hubkel_ref.ref_id=w.ref_id_hubungan_keluarga AND hubkel_ref.ref_kategori='hubungan_keluarga'
    LEFT JOIN (
		SELECT c.`warga_id`, c.`warga_nama`,
		CASE WHEN c.`ref_id_hubungan_keluarga`=49 THEN CONCAT(c.`warga_hubungan_keluarga`,' (KK : ',a.warga_nama,')')
		WHEN a.`ref_id_hubungan_keluarga`<>49 THEN CONCAT(h.`ref_nama`,' (KK : ',a.warga_nama,')')
		ELSE '' END AS status_keluarga, a.warga_nama AS nama_kk, c.`warga_parent`,
		CAST(d.rumah_nomor_tampil AS INT) nomor_rumah, d.rumah_nomor_tampil, d.rumah_nomor,
		IF(e.`ref_id`=20, e.`ref_nama`, CONCAT('Gg.', e.`ref_nama`)) AS gang_tampil, e.ref_nama AS gang
		FROM warga a
		JOIN referensi g ON g.`ref_id`=a.`ref_id_hubungan_keluarga` AND g.`ref_kategori`='hubungan_keluarga'
		JOIN warga c ON c.`warga_parent`=a.`warga_id`
		JOIN referensi h ON h.`ref_id`=c.`ref_id_hubungan_keluarga` AND h.`ref_kategori`='hubungan_keluarga'
		JOIN warga_rumah f ON a.warga_id=f.`warga_id` AND f.`is_aktif`=1
		JOIN rumah d ON d.`rumah_id`=f.`rumah_id` AND d.`is_aktif`=1
		JOIN referensi e ON e.`ref_id`=d.`ref_id_gang` AND e.`ref_kategori`='gang'
	) h ON h.warga_id=w.warga_id    
";

function getCountHardened($conn, $warga_field, $ref_kategori, $from_join_clause, $where_clause, $params, $types) {
    $query = "SELECT r_data.ref_nama, COUNT(w.warga_id) as jumlah
              " . $from_join_clause . "
              LEFT JOIN referensi r_data ON w.$warga_field = r_data.ref_id AND r_data.ref_kategori = '$ref_kategori'
              " . $where_clause . "
              GROUP BY r_data.ref_nama
              ORDER BY r_data.ref_id";
    $stmt = mysqli_prepare($conn, $query);
    if ($types !== "") mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $label = $row['ref_nama'] ?? 'Tidak Didefinisikan';
        $data[$label] = (int)$row['jumlah'];
    }
    return $data;
}

if (isset($_GET['action']) && $_GET['action'] == 'get_warga_data') {
    header('Content-Type: application/json');
    $chart_type = $_GET['chart_type'] ?? '';
    $label = $_GET['label'] ?? '';
    $ajax_where_clause = " WHERE w.is_delete IS NULL ";
    $ajax_params = [];
    $ajax_types = "";
    
    $status_ajax = $_GET['status'] ?? '';
    $gang_ajax = $_GET['gang'] ?? '';
    if ($status_ajax !== '') { $ajax_where_clause .= " AND status_aktif.ref_nama = ?"; $ajax_params[] = $status_ajax; $ajax_types .= "s"; }
    if ($gang_ajax !== '') { $ajax_where_clause .= " AND (gang_ref.ref_nama = ? OR h.gang = ?)"; $ajax_params[] = $gang_ajax; $ajax_params[] = $gang_ajax; $ajax_types .= "ss"; }

    $join_ajax = "";
    switch ($chart_type) {
        case 'jk': $w_f = 'ref_id_jenis_kelamin'; $r_k = 'jenis_kelamin'; break;
        case 'agama': $w_f = 'ref_id_agama'; $r_k = 'agama'; break;
        case 'pendidikan': $w_f = 'ref_id_pendidikan'; $r_k = 'pendidikan'; break;
        case 'pekerjaan': $w_f = 'ref_id_pekerjaan'; $r_k = 'pekerjaan'; break;
        case 'nikah': $w_f = 'ref_id_status_kawin'; $r_k = 'status_kawin'; break;
        case 'keluarga': $w_f = 'ref_id_hubungan_keluarga'; $r_k = 'hubungan_keluarga'; break;
        case 'usia':
            if ($label == 'N/A') {
                $ajax_where_clause .= " AND (w.warga_tgl_lahir='' OR w.warga_tgl_lahir IS NULL OR w.warga_tgl_lahir='0000-00-00')";
            } else {
                $age_min = 0; $age_max = 999;
                if ($label == '0-5') { $age_min = 0; $age_max = 5; }
                elseif ($label == '6-10') { $age_min = 6; $age_max = 10; }
                elseif ($label == '11-15') { $age_min = 11; $age_max = 15; }
                elseif ($label == '16-20') { $age_min = 16; $age_max = 20; }
                elseif ($label == '21-25') { $age_min = 21; $age_max = 25; }
                elseif ($label == '26-30') { $age_min = 26; $age_max = 30; }
                elseif ($label == '31-35') { $age_min = 31; $age_max = 35; }
                elseif ($label == '36-40') { $age_min = 36; $age_max = 40; }
                elseif ($label == '41-45') { $age_min = 41; $age_max = 45; }
                elseif ($label == '46-50') { $age_min = 46; $age_max = 50; }
                elseif ($label == '51-55') { $age_min = 51; $age_max = 55; }
                elseif ($label == '56-60') { $age_min = 56; $age_max = 60; }
                elseif ($label == '61+') { $age_min = 61; }
                
                if ($label == '61+') {
                    $ajax_where_clause .= " AND TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) >= ?";
                    $ajax_params[] = $age_min;
                    $ajax_types .= "i";
                } else {
                    $ajax_where_clause .= " AND TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN ? AND ?";
                    $ajax_params[] = $age_min;
                    $ajax_params[] = $age_max;
                    $ajax_types .= "ii";
                }
            }
            break;
        case 'ktp':
            $ktp_map = ['KTP WP' => 1, 'Bukan KTP WP' => 0, 'Belum Ber-KTP' => 2];
            if (isset($ktp_map[$label])) {
                $ajax_where_clause .= " AND w.is_ktp_wp = ?";
                $ajax_params[] = $ktp_map[$label];
                $ajax_types .= "i";
            } else {
                $ajax_where_clause .= " AND (w.is_ktp_wp IS NULL OR w.is_ktp_wp NOT IN (0, 1, 2))";
            }
            break;
        case 'status_rumah':
            $sr_map = ['Milik Sendiri' => 169, 'Sewa/Kontrak' => 170, 'Mendiami' => 171];
            if (isset($sr_map[$label])) {
                $ajax_where_clause .= " AND wr.ref_id_status_rumah = ?";
                $ajax_params[] = $sr_map[$label];
                $ajax_types .= "i";
            } else {
                $ajax_where_clause .= " AND (wr.ref_id_status_rumah IS NULL OR wr.ref_id_status_rumah NOT IN (169, 170, 171))";
            }
            break;
    }

    if (isset($w_f)) {
        if ($label === 'Tidak Didefinisikan') {
            $ajax_where_clause .= " AND (w.$w_f IS NULL OR w.$w_f = 0 OR w.$w_f = '')";
        } else {
            $ajax_where_clause .= " AND r_data_ajax.ref_nama = ?";
            $ajax_params[] = $label;
            $ajax_types .= "s";
            $join_ajax = " LEFT JOIN referensi r_data_ajax ON w.$w_f = r_data_ajax.ref_id AND r_data_ajax.ref_kategori = '$r_k' ";
        }
    }

    $query_sql = "SELECT w.warga_nama,
        CONCAT('WP ', IF(h.warga_id IS NULL, r.rumah_nomor_tampil, h.rumah_nomor_tampil),' - ', IF(h.warga_id IS NULL, IF(gang_ref.ref_id=20, gang_ref.ref_nama, CONCAT('Gg.', gang_ref.ref_nama)), h.gang_tampil)) AS alamat,
        IF(h.warga_id IS NULL, hubkel_ref.ref_nama, h.status_keluarga) AS hubungan_keluarga
        " . $from_join_clause . $join_ajax . $ajax_where_clause . " 
        ORDER BY h.rumah_nomor, IF(h.warga_parent IS NULL, w.warga_id, h.warga_parent), hubkel_ref.ref_id, hubungan_keluarga, w.warga_nama ASC";

    $stmt = mysqli_prepare($conn, $query_sql);
    if ($ajax_types !== "") mysqli_stmt_bind_param($stmt, $ajax_types, ...$ajax_params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $warga_data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $warga_data[] = $row;
    }
    echo json_encode($warga_data);
    exit;
}

// Ambil data statistik (Usia, KTP, Rumah, dsb)
$usia_query = "SELECT SUM(CASE WHEN w.warga_tgl_lahir='' OR w.warga_tgl_lahir IS NULL OR w.warga_tgl_lahir='0000-00-00' THEN 1 ELSE 0 END) AS 'N/A', SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 0 AND 5 THEN 1 ELSE 0 END) AS '0-5', SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 6 AND 10 THEN 1 ELSE 0 END) AS '6-10', SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 11 AND 15 THEN 1 ELSE 0 END) AS '11-15', SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 16 AND 20 THEN 1 ELSE 0 END) AS '16-20', SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 21 AND 25 THEN 1 ELSE 0 END) AS '21-25', SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 26 AND 30 THEN 1 ELSE 0 END) AS '26-30', SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 31 AND 35 THEN 1 ELSE 0 END) AS '31-35', SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 36 AND 40 THEN 1 ELSE 0 END) AS '36-40', SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 41 AND 45 THEN 1 ELSE 0 END) AS '41-45', SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 46 AND 50 THEN 1 ELSE 0 END) AS '46-50', SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 51 AND 55 THEN 1 ELSE 0 END) AS '51-55', SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 56 AND 60 THEN 1 ELSE 0 END) AS '56-60', SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) >= 61 THEN 1 ELSE 0 END) AS '61+' $from_join_clause $where_clause";
$stmt_u = mysqli_prepare($conn, $usia_query);
if ($types !== "") mysqli_stmt_bind_param($stmt_u, $types, ...$params);
mysqli_stmt_execute($stmt_u);
$usia_range = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_u));

$jk_data = getCountHardened($conn, 'ref_id_jenis_kelamin', 'jenis_kelamin', $from_join_clause, $where_clause, $params, $types);
$agama_data = getCountHardened($conn, 'ref_id_agama', 'agama', $from_join_clause, $where_clause, $params, $types);
$pendidikan_data = getCountHardened($conn, 'ref_id_pendidikan', 'pendidikan', $from_join_clause, $where_clause, $params, $types);
$pekerjaan_data = getCountHardened($conn, 'ref_id_pekerjaan', 'pekerjaan', $from_join_clause, $where_clause, $params, $types);
$nikah_data = getCountHardened($conn, 'ref_id_status_kawin', 'status_kawin', $from_join_clause, $where_clause, $params, $types);
$keluarga_data = getCountHardened($conn, 'ref_id_hubungan_keluarga', 'hubungan_keluarga', $from_join_clause, $where_clause, $params, $types);

$ktp_query = "SELECT CASE WHEN w.is_ktp_wp = 1 THEN 'KTP WP' WHEN w.is_ktp_wp = 0 THEN 'Bukan KTP WP' WHEN w.is_ktp_wp = 2 THEN 'Belum Ber-KTP' ELSE 'Tidak Didefinisikan' END as status_ktp, COUNT(w.warga_id) as jumlah $from_join_clause $where_clause GROUP BY status_ktp ORDER BY FIELD(status_ktp, 'KTP WP', 'Bukan KTP WP', 'Belum Ber-KTP', 'Tidak Didefinisikan')";
$stmt_ktp = mysqli_prepare($conn, $ktp_query);
if ($types !== "") mysqli_stmt_bind_param($stmt_ktp, $types, ...$params);
mysqli_stmt_execute($stmt_ktp);
$ktp_res = mysqli_stmt_get_result($stmt_ktp);
$ktp_data = []; while ($row = mysqli_fetch_assoc($ktp_res)) { $ktp_data[$row['status_ktp']] = (int)$row['jumlah']; }

$sr_query = "SELECT CASE WHEN wr.ref_id_status_rumah = 169 THEN 'Milik Sendiri' WHEN wr.ref_id_status_rumah = 170 THEN 'Sewa/Kontrak' WHEN wr.ref_id_status_rumah = 171 THEN 'Mendiami' ELSE 'Tidak Didefinisikan' END as status_rumah, COUNT(w.warga_id) as jumlah $from_join_clause $where_clause AND wr.ref_id_status_rumah IS NOT NULL GROUP BY status_rumah ORDER BY FIELD(status_rumah, 'Milik Sendiri', 'Mendiami', 'Sewa/Kontrak', 'Tidak Didefinisikan')";
$stmt_sr = mysqli_prepare($conn, $sr_query);
if ($types !== "") mysqli_stmt_bind_param($stmt_sr, $types, ...$params);
mysqli_stmt_execute($stmt_sr);
$sr_res = mysqli_stmt_get_result($stmt_sr);
$status_rumah_data = []; while ($row = mysqli_fetch_assoc($sr_res)) { $status_rumah_data[$row['status_rumah']] = (int)$row['jumlah']; }

$dataSets = [
    'jk' => ['labels' => array_keys($jk_data), 'data' => array_values($jk_data)],
    'usia' => ['labels' => array_keys($usia_range), 'data' => array_values($usia_range)],
    'agama' => ['labels' => array_keys($agama_data), 'data' => array_values($agama_data)],
    'pendidikan' => ['labels' => array_keys($pendidikan_data), 'data' => array_values($pendidikan_data)],
    'pekerjaan' => ['labels' => array_keys($pekerjaan_data), 'data' => array_values($pekerjaan_data)],
    'nikah' => ['labels' => array_keys($nikah_data), 'data' => array_values($nikah_data)],
    'keluarga' => ['labels' => array_keys($keluarga_data), 'data' => array_values($keluarga_data)],
    'ktp' => ['labels' => array_keys($ktp_data), 'data' => array_values($ktp_data)],
    'status_rumah' => ['labels' => array_keys($status_rumah_data), 'data' => array_values($status_rumah_data)]
];
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0 text-gray-800 fw-bold"><i class="bi bi-bar-chart-line me-2"></i>Statistik & Grafik Warga</h1>
        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary px-3"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-3">
            <form method="get" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Filter Status Domisili</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="Menetap di WP" <?= $status === 'Menetap di WP' ? 'selected' : '' ?>>Menetap di WP</option>
                        <option value="Pindah dari WP" <?= $status === 'Pindah dari WP' ? 'selected' : '' ?>>Pindah dari WP</option>
                        <option value="Meninggal Dunia" <?= $status === 'Meninggal Dunia' ? 'selected' : '' ?>>Meninggal Dunia</option>
                        <option value="Tidak Tinggal di WP" <?= $status === 'Tidak Tinggal di WP' ? 'selected' : '' ?>>Tidak Tinggal di WP</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Filter Gang / Jalan</label>
                    <select name="gang" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Gang</option>
                        <?php
                        $gang_q = mysqli_query($conn, "SELECT ref_nama FROM referensi WHERE ref_kategori='gang' ORDER BY ref_nama ASC");
                        while ($g = mysqli_fetch_assoc($gang_q)) :
                        ?>
                            <option value="<?= e($g['ref_nama']) ?>" <?= $gang === $g['ref_nama'] ? 'selected' : '' ?>><?= e($g['ref_nama']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end h-100 mt-4">
                    <a href="grafik.php" class="btn btn-light border w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="row">
        <!-- Jenis Kelamin -->
        <div class="col-xl-4 col-lg-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary">Jenis Kelamin</h6></div>
                <div class="card-body"><canvas id="jkChart" style="max-height: 250px;"></canvas></div>
            </div>
        </div>
        <!-- Usia -->
        <div class="col-xl-8 col-lg-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary">Rentang Usia</h6></div>
                <div class="card-body"><canvas id="usiaChart" style="max-height: 250px;"></canvas></div>
            </div>
        </div>
        <!-- Agama -->
        <div class="col-xl-4 col-lg-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary">Agama</h6></div>
                <div class="card-body"><canvas id="agamaChart" style="max-height: 250px;"></canvas></div>
            </div>
        </div>
        <!-- Pendidikan -->
        <div class="col-xl-8 col-lg-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary">Pendidikan</h6></div>
                <div class="card-body"><canvas id="pendidikanChart" style="max-height: 250px;"></canvas></div>
            </div>
        </div>
        <!-- Pekerjaan -->
        <div class="col-12 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary">Pekerjaan</h6></div>
                <div class="card-body"><canvas id="pekerjaanChart" style="max-height: 250px;"></canvas></div>
            </div>
        </div>
        <!-- Status Perkawinan -->
        <div class="col-xl-4 col-lg-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary">Status Perkawinan</h6></div>
                <div class="card-body"><canvas id="nikahChart" style="max-height: 250px;"></canvas></div>
            </div>
        </div>
        <!-- Hubungan Keluarga -->
        <div class="col-xl-8 col-lg-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary">Hubungan Keluarga</h6></div>
                <div class="card-body"><canvas id="keluargaChart" style="max-height: 250px;"></canvas></div>
            </div>
        </div>
        <!-- Status KTP -->
        <div class="col-xl-4 col-lg-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary">Status KTP</h6></div>
                <div class="card-body"><canvas id="ktpChart" style="max-height: 250px;"></canvas></div>
            </div>
        </div>
        <!-- Status Rumah -->
        <div class="col-xl-4 col-lg-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary">Status Rumah</h6></div>
                <div class="card-body"><canvas id="status_rumahChart" style="max-height: 250px;"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Detail Table Section -->
    <div id="loadingIndicator" class="text-center my-4" style="display: none;">
        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
        <p class="mt-2 small text-muted">Mengambil data detail...</p>
    </div>
    <div id="wargaDataTable" class="card shadow-sm border-0 mb-5" style="display: none;">
        <!-- Data table injected via JS -->
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
<script>
Chart.register(ChartDataLabels);
const dataSets = <?= json_encode($dataSets); ?>;

function generateColors(length) {
    const base = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#fd7e14', '#20c997', '#6f42c1', '#0dcaf0'];
    return Array.from({ length }, (_, i) => base[i % base.length]);
}

async function displayWargaTable(data, title) {
    const tableContainer = document.getElementById('wargaDataTable');
    tableContainer.style.display = 'block';
    if (data.length === 0) {
        tableContainer.innerHTML = `<div class="card-body text-center py-4">Tidak ada data warga untuk kriteria ini.</div>`;
        return;
    }
    let tableHTML = `<div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary">Detail Data: ${title}</h6></div>
                     <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover mb-0">
                                <thead class="table-light"><tr><th>No.</th><th>Nama</th><th>Alamat</th><th>Status Keluarga</th></tr></thead>
                                <tbody>`;
    data.forEach((warga, index) => {
        tableHTML += `<tr><td>${index + 1}</td><td>${warga.warga_nama || '-'}</td><td>${warga.alamat || '-'}</td><td>${warga.hubungan_keluarga || '-'}</td></tr>`;
    });
    tableHTML += `</tbody></table></div></div>`;
    tableContainer.innerHTML = tableHTML;
    tableContainer.scrollIntoView({ behavior: 'smooth' });
}

async function fetchWargaData(chartType, label) {
    const loading = document.getElementById('loadingIndicator');
    const table = document.getElementById('wargaDataTable');
    const params = new URLSearchParams(window.location.search);
    const status = params.get('status') || '';
    const gang = params.get('gang') || '';

    loading.style.display = 'block';
    table.style.display = 'none';

    try {
        const response = await fetch(`grafik.php?action=get_warga_data&chart_type=${chartType}&label=${encodeURIComponent(label)}&status=${encodeURIComponent(status)}&gang=${encodeURIComponent(gang)}`);
        const data = await response.json();
        
        if (data.error) {
            table.innerHTML = `<div class="card-body text-danger">Error: ${data.error}</div>`;
            table.style.display = 'block';
        } else {
            const chartTitleMap = { jk: 'Jenis Kelamin', usia: 'Rentang Usia', agama: 'Agama', pendidikan: 'Pendidikan', pekerjaan: 'Pekerjaan', nikah: 'Status Perkawinan', keluarga: 'Hubungan Keluarga', ktp: 'Status KTP', status_rumah: 'Status Rumah' };
            displayWargaTable(data, `${chartTitleMap[chartType]} - ${label}`);
        }
    } catch (error) {
        table.innerHTML = `<div class="card-body text-danger">Gagal memuat data detail.</div>`;
        table.style.display = 'block';
    } finally {
        loading.style.display = 'none';
    }
}

function generateChart(id, label, labels, data, type = 'bar') {
    const ctx = document.getElementById(id).getContext('2d');
    new Chart(ctx, {
        type: type,
        data: {
            labels: labels,
            datasets: [{ label: label, data: data, backgroundColor: generateColors(labels.length), borderRadius: 5 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: type === 'pie', position: 'bottom' },
                datalabels: { color: '#444', anchor: 'end', align: 'top', formatter: v => v > 0 ? v : '' }
            },
            scales: type === 'pie' ? {} : { y: { beginAtZero: true, grid: { display: false } }, x: { grid: { display: false } } },
            onClick: (event, elements, chart) => {
                if (elements.length > 0) {
                    const index = elements[0].index;
                    const clickedLabel = chart.data.labels[index];
                    const chartType = chart.canvas.id.replace('Chart', '');
                    fetchWargaData(chartType, clickedLabel);
                }
            }
        }
    });
}

generateChart('jkChart', 'Jiwa', dataSets.jk.labels, dataSets.jk.data, 'pie');
generateChart('usiaChart', 'Jiwa', dataSets.usia.labels, dataSets.usia.data, 'bar');
generateChart('agamaChart', 'Jiwa', dataSets.agama.labels, dataSets.agama.data, 'bar');
generateChart('pendidikanChart', 'Jiwa', dataSets.pendidikan.labels, dataSets.pendidikan.data, 'bar');
generateChart('pekerjaanChart', 'Jiwa', dataSets.pekerjaan.labels, dataSets.pekerjaan.data, 'bar');
generateChart('nikahChart', 'Jiwa', dataSets.nikah.labels, dataSets.nikah.data, 'bar');
generateChart('keluargaChart', 'Jiwa', dataSets.keluarga.labels, dataSets.keluarga.data, 'bar');
generateChart('ktpChart', 'Jiwa', dataSets.ktp.labels, dataSets.ktp.data, 'bar');
generateChart('status_rumahChart', 'Jiwa', dataSets.status_rumah.labels, dataSets.status_rumah.data, 'bar');
</script>

<?php include '../../views/footer.php'; ?>
