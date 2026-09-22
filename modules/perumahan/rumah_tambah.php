<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$user = $_SESSION['user'];
$role = $user['role'];
$username = $_SESSION['user']['username'];

// Ambil referensi gang
$gangs = mysqli_query($conn, "SELECT * FROM referensi WHERE ref_kategori='gang' AND is_aktif=1 ORDER BY ref_nama");

$error = '';

// Handle form submit
if (isset($_POST['simpan'])) {
    verify_csrf_token(); // Proteksi CSRF
    
    $nomor = trim($_POST['rumah_nomor']);
    $ref_id_gang = $_POST['ref_id_gang'];
    $luas_tanah = $_POST['rumah_luas_tanah'];
    $luas_bangunan = $_POST['rumah_luas_bangunan'];
    $keterangan = trim($_POST['rumah_keterangan']);
    $status = trim($_POST['rumah_status']);

    if ($nomor && $ref_id_gang) {
        $nomor_tampil = $nomor;

        $sql = "INSERT INTO rumah (rumah_nomor, rumah_nomor_tampil, ref_id_gang, rumah_luas_tanah, rumah_luas_bangunan, rumah_keterangan, rumah_status, created_by, created_time)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssisssss", $nomor, $nomor_tampil, $ref_id_gang, $luas_tanah, $luas_bangunan, $keterangan, $status, $username);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: rumah.php?msg=sukses");
            exit;
        } else {
            $error = "Gagal menyimpan data: " . mysqli_stmt_error($stmt);
        }
    } else {
        $error = "Nomor rumah dan gang harus diisi.";
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <h4>➕ Tambah Data Rumah</h4>

    <?php if ($error): ?>
        <div class="alert alert-danger">❌ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_input() ?>
        <div class="mb-3">
            <label>Nomor Rumah <span class="text-danger">*</span></label>
            <input type="text" name="rumah_nomor" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Gang / Jalan <span class="text-danger">*</span></label>
            <select name="ref_id_gang" class="form-control" required>
                <option value="">- Pilih Gang -</option>
                <?php while ($row = mysqli_fetch_assoc($gangs)) : ?>
                    <option value="<?= e($row['ref_id']) ?>"><?= e($row['ref_nama']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label>Luas Tanah (m<sup>2</sup>)</label>
            <input type="number" name="rumah_luas_tanah" class="form-control" min="0">
        </div>

        <div class="mb-3">
            <label>Luas Bangunan (m<sup>2</sup>)</label>
            <input type="number" name="rumah_luas_bangunan" class="form-control" min="0">
        </div>

        <div class="mb-3">
            <label>Keterangan</label>
            <textarea name="rumah_keterangan" class="form-control"></textarea>
        </div>
 
        <div class="mb-3">
			<label>Status Rumah <span class="text-danger">*</span></label>
			<select name="rumah_status" class="form-control" required>
				<option value="">- Pilih Status -</option>
				<option value="Dihuni">Dihuni</option>
				<option value="Kosong">Kosong</option>
				<option value="Dijual">Dijual</option>
			</select>
		</div>

        <button type="submit" name="simpan" class="btn btn-primary">💾 Simpan</button>
        <a href="rumah.php" class="btn btn-secondary">🔙 Batal</a>
    </form>
</div>

<?php include '../../views/footer.php'; ?>
