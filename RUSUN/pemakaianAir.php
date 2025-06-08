<?php
session_start();
require_once 'config.php';

// Get parameters
$unit = $_GET['unit'] ?? null;
$tahun = $_GET['tahun'] ?? null;
$bulan = $_GET['bulan'] ?? null;
$hari = $_GET['hari'] ?? null;

if (!$unit) {
    die("Unit parameter is required");
}

// Validasi input
if ($bulan && !$tahun) {
    die("Tahun harus dipilih jika memilih bulan");
}
if ($hari && !$bulan) {
    die("Bulan harus dipilih jika memilih hari");
}

// Query berdasarkan filter
$sql = "";
$params = [$unit];
$groupBy = "";
$xLabel = "";

if ($hari && $bulan && $tahun) {
    // Filter per jam dalam sehari
    $sql = "
        SELECT 
            DATEPART(HOUR, pa.waktu) AS periode,
            SUM(pa.liter) AS totalLiter
        FROM PemakaianAir pa
        JOIN PerangkatIOT iot ON pa.serialNumSen = iot.SerialNum
        WHERE iot.noUnit = ?
        AND YEAR(pa.waktu) = ?
        AND MONTH(pa.waktu) = ?
        AND DAY(pa.waktu) = ?
        GROUP BY DATEPART(HOUR, pa.waktu)
        ORDER BY periode
    ";
    $params = [$unit, $tahun, $bulan, $hari];
    $groupBy = "Jam";
    $xLabel = "Jam (0-23)";
} elseif ($bulan && $tahun) {
    // Filter per hari dalam sebulan
    $sql = "
        SELECT 
            DAY(pa.waktu) AS periode,
            SUM(pa.liter) AS totalLiter
        FROM PemakaianAir pa
        JOIN PerangkatIOT iot ON pa.serialNumSen = iot.SerialNum
        WHERE iot.noUnit = ?
        AND YEAR(pa.waktu) = ?
        AND MONTH(pa.waktu) = ?
        GROUP BY DAY(pa.waktu)
        ORDER BY periode
    ";
    $params = [$unit, $tahun, $bulan];
    $groupBy = "Hari";
    $xLabel = "Tanggal (1-31)";
} elseif ($tahun) {
    // Filter per bulan dalam setahun
    $sql = "
        SELECT 
            MONTH(pa.waktu) AS periode,
            SUM(pa.liter) AS totalLiter
        FROM PemakaianAir pa
        JOIN PerangkatIOT iot ON pa.serialNumSen = iot.SerialNum
        WHERE iot.noUnit = ?
        AND YEAR(pa.waktu) = ?
        GROUP BY MONTH(pa.waktu)
        ORDER BY periode
    ";
    $params = [$unit, $tahun];
    $groupBy = "Bulan";
    $xLabel = "Bulan (1-12)";
} else {
    // Default: semua data per hari
    $sql = "
        SELECT
            CONVERT(VARCHAR(10), pa.waktu, 23) AS periode,
            SUM(pa.liter) AS totalLiter
        FROM PemakaianAir pa
        JOIN PerangkatIOT iot ON pa.serialNumSen = iot.SerialNum
        WHERE iot.noUnit = ?
        GROUP BY CONVERT(VARCHAR(10), pa.waktu, 23)
        ORDER BY periode
    ";
    $params = [$unit];
    $groupBy = "Harian";
    $xLabel = "Tanggal";
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$labels = [];
$values = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if ($groupBy === "Bulan") {
        $bulanNames = ["", "Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Ags", "Sep", "Okt", "Nov", "Des"];
        $labels[] = $bulanNames[$row['periode']];
    } else {
        $labels[] = $row['periode'];
    }
    $values[] = (float)$row['totalLiter'];
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Grafik Pemakaian Air Unit <?= htmlspecialchars($unit) ?></title>
  <link href="https://fonts.googleapis.com/css?family=Offside&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
    .filter-container {
      display: flex;
      gap: 15px;
      margin-bottom: 20px;
      flex-wrap: wrap;
      justify-content: center;
    }
    .filter-group {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .filter-group label {
      font-weight: bold;
      color: #013C2B;
    }
    .filter-group select {
      padding: 8px 12px;
      border-radius: 4px;
      border: 1px solid #ddd;
    }
    .btn-filter {
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
  
  <div class="filter-container">
    <form method="get" action="" class="d-flex flex-wrap gap-3 align-items-center">
      <input type="hidden" name="unit" value="<?= htmlspecialchars($unit) ?>">
      
      <div class="filter-group">
        <label for="tahun">Tahun:</label>
        <select name="tahun" id="tahun" required>
          <option value="">Pilih Tahun</option>
          <?php 
          $currentYear = date('Y');
          for ($year = $currentYear; $year >= 2000; $year--) {
              $selected = $tahun == $year ? 'selected' : '';
              echo "<option value='$year' $selected>$year</option>";
          }
          ?>
        </select>
      </div>
      
      <div class="filter-group">
        <label for="bulan">Bulan:</label>
        <select name="bulan" id="bulan" <?= !$tahun ? 'disabled' : '' ?>>
          <option value="">Semua Bulan</option>
          <?php 
          $bulanNames = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", 
                        "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
          foreach ($bulanNames as $index => $name) {
              $monthNum = $index + 1;
              $selected = $bulan == $monthNum ? 'selected' : '';
              echo "<option value='$monthNum' $selected>$name</option>";
          }
          ?>
        </select>
      </div>
      
      <div class="filter-group">
        <label for="hari">Hari:</label>
        <select name="hari" id="hari" <?= !$bulan ? 'disabled' : '' ?>>
          <option value="">Semua Hari</option>
          <?php 
          if ($bulan && $tahun) {
              $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
              for ($day = 1; $day <= $daysInMonth; $day++) {
                  $selected = $hari == $day ? 'selected' : '';
                  echo "<option value='$day' $selected>$day</option>";
              }
          }
          ?>
        </select>
      </div>
      
      <button type="submit" class="btn-filter">Filter</button>
    </form>
  </div>

  <?php if (count($labels) > 0): ?>
  <div class="info-box">
    <strong>Unit:</strong> <?= htmlspecialchars($unit) ?><br>
    <strong>Periode:</strong> 
    <?= $tahun ? $tahun : 'Semua Tahun' ?>
    <?= $bulan ? ' - ' . $bulanNames[$bulan-1] : '' ?>
    <?= $hari ? ' - ' . $hari : '' ?>
    <br>
    <strong>Total Data:</strong> <?= count($labels) ?> <?= strtolower($groupBy) ?><br>
    <strong>Total Pemakaian:</strong> <?= number_format(array_sum($values), 2) ?> liter<br>
    <strong>Rata-rata per <?= $groupBy ?>:</strong> <?= number_format(array_sum($values) / count($values), 2) ?> liter
  </div>
  <?php endif; ?>
  
  <div id="chartContainer">
    <?php if (empty($labels)): ?>
      <p style="text-align: center; color: #666; padding: 50px;">Tidak ada data pemakaian air yang tersedia untuk filter ini.</p>
    <?php else: ?>
      <canvas id="pemakaianChart"></canvas>
      <div class="total-info">
        Data menampilkan total pemakaian air per <?= strtolower($groupBy) ?> untuk unit <?= htmlspecialchars($unit) ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Chart.js CDN -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Dynamic filter enabling
    document.getElementById('tahun').addEventListener('change', function() {
      const bulanSelect = document.getElementById('bulan');
      bulanSelect.disabled = !this.value;
      if (!this.value) {
        bulanSelect.value = '';
        document.getElementById('hari').disabled = true;
        document.getElementById('hari').value = '';
      }
    });

    document.getElementById('bulan').addEventListener('change', function() {
      const hariSelect = document.getElementById('hari');
      hariSelect.disabled = !this.value;
      if (!this.value) {
        hariSelect.value = '';
      } else {
        // Update days dropdown based on selected month/year
        const tahun = document.getElementById('tahun').value;
        const bulan = this.value;
        
        // Simple implementation - could be improved with AJAX
        const daysInMonth = new Date(tahun, bulan, 0).getDate();
        const hariSelect = document.getElementById('hari');
        const currentDay = hariSelect.value;
        
        hariSelect.innerHTML = '<option value="">Semua Hari</option>';
        for (let day = 1; day <= daysInMonth; day++) {
          const option = document.createElement('option');
          option.value = day;
          option.textContent = day;
          if (day == currentDay) option.selected = true;
          hariSelect.appendChild(option);
        }
      }
    });

    <?php if (!empty($labels)): ?>
    // Data PHP ke JS
    const labels = <?= json_encode($labels) ?>;
    const dataValues = <?= json_encode($values) ?>;
    const xLabel = "<?= $xLabel ?>";

    // Inisialisasi Chart
    const ctx = document.getElementById('pemakaianChart').getContext('2d');
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Total Liter per ' + "<?= $groupBy ?>",
          data: dataValues,
          backgroundColor: '#018A38',
          borderColor: '#013C2B',
          borderWidth: 1
        }]
      },
      options: {
        scales: {
          x: {
            title: { 
              display: true, 
              text: xLabel,
              font: { size: 14, weight: 'bold' }
            },
            grid: {
              display: false
            }
          },
          y: {
            title: { 
              display: true, 
              text: 'Total Liter',
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
            display: false
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
        maintainAspectRatio: false
      }
    });
    <?php endif; ?>
  </script>
</body>
</html>