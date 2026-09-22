<?php
session_start();

/**
 * Fungsi untuk menghasilkan string CAPTCHA acak
 * @param int $length Panjang string
 * @return string String CAPTCHA
 */
function generate_captcha_string($length = 7) {
    $characters = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ'; // Karakter yang mudah dibaca (tanpa 0, 1, O, I, L)
    $characters_length = strlen($characters);
    $captcha_string = '';
    for ($i = 0; $i < $length; $i++) {
        $captcha_string .= $characters[rand(0, $characters_length - 1)];
    }
    return $captcha_string;
}

// Buat kode baru SETIAP KALI file ini dipanggil
$captcha_text = generate_captcha_string();

// Simpan kode baru itu ke session
$_SESSION['captcha_text'] = $captcha_text;

// Buat gambar lebih besar
$image_width = 180;
$image_height = 60;
$image = imagecreatetruecolor($image_width, $image_height);

// Atur warna
$bg_color = imagecolorallocate($image, 255, 255, 255); 
$text_color = imagecolorallocate($image, 46, 74, 182);      // Warna biru primary
$noise_color = imagecolorallocate($image, 220, 220, 220); 

// Isi background
imagefilledrectangle($image, 0, 0, $image_width, $image_height, $bg_color);

// Tambahkan noise (titik-titik acak)
for ($i = 0; $i < 1000; $i++) {
    imagesetpixel($image, rand(0, $image_width), rand(0, $image_height), $noise_color);
}

// Tambahkan noise (garis-garis acak)
for ($i = 0; $i < 4; $i++) {
    imageline($image, 
        rand(0, $image_width), rand(0, $image_height), 
        rand(0, $image_width), rand(0, $image_height), 
        $noise_color
    );
}

// Lokasi font
$font_path = 'libs/tfpdf/font/unifont/DejaVuSans-Bold.ttf';
$font_size = 14;

// Tambahkan teks CAPTCHA ke gambar dengan TrueType Font
// Kita gunakan sedikit rotasi acak untuk setiap karakter agar lebih aman tapi tetap terbaca
$x = 20;
$y = 35;
for ($i = 0; $i < strlen($captcha_text); $i++) {
    $angle = rand(-10, 10);
    imagettftext($image, $font_size, $angle, $x, $y, $text_color, $font_path, $captcha_text[$i]);
    $x += 20; // Jarak antar karakter
}

// Output gambar sebagai PNG
header('Content-Type: image/png');
imagepng($image);

// Hancurkan gambar dari memori
imagedestroy($image);
?>
