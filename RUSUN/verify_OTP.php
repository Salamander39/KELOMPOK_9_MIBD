<?php
// Pastikan tidak ada output sebelum session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'log_action.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['otp'])) {
    $submittedOtp = trim($_POST['otp']);
    $generatedOtp = isset($_SESSION['generated_otp']) ? trim($_SESSION['generated_otp']) : null;
    
    if ($generatedOtp !== null && $submittedOtp === $generatedOtp) {
        $userId = $_SESSION['temp_user_id_for_otp_verification'] ?? null;
        
        if ($userId) {
            // >>>>>>>>>>>>>> THIS IS THE CRITICAL FIX SECTION <<<<<<<<<<<<<<
            // These lines ensure the correct user ID is set and temporary variables are cleared
            $_SESSION['user_id'] = $userId;
            $_SESSION['is_logged_in'] = true;
            unset($_SESSION['temp_user_id_for_otp_verification']);
            unset($_SESSION['phone_for_otp_verification']);
            // >>>>>>>>>>>>>>>>>>>>>>>>>>>>>> END OF FIX <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<

            logAction($conn, $userId, 'Login berhasil', $generatedOtp);
            echo "MATCH|" . $userId;
        } else {
            echo "NO_MATCH|User ID not found in session";
        }
        
        // Bersihkan OTP dari session setelah digunakan
        unset($_SESSION['generated_otp']);
    } else {
        $userId = $_SESSION['temp_user_id_for_otp_verification'] ?? null;
        if ($userId) {
            logAction($conn, $userId, 'Login gagal (OTP salah)', $submittedOtp);
        }
        echo "NO_MATCH|OTP tidak sesuai";
    }
} else {
    echo "INVALID_REQUEST";
}

sqlsrv_close($conn);
?>