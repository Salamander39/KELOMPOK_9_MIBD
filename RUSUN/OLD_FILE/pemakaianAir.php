<?php
session_start();
require_once 'config.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: LOGIN.html'); 
    exit();
}

// Ambil data harian pemakaian liter
$sql = "
    SELECT 
      CONVERT(VARCHAR(10), waktu, 23) AS tanggal,
      SUM(liter) AS totalLiter
    FROM PemakaianAir
    GROUP BY CONVERT(VARCHAR(10), waktu, 23)
    ORDER BY tanggal
";
$stmt = sqlsrv_query($conn, $sql);
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
  <title>Grafik Pemakaian Air Harian</title>
  <link href="https://fonts.googleapis.com/css?family=Offside&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Offside', cursive;
      background: #f5f5f5;
      margin: 0;
      padding: 20px; 
    }
    h2 {
      text-align: center;
      color: #014d24;
      margin-top: 50px; 
    }
    #chartContainer {
      width: 90%;
      max-width: 800px;
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      margin: 20px auto; 
    }
    canvas {
      width: 100% !important;
      height: auto !important;
    }

    .exit-button {
      position: absolute;
      top: 30px; 
      left: 30px; 
      background: none;
      border: none;
      cursor: pointer;
      z-index: 10; 
    }

    .exit-button img {
      width: 60px; 
      height: 60px; 
    }

    .exit-button:hover img {
      filter: brightness(1.1); 
    }
  </style>
</head>
<body>
  <button class="exit-button">
    <img src="images/back2.png" alt="Exit"> </button>

  <h2>Grafik Pemakaian Air Harian</h2>
  <div id="chartContainer">
    <canvas id="pemakaianChart"></canvas>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="./common.js"></script> 
  <script>
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
          label: 'Liter per Hari',
          data: dataValues,
          fill: true,
          tension: 0.4,
          borderColor: '#018A38',
          backgroundColor: 'rgba(1,138,56,0.2)',
          pointBackgroundColor: '#018A38',
          pointBorderColor: '#fff',
          pointHoverBackgroundColor: '#fff',
          pointHoverBorderColor: '#018A38'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          title: {
            display: true,
            text: 'Pemakaian Air Harian Unit Anda',
            font: {
              size: 18,
              family: 'Offside'
            },
            color: '#333'
          },
          tooltip: {
            mode: 'index',
            intersect: false,
          }
        },
        scales: {
          x: {
            title: {
              display: true,
              text: 'Tanggal',
              font: {
                family: 'Offside'
              }
            }
          },
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: 'Liter',
              font: {
                family: 'Offside'
              }
            }
          }
        }
      }
    });
  </script>
</body>
</html>