<?php
session_start();
require_once 'config.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    die("Unauthorized");
}

$sql = "
    SELECT 
        k.noUnit,
        COALESCE(k.idPengguna, 'Tidak Ditetapkan') AS idPenggunaUntukTampilan 
    FROM Kepemilikan k
    ORDER BY k.noUnit ASC
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$units = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $units[] = $row;
}

// Check for and display session messages
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
    }
  </style>
</head>
<body>

  <div class="container">
    <div class="header">
      <h2>Daftar Unit Sarusun</h2>
      <div>
        <a href="add_unit.php" class="add-button">Tambah Unit Baru</a>
        <button class="exit-button" title="Kembali ke Admin Menu">
          <img src="images/back2.png" alt="Kembali" />
        </button>
      </div>
    </div>

    <?php if (!empty($message)): ?>
    <div class="message"><?= $message ?></div>
    <?php endif; ?>

    <table id="unitListTable">
      <tr>
        <th>No Unit</th>
        <th>ID Pengguna</th>
        <th>Aksi</th>
      </tr>
      <?php if (empty($units)): ?>
      <tr>
        <td colspan="3">Tidak ada data unit tersedia.</td>
      </tr>
      <?php else: ?>
      <?php foreach ($units as $unit): ?>
      <tr>
        <td><?= htmlspecialchars($unit['noUnit']) ?></td>
        <td><?= htmlspecialchars($unit['idPenggunaUntukTampilan']) ?></td>
        <td>
          <a href="detail_unit.php?noUnit=<?= urlencode($unit['noUnit']) ?>" class="detail-button">Detail</a>
          <button class="delete-button" data-unit="<?= htmlspecialchars($unit['noUnit']) ?>">Hapus</button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </table>
  </div>

  <script src="./common.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('.delete-button');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const noUnit = this.dataset.unit;
                if (confirm(`Apakah Anda yakin ingin menghapus unit ${noUnit}?`)) {
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
    });
  </script>
</body>
</html>