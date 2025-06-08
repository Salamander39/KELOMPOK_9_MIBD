<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');
// $userId = $_SESSION['user_id'] ?? 1;
// if (!$userId) {
//     http_response_code(401);
//     echo json_encode(['success'=>false,'error'=>'Unauthorized']);
//     exit;
// }

$userId = 1;

$input     = json_decode(file_get_contents('php://input'), true);
$serial    = $input['serial']  ?? '';
$newStatus = $input['status']  ?? '';
if (!$serial || !in_array($newStatus, ['ON','OFF'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Invalid input']);
    exit;
}

// 1) Coba UPDATE
$sqlUp = "UPDATE Aktuator
            SET statusAkt = ?
          WHERE serialNumAkt = ?";
$paramsUp = [$newStatus, $serial];
$stmtUp = sqlsrv_query($conn, $sqlUp, $paramsUp);
if ($stmtUp === false) {
    $err = sqlsrv_errors();
    echo json_encode(['success'=>false,'error'=>'Update failed','sql_error'=>$err]);
    exit;
}

// 2) Kalau tidak ada baris yang di-update, INSERT baru
$rowsAffected = sqlsrv_rows_affected($stmtUp);
if ($rowsAffected === 0) {
    $sqlIn = "INSERT INTO Aktuator (serialNumAkt, statusAkt)
              VALUES (?, ?)";
    $stmtIn = sqlsrv_query($conn, $sqlIn, [$serial, $newStatus]);
    if ($stmtIn === false) {
        $err = sqlsrv_errors();
        echo json_encode(['success'=>false,'error'=>'Insert failed','sql_error'=>$err]);
        exit;
    }
}

// 3) Catat LogAksi dengan serial number
$ket = ($newStatus === 'ON' ? 'buka aktuator ' : 'tutup aktuator ') . $serial;
$sqlLog = "INSERT INTO LogAksi (idPengguna, ketAksi) VALUES (?, ?)";
sqlsrv_query($conn, $sqlLog, [$userId, $ket]);

echo json_encode(['success'=>true,'status'=>$newStatus]);
