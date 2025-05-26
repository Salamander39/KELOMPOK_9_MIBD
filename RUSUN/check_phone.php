<?php
session_start(); // Sesi awal PHP
//if (session_status() !== PHP_SESSION_ACTIVE) {
 //   session_regenerate_id(true);
//}

$serverName = "LODAYA";
$connectionOptions = [
    "Database" => "RUSUNAMI",
    "Uid" => "",
    "PWD" => ""
];

//Mencoba membuat koneksi ke database SQL
$conn = sqlsrv_connect($serverName, $connectionOptions);

//Cek koneksi database (Jika gagal)
if (!$conn) {
    http_response_code(500);
    echo "ERROR: " . print_r(sqlsrv_errors(), true);
    exit;
}

//
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['phone'])) {
    $phone = $_POST['phone'];

    //Kueri untuk mencari informasi peran pengguna dengan join
    // '?' adalah placeholder untuk nilai variabel $phone mencegah SQL Injection
    $sql = "SELECT Pengguna.Peran, Pemilik.idPengguna 
            FROM Pemilik 
            JOIN Pengguna ON Pemilik.idPengguna = Pengguna.idPengguna 
            WHERE Pemilik.noPonsel = ?"; // **SESUAIKAN NAMA TABEL DAN KOLOM**
    
    //Mengambil koneksi  , kueri , dan parameter
    $stmt = sqlsrv_query($conn, $sql, [$phone]);

    //Eksekusi Kueri gagal
    if ($stmt === false) {
        http_response_code(500);
        echo "ERROR: " . print_r(sqlsrv_errors(), true);
        exit;
    }

    //Terdapat Nomor
    if (sqlsrv_has_rows($stmt)) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $role = $row['Peran'];
        $userId = $row['idPengguna']; // Dapatkan ID pengguna

        //Menyimpan peran penggna dalam sesi (Berguna untuk menyimpan aman tanpa melakukan pencarian kueri ulang)
        $_SESSION['temp_user_id_for_otp_verification'] = $userId;
        $_SESSION['userRole'] = $role;
        $_SESSION['phone_for_otp_verification'] = $phone; 

        echo "OK|" . $role; // Kirim "OK" dan peran ke frontend
    } else {
        echo "NOT_FOUND"; //No telp tidak ditemukan
    }
    // Membebaskan sumber daya statement kueri dan menutup koneksi database.
    // Ini adalah praktik yang baik untuk efisiensi dan mencegah kebocoran memori.
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
} else { // Jika permintaan bukan POST atau tidak ada data 'phone' yang dikirim.
    http_response_code(400); //Bad REquest
    echo "Invalid request";
}
?>