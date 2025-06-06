<?php
session_start();
require_once 'config.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    die("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $noUnit = $_POST['noUnit'] ?? '';

    if (empty($noUnit)) {
        $_SESSION['message'] = '<p style="color: red;">Nomor Unit tidak valid untuk dihapus.</p>';
        header('Location: edit_prusun.php');
        exit();
    }

    $sql = "DELETE FROM Kepemilikan WHERE noUnit = ?";
    $params = array($noUnit);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['message'] = '<p style="color: red;">Gagal menghapus unit: ' . print_r(sqlsrv_errors(), true) . '</p>';
    } else {
        if (sqlsrv_rows_affected($stmt) > 0) {
            $_SESSION['message'] = '<p style="color: green;">Unit ' . htmlspecialchars($noUnit) . ' berhasil dihapus.</p>';
        } else {
            $_SESSION['message'] = '<p style="color: orange;">Unit ' . htmlspecialchars($noUnit) . ' tidak ditemukan atau sudah dihapus.</p>';
        }
    }
    header('Location: edit_prusun.php');
    exit();

} else {
    header('Location: edit_prusun.php');
    exit();
}
?>