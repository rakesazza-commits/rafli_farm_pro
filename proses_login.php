<?php
// File: proses_login.php
session_start();
include 'koneksi.php';

// Cek apakah form di-submit via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Validasi kosong
    if (empty($username) || empty($password)) {
        header("Location: login.php?error=empty");
        exit;
    }

    // Anti SQL Injection
    $username = $koneksi->real_escape_string($username);

    // Cari user di database
    $sql = "SELECT * FROM users WHERE username = '$username'";
    $result = $koneksi->query($sql);

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verifikasi password (BCRYPT)
        if (password_verify($password, $user['password'])) {
            
            // Login berhasil! Simpan ke session
            $_SESSION['login'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];

            // Arahkan ke dashboard
            header("Location: dashboard.php");
            exit;

        } else {
            // Password salah
            header("Location: login.php?error=invalid");
            exit;
        }

    } else {
        // Username tidak ditemukan
        header("Location: login.php?error=invalid");
        exit;
    }

} else {
    // Jika diakses langsung tanpa POST
    header("Location: login.php");
    exit;
}
?>