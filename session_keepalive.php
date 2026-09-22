<?php
/**
 * API Endpoint: Session Keep-Alive / Heartbeat
 * Digunakan oleh frontend saat pengguna memilih "Perpanjang Sesi".
 * Dilengkapi dengan validasi CSRF Token, status autentikasi, dan pembatasan timeout server-side 180s.
 */

require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Hanya izinkan metode HTTP POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'code' => 405,
        'message' => 'Method Not Allowed'
    ]);
    exit;
}

// 2. Ambil dan validasi CSRF Token untuk mencegah pemanggilan tidak sah (anti-CSRF)
$headers = function_exists('getallheaders') ? getallheaders() : [];
$header_token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
$input_token = $_POST['csrf_token'] ?? $header_token;

if (empty($_SESSION['csrf_token']) || empty($input_token) || !hash_equals($_SESSION['csrf_token'], $input_token)) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'code' => 403,
        'message' => 'Validasi CSRF token gagal. Akses ditolak.'
    ]);
    exit;
}

// 3. Validasi status autentikasi pengguna
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'code' => 401,
        'message' => 'User tidak terautentikasi atau sesi telah berakhir.'
    ]);
    exit;
}

// 4. Validasi waktu inaktivitas server-side (180 detik)
$timeout_limit = 180;
$now = time();
$last_activity = $_SESSION['last_activity'] ?? $now;

if (($now - $last_activity) > $timeout_limit) {
    // Sesi telah kedaluwarsa di server, hapus total sesi
    invalidate_session();

    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'code' => 401,
        'message' => 'Sesi telah kedaluwarsa karena tidak ada aktivitas selama 3 menit. Silakan login kembali.'
    ]);
    exit;
}

// 5. Perpanjang sesi: Perbarui timestamp aktivitas terakhir
$_SESSION['last_activity'] = $now;

echo json_encode([
    'status' => 'success',
    'code' => 200,
    'message' => 'Sesi berhasil diperpanjang.',
    'expires_in' => $timeout_limit
]);
exit;
