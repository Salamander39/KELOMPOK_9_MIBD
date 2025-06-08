<?php
session_start();
require_once 'config.php'; // Make sure config.php has your database connection

// Pastikan user sudah login
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: page1.html');
    exit();
}
$userRole = $_SESSION['userRole'] ?? null; //Ambil Peran User

// Query untuk mengambil semua data log
// Filtering and sorting will be done client-side with JavaScript
if ($userRole === 'Admin') {
    $sql = "
    SELECT idPengguna, ketAksi, waktu
    FROM LogAksi
    ORDER BY waktu DESC
    ";
    $isAdmin = true;
    $stmt = sqlsrv_query($conn, $sql);
} else {
    // For non-admin, restrict query to their own logs
    $sql = "
    SELECT idPengguna, ketAksi, waktu
    FROM LogAksi
    WHERE idPengguna = ?
    ORDER BY waktu DESC
    ";
    $isAdmin = false;
    $params = [$userId];
    $stmt = sqlsrv_query($conn, $sql, $params);
}

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Ambil data ke array
$logs = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $logs[] = [
      'idPengguna' => $row['idPengguna'],
      'ketAksi' => $row['ketAksi'],
      'waktu'   => $row['waktu'] instanceof DateTime ? $row['waktu']->format('Y-m-d H:i:s') : 'N/A',
      'waktuSortable' => $row['waktu'] instanceof DateTime ? $row['waktu']->format('Y-m-d') : '' // For date filtering/sorting
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Riwayat Aksi</title>
    <link href="https://fonts.googleapis.com/css?family=Offside&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Offside', cursive;
            background: rgb(208, 208, 208);
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            width: 90%;
            max-width: 900px;
            margin-top: 20px;
            background: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            margin-bottom: 30px;
        }
        h2 {
            text-align: center;
            color: #014d24;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #018A38;
            color: #fff;
            text-transform: uppercase;
            font-size: 14px;
        }
        tr:hover {
            background: #f0f8ff;
        }
        tr:last-child td {
            border-bottom: none;
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

        .exit-button:hover img{
            filter: brightness(1.1);
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

        .filter-group input[type="text"],
        .filter-group input[type="date"],
        .sort-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .filter-group input[type="date"] {
            width: 130px;
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
    </style>
</head>
<body>
    <button class="exit-button">
        <img src="images/back2.png" alt="Exit">
    </button>

    <div class="container">
        <h2>Riwayat Aksi Anda</h2>

        <div class="filter-container">
            <div class="filter-row">
                <div class="filter-input-groups">
                    <?php if ($isAdmin): ?>
                    <div class="filter-group">
                        <label for="idPenggunaFilter">ID Pengguna:</label>
                        <input type="text" id="idPenggunaFilter" placeholder="Cari ID Pengguna">
                    </div>
                    <?php endif; ?>
                    <div class="filter-group">
                        <label for="dateFromFilter">Waktu Dari:</label>
                        <input type="date" id="dateFromFilter">
                    </div>
                    <div class="filter-group">
                        <label for="dateToFilter">Waktu Hingga:</label>
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
                            <?php if ($isAdmin): ?>
                                <option value="idPengguna-asc">ID Pengguna (A-Z)</option>
                                <option value="idPengguna-desc">ID Pengguna (Z-A)</option>
                            <?php endif; ?>
                            <option value="waktu-desc">Waktu (Terbaru)</option>
                            <option value="waktu-asc">Waktu (Terlama)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="filter-info" id="filterInfo">
                Menampilkan semua riwayat aksi. Gunakan filter di atas untuk menyaring berdasarkan ID Pengguna atau rentang waktu.
            </div>
        </div>

        <table>
            <thead>
                <tr id="tableHeaderRow">
                    <?php if ($isAdmin): ?>
                        <th>ID Pengguna</th>
                    <?php endif; ?>
                    <th>Deskripsi Aksi</th>
                    <th>Waktu</th>
                </tr>
            </thead>
            <tbody id="logTableBody">
                <?php if (empty($logs)): ?>
                    <tr id="noLogsRow"><td colspan="<?= $isAdmin ? '3' : '2' ?>" style="text-align:center; padding:20px;">Belum ada riwayat aksi.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr class="log-row"
                            data-idpengguna="<?= htmlspecialchars($log['idPengguna']) ?>"
                            data-waktu="<?= htmlspecialchars($log['waktuSortable']) ?>">
                            <?php if ($isAdmin): ?>
                                <td><?= htmlspecialchars($log['idPengguna']) ?></td>
                            <?php endif; ?>
                            <td><?= htmlspecialchars($log['ketAksi']) ?></td>
                            <td><?= htmlspecialchars($log['waktu']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div id="noResultsMessage" class="no-results-message">
            <h3>Tidak ada riwayat aksi yang sesuai dengan filter</h3>
            <p>Coba ubah kriteria filter atau reset filter untuk melihat semua riwayat.</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const backButton = document.querySelector('.exit-button');
            if (backButton) {
                backButton.addEventListener('click', function() {
                    // Navigate back based on user role
                    const isAdmin = <?= json_encode($isAdmin); ?>;
                    if (isAdmin) {
                        window.location.href = 'EDIT_ADMIN.html'; // Admin goes to admin dashboard
                    } else {
                        window.location.href = 'dashboard.php'; // Regular user goes to their dashboard
                    }
                });
            }

            // Get isAdmin status from PHP
            const isAdmin = <?= json_encode($isAdmin); ?>;

            const idPenggunaFilter = document.getElementById('idPenggunaFilter'); // May be null if not admin
            const dateFromFilter = document.getElementById('dateFromFilter');
            const dateToFilter = document.getElementById('dateToFilter');
            const applyFilterBtn = document.getElementById('applyFilter');
            const resetFilterBtn = document.getElementById('resetFilter');
            const filterInfo = document.getElementById('filterInfo');
            const logTableBody = document.getElementById('logTableBody');
            const allLogRows = Array.from(document.querySelectorAll('#logTableBody .log-row'));
            const noResultsMessage = document.getElementById('noResultsMessage');
            const noLogsRow = document.getElementById('noLogsRow');
            const sortOrderSelect = document.getElementById('sortOrder');

            function applyFilterAndSort() {
                // If not admin, idPenggunaValue should always be empty
                const idPenggunaValue = isAdmin && idPenggunaFilter ? idPenggunaFilter.value.trim().toLowerCase() : '';
                const dateFromValue = dateFromFilter.value; // YYYY-MM-DD format
                const dateToValue = dateToFilter.value;     // YYYY-MM-DD format
                const sortOrder = sortOrderSelect.value;

                let visibleCount = 0;
                let filterTexts = [];
                let rowsToDisplay = [];

                // 1. Filter the original set of rows
                allLogRows.forEach(row => {
                    const idPengguna = row.getAttribute('data-idpengguna').toLowerCase();
                    const waktu = row.getAttribute('data-waktu'); // YYYY-MM-DD format

                    let showRow = true;

                    // Filter by ID Pengguna (only if admin and input is not empty)
                    if (isAdmin && idPenggunaValue !== '' && !idPengguna.includes(idPenggunaValue)) {
                        showRow = false;
                    }

                    // Filter by Waktu (date range)
                    if (dateFromValue && waktu < dateFromValue) {
                        showRow = false;
                    }
                    if (dateToValue && waktu > dateToValue) {
                        showRow = false;
                    }

                    if (showRow) {
                        rowsToDisplay.push(row);
                    }
                });

                // 2. Sort the filtered rows
                rowsToDisplay.sort((a, b) => {
                    const sortKey = sortOrder.split('-')[0]; // 'idPengguna', 'waktu'
                    const orderDirection = sortOrder.split('-')[1]; // 'asc' or 'desc'

                    let comparison;
                    if (sortKey === 'idPengguna') { // Only for admins
                        const valA = a.getAttribute('data-idpengguna');
                        const valB = b.getAttribute('data-idpengguna');
                        comparison = valA.localeCompare(valB);
                    } else if (sortKey === 'waktu') {
                        const valA = a.getAttribute('data-waktu'); // YYYY-MM-DD
                        const valB = b.getAttribute('data-waktu'); // YYYY-MM-DD
                        // Parse as dates for accurate comparison
                        comparison = new Date(valA).getTime() - new Date(valB).getTime();
                    }

                    return orderDirection === 'asc' ? comparison : -comparison;
                });

                // 3. Clear existing table body content
                while (logTableBody.firstChild) {
                    logTableBody.removeChild(logTableBody.firstChild);
                }

                // 4. Append the sorted and filtered rows
                rowsToDisplay.forEach(row => {
                    logTableBody.appendChild(row);
                });

                visibleCount = rowsToDisplay.length;

                // Update filter info text
                if (isAdmin && idPenggunaValue !== '') filterTexts.push(`ID Pengguna: "${idPenggunaFilter.value.trim()}"`);
                if (dateFromValue || dateToValue) {
                    let dateRangeText = 'Waktu: ';
                    if (dateFromValue && dateToValue) {
                        dateRangeText += `${dateFromValue} hingga ${dateToValue}`;
                    } else if (dateFromValue) {
                        dateRangeText += `Dari ${dateFromValue}`;
                    } else if (dateToValue) {
                        dateRangeText += `Hingga ${dateToValue}`;
                    }
                    filterTexts.push(dateRangeText);
                }

                if (filterTexts.length === 0) {
                    filterInfo.textContent = `Menampilkan semua riwayat aksi (${visibleCount} riwayat).`;
                } else {
                    filterInfo.textContent = `Filter aktif: ${filterTexts.join(', ')} - Menampilkan ${visibleCount} riwayat.`;
                }

                // Show/hide no results message
                if (visibleCount === 0) {
                    noResultsMessage.style.display = 'block';
                    if (noLogsRow) noLogsRow.style.display = 'none'; // Hide initial no logs row if it exists
                } else {
                    noResultsMessage.style.display = 'none';
                    if (noLogsRow) noLogsRow.style.display = 'none'; // Always hide it if there are results
                }
            }

            function resetFilterAndSort() {
                if (isAdmin && idPenggunaFilter) { // Check if idPenggunaFilter exists for admin
                    idPenggunaFilter.value = '';
                }
                dateFromFilter.value = '';
                dateToFilter.value = '';
                // Set default sort based on role
                sortOrderSelect.value = 'waktu-desc';

                applyFilterAndSort();
            }

            // Event listeners for filter and sort actions
            applyFilterBtn.addEventListener('click', applyFilterAndSort);
            resetFilterBtn.addEventListener('click', resetFilterAndSort);
            sortOrderSelect.addEventListener('change', applyFilterAndSort); // Keep this to sort immediately on change

            // Initial setup on page load
            applyFilterAndSort();
            // Handle initial 'no logs' row visibility
            if (allLogRows.length === 0 && noLogsRow) {
                noLogsRow.style.display = ''; // Show if no logs are loaded
            } else if (noLogsRow) {
                noLogsRow.style.display = 'none'; // Hide if logs are loaded
            }
        });
    </script>
    <script src="./common.js"></script>
</body>
</html>