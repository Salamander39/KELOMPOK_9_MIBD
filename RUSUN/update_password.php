<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated. Please log in.', 'code' => 403]);
    http_response_code(403);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$oldPassword = $data['oldPassword'] ?? '';
$newPassword = $data['newPassword'] ?? '';

// Trim whitespace from input passwords just in case
$oldPassword = trim($oldPassword);
$newPassword = trim($newPassword);

if (empty($oldPassword) || empty($newPassword)) {
    echo json_encode(['status' => 'error', 'message' => 'Old and new passwords are required.']);
    exit();
}

if (strlen($newPassword) < 5) { // Server-side validation for password length
    echo json_encode(['status' => 'error', 'message' => 'New password must be at least 5 characters long.']);
    exit();
}

// Start a transaction for atomicity
sqlsrv_begin_transaction($conn);

try {
    // 1. Fetch current (plain-text) password from the database for the logged-in user
    $sql_fetch_password = "SELECT passRusun FROM Pemilik WHERE idPengguna = ?";
    $params_fetch_password = array($userId);
    $stmt_fetch_password = sqlsrv_query($conn, $sql_fetch_password, $params_fetch_password);

    if ($stmt_fetch_password === false) {
        throw new Exception("SQL fetch error: " . print_r(sqlsrv_errors(), true));
    }

    $user = sqlsrv_fetch_array($stmt_fetch_password, SQLSRV_FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found in the database or invalid user ID.');
    }

    $storedPasswordFromDB = $user['passRusun']; // Ini adalah password plain text dari DB Anda saat ini-

    // --- VERIFIKASI OLD PASSWORD (UNTUK PROTOTIPE PLAIN TEXT) ---
    // PERINGATAN KERAS: JANGAN PERNAH LAKUKAN INI DI LINGKUNGAN PRODUKSI!
    if ($oldPassword !== $storedPasswordFromDB) {
        sqlsrv_rollback($conn); // Rollback jika old password tidak cocok
        echo json_encode(['status' => 'error', 'message' => 'Password lama salah. (Perbandingan langsung gagal)']);
        exit();
    }
    // --- AKHIR VERIFIKASI PROTOTIPE ---


    // --- PENYIMPANAN NEW PASSWORD (UNTUK PROTOTIPE PLAIN TEXT) ---
    // PERINGATAN KERAS: JANGAN PERNAH LAKUKAN INI DI LINGKUNGAN PRODUKSI!
    $newPasswordToStore = $newPassword; // Simpan password baru sebagai plain text
    // --- AKHIR PENYIMPANAN PROTOTIPE ---


    // 4. Update password di database
    $sql_update_password = "UPDATE Pemilik SET passRusun = ? WHERE idPengguna = ?";
    $params_update_password = array($newPasswordToStore, $userId); // Gunakan $newPasswordToStore (yang sekarang plain text)
    $stmt_update_password = sqlsrv_query($conn, $sql_update_password, $params_update_password);

    if ($stmt_update_password === false) {
        // Jika ada kesalahan di sini, mungkin kolom passRusun masih terlalu kecil
        // bahkan untuk plain text yang panjang, atau ada masalah lain.
        throw new Exception("SQL update error: " . print_r(sqlsrv_errors(), true));
    }

    sqlsrv_commit($conn); // Commit transaksi
    echo json_encode(['status' => 'success', 'message' => 'Password berhasil diubah.']);

} catch (Exception $e) {
    sqlsrv_rollback($conn); // Rollback jika ada error
    error_log("Password update error for user ID {$userId}: " . $e->getMessage()); // Log error untuk debugging
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan saat mengubah password: ' . $e->getMessage()]);
}

sqlsrv_close($conn); // Tutup koneksi
?>