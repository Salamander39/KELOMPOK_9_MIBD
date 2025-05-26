<?php
session_start();

// Generate a 6-digit OTP
$digit1 = random_int(0,9);
$digit2 = random_int(0,9);
$digit3 = random_int(0,9);
$digit4 = random_int(0,9);
$digit5 = random_int(0,9);
$digit6 = random_int(0,9);
$otp = $digit1 . $digit2 . $digit3 . $digit4 . $digit5 . $digit6;

// Store the OTP in session 
$_SESSION['generated_otp'] = $otp;

// Return the OTP 
echo $otp;
?>