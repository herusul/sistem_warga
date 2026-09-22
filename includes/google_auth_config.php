<?php
/**
 * Konfigurasi Integrasi Google Sign-In (OAuth / Google Identity Services)
 * 
 * Untuk menggunakan Google Sign-In resmi:
 * 1. Buka Google Cloud Console (https://console.cloud.google.com/)
 * 2. Buat / pilih project, lalu aktifkan OAuth Consent Screen
 * 3. Masuk ke 'Credentials' -> 'Create Credentials' -> 'OAuth client ID'
 * 4. Application type: Web application
 * 5. Masukkan Authorized JavaScript origins (contoh: http://localhost)
 * 6. Masukkan Authorized redirect URIs (contoh: http://localhost/sistem_warga/google_auth.php)
 * 7. Salin Client ID yang dihasilkan ke konstanta GOOGLE_CLIENT_ID di bawah ini.
 */

if (!defined('GOOGLE_CLIENT_ID')) {
    // Ganti string di bawah ini dengan Client ID Google Cloud Console Anda
    define('GOOGLE_CLIENT_ID', ''); 
}

/**
 * Memeriksa apakah konfigurasi Google OAuth Client ID sudah terpasang
 */
function is_google_auth_configured() {
    $cid = trim(GOOGLE_CLIENT_ID);
    return !empty($cid) && strpos($cid, 'apps.googleusercontent.com') !== false;
}

/**
 * Deteksi apakah aplikasi berjalan di lingkungan lokal (Dev/Test mode)
 */
function is_local_dev_environment() {
    $server_name = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    return in_array($server_name, ['localhost', '127.0.0.1', '::1'])
        || in_array($remote_addr, ['127.0.0.1', '::1']);
}
?>
