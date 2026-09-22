<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$encrypted_id = isset($_GET['id']) ? $_GET['id'] : '';
$id = decrypt_id($encrypted_id);

if ($id === false || !is_numeric($id) || (int)$id <= 0) {
    header("Location: rumah.php");
    exit;
}
$id = (int)$id;

// Pastikan rumah tidak sedang dihuni (opsional, tapi bagus untuk stabilitas)
$sql_check = "SELECT COUNT(*) as total FROM warga_rumah WHERE rumah_id = ? AND is_aktif = 1";
$stmt_check = mysqli_prepare($conn, $sql_check);
mysqli_stmt_bind_param($stmt_check, "i", $id);
mysqli_stmt_execute($stmt_check);
$is_occupied = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check))['total'];

if ($is_occupied > 0) {
    echo "<script>alert('Gagal: Rumah masih memiliki warga aktif. Kosongkan rumah terlebih dahulu.'); window.location='rumah.php';</script>";
    exit;
}

// Lakukan delete dengan prepared statement
$sql_delete = "DELETE FROM rumah WHERE rumah_id = ?";
$stmt_delete = mysqli_prepare($conn, $sql_delete);
mysqli_stmt_bind_param($stmt_delete, "i", $id);

if (mysqli_stmt_execute($stmt_delete)) {
    header("Location: rumah.php?msg=hapus_sukses");
} else {
    header("Location: rumah.php?msg=hapus_gagal");
}
exit;
?>
