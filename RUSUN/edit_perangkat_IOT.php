<?php
session_start();
require_once 'config.php';

$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['userRole'] ?? null;

if (!$userId || $userRole !== 'Admin') {
    die("Unauthorized: You must be an administrator to manage IoT devices.");
}

$message = '';
$messageType = '';

// --- DELETE Operation ---
if (isset($_GET['delete_serial'])) {
    $serialToDelete = $_GET['delete_serial'];

    sqlsrv_begin_transaction($conn); // Start transaction

    try {
        // First, delete from Aktuator table if there's a foreign key dependency
        // This query assumes serialNumAkt in Aktuator references serialNum in PerangkatIOT
        $sql_delete_aktuator = "DELETE FROM Aktuator WHERE serialNumAkt = ?";
        $stmt_delete_aktuator = sqlsrv_query($conn, $sql_delete_aktuator, array($serialToDelete));

        if ($stmt_delete_aktuator === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        // Then, delete from PerangkatIOT table
        $sql_delete_perangkat = "DELETE FROM PerangkatIOT WHERE serialNum = ?";
        $stmt_delete_perangkat = sqlsrv_query($conn, $sql_delete_perangkat, array($serialToDelete));

        if ($stmt_delete_perangkat === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        sqlsrv_commit($conn); // Commit transaction
        $message = "Perangkat IoT '{$serialToDelete}' berhasil dihapus.";
        $messageType = 'success';

    } catch (Exception $e) {
        sqlsrv_rollback($conn); // Rollback on error
        $message = "Error deleting device: " . $e->getMessage();
        $messageType = 'error';
    }
}

// --- FETCH Operation (All data fetched, filtering/sorting done client-side) ---
// We now fetch all relevant data and let JavaScript handle the filtering and sorting.
$sql_fetch_all = "
    SELECT
        serialNum,
        noUnit,
        tglPasang
    FROM PerangkatIOT
    ORDER BY serialNum ASC
";
$result_all_devices = sqlsrv_query($conn, $sql_fetch_all);

if ($result_all_devices === false) {
    if (empty($message)) {
        $message = "Error loading devices: " . print_r(sqlsrv_errors(), true);
    }
    $messageType = 'error';
}

$devices = [];
if ($result_all_devices) {
    while ($row = sqlsrv_fetch_array($result_all_devices, SQLSRV_FETCH_ASSOC)) {
        $devices[] = $row;
    }
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
            font-family: 'Offside', cursive;
            background-color: rgb(208, 208, 208);
            padding: 20px;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            width: 80%;
            max-width: 900px;
            margin-top: 20px;
            background: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgb(1, 138, 56);
        }
        .header h2 {
            margin: 0;
            color: rgb(1, 138, 56);
        }
        .btn {
            padding: 10px 20px;
            margin-right: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-family: 'Offside', cursive;
            text-decoration: none;
            display: inline-block;
        }
        .btn-add {
            background-color: rgb(1, 138, 56);
            color: white;
        }
        .btn-add:hover {
            filter: brightness(1.1);
        }
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

        .device-list-table {
            border-collapse: collapse;
            width: 100%;
            background: white;
        }
        .device-list-table th, .device-list-table td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
        }
        .device-list-table th {
            background-color: rgb(1, 138, 56);
            color: white;
        }
        .device-list-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .btn-danger {
            background-color: #dc3545; /* Red color for delete */
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .btn-danger:hover {
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

        /* Filter and Sort Styles */
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

        .filter-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
            justify-content: space-between;
        }

        .filter-input-groups {
            display: flex;
            gap: 15px;
            align-items: flex-end;
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
        .filter-group input[type="number"],
        .filter-group input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
            width: 100px; /* Consistent width for filter inputs */
        }
        .filter-group input[type="date"] {
            width: 130px; /* Wider for date inputs */
        }


        .filter-actions-and-sort {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
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

        .no-results-message {
            text-align: center;
            padding: 20px;
            color: #666;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            margin-top: 20px;
            display: none; /* Hidden by default */
        }
        
        .sort-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .sort-group label {
            font-size: 0.9rem;
            color: #555;
            font-weight: bold;
        }

        .sort-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="header">
            <h2>Daftar Perangkat IoT</h2>
            <button class="back-button-img">
                <img src="images/back2.png" alt="Kembali" />
            </button>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div style="margin-bottom: 20px; text-align: right;">
            <a href="add_perangkat_iot.php" class="btn btn-add">Tambah Perangkat IoT Baru</a>
        </div>

        <div class="filter-container">
            
            <div class="filter-row">
                <div class="filter-input-groups">
                    <div class="filter-group">
                        <label for="towerFilter">Tower:</label>
                        <select id="towerFilter">
                            <option value="">-</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="lantaiFilter">Lantai:</label>
                        <input type="number" id="lantaiFilter" placeholder="-" min="1" max="99">
                    </div>
                    <div class="filter-group">
                        <label for="urutFilter">No Urut:</label>
                        <input type="number" id="urutFilter" placeholder="-" min="1" max="99">
                    </div>
                    <div class="filter-group">
                        <label for="dateFromFilter">Tanggal Pasang Dari:</label>
                        <input type="date" id="dateFromFilter">
                    </div>
                    <div class="filter-group">
                        <label for="dateToFilter">Tanggal Pasang Hingga:</label>
                        <input type="date" id="dateToFilter">
                    </div>
                </div>

                <div class="filter-actions-and-sort">
                    <div class="filter-buttons">
                        <button id="applyFilter" class="filter-btn btn-filter">Terapkan Filter</button>
                        <button id="resetFilter" class="filter-btn btn-reset">Reset</button>
                    </div>
                    <div class="sort-group">
                        <label for="sortOrder">Urutkan:</label>
                        <select id="sortOrder">
                            <option value="serialNum-asc">Serial Number (A-Z)</option>
                            <option value="serialNum-desc">Serial Number (Z-A)</option>
                            <option value="noUnit-asc">No. Unit (A-Z)</option>
                            <option value="noUnit-desc">No. Unit (Z-A)</option>
                            <option value="tglPasang-asc">Tanggal Pasang (Asc)</option>
                            <option value="tglPasang-desc">Tanggal Pasang (Desc)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="filter-info" id="filterInfo">
                Menampilkan semua perangkat. Gunakan filter di atas untuk menyaring perangkat berdasarkan Tower, Lantai, No Urut, atau Tanggal Pasang.
            </div>
        </div>
        
        <table class="device-list-table">
            <thead>
                <tr>
                    <th>Serial Number</th>
                    <th>No. Unit</th>
                    <th>Tanggal Pasang</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="deviceTableBody">
                <?php
                if (!empty($devices)) {
                    foreach ($devices as $device):
                        // Format tglPasang if it's a DateTime object
                        $tglPasangFormatted = $device['tglPasang'] instanceof DateTime ? $device['tglPasang']->format('Y-m-d H:i:s') : 'N/A';
                        $tglPasangSortable = $device['tglPasang'] instanceof DateTime ? $device['tglPasang']->format('Y-m-d') : ''; // For date filtering/sorting

                        // Extract tower, lantai, urut from noUnit string for data attributes
                        $noUnit = htmlspecialchars($device['noUnit']);
                        $tower = (strlen($noUnit) >= 1) ? substr($noUnit, 0, 1) : '';
                        $lantai = (strlen($noUnit) >= 3) ? substr($noUnit, 1, 2) : '';
                        $no_urut = (strlen($noUnit) >= 5) ? substr($noUnit, 3, 2) : '';
                ?>
                    <tr class="device-row" 
                        data-serial="<?= htmlspecialchars($device['serialNum']) ?>"
                        data-nounit="<?= $noUnit ?>"
                        data-tower="<?= $tower ?>"
                        data-lantai="<?= $lantai ?>"
                        data-urut="<?= $no_urut ?>"
                        data-tglpasang="<?= htmlspecialchars($tglPasangSortable) ?>">
                        <td><?= htmlspecialchars($device['serialNum']) ?></td>
                        <td><?= $noUnit ?></td>
                        <td><?= htmlspecialchars($tglPasangFormatted) ?></td>
                        <td>
                            <a href="?delete_serial=<?= htmlspecialchars($device['serialNum']) ?>"
                               class="btn btn-danger"
                               onclick="return confirm('Anda yakin ingin menghapus device <?= htmlspecialchars($device['serialNum']) ?>? ')">Delete</a>
                        </td>
                    </tr>
                <?php
                    endforeach;
                } else {
                    echo '<tr id="noDevicesRow"><td colspan="4" style="text-align:center;">Tidak ada perangkat IoT yang ditemukan.</td></tr>';
                }
                ?>
            </tbody>
        </table>

        <div id="noResultsMessage" class="no-results-message">
            <h3>Tidak ada perangkat yang sesuai dengan filter</h3>
            <p>Coba ubah kriteria filter atau reset filter untuk melihat semua perangkat.</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const backButton = document.querySelector('.back-button-img');
            if (backButton) {
                backButton.addEventListener('click', function() {
                    window.location.href = 'EDIT_ADMIN.html';
                });
            }

            // --- Client-side Filtering and Sorting Logic ---
            const towerFilter = document.getElementById('towerFilter');
            const lantaiFilter = document.getElementById('lantaiFilter');
            const urutFilter = document.getElementById('urutFilter');
            const dateFromFilter = document.getElementById('dateFromFilter');
            const dateToFilter = document.getElementById('dateToFilter');
            const applyFilterBtn = document.getElementById('applyFilter');
            const resetFilterBtn = document.getElementById('resetFilter');
            const filterInfo = document.getElementById('filterInfo');
            const deviceTableBody = document.getElementById('deviceTableBody');
            const allDeviceRows = Array.from(document.querySelectorAll('#deviceTableBody .device-row'));
            const noResultsMessage = document.getElementById('noResultsMessage');
            const noDevicesRow = document.getElementById('noDevicesRow'); 
            const sortOrderSelect = document.getElementById('sortOrder');

            // Function to parse the noUnit string into its components
            function parseNoUnit(noUnitString) {
                if (noUnitString && noUnitString.length === 5) {
                    const tower = noUnitString.substring(0, 1);
                    const lantai = parseInt(noUnitString.substring(1, 3));
                    const urut = parseInt(noUnitString.substring(3, 5));
                    return { tower: tower, lantai: lantai, urut: urut };
                }
                // Return defaults for invalid or short noUnit strings
                return { tower: '', lantai: NaN, urut: NaN }; 
            }

            function applyFilterAndSort() {
                const towerValue = towerFilter.value.trim();
                const lantaiInput = lantaiFilter.value.trim();
                const urutInput = urutFilter.value.trim();
                const dateFromValue = dateFromFilter.value; //YYYY-MM-DD format
                const dateToValue = dateToFilter.value;     //YYYY-MM-DD format
                const sortOrder = sortOrderSelect.value;

                // Use null for empty number inputs so we can distinguish from 0
                const lantaiFilterValue = lantaiInput ? parseInt(lantaiInput) : null;
                const urutFilterValue = urutInput ? parseInt(urutInput) : null;
                
                let visibleCount = 0;
                let filterTexts = [];
                let rowsToDisplay = [];

                // 1. Filter the original set of rows
                allDeviceRows.forEach(row => {
                    const noUnit = row.getAttribute('data-nounit');
                    const parsed = parseNoUnit(noUnit); // Parse the noUnit string for comparison
                    const tglPasang = row.getAttribute('data-tglpasang'); //YYYY-MM-DD format
                    
                    let showRow = true;
                    
                    // Filter by tower (if a tower is selected)
                    if (towerValue !== '' && parsed.tower !== towerValue) {
                        showRow = false;
                    }
                    
                    // Filter by lantai (if a value is entered)
                    if (lantaiFilterValue !== null && parsed.lantai !== lantaiFilterValue) {
                        showRow = false;
                    }
                    
                    // Filter by no. urut (if a value is entered)
                    if (urutFilterValue !== null && parsed.urut !== urutFilterValue) {
                        showRow = false;
                    }

                    // Filter by Tanggal Pasang (date range)
                    if (dateFromValue && tglPasang < dateFromValue) {
                        showRow = false;
                    }
                    if (dateToValue && tglPasang > dateToValue) {
                        showRow = false;
                    }
                    
                    if (showRow) {
                        rowsToDisplay.push(row);
                    }
                });

                // 2. Sort the filtered rows
                rowsToDisplay.sort((a, b) => {
                    const sortKey = sortOrder.split('-')[0]; // 'serialNum', 'noUnit', 'tglPasang'
                    const orderDirection = sortOrder.split('-')[1]; // 'asc' or 'desc'

                    let comparison;
                    if (sortKey === 'serialNum') {
                        const valA = a.getAttribute('data-serial');
                        const valB = b.getAttribute('data-serial');
                        comparison = valA.localeCompare(valB);
                    } else if (sortKey === 'noUnit') {
                        const valA = a.getAttribute('data-nounit');
                        const valB = b.getAttribute('data-nounit');
                        const parsedA = parseNoUnit(valA);
                        const parsedB = parseNoUnit(valB);

                        comparison = parsedA.tower.localeCompare(parsedB.tower);
                        if (comparison === 0) {
                            comparison = parsedA.lantai - parsedB.lantai;
                            if (comparison === 0) {
                                comparison = parsedA.urut - parsedB.urut;
                            }
                        }
                    } else if (sortKey === 'tglPasang') {
                        const valA = a.getAttribute('data-tglpasang');
                        const valB = b.getAttribute('data-tglpasang');
                        comparison = new Date(valA).getTime() - new Date(valB).getTime(); // Compare as timestamps
                    }

                    return orderDirection === 'asc' ? comparison : -comparison;
                });

                // 3. Clear existing table body content
                while (deviceTableBody.firstChild) {
                    deviceTableBody.removeChild(deviceTableBody.firstChild);
                }

                // 4. Append the sorted and filtered rows
                rowsToDisplay.forEach(row => {
                    deviceTableBody.appendChild(row);
                });
                
                visibleCount = rowsToDisplay.length;

                // Update filter info text
                if (filterTexts.length === 0) {
                    filterInfo.textContent = `Menampilkan semua perangkat (${visibleCount} perangkat).`;
                } else {
                    filterInfo.textContent = `Filter aktif: ${filterTexts.join(', ')} - Menampilkan ${visibleCount} perangkat.`;
                }

                // Show/hide no results message
                if (visibleCount === 0) {
                    noResultsMessage.style.display = 'block';
                    if (noDevicesRow) noDevicesRow.style.display = 'none'; // Hide initial no devices row if it exists
                } else {
                    noResultsMessage.style.display = 'none';
                    if (noDevicesRow) noDevicesRow.style.display = 'none'; // Always hide it if there are results
                }
            }

            function resetFilterAndSort() {
                towerFilter.value = '';
                lantaiFilter.value = '';
                urutFilter.value = '';
                dateFromFilter.value = '';
                dateToFilter.value = '';
                sortOrderSelect.value = 'serialNum-asc'; // Default sort order
                
                applyFilterAndSort();
            }

            // Event listeners for filter and sort actions
            applyFilterBtn.addEventListener('click', applyFilterAndSort);
            resetFilterBtn.addEventListener('click', resetFilterAndSort);
            sortOrderSelect.addEventListener('change', applyFilterAndSort); // Keep this to sort immediately on change

            // Initial setup on page load
            applyFilterAndSort(); 
            // Handle initial 'no devices' row visibility
            if (allDeviceRows.length === 0 && noDevicesRow) {
                noDevicesRow.style.display = ''; // Show if no devices are loaded
            } else if (noDevicesRow) {
                noDevicesRow.style.display = 'none'; // Hide if devices are loaded
            }
        });
    </script>
</body>
</html>