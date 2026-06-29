<?php
// File: track_visitor.php
// Script ini akan mencatat setiap kunjungan ke database

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'koneksi.php';

// Ambil data pengunjung
$ip_address = $_SERVER['REMOTE_ADDR'];

// Jika diakses via localhost, IP akan 127.0.0.1 atau ::1
// Jika diakses via jaringan lokal (HP ke Laptop), IP akan seperti 192.168.1.x
// Jika sudah online, IP akan menjadi IP publik asli pengunjung

$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
$current_page = $_SERVER['REQUEST_URI'];

// Simpan ke database
$stmt = $koneksi->prepare("INSERT INTO visitor_logs (ip_address, user_agent, page_visited) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $ip_address, $user_agent, $current_page);
$stmt->execute();
$stmt->close();
?>