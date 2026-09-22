<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

check_auth(['admin', 'superadmin', 'operator']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    verify_csrf_token();
    
    $id = decrypt_id($_POST['id']);
    if ($id !== false && is_numeric($id)) {
        $username = $_SESSION['user']['username'];
        $sql = "UPDATE warga SET is_delete = 1, updated_by = ?, updated_time = NOW() WHERE warga_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $username, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            header("Location: warga.php?pesan=sukses_hapus");
            exit;
        }
    }
}

header("Location: warga.php");
exit;
