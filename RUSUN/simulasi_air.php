<?php
session_start();
require_once 'config.php'; 

$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['userRole'] ?? null;

// Ensure only administrators can access this page
if (!$userId || $userRole !== 'Admin') {
    die("Unauthorized: You must be an administrator to input simulation data.");
}

$message = '';
$messageType = '';

// Fetch existing unit numbers for the dropdown
$units = [];
$sql_units =    "SELECT DISTINCT noUnit 
                FROM Kepemilikan 
                ORDER BY noUnit ASC";
$stmt_units = sqlsrv_query($conn, $sql_units);
if ($stmt_units) {
    while ($row = sqlsrv_fetch_array($stmt_units, SQLSRV_FETCH_ASSOC)) {
        $units[] = $row['noUnit'];
    }
} else {
    $message = "Error fetching unit numbers: " . print_r(sqlsrv_errors(), true);
    $messageType = 'error';
}

// Handle form submission
if (isset($_POST['add_simulasi'])) {
    $noUnit = $_POST['noUnit'] ?? '';
    $serialNum = $_POST['serialNum'] ?? '';
    $waktu = $_POST['waktu'] ?? '';
    $liter = $_POST['liter'] ?? '';

    // Validate inputs
    if (empty($noUnit) || empty($serialNum) || empty($waktu) || !is_numeric($liter) || $liter < 0) {
        $message = "Semua kolom harus diisi dengan benar.";
        $messageType = 'error';
    } else {
        // Format waktu for SQL Server 
       $formattedWaktu = (new DateTime($waktu))->format('Y-m-d H:i:s'); 

        // SQL
        $check_serial_sql = "SELECT 1 
                            FROM PerangkatIOT 
                            WHERE serialNum = ? 
                            AND noUnit = ?";
        $check_serial_params = array($serialNum, $noUnit);
        $check_serial_stmt = sqlsrv_query($conn, $check_serial_sql, $check_serial_params);
        
        if ($check_serial_stmt === false) {
            $message = "Error checking serial number: " . print_r(sqlsrv_errors(), true);
            $messageType = 'error';
        } elseif (sqlsrv_has_rows($check_serial_stmt) === false) {
            $message = "Serial Number '{$serialNum}' tidak ditemukan untuk Unit '{$noUnit}'. Pastikan SN dan Unit sudah terdaftar.";
            $messageType = 'error';
        } else {
           //INSERT PEMAKAIAN AIR
            $sql_insert = "INSERT INTO PemakaianAir (serialNumSen, waktu, liter) VALUES (?, ?, ?)";
            $params_insert = array($serialNum, $formattedWaktu, (float)$liter);

            $stmt_insert = sqlsrv_query($conn, $sql_insert, $params_insert);

            if ($stmt_insert === false) {
                $errors = sqlsrv_errors();
                $message = "Error menambahkan data simulasi: " . print_r($errors, true); // Detailed error for debugging
                $messageType = 'error';
            } else {
                $message = "Data simulasi berhasil ditambahkan: Unit '{$noUnit}', SN '{$serialNum}', {$liter} liter pada {$waktu}.";
                $messageType = 'success';
               //CLEAR
                $serialNum = ''; 
                $waktu = '';
                $liter = '';
            }
        }
    }
} else {
    // Default values for form fields
    $noUnit = '';
    $serialNum = '';
    $waktu = '';
    $liter = '';
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Input Data Simulasi Air</title>
    <link href="https://fonts.googleapis.com/css?family=Offside&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Offside', cursive;
            background-color: rgb(208, 208, 208);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 550px; 
            box-sizing: border-box;
        }
        .header {
            display: flex;
            justify-content: space-between; 
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgb(1, 138, 56);
        }
        .header h2 {
            color: rgb(1, 138, 56);
            margin: 0;
            font-size: 1.8rem;
        }
        /* Removed .back-button styling */
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="datetime-local"],
        .form-group select {
            width: calc(100% - 20px);
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .btn-container {
            display: flex;
            justify-content: flex-end; 
            gap: 10px; 
            margin-top: 20px;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1rem;
            font-family: 'Offside', cursive;
            transition: background-color 0.3s ease, filter 0.3s ease;
            display: inline-block;
        }
        .btn-submit {
            background-color: rgb(1, 138, 56);
            color: white;
        }
        .btn-submit:hover {
            filter: brightness(1.1);
        }
        .btn-cancel {
            background-color: #6c757d;
            color: white;
        }
        .btn-cancel:hover {
            filter: brightness(1.1);
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
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Input Data Simulasi Air</h2>
            </div>

        <?php if (!empty($message)): ?>
            <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="noUnit">Nomor Unit:</label>
                <select id="noUnit" name="noUnit" required>
                    <option value="">Pilih Nomor Unit</option>
                    <?php foreach ($units as $unit_option): ?>
                        <option value="<?= htmlspecialchars($unit_option) ?>" <?= ($noUnit == $unit_option) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($unit_option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="serialNum">Serial Number (SN):</label>
                <select id="serialNum" name="serialNum" required>
                    <option value="">Pilih Serial Number</option>
                    <?php 
                        // If noUnit is already selected 
                        // populate the SN dropdown for the selected unit
                        if (!empty($noUnit)) {
                            $sql_sn_on_load =   "SELECT serialNum 
                                                FROM PerangkatIOT 
                                                WHERE noUnit = ? 
                                                ORDER BY serialNum ASC";
                            $stmt_sn_on_load = sqlsrv_query($conn, $sql_sn_on_load, array($noUnit));
                            if ($stmt_sn_on_load) {
                                while ($row_sn = sqlsrv_fetch_array($stmt_sn_on_load, SQLSRV_FETCH_ASSOC)) {
                                    $selected = ($serialNum == $row_sn['serialNum']) ? 'selected' : '';
                                    echo "<option value=\"" . htmlspecialchars($row_sn['serialNum']) . "\" {$selected}>" . htmlspecialchars($row_sn['serialNum']) . "</option>";
                                }
                            }
                        }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label for="waktu">Waktu (Tanggal & Jam):</label>
                <input type="datetime-local" id="waktu" name="waktu" value="<?= htmlspecialchars($waktu) ?>" required>
            </div>

            <div class="form-group">
                <label for="liter">Liter:</label>
                <input type="number" step="0.01" id="liter" name="liter" value="<?= htmlspecialchars($liter) ?>" required>
            </div>

            <div class="btn-container">
                <button type="submit" name="add_simulasi" class="btn btn-submit">Tambah Data</button>
                <button type="button" class="btn btn-cancel" onclick="window.location.href='HOME_Admin.html'">Batal</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const noUnitSelect = document.getElementById('noUnit');
            const serialNumSelect = document.getElementById('serialNum');

            // Function to fetch and populate serial numbers
            async function fetchAndPopulateSerialNumbers() {
                const selectedUnit = noUnitSelect.value;
                
                // Clear current options
                serialNumSelect.innerHTML = '<option value="">Pilih Serial Number</option>';

                if (selectedUnit) {
                    try {
                        const response = await fetch(`get_serial_number.php?unit=${encodeURIComponent(selectedUnit)}`);
                        const result = await response.json(); 

                        if (result.error) { 
                            console.error('Error fetching serial numbers:', result.error);
                            alert('Gagal mengambil Serial Number: ' + result.error);
                            return;
                        }
                        
                        if (result.success && Array.isArray(result.serialNumbers)) { 
                            result.serialNumbers.forEach(sn => {
                                const option = document.createElement('option');
                                option.value = sn;
                                option.textContent = sn;
                                serialNumSelect.appendChild(option);
                            });
                        } else {
                            // Handle unexpected successful response structure
                            console.error('Unexpected successful response structure:', result);
                            alert('Gagal mengambil Serial Number: Respon tidak valid.');
                        }

                        // If a serial number was previously selected, try to re-select it
                        const previouslySelectedSN = "<?= htmlspecialchars($serialNum) ?>"; // PHP variable for re-selection
                        if (previouslySelectedSN && Array.from(serialNumSelect.options).some(opt => opt.value === previouslySelectedSN)) {
                            serialNumSelect.value = previouslySelectedSN;
                        }

                    } catch (error) {
                        console.error('Network or parsing error:', error);
                        alert('Terjadi kesalahan jaringan saat mengambil Serial Number.');
                    }
                }
            }

            noUnitSelect.addEventListener('change', fetchAndPopulateSerialNumbers);

            // Populate serial numbers on page load if a unit is already selected 
            if (noUnitSelect.value) {
                fetchAndPopulateSerialNumbers();
            }
        });
    </script>
</body>
</html>