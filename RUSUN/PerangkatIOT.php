<?php
session_start();
require_once 'config.php';

$userId = $_SESSION['user_id'] ?? null;
$unit = $_GET['unit'] ?? null;
$userRole = $_SESSION['userRole'] ?? null;

if ($unit === null) {
    die("Unit parameter is missing");
}

// Modified query without inline comments
$sql = "
  SELECT
    p.serialNum,
    p.tglPasang,
    CASE 
      WHEN SUBSTRING(p.serialNum, 3, 1) = '2' THEN COALESCE(a.statusAkt, 'OFF')
      ELSE NULL
    END AS statusAkt,
    k.idPengguna as ownerId
  FROM PerangkatIOT p
  JOIN Kepemilikan k ON p.noUnit = k.noUnit
  LEFT JOIN Aktuator a ON p.serialNum = a.serialNumAkt
  WHERE p.noUnit = ?
  " . ($userRole !== 'Admin' ? " AND k.idPengguna = ?" : "") . "
  ORDER BY p.serialNum
";

$params = [$unit];
if ($userRole !== 'Admin') {
    $params[] = $userId;
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$devices = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $devices[] = [
      'serialNum' => $r['serialNum'],
      'tglPasang' => $r['tglPasang']->format('Y-m-d'),
      'statusAkt' => $r['statusAkt'],
      'isOwner' => ($userRole === 'Admin' || $r['ownerId'] == $userId) // Add ownership flag
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
      <button id="back-btn" class="exit-button" title="Kembali">
        <img src="images/back2.png" alt="Back">
      </button>
    </div>
    
    <table id="deviceTable">
      <tr><th>Serial Number</th><th>Status</th></tr>
      <?php foreach ($devices as $d): ?>
      <tr
      data-serial="<?= htmlspecialchars($d['serialNum']) ?>"
      data-tgl="<?= htmlspecialchars($d['tglPasang']) ?>"
      data-statusakt="<?= htmlspecialchars($d['statusAkt'] ?? '') ?>"
      data-owner="<?= $d['isOwner'] ? 'true' : 'false' ?>">
      <td><?= htmlspecialchars($d['serialNum']) ?></td>
      <td><?= $d['statusAkt'] ? htmlspecialchars($d['statusAkt']) : '-' ?></td>
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
    const backBtn = document.getElementById('back-btn');
    const rows = document.querySelectorAll('#deviceTable tr[data-serial]');
    const info = document.getElementById('infoBox');
    let currentRow = null;

    backBtn.addEventListener('click', () => {
        window.location.href = './listUnit.php';
    });

    rows.forEach(row => {
        row.addEventListener('click', () => {
            rows.forEach(r => r.classList.remove('selected'));
            row.classList.add('selected');
            currentRow = row;

            const serial = row.dataset.serial;
            const tgl = row.dataset.tgl;
            const status = row.dataset.statusakt;
            const isOwner = row.dataset.owner === 'true';
            const typeChar = serial.charAt(2);
            const tipe = typeChar === '1' ? 'Sensor' 
                         : typeChar === '2' ? 'Aktuator' 
                         : 'Unknown';

            let html = `
                <h2>Detail ${tipe} ${serial}</h2>
                <div class="device-detail">
                    <strong>Serial Number:</strong> ${serial}<br>
                    <strong>Tanggal Pasang:</strong> ${tgl}<br>
            `;

            if (typeChar === '2') {
                html += `
                    <strong>Status:</strong>
                    <span id="statusSpan">${status || 'OFF'}</span><br>
                `;
                
                if (isOwner) {
                    html += `
                        <button id="toggleBtn" style="margin-top:12px;padding:8px 16px;">
                            ${status === 'ON' ? 'Turn OFF' : 'Turn ON'}
                        </button>
                    `;
                } else if (userRole === 'Admin') {
                    html += `<p><em>(Read-only - Admin view)</em></p>`;
                }
            } else if (typeChar === '1') {
                html += `<strong>Tipe:</strong> Sensor (Tidak memiliki kontrol ON/OFF)<br>`;
            }
            
            html += `</div>`;
            info.innerHTML = html;

            if (typeChar === '2' && isOwner) {
                document.getElementById('toggleBtn').addEventListener('click', toggleStatus);
            }
        });
    });

    async function toggleStatus() {
        if (!currentRow || currentRow.dataset.owner !== 'true') {
            alert("You don't have permission to control this device");
            return;
        }
        
        const serial = currentRow.dataset.serial;
        const oldSt = currentRow.dataset.statusakt;
        const newSt = oldSt === 'ON' ? 'OFF' : 'ON';

        try {
            const res = await fetch('update_status.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ serial, status: newSt })
            });
            const data = await res.json();
            
            if (!data.success) {
                alert('Gagal: ' + (data.error || JSON.stringify(data)));
                return;
            }
            
            currentRow.dataset.statusakt = newSt;
            currentRow.cells[1].textContent = newSt;
            document.getElementById('statusSpan').textContent = newSt;
            document.getElementById('toggleBtn').textContent = 
                newSt === 'ON' ? 'Turn OFF' : 'Turn ON';
        } catch (e) {
            console.error(e);
            alert('Kesalahan jaringan');
        }
    }
});
  </script><script src="./common.js"></script>
</body>
</html>