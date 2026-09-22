<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');

check_auth(['admin', 'superadmin', 'operator']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token(); // Proteksi CSRF
    
    $kategori = trim($_POST['kategori'] ?? '');
    $nama = trim($_POST['isi'] ?? '');
    $is_aktif = isset($_POST['is_aktif']) ? (int)$_POST['is_aktif'] : 1;

    if ($kategori === '' || $nama === '') {
        $_SESSION['error'] = "Kategori dan nama referensi harus diisi.";
    } else {
        // Cek apakah data sudah ada dengan prepared statement
        $sql_cek = "SELECT 1 FROM referensi WHERE ref_kategori = ? AND ref_nama = ?";
        $stmt_cek = mysqli_prepare($conn, $sql_cek);
        mysqli_stmt_bind_param($stmt_cek, "ss", $kategori, $nama);
        mysqli_stmt_execute($stmt_cek);
        
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt_cek)) > 0) {
            $_SESSION['error'] = "❌ Referensi '<strong>" . e($nama) . "</strong>' sudah ada di kategori '<strong>" . e($kategori) . "</strong>'.";
        } else {
            $sql_insert = "INSERT INTO referensi (ref_kategori, ref_nama, is_aktif) VALUES (?, ?, ?)";
            $stmt_insert = mysqli_prepare($conn, $sql_insert);
            mysqli_stmt_bind_param($stmt_insert, 'ssi', $kategori, $nama, $is_aktif);
            mysqli_stmt_execute($stmt_insert);
            $_SESSION['success'] = "✅ Referensi berhasil ditambahkan.";
        }
    }

    header("Location: referensi.php?kategori=" . urlencode($kategori));
    exit;
}
?>
