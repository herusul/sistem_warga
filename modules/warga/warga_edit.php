<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$username = $_SESSION['user']['username'];

if (!isset($_GET['id'])) {
    header("Location: warga.php");
    exit;
}

$id = decrypt_id($_GET['id']);
if ($id === false || !is_numeric($id)) {
    header("Location: warga.php");
    exit;
}
$id = (int)$id;

// --- Fetch dropdown data with prepared statements helper ---
function fetchDropdown($conn, $category) {
    $sql = "SELECT ref_id, ref_nama FROM referensi WHERE ref_kategori=? AND is_aktif=1 ORDER BY ref_id";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $category);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

$agama_query = fetchDropdown($conn, 'agama');
$jk_query = fetchDropdown($conn, 'jenis_kelamin');
$gd_query = fetchDropdown($conn, 'golongan_darah');
$pdk_query = fetchDropdown($conn, 'pendidikan');
$pkj_query = fetchDropdown($conn, 'pekerjaan');
$kwn_query = fetchDropdown($conn, 'status_kawin');
$klg_query = fetchDropdown($conn, 'hubungan_keluarga');
$kk_query = mysqli_query($conn, "SELECT a.warga_id, CONCAT(a.warga_nama, ' (WP ', c.rumah_nomor_tampil, ')') AS nama FROM warga a JOIN warga_rumah b ON b.warga_id=a.warga_id JOIN rumah c ON c.rumah_id=b.rumah_id WHERE a.ref_id_hubungan_keluarga=34 AND b.is_aktif=1 ORDER BY c.rumah_nomor, a.warga_nama");

// Ambil data warga yang akan diedit
$sql_data = "SELECT * FROM warga WHERE warga_id = ?";
$stmt_data = mysqli_prepare($conn, $sql_data);
mysqli_stmt_bind_param($stmt_data, "i", $id);
mysqli_stmt_execute($stmt_data);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_data));

if (!$row) {
    header("Location: warga.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    verify_csrf_token();
    
    $nama = $_POST['warga_nama'];
    $nama_gelar = $_POST['warga_nama_gelar'];
    $nik = $_POST['warga_nik'];
    $kk = $_POST['warga_nomor_kk'];
    $wn = $_POST['warga_negara'];
    $hp = $_POST['warga_no_hp'];
    $email = $_POST['warga_email'];
    $tempat_lahir = $_POST['warga_tempat_lahir'];
    $tgl_lahir = !empty($_POST['warga_tgl_lahir']) ? $_POST['warga_tgl_lahir'] : null;
    $agama = !empty($_POST['ref_id_agama']) ? $_POST['ref_id_agama'] : null;
    $jk = $_POST['ref_id_jk'];
    $gd = !empty($_POST['ref_id_gd']) ? $_POST['ref_id_gd'] : null;
    $pdk = !empty($_POST['ref_id_pdk']) ? $_POST['ref_id_pdk'] : null;
    $pkj = !empty($_POST['ref_id_pkj']) ? $_POST['ref_id_pkj'] : null;
    $pkj_lain = !empty($_POST['warga_pekerjaan']) ? $_POST['warga_pekerjaan'] : null;
    $kwn = !empty($_POST['ref_id_kwn']) ? $_POST['ref_id_kwn'] : null;
    $klg = $_POST['ref_id_klg'];
    $kk_id = !empty($_POST['kk_id']) ? $_POST['kk_id'] : null;
    $klg_lain = $_POST['warga_hubungan_keluarga'];
    $is_ktp_wp = $_POST['is_ktp_wp'];

    // Hardened File Upload Logic
    function updateWargaFile($file_key, $existing_path, $allowed_exts, $allowed_mimes, $max_size = 5000000) {
        if (empty($_FILES[$file_key]['name'])) return $existing_path;
        
        $file = $_FILES[$file_key];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        
        if (!in_array($ext, $allowed_exts) || !in_array($mime, $allowed_mimes)) {
            echo "<script>alert('Format file $file_key tidak valid!'); window.history.back();</script>";
            exit;
        }
        
        if ($file['size'] > $max_size) {
            echo "<script>alert('Ukuran file $file_key terlalu besar (Max 5MB)!'); window.history.back();</script>";
            exit;
        }
        
        $new_name = date('YmdHis') . "_" . bin2hex(random_bytes(8)) . "." . $ext;
        $dest = "../../assets/uploads/" . $new_name;
        
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            // Optional: delete old file if needed, but keeping for history/safety for now
            return $new_name;
        }
        return $existing_path;
    }

    $foto_path = updateWargaFile('warga_foto', $row['warga_foto'], ['jpg', 'jpeg', 'png'], ['image/jpeg', 'image/png']);
    $dokumen_ktp_path = updateWargaFile('warga_dokumen_ktp', $row['warga_dokumen_ktp'], ['pdf'], ['application/pdf']);
    $dokumen_kk_path = updateWargaFile('warga_dokumen_kk', $row['warga_dokumen_kk'], ['pdf'], ['application/pdf']);

    // --- Validasi Duplikasi NIK ---
    $check_nik = mysqli_prepare($conn, "SELECT warga_id FROM warga WHERE warga_nik = ? AND warga_id != ? AND is_delete IS NULL");
    mysqli_stmt_bind_param($check_nik, "si", $nik, $id);
    mysqli_stmt_execute($check_nik);
    mysqli_stmt_store_result($check_nik);
    
    if (mysqli_stmt_num_rows($check_nik) > 0) {
        $error = "Data dengan NIK $nik sudah digunakan oleh warga lain.";
    } else {
        $query = "UPDATE warga SET
            warga_nama=?, warga_nama_gelar=?, warga_nik=?, warga_nomor_kk=?, warga_negara=?, warga_no_hp=?,
            warga_email=?, warga_tempat_lahir=?, warga_tgl_lahir=?,
            ref_id_agama=?, ref_id_jenis_kelamin=?, ref_id_golongan_darah=?, ref_id_pendidikan=?,
            ref_id_pekerjaan=?, warga_pekerjaan=?, ref_id_status_kawin=?,
            ref_id_hubungan_keluarga=?, warga_parent=?, warga_hubungan_keluarga=?,
            warga_foto=?, warga_dokumen_ktp=?, is_ktp_wp=?, warga_dokumen_kk=?,
            updated_by=?, updated_time=NOW() WHERE warga_id=?";
        
        $stmt_update = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt_update, "sssssssssiiiiisiiisssissi", 
            $nama, $nama_gelar, $nik, $kk, $wn, $hp, $email, $tempat_lahir, $tgl_lahir,
            $agama, $jk, $gd, $pdk, $pkj, $pkj_lain, $kwn, $klg,
            $kk_id, $klg_lain, $foto_path, $dokumen_ktp_path, $is_ktp_wp, $dokumen_kk_path, $username, $id);
        
        if (mysqli_stmt_execute($stmt_update)) {
            header("Location: warga.php?pesan=sukses");
            exit;
        } else {
            $error = "Gagal memperbarui data: " . mysqli_error($conn);
        }
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0 text-gray-800 fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Data Warga</h1>
        <a href="warga.php" class="btn btn-sm btn-outline-secondary px-3"><i class="bi bi-arrow-left me-1"></i> Batal</a>
    </div>

    <?php if (isset($error)) : ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form id="formEditWarga" method="post" enctype="multipart/form-data">
        <?= csrf_input() ?>
        <div class="row">
            <!-- Left Column: Basic Info -->
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="bi bi-person-lines-fill me-2"></i>Identitas Pribadi</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label small fw-bold">Nama Lengkap (Sesuai KTP) <span class="text-danger">*</span></label>
                                <input type="text" name="warga_nama" class="form-control" value="<?= e($row['warga_nama']) ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-bold">Gelar / Nama Lengkap (Opsional)</label>
                                <input type="text" name="warga_nama_gelar" class="form-control" value="<?= e($row['warga_nama_gelar']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">NIK (16 Digit)</label>
                                <input type="text" name="warga_nik" class="form-control" value="<?= e($row['warga_nik']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Nomor KK</label>
                                <input type="text" name="warga_nomor_kk" class="form-control" value="<?= e($row['warga_nomor_kk']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Tempat Lahir</label>
                                <input type="text" name="warga_tempat_lahir" class="form-control" value="<?= e($row['warga_tempat_lahir']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Tanggal Lahir</label>
                                <input type="date" name="warga_tgl_lahir" class="form-control" value="<?= e($row['warga_tgl_lahir']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Jenis Kelamin <span class="text-danger">*</span></label>
                                <select name="ref_id_jk" class="form-select" required>
                                    <option value="">- Pilih -</option>
                                    <?php while ($j = mysqli_fetch_assoc($jk_query)) : ?>
                                        <option value="<?= e($j['ref_id']) ?>" <?= $j['ref_id'] == $row['ref_id_jenis_kelamin'] ? 'selected' : '' ?>><?= e($j['ref_nama']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Agama</label>
                                <select name="ref_id_agama" class="form-select">
                                    <option value="">- Pilih -</option>
                                    <?php while ($a = mysqli_fetch_assoc($agama_query)) : ?>
                                        <option value="<?= e($a['ref_id']) ?>" <?= $a['ref_id'] == $row['ref_id_agama'] ? 'selected' : '' ?>><?= e($a['ref_nama']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Golongan Darah</label>
                                <select name="ref_id_gd" class="form-select">
                                    <option value="">- Pilih -</option>
                                    <?php while ($g = mysqli_fetch_assoc($gd_query)) : ?>
                                        <option value="<?= e($g['ref_id']) ?>" <?= $g['ref_id'] == $row['ref_id_golongan_darah'] ? 'selected' : '' ?>><?= e($g['ref_nama']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Kewarganegaraan</label>
                                <input type="text" name="warga_negara" class="form-control" value="<?= e($row['warga_negara']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Nomor HP/WA</label>
                                <input type="text" name="warga_no_hp" class="form-control" value="<?= e($row['warga_no_hp']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Email</label>
                                <input type="email" name="warga_email" class="form-control" value="<?= e($row['warga_email']) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="bi bi-briefcase me-2"></i>Kependudukan & Pekerjaan</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Pendidikan Terakhir</label>
                                <select name="ref_id_pdk" class="form-select">
                                    <option value="">- Pilih -</option>
                                    <?php while ($p = mysqli_fetch_assoc($pdk_query)) : ?>
                                        <option value="<?= e($p['ref_id']) ?>" <?= $p['ref_id'] == $row['ref_id_pendidikan'] ? 'selected' : '' ?>><?= e($p['ref_nama']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Pekerjaan Utama</label>
                                <select name="ref_id_pkj" class="form-select mb-2">
                                    <option value="">- Pilih -</option>
                                    <?php while ($pk = mysqli_fetch_assoc($pkj_query)) : ?>
                                        <option value="<?= e($pk['ref_id']) ?>" <?= $pk['ref_id'] == $row['ref_id_pekerjaan'] ? 'selected' : '' ?>><?= e($pk['ref_nama']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <input type="text" name="warga_pekerjaan" class="form-control" placeholder="Sebutkan jika pilih 'Lainnya'" value="<?= e($row['warga_pekerjaan']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Status Perkawinan</label>
                                <select name="ref_id_kwn" class="form-select">
                                    <option value="">- Pilih -</option>
                                    <?php while ($k = mysqli_fetch_assoc($kwn_query)) : ?>
                                        <option value="<?= e($k['ref_id']) ?>" <?= $k['ref_id'] == $row['ref_id_status_kawin'] ? 'selected' : '' ?>><?= e($k['ref_nama']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Family & Files -->
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="bi bi-people me-2"></i>Status Keluarga</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Status Hubungan (SHDK) <span class="text-danger">*</span></label>
                            <select name="ref_id_klg" id="shdk" class="form-select" required>
                                <option value="">- Pilih -</option>
                                <?php while ($h = mysqli_fetch_assoc($klg_query)) : ?>
                                    <option value="<?= e($h['ref_id']) ?>" <?= $h['ref_id'] == $row['ref_id_hubungan_keluarga'] ? 'selected' : '' ?>><?= e($h['ref_nama']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <input type="text" name="warga_hubungan_keluarga" class="form-control mt-2" placeholder="SHDK Lainnya..." value="<?= e($row['warga_hubungan_keluarga']) ?>">
                        </div>
                        <div id="kk-group" class="mb-0">
                            <label class="form-label small fw-bold">Nama Kepala Keluarga</label>
                            <select name="kk_id" id="kk_id" class="form-select">
                                <option value="">- Pilih Nama KK -</option>
                                <?php while ($kk = mysqli_fetch_assoc($kk_query)) : ?>
                                    <option value="<?= e($kk['warga_id']) ?>" <?= $kk['warga_id'] == $row['warga_parent'] ? 'selected' : '' ?>><?= e($kk['nama']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="bi bi-camera me-2"></i>Foto & Dokumen</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Upload Pas Foto <span class="text-muted fw-normal">(abaikan jika tidak diganti)</span></label>
                            <input type="file" name="warga_foto" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Dokumen KTP (PDF)</label>
                            <input type="file" name="warga_dokumen_ktp" class="form-control">
                            <div class="mt-2 small">
                                <span class="text-muted me-2">KTP WP?</span>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="is_ktp_wp" value="1" id="ya" <?= $row['is_ktp_wp'] == 1 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="ya">Ya</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="is_ktp_wp" value="0" id="tidak" <?= $row['is_ktp_wp'] == 0 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="tidak">Tidak</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="is_ktp_wp" value="2" id="belum" <?=$row['is_ktp_wp'] == 2 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="belum">Belum ber-KTP</label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label small fw-bold">Dokumen KK (PDF)</label>
                            <input type="file" name="warga_dokumen_kk" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 mb-5">
                    <button type="submit" name="simpan" id="btnSimpan" class="btn btn-primary py-3 fw-bold"><i class="bi bi-save me-2"></i> SIMPAN PERUBAHAN</button>
                    <a href="warga.php" class="btn btn-light py-2">Batal</a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const shdk = document.getElementById('shdk');
    const kkGroup = document.getElementById('kk-group');
    const kk = document.getElementById('kk_id');

    function toggleKKField() {
        if (shdk.value === '34') {
            kkGroup.style.display = 'none';
            kk.removeAttribute('required');
        } else {
            kkGroup.style.display = 'block';
            kk.setAttribute('required', 'required');
        }
    }
    toggleKKField();
    shdk.addEventListener('change', toggleKKField);

    // Prevent double submission
    const form = document.getElementById('formEditWarga');
    const btnSimpan = document.getElementById('btnSimpan');

    form.addEventListener('submit', function() {
        btnSimpan.disabled = true;
        btnSimpan.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Menyimpan...';

        // Tambahkan input hidden agar proses POST tetap berjalan lancar
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'simpan';
        hiddenInput.value = '1';
        form.appendChild(hiddenInput);
    });
});
</script>

<?php include '../../views/footer.php'; ?>
