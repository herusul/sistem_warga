<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');

check_auth(['admin', 'superadmin', 'operator']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token(); // Proteksi CSRF
    
    $kategori_baru = strtolower(trim($_POST['kategori_baru'] ?? ''));
    $kategori_terlarang = ['id', 'admin', 'user', 'sistem', 'login', 'logout', 'auth', 'includes', 'views', 'config', 'setting', 'password'];

    if ($kategori_baru === '') {
        $_SESSION['error'] = "❌ Nama kategori tidak boleh kosong.";
    } elseif (in_array($kategori_baru, $kategori_terlarang)) {
        $_SESSION['error'] = "❌ Kategori '<strong>" . e($kategori_baru) . "</strong>' tidak diperbolehkan.";
    } else {
        // Cek apakah kategori sudah ada dengan prepared statement
        $sql_cek = "SELECT 1 FROM referensi WHERE ref_kategori = ? LIMIT 1";
        $stmt_cek = mysqli_prepare($conn, $sql_cek);
        mysqli_stmt_bind_param($stmt_cek, "s", $kategori_baru);
        mysqli_stmt_execute($stmt_cek);
        
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt_cek)) > 0) {
            $_SESSION['error'] = "❌ Kategori '<strong>" . e($kategori_baru) . "</strong>' sudah ada.";
        } else {
            // Tambah dummy data agar kategori tampil dengan prepared statement
            $sql_insert = "INSERT INTO referensi (ref_kategori, ref_nama, is_aktif) VALUES (?, 'Contoh Isi', 0)";
            $stmt_insert = mysqli_prepare($conn, $sql_insert);
            mysqli_stmt_bind_param($stmt_insert, "s", $kategori_baru);
            mysqli_stmt_execute($stmt_insert);
            
            $_SESSION['success'] = "✅ Kategori '<strong>" . e($kategori_baru) . "</strong>' berhasil ditambahkan.";
        }
    }

    header("Location: referensi.php?kategori=" . urlencode($kategori_baru));
    exit;
}
?>
