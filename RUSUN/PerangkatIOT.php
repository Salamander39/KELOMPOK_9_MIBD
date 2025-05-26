<?php
session_start();
require_once 'config.php';

// Ganti ini dengan session user ID yang sebenarnya
$userId = $_SESSION['user_id'] ?? 1;
if (!$userId) die("Unauthorized");

// Ambil unit dari query-string
$unit = $_GET['unit'] ?? '';
if (!$unit) die("Unit not specified");

// Filter hanya perangkat di unit ini
$sql = "
  SELECT p.serialNum, p.tglPasang
    FROM PerangkatIOT p
    JOIN Kepemilikan k 
      ON p.noUnit = k.noUnit
   WHERE k.idPengguna = ? 
     AND p.noUnit    = ?
   ORDER BY p.serialNum
";
$params = [$userId, $unit];
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$devices = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $devices[] = [
      'serialNum' => $row['serialNum'],
      'tglPasang' => $row['tglPasang']->format('Y-m-d') // format jadi string
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Daftar Perangkat IoT</title>
  <link href="https://fonts.googleapis.com/css?family=Offside&display=swap" rel="stylesheet">
  <style>
    body {
      margin: 0; padding: 0;
      font-family: 'Offside', cursive;
      display: flex; height: 100vh;
      background-color: #e0e0e0;
    }
    .sidebar {
      width: 35%; background: #fff;
      box-shadow: 2px 0 8px rgba(0,0,0,0.2);
      overflow-y: auto;
    }
    .sidebar-header {
      display: flex; align-items: center;
      justify-content: space-between;
      background-color: #018A38; padding: 12px 16px;
      color: #fff;
    }
    .sidebar-header h2 { margin:0; font-size:1.2rem; }
    .exit-button {
      background:transparent; border:none; padding:4px;
      cursor:pointer; transition:0.2s;
    }
    .exit-button img { width:32px; height:32px; }
    .exit-button:hover {
      transform: scale(1.2); filter: brightness(1.2);
    }
    table { width:100%; border-collapse: collapse; }
    th, td {
      padding:12px; text-align:center;
      border-bottom:1px solid #eee;
      cursor: pointer;
    }
    tr:hover { background:#f0f8ff; }
    tr.selected { background:#add8e6; }
    /* Panel kanan */
    .info-box {
      flex:1; padding:24px; margin:16px;
      background:#fff; border-radius:8px;
      box-shadow:0 0 8px rgba(0,0,0,0.1);
    }
    .info-box h2 { margin-top:0; }
    .device-detail {
      margin-top:16px; padding:12px;
      border:1px dashed #999; border-radius:4px;
      background:#fafafa; line-height:1.5;
    }
  </style>
</head>
<body>

  <div class="sidebar">
    <div class="sidebar-header">
      <h2>Perangkat IoT</h2>
      <button id="back-btn" class="exit-button" title="Tambah Perangkat">
        <img src="images/back2.png" alt="Tambah">
      </button>
    </div>
    <table id="deviceTable">
      <tr><th>Serial Number</th></tr>
      <?php foreach ($devices as $d): ?>
      <tr data-serial="<?= htmlspecialchars($d['serialNum']) ?>"
          data-tgl="<?= htmlspecialchars($d['tglPasang']) ?>">
        <td><?= htmlspecialchars($d['serialNum']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="info-box" id="infoBox">
    <h2>Detail Perangkat</h2>
    <p>Klik salah satu perangkat di kiri untuk melihat info.</p>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const rows   = document.querySelectorAll('#deviceTable tr[data-serial]');
      const info   = document.getElementById('infoBox');
      const btnAdd = document.getElementById('btnAddDevice');

      rows.forEach(row => {
        row.addEventListener('click', function() {
          rows.forEach(r => r.classList.remove('selected'));
          this.classList.add('selected');

          const serial = this.dataset.serial;
          const tgl     = this.dataset.tgl;
          // karakter ke-3 (index 2)
          const typeChar = serial.charAt(2);
          const tipe = typeChar === '1'
                     ? 'Sensor'
                     : typeChar === '2'
                       ? 'Aktuator'
                       : 'Unknown';

          info.innerHTML = `
            <h2>Detail Perangkat ${serial}</h2>
            <div class="device-detail">
              <strong>Serial Number:</strong> ${serial}<br>
              <strong>Tanggal Pasang:</strong> ${tgl}<br>
              <strong>Tipe:</strong> ${tipe}
            </div>`;
        });
      });

      
    });
  </script><script src="common.js"></script>

</body>
</html>
