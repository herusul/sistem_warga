<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

// Otorisasi: Hanya superadmin yang boleh mengakses
check_auth(['superadmin']);

if (!isset($_GET['id'])) {
    header("Location: user.php");
    exit;
}

$id = decrypt_id($_GET['id']);
if ($id === false || !is_numeric($id)) {
    header("Location: user.php?status=id_error");
    exit;
}
$id = (int)$id;

// Ambil data user dengan prepared statement
$sql_select = "SELECT * FROM users WHERE id = ?";
$stmt_select = mysqli_prepare($conn, $sql_select);
mysqli_stmt_bind_param($stmt_select, "i", $id);
mysqli_stmt_execute($stmt_select);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_select));

if (!$user) {
    header("Location: user.php?status=notfound");
    exit;
}

$error = '';

if (isset($_POST['update'])) {
    verify_csrf_token(); // Proteksi CSRF
    
    $username = trim($_POST['username']);
    $nama = trim($_POST['nama']);
    $role = $_POST['role'];
    $password = trim($_POST['password']);

    if (empty($username) || empty($nama) || empty($role)) {
        $error = "Username, Nama, dan Hak Akses tidak boleh kosong.";
    } else {
        if (!empty($password)) {
            // Jika password diisi, update password
            if (strlen($password) < 6) {
                $error = "Password baru minimal harus 6 karakter.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql_update = "UPDATE users SET username = ?, nama = ?, role = ?, password = ? WHERE id = ?";
                $stmt_update = mysqli_prepare($conn, $sql_update);
                mysqli_stmt_bind_param($stmt_update, "ssssi", $username, $nama, $role, $hashed_password, $id);
            }
        } else {
            // Jika password kosong, jangan update password
            $sql_update = "UPDATE users SET username = ?, nama = ?, role = ? WHERE id = ?";
            $stmt_update = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "sssi", $username, $nama, $role, $id);
        }

        if (empty($error)) {
            if (mysqli_stmt_execute($stmt_update)) {
                header("Location: user.php?status=edit_sukses");
                exit;
            } else {
                $error = "Gagal memperbarui data. Cek jika username sudah ada.";
            }
        }
    }
}
?>

<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <h4>✏️ Edit Pengguna</h4>
    <hr>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_input() ?>
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" name="username" id="username" class="form-control" value="<?= e($user['username']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="nama" class="form-label">Nama Lengkap</label>
            <input type="text" name="nama" id="nama" class="form-control" value="<?= e($user['nama']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password Baru</label>
            <input type="password" name="password" id="password" class="form-control" placeholder="Biarkan kosong jika tidak ingin diubah">
            <small class="form-text text-muted">Jika diisi, minimal 6 karakter.</small>
        </div>
        <div class="mb-3">
            <label for="role" class="form-label">Hak Akses (Role)</label>
            <select name="role" id="role" class="form-select" required>
                <option value="operator" <?= $user['role'] == 'operator' ? 'selected' : '' ?>>Operator</option>
                <option value="user" <?= in_array($user['role'], ['user', 'pengguna']) ? 'selected' : '' ?>>User</option>
                <option value="superadmin" <?= $user['role'] == 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
            </select>
        </div>
        <button type="submit" name="update" class="btn btn-primary">💾 Simpan Perubahan</button>
        <a href="user.php" class="btn btn-secondary">⬅️ Batal</a>
    </form>
</div>

<?php include '../../views/footer.php'; ?>
