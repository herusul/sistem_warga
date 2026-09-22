<?php
/**
 * View Component: Session Inactivity Warning Modal & Client Monitor
 * Dijalankan pada setiap halaman terautentikasi (melalui views/footer.php).
 * Menerapkan timer 180 detik (3 menit), peringatan 30 detik sebelum habis,
 * dan pembersihan client storage (OWASP compliance).
 */

$in_modules = (strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/modules/') !== false);
$base_prefix = $in_modules ? '../../' : './';
$csrf_token = $_SESSION['csrf_token'] ?? '';
?>

<!-- Session Timeout Modal (Bootstrap 5) -->
<div class="modal fade" id="sessionTimeoutModal" tabindex="-1" aria-labelledby="sessionTimeoutModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false" style="z-index: 10600;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <div class="d-flex align-items-center">
                    <i class="bi bi-shield-exclamation fs-3 me-2"></i>
                    <h5 class="modal-title fw-bold mb-0" id="sessionTimeoutModalLabel">Sesi Akan Berakhir</h5>
                </div>
            </div>
            <div class="modal-body text-center p-4">
                <div class="mb-3">
                    <div class="countdown-circle mx-auto d-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-circle shadow-sm" style="width: 80px; height: 80px;">
                        <span id="sessionCountdownBadge" class="display-6 fw-bold">30</span>
                    </div>
                    <div class="small text-muted text-uppercase fw-semibold mt-1">Detik Tersisa</div>
                </div>

                <p class="text-secondary mb-2" style="font-size: 0.95rem;">
                    Tidak ada aktivitas terdeteksi. Demi keamanan data Anda, sistem akan mengakhiri sesi secara otomatis jika tidak diperpanjang.
                </p>

                <!-- Animated Progress Bar -->
                <div class="progress mt-3 mb-2" style="height: 6px; border-radius: 4px; background-color: #f1f3f5;">
                    <div id="sessionProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-danger" role="progressbar" style="width: 100%; transition: width 1s linear;"></div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light px-4 py-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary px-3" id="btnLogoutNow">
                    <i class="bi bi-box-arrow-right me-1"></i> Keluar
                </button>
                <button type="button" class="btn btn-primary px-4 fw-semibold" id="btnExtendSession">
                    <i class="bi bi-arrow-repeat me-1" id="btnExtendIcon"></i> Perpanjang Sesi
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // Konfigurasi Parameter Keamanan Sesi
    const CONFIG = {
        TOTAL_TIMEOUT: 180, // Total sesi inaktif 180 detik (3 menit)
        WARNING_TIME: 30,   // Peringatan visual muncul 30 detik sebelum habis
        KEEP_ALIVE_URL: '<?= $base_prefix ?>session_keepalive.php',
        LOGOUT_URL: '<?= $base_prefix ?>logout.php',
        LOGIN_URL: '<?= $base_prefix ?>login.php?timeout=1',
        CSRF_TOKEN: '<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>'
    };

    let inactivityTimer = null;
    let countdownInterval = null;
    let warningSecondsLeft = CONFIG.WARNING_TIME;
    let isModalShowing = false;
    let lastActivityTimestamp = Date.now();
    let lastKeepAliveTimestamp = Date.now();

    // DOM Elements
    const modalElement = document.getElementById('sessionTimeoutModal');
    let bootstrapModal = null;
    const countdownBadge = document.getElementById('sessionCountdownBadge');
    const progressBar = document.getElementById('sessionProgressBar');
    const btnExtend = document.getElementById('btnExtendSession');
    const btnExtendIcon = document.getElementById('btnExtendIcon');
    const btnLogout = document.getElementById('btnLogoutNow');

    /**
     * OWASP Client Storage Sanitization:
     * Hapus semua token, state aplikasi, dan data sensitif di localStorage & sessionStorage.
     */
    function purgeClientStorage() {
        try {
            localStorage.clear();
            sessionStorage.clear();
        } catch (e) {
            console.error('Gagal membersihkan client storage:', e);
        }
    }

    /**
     * Inisialisasi Bootstrap Modal
     */
    function getModalInstance() {
        if (!bootstrapModal && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrapModal = new bootstrap.Modal(modalElement, {
                backdrop: 'static',
                keyboard: false
            });
        }
        return bootstrapModal;
    }

    /**
     * Tampilkan Modal Peringatan Timeout (30 Detik Terakhir)
     */
    function showTimeoutWarning() {
        const modal = getModalInstance();
        if (!modal) return;

        isModalShowing = true;
        warningSecondsLeft = CONFIG.WARNING_TIME;
        updateWarningDisplay();
        modal.show();

        // Mulai hitung mundur setiap 1 detik
        if (countdownInterval) clearInterval(countdownInterval);
        countdownInterval = setInterval(function() {
            warningSecondsLeft--;
            updateWarningDisplay();

            if (warningSecondsLeft <= 0) {
                clearInterval(countdownInterval);
                handleSessionExpired();
            }
        }, 1000);
    }

    /**
     * Perbarui angka detik dan animasi progress bar pada modal
     */
    function updateWarningDisplay() {
        if (countdownBadge) {
            countdownBadge.textContent = warningSecondsLeft;
        }
        if (progressBar) {
            const percentage = Math.max(0, (warningSecondsLeft / CONFIG.WARNING_TIME) * 100);
            progressBar.style.width = percentage + '%';
        }
    }

    /**
     * Tangani Sesi Habis: Bersihkan memori dan alihkan ke Login
     */
    function handleSessionExpired() {
        purgeClientStorage();
        // Arahkan ke endpoint logout dengan indikator timeout agar server juga membersihkan session
        window.location.href = CONFIG.LOGOUT_URL + '?timeout=1';
    }

    /**
     * Reset Timer Inaktivitas Client
     */
    function resetInactivityTimer() {
        // Jika modal sedang tampil, aktivitas mouse biasa TIDAK boleh menutup modal.
        // Pengguna WAJIB menekan tombol 'Perpanjang Sesi' (explicit confirmation)
        if (isModalShowing) return;

        lastActivityTimestamp = Date.now();

        if (inactivityTimer) clearTimeout(inactivityTimer);

        // Timer untuk memicu peringatan modal pada detik ke-150 (30 detik sebelum timeout)
        const warnAfterMs = (CONFIG.TOTAL_TIMEOUT - CONFIG.WARNING_TIME) * 1000;
        inactivityTimer = setTimeout(showTimeoutWarning, warnAfterMs);

        // Jika pengguna terus aktif selama > 90 detik tanpa memuat halaman baru,
        // kirim sinkronisasi keep-alive otomatis ke backend agar sesi server tidak kedaluwarsa
        const elapsedSinceKeepAlive = (Date.now() - lastKeepAliveTimestamp) / 1000;
        if (elapsedSinceKeepAlive >= 90) {
            sendKeepAliveRequest(true); // background silent ping
        }
    }

    /**
     * Kirim Request Keep-Alive ke Server
     * @param {boolean} isSilent Jika true, jangan ubah status UI modal
     */
    function sendKeepAliveRequest(isSilent) {
        if (!isSilent && btnExtend) {
            btnExtend.disabled = true;
            if (btnExtendIcon) btnExtendIcon.classList.add('bi-spin');
        }

        const formData = new URLSearchParams();
        formData.append('csrf_token', CONFIG.CSRF_TOKEN);

        fetch(CONFIG.KEEP_ALIVE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': CONFIG.CSRF_TOKEN
            },
            body: formData.toString()
        })
        .then(function(response) {
            if (response.status === 401) {
                // Server telah membatalkan sesi karena timeout
                handleSessionExpired();
                return null;
            }
            return response.json();
        })
        .then(function(data) {
            if (!data) return;

            if (data.status === 'success') {
                lastKeepAliveTimestamp = Date.now();

                if (!isSilent) {
                    // Tutup modal dan reset hitung mundur
                    if (countdownInterval) clearInterval(countdownInterval);
                    const modal = getModalInstance();
                    if (modal) modal.hide();
                    isModalShowing = false;
                    resetInactivityTimer();
                }
            } else {
                handleSessionExpired();
            }
        })
        .catch(function(err) {
            console.error('Keep-alive error:', err);
            if (!isSilent) {
                handleSessionExpired();
            }
        })
        .finally(function() {
            if (!isSilent && btnExtend) {
                btnExtend.disabled = false;
                if (btnExtendIcon) btnExtendIcon.classList.remove('bi-spin');
            }
        });
    }

    /**
     * Throttle User Activity Listener (Event Mouse, Keyboard, Click, Scroll)
     */
    let lastThrottleTime = 0;
    function handleUserActivity() {
        const now = Date.now();
        // Batasi frekuensi event handler maksimal 1 kali per 1000ms
        if (now - lastThrottleTime >= 1000) {
            lastThrottleTime = now;
            resetInactivityTimer();
        }
    }

    // Pasang Event Listeners Standar Sesuai Ketentuan
    const events = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];
    events.forEach(function(evt) {
        window.addEventListener(evt, handleUserActivity, { passive: true });
    });

    // Pasang Event pada Tombol Modal
    if (btnExtend) {
        btnExtend.addEventListener('click', function() {
            sendKeepAliveRequest(false);
        });
    }

    if (btnLogout) {
        btnLogout.addEventListener('click', function() {
            purgeClientStorage();
            window.location.href = CONFIG.LOGOUT_URL;
        });
    }

    // Pasang Event Interseptor pada Seluruh Tombol Logout di Sistem
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('a[href*="logout.php"], .nav-logout').forEach(function(el) {
            el.addEventListener('click', function() {
                purgeClientStorage();
            });
        });
    });

    // Mulai Timer saat Halaman Selesai Dimuat
    resetInactivityTimer();
})();
</script>
