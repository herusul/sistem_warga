<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/google_auth_config.php';

// Fungsi helper verifikasi JWT ID Token dari Google
function verify_google_id_token($id_token) {
    if (empty($id_token)) return false;

    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($id_token);
    $response = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $response = @file_get_contents($url);
    }

    if (!$response) return false;

    $data = json_decode($response, true);
    if (!empty($data['email']) && isset($data['email_verified']) && ($data['email_verified'] === true || $data['email_verified'] === 'true')) {
        return $data;
    }

    return false;
}

$google_email = '';
$google_name = '';

// 1. Tangani Google Identity Services POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['credential'])) {
    $token_payload = verify_google_id_token($_POST['credential']);
    if ($token_payload) {
        $google_email = strtolower(trim($token_payload['email']));
        $google_name = $token_payload['name'] ?? '';
    } else {
        header("Location: login.php?error=google_token_invalid");
        exit;
    }
}
// 2. Tangani Dev / Test Mode (jika di localhost dan tombol simulasi dev digunakan)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dev_google_email']) && is_local_dev_environment()) {
    verify_csrf_token();
    $google_email = strtolower(trim($_POST['dev_google_email']));
} else {
    header("Location: login.php");
    exit;
}

if (empty($google_email)) {
    header("Location: login.php?error=google_empty_email");
    exit;
}

// 3. Cari email di tabel warga
$sql = "SELECT w.*, r.ref_nama AS hubungan_keluarga_nama, m.ref_id_status_aktif, sa.ref_nama AS status_aktif_nama
        FROM warga w
        LEFT JOIN referensi r ON r.ref_id = w.ref_id_hubungan_keluarga AND r.ref_kategori = 'hubungan_keluarga'
        LEFT JOIN warga_mutasi m ON m.warga_id = w.warga_id AND m.is_aktif = 1
        LEFT JOIN referensi sa ON sa.ref_id = m.ref_id_status_aktif AND sa.ref_kategori = 'status_aktif'
        WHERE LOWER(TRIM(w.warga_email)) = ? AND w.is_delete IS NULL";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $google_email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($warga = mysqli_fetch_assoc($result)) {
    // Berhasil ditemukan di tabel warga! Set sesi dengan role 'user'
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'                      => (int)$warga['warga_id'],
        'warga_id'                => (int)$warga['warga_id'],
        'username'                => $warga['warga_email'],
        'nama'                    => $warga['warga_nama'],
        'role'                    => 'user',
        'email'                   => $warga['warga_email'],
        'warga_parent'            => $warga['warga_parent'],
        'ref_id_hubungan_keluarga'=> (int)$warga['ref_id_hubungan_keluarga'],
        'hubungan_keluarga'       => $warga['hubungan_keluarga_nama'] ?? 'Warga',
        'foto'                    => $warga['warga_foto'] ?? 'profile_blank.jpg',
        'status_aktif'            => $warga['status_aktif_nama'] ?? 'Menetap di WP'
    ];

    $_SESSION['last_activity'] = time();
    $_SESSION['login_time']    = time();

    header("Location: modules/dashboard/dashboard.php");
    exit;
} else {
    // Email tidak terdaftar dalam tabel warga
    header("Location: login.php?error=google_not_registered&email=" . urlencode($google_email));
    exit;
}
?>
