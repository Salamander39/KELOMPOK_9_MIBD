<?php
session_start();
require_once 'config.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    die("Unauthorized");
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $noUnit = $_POST['noUnit'] ?? '';
    $idPengguna = $_POST['idPengguna'] ?? null;

    if (empty($noUnit)) {
        $message = '<p style="color: red;">Nomor Unit tidak boleh kosong.</p>';
    } else {
        $checkSql = "SELECT noUnit FROM Kepemilikan WHERE noUnit = ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, array($noUnit));
        if ($checkStmt === false) {
            $message = '<p style="color: red;">Error checking existing unit: ' . print_r(sqlsrv_errors(), true) . '</p>';
        } else if (sqlsrv_has_rows($checkStmt)) {
            $message = '<p style="color: red;">Nomor Unit tersebut sudah ada.</p>';
        } else {
            $idPenggunaParam = (!empty($idPengguna)) ? $idPengguna : NULL;

            $insertSql = "INSERT INTO Kepemilikan (noUnit, idPengguna) VALUES (?, ?)";
            $insertParams = array($noUnit, $idPenggunaParam);
            $insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);

            if ($insertStmt === false) {
                $message = '<p style="color: red;">Gagal menambahkan unit: ' . print_r(sqlsrv_errors(), true) . '</p>';
            } else {
                $message = '<p style="color: green;">Unit berhasil ditambahkan!</p>';
            }
        }
    }
}

$pemilikList = [];
$pemilikSql = "SELECT idPengguna, nama FROM pemilik ORDER BY nama ASC";
$pemilikStmt = sqlsrv_query($conn, $pemilikSql);
if ($pemilikStmt === false) {
    die(print_r(sqlsrv_errors(), true));
}
while ($row = sqlsrv_fetch_array($pemilikStmt, SQLSRV_FETCH_ASSOC)) {
    $pemilikList[] = $row;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Tambah Unit Baru</title>
    <link href="https://fonts.googleapis.com/css?family=Offside&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Offside', cursive;
            background-color: rgb(208, 208, 208);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            width: 50%;
            background: #fff;
            padding: 30px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        h2 {
            text-align: center;
            color: rgb(1, 138, 56);
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input[type="text"],
        .form-group select {
            width: calc(100% - 22px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        .form-group input[type="submit"] {
            background-color: rgb(1, 138, 56);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            width: 100%;
        }
        .form-group input[type="submit"]:hover {
            filter: brightness(1.1);
        }
        .back-button {
            display: block;
            width: fit-content;
            margin: 20px auto 0;
            background-color: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-align: center;
            text-decoration: none;
            font-size: 1rem;
        }
        .back-button:hover {
            filter: brightness(1.1);
        }
        .message {
            text-align: center;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

    <div class="container">
        <h2>Tambah Unit Baru</h2>
        <div class="message"><?= $message ?></div>
        <form action="" method="POST">
            <div class="form-group">
                <label for="noUnit">Nomor Unit:</label>
                <input type="text" id="noUnit" name="noUnit" required>
            </div>
            <div class="form-group">
                <label for="idPengguna">ID Pengguna (Pemilik):</label>
                <select id="idPengguna" name="idPengguna">
                    <option value="">Tidak Ditetapkan</option>
                    <?php foreach ($pemilikList as $pemilik): ?>
                        <option value="<?= htmlspecialchars($pemilik['idPengguna']) ?>">
                            <?= htmlspecialchars($pemilik['nama']) ?> (<?= htmlspecialchars($pemilik['idPengguna']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <input type="submit" value="Tambah Unit">
            </div>
        </form>
        <a href="edit_prusun.php" class="back-button">Kembali ke Daftar Unit</a>
    </div>

</body>
</html>