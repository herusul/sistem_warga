<?php
// Output Buffering untuk mencegah error "File corrupt" pada PDF
ob_start();

require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../libs/tfpdf/tfpdf.php');

check_auth(['superadmin', 'operator', 'admin']);

// Set Time Limit (5 menit) untuk menangani data yang banyak
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
$data_query = "SELECT nr.rumah_nomor, nr.`rumah_nomor_tampil`, IF(e.`ref_id`=20, e.`ref_nama`, CONCAT('Gg.', e.`ref_nama`)) AS gang, e.`ref_urut`, 
kk.`warga_nama` AS nama_kk, kk.`warga_no_hp` AS nomor_hp, CASE WHEN kk.`warga_id`IN (7, 34) THEN 3 ELSE kk.jumlah_penghuni END AS jumlah_penghuni
FROM
  `rumah` nr
LEFT JOIN (SELECT DISTINCT kk.`warga_id`, nr.`rumah_id`, nr.`rumah_nomor`, nr.`rumah_nomor_tampil`, kk.`warga_nama`, kk.`warga_no_hp`,
COUNT(agt.`warga_id`) + 1 AS jumlah_penghuni
FROM `warga_rumah` mr
JOIN `rumah` nr ON nr.`rumah_id`=mr.`rumah_id` AND nr.`is_aktif`=1
JOIN `warga` kk ON kk.`warga_id`=mr.`warga_id`
JOIN `warga_mutasi` mk ON mk.`warga_id`=kk.`warga_id`
LEFT JOIN (SELECT agt.warga_id, agt.`warga_parent`, agt.`ref_id_hubungan_keluarga`
FROM `warga` agt
JOIN `warga_mutasi` mk ON mk.`warga_id`=agt.`warga_id`
WHERE mk.`is_aktif`=1 AND mk.`ref_id_status_aktif`=161
) agt ON agt.`warga_parent` = kk.`warga_id`
WHERE kk.`ref_id_hubungan_keluarga`=34
AND mr.is_aktif=1 AND mk.`is_aktif`=1 AND mk.`ref_id_status_aktif`=161 OR kk.`warga_id`IN (7, 34)
GROUP BY kk.`warga_id`, nr.`rumah_nomor`, kk.`warga_nama`) kk ON kk.`rumah_id`=nr.`rumah_id`
JOIN `referensi` e ON e.`ref_id`=nr.`ref_id_gang` AND e.`ref_kategori`='gang'
WHERE nr.is_aktif=1 
UNION 
SELECT '1000' AS rumah_nomor, '-----------' AS rumah_nomor_tampil, '--------------------' AS gang, '' AS ref_urut, 'JUMLAH TOTAL' AS nama_kk, '' AS nomor_hp, COUNT(kk.`warga_id`)+6 AS jumlah_penghuni
FROM `warga` kk
JOIN `warga_mutasi` mk ON mk.`warga_id`=kk.`warga_id`
WHERE mk.`is_aktif`=1 AND mk.`ref_id_status_aktif`=161
ORDER BY CAST(rumah_nomor AS UNSIGNED), ref_urut ASC, nama_kk ASC";

$result = mysqli_query($conn, $data_query);
if (!$result) {
    die('Query Error.');
}

// Inisialisasi PDF
$pdf = new PDF();
$pdf->AddFont('DejaVu', '', 'DejaVuSansCondensed.ttf', true);
$pdf->SetFont('DejaVu', '', 12);

// Tentukan lebar kolom tabel
$widths = array(10, 22, 31, 65, 30);
$total_width = array_sum($widths);

// Fungsi untuk header tabel
function headerTabel($pdf, $widths, $total_width) {
    $header = array('No.', 'No.Rumah', 'Gang/Jalan', 'Nama Kepala Keluarga (KK)', 'Jumlah Keluarga');
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

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 10, 'DATA DAN JUMLAH WARGA RT 03 PERUMAHAN WAHANA PRAJA I BULAN ' . $bulan_indonesia . ' ' . $tahun_sekarang, 0, 1, 'C');
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
    $pdf->Cell($widths[1], 10, $row['rumah_nomor_tampil'], 1, 0, 'C');
    $pdf->Cell($widths[2], 10, $row['gang'], 1);
    $pdf->Cell($widths[3], 10, $row['nama_kk'], 1);
    $pdf->Cell($widths[4], 10, $row['jumlah_penghuni'], 1, 0, 'C');
    $pdf->Ln();
}

if (ob_get_length()) ob_end_clean();
$pdf->Output('D', 'Data_Nama_KK_WP_Jumlah_Keluarga.pdf');
?>
