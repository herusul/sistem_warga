<?php
// Pastikan Anda MENGGANTI nilai-nilai ini dengan string acak yang kuat dan unik untuk proyek Anda.
// Jangan gunakan nilai default ini di lingkungan produksi.
define('ENCRYPTION_KEY', '%8956*^@!~?ini_4d4lah_untuk_k34am4nan_d4t4_2567817&%*%?!?_ok3y^^?-s3moga-s3lalu-d1berik4n-keberk4h4n');
define('ENCRYPTION_IV', substr(hash('sha256', '64LON-a1r_unTUK_m1nuM_j4n64n_lup4_SHOLAT-y4^_^MERDEK4!'), 0, 16));

/**
 * Mengenkripsi sebuah string (dalam kasus ini, ID).
 * @param string $string ID yang akan dienkripsi.
 * @return string ID yang sudah dienkripsi dan aman untuk URL.
 */
function encrypt_id($string) {
    $output = openssl_encrypt($string, 'aes-256-cbc', ENCRYPTION_KEY, 0, ENCRYPTION_IV);
    // base64_encode digunakan agar hasil enkripsi tidak mengandung karakter aneh yang bisa merusak URL.
    return urlencode(base64_encode($output));
}

/**
 * Mendekripsi sebuah string dari URL.
 * @param string $string ID terenkripsi dari URL.
 * @return string|false ID asli, atau false jika dekripsi gagal.
 */
function decrypt_id($string) {
    // urldecode dan base64_decode adalah kebalikan dari proses di fungsi encrypt_id.
    $string = base64_decode(urldecode($string));
    return openssl_decrypt($string, 'aes-256-cbc', ENCRYPTION_KEY, 0, ENCRYPTION_IV);
}
?>
