<?php
// BARIS PERTAMA FILE, tidak boleh ada spasi/enter sebelum <?php
declare(strict_types=1);

// Start session dengan pengaturan yang lebih ketat
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'read_and_close'  => false,
        'cookie_secure'   => false,    // Sesuaikan dengan HTTPS jika perlu
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

require_once 'config.php';
require_once 'log_action.php';

// Log semua request untuk debugging
error_log('[LOG_LOGOUT] Request received: ' . date('Y-m-d H:i:s'));
error_log('[LOG_LOGOUT] POST data: ' . print_r($_POST, true));
error_log('[LOG_LOGOUT] SESSION data: ' . print_r($_SESSION, true));

// Validasi request
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['userId'])) {
    http_response_code(400);
    error_log('[LOG_LOGOUT] Invalid request method or missing userId');
    die("INVALID_REQUEST");
}

$userId = (int)$_POST['userId']; // Pastikan integer

// Validasi user ID
if ($userId <= 0) {
    http_response_code(400);
    error_log('[LOG_LOGOUT] Invalid user ID: ' . $userId);
    die("INVALID_USER_ID");
}

// Log aksi logout
try {
    $sql = "INSERT INTO LogAksi (idPengguna, ketAksi) VALUES (?, ?)";
    $params = [$userId, 'Logout'];
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        error_log('[LOG_LOGOUT] Database error: ' . print_r($errors, true));
        http_response_code(500);
        die("DATABASE_ERROR");
    }
    
    sqlsrv_free_stmt($stmt);
    
    error_log('[LOG_LOGOUT] Successfully logged logout for user: ' . $userId);
    echo "SUCCESS";
    
} catch (Exception $e) {
    error_log('[LOG_LOGOUT] Exception: ' . $e->getMessage());
    http_response_code(500);
    die("EXCEPTION_OCCURRED");
}
?>