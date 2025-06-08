<?php
session_start();
require_once 'config.php';

$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['userRole'] ?? null;

// Query untuk mengambil SEMUA unit dan aktuator (pengelola bisa lihat semua)
// The initial query will fetch all units. Filtering and sorting will be done on the client-side (JavaScript).
$sql = "
    SELECT
        s.noUnit,
        p.serialNum,
        p.tglPasang,
        COALESCE(a.statusAkt, 'OFF') as statusAkt,
        k.idPengguna as ownerId
    FROM Sarusun s
    LEFT JOIN PerangkatIOT p ON s.noUnit = p.noUnit AND SUBSTRING(p.serialNum, 3, 1) = '2'
    LEFT JOIN Kepemilikan k ON s.noUnit = k.noUnit
    LEFT JOIN Aktuator a ON p.serialNum = a.serialNumAkt
    ORDER BY s.noUnit, p.serialNum
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Organize data by unit
$units = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $unitNo = $row['noUnit'];
    
    // Initialize unit entry if it doesn't exist
    if (!isset($units[$unitNo])) {
        $units[$unitNo] = [
            'noUnit' => $row['noUnit'],
            'ownerId' => $row['ownerId'], // Add ownerId to the unit level for filtering
            'actuators' => []
        ];
    }
    
    // Only add actuators if they exist for the unit
    if ($row['serialNum']) {
        $units[$unitNo]['actuators'][] = [
            'serialNum' => $row['serialNum'],
            // Ensure tglPasang is formatted as a string for consistency with client-side parsing
            'tglPasang' => $row['tglPasang'] ? $row['tglPasang']->format('Y-m-d') : 'N/A',
            'statusAkt' => $row['statusAkt']
        ];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengendalian Air</title>
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

        .unit-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .unit-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .unit-info h3 {
            margin: 0;
            color: #333;
        }

        .unit-status {
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #495057;
        }

        .actuators-container {
            padding: 20px;
        }

        .no-actuators {
            text-align: center;
            color: #666;
            padding: 40px 20px;
        }

        .actuator-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .actuator-table th,
        .actuator-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .actuator-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #495057;
        }

        .actuator-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .status-on {
            background: #d4edda;
            color: #155724;
        }

        .status-off {
            background: #f8d7da;
            color: #721c24;
        }

        .control-button {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .control-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-turn-on {
            background: #28a745;
            color: white;
        }

        .btn-turn-on:hover:not(:disabled) {
            background: #218838;
        }

        .btn-turn-off {
            background: #dc3545;
            color: white;
        }

        .btn-turn-off:hover:not(:disabled) {
            background: #c82333;
        }

        .owner-info {
            font-size: 0.8rem;
            color: #666;
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
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
            width: 100px; /* Adjust as needed */
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

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .actuator-table {
                font-size: 0.8rem;
            }

            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-input-groups, .filter-actions-and-sort, .filter-buttons {
                flex-direction: column;
                gap: 10px;
            }

            .filter-group select,
            .filter-group input {
                width: 100%; /* Make inputs full width on smaller screens */
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Pengendalian Air - Semua Unit</h1>
        <button id="back-btn" class="exit-button">Kembali</button>
    </div>

    <div class="container">
        <div class="filter-container">
            <h3 class="filter-title">Filter Unit</h3>
            
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
                </div>

                <div class="filter-actions-and-sort">
                    <div class="filter-buttons">
                        <button id="applyFilter" class="filter-btn btn-filter">Terapkan Filter</button>
                        <button id="resetFilter" class="filter-btn btn-reset">Reset</button>
                    </div>
                    <div class="sort-group">
                        <label for="sortOrder">Urutkan:</label>
                        <select id="sortOrder">
                            <option value="asc">No Unit (A-Z)</option>
                            <option value="desc">No Unit (Z-A)</option>
                            <option value="date-asc">Tanggal Pasang (Terlama)</option>
                            <option value="date-desc">Tanggal Pasang (Terbaru)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="filter-info" id="filterInfo">
                Menampilkan semua unit. Gunakan filter di atas untuk menyaring unit berdasarkan Tower, Lantai, atau No Urut.
            </div>
        </div>

        <div id="unitCardsContainer">
            <?php if (empty($units)): ?>
                <div class="unit-card no-units-overall">
                    <div class="no-actuators">
                        <h3>Tidak ada data unit</h3>
                        <p>Belum ada unit yang terdaftar dalam sistem.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($units as $unit): ?>
                    <div class="unit-card unit-row" data-unit="<?= htmlspecialchars($unit['noUnit']) ?>">
                        <div class="unit-header">
                            <div class="unit-info">
                                <h3>Unit <?= htmlspecialchars($unit['noUnit']) ?></h3>
                            </div>
                            <div class="unit-status">
                                <?= count($unit['actuators']) ?> Aktuator Terpasang
                            </div>
                        </div>
                        
                        <div class="actuators-container">
                            <?php if (empty($unit['actuators'])): ?>
                                <div class="no-actuators">
                                    <p>Tidak ada aktuator yang terpasang di unit ini</p>
                                </div>
                            <?php else: ?>
                                <table class="actuator-table">
                                    <thead>
                                        <tr>
                                            <th>Serial Number</th>
                                            <th>Tanggal Pasang</th>
                                            <th>Status</th>
                                            <th>Pemilik</th>
                                            <th>Kontrol</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($unit['actuators'] as $actuator): ?>
                                            <tr data-serial="<?= htmlspecialchars($actuator['serialNum']) ?>">
                                                <td><?= htmlspecialchars($actuator['serialNum']) ?></td>
                                                <td><?= htmlspecialchars($actuator['tglPasang']) ?></td>
                                                <td>
                                                    <span class="status-badge <?= $actuator['statusAkt'] === 'ON' ? 'status-on' : 'status-off' ?>">
                                                        <?= htmlspecialchars($actuator['statusAkt']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="owner-info">
                                                        User ID: <?= htmlspecialchars($unit['ownerId'] ?? 'N/A') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="control-button <?= $actuator['statusAkt'] === 'ON' ? 'btn-turn-off' : 'btn-turn-on' ?>"
                                                            onclick="toggleActuator('<?= htmlspecialchars($actuator['serialNum']) ?>', '<?= $actuator['statusAkt'] ?>', this)">
                                                        <?= $actuator['statusAkt'] === 'ON' ? 'TUTUP' : 'BUKA' ?>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div id="noResultsMessage" class="no-results-message">
            <h3>Tidak ada unit yang sesuai dengan filter</h3>
            <p>Coba ubah kriteria filter atau reset filter untuk melihat semua unit.</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const backBtn = document.getElementById('back-btn');
            
            backBtn.addEventListener('click', () => {
                window.location.href = './HOME_Pengelola.html';
            });

            // --- Client-side Filtering and Sorting Logic ---
            const towerFilter = document.getElementById('towerFilter');
            const lantaiFilter = document.getElementById('lantaiFilter');
            const urutFilter = document.getElementById('urutFilter');
            const applyFilterBtn = document.getElementById('applyFilter');
            const resetFilterBtn = document.getElementById('resetFilter');
            const filterInfo = document.getElementById('filterInfo');
            const unitCardsContainer = document.getElementById('unitCardsContainer');
            const allUnitCards = Array.from(document.querySelectorAll('#unitCardsContainer .unit-row'));
            const noResultsMessage = document.getElementById('noResultsMessage');
            const sortOrderSelect = document.getElementById('sortOrder');
            const noUnitsOverall = document.querySelector('.no-units-overall'); // The "no units overall" card

            function parseUnitNumber(unitNo) {
                const tower = unitNo.substring(0, 1);
                const lantai = parseInt(unitNo.substring(1, 3));
                const urut = parseInt(unitNo.substring(3, 5));
                return { tower: tower, lantai: lantai, urut: urut };
            }

            function applyFilterAndSort() {
                const towerValue = towerFilter.value.trim();
                const lantaiInput = lantaiFilter.value.trim();
                const urutInput = urutFilter.value.trim();
                
                const lantaiFilterValue = lantaiInput ? parseInt(lantaiInput) : null;
                const urutFilterValue = urutInput ? parseInt(urutInput) : null;
                
                let visibleCount = 0;
                let filterTexts = [];
                let cardsToDisplay = [];

                // Hide the "no units overall" message if it exists
                if (noUnitsOverall) {
                    noUnitsOverall.style.display = 'none';
                }

                // 1. Filter the original set of cards
                allUnitCards.forEach(card => {
                    const unitNo = card.getAttribute('data-unit');
                    const parsed = parseUnitNumber(unitNo);
                    
                    let showCard = true;
                    
                    if (towerValue && parsed.tower !== towerValue) {
                        showCard = false;
                    }
                    
                    if (lantaiFilterValue !== null && parsed.lantai !== lantaiFilterValue) {
                        showCard = false;
                    }
                    
                    if (urutFilterValue !== null && parsed.urut !== urutFilterValue) {
                        showCard = false;
                    }
                    
                    if (showCard) {
                        cardsToDisplay.push(card);
                    }
                });

                // 2. Sort the filtered cards
                const order = sortOrderSelect.value;
                cardsToDisplay.sort((a, b) => {
                    if (order === 'asc' || order === 'desc') {
                        const unitA = a.getAttribute('data-unit');
                        const unitB = b.getAttribute('data-unit');
                        let comparison = unitA.localeCompare(unitB);
                        return order === 'asc' ? comparison : -comparison;
                    } else if (order === 'date-asc' || order === 'date-desc') {
                        // Get all actuator dates for unit A and B
                        const datesA = Array.from(a.querySelectorAll('.actuator-table tbody tr'))
                                        .map(row => row.children[1].textContent)
                                        .filter(date => date !== 'N/A'); // Filter out N/A if any

                        const datesB = Array.from(b.querySelectorAll('.actuator-table tbody tr'))
                                        .map(row => row.children[1].textContent)
                                        .filter(date => date !== 'N/A');

                        // If no dates, consider them equal for sorting purposes
                        if (datesA.length === 0 && datesB.length === 0) return 0;
                        if (datesA.length === 0) return order === 'date-asc' ? 1 : -1; // No date means it's "later" for asc, "earlier" for desc
                        if (datesB.length === 0) return order === 'date-asc' ? -1 : 1;

                        // Sort dates within each unit to pick the earliest/latest as the primary sort key
                        // This ensures consistent picking of min/max if there are multiple dates
                        datesA.sort();
                        datesB.sort();

                        const primaryDateA = order === 'date-asc' ? datesA[0] : datesA[datesA.length - 1];
                        const primaryDateB = order === 'date-desc' ? datesB[0] : datesB[datesB.length - 1];
                        
                        // Compare the primary dates
                        let dateComparison = new Date(primaryDateA) - new Date(primaryDateB);

                        return order === 'date-asc' ? dateComparison : -dateComparison;

                    }
                    return 0; // Default case, should not be reached if options are handled
                });

                // 3. Clear existing container content
                while (unitCardsContainer.firstChild) {
                    unitCardsContainer.removeChild(unitCardsContainer.firstChild);
                }

                // 4. Append the sorted and filtered cards
                cardsToDisplay.forEach(card => {
                    unitCardsContainer.appendChild(card);
                });
                
                visibleCount = cardsToDisplay.length;

                // Update filter info text
                if (towerValue) filterTexts.push(`Tower: ${towerValue}`);
                if (lantaiInput) filterTexts.push(`Lantai: ${parseInt(lantaiInput)}`);
                if (urutInput) filterTexts.push(`No Urut: ${parseInt(urutInput)}`);

                if (filterTexts.length === 0) {
                    filterInfo.textContent = `Menampilkan semua unit (${visibleCount} unit).`;
                } else {
                    filterInfo.textContent = `Filter aktif: ${filterTexts.join(', ')} - Menampilkan ${visibleCount} unit.`;
                }

                // Show/hide no results message
                if (visibleCount === 0) {
                    noResultsMessage.style.display = 'block';
                } else {
                    noResultsMessage.style.display = 'none';
                }
            }

            function resetFilterAndSort() {
                towerFilter.value = '';
                lantaiFilter.value = '';
                urutFilter.value = '';
                sortOrderSelect.value = 'asc';
                
                applyFilterAndSort();
            }

            // Event listeners for filter and sort actions
            applyFilterBtn.addEventListener('click', applyFilterAndSort);
            resetFilterBtn.addEventListener('click', resetFilterAndSort);
            sortOrderSelect.addEventListener('change', applyFilterAndSort);

            // Apply filter on Enter key for input fields
            [lantaiFilter, urutFilter].forEach(input => {
                input.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        applyFilterAndSort();
                    }
                });
            });

            // Initial setup on page load
            applyFilterAndSort(); 
        });

        async function toggleActuator(serialNum, currentStatus, button) {
            const newStatus = currentStatus === 'ON' ? 'OFF' : 'ON';
            const row = button.closest('tr');
            const statusBadge = row.querySelector('.status-badge');
            
            // Set loading state
            button.disabled = true;
            button.textContent = 'PROSES...';
            
            try {
                const response = await fetch('update_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        serial: serialNum,
                        status: newStatus
                    })
                });
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Gagal mengubah status aktuator');
                }
                
                // Update UI
                statusBadge.textContent = newStatus;
                statusBadge.className = `status-badge ${newStatus === 'ON' ? 'status-on' : 'status-off'}`;
                
                button.className = `control-button ${newStatus === 'ON' ? 'btn-turn-off' : 'btn-turn-on'}`;
                button.textContent = newStatus === 'ON' ? 'TUTUP' : 'BUKA';
                
                // Update button onclick
                button.onclick = () => toggleActuator(serialNum, newStatus, button);
                
                alert(`Aktuator ${serialNum} berhasil ${newStatus === 'ON' ? 'dibuka' : 'ditutup'}`);
                
            } catch (error) {
                console.error('Error:', error);
                alert(`Gagal mengubah status: ${error.message}`);
            } finally {
                button.disabled = false;
            }
        }
    </script>
    <script src="./common.js"></script>
</body>
</html>