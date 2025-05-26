<?php
session_start();
require_once 'config.php';

// Pastikan user sudah login
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: page1.html');
    exit();
}
$userRole = $_SESSION['userRole'] ?? null; //Ambil Peran User

//Kueri Riwayat Aksi
if ($userRole === 'Admin') {
    $sql = "
    SELECT idPengguna, ketAksi, waktu
    FROM LogAksi
    ORDER BY waktu DESC
    ";
    $isAdmin = true;
}else{
    $sql = "
    SELECT idPengguna, ketAksi, waktu
    FROM LogAksi
    WHERE idPengguna = ?
    ORDER BY waktu DESC
    ";
    $isAdmin = false;
}

$params = [$userId];
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Ambil data ke array
$logs = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $logs[] = [
      'idPengguna' => $row['idPengguna'],
      'ketAksi' => $row['ketAksi'],
      'waktu'   => $row['waktu']->format('Y-m-d H:i:s')
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
      background: #f5f5f5;
      margin: 0; padding: 20px;
    }
    h2 {
      text-align: center;
      color: #014d24;
    }
    table {
      width: 90%;
      max-width: 800px;
      margin: 20px auto;
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
  </style>
</head>
<body>
  <button class="exit-button">
    <img src="images/back2.png" alt="Exit">
  </button>

  <h2>Riwayat Aksi Anda</h2>
  <table>
    <tr>
      <?php if ($isAdmin): ?>
        <th>ID Pengguna</th>
      <?php endif; ?>
      <th>Deskripsi Aksi</th>
      <th>Waktu</th>
    </tr>
    <?php if (empty($logs)): ?>
      <tr><td colspan="2" style="text-align:center; padding:20px;">Belum ada riwayat aksi.</td></tr>
    <?php else: ?>
      <?php foreach ($logs as $log): ?>
        <tr>
        <?php if ($isAdmin): ?>
            <td><?= htmlspecialchars($log['idPengguna']) ?></td>
        <?php endif; ?>

          <td><?= htmlspecialchars($log['ketAksi']) ?></td>
          <td><?= htmlspecialchars($log['waktu']) ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </table>

  <script src="./common.js"></script>
</body>
</html>