<?php
$serverName = "LODAYA";
$connectionOptions = array(
    "Database" => "RUSUNAMI",
    "Uid" => "", // your SQL Server username
    "PWD" => ""  // your password
);

$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}

// --- DELETE Operation ---
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];

    // Start transaction for delete
    sqlsrv_begin_transaction($conn);

    try {
        
        // Delete from kepemilikan (identified child table)
        // Ensure this is deleted before 'pemilik' if it references 'pemilik.idPengguna'
        $stmt_kepemilikan_del = sqlsrv_query($conn, "DELETE FROM kepemilikan WHERE idPengguna = ?", array($id));
        if ($stmt_kepemilikan_del === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        // Delete from LogAksi (newly identified child table)
        // Ensure this is deleted before 'pemilik' if it references 'pemilik.idPengguna'
        $stmt_logaksi_del = sqlsrv_query($conn, "DELETE FROM LogAksi WHERE idPengguna = ?", array($id));
        if ($stmt_logaksi_del === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        // Delete from pemilik (parent table)
        $stmt_pemilik_del = sqlsrv_query($conn, "DELETE FROM pemilik WHERE idPengguna = ?", array($id));
        if ($stmt_pemilik_del === false) {
            throw new Exception(print_r(sqsrv_errors(), true));
        }

           // Delete from Pengguna (child table first due to foreign key constraints)
        $stmt_pengguna_del = sqlsrv_query($conn, "DELETE FROM Pengguna WHERE idPengguna = ?", array($id));
        if ($stmt_pengguna_del === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        sqlsrv_commit($conn); // Commit if all deletions are successful
        header("Location: edit_pemilik.php");
        exit;
    } catch (Exception $e) {
        sqlsrv_rollback($conn); // Rollback on error
        die("Error deleting data: " . $e->getMessage());
    }
}

// --- UPDATE Operation ---
if (isset($_POST['update'])) {
    $id = $_POST['idPengguna'];
    $nik = $_POST['NIK'];
    $nama = $_POST['nama'];
    $alamat = $_POST['alamat'];
    $ponsel = $_POST['noPonsel'];
    $pass = $_POST['passRusun'];
    $peran = $_POST['peran'];

    // Start transaction for update
    sqlsrv_begin_transaction($conn);

    try {
        $stmt_pemilik_update = sqlsrv_query($conn, "UPDATE pemilik SET NIK=?, nama=?, alamat=?, noPonsel=?, passRusun=? WHERE idPengguna=?",
            array($nik, $nama, $alamat, $ponsel, $pass, $id));
        if ($stmt_pemilik_update === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        $stmt_pengguna_update = sqlsrv_query($conn, "UPDATE Pengguna SET peran=? WHERE idPengguna=?", array($peran, $id));
        if ($stmt_pengguna_update === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        sqlsrv_commit($conn); // Commit if both updates are successful
        header("Location: edit_pemilik.php");
        exit;
    } catch (Exception $e) {
        sqlsrv_rollback($conn); // Rollback on error
        die("Error updating data: " . $e->getMessage());
    }
}

// --- INSERT Operation ---
if (isset($_POST['insert'])) {
    $nik = $_POST['NIK'];
    $nama = $_POST['nama'];
    $alamat = $_POST['alamat'];
    $ponsel = $_POST['noPonsel'];
    $pass = $_POST['passRusun'];
    $peran = $_POST['peran'];

    // Start a transaction for the insert operation
    sqlsrv_begin_transaction($conn);

    try {
        // Insert into 'pemilik' table and get the auto-generated idPengguna
        $stmt_pemilik_insert = sqlsrv_query($conn,
            "INSERT INTO pemilik (NIK, nama, alamat, noPonsel, passRusun) OUTPUT INSERTED.idPengguna VALUES (?, ?, ?, ?, ?)",
            array($nik, $nama, $alamat, $ponsel, $pass)
        );

        if ($stmt_pemilik_insert === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        // Fetch the inserted idPengguna
        $insertedRow = sqlsrv_fetch_array($stmt_pemilik_insert, SQLSRV_FETCH_ASSOC);
        if ($insertedRow === null || !isset($insertedRow['idPengguna'])) {
            throw new Exception("Failed to retrieve inserted idPengguna from 'pemilik'.");
        }
        $idPengguna = $insertedRow['idPengguna'];

        // Insert into 'Pengguna' table using the retrieved idPengguna
        $stmt_pengguna_insert = sqlsrv_query($conn, "INSERT INTO Pengguna (idPengguna, peran) VALUES (?, ?)", array($idPengguna, $peran));

        if ($stmt_pengguna_insert === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        // Commit the transaction if all operations are successful
        sqlsrv_commit($conn);
        header("Location: edit_pemilik.php");
        exit;

    } catch (Exception $e) {
        // Rollback the transaction on any error
        sqlsrv_rollback($conn);
        die("Error during insert: " . $e->getMessage());
    }
}

// --- FETCH Operation ---
$sql = "
SELECT
    p.idPengguna,
    p.NIK,
    p.nama,
    p.alamat,
    p.noPonsel,
    p.passRusun,
    COALESCE(g.peran, 'none') as peran
FROM pemilik p
LEFT JOIN Pengguna g ON p.idPengguna = g.idPengguna
ORDER BY p.idPengguna ASC -- Changed to ASC for ascending order
";
$result = sqlsrv_query($conn, $sql);

// Check if query executed successfully
if ($result === false) {
    die(print_r(sqlsrv_errors(), true));
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Pemilik</title>
    <link href="https://fonts.googleapis.com/css?family=Offside&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Offside', cursive; 
            background-color: rgb(208, 208, 208); 
            padding: 20px; 
            margin: 0;
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
        .header h1 {
            margin: 0;
            color: rgb(1, 138, 56);
        }
        table { 
            border-collapse: collapse; 
            width: 100%; 
            background: white; 
            margin-top: 20px;
        }
        th, td { 
            border: 1px solid #ccc; 
            padding: 10px; 
            text-align: left; 
        }
        th { 
            background-color: rgb(1, 138, 56); 
            color: white; 
        }
        tr:nth-child(even) { background-color: #f9f9f9; }
        form.inline-form { display: inline; }
        input, select { width: 100%; padding: 5px; box-sizing: border-box; } /* Added box-sizing */
        .btn { 
            padding: 5px 10px; 
            margin: 2px; 
            cursor: pointer; 
            border-radius: 3px; 
            display: inline-block; /* Ensure consistent display behavior */
            min-width: 75px; /* Set a minimum width for consistency */
            text-align: center; /* Center text within the button */
            box-sizing: border-box; /* Include padding and border in the element's total width and height */
            font-size: 16px; /* Explicitly set font size */
            font-weight: normal; /* Ensure consistent font weight */
        }
        .btn-danger { background-color: #dc3545; color: white; border: 1px solid #dc3545; }
        .btn-danger:hover { background-color: #c82333; }
        .btn-success { background-color: #28a745; color: white; border: 1px solid #28a745; }
        .btn-success:hover { background-color: #218838; }

        /* Styles for the image back button */
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Data Pemilik</h1>
            <button class="back-button-img" title="Kembali">
                <img src="images/back2.png" alt="Kembali" />
            </button>
        </div>

        <table>
            <tr>
                <th>ID Pengguna</th>
                <th>NIK</th>
                <th>Nama</th>
                <th>Alamat</th>
                <th>No Ponsel</th>
                <th>Password Rusun</th>
                <th>Peran</th>
                <th>Aksi</th>
            </tr>

            <tr>
                <form method="POST" class="inline-form">
                    <td>#</td> <td><input type="text" name="NIK" placeholder="NIK" required></td>
                    <td><input type="text" name="nama" placeholder="Nama" required></td>
                    <td><input type="text" name="alamat" placeholder="Alamat" required></td>
                    <td><input type="text" name="noPonsel" placeholder="No Ponsel" required></td>
                    <td><input type="text" name="passRusun" placeholder="Password Rusun" required></td>
                    <td>
                        <select name="peran" required>
                            <option value="none">none</option>
                            <option value="admin">admin</option>
                            <option value="pengelola">pengelola</option>
                        </select>
                    </td>
                    <td><button type="submit" name="insert" class="btn btn-success">Tambah</button></td>
                </form>
            </tr>

            <?php
            if ($result) {
                while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)): ?>
                <tr>
                    <form method="POST" class="inline-form">
                        <td><?= htmlspecialchars($row['idPengguna']) ?><input type="hidden" name="idPengguna" value="<?= $row['idPengguna'] ?>"></td>
                        <td><input type="text" name="NIK" value="<?= htmlspecialchars($row['NIK']) ?>" required></td>
                        <td><input type="text" name="nama" value="<?= htmlspecialchars($row['nama']) ?>" required></td>
                        <td><input type="text" name="alamat" value="<?= htmlspecialchars($row['alamat']) ?>" required></td>
                        <td><input type="text" name="noPonsel" value="<?= htmlspecialchars($row['noPonsel']) ?>" required></td>
                        <td><input type="text" name="passRusun" value="<?= htmlspecialchars($row['passRusun']) ?>" required></td>
                        <td>
                            <select name="peran" required>
                                <option value="none" <?= $row['peran'] === 'none' ? 'selected' : '' ?>>none</option>
                                <option value="admin" <?= $row['peran'] === 'admin' ? 'selected' : '' ?>>admin</option>
                                <option value="pengelola" <?= $row['peran'] === 'pengelola' ? 'selected' : '' ?>>pengelola</option>
                            </select>
                        </td>
                        <td>
                            <div style="display: flex;">
                                <button type="submit" name="update" class="btn btn-success">Update</button>
                                <a href="?delete_id=<?= $row['idPengguna'] ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus data ini?')">Hapus</a>
                            </div>
                        </td>
                    </form>
                </tr>
                <?php endwhile;
            } else {
                echo "<tr><td colspan='8'>Error loading data or no data available.</td></tr>";
            }
            ?>
        </table>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const backButton = document.querySelector('.back-button-img');
            if (backButton) {
                backButton.addEventListener('click', function() {
                    window.location.href = 'EDIT_ADMIN.html'; 
                });
            }
        });
    </script>
</body>
</html>