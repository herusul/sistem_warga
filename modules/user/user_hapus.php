<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

// Otorisasi: Hanya superadmin yang boleh mengakses
check_auth(['superadmin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: user.php");
    exit;
}

verify_csrf_token(); // Proteksi CSRF

$id = isset($_POST['id']) ? decrypt_id($_POST['id']) : 0;
if ($id === false || !is_numeric($id)) {
    header("Location: user.php?status=id_error");
    exit;
}
$id = (int)$id;

// Keamanan: Pengguna tidak bisa menghapus akunnya sendiri
if ($_SESSION['user']['id'] == $id) {
    header("Location: user.php?status=hapus_diri_gagal");
    exit;
}

// Hapus dengan PREPARED STATEMENT
$sql = "DELETE FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt)) {
    header("Location: user.php?status=hapus_sukses");
} else {
    header("Location: user.php?status=hapus_gagal");
}
exit;
?>
