<?php
// Pastikan parameter cookie sesi memenuhi standar keamanan OWASP sebelum session_start()
if (session_status() === PHP_SESSION_NONE) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    // Mencegah Session Fixation & memastikan session hanya via Cookie
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,          // Cookie kedaluwarsa saat browser ditutup
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,  // Otomatis aktif jika koneksi HTTPS
        'httponly' => true,       // Mencegah akses cookie via JavaScript (anti-XSS)
        'samesite' => 'Strict'    // Mencegah CSRF dengan SameSite=Strict
    ]);

    session_start();
}

/**
 * Invalidate session secara menyeluruh (OWASP compliant)
 */
function invalidate_session() {
    $_SESSION = [];

    if (ini_get("session.use_cookies") && !headers_sent()) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/**
 * Deteksi apakah request merupakan AJAX / Fetch / API call
 */
function is_ajax_request() {
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

/**
 * Mendapatkan relative path ke login.php berdasarkan lokasi script
 */
function get_login_path() {
    $script_path = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (strpos($script_path, '/modules/') !== false) {
        return '../../login.php';
    }
    return 'login.php';
}

/**
 * Memeriksa apakah pengguna sudah login, sesi belum timeout (180s), dan memiliki role yang diizinkan.
 * Mengembalikan HTTP 401 Unauthorized jika sesi kedaluwarsa.
 * @param array $allowed_roles Daftar role yang diizinkan (opsional)
 */
function check_auth($allowed_roles = []) {
    $login_url = get_login_path();
    $timeout_duration = 180; // 3 menit (180 detik)
    $now = time();

    // 1. Periksa apakah user terautentikasi
    if (!isset($_SESSION['user'])) {
        if (is_ajax_request()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'error',
                'code' => 401,
                'message' => 'Anda belum login atau sesi telah berakhir.'
            ]);
            exit;
        }

        header("Location: " . $login_url);
        exit;
    }

    // 2. Server-side validation: Validasi selisih waktu aktivitas terakhir
    if (isset($_SESSION['last_activity'])) {
        $inactive_seconds = $now - $_SESSION['last_activity'];
        if ($inactive_seconds > $timeout_duration) {
            // Sesi kedaluwarsa -> Invalidate sesi dan cookie
            invalidate_session();

            // Tangani AJAX / API request
            if (is_ajax_request()) {
                if (!headers_sent()) {
                    http_response_code(401);
                    header('Content-Type: application/json; charset=utf-8');
                }
                echo json_encode([
                    'status' => 'error',
                    'code' => 401,
                    'message' => 'Sesi telah berakhir karena tidak ada aktivitas selama 3 menit. Silakan login kembali.'
                ]);
                exit;
            }

            // Tangani regular page request: Kirim status HTTP 401 Unauthorized dan redirect
            if (!headers_sent()) {
                http_response_code(401);
            }
            $redirect_target = $login_url . '?timeout=1';
            echo '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirect_target, ENT_QUOTES, 'UTF-8') . '">
    <title>401 Sesi Berakhir</title>
</head>
<body>
    <script>
        try {
            localStorage.clear();
            sessionStorage.clear();
        } catch(e) {}
        window.location.href = "' . htmlspecialchars($redirect_target, ENT_QUOTES, 'UTF-8') . '";
    </script>
    <p>Sesi Anda telah kedaluwarsa. Mengalihkan ke halaman <a href="' . htmlspecialchars($redirect_target, ENT_QUOTES, 'UTF-8') . '">login</a>...</p>
</body>
</html>';
            exit;
        }
    }

    // Perbarui timestamp aktivitas terakhir untuk request saat ini
    $_SESSION['last_activity'] = $now;

    // 3. Validasi hak akses role
    if (!empty($allowed_roles)) {
        $user_role = $_SESSION['user']['role'] ?? '';
        $is_allowed = in_array($user_role, $allowed_roles)
            || ($user_role === 'user' && in_array('pengguna', $allowed_roles))
            || ($user_role === 'pengguna' && in_array('user', $allowed_roles));

        if (!$is_allowed) {
            echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='../dashboard/dashboard.php';</script>";
            exit;
        }
    }
}
?>
