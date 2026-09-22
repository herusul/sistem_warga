<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth();

$user = $_SESSION['user'];
$role = $user['role'];
$warga_id = $user['warga_id'] ?? null;

// Jika admin/operator login tanpa warga_id dan ada parameter impersonasi di URL
if (empty($warga_id) && isset($_GET['warga_id']) && in_array($role, ['superadmin', 'operator'])) {
    $warga_id = (int)decrypt_id($_GET['warga_id']);
}

// Jika masih belum ada warga_id (misal superadmin login biasa)
if (empty($warga_id)) {
    include '../../views/header.php';
    include '../../views/sidebar.php';
    ?>
    <div class="container-fluid">
        <div class="alert alert-info shadow-sm p-4 mt-4">
            <h5 class="fw-bold"><i class="bi bi-info-circle me-2"></i> Mode Pratinjau Profil Keluarga</h5>
            <p class="mb-3">Akun pengurus ini tidak terikat secara otomatis ke ID warga tertentu. Silakan pilih warga yang ingin dipratinjau atau login menggunakan akun Google warga.</p>
            <form method="get" class="row g-2 col-md-6">
                <div class="col-8">
                    <select name="warga_id" class="form-select">
                        <option value="" disabled selected>- Pilih Kepala Keluarga / Warga -</option>
                        <?php
                        $q_w = mysqli_query($conn, "SELECT warga_id, warga_nama, warga_email, ref_id_hubungan_keluarga FROM warga WHERE is_delete IS NULL ORDER BY ref_id_hubungan_keluarga, warga_nama ASC LIMIT 50");
                        while ($rw = mysqli_fetch_assoc($q_w)) {
                            $tag = ($rw['ref_id_hubungan_keluarga'] == 34) ? '[KK]' : '[Anggota]';
                            echo '<option value="' . encrypt_id($rw['warga_id']) . '">' . $tag . ' ' . htmlspecialchars($rw['warga_nama']) . ' (' . htmlspecialchars($rw['warga_email'] ?: 'tanpa email') . ')</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-4">
                    <button type="submit" class="btn btn-primary w-100">Buka Profil</button>
                </div>
            </form>
        </div>
    </div>
    <?php
    include '../../views/footer.php';
    exit;
}

// 1. Ambil data warga yang sedang aktif/login
$sql_current = "SELECT w.*, r.ref_nama AS hubungan_keluarga_nama
                FROM warga w
                LEFT JOIN referensi r ON r.ref_id = w.ref_id_hubungan_keluarga AND r.ref_kategori = 'hubungan_keluarga'
                WHERE w.warga_id = ? AND w.is_delete IS NULL";
$stmt_curr = mysqli_prepare($conn, $sql_current);
mysqli_stmt_bind_param($stmt_curr, "i", $warga_id);
mysqli_stmt_execute($stmt_curr);
$current_warga = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_curr));

if (!$current_warga) {
    die("Data warga tidak ditemukan.");
}

$is_kk = ((int)$current_warga['ref_id_hubungan_keluarga'] === 34);
$head_id = $is_kk ? $current_warga['warga_id'] : ($current_warga['warga_parent'] ?: $current_warga['warga_id']);

// 2. Ambil data Kepala Keluarga (KK)
$sql_kk = "SELECT w.*, r.ref_nama AS hubungan_keluarga_nama, jk.ref_nama AS jk_nama,
           agm.ref_nama AS agama_nama, pdk.ref_nama AS pdk_nama, pkj.ref_nama AS pkj_nama,
           kwn.ref_nama AS kwn_nama, gd.ref_nama AS gd_nama,
           d.rumah_nomor_tampil, IF(e.ref_id=20, e.ref_nama, CONCAT('Gg. ', e.ref_nama)) AS gang_tampil,
           d.rumah_nomor
           FROM warga w
           LEFT JOIN referensi r ON r.ref_id = w.ref_id_hubungan_keluarga AND r.ref_kategori = 'hubungan_keluarga'
           LEFT JOIN referensi jk ON jk.ref_id = w.ref_id_jenis_kelamin AND jk.ref_kategori = 'jenis_kelamin'
           LEFT JOIN referensi agm ON agm.ref_id = w.ref_id_agama AND agm.ref_kategori = 'agama'
           LEFT JOIN referensi pdk ON pdk.ref_id = w.ref_id_pendidikan AND pdk.ref_kategori = 'pendidikan'
           LEFT JOIN referensi pkj ON pkj.ref_id = w.ref_id_pekerjaan AND pkj.ref_kategori = 'pekerjaan'
           LEFT JOIN referensi kwn ON kwn.ref_id = w.ref_id_status_kawin AND kwn.ref_kategori = 'status_kawin'
           LEFT JOIN referensi gd ON gd.ref_id = w.ref_id_golongan_darah AND gd.ref_kategori = 'golongan_darah'
           LEFT JOIN warga_rumah wr ON wr.warga_id = w.warga_id AND wr.is_aktif = 1
           LEFT JOIN rumah d ON d.rumah_id = wr.rumah_id
           LEFT JOIN referensi e ON e.ref_id = d.ref_id_gang AND e.ref_kategori = 'gang'
           WHERE w.warga_id = ? AND w.is_delete IS NULL";
$stmt_kk = mysqli_prepare($conn, $sql_kk);
mysqli_stmt_bind_param($stmt_kk, "i", $head_id);
mysqli_stmt_execute($stmt_kk);
$data_kk = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_kk));

// 3. Ambil seluruh anggota keluarga (anak, istri, suami, famili, dll)
$sql_anggota = "SELECT w.*, r.ref_nama AS hubungan_keluarga_nama, jk.ref_nama AS jk_nama,
                agm.ref_nama AS agama_nama, pdk.ref_nama AS pdk_nama, pkj.ref_nama AS pkj_nama,
                kwn.ref_nama AS kwn_nama, gd.ref_nama AS gd_nama
                FROM warga w
                LEFT JOIN referensi r ON r.ref_id = w.ref_id_hubungan_keluarga AND r.ref_kategori = 'hubungan_keluarga'
                LEFT JOIN referensi jk ON jk.ref_id = w.ref_id_jenis_kelamin AND jk.ref_kategori = 'jenis_kelamin'
                LEFT JOIN referensi agm ON agm.ref_id = w.ref_id_agama AND agm.ref_kategori = 'agama'
                LEFT JOIN referensi pdk ON pdk.ref_id = w.ref_id_pendidikan AND pdk.ref_kategori = 'pendidikan'
                LEFT JOIN referensi pkj ON pkj.ref_id = w.ref_id_pekerjaan AND pkj.ref_kategori = 'pekerjaan'
                LEFT JOIN referensi kwn ON kwn.ref_id = w.ref_id_status_kawin AND kwn.ref_kategori = 'status_kawin'
                LEFT JOIN referensi gd ON gd.ref_id = w.ref_id_golongan_darah AND gd.ref_kategori = 'golongan_darah'
                WHERE w.warga_parent = ? AND w.warga_id != ? AND w.is_delete IS NULL
                ORDER BY w.ref_id_hubungan_keluarga ASC, w.warga_tgl_lahir ASC";
$stmt_anggota = mysqli_prepare($conn, $sql_anggota);
mysqli_stmt_bind_param($stmt_anggota, "ii", $head_id, $head_id);
mysqli_stmt_execute($stmt_anggota);
$result_anggota = mysqli_stmt_get_result($stmt_anggota);
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container-fluid">
    <!-- Header Page -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">👨‍👩‍👧‍👦 Profil Saya & Keluarga</h1>
            <p class="text-muted small mb-0">
                Kelola data kependudukan pribadi dan anggota keluarga dalam satu Kartu Keluarga
            </p>
        </div>
        <div>
            <span class="badge bg-light text-primary border border-primary px-3 py-2">
                <i class="bi bi-person-check me-1"></i> Anda login sebagai: <strong><?= e($current_warga['warga_nama']) ?></strong> (<?= e($current_warga['hubungan_keluarga_nama']) ?>)
            </span>
        </div>
    </div>

    <?php if (isset($_GET['status'])) : ?>
        <?php if ($_GET['status'] === 'tambah_sukses') : ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> Data anggota keluarga baru berhasil ditambahkan!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif ($_GET['status'] === 'edit_sukses') : ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> Perubahan data kependudukan berhasil disimpan!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- 1. KEPALA KELUARGA SECTION -->
    <?php if ($data_kk) : ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bi bi-house-door-fill me-2"></i>Data Kepala Keluarga (KK)
                </h6>
                <a href="anggota_edit.php?id=<?= encrypt_id($data_kk['warga_id']) ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-pencil-square me-1"></i> Edit Data KK
                </a>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center mb-3 mb-md-0 border-end">
                        <?php if (!empty($data_kk['warga_foto'])) : ?>
                            <img src="/assets/uploads/<?= e($data_kk['warga_foto']) ?>" class="rounded-circle shadow-sm border border-3 mb-2" style="width: 120px; height: 120px; object-fit: cover;">
                        <?php else : ?>
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto mb-2 border shadow-sm" style="width: 120px; height: 120px;">
                                <i class="bi bi-person text-secondary" style="font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>
                        <h5 class="fw-bold text-dark mb-0"><?= e($data_kk['warga_nama']) ?></h5>
                        <span class="badge bg-success mt-1">Kepala Keluarga</span>
                        <div class="small text-muted mt-2">
                            <i class="bi bi-house me-1"></i> WP <?= e($data_kk['rumah_nomor_tampil'] ?: '-') ?> (<?= e($data_kk['gang_tampil'] ?: '-') ?>)
                        </div>
                    </div>
                    <div class="col-md-9">
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="small text-muted text-uppercase mb-0">Nomor Induk Kependudukan (NIK)</label>
                                <div class="fw-bold"><?= e($data_kk['warga_nik']) ?: '-' ?></div>
                            </div>
                            <div class="col-sm-6">
                                <label class="small text-muted text-uppercase mb-0">Nomor Kartu Keluarga (KK)</label>
                                <div class="fw-bold"><?= e($data_kk['warga_nomor_kk']) ?: '-' ?></div>
                            </div>
                            <div class="col-sm-6 mt-3">
                                <label class="small text-muted text-uppercase mb-0">Tempat, Tanggal Lahir</label>
                                <div class="fw-bold"><?= e($data_kk['warga_tempat_lahir']) ?>, <?= !empty($data_kk['warga_tgl_lahir']) ? date('d-m-Y', strtotime($data_kk['warga_tgl_lahir'])) : '-' ?></div>
                            </div>
                            <div class="col-sm-6 mt-3">
                                <label class="small text-muted text-uppercase mb-0">Jenis Kelamin / Gol. Darah</label>
                                <div class="fw-bold"><?= e($data_kk['jk_nama']) ?> (Goldar: <?= e($data_kk['gd_nama'] ?: '-') ?>)</div>
                            </div>
                            <div class="col-sm-6 mt-3">
                                <label class="small text-muted text-uppercase mb-0">Kontak WhatsApp / No. HP</label>
                                <div class="fw-bold text-primary"><i class="bi bi-telephone me-1"></i><?= e($data_kk['warga_no_hp']) ?: '-' ?></div>
                            </div>
                            <div class="col-sm-6 mt-3">
                                <label class="small text-muted text-uppercase mb-0">Alamat Email (Google)</label>
                                <div class="fw-bold"><?= e($data_kk['warga_email']) ?: '<span class="text-muted fst-italic">Belum ditautkan</span>' ?></div>
                            </div>
                            <div class="col-sm-6 mt-3">
                                <label class="small text-muted text-uppercase mb-0">Agama / Status Kawin</label>
                                <div class="fw-bold"><?= e($data_kk['agama_nama']) ?> / <?= e($data_kk['kwn_nama']) ?></div>
                            </div>
                            <div class="col-sm-6 mt-3">
                                <label class="small text-muted text-uppercase mb-0">Pendidikan / Pekerjaan</label>
                                <div class="fw-bold"><?= e($data_kk['pdk_nama']) ?> - <?= e($data_kk['warga_pekerjaan'] ?: $data_kk['pkj_nama']) ?></div>
                            </div>
                        </div>

                        <!-- Dokumen KTP & KK -->
                        <div class="mt-3 pt-2 border-top d-flex gap-2">
                            <?php if (!empty($data_kk['warga_dokumen_ktp'])) : ?>
                                <a href="/assets/uploads/<?= e($data_kk['warga_dokumen_ktp']) ?>" target="_blank" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-file-earmark-pdf me-1"></i> Scan KTP KK
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($data_kk['warga_dokumen_kk'])) : ?>
                                <a href="/assets/uploads/<?= e($data_kk['warga_dokumen_kk']) ?>" target="_blank" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-file-earmark-pdf me-1"></i> Scan Kartu Keluarga
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 2. ANGGOTA KELUARGA SECTION -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-sm-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-dark">
                <i class="bi bi-people-fill me-2 text-primary"></i>Daftar Anggota Keluarga
            </h6>
            <a href="anggota_tambah.php" class="btn btn-sm btn-success">
                <i class="bi bi-person-plus-fill me-1"></i> Tambah Anggota Keluarga
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 50px;">No.</th>
                            <th>Foto & Nama Lengkap</th>
                            <th>Hubungan Keluarga</th>
                            <th>NIK</th>
                            <th>L/P</th>
                            <th>Tempat, Tgl Lahir</th>
                            <th>No. HP & Email</th>
                            <th class="text-center" style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_anggota && mysqli_num_rows($result_anggota) > 0): ?>
                            <?php $no = 1; while ($row = mysqli_fetch_assoc($result_anggota)): ?>
                                <tr>
                                    <td class="text-center text-muted small"><?= $no++ ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (!empty($row['warga_foto'])): ?>
                                                <img src="/assets/uploads/<?= e($row['warga_foto']) ?>" class="rounded-circle border" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center border" style="width: 40px; height: 40px;">
                                                    <i class="bi bi-person text-secondary"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-bold text-dark"><?= e($row['warga_nama']) ?></div>
                                                <small class="text-muted"><?= e($row['warga_pekerjaan'] ?: $row['pkj_nama']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                            <?= e($row['hubungan_keluarga_nama']) ?>
                                        </span>
                                    </td>
                                    <td class="small fw-semibold"><?= e($row['warga_nik']) ?: '-' ?></td>
                                    <td><?= e($row['jk_nama'] ?: '-') ?></td>
                                    <td class="small">
                                        <?= e($row['warga_tempat_lahir']) ?><br>
                                        <span class="text-muted"><?= !empty($row['warga_tgl_lahir']) ? date('d-m-Y', strtotime($row['warga_tgl_lahir'])) : '-' ?></span>
                                    </td>
                                    <td class="small">
                                        <?php if (!empty($row['warga_no_hp'])): ?>
                                            <div><i class="bi bi-telephone text-primary me-1"></i><?= e($row['warga_no_hp']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['warga_email'])): ?>
                                            <div class="text-muted"><i class="bi bi-envelope me-1"></i><?= e($row['warga_email']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="anggota_edit.php?id=<?= encrypt_id($row['warga_id']) ?>" class="btn btn-sm btn-outline-primary" title="Edit Data Anggota">
                                            <i class="bi bi-pencil me-1"></i> Edit
                                        </a>
                                        <!-- Tombol Hapus ditiadakan sesuai ketentuan role user -->
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-people fs-1 d-block mb-2 opacity-50"></i>
                                    Belum ada data anggota keluarga yang tercatat. Klik tombol <strong>Tambah Anggota Keluarga</strong> di atas untuk mendaftarkan istri, anak, atau anggota keluarga lainnya.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../views/footer.php'; ?>
