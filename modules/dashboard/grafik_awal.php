<?php
require_once('../../includes/auth.php');
require_once('../../includes/db.php');

check_auth();

// --- Ambil dan sanitasi parameter filter ---
$status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$gang   = isset($_GET['gang']) ? mysqli_real_escape_string($conn, $_GET['gang']) : '';

// --- Bangun klausa WHERE berdasarkan filter ---
$where_clause = " WHERE w.is_delete IS NULL ";
if ($status !== '') {
    // Pastikan untuk join tabel yang diperlukan jika filter aktif
    $where_clause .= " AND status_aktif.ref_nama = '$status'";
}
if ($gang !== '') {
    // Pastikan untuk join tabel yang diperlukan jika filter aktif
    $where_clause .= " AND (gang_ref.ref_nama = '$gang' OR h.gang = '$gang')";
}

// --- Klausa FROM dan JOIN yang umum digunakan ---
$from_join_clause = "
    FROM warga w
    LEFT JOIN warga_mutasi mutasi ON w.warga_id = mutasi.warga_id AND mutasi.is_aktif = 1
    LEFT JOIN referensi status_aktif ON status_aktif.ref_id = mutasi.ref_id_status_aktif AND status_aktif.ref_kategori = 'status_aktif'
    LEFT JOIN warga_rumah wr ON w.warga_id = wr.warga_id AND wr.is_aktif = 1
    LEFT JOIN rumah r ON wr.rumah_id = r.rumah_id
    LEFT JOIN referensi gang_ref ON r.ref_id_gang = gang_ref.ref_id AND gang_ref.ref_kategori = 'gang'
    LEFT JOIN referensi hubkel_ref ON hubkel_ref.ref_id=w.ref_id_hubungan_keluarga AND hubkel_ref.ref_kategori='hubungan_keluarga'
    LEFT JOIN (
		SELECT c.`warga_id`, c.`warga_nama`, CONCAT(h.`ref_nama`,' (KK : ',a.warga_nama,')') AS status_keluarga_old,
		CASE WHEN c.`ref_id_hubungan_keluarga`=49 THEN CONCAT(c.`warga_hubungan_keluarga`,' (KK : ',a.warga_nama,')')
		WHEN a.`ref_id_hubungan_keluarga`<>49 THEN CONCAT(h.`ref_nama`,' (KK : ',a.warga_nama,')')
		ELSE '' END AS status_keluarga, a.warga_nama AS nama_kk, c.`warga_parent`,
		CAST(d.rumah_nomor_tampil AS INT) nomor_rumah, d.rumah_nomor_tampil, d.rumah_nomor,
		IF(e.`ref_id`=20, e.`ref_nama`, CONCAT('Gg.', e.`ref_nama`)) AS gang_tampil, e.ref_nama AS gang
		FROM warga a
		JOIN `wahanapraja`.`referensi` g ON g.`ref_id`=a.`ref_id_hubungan_keluarga` AND g.`ref_kategori`='hubungan_keluarga'
		JOIN warga c ON c.`warga_parent`=a.`warga_id`
		JOIN `wahanapraja`.`referensi` h ON h.`ref_id`=c.`ref_id_hubungan_keluarga` AND h.`ref_kategori`='hubungan_keluarga'
		JOIN `wahanapraja`.`warga_rumah` f ON a.warga_id=f.`warga_id` AND f.`is_aktif`=1
		JOIN `wahanapraja`.`rumah` d ON d.`rumah_id`=f.`rumah_id` AND d.`is_aktif`=1
		JOIN `wahanapraja`.`referensi` e ON e.`ref_id`=d.`ref_id_gang` AND e.`ref_kategori`='gang'
		ORDER BY d.rumah_nomor, h.`ref_id`
	) h ON h.warga_id=w.warga_id    
";

// --- FUNGSI DIPERBAIKI: Fungsi untuk mengambil data statistik dengan filter ---
function getCount($conn, $warga_field, $ref_kategori, $from_join_clause, $where_clause) {
    // Query diperbaiki: Klausa FROM tidak lagi diduplikasi.
    $query = "SELECT r_data.ref_nama, COUNT(w.warga_id) as jumlah
              " . $from_join_clause . "
              LEFT JOIN referensi r_data ON w.$warga_field = r_data.ref_id AND r_data.ref_kategori = '$ref_kategori'
              " . $where_clause . "
              GROUP BY r_data.ref_nama
              ORDER BY r_data.ref_id";

    $result = mysqli_query($conn, $query);
    if (!$result) {
        // Tampilkan error jika query gagal untuk debugging
        die('Query Error in getCount: ' . mysqli_error($conn));
    }
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $label = $row['ref_nama'] ?? 'Tidak Didefinisikan';
        $data[$label] = (int)$row['jumlah'];
    }
    return $data;
}


// --- Logika untuk menangani permintaan AJAX ---
if (isset($_GET['action']) && $_GET['action'] == 'get_warga_data') {
    header('Content-Type: application/json');

    // Sanitasi semua input AJAX
    $chart_type = isset($_GET['chart_type']) ? mysqli_real_escape_string($conn, $_GET['chart_type']) : '';
    $label = isset($_GET['label']) ? mysqli_real_escape_string($conn, $_GET['label']) : '';
    $status_ajax = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
    $gang_ajax = isset($_GET['gang']) ? mysqli_real_escape_string($conn, $_GET['gang']) : '';

    // Bangun klausa WHERE untuk AJAX
    $ajax_where_clause = " WHERE w.is_delete IS NULL ";
    if ($status_ajax !== '') {
        $ajax_where_clause .= " AND status_aktif.ref_nama = '$status_ajax'";
    }
    if ($gang_ajax !== '') {
        $ajax_where_clause .= " AND (gang_ref.ref_nama = '$gang_ajax' OR h.gang = '$gang_ajax')";
    }

    $warga_data = [];
    $query_sql = "";
    $warga_field = '';
    $ref_kategori = '';

    switch ($chart_type) {
        case 'jk': $warga_field = 'ref_id_jenis_kelamin'; $ref_kategori = 'jenis_kelamin'; break;
        case 'agama': $warga_field = 'ref_id_agama'; $ref_kategori = 'agama'; break;
        case 'pendidikan': $warga_field = 'ref_id_pendidikan'; $ref_kategori = 'pendidikan'; break;
        case 'pekerjaan': $warga_field = 'ref_id_pekerjaan'; $ref_kategori = 'pekerjaan'; break;
        case 'nikah': $warga_field = 'ref_id_status_kawin'; $ref_kategori = 'status_kawin'; break;
        case 'keluarga': $warga_field = 'ref_id_hubungan_keluarga'; $ref_kategori = 'hubungan_keluarga'; break;
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
             
             $age_condition = ($label == '61+') ? ">= $age_min" : "BETWEEN $age_min AND $age_max";
             //$ajax_where_clause .= " AND w.warga_tgl_lahir IS NOT NULL AND w.warga_tgl_lahir <> '0000-00-00' AND TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) $age_condition";
             $ajax_where_clause .= " AND TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) $age_condition";
			}
             break;
        case 'ktp':
            $ktp_value_map = ['KTP WP' => 1, 'Bukan KTP WP' => 0, 'Belum Ber-KTP' => 2];
            if (array_key_exists($label, $ktp_value_map)) {
                $ajax_where_clause .= " AND w.is_ktp_wp = " . $ktp_value_map[$label];
            } else { // Handle 'Tidak Didefinisikan'
                $ajax_where_clause .= " AND (w.is_ktp_wp IS NULL OR w.is_ktp_wp NOT IN (0, 1, 2))";
            }
            break;
        case 'status_rumah':
            $status_rumah_value_map = ['Milik Sendiri' => 169, 'Sewa/Kontrak' => 170, 'Mendiami' => 171];
            if (array_key_exists($label, $status_rumah_value_map)) {
                $ajax_where_clause .= " AND wr.ref_id_status_rumah = " . $status_rumah_value_map[$label];
            } else { // Handle 'Tidak Didefinisikan'
                $ajax_where_clause .= " AND (wr.ref_id_status_rumah IS NULL OR wr.ref_id_status_rumah NOT IN (169, 170, 171))";
            }
            break;        
    }

    if (in_array($chart_type, ['jk', 'agama', 'pendidikan', 'pekerjaan', 'nikah', 'keluarga'])) {
        if ($label === 'Tidak Didefinisikan') {
            $ajax_where_clause .= " AND (w.$warga_field IS NULL OR w.$warga_field = 0 OR w.$warga_field = '')";
        } else {
            $ref_id_query = mysqli_query($conn, "SELECT ref_id FROM referensi WHERE ref_nama = '$label' AND ref_kategori = '$ref_kategori'");
            if ($ref_id_row = mysqli_fetch_assoc($ref_id_query)) {
                $ajax_where_clause .= " AND w.$warga_field = " . $ref_id_row['ref_id'];
            }
        }
    }

    $query_sql = "SELECT w.warga_nama,
CONCAT('WP ', IF(h.warga_id IS NULL, r.rumah_nomor_tampil, h.rumah_nomor_tampil),' - ', IF(h.warga_id IS NULL, IF(gang_ref.ref_id=20, gang_ref.ref_nama, CONCAT('Gg.', gang_ref.ref_nama)), h.gang_tampil)) AS alamat,
IF(h.warga_id IS NULL, hubkel_ref.ref_nama, h.status_keluarga) AS hubungan_keluarga,
IF(h.warga_id IS NULL, r.rumah_nomor, h.rumah_nomor) AS rumah_nomor,
IF(h.warga_id IS NULL, CAST(r.rumah_nomor_tampil AS INT), h.nomor_rumah) AS nomor_rumah_tampil " . $from_join_clause . $ajax_where_clause . " ORDER BY rumah_nomor, IF(h.`warga_parent` IS NULL, w.warga_id, h.`warga_parent`), hubkel_ref.ref_id, hubungan_keluarga, w.warga_nama ASC ";

    $result = mysqli_query($conn, $query_sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $warga_data[] = $row;
        }
        echo json_encode($warga_data);
    } else {
        echo json_encode(['error' => 'Gagal mengambil data: ' . mysqli_error($conn)]);
    }
    exit;
}

// --- Ambil data statistik dengan filter ---
$usia_query = "SELECT
	SUM(CASE WHEN w.warga_tgl_lahir='' OR w.warga_tgl_lahir IS NULL OR w.warga_tgl_lahir='0000-00-00' THEN 1 ELSE 0 END) AS 'N/A',
    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 0 AND 5 THEN 1 ELSE 0 END) AS '0-5',
    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 6 AND 10 THEN 1 ELSE 0 END) AS '6-10',
    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 11 AND 15 THEN 1 ELSE 0 END) AS '11-15',
    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 16 AND 20 THEN 1 ELSE 0 END) AS '16-20',
    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 21 AND 25 THEN 1 ELSE 0 END) AS '21-25',
    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 26 AND 30 THEN 1 ELSE 0 END) AS '26-30',
    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 31 AND 35 THEN 1 ELSE 0 END) AS '31-35',
    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 36 AND 40 THEN 1 ELSE 0 END) AS '36-40',
    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 41 AND 45 THEN 1 ELSE 0 END) AS '41-45',
    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 46 AND 50 THEN 1 ELSE 0 END) AS '46-50',
    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 51 AND 55 THEN 1 ELSE 0 END) AS '51-55',
    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) BETWEEN 56 AND 60 THEN 1 ELSE 0 END) AS '56-60',
    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, w.warga_tgl_lahir, CURDATE()) >= 61 THEN 1 ELSE 0 END) AS '61+'
    $from_join_clause
    $where_clause AND 1 ";
$usia_result = mysqli_query($conn, $usia_query);
$usia_range = mysqli_fetch_assoc($usia_result);

$ktp_query = "SELECT
                CASE
                    WHEN w.is_ktp_wp = 1 THEN 'KTP WP'
                    WHEN w.is_ktp_wp = 0 THEN 'Bukan KTP WP'
                    WHEN w.is_ktp_wp = 2 THEN 'Belum Ber-KTP'
                    ELSE 'Tidak Didefinisikan'
                END as status_ktp,
                COUNT(w.warga_id) as jumlah
              $from_join_clause
              $where_clause
              GROUP BY status_ktp
              ORDER BY FIELD(status_ktp, 'KTP WP', 'Bukan KTP WP', 'Belum Ber-KTP', 'Tidak Didefinisikan')";
$ktp_result = mysqli_query($conn, $ktp_query);
$ktp_data = [];
while ($row = mysqli_fetch_assoc($ktp_result)) {
    $ktp_data[$row['status_ktp']] = (int)$row['jumlah'];
}

$status_rumah_query = "SELECT
                CASE
                    WHEN wr.ref_id_status_rumah = 169 THEN 'Milik Sendiri'
                    WHEN wr.ref_id_status_rumah = 170 THEN 'Sewa/Kontrak'
                    WHEN wr.ref_id_status_rumah = 171 THEN 'Mendiami'
                    ELSE 'Tidak Didefinisikan'
                END as status_rumah,
                COUNT(w.warga_id) as jumlah
              $from_join_clause
              $where_clause AND wr.ref_id_status_rumah IS NOT NULL
              GROUP BY status_rumah
              ORDER BY FIELD(status_rumah, 'Milik Sendiri', 'Mendiami', 'Sewa/Kontrak', 'Tidak Didefinisikan')";
$status_rumah_result = mysqli_query($conn, $status_rumah_query);
$status_rumah_data = [];
while ($row = mysqli_fetch_assoc($status_rumah_result)) {
    $status_rumah_data[$row['status_rumah']] = (int)$row['jumlah'];
}

$jk_data = getCount($conn, 'ref_id_jenis_kelamin', 'jenis_kelamin', $from_join_clause, $where_clause);
$agama_data = getCount($conn, 'ref_id_agama', 'agama', $from_join_clause, $where_clause);
$pendidikan_data = getCount($conn, 'ref_id_pendidikan', 'pendidikan', $from_join_clause, $where_clause);
$pekerjaan_data = getCount($conn, 'ref_id_pekerjaan', 'pekerjaan', $from_join_clause, $where_clause);
$status_nikah_data = getCount($conn, 'ref_id_status_kawin', 'status_kawin', $from_join_clause, $where_clause);
$keluarga_data = getCount($conn, 'ref_id_hubungan_keluarga', 'hubungan_keluarga', $from_join_clause, $where_clause);
?>

<?php include '../views/header.php'; ?>
<?php include '../views/sidebar.php'; ?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Grafik Statistik Warga</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <style>
        .content { margin-left: 100px; padding: 20px; }
        .charts-container { display: flex; flex-wrap: wrap; gap: 30px; justify-content: center; padding: 20px 0; }
        .chart-wrapper { flex: 1 1 100%; max-width: 100%; min-width: 300px; background: #fff; padding: 20px; box-sizing: border-box; border-radius: 12px; box-shadow: 0 6px 12px rgba(0,0,0,0.15); display: flex; justify-content: center; align-items: center; flex-direction: column; }
        canvas { width: 100% !important; height: 100% !important; max-width: 100%; max-height: 450px; }
        #wargaDataTable { margin-top: 40px; padding: 20px; background-color: #fff; border-radius: 12px; box-shadow: 0 6px 12px rgba(0,0,0,0.15); overflow-x: auto; }
        #wargaDataTable table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        #wargaDataTable th, #wargaDataTable td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        #wargaDataTable th { background-color: #f2f2f2; font-weight: bold; }
        #loadingIndicator { text-align: center; margin-top: 20px; font-size: 1.1em; color: #555; }
        @media (max-width: 768px) { .content { margin-left: 0; } .chart-wrapper { max-width: 100%; } .charts-container, #wargaDataTable { padding: 10px; } }
    </style>
</head>

<body>
<div class="content">
    <div class="container-fluid mt-4">
        <h4 class="mb-3">📊 Statistik Data Warga</h4>
        <a href="dashboard.php" class="btn btn-sm btn-secondary mb-4">← Kembali ke Dashboard</a>

        <form method="get" id="filterForm" class="mb-4 p-3 border rounded bg-light">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <!-- <label for="status" class="form-label fw-bold">Filter Status Domisili:</label> -->
                    <select name="status" id="status" class="form-select" onchange="this.form.submit()">
                        <option value="">🔎 Tampilkan: Semua Status Domisili</option>
                        <option value="Menetap di WP" <?= $status === 'Menetap di WP' ? 'selected' : '' ?>>🏠 Menetap di WP</option>
                        <option value="Pindah dari WP" <?= $status === 'Pindah dari WP' ? 'selected' : '' ?>>🚚 Pindah dari WP</option>
                        <option value="Meninggal Dunia" <?= $status === 'Meninggal Dunia' ? 'selected' : '' ?>>🕒 Meninggal Dunia</option>
                        <option value="Tidak Tinggal di WP" <?= $status === 'Tidak Tinggal di WP' ? 'selected' : '' ?>>🌌 Tidak Tinggal di WP</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <!-- <label for="gang" class="form-label fw-bold">Filter Gang/Jalan:</label> -->
                    <select name="gang" id="gang" class="form-select" onchange="this.form.submit()">
                        <option value="">🚏 Tampilkan: Semua Gang/Jalan</option>
                        <?php
                        $gang_query = mysqli_query($conn, "SELECT ref_nama FROM referensi WHERE ref_kategori='gang' ORDER BY ref_nama ASC");
                        while ($g = mysqli_fetch_assoc($gang_query)) :
                        ?>
                            <option value="<?= htmlspecialchars($g['ref_nama']) ?>" <?= $gang === $g['ref_nama'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($g['ref_nama']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-auto">
                     <a href="grafik.php" class="btn btn-outline-secondary w-100">🔁 Reset Filter</a>
                </div>
            </div>
        </form>

        <div class="row gy-4 charts-container">
            <div class="col-12 mb-5 chart-wrapper"><canvas id="jkChart"></canvas></div>
            <div class="col-12 mb-5 chart-wrapper"><canvas id="usiaChart"></canvas></div>
            <div class="col-12 mb-5 chart-wrapper"><canvas id="agamaChart"></canvas></div>
            <div class="col-12 mb-5 chart-wrapper"><canvas id="pendidikanChart"></canvas></div>
            <div class="col-12 mb-5 chart-wrapper"><canvas id="pekerjaanChart"></canvas></div>
            <div class="col-12 mb-5 chart-wrapper"><canvas id="nikahChart"></canvas></div>
            <div class="col-12 mb-5 chart-wrapper"><canvas id="keluargaChart"></canvas></div>
            <div class="col-12 mb-5 chart-wrapper"><canvas id="ktpChart"></canvas></div>
            <div class="col-12 mb-5 chart-wrapper"><canvas id="status_rumahChart"></canvas></div>            
        </div>

        <div id="loadingIndicator" style="display: none;">Memuat data...</div>
        <div id="wargaDataTable" style="display: none;"></div>
    </div>
</div>

<script>
Chart.register(ChartDataLabels);

const dataSets = {
    jk: { labels: <?= json_encode(array_keys($jk_data)) ?>, data: <?= json_encode(array_values($jk_data)) ?> },
    usia: { labels: <?= json_encode(array_keys($usia_range)) ?>, data: <?= json_encode(array_values($usia_range)) ?> },
    agama: { labels: <?= json_encode(array_keys($agama_data)) ?>, data: <?= json_encode(array_values($agama_data)) ?> },
    pendidikan: { labels: <?= json_encode(array_keys($pendidikan_data)) ?>, data: <?= json_encode(array_values($pendidikan_data)) ?> },
    pekerjaan: { labels: <?= json_encode(array_keys($pekerjaan_data)) ?>, data: <?= json_encode(array_values($pekerjaan_data)) ?> },
    nikah: { labels: <?= json_encode(array_keys($status_nikah_data)) ?>, data: <?= json_encode(array_values($status_nikah_data)) ?> },
    keluarga: { labels: <?= json_encode(array_keys($keluarga_data)) ?>, data: <?= json_encode(array_values($keluarga_data)) ?> },
    ktp: { labels: <?= json_encode(array_keys($ktp_data)) ?>, data: <?= json_encode(array_values($ktp_data)) ?> },
    status_rumah: { labels: <?= json_encode(array_keys($status_rumah_data)) ?>, data: <?= json_encode(array_values($status_rumah_data)) ?> }
};

function generateColors(length) {
    const base = ['#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b','#858796','#fd7e14','#20c997','#6f42c1','#0dcaf0'];
    return Array.from({ length }, (_, i) => base[i % base.length]);
}

async function displayWargaTable(data, title) {
    const tableContainer = document.getElementById('wargaDataTable');
    tableContainer.style.display = 'block';
    if (data.length === 0) {
        tableContainer.innerHTML = `<p class="text-center mt-3">Tidak ada data warga untuk kriteria ini.</p>`;
        return;
    }
    let tableHTML = `<h5 class="mb-3">Data Warga: ${title}</h5><table class="table table-bordered table-striped table-hover"><thead><tr><th class="text-center">No.</th><th class="text-center">Nama</th><th class="text-center">Alamat</th><th class="text-center">Status Keluarga</th></tr></thead><tbody>`;
    data.forEach((warga, index) => {
        tableHTML += `<tr><td class="text-center">${index + 1}</td><td>${warga.warga_nama || '-'}</td><td>${warga.alamat || '-'}</td><td>${warga.hubungan_keluarga || '-'}</td></tr>`;
    });
    tableHTML += `</tbody></table>`;
    tableContainer.innerHTML = tableHTML;
}

async function fetchWargaData(chartType, label) {
    const loadingIndicator = document.getElementById('loadingIndicator');
    const tableContainer = document.getElementById('wargaDataTable');
    const statusFilter = document.getElementById('status').value;
    const gangFilter = document.getElementById('gang').value;

    loadingIndicator.style.display = 'block';
    tableContainer.style.display = 'none';

    try {
        const response = await fetch(`grafik.php?action=get_warga_data&chart_type=${chartType}&label=${encodeURIComponent(label)}&status=${encodeURIComponent(statusFilter)}&gang=${encodeURIComponent(gangFilter)}`);
        const data = await response.json();
        
        if (data.error) {
            tableContainer.innerHTML = `<p class="alert alert-danger">Terjadi kesalahan: ${data.error}</p>`;
        } else {
            const chartTitleMap = { jk: 'Jenis Kelamin', usia: 'Rentang Usia', agama: 'Agama', pendidikan: 'Pendidikan', pekerjaan: 'Pekerjaan', nikah: 'Status Perkawinan', keluarga: 'Status Dalam Keluarga', ktp: 'Status KTP', status_rumah: 'Status Rumah' };
            displayWargaTable(data, `${chartTitleMap[chartType]}: ${label}`);
        }
    } catch (error) {
        tableContainer.innerHTML = `<p class="alert alert-danger">Gagal memuat data warga. Silakan coba lagi.</p>`;
    } finally {
        loadingIndicator.style.display = 'none';
        tableContainer.style.display = 'block';
    }
}

function generateChart(id, label, labels, data, type = 'bar') {
    const ctx = document.getElementById(id).getContext('2d');
    new Chart(ctx, {
        type: type,
        data: {
            labels: labels,
            datasets: [{ label: label, data: data, backgroundColor: generateColors(labels.length), borderWidth: 1 }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            plugins: {
                datalabels: { color: '#000', anchor: 'center', align: type === 'pie' ? 'center' : 'end', font: { weight: 'bold', size: 12 }, formatter: (value) => value > 0 ? value : '' },
                legend: { display: type === 'pie' || type === 'doughnut' },
                title: { display: true, text: label, font: { size: 16 } },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (type === 'pie' || type === 'doughnut') {
                                let sum = context.dataset.data.reduce((a, b) => a + b, 0);
                                if (sum === 0) return `${context.label}: 0 (0%)`;
                                let percentage = (context.parsed / sum * 100).toFixed(1) + '%';
                                return `${context.label}: ${context.parsed} (${percentage})`;
                            }
                            return `${context.label}: ${context.parsed}`;
                        }
                    }
                }
            },
            scales: type.includes('pie') || type.includes('doughnut') ? {} : { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { autoSkip: false, maxRotation: 45, minRotation: 0 } } },
            onClick: (event, elements, chart) => {
                if (elements.length > 0) {
                    const index = elements[0].index;
                    const clickedLabel = chart.data.labels[index];
                    const chartType = chart.canvas.id.replace('Chart', '');
                    fetchWargaData(chartType, clickedLabel);
                }
            }
        },
        plugins: [ChartDataLabels]
    });
}

// Panggil fungsi generateChart untuk setiap grafik
generateChart('jkChart', 'Distribusi Jenis Kelamin', dataSets.jk.labels, dataSets.jk.data, 'pie');
generateChart('usiaChart', 'Distribusi Rentang Usia', dataSets.usia.labels, dataSets.usia.data, 'bar');
generateChart('agamaChart', 'Distribusi Agama', dataSets.agama.labels, dataSets.agama.data, 'bar');
generateChart('pendidikanChart', 'Distribusi Tingkat Pendidikan', dataSets.pendidikan.labels, dataSets.pendidikan.data, 'bar');
generateChart('pekerjaanChart', 'Distribusi Jenis Pekerjaan', dataSets.pekerjaan.labels, dataSets.pekerjaan.data, 'bar');
generateChart('nikahChart', 'Distribusi Status Perkawinan', dataSets.nikah.labels, dataSets.nikah.data, 'bar');
generateChart('keluargaChart', 'Distribusi Status Dalam Keluarga', dataSets.keluarga.labels, dataSets.keluarga.data, 'bar');
generateChart('ktpChart', 'Distribusi Status KTP', dataSets.ktp.labels, dataSets.ktp.data, 'bar');
generateChart('status_rumahChart', 'Distribusi Status Rumah', dataSets.status_rumah.labels, dataSets.status_rumah.data, 'bar');
</script>
</body>
</html>
<script>
    // JSON Encode dengan flags keamanan untuk mencegah XSS saat parsing JS
    const dataSets = <?= json_encode($dataSets, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>
<?php include '../views/footer.php'; ?>
