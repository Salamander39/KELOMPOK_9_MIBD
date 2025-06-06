<?php
session_start();
require_once 'config.php';

$noUnit = $_GET['noUnit'] ?? null;

if (!$noUnit) {
    die("No Unit parameter is missing.");
}

$sql_unit_details = "
    SELECT 
        k.noUnit,
        COALESCE(p.nama, 'Belum Ada Pemilik') AS namaPemilik,
        COALESCE(p.idPengguna, 'Tidak Ditetapkan') AS idPengguna
    FROM Kepemilikan k
    LEFT JOIN pemilik p ON k.idPengguna = p.idPengguna
    WHERE k.noUnit = ?
";
$stmt_unit_details = sqlsrv_query($conn, $sql_unit_details, [$noUnit]);
if ($stmt_unit_details === false) {
    die("Error fetching unit details: " . print_r(sqlsrv_errors(), true));
}
$unit_details = sqlsrv_fetch_array($stmt_unit_details, SQLSRV_FETCH_ASSOC);

if (!$unit_details) {
    die("Unit not found.");
}

$sql_water_usage = "
    SELECT
        CONVERT(VARCHAR(10), pa.waktu, 23) AS tanggal,
        SUM(pa.liter) AS totalLiter
    FROM PemakaianAir pa
    JOIN PerangkatIOT iot ON pa.serialNumSen = iot.serialNum
    WHERE iot.noUnit = ?
    GROUP BY CONVERT(VARCHAR(10), pa.waktu, 23)
    ORDER BY tanggal
";
$stmt_water_usage = sqlsrv_query($conn, $sql_water_usage, [$noUnit]);
if ($stmt_water_usage === false) {
    die("Error fetching water usage: " . print_r(sqlsrv_errors(), true));
}

$water_usages = [];
while ($row = sqlsrv_fetch_array($stmt_water_usage, SQLSRV_FETCH_ASSOC)) {
    $water_usages[] = $row;
}

$sql_iot_devices = "
    SELECT
        p.serialNum,
        p.tglPasang,
        CASE
            WHEN SUBSTRING(p.serialNum, 3, 1) = '2' THEN COALESCE(a.statusAkt, 'OFF')
            ELSE NULL
        END AS statusAkt
    FROM PerangkatIOT p
    LEFT JOIN Aktuator a ON p.serialNum = a.serialNumAkt
    WHERE p.noUnit = ?
    ORDER BY p.serialNum
";
$stmt_iot_devices = sqlsrv_query($conn, $sql_iot_devices, [$noUnit]);
if ($stmt_iot_devices === false) {
    die("Error fetching IoT devices: " . print_r(sqlsrv_errors(), true));
}

$iot_devices = [];
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
        'tglPasang' => $row['tglPasang'] ? $row['tglPasang']->format('Y-m-d') : 'N/A',
        'statusAkt' => $row['statusAkt']
    ];
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
        }
        .header h2 {
            margin: 0;
            color: #018A38;
            border-bottom: none; /* Remove duplicate border */
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
        .no-data {
            text-align: center;
            color: #777;
            padding: 20px;
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

        <div class="detail-item">
            <strong>Nomor Unit:</strong> <?= htmlspecialchars($unit_details['noUnit']) ?>
        </div>
        <div class="detail-item">
            <strong>ID Pengguna:</strong> <?= htmlspecialchars($unit_details['idPengguna']) ?>
        </div>
        <div class="detail-item">
            <strong>Nama Pemilik:</strong> <?= htmlspecialchars($unit_details['namaPemilik']) ?>
        </div>

        <h2>Data Pemakaian Air</h2>
        <?php if (empty($water_usages)): ?>
            <p class="no-data">Tidak ada data pemakaian air yang tersedia untuk unit ini.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Tanggal</th>
                    <th>Total Liter</th>
                </tr>
                <?php foreach ($water_usages as $usage): ?>
                    <tr>
                        <td><?= htmlspecialchars($usage['tanggal']) ?></td>
                        <td><?= htmlspecialchars(number_format($usage['totalLiter'], 2)) ?> L</td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <h2>Perangkat IoT Terdaftar</h2>
        <?php if (empty($iot_devices)): ?>
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