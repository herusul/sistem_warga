<?php
require_once 'includes/auth.php'; // Otomatis mengonfigurasi session cookie dengan standar keamanan OWASP
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/google_auth_config.php';

// Jika sudah login, langsung ke dashboard
if (isset($_SESSION['user'])) {
    header("Location: modules/dashboard/dashboard.php");
    exit;
}

$error = '';
$google_error = '';

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'google_not_registered') {
        $attempted_email = htmlspecialchars($_GET['email'] ?? '', ENT_QUOTES, 'UTF-8');
        $google_error = "Email Google <strong>" . $attempted_email . "</strong> belum terdaftar dalam sistem warga RT 03/14. Silakan hubungi pengurus RT untuk mendaftarkan email Anda.";
    } elseif ($_GET['error'] === 'google_token_invalid') {
        $google_error = "Verifikasi akun Google gagal atau kedaluwarsa. Silakan coba lagi.";
    } elseif ($_GET['error'] === 'google_empty_email') {
        $google_error = "Gagal membaca alamat email dari akun Google Anda.";
    }
}

generate_csrf_token();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    verify_csrf_token();
    
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $captcha_input = trim($_POST['captcha']);
    $stored_captcha = $_SESSION['captcha_text'] ?? '';

    if (empty($captcha_input) || $captcha_input !== $stored_captcha) {
        $error = "Kode verifikasi salah!";
    } elseif (empty($username) || empty($password)) {
        $error = "Username/Password wajib diisi!";
    } else {
        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($user = mysqli_fetch_assoc($result)) {
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true); // Mitigasi session fixation
                $_SESSION['user'] = $user;
                $_SESSION['last_activity'] = time(); // Inisialisasi timestamp aktivitas
                $_SESSION['login_time'] = time();
                generate_csrf_token();
                header("Location: modules/dashboard/dashboard.php");
                exit;
            }
        }
        $error = "Username atau password salah!";
    }
}

// Untuk Dev Test Mode: ambil contoh warga yang memiliki email
$sample_warga_emails = [];
if (is_local_dev_environment()) {
    $res_emails = mysqli_query($conn, "SELECT warga_id, warga_nama, warga_email, ref_id_hubungan_keluarga FROM warga WHERE warga_email IS NOT NULL AND warga_email != '' AND is_delete IS NULL LIMIT 6");
    if ($res_emails) {
        while ($rw = mysqli_fetch_assoc($res_emails)) {
            $sample_warga_emails[] = $rw;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login SI-WARGA - RT 03/14</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="../../assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <?php if (is_google_auth_configured()): ?>
        <script src="https://accounts.google.com/gsi/client" async defer></script>
    <?php endif; ?>
    <style>
        :root {
            --primary-grad: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        }
        body { 
            background: var(--primary-grad); 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 15px 10px;
        }
        .login-container { width: 100%; max-width: 400px; }
        .login-card { 
            background: #ffffff; 
            border-radius: 16px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.2); 
            padding: 25px 25px 18px;
            border: none;
        }
        .login-header { text-align: center; margin-bottom: 15px; }
        .login-header .icon-box {
            width: 45px; height: 45px;
            background: var(--primary-grad);
            color: white; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 10px; font-size: 1.4rem;
            box-shadow: 0 4px 8px rgba(78, 115, 223, 0.2);
        }
        .form-label { font-size: 0.8rem; font-weight: 600; color: #4a5568; margin-bottom: 4px; }
        .form-control { 
            border-radius: 8px; padding: 10px 12px; 
            background-color: #f8fafc; font-size: 0.9rem;
        }
        .btn-login { 
            background: var(--primary-grad); border: none; 
            padding: 10px; font-weight: 700; border-radius: 10px;
            margin-top: 5px; font-size: 0.95rem;
        }
        .captcha-row { display: flex; flex-direction: column; gap: 10px; margin-bottom: 15px; }
        .captcha-img-box { background: #ffffff; border-radius: 10px; padding: 0; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; border: 1px solid #e2e8f0; }
        .captcha-refresh { position: absolute; right: 5px; top: 5px; background: rgba(255,255,255,0.8); border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: all 0.2s; }
        .captcha-refresh:hover { background: #fff; transform: rotate(180deg); color: #224abe; }
        .btn-google {
            border: 1px solid #dadce0;
            background-color: #ffffff;
            color: #3c4043;
            font-weight: 600;
            font-size: 0.9rem;
            border-radius: 10px;
            padding: 10px 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            transition: all 0.2s ease;
        }
        .btn-google:hover {
            background-color: #f8fafd;
            border-color: #c2c7d0;
            box-shadow: 0 1px 3px rgba(60,64,67,0.15);
        }
        .dev-badge {
            background-color: #e7f5ff;
            color: #1971c2;
            border: 1px dashed #74c0fc;
            border-radius: 8px;
            padding: 10px;
            font-size: 0.78rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card shadow-lg">
            <div class="login-header">
                <div class="icon-box"><i class="bi bi-shield-lock-fill"></i></div>
                <h5 class="fw-bold text-dark mb-0">SI-WARGA WP RT 03/14</h5>
                <p class="text-muted small mb-0 mt-1">Sistem Informasi Kependudukan Warga</p>
            </div>
            
            <?php if (isset($_GET['timeout'])): ?>
                <div class="alert alert-warning border-0 small py-2 px-3 mb-3 text-center" role="alert">
                    <i class="bi bi-clock-history me-1"></i> Sesi Anda telah berakhir karena tidak ada aktivitas selama 3 menit. Silakan login kembali demi keamanan.
                </div>
                <script>
                    try {
                        localStorage.clear();
                        sessionStorage.clear();
                    } catch(e) {}
                </script>
            <?php endif; ?>

            <?php if ($google_error): ?>
                <div class="alert alert-warning border-0 small py-2 px-3 mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i> <?= $google_error ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger border-0 small py-2 px-3 mb-3 text-center" role="alert">
                    <i class="bi bi-exclamation-circle me-1"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>

            <!-- SECTION 1: LOGIN GOOGLE UNTUK WARGA -->
            <div class="mb-3">
                <div class="text-center mb-2">
                    <span class="small fw-bold text-primary"><i class="bi bi-people-fill me-1"></i> Login Warga (Role User)</span>
                </div>

                <?php if (is_google_auth_configured()): ?>
                    <div id="g_id_onload"
                         data-client_id="<?= htmlspecialchars(GOOGLE_CLIENT_ID, ENT_QUOTES, 'UTF-8') ?>"
                         data-context="signin"
                         data-ux_mode="popup"
                         data-login_uri="google_auth.php"
                         data-auto_prompt="false">
                    </div>
                    <div class="g_id_signin d-flex justify-content-center"
                         data-type="standard"
                         data-shape="rectangular"
                         data-theme="outline"
                         data-text="signin_with"
                         data-size="large"
                         data-logo_alignment="left"
                         data-width="350">
                    </div>
                <?php else: ?>
                    <!-- Fallback / Dev Mode jika Client ID belum diisi -->
                    <div class="dev-badge mb-2">
                        <div class="fw-bold mb-1"><i class="bi bi-google me-1 text-danger"></i> Masuk dengan Google</div>
                        <p class="mb-1 text-muted" style="font-size: 0.72rem;">
                            Pastikan Client ID sudah dikonfigurasi di <code>includes/google_auth_config.php</code>.
                        </p>
                        <?php if (is_local_dev_environment() && !empty($sample_warga_emails)): ?>
                            <form action="google_auth.php" method="post" class="mt-2">
                                <?= csrf_input() ?>
                                <label class="fw-semibold text-dark" style="font-size: 0.75rem;">Simulasi Login Warga (Dev Mode):</label>
                                <div class="input-group input-group-sm mt-1">
                                    <select name="dev_google_email" class="form-select form-select-sm" required>
                                        <option value="" disabled selected>- Pilih Email Warga Terdaftar -</option>
                                        <?php foreach ($sample_warga_emails as $sw): ?>
                                            <option value="<?= e($sw['warga_email']) ?>">
                                                <?= e($sw['warga_nama']) ?> (<?= e($sw['warga_email']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary">Masuk</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="d-flex align-items-center my-3">
                <hr class="flex-grow-1 my-0 opacity-25">
                <span class="px-2 text-muted small fw-semibold" style="font-size: 0.75rem;">atau Login Pengurus (Superadmin / Operator)</span>
                <hr class="flex-grow-1 my-0 opacity-25">
            </div>

            <!-- SECTION 2: LOGIN INTERNAL USERNAME & PASSWORD -->
            <form method="post">
                <?= csrf_input() ?>
                <div class="mb-2">
                    <label class="form-label">Username</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" class="form-control border-start-0 ps-0" placeholder="Username pengurus" required>
                    </div>
                </div>
                
                <div class="mb-2">
                    <label class="form-label">Password</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-key"></i></span>
                        <input type="password" name="password" class="form-control border-start-0 ps-0" placeholder="Password" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Verifikasi</label>
                    <div class="captcha-row">
                        <div class="captcha-img-box">
                            <img src="captcha_image.php" id="cap-img" alt="Captcha" style="width: 100%; height: 50px; object-fit: cover;">
                            <button type="button" class="captcha-refresh" onclick="document.getElementById('cap-img').src='captcha_image.php?'+Date.now()" title="Refresh Kode">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                        <input type="text" name="captcha" class="form-control" placeholder="Masukkan kode di atas" required autocomplete="off">
                    </div>
                </div>

                <button type="submit" name="login" class="btn btn-primary w-100 btn-login shadow-sm">
                    Masuk sebagai Pengurus <i class="bi bi-arrow-right ms-1"></i>
                </button>
                
                <div class="text-center mt-3">
                    <a href="index.php" class="text-decoration-none extra-small text-muted">
                        <i class="bi bi-house-door me-1"></i> Beranda Publik
                    </a>
                </div>
            </form>
        </div>
        <div class="text-center mt-3 text-white-50" style="font-size: 10px;">
            &copy; <?= date('Y') ?> SI-WARGA RT 03/14
        </div>
    </div>
</body>
</html>
