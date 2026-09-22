<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$username = $_SESSION['user']['username'];

// --- Handle pre-selected KK ---
$pre_selected_kk_id = '';
if (isset($_GET['kk_id'])) {
    $decrypted_kk = decrypt_id($_GET['kk_id']);
    if ($decrypted_kk !== false && is_numeric($decrypted_kk)) {
        $pre_selected_kk_id = (int)$decrypted_kk;
    }
}

// --- Fetch dropdown data ---
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
$status_aktif_query = fetchDropdown($conn, 'status_aktif');
$status_rumah_query = fetchDropdown($conn, 'status_rumah');

$rumah_query = mysqli_query($conn, "SELECT rumah_id, CONCAT('WP ', rumah_nomor_tampil) as rumah_nama FROM rumah WHERE is_aktif=1 ORDER BY rumah_nomor");

$namakk_query = mysqli_query($conn, "SELECT a.warga_id as kk_id, CONCAT(a.warga_nama, ' (WP ', c.rumah_nomor_tampil,')') AS kk_nama
FROM warga a
JOIN warga_rumah b ON b.warga_id=a.warga_id
JOIN rumah c ON c.rumah_id=b.rumah_id
WHERE a.ref_id_hubungan_keluarga=34 AND b.is_aktif=1
ORDER BY c.rumah_nomor, a.warga_nama");

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
    $pkj_lain = $_POST['warga_pekerjaan'];
    $kwn = !empty($_POST['ref_id_kwn']) ? $_POST['ref_id_kwn'] : null;
    $klg = $_POST['ref_id_klg'];
    $namakk = !empty($_POST['kk_id']) ? $_POST['kk_id'] : null;
    $klg_lain = $_POST['warga_hubungan_keluarga'];
    $status_aktif = $_POST['ref_id_status_aktif'];
	$tgl_mutasi = $_POST['warga_mutasi_tanggal'];
	$is_ktp_wp = $_POST['is_ktp_wp'];
    
    $rumah_id = !empty($_POST['rumah_id']) ? $_POST['rumah_id'] : null;
    $status_rumah = !empty($_POST['ref_id_status_rumah']) ? $_POST['ref_id_status_rumah'] : null;
    
    // File Upload Helper
    function uploadWargaFile($file_key, $allowed_exts, $allowed_mimes, $max_size = 5000000) {
        if (empty($_FILES[$file_key]['name'])) return null;
        $file = $_FILES[$file_key];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($ext, $allowed_exts) || !in_array($mime, $allowed_mimes)) return null;
        if ($file['size'] > $max_size) return null;
        $new_name = date('YmdHis') . "_" . bin2hex(random_bytes(8)) . "." . $ext;
        if (move_uploaded_file($file['tmp_name'], "../../assets/uploads/" . $new_name)) return $new_name;
        return null;
    }

    $foto_path = uploadWargaFile('warga_foto', ['jpg', 'jpeg', 'png'], ['image/jpeg', 'image/png']);
    $dokumen_ktp_path = uploadWargaFile('warga_dokumen_ktp', ['pdf'], ['application/pdf']);
    $dokumen_kk_path = uploadWargaFile('warga_dokumen_kk', ['pdf'], ['application/pdf']);

    // --- Validasi Duplikasi NIK ---
    $check_nik = mysqli_prepare($conn, "SELECT warga_id FROM warga WHERE warga_nik = ? AND is_delete IS NULL");
    mysqli_stmt_bind_param($check_nik, "s", $nik);
    mysqli_stmt_execute($check_nik);
    mysqli_stmt_store_result($check_nik);
    
    if (mysqli_stmt_num_rows($check_nik) > 0) {
        $error = "Data dengan NIK $nik sudah terdaftar dalam sistem.";
    } else {
        $sql_warga = "INSERT INTO warga (warga_nama, warga_nama_gelar, warga_nik, warga_nomor_kk, warga_negara, warga_no_hp, warga_email, warga_tempat_lahir, warga_tgl_lahir,
        ref_id_agama, ref_id_jenis_kelamin, ref_id_golongan_darah, ref_id_pendidikan, ref_id_pekerjaan, warga_pekerjaan, ref_id_status_kawin,
        ref_id_hubungan_keluarga, warga_parent, warga_hubungan_keluarga, warga_foto, warga_dokumen_ktp, is_ktp_wp, warga_dokumen_kk, created_by, created_time) VALUES 
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt_warga = mysqli_prepare($conn, $sql_warga);
        mysqli_stmt_bind_param($stmt_warga, "ssssssssssisssssissssiss", 
            $nama, $nama_gelar, $nik, $kk, $wn, $hp, $email, $tempat_lahir, $tgl_lahir,
            $agama, $jk, $gd, $pdk, $pkj, $pkj_lain, $kwn, $klg,
            $namakk, $klg_lain, $foto_path, $dokumen_ktp_path, $is_ktp_wp, $dokumen_kk_path, $username);
        
        if (mysqli_stmt_execute($stmt_warga)) {
            $warga_id = mysqli_insert_id($conn);
            $sql_mutasi = "INSERT INTO warga_mutasi (warga_id, ref_id_status_aktif, warga_mutasi_tanggal, is_aktif, created_by, created_time) 
            VALUES (?, ?, ?, '1', ?, NOW())";
            $stmt_mutasi = mysqli_prepare($conn, $sql_mutasi);
            mysqli_stmt_bind_param($stmt_mutasi, "iiss", $warga_id, $status_aktif, $tgl_mutasi, $username);
            mysqli_stmt_execute($stmt_mutasi);

            // Jika Kepala Keluarga, insert ke warga_rumah
            if ($klg == 34 && !empty($rumah_id)) {
                $sql_rumah = "INSERT INTO warga_rumah (warga_id, rumah_id, ref_id_status_rumah, warga_rumah_tanggal, is_aktif, created_by, created_time)
                VALUES (?, ?, ?, ?, '1', ?, NOW())";
                $stmt_rumah = mysqli_prepare($conn, $sql_rumah);
                mysqli_stmt_bind_param($stmt_rumah, "iiiss", $warga_id, $rumah_id, $status_rumah, $tgl_mutasi, $username);
                mysqli_stmt_execute($stmt_rumah);
            }

            header("Location: warga.php?pesan=sukses");
            exit;
        } else {
            $error = "Gagal menyimpan data: " . mysqli_error($conn);
        }
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0 text-gray-800 fw-bold"><i class="bi bi-person-plus me-2"></i>Tambah Data Warga Baru</h1>
        <a href="warga.php" class="btn btn-sm btn-outline-secondary px-3"><i class="bi bi-arrow-left me-1"></i> Batal</a>
    </div>

    <?php if (isset($error)) : ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form id="formTambahWarga" method="post" enctype="multipart/form-data">
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
                                <input type="text" name="warga_nama" class="form-control" placeholder="Contoh: Budi Santoso" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-bold">Gelar / Nama Lengkap (Opsional)</label>
                                <input type="text" name="warga_nama_gelar" class="form-control" placeholder="Contoh: Ir. Budi Santoso, M.Si">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">NIK (16 Digit) <span class="text-danger">*</span></label>
                                <input type="text" name="warga_nik" class="form-control" placeholder="3201..." required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Nomor KK <span class="text-danger">*</span></label>
                                <input type="text" name="warga_nomor_kk" class="form-control" placeholder="3201..." required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Tempat Lahir</label>
                                <input type="text" name="warga_tempat_lahir" class="form-control" placeholder="Kota Lahir">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Tanggal Lahir</label>
                                <input type="date" name="warga_tgl_lahir" class="form-control" >
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Jenis Kelamin <span class="text-danger">*</span></label>
                                <select name="ref_id_jk" class="form-select" required>
                                    <option value="">- Pilih -</option>
                                    <?php mysqli_data_seek($jk_query, 0); while ($row = mysqli_fetch_assoc($jk_query)) : ?>
                                        <option value="<?= e($row['ref_id']) ?>"><?= e($row['ref_nama']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Agama</label>
                                <select name="ref_id_agama" class="form-select">
                                    <option value="">- Pilih -</option>
                                    <?php mysqli_data_seek($agama_query, 0); while ($row = mysqli_fetch_assoc($agama_query)) : ?>
                                        <option value="<?= e($row['ref_id']) ?>"><?= e($row['ref_nama']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Golongan Darah</label>
                                <select name="ref_id_gd" class="form-select">
                                    <option value="">- Pilih -</option>
                                    <?php mysqli_data_seek($gd_query, 0); while ($row = mysqli_fetch_assoc($gd_query)) : ?>
                                        <option value="<?= e($row['ref_id']) ?>"><?= e($row['ref_nama']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Kewarganegaraan</label>
                                <input type="text" name="warga_negara" class="form-control" value="WNI">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Nomor HP/WA</label>
                                <input type="text" name="warga_no_hp" class="form-control" placeholder="0812...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Email</label>
                                <input type="email" name="warga_email" class="form-control" placeholder="contoh@email.com">
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
                                    <?php mysqli_data_seek($pdk_query, 0); while ($row = mysqli_fetch_assoc($pdk_query)) : ?>
                                        <option value="<?= e($row['ref_id']) ?>"><?= e($row['ref_nama']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Pekerjaan Utama</label>
                                <select name="ref_id_pkj" class="form-select mb-2">
                                    <option value="">- Pilih -</option>
                                    <?php mysqli_data_seek($pkj_query, 0); while ($row = mysqli_fetch_assoc($pkj_query)) : ?>
                                        <option value="<?= e($row['ref_id']) ?>"><?= e($row['ref_nama']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <input type="text" name="warga_pekerjaan" class="form-control" placeholder="Sebutkan jika pilih 'Lainnya'">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Status Perkawinan</label>
                                <select name="ref_id_kwn" class="form-select">
                                    <option value="">- Pilih -</option>
                                    <?php mysqli_data_seek($kwn_query, 0); while ($row = mysqli_fetch_assoc($kwn_query)) : ?>
                                        <option value="<?= e($row['ref_id']) ?>"><?= e($row['ref_nama']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Status Domisili <span class="text-danger">*</span></label>
                                <select name="ref_id_status_aktif" class="form-select" required>
                                    <option value="">- Pilih -</option>
                                    <?php mysqli_data_seek($status_aktif_query, 0); while ($row = mysqli_fetch_assoc($status_aktif_query)) : ?>
                                        <option value="<?= e($row['ref_id']) ?>"><?= e($row['ref_nama']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Tanggal Status Domisili <span class="text-danger">*</span></label>
                                <input type="date" name="warga_mutasi_tanggal" class="form-control" required>
                            </div>

                            <!-- Conditional House Fields for Kepala Keluarga -->
                            <div id="house-fields" class="col-12 d-none">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Nomor Rumah</label>
                                        <select name="rumah_id" id="rumah_id" class="form-select">
                                            <option value="">- Pilih Nomor Rumah -</option>
                                            <?php while ($row = mysqli_fetch_assoc($rumah_query)) : ?>
                                                <option value="<?= e($row['rumah_id']) ?>"><?= e($row['rumah_nama']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Status Rumah</label>
                                        <select name="ref_id_status_rumah" id="status_rumah" class="form-select">
                                            <option value="">- Pilih Status Rumah -</option>
                                            <?php mysqli_data_seek($status_rumah_query, 0); while ($row = mysqli_fetch_assoc($status_rumah_query)) : ?>
                                                <option value="<?= e($row['ref_id']) ?>"><?= e($row['ref_nama']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
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
                                <?php mysqli_data_seek($klg_query, 0); while ($row = mysqli_fetch_assoc($klg_query)) : ?>
                                    <option value="<?= e($row['ref_id']) ?>" <?= ($pre_selected_kk_id && $row['ref_id'] != 34) ? 'selected' : '' ?>><?= e($row['ref_nama']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <input type="text" name="warga_hubungan_keluarga" class="form-control mt-2" placeholder="SHDK Lainnya...">
                        </div>
                        <div id="kk-group" class="mb-0">
                            <label class="form-label small fw-bold">Nama Kepala Keluarga</label>
                            <select name="kk_id" id="kk_id" class="form-select">
                                <option value="">- Pilih Nama KK -</option>
                                <?php while ($row = mysqli_fetch_assoc($namakk_query)) : ?>
                                    <option value="<?= e($row['kk_id']) ?>" <?= ($pre_selected_kk_id == $row['kk_id']) ? 'selected' : '' ?>><?= e($row['kk_nama']) ?></option>
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
                            <label class="form-label small fw-bold">Upload Pas Foto</label>
                            <input type="file" name="warga_foto" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Dokumen KTP (PDF)</label>
                            <input type="file" name="warga_dokumen_ktp" class="form-control">
                            <div class="mt-2 small">
                                <span class="text-muted me-2">KTP WP?</span>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="is_ktp_wp" value="1" id="ya" checked>
                                    <label class="form-check-label" for="ya">Ya</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="is_ktp_wp" value="0" id="tidak">
                                    <label class="form-check-label" for="tidak">Tidak</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="is_ktp_wp" value="2" id="belum">
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
                    <button type="submit" name="simpan" id="btnSimpan" class="btn btn-primary py-3 fw-bold"><i class="bi bi-save me-2"></i> SIMPAN DATA WARGA</button>
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
    const houseFields = document.getElementById('house-fields');
    const rumahId = document.getElementById('rumah_id');
    const statusRumah = document.getElementById('status_rumah');

    function toggleFields() {
        if (shdk.value === '34') { // 34 = Kepala Keluarga
            kkGroup.classList.add('d-none');
            kk.removeAttribute('required');
            
            houseFields.classList.remove('d-none');
            rumahId.setAttribute('required', 'required');
            statusRumah.setAttribute('required', 'required');
        } else {
            kkGroup.classList.remove('d-none');
            kk.setAttribute('required', 'required');
            
            houseFields.classList.add('d-none');
            rumahId.removeAttribute('required');
            statusRumah.removeAttribute('required');
        }
    }
    toggleFields();
    shdk.addEventListener('change', toggleFields);

    // Prevent double submission
    const form = document.getElementById('formTambahWarga');
    const btnSimpan = document.getElementById('btnSimpan');

    form.addEventListener('submit', function() {
        btnSimpan.disabled = true;
        btnSimpan.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Menyimpan...';
        
        // Tambahkan input hidden agar $_POST['simpan'] tetap terkirim jika dibutuhkan
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'simpan';
        hiddenInput.value = '1';
        form.appendChild(hiddenInput);
    });
});
</script>

<?php include '../../views/footer.php'; ?>
