<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['superadmin', 'operator', 'admin']);

if (!isset($_GET['id'])) {
    header("Location: warga.php");
    exit;
}

$encrypted_id = mysqli_real_escape_string($conn, $_GET['id']);
$warga_id = decrypt_id($encrypted_id);

if ($warga_id === false || !is_numeric($warga_id)) {
    header("Location: warga.php");
    exit;
}
$warga_id = (int)$warga_id;

$sql_data = "SELECT a.*, IF(m.warga_id IS NULL, CAST(b.rumah_nomor_tampil AS INT), m.nomor_rumah) AS nomor_rumah_tampil,
    IF(m.warga_id IS NULL, IF(c.ref_id=20, c.ref_nama, CONCAT('Gg.', c.ref_nama)), m.gang) AS nama_gang, d.ref_nama AS nama_agama,
    e.ref_nama AS nama_jk, f.ref_nama AS nama_pendidikan, g.ref_nama AS nama_pekerjaan, h.ref_nama AS nama_status_kawin,
    IF(m.warga_id IS NULL, i.ref_nama, m.status_keluarga) AS nama_shdk, j.ref_nama AS nama_status_aktif, k.ref_nama AS nama_gd,
    IF(j.ref_nama='Meninggal Dunia', u.tgl_lahir_pnd, CONCAT(u.tgl_lahir_pnd,' (',u.usia,')')) AS warga_tgl_lahir_usia
FROM warga a
LEFT JOIN warga_rumah wr ON a.warga_id = wr.warga_id AND wr.is_aktif = 1
LEFT JOIN rumah b ON b.rumah_id = wr.rumah_id
LEFT JOIN referensi c ON c.ref_id = b.ref_id_gang AND c.ref_kategori='gang'
LEFT JOIN referensi d ON d.ref_id = a.ref_id_agama AND d.ref_kategori='agama'
LEFT JOIN referensi e ON e.ref_id = a.ref_id_jenis_kelamin AND e.ref_kategori='jenis_kelamin'
LEFT JOIN referensi f ON f.ref_id = a.ref_id_pendidikan AND f.ref_kategori='pendidikan'
LEFT JOIN referensi g ON g.ref_id = a.ref_id_pekerjaan AND g.ref_kategori='pekerjaan'
LEFT JOIN referensi h ON h.ref_id = a.ref_id_status_kawin AND h.ref_kategori='status_kawin'
LEFT JOIN referensi i ON i.ref_id = a.ref_id_hubungan_keluarga AND i.ref_kategori='hubungan_keluarga'
LEFT JOIN referensi k ON k.ref_id = a.ref_id_golongan_darah AND k.ref_kategori='golongan_darah'
LEFT JOIN warga_mutasi wm ON a.warga_id = wm.warga_id AND wm.is_aktif = 1
LEFT JOIN referensi j ON j.ref_id = wm.ref_id_status_aktif AND j.ref_kategori='status_aktif'
JOIN wahanapraja.`v_warga_usia` u ON u.`warga_id`=a.`warga_id`
LEFT JOIN (
    SELECT c.`warga_id`, c.`warga_nama`,
    CASE WHEN c.`ref_id_hubungan_keluarga`=49 THEN CONCAT(c.`warga_hubungan_keluarga`,' (KK : ',a.warga_nama,')')
    WHEN c.`ref_id_hubungan_keluarga`<>49 THEN CONCAT(h.`ref_nama`,' (KK : ',a.warga_nama,')')
    ELSE '' END AS status_keluarga,
    CAST(d.rumah_nomor_tampil AS INT) nomor_rumah, IF(e.`ref_id`=20, e.`ref_nama`, CONCAT('Gg.', e.`ref_nama`)) AS gang
    FROM warga a
    JOIN warga c ON c.`warga_parent`=a.`warga_id`
    JOIN `referensi` h ON h.`ref_id`=c.`ref_id_hubungan_keluarga` AND h.`ref_kategori`='hubungan_keluarga'
    JOIN `warga_rumah` f ON a.warga_id=f.`warga_id` AND f.`is_aktif`=1
    JOIN `rumah` d ON d.`rumah_id`=f.`rumah_id` AND d.`is_aktif`=1
    JOIN `referensi` e ON e.`ref_id`=d.`ref_id_gang` AND e.`ref_kategori`='gang'
) m ON m.warga_id=a.warga_id
WHERE a.warga_id = ?";

$stmt = mysqli_prepare($conn, $sql_data);
mysqli_stmt_bind_param($stmt, "i", $warga_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) === 0) {
    header("Location: warga.php");
    exit;
}

$data = mysqli_fetch_assoc($result);
$status_class = match($data['nama_status_aktif']) {
    'Menetap di WP' => 'bg-success',
    'Pindah dari WP' => 'bg-warning text-dark',
    'Meninggal Dunia' => 'bg-danger',
    default => 'bg-secondary'
};
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container-fluid">
    <!-- Header Navigation -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0 text-gray-800 fw-bold"><i class="bi bi-person-badge me-2"></i>Detail Profil Warga</h1>
        <a href="warga.php" class="btn btn-sm btn-outline-secondary px-3"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
    </div>

    <div class="row">
        <!-- Sidebar Profile -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-body text-center py-5">
                    <div class="position-relative d-inline-block mb-3">
                        <?php if ($data['warga_foto']) : ?>
                            <img src="/assets/uploads/<?= e($data['warga_foto']) ?>" class="rounded-circle border border-4 border-white shadow" style="width: 175px; height: 175px; object-fit: cover;">
                        <?php else : ?>
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center border border-4 border-white shadow" style="width: 175px; height: 175px;">
                                <i class="bi bi-person text-muted" style="font-size: 4rem;"></i>
                            </div>
                        <?php endif; ?>
                        <span class="position-absolute bottom-0 end-0 p-2 badge rounded-pill <?= $status_class ?> border border-white" style="font-size: 0.7rem;">
                            <?= e($data['nama_status_aktif']) ?>
                        </span>
                    </div>
                    <h4 class="fw-bold mb-1"><?= e($data['warga_nama']) ?></h4>
                    <p class="text-muted small mb-3"><?= e($data['nama_shdk']) ?></p>
                    <div class="d-flex justify-content-center gap-2">
                        <span class="badge bg-light text-primary border border-primary"><?= e($data['nama_jk']) ?></span>
                        <span class="badge bg-light text-info border border-info">Goldar. <?= e($data['nama_gd'] ?: '-') ?></span>
                    </div>
                </div>
                <div class="card-footer bg-white border-top-0 py-3">
                    <div class="row text-center">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-uppercase text-muted mb-1 small">No. Rumah</div>
                            <div class="h6 mb-0 fw-bold"><?= e($data['nomor_rumah_tampil'] ?: '-') ?></div>
                        </div>
                        <div class="col border-start">
                            <div class="text-xs font-weight-bold text-uppercase text-muted mb-1 small">Gang</div>
                            <div class="h6 mb-0 fw-bold"><?= e($data['nama_gang'] ?: '-') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="bi bi-telephone me-2"></i>Kontak</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="small text-muted mb-0">Nomor HP / WA</label>
                        <div class="fw-bold"><?= e($data['warga_no_hp']) ?: '<em class="text-muted">Tidak ada</em>' ?></div>
                    </div>
                    <div>
                        <label class="small text-muted mb-0">Alamat Email</label>
                        <div class="fw-bold"><?= e($data['warga_email']) ?: '<em class="text-muted">Tidak ada</em>' ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Info -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-primary"><i class="bi bi-person-lines-fill me-2"></i>Informasi Kependudukan</h6>
                    <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] != 'pengguna') : ?>
                        <a href="warga_edit.php?id=<?= $encrypted_id ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i> Edit Data</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted small text-uppercase">NIK</div>
                        <div class="col-sm-8 fw-bold"><?= e($data['warga_nik']) ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted small text-uppercase">Nomor KK</div>
                        <div class="col-sm-8 fw-bold"><?= e($data['warga_nomor_kk']) ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted small text-uppercase">Tempat, Tgl Lahir</div>
                        <div class="col-sm-8 fw-bold"><?= e($data['warga_tempat_lahir']) ?>, <?= e($data['warga_tgl_lahir_usia']) ?></div>
                    </div>
                    <hr>
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted small text-uppercase">Agama</div>
                        <div class="col-sm-8 fw-bold"><?= e($data['nama_agama']) ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted small text-uppercase">Status Kawin</div>
                        <div class="col-sm-8 fw-bold"><?= e($data['nama_status_kawin']) ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted small text-uppercase">Pendidikan</div>
                        <div class="col-sm-8 fw-bold"><?= e($data['nama_pendidikan']) ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted small text-uppercase">Pekerjaan</div>
                        <div class="col-sm-8 fw-bold"><?= $data['warga_pekerjaan'] ? e($data['warga_pekerjaan']) : e($data['nama_pekerjaan']) ?></div>
                    </div>
                    <div class="row mb-0">
                        <div class="col-sm-4 text-muted small text-uppercase">Kewarganegaraan</div>
                        <div class="col-sm-8 fw-bold"><?= e($data['warga_negara']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Documents -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 mb-4 h-100">
                        <div class="card-header bg-white py-3">
                            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-card-heading me-2"></i>Dokumen KTP</h6>
                        </div>
                        <div class="card-body text-center">
                            <?php if ($data['warga_dokumen_ktp']) : ?>
                                <div class="bg-light rounded p-3 mb-3">
                                    <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 3rem;"></i>
                                    <p class="small text-muted mt-2">Pratinjau KTP (PDF)</p>
                                </div>
                                <a href="/assets/uploads/<?= e($data['warga_dokumen_ktp']) ?>" class="btn btn-sm btn-outline-primary w-100" target="_blank"><i class="bi bi-download me-1"></i> Lihat Dokumen</a>
                            <?php else : ?>
                                <div class="py-4 text-muted small italic">Dokumen belum tersedia</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 mb-4 h-100">
                        <div class="card-header bg-white py-3">
                            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-journal-text me-2"></i>Dokumen KK</h6>
                        </div>
                        <div class="card-body text-center">
                            <?php if ($data['warga_dokumen_kk']) : ?>
                                <div class="bg-light rounded p-3 mb-3">
                                    <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 3rem;"></i>
                                    <p class="small text-muted mt-2">Pratinjau KK (PDF)</p>
                                </div>
                                <a href="/assets/uploads/<?= e($data['warga_dokumen_kk']) ?>" class="btn btn-sm btn-outline-primary w-100" target="_blank"><i class="bi bi-download me-1"></i> Lihat Dokumen</a>
                            <?php else : ?>
                                <div class="py-4 text-muted small italic">Dokumen belum tersedia</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../views/footer.php'; ?>
