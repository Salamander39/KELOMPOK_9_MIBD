<?php
session_start();
require_once 'config.php';

// --- DELETE Operation ---
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];

    sqlsrv_begin_transaction($conn);

    try {
        //DELETE FROM KEPEMILIKAN
        $stmt_kepemilikan_del = sqlsrv_query($conn, "DELETE FROM kepemilikan WHERE idPengguna = ?", array($id));
        if ($stmt_kepemilikan_del === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        //DELETE FROM PEMILIK
        $stmt_pemilik_del = sqlsrv_query($conn, "DELETE FROM pemilik WHERE idPengguna = ?", array($id));
        if ($stmt_pemilik_del === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        //DELETE FROM PENGGUNA
        $stmt_pengguna_del = sqlsrv_query($conn, "DELETE FROM Pengguna WHERE idPengguna = ?", array($id));
        if ($stmt_pengguna_del === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        sqlsrv_commit($conn);
        header("Location: edit_pemilik.php");
        exit;
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
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
    // Removed $pass = $_POST['passRusun'];
    $peran = $_POST['peran'];

    sqlsrv_begin_transaction($conn);

    try {
        // Updated query: Removed passRusun from the UPDATE statement
        $stmt_pemilik_update = sqlsrv_query($conn, "UPDATE pemilik SET NIK=?, nama=?, alamat=?, noPonsel=? WHERE idPengguna=?",
            array($nik, $nama, $alamat, $ponsel, $id));
        if ($stmt_pemilik_update === false) {
            throw new Exception("Error updating pemilik: " . print_r(sqlsrv_errors(), true));
        }

        $stmt_pengguna_update = sqlsrv_query($conn, "UPDATE Pengguna SET peran=? WHERE idPengguna=?", array($peran, $id));
        if ($stmt_pengguna_update === false) {
            throw new Exception("Error updating Pengguna: " . print_r(sqlsrv_errors(), true));
        }

        sqlsrv_commit($conn);
        header("Location: edit_pemilik.php");
        exit;
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        die("Error updating data: " . $e->getMessage());
    }
}

// --- INSERT Operation ---
if (isset($_POST['insert'])) {
    $nik = $_POST['NIK'];
    $nama = $_POST['nama'];
    $alamat = $_POST['alamat'];
    $ponsel = $_POST['noPonsel'];
    // Removed $pass = $_POST['passRusun'];
    $peran = $_POST['peran'];

    sqlsrv_begin_transaction($conn);

    try {
        $stmt_pengguna_insert = sqlsrv_query($conn,
            "INSERT INTO Pengguna (peran) OUTPUT INSERTED.idPengguna VALUES (?)",
            array($peran)
        );

        if ($stmt_pengguna_insert === false) {
            throw new Exception("Error executing INSERT into Pengguna: " . print_r(sqlsrv_errors(), true));
        }

        $insertedPenggunaRow = sqlsrv_fetch_array($stmt_pengguna_insert, SQLSRV_FETCH_ASSOC);

        if ($insertedPenggunaRow === false || !isset($insertedPenggunaRow['idPengguna'])) {
            throw new Exception("Failed to retrieve generated idPengguna from 'Pengguna' table. Result: " . print_r($insertedPenggunaRow, true));
        }
        $idPengguna = $insertedPenggunaRow['idPengguna'];

        // Updated query: Removed passRusun from the INSERT statement
        $stmt_pemilik_insert = sqlsrv_query($conn,
            "INSERT INTO pemilik (NIK, idPengguna, nama, alamat, noPonsel) VALUES (?, ?, ?, ?, ?)",
            array($nik, $idPengguna, $nama, $alamat, $ponsel)
        );

        if ($stmt_pemilik_insert === false) {
            throw new Exception("Error executing INSERT into pemilik: " . print_r(sqlsrv_errors(), true));
        }

        sqlsrv_commit($conn);
        header("Location: edit_pemilik.php");
        exit;

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        die("Error during insert: " . $e->getMessage());
    }
}

// --- FETCH Operation with Filter and Sort ---
$search_query_parts = array();
$params = array();

// Apply search filter if present
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = '%' . $_GET['search'] . '%';
    // 'p.alamat LIKE ?' is kept here as requested
    $search_query_parts[] = " (p.NIK LIKE ? OR p.nama LIKE ? OR p.alamat LIKE ? OR p.noPonsel LIKE ? OR g.peran LIKE ?) ";
    $params = array_merge($params, array($search_term, $search_term, $search_term, $search_term, $search_term));
}

// Default sort order
$order_by = "p.idPengguna ASC";
if (isset($_GET['sort_order'])) {
    switch ($_GET['sort_order']) {
        case 'id_asc':
            $order_by = "p.idPengguna ASC";
            break;
        case 'id_desc':
            $order_by = "p.idPengguna DESC";
            break;
        case 'nik_asc':
            $order_by = "p.NIK ASC";
            break;
        case 'nik_desc':
            $order_by = "p.NIK DESC";
            break;
        case 'nama_asc':
            $order_by = "p.nama ASC";
            break;
        case 'nama_desc':
            $order_by = "p.nama DESC";
            break;
        // Removed 'alamat' cases, but 'alamat' is still searchable and displayed
        case 'ponsel_asc':
            $order_by = "p.noPonsel ASC";
            break;
        case 'ponsel_desc':
            $order_by = "p.noPonsel DESC";
            break;
        default:
            $order_by = "p.idPengguna ASC"; // Fallback to default
            break;
    }
}


$sql = "
SELECT
    p.idPengguna,
    p.NIK,
    p.nama,
    p.alamat, -- Keep p.alamat in the select as requested
    p.noPonsel,
    -- Removed p.passRusun,
    COALESCE(g.peran, 'none') as peran
FROM pemilik p
LEFT JOIN Pengguna g ON p.idPengguna = g.idPengguna
";

if (!empty($search_query_parts)) {
    $sql .= " WHERE " . implode(" AND ", $search_query_parts);
}

$sql .= " ORDER BY " . $order_by;

$result = sqlsrv_query($conn, $sql, $params);

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
        input, select { width: 100%; padding: 5px; box-sizing: border-box; }
        .btn {
            padding: 5px 10px;
            margin: 2px;
            cursor: pointer;
            border-radius: 3px;
            display: inline-block;
            min-width: 75px;
            text-align: center;
            box-sizing: border-box;
            font-size: 16px;
            font-weight: normal;
            font-family: 'Offside', cursive;
        }
        .btn-danger { background-color: #dc3545; color: white; border: 1px solid #dc3545; }
        .btn-danger:hover { background-color: #c82333; }
        .btn-success { background-color: #28a745; color: white; border: 1px solid #28a745; }
        .btn-success:hover { background-color: #218838; }
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
        .search-container {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .search-container form {
            display: flex;
            flex-grow: 1;
            gap: 10px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .search-container input[type="text"] {
            flex-grow: 1;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            min-width: 150px;
        }
        .search-container button {
            padding: 8px 15px;
            background-color: rgb(1, 138, 56);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .search-container button:hover {
            background-color: rgb(1, 110, 45);
        }

        /* Style for the sort control within the search container */
        .sort-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
            white-space: nowrap;
        }
        .sort-group label {
            font-size: 0.9rem;
            color: #555;
            font-weight: bold;
            margin-bottom: 0;
        }
        .sort-group select {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.9rem;
            background-color: white;
            color: #333;
            min-width: 120px;
            box-sizing: border-box;
            height: 34px;
            line-height: 18px;
        }
        .sort-group select:hover {
            border-color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Data Pemilik</h1>
            <button class="back-button-img"> <img src="images/back2.png" alt="Kembali" />
            </button>
        </div>

        <div class="search-container">
            <form method="GET" style="display: flex; width: 100%; gap: 10px; align-items: flex-end;">
                <input type="text" name="search" placeholder="Mencari berdasarkan NIK, Nama, Alamat, No Ponsel, atau Peran" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                
                <div class="sort-group">
                    <label for="sort_order">Urutkan:</label>
                    <select id="sort_order" name="sort_order">
                        <option value="id_asc" <?= (isset($_GET['sort_order']) && $_GET['sort_order'] == 'id_asc') ? 'selected' : '' ?>>ID Pengguna (A-Z)</option>
                        <option value="id_desc" <?= (isset($_GET['sort_order']) && $_GET['sort_order'] == 'id_desc') ? 'selected' : '' ?>>ID Pengguna (Z-A)</option>
                        <option value="nik_asc" <?= (isset($_GET['sort_order']) && $_GET['sort_order'] == 'nik_asc') ? 'selected' : '' ?>>NIK (A-Z)</option>
                        <option value="nik_desc" <?= (isset($_GET['sort_order']) && $_GET['sort_order'] == 'nik_desc') ? 'selected' : '' ?>>NIK (Z-A)</option>
                        <option value="nama_asc" <?= (isset($_GET['sort_order']) && $_GET['sort_order'] == 'nama_asc') ? 'selected' : '' ?>>Nama (A-Z)</option>
                        <option value="nama_desc" <?= (isset($_GET['sort_order']) && $_GET['sort_order'] == 'nama_desc') ? 'selected' : '' ?>>Nama (Z-A)</option>
                        <option value="ponsel_asc" <?= (isset($_GET['sort_order']) && $_GET['sort_order'] == 'ponsel_asc') ? 'selected' : '' ?>>No Ponsel (A-Z)</option>
                        <option value="ponsel_desc" <?= (isset($_GET['sort_order']) && $_GET['sort_order'] == 'ponsel_desc') ? 'selected' : '' ?>>No Ponsel (Z-A)</option>
                    </select>
                </div>

                <button type="submit">Cari</button>
                <?php if (isset($_GET['search']) || isset($_GET['sort_order'])): ?>
                    <button type="button" onclick="window.location.href='edit_pemilik.php'">Reset</button>
                <?php endif; ?>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID Pengguna</th>
                    <th>NIK</th>
                    <th>Nama</th>
                    <th>Alamat</th>
                    <th>No Ponsel</th>
                    <th>Peran</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <form method="POST" class="inline-form">
                        <td>#</td>
                        <td><input type="text" name="NIK" placeholder="NIK" required></td>
                        <td><input type="text" name="nama" placeholder="Nama" required></td>
                        <td><input type="text" name="alamat" placeholder="Alamat" required></td>
                        <td><input type="text" name="noPonsel" placeholder="No Ponsel" required></td>
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
                            <td title="<?= htmlspecialchars($row['idPengguna']) ?>">
                                <?= htmlspecialchars($row['idPengguna']) ?>
                                <input type="hidden" name="idPengguna" value="<?= $row['idPengguna'] ?>">
                            </td>
                            <td><input type="text" name="NIK" value="<?= htmlspecialchars($row['NIK']) ?>" required title="<?= htmlspecialchars($row['NIK']) ?>"></td>
                            <td><input type="text" name="nama" value="<?= htmlspecialchars($row['nama']) ?>" required title="<?= htmlspecialchars($row['nama']) ?>"></td>
                            <td><input type="text" name="alamat" value="<?= htmlspecialchars($row['alamat']) ?>" required title="<?= htmlspecialchars($row['alamat']) ?>"></td>
                            <td><input type="text" name="noPonsel" value="<?= htmlspecialchars($row['noPonsel']) ?>" required title="<?= htmlspecialchars($row['noPonsel']) ?>"></td>
                            <td>
                                <select name="peran" required title="<?= htmlspecialchars($row['peran']) ?>">
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
                    // Adjusted colspan to 7 (8 original columns - 1 password column)
                    echo "<tr><td colspan='7'>Error loading data or no data available.</td></tr>";
                }
                ?>
            </tbody>
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