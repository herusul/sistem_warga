<?php
// Output Buffering untuk mencegah error "File corrupt" pada PDF
ob_start();

require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../libs/tfpdf/tfpdf.php');

check_auth(['superadmin', 'operator', 'admin']);

// Mencegah Timeout
set_time_limit(300);

class PDF extends tFPDF
{
    function Header()
    {
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('DejaVu', '', 10);
        $this->Cell(0, 10, 'Hal. ' . $this->PageNo() . '', 0, 0, 'R');
    }
}

// Query untuk mengambil data
$data_query = "SELECT a.warga_id, a.`warga_nama_tampil`, IF(h.warga_id IS NULL, g.`ref_nama`, h.status_keluarga) AS hubungan_keluarga, 
IF(h.warga_id IS NULL, d.rumah_nomor_tampil, h.rumah_nomor_tampil) AS rumah_nomor_tampil,
IF(h.warga_id IS NULL, d.rumah_nomor, h.rumah_nomor) AS rumah_nomor,
IF(h.warga_id IS NULL, IF(e.ref_id=20, e.ref_nama, CONCAT('Gg.', e.ref_nama)), h.gang_tampil) AS gang_tampil,
IF(h.warga_id IS NULL, e.ref_nama, h.gang) AS gang,
a.`warga_no_hp`, c.`ref_nama`, DATE_FORMAT(a.warga_tgl_lahir,'%d-%m-%Y') AS warga_tgl_lahir 
FROM warga a
LEFT JOIN warga_mutasi b ON a.warga_id=b.warga_id AND b.is_aktif=1
LEFT JOIN referensi c ON c.ref_id=b.ref_id_status_aktif AND c.ref_kategori='status_aktif'
LEFT JOIN `warga_rumah` f ON a.warga_id=f.`warga_id` AND f.`is_aktif`=1
LEFT JOIN `rumah` d ON d.`rumah_id`=f.`rumah_id`
LEFT JOIN `referensi` e ON e.`ref_id`=d.`ref_id_gang` AND e.`ref_kategori`='gang'
LEFT JOIN `referensi` g ON g.`ref_id`=a.`ref_id_hubungan_keluarga` AND g.`ref_kategori`='hubungan_keluarga'
LEFT JOIN (
SELECT c.`warga_id`, c.`warga_nama_tampil`, 
CASE WHEN c.`ref_id_hubungan_keluarga`=49 THEN CONCAT(c.`warga_hubungan_keluarga`,' (KK : ',a.warga_nama_tampil,')')
WHEN a.`ref_id_hubungan_keluarga`<>49 THEN CONCAT(h.`ref_nama`,' (KK : ',a.warga_nama_tampil,')')
ELSE '' END AS status_keluarga, a.warga_nama_tampil AS nama_kk, c.`warga_parent`,
d.rumah_nomor_tampil, d.rumah_nomor, IF(e.`ref_id`=20, e.`ref_nama`, CONCAT('Gg.', e.`ref_nama`)) AS gang_tampil, e.ref_nama AS gang
FROM warga a
JOIN `referensi` g ON g.`ref_id`=a.`ref_id_hubungan_keluarga` AND g.`ref_kategori`='hubungan_keluarga'
JOIN warga c ON c.`warga_parent`=a.`warga_id`
JOIN `referensi` h ON h.`ref_id`=c.`ref_id_hubungan_keluarga` AND h.`ref_kategori`='hubungan_keluarga'
JOIN `warga_rumah` f ON a.warga_id=f.`warga_id` AND f.`is_aktif`=1
JOIN `rumah` d ON d.`rumah_id`=f.`rumah_id` AND d.`is_aktif`=1
JOIN `referensi` e ON e.`ref_id`=d.`ref_id_gang` AND e.`ref_kategori`='gang'
) h ON h.warga_id=a.warga_id
WHERE a.is_delete IS NULL
AND (c.ref_nama = 'Menetap di WP' OR a.`warga_id` IN (7, 34) OR h.warga_parent IN (7, 34))
ORDER BY rumah_nomor, IF(h.`warga_parent` IS NULL, a.warga_id, h.`warga_parent`), g.`ref_id`, hubungan_keluarga, a.warga_nama_tampil ASC";

$result = mysqli_query($conn, $data_query);
if (!$result) {
    die('Query Error.');
}

// Inisialisasi PDF
$pdf = new PDF();
$pdf->AddFont('DejaVu', '', 'DejaVuSansCondensed.ttf', true);
$pdf->SetFont('DejaVu', '', 12);

// Tentukan lebar kolom tabel
$widths = array(9, 69, 76, 18, 24);
$total_width = array_sum($widths);

// Fungsi untuk header tabel
function headerTabel($pdf, $widths, $total_width) {
    $header = array('No.', 'Nama Warga', 'Status Keluarga', 'No.Rumah', 'Gang/Jalan');
    $left_margin = ($pdf->GetPageWidth() - $total_width) / 2;
    $pdf->SetX($left_margin);

    $pdf->SetFont('DejaVu', '', 10);
    $pdf->SetFillColor(200, 200, 200);
    foreach ($header as $i => $col) {
        $pdf->Cell($widths[$i], 10, $col, 1, 0, 'C', true);
    }
    $pdf->Ln();
}

// Tambahkan halaman pertama
$pdf->AddPage('P', 'A4');

// Judul
$nama_bulan = array(
    'January' => 'JANUARI',
    'February' => 'FEBRUARI',
    'March' => 'MARET',
    'April' => 'APRIL',
    'May' => 'MEI',
    'June' => 'JUNI',
    'July' => 'JULI',
    'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER',
    'October' => 'OKTOBER',
    'November' => 'NOVEMBER',
    'December' => 'DESEMBER'
);

$bulan_sekarang = date('F');
$tahun_sekarang = date('Y');
$bulan_indonesia = $nama_bulan[$bulan_sekarang];

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 10, 'DATA WARGA RT 03 PERUMAHAN WAHANA PRAJA I BULAN ' . $bulan_indonesia . ' ' . $tahun_sekarang, 0, 1, 'C');
$pdf->Cell(0, 10, 'PADUKUHAN BANGLEN KALURAHAN WIDODOMARTANI KAPANEWON NGEMPLAK', 0, 1, 'C');

$pdf->Ln(5);

headerTabel($pdf, $widths, $total_width);

$no = 1;
while ($row = mysqli_fetch_assoc($result)) {
    if ($pdf->GetY() > 270) {
        $pdf->AddPage('P', 'A4');
        headerTabel($pdf, $widths, $total_width);
    }
    
    $left_margin = ($pdf->GetPageWidth() - $total_width) / 2;
    $pdf->SetX($left_margin);

    $pdf->Cell($widths[0], 10, $no++, 1, 0, 'C');
    $pdf->Cell($widths[1], 10, $row['warga_nama_tampil'], 1);
    $pdf->Cell($widths[2], 10, $row['hubungan_keluarga'], 1);
    $pdf->Cell($widths[3], 10, $row['rumah_nomor_tampil'], 1, 0, 'C');
    $pdf->Cell($widths[4], 10, $row['gang_tampil'], 1);
    $pdf->Ln();
}

if (ob_get_length()) ob_end_clean();
$pdf->Output('D', 'Data_Nama_Warga_WP.pdf');
?>
