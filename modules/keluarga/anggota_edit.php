<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth();

$user = $_SESSION['user'];
$current_warga_id = $user['warga_id'] ?? null;

if (empty($current_warga_id) && in_array($user['role'], ['superadmin', 'operator'])) {
    if (isset($_GET['warga_id'])) {
        $current_warga_id = (int)decrypt_id($_GET['warga_id']);
    }
}

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$target_id = decrypt_id($_GET['id']);
if ($target_id === false || !is_numeric($target_id)) {
    header("Location: index.php");
    exit;
}
$target_id = (int)$target_id;

// 1. Ambil data warga yang sedang login untuk mengetahui head_id keluarga
$sql_curr = "SELECT * FROM warga WHERE warga_id = ? AND is_delete IS NULL";
$stmt_curr = mysqli_prepare($conn, $sql_curr);
mysqli_stmt_bind_param($stmt_curr, "i", $current_warga_id);
mysqli_stmt_execute($stmt_curr);
$current_warga = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_curr));

if (!$current_warga) {
    header("Location: index.php");
    exit;
}

$is_kk = ((int)$current_warga['ref_id_hubungan_keluarga'] === 34);
$head_id = $is_kk ? $current_warga['warga_id'] : ($current_warga['warga_parent'] ?: $current_warga['warga_id']);

// 2. Ambil data target yang akan diedit
$sql_target = "SELECT * FROM warga WHERE warga_id = ? AND is_delete IS NULL";
$stmt_target = mysqli_prepare($conn, $sql_target);
mysqli_stmt_bind_param($stmt_target, "i", $target_id);
mysqli_stmt_execute($stmt_target);
$target_warga = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_target));

if (!$target_warga) {
    header("Location: index.php");
    exit;
}

// 3. Otorisasi Ketat: Pastikan target adalah diri sendiri, KK, atau anggota dalam keluarga yang sama
$is_own_self = ($target_id === (int)$current_warga_id);
$is_family_member = ($target_id === (int)$head_id) || ((int)$target_warga['warga_parent'] === (int)$head_id);

if (!$is_own_self && !$is_family_member && !in_array($user['role'], ['superadmin', 'operator'])) {
    echo "<script>alert('Anda tidak memiliki izin mengedit data warga di luar keluarga Anda!'); window.location.href='index.php';</script>";
    exit;
}

$target_is_kk = ((int)$target_warga['ref_id_hubungan_keluarga'] === 34);

// Dropdowns
function getRefOptions($conn, $kategori) {
    $sql = "SELECT ref_id, ref_nama FROM referensi WHERE ref_kategori = ? AND is_aktif = 1 ORDER BY ref_id ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $kategori);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    verify_csrf_token();

    $nama           = trim($_POST['warga_nama'] ?? '');
    $nama_gelar     = trim($_POST['warga_nama_gelar'] ?? '');
    $nik            = trim($_POST['warga_nik'] ?? '');
    $nomor_kk       = trim($_POST['warga_nomor_kk'] ?? $target_warga['warga_nomor_kk']);
    $warga_negara   = trim($_POST['warga_negara'] ?? 'WNI');
    $no_hp          = trim($_POST['warga_no_hp'] ?? '');
    $email          = trim($_POST['warga_email'] ?? '');
    $tempat_lahir   = trim($_POST['warga_tempat_lahir'] ?? '');
    $tgl_lahir      = !empty($_POST['warga_tgl_lahir']) ? $_POST['warga_tgl_lahir'] : null;
    $ref_id_agama   = !empty($_POST['ref_id_agama']) ? (int)$_POST['ref_id_agama'] : null;
    $ref_id_jk      = (int)($_POST['ref_id_jk'] ?? $target_warga['ref_id_jenis_kelamin']);
    $ref_id_gd      = !empty($_POST['ref_id_gd']) ? (int)$_POST['ref_id_gd'] : null;
    $ref_id_pdk     = !empty($_POST['ref_id_pdk']) ? (int)$_POST['ref_id_pdk'] : null;
    $ref_id_pkj     = !empty($_POST['ref_id_pkj']) ? (int)$_POST['ref_id_pkj'] : null;
    $warga_pkj      = trim($_POST['warga_pekerjaan'] ?? '');
    $ref_id_kwn     = !empty($_POST['ref_id_kwn']) ? (int)$_POST['ref_id_kwn'] : null;
    $ref_id_klg     = $target_is_kk ? 34 : (int)($_POST['ref_id_klg'] ?? $target_warga['ref_id_hubungan_keluarga']);
    $is_ktp_wp      = (int)($_POST['is_ktp_wp'] ?? 1);
    $klg_lain       = trim($_POST['warga_hubungan_keluarga'] ?? '');

    if (empty($nama) || empty($ref_id_jk)) {
        $error = "Nama Lengkap dan Jenis Kelamin wajib diisi.";
    } else {
        // Validasi NIK jika diubah
        if (!empty($nik) && $nik !== $target_warga['warga_nik']) {
            $stmt_nik = mysqli_prepare($conn, "SELECT warga_id FROM warga WHERE warga_nik = ? AND warga_id != ? AND is_delete IS NULL");
            mysqli_stmt_bind_param($stmt_nik, "si", $nik, $target_id);
            mysqli_stmt_execute($stmt_nik);
            if (mysqli_stmt_get_result($stmt_nik)->num_rows > 0) {
                $error = "NIK $nik sudah terdaftar pada warga lain.";
            }
        }

        if (empty($error)) {
            // Helper Upload
            function handleUploadOrKeep($field_name, $existing_val, $allowed_exts, $allowed_mimes, $max_size = 5000000) {
                if (empty($_FILES[$field_name]['name'])) return $existing_val;
                $file = $_FILES[$field_name];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                if (!in_array($ext, $allowed_exts) || !in_array($mime, $allowed_mimes) || $file['size'] > $max_size) {
                    return $existing_val;
                }
                $new_name = date('YmdHis') . "_" . bin2hex(random_bytes(8)) . "." . $ext;
                if (move_uploaded_file($file['tmp_name'], "../../assets/uploads/" . $new_name)) {
                    return $new_name;
                }
                return $existing_val;
            }

            $foto_path = handleUploadOrKeep('warga_foto', $target_warga['warga_foto'], ['jpg', 'jpeg', 'png'], ['image/jpeg', 'image/png']);
            $ktp_path  = handleUploadOrKeep('warga_dokumen_ktp', $target_warga['warga_dokumen_ktp'], ['pdf', 'jpg', 'jpeg', 'png'], ['application/pdf', 'image/jpeg', 'image/png']);
            $kk_path   = handleUploadOrKeep('warga_dokumen_kk', $target_warga['warga_dokumen_kk'], ['pdf', 'jpg', 'jpeg', 'png'], ['application/pdf', 'image/jpeg', 'image/png']);

            $editor = $user['username'] ?? $current_warga['warga_nama'];

            $sql_up = "UPDATE warga SET 
                warga_nama = ?, warga_nama_gelar = ?, warga_nik = ?, warga_nomor_kk = ?, warga_negara = ?,
                warga_no_hp = ?, warga_email = ?, warga_tempat_lahir = ?, warga_tgl_lahir = ?,
                ref_id_agama = ?, ref_id_jenis_kelamin = ?, ref_id_golongan_darah = ?, ref_id_pendidikan = ?,
                ref_id_pekerjaan = ?, warga_pekerjaan = ?, ref_id_status_kawin = ?, ref_id_hubungan_keluarga = ?,
                warga_hubungan_keluarga = ?, warga_foto = ?, warga_dokumen_ktp = ?, warga_dokumen_kk = ?, is_ktp_wp = ?,
                updated_by = ?, updated_time = NOW()
                WHERE warga_id = ?";

            $stmt_up = mysqli_prepare($conn, $sql_up);
            mysqli_stmt_bind_param(
                $stmt_up,
                "ssssssssssisssssissssisi",
                $nama, $nama_gelar, $nik, $nomor_kk, $warga_negara,
                $no_hp, $email, $tempat_lahir, $tgl_lahir,
                $ref_id_agama, $ref_id_jk, $ref_id_gd, $ref_id_pdk,
                $ref_id_pkj, $warga_pkj, $ref_id_kwn, $ref_id_klg,
                $klg_lain, $foto_path, $ktp_path, $kk_path, $is_ktp_wp,
                $editor, $target_id
            );

            if (mysqli_stmt_execute($stmt_up)) {
                // Jika KK mengubah nomor KK atau berkas KK, perbarui juga untuk semua anggota keluarga di bawahnya
                if ($target_is_kk) {
                    $sql_sync = "UPDATE warga SET warga_nomor_kk = ?, warga_dokumen_kk = ? WHERE warga_parent = ?";
                    $stmt_sync = mysqli_prepare($conn, $sql_sync);
                    mysqli_stmt_bind_param($stmt_sync, "ssi", $nomor_kk, $kk_path, $target_id);
                    mysqli_stmt_execute($stmt_sync);
                }

                header("Location: index.php?status=edit_sukses");
                exit;
            } else {
                $error = "Gagal menyimpan perubahan: " . mysqli_error($conn);
            }
        }
    }
}

$jk_options   = getRefOptions($conn, 'jenis_kelamin');
$agama_options= getRefOptions($conn, 'agama');
$gd_options   = getRefOptions($conn, 'golongan_darah');
$pdk_options  = getRefOptions($conn, 'pendidikan');
$pkj_options  = getRefOptions($conn, 'pekerjaan');
$kwn_options  = getRefOptions($conn, 'status_kawin');
$klg_options  = getRefOptions($conn, 'hubungan_keluarga');
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 mb-0 text-gray-800 fw-bold">✏️ Edit Data: <?= e($target_warga['warga_nama']) ?></h1>
            <p class="text-muted small mb-0">
                Perbarui data kependudukan <?= $target_is_kk ? 'Kepala Keluarga' : 'Anggota Keluarga' ?>
            </p>
        </div>
        <a href="index.php" class="btn btn-sm btn-outline-secondary px-3">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Profil Keluarga
        </a>
    </div>

    <?php if (!empty($error)) : ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 fw-bold text-primary"><i class="bi bi-pencil-square me-2"></i>Formulir Pembaruan Data</h6>
        </div>
        <div class="card-body p-4">
            <form method="post" enctype="multipart/form-data">
                <?= csrf_input() ?>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="warga_nama" class="form-control" required value="<?= e($target_warga['warga_nama']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Nama dengan Gelar</label>
                        <input type="text" name="warga_nama_gelar" class="form-control" value="<?= e($target_warga['warga_nama_gelar']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Nomor Induk Kependudukan (NIK)</label>
                        <input type="text" name="warga_nik" class="form-control" maxlength="16" value="<?= e($target_warga['warga_nik']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Nomor Kartu Keluarga (KK)</label>
                        <input type="text" name="warga_nomor_kk" class="form-control <?= !$target_is_kk ? 'bg-light' : '' ?>" maxlength="16" value="<?= e($target_warga['warga_nomor_kk']) ?>" <?= !$target_is_kk ? 'readonly' : '' ?>>
                        <?php if (!$target_is_kk): ?>
                            <small class="text-muted">Nomor KK anggota disinkronkan dari Kepala Keluarga.</small>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Hubungan Keluarga</label>
                        <?php if ($target_is_kk): ?>
                            <input type="text" class="form-control bg-light" value="Kepala Keluarga" readonly>
                        <?php else: ?>
                            <select name="ref_id_klg" class="form-select" required>
                                <?php while ($kl = mysqli_fetch_assoc($klg_options)): ?>
                                    <?php if ($kl['ref_id'] == 34) continue; ?>
                                    <option value="<?= $kl['ref_id'] ?>" <?= $target_warga['ref_id_hubungan_keluarga'] == $kl['ref_id'] ? 'selected' : '' ?>>
                                        <?= e($kl['ref_nama']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Jenis Kelamin <span class="text-danger">*</span></label>
                        <select name="ref_id_jk" class="form-select" required>
                            <?php while ($jk = mysqli_fetch_assoc($jk_options)): ?>
                                <option value="<?= $jk['ref_id'] ?>" <?= $target_warga['ref_id_jenis_kelamin'] == $jk['ref_id'] ? 'selected' : '' ?>>
                                    <?= e($jk['ref_nama']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Golongan Darah</label>
                        <select name="ref_id_gd" class="form-select">
                            <option value="">- Tidak Tahu / Belum Cek -</option>
                            <?php while ($gd = mysqli_fetch_assoc($gd_options)): ?>
                                <option value="<?= $gd['ref_id'] ?>" <?= $target_warga['ref_id_golongan_darah'] == $gd['ref_id'] ? 'selected' : '' ?>>
                                    <?= e($gd['ref_nama']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Tempat Lahir</label>
                        <input type="text" name="warga_tempat_lahir" class="form-control" value="<?= e($target_warga['warga_tempat_lahir']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Tanggal Lahir</label>
                        <input type="date" name="warga_tgl_lahir" class="form-control" value="<?= e($target_warga['warga_tgl_lahir']) ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Agama</label>
                        <select name="ref_id_agama" class="form-select">
                            <option value="">- Pilih Agama -</option>
                            <?php while ($ag = mysqli_fetch_assoc($agama_options)): ?>
                                <option value="<?= $ag['ref_id'] ?>" <?= $target_warga['ref_id_agama'] == $ag['ref_id'] ? 'selected' : '' ?>>
                                    <?= e($ag['ref_nama']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Status Perkawinan</label>
                        <select name="ref_id_kwn" class="form-select">
                            <option value="">- Pilih Status Kawin -</option>
                            <?php while ($kw = mysqli_fetch_assoc($kwn_options)): ?>
                                <option value="<?= $kw['ref_id'] ?>" <?= $target_warga['ref_id_status_kawin'] == $kw['ref_id'] ? 'selected' : '' ?>>
                                    <?= e($kw['ref_nama']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Pendidikan Terakhir</label>
                        <select name="ref_id_pdk" class="form-select">
                            <option value="">- Pilih Pendidikan -</option>
                            <?php while ($pd = mysqli_fetch_assoc($pdk_options)): ?>
                                <option value="<?= $pd['ref_id'] ?>" <?= $target_warga['ref_id_pendidikan'] == $pd['ref_id'] ? 'selected' : '' ?>>
                                    <?= e($pd['ref_nama']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Kategori Pekerjaan</label>
                        <select name="ref_id_pkj" class="form-select">
                            <option value="">- Pilih Kategori Pekerjaan -</option>
                            <?php while ($pk = mysqli_fetch_assoc($pkj_options)): ?>
                                <option value="<?= $pk['ref_id'] ?>" <?= $target_warga['ref_id_pekerjaan'] == $pk['ref_id'] ? 'selected' : '' ?>>
                                    <?= e($pk['ref_nama']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Pekerjaan Spesifik</label>
                        <input type="text" name="warga_pekerjaan" class="form-control" value="<?= e($target_warga['warga_pekerjaan']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Nomor HP / WhatsApp</label>
                        <input type="text" name="warga_no_hp" class="form-control" value="<?= e($target_warga['warga_no_hp']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Email Google (Untuk Login Mandiri)</label>
                        <input type="email" name="warga_email" class="form-control" value="<?= e($target_warga['warga_email']) ?>">
                        <small class="text-muted">Gunakan alamat Gmail jika ingin login menggunakan akun Google.</small>
                    </div>

                    <!-- Upload Foto & Dokumen -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Foto Profil</label>
                        <?php if (!empty($target_warga['warga_foto'])): ?>
                            <div class="mb-2">
                                <img src="/assets/uploads/<?= e($target_warga['warga_foto']) ?>" class="rounded border" style="width: 60px; height: 60px; object-fit: cover;">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="warga_foto" class="form-control form-control-sm" accept="image/jpeg,image/png">
                        <small class="text-muted">Biarkan kosong jika tidak ingin mengganti</small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Dokumen Scan KTP / Akta</label>
                        <?php if (!empty($target_warga['warga_dokumen_ktp'])): ?>
                            <div class="mb-2">
                                <a href="/assets/uploads/<?= e($target_warga['warga_dokumen_ktp']) ?>" target="_blank" class="badge bg-light text-primary border text-decoration-none">
                                    <i class="bi bi-file-earmark-pdf me-1"></i> Lihat Dokumen KTP
                                </a>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="warga_dokumen_ktp" class="form-control form-control-sm" accept="application/pdf,image/jpeg,image/png">
                        <small class="text-muted">Biarkan kosong jika tidak diubah</small>
                    </div>

                    <?php if ($target_is_kk): ?>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase">Dokumen Scan Kartu Keluarga (KK)</label>
                            <?php if (!empty($target_warga['warga_dokumen_kk'])): ?>
                                <div class="mb-2">
                                    <a href="/assets/uploads/<?= e($target_warga['warga_dokumen_kk']) ?>" target="_blank" class="badge bg-light text-primary border text-decoration-none">
                                        <i class="bi bi-file-earmark-pdf me-1"></i> Lihat Dokumen KK
                                    </a>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="warga_dokumen_kk" class="form-control form-control-sm" accept="application/pdf,image/jpeg,image/png">
                            <small class="text-muted">Biarkan kosong jika tidak diubah</small>
                        </div>
                    <?php endif; ?>
                </div>

                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <a href="index.php" class="btn btn-light border px-4">Batal</a>
                    <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../views/footer.php'; ?>
