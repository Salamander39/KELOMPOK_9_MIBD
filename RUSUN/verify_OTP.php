<?php
session_start();
//if (session_status() !== PHP_SESSION_ACTIVE) {
   // session_regenerate_id(true);
//}

$serverName = "LODAYA";
$connectionOptions = [
    "Database" => "RUSUNAMI",
    "Uid" => "",
    "PWD" => ""
];

$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection error."]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['otp'])) {
    $enteredOtp = $_POST['otp'];

    $correctOtp = $_SESSION['generated_otp'];

    if (!isset($_SESSION['temp_user_id_for_otp_verification'])) {
        http_response_code(400);
        echo "SESSION_EXPIRED";
        exit;
    }
    
    $userId = $_SESSION['temp_user_id_for_otp_verification'];
    $phone = $_SESSION['phone_for_otp_verification'];
 
    if ($enteredOtp === $correctOtp) {
        $_SESSION['user_id'] = $userId;
        $_SESSION['is_logged_in'] = true;
        
        // Clear temporary data
        unset($_SESSION['temp_user_id_for_otp_verification']);
        unset($_SESSION['phone_for_otp_verification']);
        unset($_SESSION['generated_otp']); // Clear the OTP after successful verification

        echo "MATCH|" . $userId;
    } else {
        echo "NO_MATCH";
    }
} else {
    http_response_code(400);
    echo "Invalid request";
}
sqlsrv_close($conn);
?>