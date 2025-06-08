<?php
session_start();
require_once 'config.php'; // Ensure your database configuration is correct here

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['userRole'] ?? null;

// Initialize a response array with default values
$response = ['success' => false, 'serialNumbers' => [], 'error' => ''];

// Basic security check: ensure an admin is logged in, or adjust as needed for AJAX.
// For a live environment, consider more robust AJAX security.
if (!$userId || $userRole !== 'Admin') {
    $response['error'] = 'Unauthorized access.';
    echo json_encode($response);
    exit();
}

$noUnit = $_GET['unit'] ?? '';

if (!empty($noUnit)) {
    // Fetch serial numbers from PerangkatIOT table for the given unit
    $sql = "SELECT serialNum 
            FROM PerangkatIOT 
            WHERE noUnit = ? 
            AND serialNum LIKE 'SN1%'
            ORDER BY serialNum 
            ASC";
    $params = array($noUnit);

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        // Log the error for debugging, but send a generic message to the client
        error_log("Error fetching serial numbers for unit {$noUnit}: " . print_r(sqlsrv_errors(), true));
        $response['error'] = 'Database error occurred.';
        echo json_encode($response);
        exit();
    }

    $serialNumbers = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $serialNumbers[] = $row['serialNum'];
    }

    // Set success to true and populate serialNumbers for a valid response
    $response['success'] = true;
    $response['serialNumbers'] = $serialNumbers;
} else {
    // If noUnit is empty, return an error
    $response['error'] = 'Unit parameter is missing.';
}

echo json_encode($response);
