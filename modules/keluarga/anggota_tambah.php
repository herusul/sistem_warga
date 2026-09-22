<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth();

$user = $_SESSION['user'];
$warga_id = $user['warga_id'] ?? null;

if (empty($warga_id) && in_array($user['role'], ['superadmin', 'operator'])) {
    if (isset($_GET['warga_id'])) {
        $warga_id = (int)decrypt_id($_GET['warga_id']);
    }
}

if (empty($warga_id)) {
    header("Location: index.php");
    exit;
}

// 1. Ambil data warga aktif untuk mencari Kepala Keluarga
$sql_curr = "SELECT * FROM warga WHERE warga_id = ? AND is_delete IS NULL";
$stmt_curr = mysqli_prepare($conn, $sql_curr);
mysqli_stmt_bind_param($stmt_curr, "i", $warga_id);
mysqli_stmt_execute($stmt_curr);
$current_warga = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_curr));

if (!$current_warga) {
    header("Location: index.php");
    exit;
}

$is_kk = ((int)$current_warga['ref_id_hubungan_keluarga'] === 34);
$head_id = $is_kk ? $current_warga['warga_id'] : ($current_warga['warga_parent'] ?: $current_warga['warga_id']);

// Ambil data KK untuk default nomor KK dan nama KK
$sql_kk = "SELECT warga_nama, warga_nomor_kk FROM warga WHERE warga_id = ?";
$stmt_kk = mysqli_prepare($conn, $sql_kk);
mysqli_stmt_bind_param($stmt_kk, "i", $head_id);
mysqli_stmt_execute($stmt_kk);
$data_kk = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_kk));
$default_no_kk = $data_kk['warga_nomor_kk'] ?? '';
$nama_kk = $data_kk['warga_nama'] ?? '';

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
    $nomor_kk       = trim($_POST['warga_nomor_kk'] ?? $default_no_kk);
    $warga_negara   = trim($_POST['warga_negara'] ?? 'WNI');
    $no_hp          = trim($_POST['warga_no_hp'] ?? '');
    $email          = trim($_POST['warga_email'] ?? '');
    $tempat_lahir   = trim($_POST['warga_tempat_lahir'] ?? '');
    $tgl_lahir      = !empty($_POST['warga_tgl_lahir']) ? $_POST['warga_tgl_lahir'] : null;
    $ref_id_agama   = !empty($_POST['ref_id_agama']) ? (int)$_POST['ref_id_agama'] : null;
    $ref_id_jk      = (int)($_POST['ref_id_jk'] ?? 0);
    $ref_id_gd      = !empty($_POST['ref_id_gd']) ? (int)$_POST['ref_id_gd'] : null;
    $ref_id_pdk     = !empty($_POST['ref_id_pdk']) ? (int)$_POST['ref_id_pdk'] : null;
    $ref_id_pkj     = !empty($_POST['ref_id_pkj']) ? (int)$_POST['ref_id_pkj'] : null;
    $warga_pkj      = trim($_POST['warga_pekerjaan'] ?? '');
    $ref_id_kwn     = !empty($_POST['ref_id_kwn']) ? (int)$_POST['ref_id_kwn'] : null;
    $ref_id_klg     = (int)($_POST['ref_id_klg'] ?? 0);
    $is_ktp_wp      = (int)($_POST['is_ktp_wp'] ?? 1);
    $klg_lain       = trim($_POST['warga_hubungan_keluarga'] ?? '');

    // Validasi dasar
    if (empty($nama) || empty($ref_id_jk) || empty($ref_id_klg)) {
        $error = "Nama Lengkap, Jenis Kelamin, dan Hubungan Keluarga wajib diisi.";
    } elseif ($ref_id_klg === 34) {
        $error = "Tidak dapat menambahkan Kepala Keluarga ganda. Pilih hubungan keluarga selain Kepala Keluarga.";
    } else {
        // Cek duplikasi NIK jika NIK diisi
        if (!empty($nik)) {
            $stmt_nik = mysqli_prepare($conn, "SELECT warga_id FROM warga WHERE warga_nik = ? AND is_delete IS NULL");
            mysqli_stmt_bind_param($stmt_nik, "s", $nik);
            mysqli_stmt_execute($stmt_nik);
            if (mysqli_stmt_get_result($stmt_nik)->num_rows > 0) {
                $error = "Warga dengan NIK $nik sudah terdaftar dalam sistem.";
            }
        }

        if (empty($error)) {
            // Helper Upload
            function uploadFileLocal($field_name, $allowed_exts, $allowed_mimes, $max_size = 5000000) {
                if (empty($_FILES[$field_name]['name'])) return null;
                $file = $_FILES[$field_name];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                if (!in_array($ext, $allowed_exts) || !in_array($mime, $allowed_mimes) || $file['size'] > $max_size) {
                    return null;
                }
                $new_name = date('YmdHis') . "_" . bin2hex(random_bytes(8)) . "." . $ext;
                if (move_uploaded_file($file['tmp_name'], "../../assets/uploads/" . $new_name)) {
                    return $new_name;
                }
                return null;
            }

            $foto_path = uploadFileLocal('warga_foto', ['jpg', 'jpeg', 'png'], ['image/jpeg', 'image/png']);
            $ktp_path  = uploadFileLocal('warga_dokumen_ktp', ['pdf', 'jpg', 'jpeg', 'png'], ['application/pdf', 'image/jpeg', 'image/png']);

            $creator = $user['username'] ?? $current_warga['warga_nama'];

            $sql_ins = "INSERT INTO warga (
                warga_nama, warga_nama_gelar, warga_nik, warga_nomor_kk, warga_negara,
                warga_no_hp, warga_email, warga_tempat_lahir, warga_tgl_lahir,
                ref_id_agama, ref_id_jenis_kelamin, ref_id_golongan_darah, ref_id_pendidikan,
                ref_id_pekerjaan, warga_pekerjaan, ref_id_status_kawin, ref_id_hubungan_keluarga,
                warga_parent, warga_hubungan_keluarga, warga_foto, warga_dokumen_ktp, is_ktp_wp,
                created_by, created_time
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, NOW()
            )";

            $stmt_ins = mysqli_prepare($conn, $sql_ins);
            mysqli_stmt_bind_param(
                $stmt_ins,
                "ssssssssssisssssissssiss",
                $nama, $nama_gelar, $nik, $nomor_kk, $warga_negara,
                $no_hp, $email, $tempat_lahir, $tgl_lahir,
                $ref_id_agama, $ref_id_jk, $ref_id_gd, $ref_id_pdk,
                $ref_id_pkj, $warga_pkj, $ref_id_kwn, $ref_id_klg,
                $head_id, $klg_lain, $foto_path, $ktp_path, $is_ktp_wp,
                $creator
            );

            if (mysqli_stmt_execute($stmt_ins)) {
                $new_id = mysqli_insert_id($conn);

                // Insert log mutasi aktif (Menetap di WP = 161)
                $sql_mut = "INSERT INTO warga_mutasi (warga_id, ref_id_status_aktif, warga_mutasi_tanggal, is_aktif, created_by, created_time) VALUES (?, 161, CURDATE(), 1, ?, NOW())";
                $stmt_mut = mysqli_prepare($conn, $sql_mut);
                mysqli_stmt_bind_param($stmt_mut, "is", $new_id, $creator);
                mysqli_stmt_execute($stmt_mut);

                header("Location: index.php?status=tambah_sukses");
                exit;
            } else {
                $error = "Gagal menambahkan anggota keluarga: " . mysqli_error($conn);
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
            <h1 class="h4 mb-0 text-gray-800 fw-bold">➕ Tambah Anggota Keluarga</h1>
            <p class="text-muted small mb-0">
                Pendaftaran anggota baru ke dalam Kartu Keluarga: <strong><?= e($nama_kk) ?></strong> (No. KK: <?= e($default_no_kk) ?>)
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
            <h6 class="m-0 fw-bold text-primary"><i class="bi bi-person-lines-fill me-2"></i>Formulir Data Anggota Keluarga Baru</h6>
        </div>
        <div class="card-body p-4">
            <form method="post" enctype="multipart/form-data">
                <?= csrf_input() ?>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="warga_nama" class="form-control" placeholder="Nama lengkap sesuai KTP/Akta" required value="<?= e($_POST['warga_nama'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Nama dengan Gelar</label>
                        <input type="text" name="warga_nama_gelar" class="form-control" placeholder="Contoh: dr. Ahmad, Sp.A" value="<?= e($_POST['warga_nama_gelar'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Nomor Induk Kependudukan (NIK)</label>
                        <input type="text" name="warga_nik" class="form-control" maxlength="16" placeholder="16 digit NIK" value="<?= e($_POST['warga_nik'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Nomor Kartu Keluarga (KK)</label>
                        <input type="text" name="warga_nomor_kk" class="form-control bg-light" maxlength="16" value="<?= e($default_no_kk) ?>" readonly>
                        <small class="text-muted">Nomor KK otomatis disamakan dengan Kepala Keluarga.</small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Hubungan Keluarga <span class="text-danger">*</span></label>
                        <select name="ref_id_klg" class="form-select" required>
                            <option value="" disabled selected>- Pilih Hubungan Keluarga -</option>
                            <?php while ($kl = mysqli_fetch_assoc($klg_options)): ?>
                                <?php if ($kl['ref_id'] == 34) continue; // Jangan tampilkan Kepala Keluarga ?>
                                <option value="<?= $kl['ref_id'] ?>" <?= (isset($_POST['ref_id_klg']) && $_POST['ref_id_klg'] == $kl['ref_id']) ? 'selected' : '' ?>>
                                    <?= e($kl['ref_nama']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Jenis Kelamin <span class="text-danger">*</span></label>
                        <select name="ref_id_jk" class="form-select" required>
                            <option value="" disabled selected>- Pilih Jenis Kelamin -</option>
                            <?php while ($jk = mysqli_fetch_assoc($jk_options)): ?>
                                <option value="<?= $jk['ref_id'] ?>" <?= (isset($_POST['ref_id_jk']) && $_POST['ref_id_jk'] == $jk['ref_id']) ? 'selected' : '' ?>>
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
                                <option value="<?= $gd['ref_id'] ?>" <?= (isset($_POST['ref_id_gd']) && $_POST['ref_id_gd'] == $gd['ref_id']) ? 'selected' : '' ?>>
                                    <?= e($gd['ref_nama']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Tempat Lahir</label>
                        <input type="text" name="warga_tempat_lahir" class="form-control" placeholder="Kota / Kabupaten kelahiran" value="<?= e($_POST['warga_tempat_lahir'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Tanggal Lahir</label>
                        <input type="date" name="warga_tgl_lahir" class="form-control" value="<?= e($_POST['warga_tgl_lahir'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Agama</label>
                        <select name="ref_id_agama" class="form-select">
                            <option value="">- Pilih Agama -</option>
                            <?php while ($ag = mysqli_fetch_assoc($agama_options)): ?>
                                <option value="<?= $ag['ref_id'] ?>" <?= (isset($_POST['ref_id_agama']) && $_POST['ref_id_agama'] == $ag['ref_id']) ? 'selected' : '' ?>>
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
                                <option value="<?= $kw['ref_id'] ?>" <?= (isset($_POST['ref_id_kwn']) && $_POST['ref_id_kwn'] == $kw['ref_id']) ? 'selected' : '' ?>>
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
                                <option value="<?= $pd['ref_id'] ?>" <?= (isset($_POST['ref_id_pdk']) && $_POST['ref_id_pdk'] == $pd['ref_id']) ? 'selected' : '' ?>>
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
                                <option value="<?= $pk['ref_id'] ?>" <?= (isset($_POST['ref_id_pkj']) && $_POST['ref_id_pkj'] == $pk['ref_id']) ? 'selected' : '' ?>>
                                    <?= e($pk['ref_nama']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Pekerjaan Spesifik</label>
                        <input type="text" name="warga_pekerjaan" class="form-control" placeholder="Contoh: Guru, Karyawan Swasta, Mahasiswa" value="<?= e($_POST['warga_pekerjaan'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Nomor HP / WhatsApp</label>
                        <input type="text" name="warga_no_hp" class="form-control" placeholder="Contoh: 08123456789" value="<?= e($_POST['warga_no_hp'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Email Google (Untuk Login Mandiri)</label>
                        <input type="email" name="warga_email" class="form-control" placeholder="nama@gmail.com" value="<?= e($_POST['warga_email'] ?? '') ?>">
                        <small class="text-muted">Jika diisi, anggota ini juga dapat login menggunakan akun Google-nya.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Foto Profil</label>
                        <input type="file" name="warga_foto" class="form-control" accept="image/jpeg,image/png">
                        <small class="text-muted">Format: JPG, PNG (Maks 5MB)</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Scan Dokumen KTP / Akta / KIA</label>
                        <input type="file" name="warga_dokumen_ktp" class="form-control" accept="application/pdf,image/jpeg,image/png">
                        <small class="text-muted">Format: PDF atau Foto (Maks 5MB)</small>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <a href="index.php" class="btn btn-light border px-4">Batal</a>
                    <button type="submit" class="btn btn-success px-4 py-2 fw-semibold">
                        <i class="bi bi-save me-1"></i> Simpan Data Anggota
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../views/footer.php'; ?>
