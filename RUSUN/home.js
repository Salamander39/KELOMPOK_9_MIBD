document.addEventListener('DOMContentLoaded', function() {

    // Tombol Profil - Ada di semua halaman HOME
    const profilButton = document.querySelector('.profil-btn');
    if (profilButton) {
        profilButton.addEventListener('click', function() {
            window.location.href = 'PROFIL.html'; // Arahkan ke halaman PROFIL.html
        });
    }

    // Tombol Aktivitas - Ada di semua halaman HOME
    const aktivitasButton = document.querySelector('.aktivitas-btn');
    if (aktivitasButton) {
        aktivitasButton.addEventListener('click', function() {
            //alert('Mengunjungi halaman Aktivitas!'); //TEMP
            // REDIRECT disini
            window.location.href = 'riwayat_aksi.php'; 
        });
    }

    // Tombol Unit - Ada di semua halaman HOME
    const unitButton = document.querySelector('.unit-btn');
    if (unitButton) {
        unitButton.addEventListener('click', function() {
            window.location.href = 'listUnit.php';
        });
    }

    // --- Tombol Khusus untuk HOME_Admin.html ---
    // (Jika ada elemen dengan class 'data-btn' di halaman saat ini)
    const dataButton = document.querySelector('.data-btn');
    if (dataButton) {
        dataButton.addEventListener('click', function() {
            window.location.href = 'EDIT_ADMIN.html';
             //alert('Mengunjungi halaman Data (Admin)!'); // TEMP
        });
    }

      // --- Tombol Khusus untuk HOME_Admin.html ---
    // (Jika ada elemen dengan class 'sim-btn' di halaman saat ini)
    const simulasiButton = document.querySelector('.sim-btn');
    if (simulasiButton) {
        simulasiButton.addEventListener('click', function() {
            window.location.href = 'simulasi_air.php';
            //alert('Mengunjungi halaman Simulasi (Admin)!'); // TEMP
        });
    }

    // --- Tombol Khusus untuk HOME_Pengelola.html ---
    // (Jika ada elemen dengan class 'pengendalian-btn' di halaman saat ini)
    const pengendalianButton = document.querySelector('.pengendalian-btn');
    if (pengendalianButton) {
        pengendalianButton.addEventListener('click', function() {
            //alert('Mengunjungi halaman Pengendalian Air (Pengelola)!'); //TEMP
            // REDIRECT
            window.location.href = 'pengendalianAir.php';
        });
    }

    // --- Tombol Khusus untuk HOME_Pengelola.html ---
    // (Jika ada elemen dengan class 'pengendalian-btn' di halaman saat ini)
    const pemakaianButton = document.querySelector('.pemakaian-btn');
    if (pemakaianButton) {
        pemakaianButton.addEventListener('click', function() {
            //alert('Mengunjungi halaman Pemakaian Air (Pengelola)!'); //TEMP
            // REDIRECT
            window.location.href = 'pemakaianAirAll.php';
        });
    }

});