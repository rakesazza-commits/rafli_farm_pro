<?php
// File: koneksi.php
// Konfigurasi Database XAMPP
$host = "localhost";
$user = "root";
$password = ""; // Default XAMPP kosong
$database = "rafli_farm_pro_db";

// Membuat koneksi menggunakan MySQLi
$koneksi = new mysqli($host, $user, $password, $database);

// Cek jika koneksi gagal
if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}
?>