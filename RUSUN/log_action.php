<?php
// BARIS PERTAMA FILE
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'use_strict_mode' => true
    ]);
}

/**
 * Fungsi untuk logging aksi
 * @param resource $conn Koneksi database
 * @param int $userId ID Pengguna
 * @param string $actionDesc Deskripsi aksi
 * @param string|null $otpCode Kode OTP (opsional)
 * @return bool True jika berhasil, false jika gagal
 */
function logAction($conn, int $userId, string $actionDesc, ?string $otpCode = null): bool {
    // Validasi parameter
    if ($userId <= 0) {
        error_log('[LOG_ACTION] Invalid user ID: ' . $userId);
        return false;
    }
    
    // Persiapan query
    $sql = "INSERT INTO LogAksi (idPengguna, ketAksi, kodeOTP) VALUES (?, ?, ?)";
    $params = [
        $userId,
        substr($actionDesc, 0, 50), // Pastikan tidak melebihi 50 karakter
        $otpCode !== null ? substr($otpCode, 0, 6) : null
    ];
    
    // Eksekusi query
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log('[LOG_ACTION] Query error: ' . print_r(sqlsrv_errors(), true));
        return false;
    }
    
    sqlsrv_free_stmt($stmt);
    return true;
}
?>