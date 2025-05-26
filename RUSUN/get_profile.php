<?php
session_start(); // Memulai sesi PHP untuk mengakses data sesi
error_reporting(E_ALL);
ini_set('display_errors', 1);

$serverName = "LODAYA";    // sesuaikan
$connectionOptions = [
  "Database" => "RUSUNAMI",
  "Uid"      => "",
  "PWD"      => ""
];
$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
  die(print_r(sqlsrv_errors(), true));
}

// Pastikan pengguna sudah login dan idPengguna tersimpan di sesi
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(["error" => "User not authenticated."]);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Kueri SQL untuk mengambil data profil pengguna berdasarkan idPengguna
    // **SESUAIKAN NAMA TABEL DAN KOLOM DENGAN STRUKTUR DATABASE ANDA**
    $sql = "SELECT nama, NIK, noPonsel , alamat, idPengguna, passRusun
            FROM Pemilik 
            WHERE idPengguna = ?";

    $stmt = sqlsrv_query($conn, $sql, [$userId]);

    if ($stmt === false) {
        throw new Exception("Error executing query: " . print_r(sqlsrv_errors(), true));
    }

    if (sqlsrv_has_rows($stmt)) {
        $profileData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        echo json_encode(["status" => "success", "data" => $profileData]);
    } else {
        echo json_encode(["status" => "error", "message" => "Profile not found for this user."]);
    }

    sqlsrv_free_stmt($stmt);
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
} finally {
    sqlsrv_close($conn);
}
?>