<?php
session_start();
require_once 'config.php';


$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    die("Unauthorized: User ID not found in session. Please ensure you are logged in.");
}

$sql = "
    SELECT noUnit
    FROM Kepemilikan 
    WHERE idPengguna = ?
    ORDER BY noUnit
";

$params = [$userId];
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    // --- DEBUGGING OUTPUT START ---
    error_log("listUnit.php: SQL Query Error: " . print_r(sqlsrv_errors(), true));
    // --- DEBUGGING OUTPUT END ---
    die("Database query failed: " . print_r(sqlsrv_errors(), true));
}

$units = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $units[] = $row['noUnit'];
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Daftar Unit Sarusun</title>
  <link href="https://fonts.googleapis.com/css?family=Offside&display=swap" rel="stylesheet">
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: 'Offside', cursive;
      display: flex;
      height: 100vh;
      background-color:rgb(208, 208, 208);
    }
    .sidebar {
      width: 35%;
      background: #fff;
      box-shadow: 2px 0 8px rgba(0, 0, 0, 0.38);
      overflow-y: auto;
    }
    .sidebar h2 {
      text-align: center;
      padding: 16px 0;
      margin: 0;
      background:rgb(1, 138, 56);
      color: #fff;
    }
    table {
      width: 100%;
      border-collapse: collapse;
    }
    th, td {
      padding: 12px;
      border-bottom: 1px solid #eee;
      text-align: center;
    }
    tr:hover {
      background-color: #f0f8ff;
      cursor: pointer;
    }
    tr.selected {
      background-color: #add8e6;
    }
    .info-box {
      flex: 1;
      padding: 24px;
      background: #fff;
      margin: 16px;
      border-radius: 8px;
      box-shadow: 0 0 8px rgba(0,0,0,0.1);
    }
    .info-box h2 {
      margin-top: 0;
      color: #333;
    }
    .unit-detail {
      margin-top: 16px;
      padding: 12px;
      border: 1px dashed #ccc;
      border-radius: 4px;
      background: #fafafa;
      line-height: 1.5;
    }
    .info-box .img-button {
    width: 100px;
    height: 100px;
    cursor: pointer;
    transition: transform 0.2s ease, filter 0.2s ease;
    margin-top: 20px;
    }
    .info-box .img-button:hover {
    transform: scale(1.2);
    filter: brightness(1.3);
    }

    .sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background-color:rgb(1, 138, 56);
    padding: 12px 16px;
    color: #fff;
    }

    .sidebar-header h2 {
    margin: 0;
    font-size: 1.2rem;
    }

    .exit-button {
    background: transparent;
    border: none;
    padding: 4px;
    cursor: pointer;
    transition: transform 0.2s ease, filter 0.2s ease;
    }

    .exit-button img {
    width: 40px;
    height: 40px;
    }

    .exit-button:hover {
    transform: scale(1.2);
    filter: brightness(1.2);
    }

  </style>
</head>
<body>

  <div class="sidebar">
  <div class="sidebar-header">
    <h2>Unit Anda</h2>
    <button class="exit-button" title="Homepage">
      <img src="images/back2.png" alt="+" />
    </button>
  </div>

  <table id="unitTable">
    <tr><th>No Unit</th></tr>
    <?php foreach ($units as $unit): ?>
    <tr data-unit="<?= htmlspecialchars($unit) ?>">
      <td><?= htmlspecialchars($unit) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

  <div class="info-box" id="infoBox">
    <h2>Detail Unit</h2>
    <p>Klik salah satu unit di kiri untuk melihat detailnya di sini.</p>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const rows    = document.querySelectorAll('#unitTable tr[data-unit]');
      const infoBox = document.getElementById('infoBox');
      
      rows.forEach(row => {
        row.addEventListener('click', function() {
          rows.forEach(r => r.classList.remove('selected'));
          this.classList.add('selected');

          const unit   = this.dataset.unit;
          const tower  = unit.charAt(0);
          const lantai = parseInt(unit.substr(1,2), 10);
          const nomor  = parseInt(unit.substr(3,2), 10);

          infoBox.innerHTML = `
            <h2>Detail Unit ${unit}</h2>
            <div class="unit-detail">
              <strong>Tower:</strong> ${tower}<br>
              <strong>Lantai:</strong> ${lantai}<br>
              <strong>Nomor Urut:</strong> ${nomor}
            </div>
            <img src="images/Droplet.png" id="btnAir" class="img-button" alt="Pemakaian Air" />
            <img src="images/PerangkatIOT.png" id="btnPerangkat" class="img-button" alt="Perangkat IoT" />
          `;

          document.getElementById('btnAir').addEventListener('click', () => {
            window.location.href = 'pemakaianAir.php?unit=' + encodeURIComponent(unit);
          });
          document.getElementById('btnPerangkat').addEventListener('click', () => {
            window.location.href = 'perangkatIOT.php?unit=' + encodeURIComponent(unit);
          });
        });
      });

      const exitButton = document.querySelector('.exit-button');
      if (exitButton) {
        exitButton.addEventListener('click', function() {
          window.location.href = 'home.admin.html'; 
        });
      }
    });
  </script> <script src="./common.js"></script>

</body>
</html>