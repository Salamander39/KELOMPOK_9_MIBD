<?php
session_start();
require_once 'config.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: login.php'); // Redirect to login page if not authorized
    exit();
}

$message = '';
$noUnit = $_GET['noUnit'] ?? null; 

// If noUnit is missing, stop execution or redirect
if (!$noUnit) {
    die("Error: Nomor Unit tidak disediakan untuk penugasan.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedIdPengguna = $_POST['idPengguna'] ?? '';
    $unitToUpdate = $_POST['noUnit'] ?? ''; // Hidden field from the form

    // Ensure noUnit from hidden field matches the one from GET to prevent tampering
    if ($unitToUpdate !== $noUnit) {
        $message = '<p style="color: red;">Kesalahan validasi unit. Harap coba lagi.</p>';
    } else {
        // Convert empty string from select option to NULL for database
        $idPenggunaParam = !empty($selectedIdPengguna) ? $selectedIdPengguna : NULL;

        // SQL to update idPengguna for the specific noUnit
        $updateSql = "UPDATE Kepemilikan 
                      SET idPengguna = ? 
                      WHERE noUnit = ?";
        $updateParams = [$idPenggunaParam, $noUnit];
        $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);

        if ($updateStmt === false) {
            error_log("Error updating idPengguna for unit " . $noUnit . ": " . print_r(sqlsrv_errors(), true));
            $message = '<p style="color: red;">Gagal memperbarui ID Pengguna: ' . print_r(sqlsrv_errors(), true) . '</p>';
        } else {
            $_SESSION['message'] = "ID Pengguna berhasil diperbarui untuk unit " . htmlspecialchars($noUnit) . "!";
            header("Location: edit_prusun.php"); // Redirect back
            exit();
        }
    }
}

// Fetch list of existing owners (pemilik) for the dropdown
$pemilikList = [];

$pemilikSql = " SELECT idPengguna, nama 
                FROM pemilik 
                ORDER BY idPengguna ASC"; 


$pemilikStmt = sqlsrv_query($conn, $pemilikSql);
if ($pemilikStmt === false) {
    error_log("Error fetching pemilik list: " . print_r(sqlsrv_errors(), true));
    die("Terjadi kesalahan saat memuat daftar pemilik.");
}
while ($row = sqlsrv_fetch_array($pemilikStmt, SQLSRV_FETCH_ASSOC)) {
    $pemilikList[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Assign ID Pengguna</title>
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
            max-width: 500px; /* Limit width */
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
        .form-group select {
            width: 100%; /* Make select fill the width */
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            box-sizing: border-box; /* Include padding and border in the element's total width and height */
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
            font-weight: bold;
        }
        /* Specific styles for message types */
        .message p {
            margin: 0;
            padding: 8px;
            border-radius: 5px;
        }
        .message p[style*="color: red"] { /* For PHP messages */
            background-color: #f8d7da;
            border: 1px solid #dc3545;
            color: #721c24;
        }
    </style>
</head>
<body>

    <div class="container">
        <h2>Assign ID Pengguna to Unit <?= htmlspecialchars($noUnit) ?></h2>
        <?php if (!empty($message)): ?>
            <div class="message"><?= $message ?></div>
        <?php endif; ?>
        <form action="" method="POST">
            <input type="hidden" name="noUnit" value="<?= htmlspecialchars($noUnit) ?>">
            <div class="form-group">
                <label for="idPengguna">Pilih ID Pengguna:</label>
                <select id="idPengguna" name="idPengguna" required>
                    <option value="">-- Pilih Pemilik --</option>
                    <?php foreach ($pemilikList as $pemilik): ?>
                        <option value="<?= htmlspecialchars($pemilik['idPengguna']) ?>">
                            <?= htmlspecialchars($pemilik['nama']) ?> (<?= htmlspecialchars($pemilik['idPengguna']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <input type="submit" value="Assign ID Pengguna">
            </div>
        </form>
        <a href="list_unit.php" class="back-button">Kembali ke Daftar Unit</a>
    </div>

</body>
</html>