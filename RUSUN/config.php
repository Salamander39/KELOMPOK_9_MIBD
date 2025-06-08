<?php
// config.php
$serverName = "LODAYA";    
$connectionOptions = [
  "Database" => "RUSUNAMI",
  "Uid"      => "",
  "PWD"      => ""
];
$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
  die(print_r(sqlsrv_errors(), true));
}
?>