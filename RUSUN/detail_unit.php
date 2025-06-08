<?php
session_start();
require_once 'config.php'; 

$userId = $_SESSION['user_id'] ?? null;

// Redirect to login if user is not logged in. 
if (!$userId) {
    header('Location: login.php');
    exit();
}

$noUnit = $_GET['noUnit'] ?? null;

if (!$noUnit) {
    die("Error: Nomor Unit parameter is missing.");
}

// Uses COALESCE to display default strings if idPengguna or nama is NULL in 'pemilik'
$sql_unit_details = "
    SELECT
        k.noUnit,
        COALESCE(p.nama, 'Belum Ada Pemilik') AS namaPemilik,
        COALESCE(CAST(p.idPengguna AS VARCHAR(50)), 'Tidak Ditetapkan') AS idPenggunaPemilik -- Cast to VARCHAR to avoid type issues if p.idPengguna is INT NULL
        FROM Kepemilikan k
        LEFT JOIN pemilik p 
        ON k.idPengguna = p.idPengguna
        WHERE k.noUnit = ?
";
$stmt_unit_details = sqlsrv_query($conn, $sql_unit_details, [$noUnit]);

if ($stmt_unit_details === false) {
    // Log the detailed error for debugging purposes
    error_log("Error fetching unit details for unit " . $noUnit . ": " . print_r(sqlsrv_errors(), true));
    die("Terjadi kesalahan saat mengambil detail unit. Silakan coba lagi nanti.");
}

$unit_details = sqlsrv_fetch_array($stmt_unit_details, SQLSRV_FETCH_ASSOC);

if (!$unit_details) {
    die("Error: Unit tidak ditemukan.");
}

// Variables to hold error messages for display
$iot_devices_error_message = ''; // Only IoT devices error message is needed now

// Fetch IoT devices data
$iot_devices = [];
$sql_iot_devices = "
    SELECT
        p.serialNum,
        p.tglPasang,
        CASE
            WHEN SUBSTRING(p.serialNum, 3, 1) = '2' 
            THEN COALESCE(a.statusAkt, 'OFF') -- Aktuator
            ELSE NULL -- Sensor 
        END AS statusAkt
    FROM PerangkatIOT p
    LEFT JOIN Aktuator a ON p.serialNum = a.serialNumAkt
    WHERE p.noUnit = ?
    ORDER BY p.serialNum
";
$stmt_iot_devices = sqlsrv_query($conn, $sql_iot_devices, [$noUnit]);

if ($stmt_iot_devices === false) {
    error_log("Error fetching IoT devices for unit " . $noUnit . ": " . print_r(sqlsrv_errors(), true));
    $iot_devices_error_message = "Terjadi kesalahan saat memuat data perangkat IoT.";
} else {
    while ($row = sqlsrv_fetch_array($stmt_iot_devices, SQLSRV_FETCH_ASSOC)) {
        $deviceTypeChar = substr($row['serialNum'], 2, 1);
        $deviceType = '';
        if ($deviceTypeChar === '1') {
            $deviceType = 'Sensor';
        } elseif ($deviceTypeChar === '2') {
            $deviceType = 'Aktuator';
        } else {
            $deviceType = 'Unknown';
        }

        $iot_devices[] = [
            'serialNum' => $row['serialNum'],
            'type' => $deviceType,
            // Format tglPasang 
            'tglPasang' => $row['tglPasang'] instanceof DateTime ? $row['tglPasang']->format('Y-m-d') : 'N/A',
            'statusAkt' => $row['statusAkt'] // Will be NULL for sensors, 'OFF' or actual status for actuators
        ];
    }
}

// Check for and display session messages (e.g., from a delete operation)
$session_message = '';
if (isset($_SESSION['message'])) {
    $session_message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear the message after displaying it
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Detail Unit: <?= htmlspecialchars($noUnit) ?></title>
    <link href="https://fonts.googleapis.com/css?family=Offside&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Offside', cursive;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 900px;
            margin: 20px auto;
            background: #fff;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        /* Styles for the header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #018A38;
        }
        .header h1 {
            margin: 0;
            color: #018A38;
            font-size: 2em; 
        }
        .header h2 {
            margin: 0;
            color: #018A38;
            border-bottom: none;
            padding-bottom: 0;
        }
        .detail-item {
            margin-bottom: 10px;
        }
        .detail-item strong {
            display: inline-block;
            width: 150px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #018A38;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #ddd;
        }
        .no-data, .error-message {
            text-align: center;
            padding: 20px;
            font-style: italic;
        }
        .no-data {
            color: #777;
        }
        .error-message {
            color: #d8000c;
            background-color: #ffbaba;
            border: 1px solid #d8000c;
            border-radius: 5px;
            margin-top: 10px;
        }
        /* Styles for the image back button */
        .back-button-img {
            background: none;
            border: none;
            padding: 4px;
            cursor: pointer;
            transition: transform 0.2s ease, filter 0.2s ease;
        }
        .back-button-img img {
            width: 40px;
            height: 40px;
        }
        .back-button-img:hover {
            transform: scale(1.2);
            filter: brightness(1.2);
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Detail Unit: <?= htmlspecialchars($unit_details['noUnit']) ?></h1>
            <button class="back-button-img" title="Kembali">
                <img src="images/back2.png" alt="Kembali" />
            </button>
        </div>

        <?php if (!empty($session_message)): ?>
            <div class="message success"><?= htmlspecialchars($session_message) ?></div>
        <?php endif; ?>

        <h2>Informasi Umum Unit</h2>
        <div class="detail-item">
            <strong>Nomor Unit:</strong> <?= htmlspecialchars($unit_details['noUnit']) ?>
        </div>
        <div class="detail-item">
            <strong>ID Pengguna:</strong> <?= htmlspecialchars($unit_details['idPenggunaPemilik']) ?>
        </div>
        <div class="detail-item">
            <strong>Nama Pemilik:</strong> <?= htmlspecialchars($unit_details['namaPemilik']) ?>
        </div>

        <h2>Perangkat IoT Terdaftar</h2>
        <?php if (!empty($iot_devices_error_message)): ?>
            <p class="error-message"><?= htmlspecialchars($iot_devices_error_message) ?></p>
        <?php elseif (empty($iot_devices)): ?>
            <p class="no-data">Tidak ada perangkat IoT yang terdaftar untuk unit ini.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Serial Number</th>
                    <th>Jenis Perangkat</th>
                    <th>Tanggal Pasang</th>
                    <th>Status (Aktuator)</th>
                </tr>
                <?php foreach ($iot_devices as $device): ?>
                    <tr>
                        <td><?= htmlspecialchars($device['serialNum']) ?></td>
                        <td><?= htmlspecialchars($device['type']) ?></td>
                        <td><?= htmlspecialchars($device['tglPasang']) ?></td>
                        <td><?= htmlspecialchars($device['statusAkt'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const backButton = document.querySelector('.back-button-img');
            if (backButton) {
                backButton.addEventListener('click', function() {
                    window.history.back(); 
                });
            }
        });
    </script>
</body>
</html>