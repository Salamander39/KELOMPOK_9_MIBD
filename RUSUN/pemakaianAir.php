<?php
session_start();
require_once 'config.php';

// Get the unit parameter from URL
$unit = $_GET['unit'] ?? null;
if (!$unit) {
    die("Unit parameter is required");
}

// Ambil data harian pemakaian liter untuk unit tertentu (sudah dikelompokkan per hari)
$sql = "
    SELECT
        CONVERT(VARCHAR(10), pa.waktu, 23) AS tanggal,
        SUM(pa.liter) AS totalLiter
    FROM PemakaianAir pa
    JOIN PerangkatIOT iot ON pa.serialNumSen = iot.SerialNum
    WHERE iot.noUnit = ?
    GROUP BY CONVERT(VARCHAR(10), pa.waktu, 23)
    ORDER BY tanggal
";
$stmt = sqlsrv_query($conn, $sql, [$unit]);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$labels = [];
$values = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $labels[] = $row['tanggal'];
    $values[] = (float)$row['totalLiter'];
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Grafik Pemakaian Air Unit <?= htmlspecialchars($unit) ?></title>
  <link href="https://fonts.googleapis.com/css?family=Offside&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Offside', cursive;
      background: #f5f5f5;
      margin: 0;
      padding: 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    h2 {
      color: #013C2B;
      text-align: center;
      margin-bottom: 30px;
    }
    #chartContainer {
      width: 90%;
      max-width: 1000px;
      background: #fff;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    canvas {
      width: 100% !important;
      height: 400px !important;
    }
    .info-box {
      background: #e8f5e8;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      border-left: 4px solid #018A38;
    }
    .total-info {
      text-align: center;
      margin-top: 20px;
      font-weight: bold;
      color: #013C2B;
    }
    .back-button {
      position: absolute;
      top: 20px;
      left: 20px;
      background: #018A38;
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 4px;
      cursor: pointer;
      font-family: 'Offside', cursive;
    }
  </style>
</head>
<body>
  <button class="back-button" onclick="window.history.back()">Kembali</button>
  
  <h2>Grafik Pemakaian Air Unit <?= htmlspecialchars($unit) ?></h2>
  
  <?php if (count($labels) > 0): ?>
  <div class="info-box">
    <strong>Unit:</strong> <?= htmlspecialchars($unit) ?><br>
    <strong>Total Hari dengan Data:</strong> <?= count($labels) ?> hari<br>
    <strong>Total Pemakaian:</strong> <?= number_format(array_sum($values), 2) ?> liter<br>
    <strong>Rata-rata per Hari:</strong> <?= number_format(array_sum($values) / count($values), 2) ?> liter
  </div>
  <?php endif; ?>
  
  <div id="chartContainer">
    <?php if (empty($labels)): ?>
      <p style="text-align: center; color: #666; padding: 50px;">Tidak ada data pemakaian air yang tersedia untuk unit ini.</p>
    <?php else: ?>
      <canvas id="pemakaianChart"></canvas>
      <div class="total-info">
        Data menampilkan total pemakaian air per hari untuk unit <?= htmlspecialchars($unit) ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Chart.js CDN -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    <?php if (!empty($labels)): ?>
    // Data PHP ke JS
    const labels = <?= json_encode($labels) ?>;
    const dataValues = <?= json_encode($values) ?>;

    // Inisialisasi Chart
    const ctx = document.getElementById('pemakaianChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Total Liter per Hari',
          data: dataValues,
          fill: true,
          tension: 0.4,
          borderColor: '#018A38',
          backgroundColor: 'rgba(1,138,56,0.2)',
          borderWidth: 3,
          pointBackgroundColor: '#018A38',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          pointRadius: 6,
          pointHoverRadius: 8,
        }]
      },
      options: {
        scales: {
          x: {
            title: { 
              display: true, 
              text: 'Tanggal',
              font: { size: 14, weight: 'bold' }
            },
            grid: {
              color: 'rgba(0,0,0,0.1)'
            }
          },
          y: {
            title: { 
              display: true, 
              text: 'Total Liter per Hari',
              font: { size: 14, weight: 'bold' }
            },
            beginAtZero: true,
            grid: {
              color: 'rgba(0,0,0,0.1)'
            },
            ticks: {
              callback: function(value) {
                return value.toFixed(2) + ' L';
              }
            }
          }
        },
        plugins: {
          legend: { 
            position: 'top',
            labels: {
              font: { size: 12, weight: 'bold' }
            }
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                return 'Total: ' + context.parsed.y.toFixed(2) + ' liter';
              }
            }
          }
        },
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          intersect: false,
          mode: 'index'
        }
      }
    });
    <?php endif; ?>
  </script>
</body>
</html>