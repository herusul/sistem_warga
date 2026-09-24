<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

$encrypted_id = isset($_GET['id']) ? $_GET['id'] : '';
$id = decrypt_id($encrypted_id);

if ($id === false || !is_numeric($id) || (int)$id <= 0) {
    header("Location: aset.php");
    exit;
}
$id = (int)$id;

// Tolak hapus jika masih ada peminjaman aktif (pola rumah.php)
$sql_check = "SELECT COUNT(*) AS total FROM aset_peminjaman WHERE aset_id = ? AND is_aktif = 1";
$stmt_check = mysqli_prepare($conn, $sql_check);
mysqli_stmt_bind_param($stmt_check, "i", $id);
mysqli_stmt_execute($stmt_check);
$aktif = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check))['total'];

if ($aktif > 0) {
    header("Location: aset.php?msg=hapus_gagal_aktif");
    exit;
}

// Hapus riwayat terkait dulu (FK NO ACTION), lalu master barang
mysqli_begin_transaction($conn);
try {
    $stmt1 = mysqli_prepare($conn, "DELETE FROM aset_peminjaman WHERE aset_id = ?");
    mysqli_stmt_bind_param($stmt1, "i", $id);
    mysqli_stmt_execute($stmt1);

    $stmt2 = mysqli_prepare($conn, "DELETE FROM aset_pemakaian WHERE aset_id = ?");
    mysqli_stmt_bind_param($stmt2, "i", $id);
    mysqli_stmt_execute($stmt2);

    $stmt3 = mysqli_prepare($conn, "DELETE FROM aset_barang WHERE aset_id = ?");
    mysqli_stmt_bind_param($stmt3, "i", $id);
    mysqli_stmt_execute($stmt3);

    mysqli_commit($conn);
    header("Location: aset.php?msg=hapus_sukses");
} catch (Throwable $ex) {
    mysqli_rollback($conn);
    header("Location: aset.php");
}
exit;
