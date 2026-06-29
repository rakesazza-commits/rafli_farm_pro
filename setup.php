<?php
// File: setup.php
include 'koneksi.php';

// 1. Membuat Tabel Users
$sql_tabel = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($koneksi->query($sql_tabel) === TRUE) {
    echo "✅ Tabel 'users' berhasil dibuat.<br>";
} else {
    echo "❌ Error membuat tabel: " . $koneksi->error . "<br>";
}

// 2. Memasukkan User Default (rafli / 07052003)
$username = "rafli";
$password_asli = "07052003";
// Hash password agar aman (menggunakan BCRYPT)
$password_hash = password_hash($password_asli, PASSWORD_DEFAULT);
$nama = "Rafli Farm Pro";
$role = "admin";

// Cek apakah user sudah ada
$cek_user = $koneksi->query("SELECT * FROM users WHERE username = '$username'");

if ($cek_user->num_rows == 0) {
    $sql_insert = "INSERT INTO users (username, password, nama_lengkap, role) 
                   VALUES ('$username', '$password_hash', '$nama', '$role')";
    
    if ($koneksi->query($sql_insert) === TRUE) {
        echo "✅ User default <b>'rafli'</b> berhasil ditambahkan!<br>";
    } else {
        echo "❌ Error menambahkan user: " . $koneksi->error . "<br>";
    }
} else {
    echo "️ User 'rafli' sudah ada di database.<br>";
}

echo "<br><hr><br>";
echo "<h3>🚀 Setup Selesai!</h3>";
echo "<p>Silakan hapus file <code>setup.php</code> ini setelah selesai demi keamanan.</p>";
echo "<p><a href='login.php' style='padding: 10px 20px; background: #00FF88; color: #000; text-decoration: none; border-radius: 5px; font-weight: bold;'>Ke Halaman Login</a></p>";
?>