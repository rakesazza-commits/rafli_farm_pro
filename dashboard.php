<?php
session_start();
include 'koneksi.php';
include 'track_visitor.php';

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login.php");
    exit;
}

$nama_user = $_SESSION['nama_lengkap'];
$username = $_SESSION['username'];

// ===== FILTER LOGIC =====
$filter_date = isset($_GET['date']) && $_GET['date'] ? $_GET['date'] : date('Y-m-d');
$filter_lahan = isset($_GET['lahan']) ? $_GET['lahan'] : 'all';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Query dengan filter
$tanaman_query = "SELECT * FROM tanaman WHERE 1=1";
$penjualan_query = "SELECT * FROM penjualan WHERE 1=1";

if ($filter_lahan !== 'all') {
    $tanaman_query .= " AND nama_lahan = '$filter_lahan'";
    $penjualan_query .= " AND nama_produk LIKE '%$filter_lahan%'";
}
if ($filter_status !== 'all') {
    $tanaman_query .= " AND status = '$filter_status'";
    $penjualan_query .= " AND status_pembayaran = '$filter_status'";
}
if (isset($_GET['date']) && $_GET['date']) {
    $penjualan_query .= " AND tanggal_jual = '$filter_date'";
}

$total_tanaman = $koneksi->query($tanaman_query)->num_rows;
$total_penjualan_result = $koneksi->query("SELECT SUM(total_harga) as t FROM penjualan WHERE status_pembayaran='lunas'");
$total_penjualan = $total_penjualan_result->fetch_assoc()['t'] ?? 0;

if (isset($_GET['date']) && $_GET['date']) {
    $total_penjualan_result2 = $koneksi->query("SELECT SUM(total_harga) as t FROM penjualan WHERE status_pembayaran='lunas' AND tanggal_jual='$filter_date'");
    $total_penjualan_filtered = $total_penjualan_result2->fetch_assoc()['t'] ?? 0;
} else {
    $total_penjualan_filtered = $total_penjualan;
}

// ==========================================
// STATISTIK SISTEM DUA GUDANG
// ==========================================

// 1. Gudang Inventaris (Pupuk, Pestisida, Alat, dll)
$total_item_inventaris = $koneksi->query("SELECT COUNT(*) as c FROM gudang_inventaris")->fetch_assoc()['c'] ?? 0;
$total_stok_inventaris = $koneksi->query("SELECT COALESCE(SUM(stok),0) as t FROM gudang_inventaris")->fetch_assoc()['t'] ?? 0;
$stok_rendah = $koneksi->query("SELECT COUNT(*) as c FROM gudang_inventaris WHERE stok <= min_stok")->fetch_assoc()['c'] ?? 0;
$total_nilai_inventaris = $koneksi->query("SELECT COALESCE(SUM(stok * harga_satuan),0) as t FROM gudang_inventaris")->fetch_assoc()['t'] ?? 0;

// 2. Gudang Panen (Hasil Panen)
$total_panen_kg = $koneksi->query("SELECT COALESCE(SUM(berat_kg),0) as t FROM gudang_panen")->fetch_assoc()['t'] ?? 0;
$total_nilai_panen = $koneksi->query("SELECT COALESCE(SUM(total_nilai),0) as t FROM gudang_panen")->fetch_assoc()['t'] ?? 0;
$panen_segar = $koneksi->query("SELECT COALESCE(SUM(berat_kg),0) as t FROM gudang_panen WHERE status='segar'")->fetch_assoc()['t'] ?? 0;
$panen_siap_jual = $koneksi->query("SELECT COALESCE(SUM(berat_kg),0) as t FROM gudang_panen WHERE status='siap_jual'")->fetch_assoc()['t'] ?? 0;
$panen_terjual = $koneksi->query("SELECT COALESCE(SUM(total_nilai),0) as t FROM gudang_panen WHERE status='terjual'")->fetch_assoc()['t'] ?? 0;

// AI Deteksi
$ai_detections = $koneksi->query("SELECT COUNT(*) as c FROM ai_detections WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'] ?? 0;

// Data grafik
$sales_data = $koneksi->query("SELECT DATE_FORMAT(tanggal_jual, '%Y-%m') as month, SUM(total_harga) as total FROM penjualan WHERE status_pembayaran='lunas' AND tanggal_jual >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY month ORDER BY month");
$months = []; $totals = [];
while($row = $sales_data->fetch_assoc()) { $months[] = date('M Y', strtotime($row['month'].'-01')); $totals[] = (float)$row['total']; }
if (empty($months)) { $months = ['Belum Ada']; $totals = [0]; }

$top_products = $koneksi->query("SELECT nama_produk, SUM(jumlah) as q, SUM(total_harga) as r FROM penjualan WHERE status_pembayaran='lunas' GROUP BY nama_produk ORDER BY r DESC LIMIT 5");
$product_names = []; $product_qty = []; $product_revenue = [];
while($r = $top_products->fetch_assoc()) { $product_names[] = $r['nama_produk']; $product_qty[] = (float)$r['q']; $product_revenue[] = (float)$r['r']; }
if (empty($product_names)) { $product_names = ['Belum Ada']; $product_qty = [0]; $product_revenue = [0]; }

$crop_data = $koneksi->query("SELECT nama_tanaman, COUNT(*) as c FROM tanaman WHERE status != 'panen' GROUP BY nama_tanaman");
$crop_labels = []; $crop_values = [];
while($r = $crop_data->fetch_assoc()) { $crop_labels[] = $r['nama_tanaman']; $crop_values[] = (int)$r['c']; }
if (empty($crop_labels)) { $crop_labels = ['Belum Ada']; $crop_values = [0]; }

// Data chart inventaris per kategori
$inventaris_kategori = $koneksi->query("SELECT kategori, COUNT(*) as jumlah, SUM(stok) as total_stok FROM gudang_inventaris GROUP BY kategori");
$inv_labels = []; $inv_values = [];
while($r = $inventaris_kategori->fetch_assoc()) { 
    $inv_labels[] = ucfirst(str_replace('_', ' ', $r['kategori'])); 
    $inv_values[] = (int)$r['jumlah']; 
}
if (empty($inv_labels)) { $inv_labels = ['Belum Ada']; $inv_values = [0]; }

// Data chart status panen
$panen_status = $koneksi->query("SELECT status, SUM(berat_kg) as total FROM gudang_panen GROUP BY status");
$panen_labels = []; $panen_values = [];
$status_display = ['segar'=>'Segar', 'pengolahan'=>'Pengolahan', 'siap_jual'=>'Siap Jual', 'terjual'=>'Terjual'];
while($r = $panen_status->fetch_assoc()) { 
    $panen_labels[] = $status_display[$r['status']] ?? $r['status']; 
    $panen_values[] = (float)$r['total']; 
}
if (empty($panen_labels)) { $panen_labels = ['Belum Ada']; $panen_values = [0]; }

// List lahan untuk filter
$laha_list = $koneksi->query("SELECT DISTINCT nama_lahan FROM tanaman");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - RAFLI_FARM_PRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { 
            theme: { 
                extend: { 
                    fontFamily: { 
                        sans: ['Inter', 'sans-serif'],
                        display: ['Orbitron', 'sans-serif']
                    }, 
                    colors: { 
                        neon: '#00FF88', 
                        cyan: '#00E5FF', 
                        danger: '#FF3366', 
                        warning: '#FFB300', 
                        purple: '#A855F7', 
                        dark: { 900: '#0A0A0A', 800: '#111111' } 
                    } 
                } 
            } 
        }
    </script>
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #0A0A0A; 
            color: #fff;
            overflow-x: hidden;
        }
        
        /* Animated Grid Background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: 
                linear-gradient(rgba(0,255,136,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,255,136,0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            pointer-events: none;
            z-index: 0;
            animation: gridMove 20s linear infinite;
        }
        @keyframes gridMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        /* Aurora Effect */
        body::after {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: 
                radial-gradient(circle at 20% 30%, rgba(0,255,136,0.08) 0%, transparent 40%),
                radial-gradient(circle at 80% 70%, rgba(0,229,255,0.08) 0%, transparent 40%),
                radial-gradient(circle at 40% 80%, rgba(168,85,247,0.05) 0%, transparent 40%);
            pointer-events: none;
            z-index: 0;
            animation: auroraMove 30s ease-in-out infinite;
        }
        @keyframes auroraMove {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(5%, 5%) rotate(120deg); }
            66% { transform: translate(-5%, -5%) rotate(240deg); }
        }
        
        .glass { 
            background: rgba(255,255,255,0.03); 
            backdrop-filter: blur(16px); 
            border: 1px solid rgba(255,255,255,0.08);
            position: relative;
            overflow: hidden;
        }
        
        .glass::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.05), transparent);
            transition: left 0.6s;
        }
        
        .glass:hover::before {
            left: 100%;
        }
        
        .glass-hover:hover { 
            background: rgba(255,255,255,0.06); 
            border-color: rgba(0,255,136,0.3);
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(0,255,136,0.1);
        }
        
        .gradient-text { 
            background: linear-gradient(135deg, #00FF88 0%, #00E5FF 50%, #A855F7 100%); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent;
            background-size: 200% auto;
            animation: gradientShift 3s ease infinite;
        }
        @keyframes gradientShift {
            0%, 100% { background-position: 0% center; }
            50% { background-position: 100% center; }
        }
        
        .sidebar { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        @media (max-width: 1024px) { 
            .sidebar { transform: translateX(-100%); position: fixed; z-index: 50; } 
            .sidebar.open { transform: translateX(0); } 
        }
        
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-up { animation: fadeUp 0.6s ease-out forwards; opacity: 0; }
        .delay-100 { animation-delay: 0.1s; } 
        .delay-200 { animation-delay: 0.2s; } 
        .delay-300 { animation-delay: 0.3s; }
        .delay-400 { animation-delay: 0.4s; }
        
        #weatherCanvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; }
        
        .menu-active { 
            background: linear-gradient(90deg, rgba(0,255,136,0.15) 0%, transparent 100%); 
            border-left: 3px solid #00FF88; 
            color: #00FF88 !important; 
        }
        
        /* Notification Panel */
        .notif-panel {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            width: 400px;
            max-height: 600px;
            background: rgba(10, 10, 10, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(0,255,136,0.3);
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            overflow: hidden;
            display: none;
            z-index: 100;
        }
        .notif-panel.active { display: block; animation: slideDown 0.3s ease-out; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        
        .notif-item {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            transition: all 0.2s;
            cursor: pointer;
        }
        .notif-item:hover { background: rgba(0,255,136,0.05); }
        .notif-item.unread { border-left: 3px solid #00FF88; background: rgba(0,255,136,0.03); }
        
        @keyframes resetSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .reset-spin { animation: resetSpin 0.6s ease-out; }
        
        .badge-pulse { animation: badgePulse 2s infinite; }
        @keyframes badgePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        /* Glowing Border Effect */
        .glow-border {
            position: relative;
        }
        .glow-border::after {
            content: '';
            position: absolute;
            inset: -1px;
            border-radius: inherit;
            padding: 1px;
            background: linear-gradient(135deg, #00FF88, #00E5FF, #A855F7);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .glow-border:hover::after {
            opacity: 1;
        }
        
        /* Stat Card Hover Effect */
        .stat-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: linear-gradient(135deg, transparent, rgba(0,255,136,0.1));
            opacity: 0;
            transition: opacity 0.3s;
        }
        .stat-card:hover::before {
            opacity: 1;
        }
        .stat-card:hover {
            transform: translateY(-4px) scale(1.02);
        }
        
        /* Icon Pulse */
        .icon-pulse {
            animation: iconPulse 2s ease-in-out infinite;
        }
        @keyframes iconPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        /* Floating Numbers Animation */
        @keyframes floatNumber {
            0% { transform: translateY(0) scale(1); opacity: 1; }
            100% { transform: translateY(-20px) scale(1.2); opacity: 0; }
        }
        
        /* Progress Indicator */
        .progress-shimmer {
            position: relative;
            overflow: hidden;
        }
        .progress-shimmer::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s infinite;
        }
        @keyframes shimmer {
            100% { left: 100%; }
        }
        
        /* Neon Glow Text */
        .neon-glow {
            text-shadow: 0 0 10px rgba(0,255,136,0.5), 0 0 20px rgba(0,255,136,0.3);
        }
        
        /* Section Title */
        .section-title {
            position: relative;
            display: inline-block;
            padding-left: 16px;
        }
        .section-title::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 24px;
            background: linear-gradient(180deg, #00FF88, #00E5FF);
            border-radius: 2px;
            box-shadow: 0 0 10px rgba(0,255,136,0.5);
        }
        
        /* Live Indicator */
        .live-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #00FF88;
            border-radius: 50%;
            margin-right: 6px;
            animation: livePulse 1.5s infinite;
            box-shadow: 0 0 10px #00FF88;
        }
        @keyframes livePulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.3); }
        }
        
        /* AI Welcome Styles */
        .ai-modal-overlay {
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at center, rgba(0,255,136,0.1) 0%, rgba(0,0,0,0.95) 100%);
            backdrop-filter: blur(20px);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.5s ease;
        }
        .ai-modal-overlay.active {
            opacity: 1;
            pointer-events: all;
        }
        
        .ai-container {
            background: linear-gradient(135deg, rgba(10,10,10,0.95) 0%, rgba(20,20,30,0.95) 100%);
            border: 2px solid rgba(0,255,136,0.5);
            border-radius: 24px;
            padding: 40px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 0 100px rgba(0,255,136,0.3), inset 0 0 50px rgba(0,255,136,0.1);
            position: relative;
            overflow: hidden;
            transform: scale(0.8);
            transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .ai-modal-overlay.active .ai-container {
            transform: scale(1);
        }
        
        .ai-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent 30%, rgba(0,255,136,0.1) 50%, transparent 70%);
            animation: hologram 3s linear infinite;
            pointer-events: none;
        }
        @keyframes hologram {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .ai-avatar-wrapper {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 30px;
        }
        
        .ai-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #00FF88 0%, #00E5FF 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 80px;
            box-shadow: 0 0 60px rgba(0,255,136,0.6), 0 0 120px rgba(0,255,136,0.3);
            position: relative;
            z-index: 2;
            animation: avatarFloat 3s ease-in-out infinite;
        }
        @keyframes avatarFloat {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-10px) scale(1.05); }
        }
        
        .ai-speaking .ai-avatar {
            animation: avatarPulse 0.5s ease-in-out infinite;
        }
        @keyframes avatarPulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 60px rgba(0,255,136,0.6); }
            50% { transform: scale(1.1); box-shadow: 0 0 100px rgba(0,255,136,0.9); }
        }
        
        .particle-ring {
            position: absolute;
            inset: -20px;
            border-radius: 50%;
            border: 2px solid rgba(0,255,136,0.3);
            animation: rotate 10s linear infinite;
        }
        .particle-ring::before,
        .particle-ring::after {
            content: '';
            position: absolute;
            width: 10px;
            height: 10px;
            background: #00FF88;
            border-radius: 50%;
            box-shadow: 0 0 20px #00FF88;
        }
        .particle-ring::before { top: 0; left: 50%; transform: translateX(-50%); }
        .particle-ring::after { bottom: 0; left: 50%; transform: translateX(-50%); }
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .audio-visualizer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            height: 60px;
            margin: 20px 0;
        }
        .audio-bar {
            width: 6px;
            background: linear-gradient(to top, #00FF88, #00E5FF);
            border-radius: 3px;
            height: 10px;
            transition: height 0.1s ease;
        }
        .ai-speaking .audio-bar {
            animation: audioWave 0.5s ease-in-out infinite;
        }
        .audio-bar:nth-child(1) { animation-delay: 0s; }
        .audio-bar:nth-child(2) { animation-delay: 0.1s; }
        .audio-bar:nth-child(3) { animation-delay: 0.2s; }
        .audio-bar:nth-child(4) { animation-delay: 0.3s; }
        .audio-bar:nth-child(5) { animation-delay: 0.4s; }
        .audio-bar:nth-child(6) { animation-delay: 0.3s; }
        .audio-bar:nth-child(7) { animation-delay: 0.2s; }
        .audio-bar:nth-child(8) { animation-delay: 0.1s; }
        .audio-bar:nth-child(9) { animation-delay: 0s; }
        @keyframes audioWave {
            0%, 100% { height: 10px; }
            50% { height: 50px; }
        }
        
        .transcript-box {
            background: rgba(0,255,136,0.05);
            border: 1px solid rgba(0,255,136,0.3);
            border-radius: 12px;
            padding: 20px;
            min-height: 100px;
            margin: 20px 0;
            position: relative;
        }
        .transcript-text {
            color: #fff;
            font-size: 14px;
            line-height: 1.6;
            font-family: 'Courier New', monospace;
        }
        .typing-cursor {
            display: inline-block;
            width: 2px;
            height: 16px;
            background: #00FF88;
            animation: blink 0.8s infinite;
            vertical-align: middle;
            margin-left: 2px;
        }
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0; }
        }
        
        .progress-ring {
            width: 80px;
            height: 80px;
            margin: 20px auto;
            position: relative;
        }
        .progress-ring svg {
            transform: rotate(-90deg);
        }
        .progress-ring circle {
            fill: none;
            stroke-width: 4;
        }
        .progress-ring .bg { stroke: rgba(255,255,255,0.1); }
        .progress-ring .fg {
            stroke: url(#gradient);
            stroke-linecap: round;
            stroke-dasharray: 220;
            stroke-dashoffset: 220;
            transition: stroke-dashoffset 0.3s ease;
        }
        
        .ai-controls {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        .ai-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .ai-btn-replay {
            background: rgba(0,255,136,0.2);
            color: #00FF88;
            border: 1px solid rgba(0,255,136,0.5);
        }
        .ai-btn-replay:hover {
            background: rgba(0,255,136,0.3);
            transform: translateY(-2px);
        }
        .ai-btn-stop {
            background: rgba(255,51,102,0.2);
            color: #FF3366;
            border: 1px solid rgba(255,51,102,0.5);
        }
        .ai-btn-stop:hover {
            background: rgba(255,51,102,0.3);
            transform: translateY(-2px);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: rgba(0,255,136,0.1);
            border: 1px solid rgba(0,255,136,0.3);
            border-radius: 20px;
            font-size: 12px;
            color: #00FF88;
            margin-bottom: 20px;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            background: #00FF88;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .fab-ai {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #00FF88 0%, #00E5FF 100%);
            border: none;
            cursor: pointer;
            box-shadow: 0 10px 40px rgba(0,255,136,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            z-index: 9998;
            transition: all 0.3s;
            animation: fabPulse 2s infinite;
        }
        .fab-ai:hover {
            transform: scale(1.1) rotate(10deg);
            box-shadow: 0 15px 60px rgba(0,255,136,0.7);
        }
        @keyframes fabPulse {
            0%, 100% { box-shadow: 0 10px 40px rgba(0,255,136,0.5); }
            50% { box-shadow: 0 10px 60px rgba(0,255,136,0.8); }
        }
        
        /* Scrollbar Custom */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: rgba(255,255,255,0.02); }
        ::-webkit-scrollbar-thumb { 
            background: linear-gradient(180deg, #00FF88, #00E5FF); 
            border-radius: 10px; 
        }
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #00E5FF, #00FF88);
        }
    </style>
</head>
<body class="min-h-screen flex relative">
    <canvas id="weatherCanvas"></canvas>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar w-64 bg-dark-800/90 backdrop-blur-xl border-r border-white/5 flex flex-col h-screen fixed lg:static z-40">
        <div class="p-6 border-b border-white/5 flex items-center gap-3">
            <div class="w-10 h-10 bg-gradient-to-br from-neon to-cyan rounded-xl flex items-center justify-center shadow-lg shadow-neon/20">
                <svg class="w-6 h-6 text-dark-900" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 007.92 12.446A9 9 0 1112 3z" /></svg>
            </div>
            <div>
                <h1 class="text-lg font-bold gradient-text">RAFLI_FARM</h1>
                <p class="text-[10px] text-gray-500 tracking-widest">PROFESSIONAL</p>
            </div>
        </div>
        <div class="p-4 border-b border-white/5">
            <div class="flex items-center gap-3 p-2 rounded-xl bg-white/5">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-cyan to-purple-500 flex items-center justify-center font-bold text-sm">
                    <?php echo strtoupper(substr($nama_user, 0, 1)); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate"><?php echo $nama_user; ?></p>
                    <p class="text-[10px] text-gray-500 truncate">@<?php echo $username; ?></p>
                </div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto py-4 space-y-1">
            <a href="dashboard.php" class="menu-active flex items-center gap-3 px-6 py-3 text-sm font-medium">📊 Dashboard</a>
            <a href="tanaman.php" class="flex items-center gap-3 px-6 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5">🌱 Tanaman</a>
            <a href="gudang.php" class="flex items-center gap-3 px-6 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5">📦 Gudang Inventaris</a>
            <a href="gudang-panen.php" class="flex items-center gap-3 px-6 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5">🌾 Gudang Panen</a>
            <a href="penjualan.php" class="flex items-center gap-3 px-6 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5">💰 Penjualan</a>
            <a href="keuangan.php" class="flex items-center gap-3 px-6 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5">💵 Keuangan</a>
            <a href="jadwal.php" class="flex items-center gap-3 px-6 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5">📅 Jadwal</a>
            <a href="ai-deteksi.php" class="flex items-center gap-3 px-6 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5">🧠 AI Deteksi</a>
            <a href="ai-voice.php" class="flex items-center gap-3 px-6 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5">🎤 AI Voice</a>
            <a href="kalkulator.php" class="flex items-center gap-3 px-6 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5">🧮 Kalkulator</a>
            <a href="statistik.php" class="flex items-center gap-3 px-6 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5">📊 Statistik</a>
            <div class="pt-4 mt-4 border-t border-white/5">
                <a href="profil.php" class="flex items-center gap-3 px-6 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5">👤 Profil</a>
                <a href="tentang.php" class="flex items-center gap-3 px-6 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5">ℹ️ Tentang</a>
                <a href="pengaturan.php" class="flex items-center gap-3 px-6 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5">⚙️ Pengaturan</a>
            </div>
        </nav>
        <div class="p-4 border-t border-white/5">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-danger hover:bg-danger/10 rounded-xl">🚪 Keluar</a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen relative z-10">
        
        <!-- Header -->
        <header class="glass border-b border-white/5 px-6 py-4 flex items-center justify-between sticky top-0 z-40">
            <div class="flex items-center gap-4">
                <button id="toggleSidebar" class="lg:hidden p-2 rounded-lg hover:bg-white/10 text-gray-400">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <div>
                    <h2 class="text-xl font-bold hidden sm:block">Dashboard Overview</h2>
                    <p class="text-xs text-gray-500 hidden sm:block"><span class="live-dot"></span>Live Monitoring • Sistem Dua Gudang Aktif</p>
                </div>
            </div>

            <div class="flex items-center gap-3 sm:gap-6">
                <!-- AI Summary Button -->
                <button onclick="triggerAI()" class="hidden sm:flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-neon/20 to-cyan/20 border border-neon/30 text-neon rounded-lg hover:from-neon/30 hover:to-cyan/30 transition-all text-sm">
                    <span>🤖</span>
                    <span>AI Summary</span>
                </button>
                
                <!-- Notification -->
                <div class="relative">
                    <button onclick="toggleNotifPanel()" id="notifBtn" class="relative p-2 rounded-lg hover:bg-white/10 text-gray-400 transition-all">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                        <span id="notifBadge" class="absolute -top-1 -right-1 w-5 h-5 bg-danger rounded-full text-[10px] text-white font-bold flex items-center justify-center badge-pulse">0</span>
                    </button>

                    <div id="notifPanel" class="notif-panel">
                        <div class="p-4 border-b border-white/10 flex items-center justify-between bg-gradient-to-r from-neon/10 to-cyan/10">
                            <div>
                                <h3 class="font-bold text-white flex items-center gap-2">
                                    🔔 Notifikasi
                                    <span id="notifBadgeHeader" class="text-xs bg-neon text-dark-900 px-2 py-0.5 rounded-full font-bold">0</span>
                                </h3>
                                <p class="text-xs text-gray-400">Pantau aktivitas sistem</p>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="markAllRead()" class="text-xs px-2 py-1 bg-white/5 hover:bg-white/10 rounded-lg text-gray-400 hover:text-white transition-all">✓ Tandai Baca</button>
                                <button onclick="clearReadNotif()" class="text-xs px-2 py-1 bg-danger/10 hover:bg-danger/20 rounded-lg text-danger transition-all">🗑️ Bersihkan</button>
                            </div>
                        </div>
                        <div id="notifList" class="overflow-y-auto max-h-[500px]">
                            <div class="p-8 text-center text-gray-500">
                                <div class="animate-pulse">Memuat...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dashboard Content -->
        <main class="flex-1 p-6 overflow-y-auto">
            
            <!-- Welcome Banner -->
            <div class="glass rounded-2xl p-6 mb-8 animate-fade-up relative overflow-hidden glow-border">
                <div class="absolute top-0 right-0 w-64 h-64 bg-neon/10 rounded-full blur-3xl -mr-16 -mt-16"></div>
                <div class="absolute bottom-0 left-0 w-48 h-48 bg-cyan/10 rounded-full blur-3xl -ml-12 -mb-12"></div>
                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="px-3 py-1 bg-neon/20 border border-neon/30 rounded-full text-xs text-neon font-bold">✨ WELCOME BACK</span>
                        </div>
                        <h1 class="text-2xl md:text-3xl font-bold mb-2">Selamat Datang, <span class="gradient-text"><?php echo explode(' ', $nama_user)[0]; ?></span>! 👨‍🌾</h1>
                        <p class="text-gray-400"><?php echo date('l, d F Y'); ?> • Sistem Dua Gudang Terintegrasi</p>
                    </div>
                    <div class="flex gap-3">
                        <a href="gudang.php" class="px-4 py-2 bg-white/5 border border-white/10 hover:border-cyan/30 text-white rounded-xl hover:shadow-lg transition-all text-sm flex items-center gap-2">
                            📦 Inventaris
                        </a>
                        <a href="gudang-panen.php" class="px-4 py-2 bg-gradient-to-r from-neon to-cyan text-dark-900 font-bold rounded-xl hover:shadow-lg hover:shadow-neon/30 transition-all text-sm flex items-center gap-2">
                            🌾 Gudang Panen
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <form method="GET" class="glass rounded-2xl p-4 mb-8 flex flex-wrap items-center gap-4 animate-fade-up delay-100">
                <div class="flex items-center gap-2 flex-1 min-w-[150px]">
                    <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    <input type="date" name="date" value="<?php echo $filter_date; ?>" class="bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-neon w-full">
                </div>
                <select name="lahan" class="bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-neon">
                    <option value="all">🌾 Semua Lahan</option>
                    <?php 
                    $laha_list->data_seek(0);
                    while($l = $laha_list->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $l['nama_lahan']; ?>" <?php echo $filter_lahan == $l['nama_lahan'] ? 'selected' : ''; ?>>📍 <?php echo $l['nama_lahan']; ?></option>
                    <?php endwhile; ?>
                </select>
                <select name="status" class="bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-neon">
                    <option value="all">Semua Status</option>
                    <option value="semai" <?php echo $filter_status == 'semai' ? 'selected' : ''; ?>>🌱 Semai</option>
                    <option value="pertumbuhan" <?php echo $filter_status == 'pertumbuhan' ? 'selected' : ''; ?>>🌿 Pertumbuhan</option>
                    <option value="siap_panen" <?php echo $filter_status == 'siap_panen' ? 'selected' : ''; ?>>🌾 Siap Panen</option>
                    <option value="lunas" <?php echo $filter_status == 'lunas' ? 'selected' : ''; ?>>💰 Lunas</option>
                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-neon text-dark-900 font-bold rounded-lg hover:shadow-lg hover:shadow-neon/30 transition-all text-sm">
                    🔍 Filter
                </button>
                <button type="button" onclick="resetFilters(this)" class="px-4 py-2 bg-white/5 border border-white/10 text-gray-300 rounded-lg hover:bg-white/10 hover:text-white transition-all text-sm flex items-center gap-2">
                    <svg id="resetIcon" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    Reset
                </button>
            </form>

            <!-- Weather Widget -->
            <div class="glass rounded-2xl p-6 mb-6 relative overflow-hidden border border-cyan/20 animate-fade-up delay-200">
                <div class="absolute top-0 right-0 w-32 h-32 bg-cyan/10 rounded-full blur-3xl"></div>
                <div class="flex flex-col md:flex-row items-center justify-between gap-4 relative z-10">
                    <div class="flex items-center gap-4">
                        <div class="text-5xl" id="weatherIcon">🌤️</div>
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="text-lg font-bold text-white">Cuaca Dompu, Sawe</h3>
                                <span class="live-dot"></span>
                            </div>
                            <p class="text-3xl font-bold text-cyan neon-glow" id="weatherTemp">--°C</p>
                            <p class="text-sm text-gray-400" id="weatherDesc">Memuat data cuaca...</p>
                        </div>
                    </div>
                    <div class="flex gap-4 text-center" id="weatherForecast">
                        <!-- Forecast di-inject via JS -->
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- SECTION: GUDANG INVENTARIS -->
            <!-- ========================================== -->
            <div class="mb-8 animate-fade-up delay-200">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-white section-title">📦 Gudang Inventaris</h2>
                    <a href="gudang.php" class="text-xs text-cyan hover:underline">Lihat Detail →</a>
                </div>
                <p class="text-xs text-gray-500 mb-4 ml-4">Pupuk, Pestisida, Benih, Alat, Mesin & Kebutuhan Petani</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="glass stat-card rounded-2xl p-5 border-l-4 border-neon glow-border">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 rounded-xl bg-neon/10 flex items-center justify-center text-2xl icon-pulse">📦</div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wider">Total Item</p>
                                <p class="text-2xl font-bold text-white"><?php echo number_format($total_item_inventaris); ?></p>
                            </div>
                        </div>
                        <div class="text-[10px] text-gray-500">Jenis barang tersedia</div>
                    </div>
                    
                    <div class="glass stat-card rounded-2xl p-5 border-l-4 border-cyan glow-border">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 rounded-xl bg-cyan/10 flex items-center justify-center text-2xl icon-pulse">📊</div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wider">Total Stok</p>
                                <p class="text-2xl font-bold text-cyan"><?php echo number_format($total_stok_inventaris, 0); ?></p>
                            </div>
                        </div>
                        <div class="text-[10px] text-gray-500">Unit keseluruhan</div>
                    </div>
                    
                    <div class="glass stat-card rounded-2xl p-5 border-l-4 border-danger glow-border">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 rounded-xl bg-danger/10 flex items-center justify-center text-2xl icon-pulse">⚠️</div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wider">Stok Rendah</p>
                                <p class="text-2xl font-bold text-danger"><?php echo number_format($stok_rendah); ?></p>
                            </div>
                        </div>
                        <div class="text-[10px] text-danger">Perlu restock segera!</div>
                    </div>
                    
                    <div class="glass stat-card rounded-2xl p-5 border-l-4 border-purple glow-border">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 rounded-xl bg-purple/10 flex items-center justify-center text-2xl icon-pulse">💰</div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wider">Nilai Inventaris</p>
                                <p class="text-2xl font-bold text-purple-400">Rp <?php echo number_format($total_nilai_inventaris/1000000, 1); ?><span class="text-sm text-gray-400"> Jt</span></p>
                            </div>
                        </div>
                        <div class="text-[10px] text-gray-500">Total aset inventaris</div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- SECTION: GUDANG PANEN -->
            <!-- ========================================== -->
            <div class="mb-8 animate-fade-up delay-300">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-white section-title">🌾 Gudang Panen</h2>
                    <a href="gudang-panen.php" class="text-xs text-cyan hover:underline">Lihat Detail →</a>
                </div>
                <p class="text-xs text-gray-500 mb-4 ml-4">Hasil Panen dari Semua Lahan Pertanian</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div class="glass stat-card rounded-2xl p-5 border-l-4 border-neon glow-border">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 rounded-xl bg-neon/10 flex items-center justify-center text-2xl icon-pulse">🌾</div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wider">Total Panen</p>
                                <p class="text-2xl font-bold text-white"><?php echo number_format($total_panen_kg, 0); ?> <span class="text-sm text-gray-400">kg</span></p>
                            </div>
                        </div>
                        <div class="text-[10px] text-gray-500">Keseluruhan hasil</div>
                    </div>
                    
                    <div class="glass stat-card rounded-2xl p-5 border-l-4 border-cyan glow-border">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 rounded-xl bg-cyan/10 flex items-center justify-center text-2xl icon-pulse">🌱</div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wider">Stok Segar</p>
                                <p class="text-2xl font-bold text-cyan"><?php echo number_format($panen_segar, 0); ?> <span class="text-sm text-gray-400">kg</span></p>
                            </div>
                        </div>
                        <div class="text-[10px] text-gray-500">Baru dipanen</div>
                    </div>
                    
                    <div class="glass stat-card rounded-2xl p-5 border-l-4 border-warning glow-border">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 rounded-xl bg-warning/10 flex items-center justify-center text-2xl icon-pulse">📦</div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wider">Siap Jual</p>
                                <p class="text-2xl font-bold text-warning"><?php echo number_format($panen_siap_jual, 0); ?> <span class="text-sm text-gray-400">kg</span></p>
                            </div>
                        </div>
                        <div class="text-[10px] text-gray-500">Menunggu pembeli</div>
                    </div>
                    
                    <div class="glass stat-card rounded-2xl p-5 border-l-4 border-purple glow-border">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 rounded-xl bg-purple/10 flex items-center justify-center text-2xl icon-pulse">💰</div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wider">Nilai Panen</p>
                                <p class="text-2xl font-bold text-purple-400">Rp <?php echo number_format($total_nilai_panen/1000000, 1); ?><span class="text-sm text-gray-400"> Jt</span></p>
                            </div>
                        </div>
                        <div class="text-[10px] text-gray-500">Total nilai aset</div>
                    </div>
                    
                    <div class="glass stat-card rounded-2xl p-5 border-l-4 border-neon glow-border">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 rounded-xl bg-neon/10 flex items-center justify-center text-2xl icon-pulse">💸</div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wider">Terjual</p>
                                <p class="text-2xl font-bold text-neon">Rp <?php echo number_format($panen_terjual/1000000, 1); ?><span class="text-sm text-gray-400"> Jt</span></p>
                            </div>
                        </div>
                        <div class="text-[10px] text-neon">Revenue masuk</div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- SECTION: OPERASIONAL -->
            <!-- ========================================== -->
            <div class="mb-8 animate-fade-up delay-300">
                <h2 class="text-xl font-bold text-white section-title mb-4">⚡ Operasional</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="glass stat-card rounded-2xl p-5 border-l-4 border-neon glow-border">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 rounded-xl bg-neon/10 flex items-center justify-center text-2xl icon-pulse">🌱</div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wider">Tanaman Aktif</p>
                                <p class="text-2xl font-bold text-white"><?php echo $total_tanaman; ?> <span class="text-sm text-gray-400">Lahan</span></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="glass stat-card rounded-2xl p-5 border-l-4 border-cyan glow-border">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 rounded-xl bg-cyan/10 flex items-center justify-center text-2xl icon-pulse">💰</div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wider">Penjualan</p>
                                <p class="text-2xl font-bold text-white">Rp <?php echo number_format($total_penjualan_filtered/1000000, 1); ?><span class="text-sm text-gray-400"> Jt</span></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="glass stat-card rounded-2xl p-5 border-l-4 border-warning glow-border">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 rounded-xl bg-warning/10 flex items-center justify-center text-2xl icon-pulse">⚠️</div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wider">Alert Gudang</p>
                                <p class="text-2xl font-bold text-white"><?php echo $stok_rendah; ?> <span class="text-sm text-gray-400">Item</span></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="glass stat-card rounded-2xl p-5 border-l-4 border-purple glow-border">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 rounded-xl bg-purple/10 flex items-center justify-center text-2xl icon-pulse">🧠</div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wider">AI Deteksi</p>
                                <p class="text-2xl font-bold text-white"><?php echo $ai_detections; ?> <span class="text-sm text-gray-400">Scan</span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="glass rounded-2xl p-6 mb-6 animate-fade-up">
                <h3 class="text-lg font-bold text-white mb-6 section-title">📈 Tren Penjualan 6 Bulan</h3>
                <div class="h-64"><canvas id="salesTrendChart"></canvas></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="glass rounded-2xl p-6 animate-fade-up">
                    <h3 class="text-lg font-bold text-white mb-6 section-title">📦 Distribusi Inventaris</h3>
                    <div class="h-64"><canvas id="inventarisChart"></canvas></div>
                </div>
                <div class="glass rounded-2xl p-6 animate-fade-up">
                    <h3 class="text-lg font-bold text-white mb-6 section-title">🌾 Status Hasil Panen</h3>
                    <div class="h-64"><canvas id="panenChart"></canvas></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="glass rounded-2xl p-6 animate-fade-up">
                    <h3 class="text-lg font-bold text-white mb-6 section-title">📊 Produk Terlaris</h3>
                    <div class="h-64"><canvas id="topProductsBarChart"></canvas></div>
                </div>
                <div class="glass rounded-2xl p-6 animate-fade-up">
                    <h3 class="text-lg font-bold text-white mb-6 section-title">🥧 Distribusi Pendapatan</h3>
                    <div class="h-64"><canvas id="productPieChart"></canvas></div>
                </div>
            </div>

            <div class="glass rounded-2xl p-6 animate-fade-up">
                <h3 class="text-lg font-bold text-white mb-6 section-title">🌱 Distribusi Tanaman</h3>
                <div class="h-64 max-w-2xl mx-auto"><canvas id="cropDistributionChart"></canvas></div>
            </div>
        </main>
    </div>

    <!-- AI Welcome Modal -->
    <div id="aiModal" class="ai-modal-overlay">
        <div class="ai-container">
            <div class="status-badge">
                <span class="status-dot"></span>
                <span>AI AKTIF</span>
            </div>

            <div class="ai-avatar-wrapper">
                <div class="particle-ring"></div>
                <div class="ai-avatar" id="aiAvatar">🤖</div>
            </div>

            <h2 class="text-2xl font-bold text-center mb-2 gradient-text">Asisten AI RAFLI_FARM</h2>
            <p class="text-center text-gray-400 text-sm mb-4">Membacakan ringkasan sistem dua gudang Anda...</p>

            <div class="audio-visualizer" id="audioVisualizer">
                <div class="audio-bar"></div>
                <div class="audio-bar"></div>
                <div class="audio-bar"></div>
                <div class="audio-bar"></div>
                <div class="audio-bar"></div>
                <div class="audio-bar"></div>
                <div class="audio-bar"></div>
                <div class="audio-bar"></div>
                <div class="audio-bar"></div>
            </div>

            <div class="transcript-box">
                <p class="transcript-text" id="transcriptText"></p>
                <span class="typing-cursor" id="typingCursor"></span>
            </div>

            <div class="progress-ring">
                <svg width="80" height="80">
                    <defs>
                        <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#00FF88"/>
                            <stop offset="100%" style="stop-color:#00E5FF"/>
                        </linearGradient>
                    </defs>
                    <circle class="bg" cx="40" cy="40" r="35"/>
                    <circle class="fg" id="progressCircle" cx="40" cy="40" r="35"/>
                </svg>
            </div>

            <div class="ai-controls">
                <button class="ai-btn ai-btn-replay" onclick="replayAI()">
                    <span>🔁</span> Ulangi
                </button>
                <button class="ai-btn ai-btn-stop" onclick="stopAI()">
                    <span>⏹️</span> Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- FAB AI -->
    <button class="fab-ai" onclick="triggerAI()" title="Dengarkan Ringkasan AI">🤖</button>

    <script>
        // ===== SIDEBAR TOGGLE =====
        const sidebar = document.getElementById('sidebar');
        document.getElementById('toggleSidebar').addEventListener('click', () => sidebar.classList.toggle('open'));

        // ===== RESET FILTERS =====
        function resetFilters(btn) {
            const icon = document.getElementById('resetIcon');
            icon.classList.add('reset-spin');
            document.querySelector('input[name="date"]').value = '<?php echo date("Y-m-d"); ?>';
            document.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
            setTimeout(() => document.querySelector('form[method="GET"]').submit(), 400);
        }

        // ===== NOTIFICATIONS =====
        let notifInterval;
        function toggleNotifPanel() {
            const panel = document.getElementById('notifPanel');
            panel.classList.toggle('active');
            if (panel.classList.contains('active')) loadNotifications();
        }

        async function loadNotifications() {
            try {
                const res = await fetch('notif.php?action=list');
                const data = await res.json();
                document.getElementById('notifBadge').textContent = data.unread;
                document.getElementById('notifBadgeHeader').textContent = data.unread;
                document.getElementById('notifBadge').style.display = data.unread === 0 ? 'none' : 'flex';

                const list = document.getElementById('notifList');
                if (data.data.length === 0) {
                    list.innerHTML = '<div class="p-8 text-center text-gray-500"><p class="text-4xl mb-2">🔔</p><p>Tidak ada notifikasi</p></div>';
                    return;
                }

                list.innerHTML = data.data.map(n => {
                    const colorMap = { 'neon': 'border-neon bg-neon/10', 'warning': 'border-warning bg-warning/10', 'danger': 'border-danger bg-danger/10', 'cyan': 'border-cyan bg-cyan/10', 'purple': 'border-purple-500 bg-purple-500/10' };
                    const colorClass = colorMap[n.color] || colorMap['neon'];
                    return `
                        <div class="notif-item ${n.is_read == 0 ? 'unread' : ''}" onclick="handleNotifClick(${n.id}, '${n.link}', ${n.is_read})">
                            <div class="flex gap-3">
                                <div class="w-10 h-10 rounded-lg ${colorClass} flex items-center justify-center flex-shrink-0 text-xl border">${n.icon}</div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="font-bold text-white text-sm truncate">${n.title}</p>
                                        ${n.is_read == 0 ? '<span class="w-2 h-2 bg-neon rounded-full flex-shrink-0 mt-1"></span>' : ''}
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1 line-clamp-2">${n.message}</p>
                                    <p class="text-[10px] text-gray-600 mt-1">${formatTimeAgo(n.created_at)}</p>
                                </div>
                                <button onclick="event.stopPropagation(); deleteNotif(${n.id})" class="text-gray-500 hover:text-danger p-1 transition-colors flex-shrink-0">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>`;
                }).join('');
            } catch (e) { console.error('Error:', e); }
        }

        function formatTimeAgo(dateStr) {
            const diff = Math.floor((new Date() - new Date(dateStr)) / 1000);
            if (diff < 60) return 'Baru saja';
            if (diff < 3600) return Math.floor(diff/60) + ' menit lalu';
            if (diff < 86400) return Math.floor(diff/3600) + ' jam lalu';
            return Math.floor(diff/86400) + ' hari lalu';
        }

        async function handleNotifClick(id, link, isRead) {
            if (!isRead) await fetch(`notif.php?action=mark_read&id=${id}`);
            if (link) window.location.href = link;
            loadNotifications();
        }
        async function deleteNotif(id) { await fetch(`notif.php?action=delete&id=${id}`); loadNotifications(); }
        async function markAllRead() { await fetch('notif.php?action=mark_read&id=0'); loadNotifications(); }
        async function clearReadNotif() {
            if (!confirm('Hapus semua notifikasi yang sudah dibaca?')) return;
            await fetch('notif.php?action=clear_all'); loadNotifications();
        }

        document.addEventListener('click', (e) => {
            const panel = document.getElementById('notifPanel');
            const btn = document.getElementById('notifBtn');
            if (!panel.contains(e.target) && !btn.contains(e.target)) panel.classList.remove('active');
        });

        loadNotifications();
        notifInterval = setInterval(loadNotifications, 30000);

        // ===== CHARTS =====
        Chart.defaults.color = '#888';
        Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
        
        const monthsData = <?php echo json_encode($months); ?>;
        const totalsData = <?php echo json_encode($totals); ?>;
        const productNames = <?php echo json_encode($product_names); ?>;
        const productQty = <?php echo json_encode($product_qty); ?>;
        const productRevenue = <?php echo json_encode($product_revenue); ?>;
        const cropLabels = <?php echo json_encode($crop_labels); ?>;
        const cropValues = <?php echo json_encode($crop_values); ?>;
        const invLabels = <?php echo json_encode($inv_labels); ?>;
        const invValues = <?php echo json_encode($inv_values); ?>;
        const panenLabels = <?php echo json_encode($panen_labels); ?>;
        const panenValues = <?php echo json_encode($panen_values); ?>;
        
        const chartColors = ['#00FF88', '#00E5FF', '#A855F7', '#FFB300', '#FF3366', '#FB923C', '#EC4899'];

        // Sales Trend
        new Chart(document.getElementById('salesTrendChart'), {
            type: 'line',
            data: { labels: monthsData, datasets: [{ label: 'Penjualan', data: totalsData, borderColor: '#00FF88', backgroundColor: 'rgba(0,255,136,0.1)', borderWidth: 3, fill: true, tension: 0.4, pointBackgroundColor: '#00FF88', pointBorderColor: '#fff', pointRadius: 5 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => 'Rp ' + (v/1000000).toFixed(1) + 'jt' } } } }
        });

        // Inventaris Chart
        new Chart(document.getElementById('inventarisChart'), {
            type: 'doughnut',
            data: { labels: invLabels, datasets: [{ data: invValues, backgroundColor: chartColors, borderWidth: 0, hoverOffset: 15 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: '#fff', padding: 15, usePointStyle: true } } }, cutout: '60%' }
        });

        // Panen Status Chart
        new Chart(document.getElementById('panenChart'), {
            type: 'bar',
            data: { labels: panenLabels, datasets: [{ label: 'Berat (kg)', data: panenValues, backgroundColor: chartColors.map(c => c + 'CC'), borderRadius: 8, borderSkipped: false }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => v + ' kg' } } } }
        });

        // Top Products
        new Chart(document.getElementById('topProductsBarChart'), {
            type: 'bar',
            data: { labels: productNames, datasets: [{ label: 'Terjual', data: productQty, backgroundColor: chartColors.map(c => c + 'CC'), borderRadius: 8 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        // Product Pie
        new Chart(document.getElementById('productPieChart'), {
            type: 'doughnut',
            data: { labels: productNames, datasets: [{ data: productRevenue, backgroundColor: chartColors }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: '#fff', padding: 15 } } } }
        });

        // Crop Distribution
        new Chart(document.getElementById('cropDistributionChart'), {
            type: 'doughnut',
            data: { labels: cropLabels, datasets: [{ data: cropValues, backgroundColor: chartColors }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: '#fff', padding: 15 } } } }
        });

        // ===== WEATHER CANVAS (Sakura) =====
        const canvas = document.getElementById('weatherCanvas');
        const ctx = canvas.getContext('2d');
        let particles = [];
        function resizeCanvas() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);
        class Particle {
            constructor() { this.x = Math.random() * canvas.width; this.y = Math.random() * canvas.height; this.size = 8 + Math.random() * 6; this.speedY = 1 + Math.random() * 1.5; this.wobble = Math.random() * Math.PI * 2; this.rotation = Math.random() * 360; }
            update() { this.y += this.speedY; this.wobble += 0.02; this.x += Math.sin(this.wobble) * 1; this.rotation += 1; if (this.y > canvas.height + 20) { this.y = -20; this.x = Math.random() * canvas.width; } }
            draw() { ctx.save(); ctx.translate(this.x, this.y); ctx.rotate((this.rotation * Math.PI) / 180); ctx.globalAlpha = 0.4; ctx.beginPath(); ctx.moveTo(0, 0); ctx.bezierCurveTo(this.size/2, -this.size/2, this.size, 0, 0, this.size); ctx.bezierCurveTo(-this.size, 0, -this.size/2, -this.size/2, 0, 0); ctx.fillStyle = '#FFB7D5'; ctx.fill(); ctx.restore(); }
        }
        for(let i=0; i<30; i++) particles.push(new Particle());
        function animate() { ctx.clearRect(0, 0, canvas.width, canvas.height); particles.forEach(p => { p.update(); p.draw(); }); requestAnimationFrame(animate); }
        animate();

        // ===== WEATHER API =====
        function getWeatherDesc(code) {
            const map = { 0:'cerah', 1:'cerah berawan', 2:'berawan sebagian', 3:'mendung', 45:'berkabut', 61:'hujan ringan', 63:'hujan', 65:'hujan lebat', 80:'gerimis', 95:'badai petir' };
            return map[code] || 'tidak diketahui';
        }

        async function loadWeather() {
            try {
                const res = await fetch('https://api.open-meteo.com/v1/forecast?latitude=-8.5278&longitude=118.4686&current=temperature_2m,weather_code,wind_speed_10m,relative_humidity_2m&daily=weather_code,temperature_2m_max,temperature_2m_min&timezone=Asia/Makassar&forecast_days=4');
                const data = await res.json();
                aiData.cuaca.temp = Math.round(data.current.temperature_2m);
                aiData.cuaca.desc = getWeatherDesc(data.current.weather_code);
                
                document.getElementById('weatherTemp').innerText = aiData.cuaca.temp + '°C';
                document.getElementById('weatherDesc').innerText = `${aiData.cuaca.desc} • Kelembapan: ${data.current.relative_humidity_2m}% • Angin: ${data.current.wind_speed_10m} km/h`;
                
                const code = data.current.weather_code;
                let icon = '🌤️';
                if(code === 0 || code === 1) icon = '☀️';
                else if(code === 2 || code === 3) icon = '⛅';
                else if(code >= 45 && code <= 48) icon = '🌫️';
                else if(code >= 51 && code <= 67) icon = '🌧️';
                else if(code >= 71 && code <= 77) icon = '❄️';
                else if(code >= 80 && code <= 82) icon = '🌦️';
                else if(code >= 95) icon = '⛈️';
                document.getElementById('weatherIcon').innerText = icon;

                const forecastDiv = document.getElementById('weatherForecast');
                forecastDiv.innerHTML = '';
                for(let i=1; i<=3; i++) {
                    const date = new Date(data.daily.time[i]).toLocaleDateString('id-ID', {weekday: 'short'});
                    const max = Math.round(data.daily.temperature_2m_max[i]);
                    const min = Math.round(data.daily.temperature_2m_min[i]);
                    forecastDiv.innerHTML += `
                        <div class="bg-white/5 rounded-lg p-3 min-w-[80px] border border-white/10 hover:border-cyan/30 transition-all">
                            <p class="text-xs text-gray-400 mb-1">${date}</p>
                            <p class="text-lg font-bold text-white">${max}°</p>
                            <p class="text-xs text-gray-500">${min}°</p>
                        </div>`;
                }
            } catch(e) { console.error('Weather error:', e); }
        }

        // ===== AI DATA & WELCOME =====
        const aiData = {
            nama: '<?php echo explode(" ", $nama_user)[0]; ?>',
            tanggal: '<?php echo date("l, d F Y"); ?>',
            cuaca: { temp: null, desc: null },
            stats: {
                tanaman: <?php echo $total_tanaman ?? 0; ?>,
                penjualan: <?php echo $total_penjualan ?? 0; ?>,
                alert_gudang: <?php echo $stok_rendah ?? 0; ?>,
                ai_detections: <?php echo $ai_detections ?? 0; ?>,
                // Data Dua Gudang
                inventaris_items: <?php echo $total_item_inventaris ?? 0; ?>,
                inventaris_nilai: <?php echo $total_nilai_inventaris ?? 0; ?>,
                panen_kg: <?php echo $total_panen_kg ?? 0; ?>,
                panen_nilai: <?php echo $total_nilai_panen ?? 0; ?>,
                panen_terjual: <?php echo $panen_terjual ?? 0; ?>
            }
        };

        loadWeather();

        let hasSpoken = false;
        let typingInterval = null;

        function buildNarration() {
            const h = new Date().getHours();
            const greet = (h>=5&&h<11)?'Selamat pagi':(h>=11&&h<15)?'Selamat siang':(h>=15&&h<18)?'Selamat sore':'Selamat malam';
            
            let txt = `${greet}, Pak ${aiData.nama}. Saya asisten AI RAFLI_FARM, siap memberikan ringkasan sistem dua gudang Anda. `;
            txt += `Hari ini adalah ${aiData.tanggal}. `;
            
            if(aiData.cuaca.temp) {
                txt += `Cuaca di Dompu saat ini ${aiData.cuaca.desc}, dengan suhu ${aiData.cuaca.temp} derajat Celsius. `;
            }
            
            // Gudang Inventaris
            txt += `Laporan Gudang Inventaris: Anda memiliki ${aiData.stats.inventaris_items} jenis barang dengan total nilai Rp ${(aiData.stats.inventaris_nilai/1000000).toFixed(1)} juta. `;
            if(aiData.stats.alert_gudang > 0) {
                txt += `Perhatian! Ada ${aiData.stats.alert_gudang} item stok rendah yang perlu segera direstock. `;
            }
            
            // Gudang Panen
            txt += `Laporan Gudang Panen: Total hasil panen mencapai ${Math.round(aiData.stats.panen_kg)} kilogram dengan nilai Rp ${(aiData.stats.panen_nilai/1000000).toFixed(1)} juta. `;
            txt += `Sudah terjual senilai Rp ${(aiData.stats.panen_terjual/1000000).toFixed(1)} juta. `;
            
            // Tanaman
            txt += `Anda memiliki ${aiData.stats.tanaman} lahan tanaman aktif. `;
            
            if(aiData.stats.ai_detections > 0) {
                txt += `Hari ini sudah ada ${aiData.stats.ai_detections} deteksi AI yang dilakukan. `;
            }
            
            txt += `Semoga hari Anda produktif dan panen melimpah. Terima kasih.`;
            return txt;
        }

        function typeText(text, elementId) {
            const el = document.getElementById(elementId);
            el.textContent = '';
            let i = 0;
            if(typingInterval) clearInterval(typingInterval);
            typingInterval = setInterval(() => {
                if(i < text.length) { el.textContent += text.charAt(i); i++; }
                else clearInterval(typingInterval);
            }, 25);
        }

        function updateProgress(percent) {
            const circle = document.getElementById('progressCircle');
            const circumference = 2 * Math.PI * 35;
            circle.style.strokeDashoffset = circumference - (percent / 100) * circumference;
        }

        function speakAI() {
            if (hasSpoken) return;
            if (!('speechSynthesis' in window)) { alert('Browser tidak mendukung TTS'); return; }

            hasSpoken = true;
            window.speechSynthesis.cancel();
            
            const narration = buildNarration();
            const utterance = new SpeechSynthesisUtterance(narration);
            utterance.lang = 'id-ID';
            utterance.rate = 0.95;
            utterance.pitch = 1.05;
            utterance.volume = 1;

            const setVoice = () => {
                const voices = window.speechSynthesis.getVoices();
                const idVoice = voices.find(v => v.lang.includes('id') || v.lang.includes('ID'));
                if (idVoice) utterance.voice = idVoice;
            };
            setVoice();
            window.speechSynthesis.onvoiceschanged = setVoice;

            document.getElementById('aiModal').classList.add('active');
            document.body.classList.add('ai-speaking');
            typeText(narration, 'transcriptText');
            
            let progress = 0;
            const progressInterval = setInterval(() => {
                if(progress < 90) { progress += 2; updateProgress(progress); }
            }, 500);

            utterance.onend = () => {
                clearInterval(progressInterval);
                updateProgress(100);
                document.body.classList.remove('ai-speaking');
                setTimeout(() => document.getElementById('aiModal').classList.remove('active'), 3000);
            };

            utterance.onerror = (e) => {
                console.error('Speech error:', e);
                clearInterval(progressInterval);
                document.body.classList.remove('ai-speaking');
            };

            window.speechSynthesis.speak(utterance);
        }

        function triggerAI() { hasSpoken = false; speakAI(); }
        function replayAI() { hasSpoken = false; window.speechSynthesis.cancel(); speakAI(); }
        function stopAI() {
            window.speechSynthesis.cancel();
            hasSpoken = true;
            document.getElementById('aiModal').classList.remove('active');
            document.body.classList.remove('ai-speaking');
            if(typingInterval) clearInterval(typingInterval);
        }

        window.addEventListener('load', () => {
            setTimeout(() => {
                try {
                    const test = new SpeechSynthesisUtterance("test");
                    test.volume = 0;
                    window.speechSynthesis.speak(test);
                    window.speechSynthesis.cancel();
                    speakAI();
                } catch(e) { console.log("Autoplay diblokir, menunggu klik..."); }
            }, 1500);
        });

        document.body.addEventListener('click', function firstClick() {
            if (!hasSpoken) speakAI();
            document.body.removeEventListener('click', firstClick);
        }, { once: true });
    </script>
</body>
</html>