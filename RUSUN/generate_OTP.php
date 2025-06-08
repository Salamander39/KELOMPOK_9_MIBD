<?php
// Pastikan tidak ada output sebelum session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'log_action.php';

// Generate a 6-digit OTP
$otp = '';
for ($i = 0; $i < 6; $i++) {
    $otp .= random_int(0, 9);
}

// Store the OTP in session 
$_SESSION['generated_otp'] = $otp;

// Hanya log jika user ID tersedia
if (isset($_SESSION['temp_user_id_for_otp_verification'])) {
    @logAction($conn, $_SESSION['temp_user_id_for_otp_verification'], 'OTP dikirim', $otp);
}

// Set header sebagai plain text
header('Content-Type: text/plain');

// Hanya output OTP saja, tanpa tambahan apapun
echo $otp;
exit; // Pastikan tidak ada output lain setelahnya
?>