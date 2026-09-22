<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

// Otorisasi: Hanya superadmin yang boleh mengakses
check_auth(['superadmin']);

$error = '';

if (isset($_POST['simpan'])) {
    verify_csrf_token(); // Proteksi CSRF
    
    $username = trim($_POST['username']);
    $nama = trim($_POST['nama']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];

    // Validasi dasar
    if (empty($username) || empty($nama) || empty($password) || empty($role)) {
        $error = "Semua kolom wajib diisi.";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal harus 6 karakter.";
    } else {
        // Cek dulu apakah username sudah ada menggunakan prepared statement
        $sql_check = "SELECT id FROM users WHERE username = ?";
        $stmt_check = mysqli_prepare($conn, $sql_check);
        mysqli_stmt_bind_param($stmt_check, "s", $username);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);

        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $error = "Username sudah digunakan. Silakan pilih username lain.";
        } else {
            // Hash password dengan metode modern dan aman
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert data baru dengan prepared statement
            $sql_insert = "INSERT INTO users (username, password, nama, role) VALUES (?, ?, ?, ?)";
            $stmt_insert = mysqli_prepare($conn, $sql_insert);
            mysqli_stmt_bind_param($stmt_insert, "ssss", $username, $hashed_password, $nama, $role);

            if (mysqli_stmt_execute($stmt_insert)) {
                header("Location: user.php?status=tambah_sukses");
                exit;
            } else {
                $error = "Gagal menyimpan data pengguna.";
            }
        }
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <h4>➕ Tambah Pengguna Baru</h4>
    <hr>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_input() ?>
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" name="username" id="username" class="form-control" placeholder="Masukkan username unik" required value="<?= e($_POST['username'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="nama" class="form-label">Nama Lengkap</label>
            <input type="text" name="nama" id="nama" class="form-control" placeholder="Masukkan nama lengkap pengguna" required value="<?= e($_POST['nama'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" name="password" id="password" class="form-control" placeholder="Minimal 6 karakter" required>
        </div>
        <div class="mb-3">
            <label for="role" class="form-label">Hak Akses (Role)</label>
            <select name="role" id="role" class="form-select" required>
                <option value="" disabled selected>- Pilih Hak Akses -</option>
                <option value="operator">Operator</option>
                <option value="user">User</option>
                <option value="superadmin">Superadmin</option>
            </select>
        </div>
        <button type="submit" name="simpan" class="btn btn-success">💾 Simpan</button>
        <a href="user.php" class="btn btn-secondary">⬅️ Batal</a>
    </form>
</div>

<?php include '../../views/footer.php'; ?>
