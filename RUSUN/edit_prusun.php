<?php
session_start();
require_once 'config.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    // Redirect to login page if not authorized
    header('Location: login.php'); // Make sure 'login.php' is your actual login page
    exit();
}

// Select noUnit and idPengguna from the Kepemilikan table
// The initial query will fetch all units. Filtering and sorting will be done on the client-side (JavaScript).
$sql = "
    SELECT
        k.noUnit,
        k.idPengguna
    FROM Kepemilikan k
    ORDER BY k.noUnit ASC
"; // Initial order remains ASC for the fetched data

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    // Log detailed error for debugging, and provide a generic message to the user
    error_log("Error fetching units: " . print_r(sqlsrv_errors(), true));
    die("Terjadi kesalahan saat memuat daftar unit. Silakan coba lagi nanti.");
}

$units = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $units[] = $row;
}

// Check for and display session messages (e.g., from add_unit.php or assign_owner.php)
$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear the message after displaying it
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
      background-color: rgb(208, 208, 208);
    }
    .container {
      width: 80%;
      margin: 20px auto;
      background: #fff;
      padding: 20px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
      border-radius: 8px;
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
    .add-button, .action-button, .detail-button {
      background-color: rgb(1, 138, 56);
      color: white;
      padding: 8px 15px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 1rem;
      text-decoration: none;
      display: inline-block;
      margin-left: 5px;
    }
    .add-button:hover, .action-button:hover, .detail-button:hover {
      filter: brightness(1.1);
    }
    .delete-button {
        background-color: #f44336;
        color: white;
        padding: 8px 15px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 1rem;
        text-decoration: none;
        display: inline-block;
        margin-left: 5px;
    }
    .delete-button:hover {
        filter: brightness(1.1);
    }
    /* New style for the Assign ID button */
    .assign-id-button {
        background-color: #007bff; /* Blue */
        color: white;
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.9rem;
        text-decoration: none;
        display: inline-block;
        margin: 2px 0; /* Add a little margin if needed */
    }
    .assign-id-button:hover {
        background-color: #0056b3;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    th, td {
      padding: 12px;
      border: 1px solid #ddd;
      text-align: center;
    }
    th {
      background-color: rgb(1, 138, 56);
      color: white;
    }
    tr:nth-child(even) {
      background-color: #f2f2f2;
    }
    tr:hover {
      background-color: #ddd;
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
    .message {
        text-align: center;
        margin-bottom: 10px;
        font-weight: bold;
        color: rgb(1, 138, 56); /* Green for success messages */
        background-color: #e6ffe6; /* Light green background */
        border: 1px solid rgb(1, 138, 56);
        padding: 8px;
        border-radius: 5px;
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

    .filter-row { /* This row will contain filter inputs, buttons, and sort dropdown */
        display: flex;
        gap: 15px; /* Space between flex items */
        align-items: flex-end; /* Aligns items at the bottom of the line */
        flex-wrap: wrap;
        justify-content: space-between; /* Pushes the left group to left, right group to right */
    }

    .filter-input-groups { /* New wrapper for Tower, Lantai, Urut filters */
        display: flex;
        gap: 15px;
        align-items: flex-end; /* Align inputs themselves at the bottom */
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
        width: 100px;
    }

    .filter-actions-and-sort { /* New wrapper for buttons and sort dropdown */
        display: flex;
        gap: 10px; /* Space between buttons and sort dropdown */
        align-items: flex-end; /* Align filter buttons and sort dropdown at the bottom */
        flex-wrap: wrap;
    }

    .filter-buttons { /* Contains only Terapkan Filter and Reset buttons */
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
        display: none;
    }

    /* Style for the sort control (no longer a separate .sort-container) */
    .sort-group { /* Group for label and select of sort */
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
      <h2>Daftar Unit Sarusun</h2>
      <div>
        <a href="add_unit.php" class="add-button">Tambah Unit Baru</a>
        <button class="exit-button"> <img src="images/back2.png" alt="Kembali" />
        </button>
      </div>
    </div>

    <?php if (!empty($message)): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

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
                    </select>
                </div>
            </div>
        </div>

        <div class="filter-info" id="filterInfo">
            Menampilkan semua unit. Gunakan filter di atas untuk menyaring unit berdasarkan Tower, Lantai, atau No Urut.
        </div>
    </div>

    <table id="unitListTable">
      <thead>
        <tr>
          <th>No Unit</th>
          <th>ID Pengguna</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody id="unitTableBody">
        <?php if (empty($units)): ?>
        <tr class="no-units-row"> <td colspan="3">Tidak ada data unit tersedia.</td>
        </tr>
        <?php else: ?>
        <?php foreach ($units as $unit): ?>
        <tr class="unit-row" data-unit="<?= htmlspecialchars($unit['noUnit']) ?>"> 
            <td><?= htmlspecialchars($unit['noUnit']) ?></td>
            <td>
                <?php if (empty($unit['idPengguna'])): ?>
                    <a href="assign_owner.php?noUnit=<?= urlencode($unit['noUnit']) ?>" class="assign-id-button">Assign</a>
                <?php else: ?>
                    <?= htmlspecialchars($unit['idPengguna']) ?>
                <?php endif; ?>
            </td>
            <td>
                <a href="detail_unit.php?noUnit=<?= urlencode($unit['noUnit']) ?>" class="detail-button">Detail</a>
                <button class="delete-button" data-unit="<?= htmlspecialchars($unit['noUnit']) ?>">Hapus</button>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <div id="noResultsMessage" class="no-results-message">
        <h3>Tidak ada unit yang sesuai dengan filter</h3>
        <p>Coba ubah kriteria filter atau reset filter untuk melihat semua unit.</p>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('.delete-button');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const noUnit = this.dataset.unit;
                if (confirm(`Apakah Anda ingin menghapus unit ${noUnit}?`)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'delete_unit.php';

                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'noUnit';
                    input.value = noUnit;
                    form.appendChild(input);

                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });

        const exitButton = document.querySelector('.exit-button');
        if (exitButton) {
            exitButton.addEventListener('click', function() {
                window.location.href = 'EDIT_ADMIN.html';
            });
        }

        // --- Client-side Filtering and Sorting Logic ---
        const towerFilter = document.getElementById('towerFilter');
        const lantaiFilter = document.getElementById('lantaiFilter');
        const urutFilter = document.getElementById('urutFilter');
        const applyFilterBtn = document.getElementById('applyFilter');
        const resetFilterBtn = document.getElementById('resetFilter');
        const filterInfo = document.getElementById('filterInfo');
        const unitTableBody = document.getElementById('unitTableBody');
        const allUnitRows = Array.from(document.querySelectorAll('#unitTableBody .unit-row'));
        const noUnitsRow = document.querySelector('.no-units-row');
        const noResultsMessage = document.getElementById('noResultsMessage');
        const sortOrderSelect = document.getElementById('sortOrder');

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
            let rowsToDisplay = [];

            // 1. Filter the original set of rows
            allUnitRows.forEach(row => {
                const unitNo = row.getAttribute('data-unit');
                const parsed = parseUnitNumber(unitNo);
                
                let showRow = true;
                
                if (towerValue && parsed.tower !== towerValue) {
                    showRow = false;
                }
                
                if (lantaiFilterValue !== null && parsed.lantai !== lantaiFilterValue) {
                    showRow = false;
                }
                
                if (urutFilterValue !== null && parsed.urut !== urutFilterValue) {
                    showRow = false;
                }
                
                if (showRow) {
                    rowsToDisplay.push(row);
                }
            });

            // 2. Sort the filtered rows
            const order = sortOrderSelect.value;
            rowsToDisplay.sort((a, b) => {
                const unitA = a.getAttribute('data-unit');
                const unitB = b.getAttribute('data-unit');
                let comparison = unitA.localeCompare(unitB);

                return order === 'asc' ? comparison : -comparison;
            });

            // 3. Clear existing table body content
            while (unitTableBody.firstChild) {
                unitTableBody.removeChild(unitTableBody.firstChild);
            }

            // 4. Append the sorted and filtered rows
            rowsToDisplay.forEach(row => {
                unitTableBody.appendChild(row);
            });
            
            visibleCount = rowsToDisplay.length;

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
                if (noUnitsRow) noUnitsRow.style.display = 'none';
            } else {
                noResultsMessage.style.display = 'none';
                if (noUnitsRow) noUnitsRow.style.display = 'none';
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
        if (allUnitRows.length === 0 && noUnitsRow) {
            noUnitsRow.style.display = '';
        } else if (noUnitsRow) {
            noUnitsRow.style.display = 'none';
        }
    });
  </script>
</body>
</html>