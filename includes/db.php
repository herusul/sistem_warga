<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "wahanapraja";

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Set charset ke utf8mb4 agar mendukung semua karakter
mysqli_set_charset($conn, "utf8mb4");

// Set timezone database agar sesuai dengan aplikasi
date_default_timezone_set("Asia/Jakarta");
mysqli_query($conn, "SET time_zone = '+07:00'");
?>
