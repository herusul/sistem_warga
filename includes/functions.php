<?php
/**
 * Escape output untuk mencegah XSS.
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token jika belum ada.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Validasi CSRF token.
 */
function verify_csrf_token() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }
}

/**
 * Menghasilkan input hidden untuk CSRF token.
 */
function csrf_input() {
    generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

/**
 * Badge status/hasil peminjaman aset.
 * Mendukung rincian pengembalian sebagian (jml_normal/rusak/hilang);
 * fallback ke kolom `hasil` untuk data lama tanpa rincian.
 */
function badge_hasil_pinjam($r) {
    if ((isset($r['is_aktif']) ? (int)$r['is_aktif'] : 0) === 1) {
        return '<span class="badge bg-info">Dipinjam</span>';
    }
    $jn = (int)($r['jml_normal'] ?? 0);
    $jr = (int)($r['jml_rusak'] ?? 0);
    $jh = (int)($r['jml_hilang'] ?? 0);
    if ($jn + $jr + $jh > 0) {
        $parts = [];
        if ($jn > 0) $parts[] = $jn . ' baik';
        if ($jr > 0) $parts[] = $jr . ' rusak';
        if ($jh > 0) $parts[] = $jh . ' hilang';
        $cls = $jh > 0 ? 'bg-danger' : ($jr > 0 ? 'bg-warning text-dark' : 'bg-success');
        return '<span class="badge ' . $cls . '">Kembali: ' . e(implode(' + ', $parts)) . '</span>';
    }
    return match($r['hasil'] ?? '') {
        'normal' => '<span class="badge bg-success">Kembali Normal</span>',
        'rusak' => '<span class="badge bg-warning text-dark">Kembali Rusak</span>',
        'hilang' => '<span class="badge bg-danger">Hilang</span>',
        default => '<span class="badge bg-secondary">Selesai</span>'
    };
}
?>
