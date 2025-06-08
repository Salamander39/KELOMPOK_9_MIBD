<?php
session_start();
require_once 'config.php'; 

$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['userRole'] ?? null;

// Get filter parameters from GET request
$tahun = $_GET['tahun'] ?? null;
$bulan = $_GET['bulan'] ?? null;
$hari = $_GET['hari'] ?? null;
$towerFilter = $_GET['towerFilter'] ?? null;
$lantaiFilter = $_GET['lantaiFilter'] ?? null;
$urutFilter = $_GET['urutFilter'] ?? null;

// Validasi input tanggal
if ($bulan && !$tahun) {
    die("Tahun harus dipilih jika memilih bulan");
}
if ($hari && !$bulan) {
    die("Bulan harus dipilih jika memilih hari");
}

//SQL query
$sql = "
    SELECT
        s.noUnit,
        k.idPengguna as ownerId,
        COALESCE(SUM(pa.liter), 0) as totalLiter,
        MAX(pa.waktu) as waktuTerakhir
        FROM Sarusun s
        LEFT JOIN Kepemilikan k ON s.noUnit = k.noUnit
        LEFT JOIN (
        SELECT
            p.serialNumSen,
            p.liter,
            p.waktu,
            iot.noUnit AS linkedUnitNo
        FROM PemakaianAir p
        JOIN Sensor sen ON p.serialNumSen = sen.serialNumSen
        JOIN PerangkatIOT iot ON sen.serialNumSen = iot.serialNum
        WHERE 1=1 -- Placeholder for date filters
";

$params = [];
$dateFilterConditions = [];

if ($tahun) {
    $dateFilterConditions[] = "YEAR(p.waktu) = ?";
    $params[] = (int)$tahun;
}
if ($bulan) {
    $dateFilterConditions[] = "MONTH(p.waktu) = ?";
    $params[] = (int)$bulan;
}
if ($hari) {
    $dateFilterConditions[] = "DAY(p.waktu) = ?";
    $params[] = (int)$hari;
}

if (!empty($dateFilterConditions)) {
    $sql .= " AND " . implode(" AND ", $dateFilterConditions);
}

// Close the subquery and join to Sarusun
$sql .= "
    ) pa ON pa.linkedUnitNo = s.noUnit
    WHERE 1=1 -- Placeholder for unit number filters on Sarusun
";

$unitFilterConditions = [];

if ($towerFilter) {
    $unitFilterConditions[] = "SUBSTRING(s.noUnit, 1, 1) = ?";
    $params[] = $towerFilter;
}
if ($lantaiFilter) {
    // Ensure 2 digits for lantai, for matching 'XX' in unit number
    $formattedLantai = str_pad((int)$lantaiFilter, 2, '0', STR_PAD_LEFT);
    $unitFilterConditions[] = "SUBSTRING(s.noUnit, 2, 2) = ?";
    $params[] = $formattedLantai;
}
if ($urutFilter) {
    // Ensure 2 digits for urut, for matching 'XX' in unit number
    $formattedUrut = str_pad((int)$urutFilter, 2, '0', STR_PAD_LEFT);
    $unitFilterConditions[] = "SUBSTRING(s.noUnit, 4, 2) = ?";
    $params[] = $formattedUrut;
}

if (!empty($unitFilterConditions)) {
    $sql .= " AND " . implode(" AND ", $unitFilterConditions);
}

$sql .= "
    GROUP BY s.noUnit, k.idPengguna
    ORDER BY s.noUnit
";

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    // Tampilkan pesan error jika query gagal
    die(print_r(sqlsrv_errors(), true));
}

// Mengatur data per unit
$units = [];
$totalPemakaianAll = 0;

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $unitNo = $row['noUnit'];
    $pemakaian = $row['totalLiter'] ?? 0;
    $waktuTerakhir = $row['waktuTerakhir'];

    $totalPemakaianAll += (float)$pemakaian;

    $units[] = [
        'noUnit' => $unitNo,
        'ownerId' => $row['ownerId'],
        'pemakaianBulanIni' => (float)$pemakaian,
        'waktuTerakhir' => $waktuTerakhir ? $waktuTerakhir->format('Y-m-d H:i:s') : null
    ];
}

// Build filter info text
$filterTexts = [];
if ($tahun) {
    $filterTexts[] = "Tahun: " . $tahun;
    if ($bulan) {
        $bulanNames = ["Januari", "Februari", "Maret", "April", "Mei", "Juni",
                       "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
        $filterTexts[] = "Bulan: " . $bulanNames[$bulan - 1];
        if ($hari) {
            $filterTexts[] = "Hari: " . $hari;
        }
    }
}
if ($towerFilter) {
    $filterTexts[] = "Tower: " . $towerFilter;
}
if ($lantaiFilter) {
    $filterTexts[] = "Lantai: " . (int)$lantaiFilter;
}
if ($urutFilter) {
    $filterTexts[] = "No Urut: " . (int)$urutFilter;
}

$initialFilterInfo = "Menampilkan semua unit.";
if (!empty($filterTexts)) {
    $initialFilterInfo = "Filter aktif: " . implode(', ', $filterTexts);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemakaian Air - Semua Unit</title>
    <link href="https://fonts.googleapis.com/css?family=Offside&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Offside', cursive;
            background-color: #f5f5f5;
            min-height: 100vh;
        }

        .header {
            background: #018A38;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            margin: 0;
            font-size: 1.5rem;
        }

        .exit-button {
            background: transparent;
            border: 1px solid white;
            color: white;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 4px;
        }

        .exit-button:hover {
            background: rgba(255,255,255,0.1);
        }

        .container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Stats Bar - Enhanced */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stats-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            margin: 0;
        }

        .stats-number.units {
            color: #018A38;
        }

        .stats-number.water {
            color: #007bff;
        }

        .stats-label {
            font-size: 0.9rem;
            color: #666;
            margin: 5px 0 0 0;
        }

        .stats-sublabel {
            font-size: 0.75rem;
            color: #999;
            margin: 2px 0 0 0;
        }

        /* Filter Styles */
        .filter-container {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .filter-title {
            margin: 0 0 15px 0;
            font-size: 1.1rem;
            color: #333;
            font-weight: bold;
        }

        .filter-form { /* Added this class for the form */
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 0.9rem;
            color: #555;
            font-weight: bold;
        }

        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
            width: 100px; /* Adjust width as needed */
        }
        .filter-group input[type="number"] {
             width: 80px; /* Smaller width for number inputs */
        }


        .filter-buttons {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            margin-top: 10px; /* Space from filters */
        }

        .filter-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: bold;
        }

        .btn-filter {
            background: #018A38;
            color: white;
        }

        .btn-filter:hover {
            background: #016930;
        }

        .btn-reset {
            background: #6c757d;
            color: white;
        }

        .btn-reset:hover {
            background: #5a6268;
        }

        .filter-info {
            margin-top: 10px;
            padding: 8px 12px;
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 4px;
            font-size: 0.8rem;
            color: #004085;
        }

        /* Units Grid */
        .units-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .unit-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .unit-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .unit-header {
            text-align: center;
            margin-bottom: 15px;
        }

        .unit-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #018A38;
            margin: 0 0 8px 0;
        }

        .unit-breakdown {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
        }

        .unit-owner {
            font-size: 0.8rem;
            color: #888;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .unit-water-info {
            background: #f0f8ff;
            border: 1px solid #b8daff;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
        }

        .water-usage {
            font-size: 1.5rem;
            font-weight: bold;
            color: #007bff;
            margin: 0 0 5px 0;
        }

        .water-label {
            font-size: 0.8rem;
            color: #666;
            margin: 0;
        }

        .water-period {
            font-size: 0.75rem;
            color: #999;
            margin: 2px 0 0 0;
        }

        .unit-actions {
            text-align: center;
            margin-top: 20px;
        }

        .cek-pemakaian-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            transition: background 0.2s ease, transform 0.1s ease;
            width: 100%;
        }

        .cek-pemakaian-btn:hover {
            background: #0056b3;
            transform: scale(1.02);
        }

        .cek-pemakaian-btn:active {
            transform: scale(0.98);
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #666;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .no-results h3 {
            margin: 0 0 10px 0;
            color: #333;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group select,
            .filter-group input {
                width: 100%;
            }

            .units-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Pemakaian Air - Semua Unit</h1>
        <button id="back-btn" class="exit-button">Kembali</button>
    </div>

    <div class="container">
        <div class="stats-container">
            <div class="stats-card">
                <h2 class="stats-number units" id="totalUnitsCount"><?= count($units) ?></h2>
                <p class="stats-label">Total Unit Terdaftar</p>
            </div>
            <div class="stats-card">
                <h2 class="stats-number water" id="totalWaterUsage"><?= number_format($totalPemakaianAll, 1) ?> liter</h2>
                <p class="stats-label">Total Pemakaian Air</p>
                <p class="stats-sublabel">liter (Unit yang difilter)</p>
            </div>
        </div>

        <div class="filter-container">
            <h3 class="filter-title">Filter Unit dan Periode</h3>
            <form method="get" action="" class="filter-form">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="towerFilter">Tower:</label>
                        <select name="towerFilter" id="towerFilter">
                            <option value="">-</option>
                            <option value="A" <?= ($towerFilter == 'A') ? 'selected' : '' ?>>A</option>
                            <option value="B" <?= ($towerFilter == 'B') ? 'selected' : '' ?>>B</option>
                            <option value="C" <?= ($towerFilter == 'C') ? 'selected' : '' ?>>C</option>
                            <option value="D" <?= ($towerFilter == 'D') ? 'selected' : '' ?>>D</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="lantaiFilter">Lantai:</label>
                        <input type="number" name="lantaiFilter" id="lantaiFilter" placeholder="-" min="1" max="99" value="<?= htmlspecialchars($lantaiFilter) ?>">
                    </div>
                    <div class="filter-group">
                        <label for="urutFilter">No Urut:</label>
                        <input type="number" name="urutFilter" id="urutFilter" placeholder="-" min="1" max="99" value="<?= htmlspecialchars($urutFilter) ?>">
                    </div>

                    <div class="filter-group">
                        <label for="tahun">Tahun:</label>
                        <select name="tahun" id="tahun">
                            <option value="">Pilih Tahun</option>
                            <?php
                            $currentYear = date('Y');
                            for ($year = $currentYear; $year >= 2000; $year--) {
                                $selected = ($tahun == $year) ? 'selected' : '';
                                echo "<option value='$year' $selected>$year</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="bulan">Bulan:</label>
                        <select name="bulan" id="bulan" <?= !$tahun ? 'disabled' : '' ?>>
                            <option value="">Pilih Bulan</option>
                            <?php
                            $bulanNames = ["Januari", "Februari", "Maret", "April", "Mei", "Juni",
                                            "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
                            foreach ($bulanNames as $index => $name) {
                                $monthNum = $index + 1;
                                $selected = ($bulan == $monthNum) ? 'selected' : '';
                                echo "<option value='$monthNum' $selected>$name</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="hari">Hari:</label>
                        <select name="hari" id="hari" <?= !$bulan ? 'disabled' : '' ?>>
                            <option value="">Pilih Hari</option>
                            <?php
                            if ($bulan && $tahun) {
                                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
                                for ($day = 1; $day <= $daysInMonth; $day++) {
                                    $selected = ($hari == $day) ? 'selected' : '';
                                    echo "<option value='$day' $selected>$day</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="filter-buttons">
                    <button type="submit" class="filter-btn btn-filter">Terapkan Filter</button>
                    <button type="button" id="resetFilter" class="filter-btn btn-reset">Reset</button>
                </div>
            </form>
            <div class="filter-info" id="filterInfo">
                <?= htmlspecialchars($initialFilterInfo) ?>. Total unit: <?= count($units) ?>. Total pemakaian: <?= number_format($totalPemakaianAll, 1) ?> liter.
            </div>
        </div>

        <div class="units-grid" id="unitsGrid">
        <?php if (empty($units)): ?>
            <div class="no-results">
                <h3>Tidak ada data unit</h3>
                <p>Belum ada unit yang terdaftar dalam sistem atau tidak ada data sesuai filter.</p>
            </div>
        <?php else: ?>
            <?php foreach ($units as $unit): ?>
                <div class="unit-card"
                     data-unit="<?= htmlspecialchars($unit['noUnit']) ?>"
                     data-usage="<?= $unit['pemakaianBulanIni'] ?>">
                    <div class="unit-header">
                        <h3 class="unit-number">Unit <?= htmlspecialchars($unit['noUnit']) ?></h3>
                        <div class="unit-breakdown">
                            <?php
                            $unitNo = $unit['noUnit'];
                            $tower = substr($unitNo, 0, 1);
                            $lantai = (int)substr($unitNo, 1, 2);
                            $urut = (int)substr($unitNo, 3, 2);
                            ?>
                            Tower <?= htmlspecialchars($tower) ?> | Lantai <?= $lantai ?> | No <?= $urut ?>
                        </div>
                        <div class="unit-owner">
                            <?= $unit['ownerId'] ? 'Pemilik: User ID ' . htmlspecialchars($unit['ownerId']) : 'Belum ada pemilik' ?>
                        </div>
                    </div>

                    <div class="unit-water-info">
                        <h4 class="water-usage"><?= number_format($unit['pemakaianBulanIni'], 1) ?> liter</h4>
                        <p class="water-label">Total Pemakaian Air</p>
                        <?php if ($unit['waktuTerakhir']): ?>
                            <p class="water-period">
                                Terakhir update: <?= date('d M Y H:i', strtotime($unit['waktuTerakhir'])) ?>
                            </p>
                        <?php else: ?>
                            <p class="water-period">Belum ada data</p>
                        <?php endif; ?>
                    </div>

                    <div class="unit-actions">
                        <button class="cek-pemakaian-btn" onclick="cekPemakaianAir('<?= htmlspecialchars($unit['noUnit']) ?>')">
                            Cek Pemakaian Air
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const backBtn = document.getElementById('back-btn');
            const tahunSelect = document.getElementById('tahun');
            const bulanSelect = document.getElementById('bulan');
            const hariSelect = document.getElementById('hari');
            const resetFilterBtn = document.getElementById('resetFilter');
            const filterForm = document.querySelector('.filter-form'); // Select the form

            // Pass the user role from PHP to JavaScript
            const userRole = "<?= htmlspecialchars($userRole) ?>"; 

            backBtn.addEventListener('click', () => {
                if (userRole === 'Admin') {
                    window.location.href = './EDIT_ADMIN.html';
                } else if (userRole === 'Pengelola') {
                    window.location.href = './HOME_Pengelola.html';
                }
                // If other roles exist, you can add more conditions here
            });

            // Dynamic filter enabling and day population
            tahunSelect.addEventListener('change', function() {
                bulanSelect.disabled = !this.value;
                if (!this.value) {
                    bulanSelect.value = '';
                    hariSelect.disabled = true;
                    hariSelect.innerHTML = '<option value="">Pilih Hari</option>'; 
                }
                updateDaysInMonth();
            });

            bulanSelect.addEventListener('change', function() {
                hariSelect.disabled = !this.value;
                if (!this.value) {
                    hariSelect.innerHTML = '<option value="">Pilih Hari</option>'; 
                } else {
                    updateDaysInMonth();
                }
            });

            function updateDaysInMonth() {
                const tahun = tahunSelect.value;
                const bulan = bulanSelect.value;
                const currentDay = hariSelect.value; // Keep current day if valid

                hariSelect.innerHTML = '<option value="">Pilih Hari</option>'; // Changed to "Pilih Hari"
                if (tahun && bulan) {
                    const daysInMonth = new Date(tahun, bulan, 0).getDate();
                    for (let day = 1; day <= daysInMonth; day++) {
                        const option = document.createElement('option');
                        option.value = day;
                        option.textContent = day;
                        if (day == currentDay) option.selected = true; // Retain selected day
                        hariSelect.appendChild(option);
                    }
                }
            }

            // Call on load to ensure initial state is correct based on PHP values
            updateDaysInMonth();


            resetFilterBtn.addEventListener('click', () => {
                // Clear all form fields
                filterForm.querySelectorAll('select').forEach(select => select.value = '');
                filterForm.querySelectorAll('input[type="number"]').forEach(input => input.value = '');
                
                // Manually re-disable month and day selects if year is cleared
                bulanSelect.disabled = true;
                hariSelect.disabled = true;
                hariSelect.innerHTML = '<option value="">Pilih Hari</option>'; 
                
                // Submit the cleared form to reload with no filters
                filterForm.submit();
            });
        });

        function cekPemakaianAir(unitNo) {
            // Redirect to the detailed usage page for a specific unit
            window.location.href = 'pemakaianAir.php?unit=' + encodeURIComponent(unitNo);
        }
    </script>
</body>
</html>