<?php
session_start();
include 'koneksi.php';
include 'track_visitor.php';

// ==========================================
// PERBAIKAN ERROR SESSION ID (FALLBACK)
// ==========================================
if (!isset($_SESSION['id']) && isset($_SESSION['username'])) {
    $u = mysqli_real_escape_string($koneksi, $_SESSION['username']);
    $res = $koneksi->query("SELECT id FROM users WHERE username='$u' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $_SESSION['id'] = $res->fetch_assoc()['id'];
    } else {
        header("Location: login.php"); exit;
    }
}

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login.php");
    exit;
}

$nama_user = $_SESSION['nama_lengkap'];
$username = $_SESSION['username'];
$user_id = $_SESSION['id'];

// Kontak Admin/Petani Utama
$admin_wa = "6285333936901"; // Format internasional tanpa +
$admin_email = "rakesazza@gmail.com";

// ==========================================
// HANDLE CRUD PRODUK & TESTIMONI
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_product') {
        $komoditas = mysqli_real_escape_string($koneksi, $_POST['komoditas']);
        $varietas = mysqli_real_escape_string($koneksi, $_POST['varietas']);
        $lahan = mysqli_real_escape_string($koneksi, $_POST['lahan_asal']);
        $berat = (float)$_POST['berat_kg'];
        $harga = (float)$_POST['harga_per_kg'];
        $total_nilai = $berat * $harga;
        $kualitas = $_POST['kualitas'];
        $deskripsi = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
        $hari_panen = (int)$_POST['hari_panen'];
        
        $sql = "INSERT INTO pasar_produk (petani_id, komoditas, varietas, lahan_asal, berat_kg, harga_per_kg, total_nilai, kualitas, deskripsi, hari_panen, status) 
                VALUES ($user_id, '$komoditas', '$varietas', '$lahan', $berat, $harga, $total_nilai, '$kualitas', '$deskripsi', $hari_panen, 'tersedia')";
        
        if ($koneksi->query($sql)) {
            $product_id = $koneksi->insert_id;
            
            // Simpan foto jika ada
            if (!empty($_FILES['foto_produk']['name'][0])) {
                $upload_dir = 'uploads/pasar/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                
                foreach ($_FILES['foto_produk']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['foto_produk']['error'][$key] === UPLOAD_ERR_OK) {
                        $ext = pathinfo($_FILES['foto_produk']['name'][$key], PATHINFO_EXTENSION);
                        $filename = 'prod_' . $product_id . '_' . uniqid() . '.' . $ext;
                        move_uploaded_file($tmp_name, $upload_dir . $filename);
                        $koneksi->query("INSERT INTO pasar_foto (produk_id, filename) VALUES ($product_id, '$filename')");
                    }
                }
            }
            header("Location: pasaran-online.php?success=1");
            exit;
        }
    }
    
    if ($action === 'update_status') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'];
        $koneksi->query("UPDATE pasar_produk SET status='$status' WHERE id=$id AND petani_id=$user_id");
        header("Location: pasaran-online.php?status_updated=1");
        exit;
    }
    
    if ($action === 'add_testimoni') {
        $produk_id = (int)$_POST['produk_id'];
        $rating = (int)$_POST['rating'];
        $testimoni = mysqli_real_escape_string($koneksi, $_POST['testimoni']);
        
        $koneksi->query("INSERT INTO pasar_testimoni (produk_id, pembeli_id, rating, testimoni, created_at) 
                         VALUES ($produk_id, $user_id, $rating, '$testimoni', NOW())");
        header("Location: pasaran-online.php?testimoni=1");
        exit;
    }
}

// ==========================================
// STATISTIK DUA GUDANG + PASAR + AI ANALYTICS
// ==========================================
$total_produk = $koneksi->query("SELECT COUNT(*) as c FROM pasar_produk WHERE petani_id=$user_id")->fetch_assoc()['c'] ?? 0;
$total_terjual = $koneksi->query("SELECT COUNT(*) as c FROM pasar_produk WHERE status='terjual' AND petani_id=$user_id")->fetch_assoc()['c'] ?? 0;
$total_pendapatan = $koneksi->query("SELECT COALESCE(SUM(total_nilai),0) as t FROM pasar_produk WHERE status='terjual' AND petani_id=$user_id")->fetch_assoc()['t'] ?? 0;

// Data Gudang Inventaris
$stok_rendah_inv = $koneksi->query("SELECT COUNT(*) as c FROM gudang_inventaris WHERE stok <= min_stok")->fetch_assoc()['c'] ?? 0;
$total_nilai_inv = $koneksi->query("SELECT COALESCE(SUM(stok * harga_satuan),0) as t FROM gudang_inventaris")->fetch_assoc()['t'] ?? 0;

// Data Gudang Panen
$total_panen_kg = $koneksi->query("SELECT COALESCE(SUM(berat_kg),0) as t FROM gudang_panen")->fetch_assoc()['t'] ?? 0;
$total_nilai_panen = $koneksi->query("SELECT COALESCE(SUM(total_nilai),0) as t FROM gudang_panen")->fetch_assoc()['t'] ?? 0;

// Ambil produk petani
$produk_list = $koneksi->query("SELECT p.*, u.nama_lengkap as petani_nama FROM pasar_produk p JOIN users u ON p.petani_id = u.id WHERE p.petani_id = $user_id ORDER BY p.created_at DESC");

// Marketplace Publik
$produk_terbaru = $koneksi->query("
    SELECT p.*, u.nama_lengkap as petani_nama, 
           (SELECT COUNT(*) FROM pasar_testimoni t WHERE t.produk_id = p.id) as jumlah_testimoni,
           (SELECT AVG(rating) FROM pasar_testimoni t WHERE t.produk_id = p.id) as avg_rating
    FROM pasar_produk p 
    JOIN users u ON p.petani_id = u.id 
    WHERE p.status = 'tersedia' 
    ORDER BY p.created_at DESC LIMIT 8
");

// Testimoni Terbaru
$testimoni_terbaru = $koneksi->query("
    SELECT t.*, p.komoditas, p.varietas, u.nama_lengkap as pembeli_nama, pp.nama_lengkap as penjual_nama
    FROM pasar_testimoni t 
    JOIN pasar_produk p ON t.produk_id = p.id 
    JOIN users u ON t.pembeli_id = u.id
    JOIN users pp ON p.petani_id = pp.id
    ORDER BY t.created_at DESC LIMIT 6
");

// AI Analytics Data (Simulasi Tren Harga)
$tren_harga_data = [15000, 16500, 15800, 17200, 18000, 17500, 19000];
$tren_labels = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pasaran Online Petani - RAFLI_FARM_PRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], display: ['Orbitron', 'sans-serif'] },
                    colors: { neon: '#00FF88', cyan: '#00E5FF', danger: '#FF3366', warning: '#FFB300', purple: '#A855F7', dark: { 900: '#0A0A0A', 800: '#111111' } }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0A0A0A; color: #fff; overflow-x: hidden; }
        
        /* Animated Background Grid */
        body::before { content: ''; position: fixed; inset: 0; background-image: linear-gradient(rgba(0,255,136,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(0,255,136,0.03) 1px, transparent 1px); background-size: 50px 50px; pointer-events: none; z-index: 0; animation: gridMove 20s linear infinite; }
        @keyframes gridMove { 0% { transform: translate(0, 0); } 100% { transform: translate(50px, 50px); } }
        
        /* Aurora Effect */
        body::after { content: ''; position: fixed; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle at 20% 30%, rgba(0,255,136,0.08) 0%, transparent 40%), radial-gradient(circle at 80% 70%, rgba(0,229,255,0.08) 0%, transparent 40%), radial-gradient(circle at 40% 80%, rgba(168,85,247,0.05) 0%, transparent 40%); pointer-events: none; z-index: 0; animation: auroraMove 30s ease-in-out infinite; }
        @keyframes auroraMove { 0%, 100% { transform: translate(0, 0) rotate(0deg); } 33% { transform: translate(5%, 5%) rotate(120deg); } 66% { transform: translate(-5%, -5%) rotate(240deg); } }
        
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.08); position: relative; overflow: hidden; transition: all 0.3s; }
        .glass:hover { background: rgba(255,255,255,0.06); border-color: rgba(0,255,136,0.3); transform: translateY(-2px); box-shadow: 0 10px 40px rgba(0,255,136,0.1); }
        
        .gradient-text { background: linear-gradient(135deg, #00FF88 0%, #00E5FF 50%, #A855F7 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-size: 200% auto; animation: gradientShift 3s ease infinite; }
        @keyframes gradientShift { 0%, 100% { background-position: 0% center; } 50% { background-position: 100% center; } }
        
        .sidebar { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        @media (max-width: 1024px) { .sidebar { transform: translateX(-100%); position: fixed; z-index: 50; } .sidebar.open { transform: translateX(0); } }
        
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-up { animation: fadeUp 0.6s ease-out forwards; opacity: 0; }
        .delay-100 { animation-delay: 0.1s; } .delay-200 { animation-delay: 0.2s; } .delay-300 { animation-delay: 0.3s; }
        
        .menu-active { background: linear-gradient(90deg, rgba(0,255,136,0.15) 0%, transparent 100%); border-left: 3px solid #00FF88; color: #00FF88 !important; }
        
        /* Product Card Hover */
        .product-card { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; }
        .product-card:hover { transform: translateY(-8px) scale(1.02); box-shadow: 0 20px 40px rgba(0,255,136,0.2); }
        
        /* Payment Badges */
        .payment-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 20px; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-qris { background: rgba(0,255,136,0.1); color: #00FF88; border: 1px solid rgba(0,255,136,0.3); }
        .badge-cod { background: rgba(255,179,0,0.1); color: #FFB300; border: 1px solid rgba(255,179,0,0.3); }
        .badge-bank { background: rgba(168,85,247,0.1); color: #A855F7; border: 1px solid rgba(168,85,247,0.3); }
        
        /* AI Chat Window */
        .ai-chat-window { position: fixed; bottom: 100px; right: 30px; width: 360px; height: 500px; background: rgba(10, 10, 10, 0.95); backdrop-filter: blur(20px); border: 1px solid rgba(0,255,136,0.3); border-radius: 20px; overflow: hidden; z-index: 9996; display: none; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.5); }
        .ai-chat-messages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 12px; }
        .message { max-width: 85%; padding: 12px 16px; border-radius: 16px; font-size: 13px; line-height: 1.5; }
        .message.ai { background: rgba(0,255,136,0.1); border-left: 3px solid #00FF88; align-self: flex-start; }
        .message.user { background: rgba(0,229,255,0.1); border-right: 3px solid #00E5FF; align-self: flex-end; }
        
        /* FAB Buttons */
        .fab-btn { position: fixed; right: 30px; width: 60px; height: 60px; border-radius: 50%; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 28px; z-index: 9997; transition: all 0.3s; }
        .fab-ai { bottom: 30px; background: linear-gradient(135deg, #00FF88 0%, #00E5FF 100%); box-shadow: 0 10px 40px rgba(0,255,136,0.5); animation: fabPulse 2s infinite; }
        .fab-contact { bottom: 100px; background: linear-gradient(135deg, #A855F7 0%, #00E5FF 100%); box-shadow: 0 10px 30px rgba(168,85,247,0.5); }
        @keyframes fabPulse { 0%, 100% { box-shadow: 0 10px 40px rgba(0,255,136,0.5); } 50% { box-shadow: 0 10px 60px rgba(0,255,136,0.8); } }
        
        /* Section Title Accent */
        .section-title { position: relative; display: inline-block; padding-left: 16px; }
        .section-title::before { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 4px; height: 24px; background: linear-gradient(180deg, #00FF88, #00E5FF); border-radius: 2px; box-shadow: 0 0 10px rgba(0,255,136,0.5); }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: rgba(255,255,255,0.02); }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #00FF88, #00E5FF); border-radius: 10px; }
        
        /* Rating Stars */
        .star { color: #444; transition: color 0.2s; }
        .star.active { color: #FFD700; text-shadow: 0 0 10px rgba(255,215,0,0.5); }
        
        /* Autocomplete Suggestions */
        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: rgba(10, 10, 10, 0.95);
            border: 1px solid rgba(0,255,136,0.3);
            border-radius: 8px;
            margin-top: 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 50;
            display: none;
        }
        .suggestion-item {
            padding: 10px 16px;
            cursor: pointer;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            transition: all 0.2s;
        }
        .suggestion-item:hover {
            background: rgba(0,255,136,0.1);
            color: #00FF88;
        }
        .suggestion-item:last-child { border-bottom: none; }
    </style>
</head>
<body class="min-h-screen flex relative">

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar w-64 bg-dark-800/90 backdrop-blur-xl border-r border-white/5 flex flex-col h-screen fixed lg:static z-40">
        <div class="p-6 border-b border-white/5 flex items-center gap-3">
            <div class="w-10 h-10 bg-gradient-to-br from-neon to-cyan rounded-xl flex items-center justify-center shadow-lg shadow-neon/20">
                <svg class="w-6 h-6 text-dark-900" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 007.92 12.446A9 9 0 1112 3z" /></svg>
            </div>
            <div><h1 class="text-lg font-bold gradient-text">RAFLI_FARM</h1><p class="text-[10px] text-gray-500 tracking-widest">PROFESSIONAL</p></div>
        </div>
        <nav class="flex-1 overflow-y-auto py-4 space-y-1 px-3">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">📊 Dashboard</a>
            <a href="tanaman.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">🌱 Tanaman</a>
            <a href="gudang.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">📦 Gudang Inventaris</a>
            <a href="gudang-panen.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">🌾 Gudang Panen</a>
            <a href="pasaran-online.php" class="menu-active flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl">🛒 Pasaran Online</a>
            <a href="penjualan.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">💰 Penjualan</a>
            <a href="keuangan.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">💵 Keuangan</a>
            <a href="jadwal.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">📅 Jadwal</a>
            <a href="ai-deteksi.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">🧠 AI Deteksi</a>
            <a href="ai-voice.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">🎤 AI Voice</a>
        </nav>
        <div class="p-4 border-t border-white/5"><a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-danger hover:bg-danger/10 rounded-xl">🚪 Keluar</a></div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen relative z-10">
        <header class="glass border-b border-white/5 px-6 py-4 flex items-center justify-between sticky top-0 z-40">
            <div class="flex items-center gap-4">
                <button id="toggleSidebar" class="lg:hidden p-2 rounded-lg hover:bg-white/10 text-gray-400">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <h2 class="text-xl font-bold gradient-text">🛒 Pasaran Online Petani</h2>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="toggleAIChat()" class="hidden sm:flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-neon/20 to-cyan/20 border border-neon/30 text-neon rounded-lg hover:from-neon/30 hover:to-cyan/30 transition-all text-sm">
                    <span>🤖</span><span>AI Asisten</span>
                </button>
            </div>
        </header>

        <main class="flex-1 p-6 overflow-y-auto">
            
            <!-- Welcome Banner -->
            <div class="glass rounded-2xl p-6 mb-8 animate-fade-up relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-neon/10 rounded-full blur-3xl -mr-16 -mt-16"></div>
                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold mb-2 gradient-text">Selamat Datang di Pasaran Online!</h1>
                        <p class="text-gray-400">Jual hasil panen Anda secara langsung ke konsumen dengan sistem modern & aman</p>
                    </div>
                    <div class="flex gap-3">
                        <a href="#tambah-produk" class="px-4 py-2 bg-neon text-dark-900 font-bold rounded-xl hover:shadow-lg hover:shadow-neon/30 transition-all text-sm flex items-center gap-2">➕ Tambah Produk</a>
                        <a href="#marketplace" class="px-4 py-2 bg-gradient-to-r from-cyan to-purple-500 text-dark-900 font-bold rounded-xl hover:shadow-lg hover:shadow-cyan/30 transition-all text-sm">🌐 Marketplace</a>
                    </div>
                </div>
            </div>

            <!-- Stats Cards (Integrasi 2 Gudang + AI Analytics) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="glass rounded-2xl p-5 border-l-4 border-neon">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-12 h-12 rounded-xl bg-neon/10 flex items-center justify-center text-2xl">🛒</div>
                        <div><p class="text-xs text-gray-400 uppercase tracking-wider">Produk Aktif</p><p class="text-2xl font-bold text-white"><?php echo $total_produk; ?></p></div>
                    </div>
                    <p class="text-[10px] text-gray-500">Produk tersedia di pasar online</p>
                </div>
                <div class="glass rounded-2xl p-5 border-l-4 border-cyan">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-12 h-12 rounded-xl bg-cyan/10 flex items-center justify-center text-2xl">💰</div>
                        <div><p class="text-xs text-gray-400 uppercase tracking-wider">Pendapatan Pasar</p><p class="text-2xl font-bold text-cyan">Rp <?php echo number_format($total_pendapatan/1000000, 1); ?><span class="text-sm text-gray-400"> Jt</span></p></div>
                    </div>
                    <p class="text-[10px] text-gray-500">Total penjualan online</p>
                </div>
                <div class="glass rounded-2xl p-5 border-l-4 border-warning">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-12 h-12 rounded-xl bg-warning/10 flex items-center justify-center text-2xl">⚠️</div>
                        <div><p class="text-xs text-gray-400 uppercase tracking-wider">Stok Inventaris Rendah</p><p class="text-2xl font-bold text-warning"><?php echo $stok_rendah_inv; ?> Item</p></div>
                    </div>
                    <p class="text-[10px] text-gray-500">Perlu restock segera!</p>
                </div>
                <div class="glass rounded-2xl p-5 border-l-4 border-purple">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-12 h-12 rounded-xl bg-purple/10 flex items-center justify-center text-2xl">🌾</div>
                        <div><p class="text-xs text-gray-400 uppercase tracking-wider">Nilai Gudang Panen</p><p class="text-2xl font-bold text-purple-400">Rp <?php echo number_format($total_nilai_panen/1000000, 1); ?><span class="text-sm text-gray-400"> Jt</span></p></div>
                    </div>
                    <p class="text-[10px] text-gray-500">Total aset hasil panen</p>
                </div>
            </div>

            <!-- Form Tambah Produk -->
            <div id="tambah-produk" class="glass rounded-2xl p-6 mb-8 animate-fade-up delay-100">
                <h2 class="text-xl font-bold text-white mb-6 section-title">➕ Tambah Produk Baru</h2>
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="action" value="add_product">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="relative">
                            <label class="block text-sm text-gray-400 mb-2">Komoditas *</label>
                            <input type="text" name="komoditas" id="komoditas_input" required placeholder="Ketik nama komoditas (contoh: Cabai Keriting)" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-neon autocomplete-input">
                            <div id="komoditas_suggestions" class="autocomplete-suggestions"></div>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Varietas</label>
                            <input type="text" name="varietas" id="varietas_input" placeholder="Contoh: IR64, Rawit" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-neon">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Lahan Asal *</label>
                            <input type="text" name="lahan_asal" required placeholder="Contoh: Lahan A, Sawe" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-neon">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Hari Sejak Panen *</label>
                            <input type="number" name="hari_panen" required min="0" value="0" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-neon">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Berat (kg) *</label>
                            <input type="number" step="0.1" name="berat_kg" required min="0.1" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-neon">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Harga/kg (Rp) *</label>
                            <input type="number" name="harga_per_kg" required min="100" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-neon">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Kualitas *</label>
                            <select name="kualitas" required class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-neon">
                                <option value="A">Grade A (Premium)</option>
                                <option value="B">Grade B (Standar)</option>
                                <option value="C">Grade C (Biasa)</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Deskripsi Produk *</label>
                        <textarea name="deskripsi" required rows="3" placeholder="Jelaskan produk Anda..." class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-neon"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Foto Produk (Maks 5)</label>
                        <input type="file" name="foto_produk[]" multiple accept="image/*" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-neon">
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="flex-1 py-3 bg-gradient-to-r from-neon to-cyan text-dark-900 font-bold rounded-xl hover:shadow-lg hover:shadow-neon/30 transition-all">📤 Publikasikan Produk</button>
                        <button type="reset" class="flex-1 py-3 bg-white/5 border border-white/10 text-gray-300 rounded-xl hover:bg-white/10 transition-all">Batal</button>
                    </div>
                </form>
            </div>

            <!-- Produk Saya -->
            <div class="mb-8 animate-fade-up delay-200">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-white section-title">📦 Produk Saya</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php if($produk_list->num_rows > 0): while($row = $produk_list->fetch_assoc()): 
                        $foto = $koneksi->query("SELECT filename FROM pasar_foto WHERE produk_id={$row['id']} LIMIT 1")->fetch_assoc();
                        $img_url = $foto ? 'uploads/pasar/' . $foto['filename'] : 'https://images.unsplash.com/photo-1592150621022-04b798fc8b5c?w=400';
                    ?>
                    <div class="product-card glass rounded-2xl overflow-hidden h-full">
                        <div class="h-48 overflow-hidden relative">
                            <img src="<?php echo $img_url; ?>" alt="<?php echo $row['komoditas']; ?>" class="w-full h-48 object-cover">
                            <div class="absolute top-3 right-3"><span class="px-3 py-1 bg-black/50 text-white text-xs rounded-full"><?php echo $row['kualitas']; ?></span></div>
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-4">
                                <h3 class="text-white font-bold"><?php echo $row['komoditas']; ?> - <?php echo $row['varietas']; ?></h3>
                                <p class="text-gray-300 text-sm"><?php echo $row['lahan_asal']; ?></p>
                            </div>
                        </div>
                        <div class="p-5">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <p class="text-sm text-gray-400">Berat: <?php echo number_format($row['berat_kg'], 1); ?> kg</p>
                                    <p class="text-sm text-gray-400">Sejak panen: <?php echo $row['hari_panen']; ?> hari</p>
                                </div>
                                <span class="text-lg font-bold text-neon">Rp <?php echo number_format($row['harga_per_kg'], 0); ?>/kg</span>
                            </div>
                            <p class="text-gray-300 text-sm mb-4 line-clamp-2"><?php echo substr(strip_tags($row['deskripsi']), 0, 80); ?>...</p>
                            <div class="flex gap-2">
                                <button onclick="updateStatus(<?php echo $row['id']; ?>, 'terjual')" class="flex-1 py-2 bg-green-500/20 hover:bg-green-500/30 text-green-400 rounded-lg text-sm">✅ Tandai Terjual</button>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; else: ?>
                    <div class="col-span-full text-center py-12">
                        <div class="text-5xl mb-4">📦</div>
                        <p class="text-gray-500">Anda belum memiliki produk di pasar online</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Marketplace Publik -->
            <div id="marketplace" class="mb-8 animate-fade-up delay-300">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-white section-title">🌐 Marketplace Publik</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php if($produk_terbaru->num_rows > 0): while($row = $produk_terbaru->fetch_assoc()): 
                        $foto_pub = $koneksi->query("SELECT filename FROM pasar_foto WHERE produk_id={$row['id']} LIMIT 1")->fetch_assoc();
                        $img_pub = $foto_pub ? 'uploads/pasar/' . $foto_pub['filename'] : 'https://images.unsplash.com/photo-1592150621022-04b798fc8b5c?w=400';
                    ?>
                    <div class="product-card glass rounded-2xl overflow-hidden h-full">
                        <div class="h-48 overflow-hidden relative">
                            <img src="<?php echo $img_pub; ?>" alt="<?php echo $row['komoditas']; ?>" class="w-full h-48 object-cover">
                            <div class="absolute top-3 left-3"><span class="px-3 py-1 bg-black/50 text-white text-xs rounded-full"><?php echo $row['kualitas']; ?></span></div>
                            <div class="absolute top-3 right-3"><span class="px-2 py-1 bg-neon/20 text-neon text-xs rounded-full"><?php echo $row['hari_panen']; ?> hari</span></div>
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-4">
                                <h3 class="text-white font-bold"><?php echo $row['komoditas']; ?></h3>
                                <p class="text-gray-300 text-sm"><?php echo $row['varietas']; ?></p>
                            </div>
                        </div>
                        <div class="p-5">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <p class="text-sm text-gray-400"><?php echo $row['petani_nama']; ?></p>
                                    <p class="text-sm text-gray-400"><?php echo $row['lahan_asal']; ?></p>
                                </div>
                                <span class="text-lg font-bold text-cyan">Rp <?php echo number_format($row['harga_per_kg'], 0); ?>/kg</span>
                            </div>
                            <div class="flex items-center gap-2 mb-3">
                                <div class="flex">
                                    <?php for($i = 1; $i <= 5; $i++): ?><span class="star <?php echo $i <= ($row['avg_rating'] ?? 4) ? 'active' : ''; ?>">★</span><?php endfor; ?>
                                </div>
                                <span class="text-xs text-gray-500"><?php echo number_format($row['avg_rating'] ?? 4.7, 1); ?> (<?php echo $row['jumlah_testimoni'] ?? 0; ?>)</span>
                            </div>
                            <p class="text-gray-300 text-sm mb-4 line-clamp-2"><?php echo substr(strip_tags($row['deskripsi']), 0, 80); ?>...</p>
                            <div class="flex gap-2 mb-3">
                                <span class="payment-badge badge-qris">QRIS</span>
                                <span class="payment-badge badge-cod">COD</span>
                                <span class="payment-badge badge-bank">Bank</span>
                            </div>
                            <div class="flex gap-2">
                                <button class="flex-1 py-2 bg-gradient-to-r from-neon to-cyan text-dark-900 rounded-lg text-sm font-bold">Beli Sekarang</button>
                                <button onclick="contactSeller('<?php echo $admin_wa; ?>', '<?php echo $admin_email; ?>', '<?php echo addslashes($row['komoditas']); ?>')" class="py-2 bg-white/5 hover:bg-white/10 text-white rounded-lg text-sm">💬 Hubungi</button>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; else: ?>
                    <div class="col-span-full text-center py-12">
                        <div class="text-5xl mb-4">🔍</div>
                        <p class="text-gray-500">Belum ada produk di marketplace</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Testimoni -->
            <div class="glass rounded-2xl p-6 mb-8 animate-fade-up delay-400">
                <h2 class="text-xl font-bold text-white mb-6 section-title">⭐ Testimoni Terbaru</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php if($testimoni_terbaru->num_rows > 0): while($row = $testimoni_terbaru->fetch_assoc()): ?>
                    <div class="bg-dark-800/50 p-5 rounded-xl border border-white/10">
                        <div class="flex items-start gap-3 mb-4">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-cyan to-purple-500 flex items-center justify-center font-bold text-sm"><?php echo strtoupper(substr($row['pembeli_nama'], 0, 1)); ?></div>
                            <div>
                                <h3 class="font-bold text-white"><?php echo $row['pembeli_nama']; ?></h3>
                                <p class="text-gray-400 text-sm"><?php echo $row['komoditas']; ?> - <?php echo $row['varietas']; ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 mb-3">
                            <div class="flex"><?php for($i = 1; $i <= 5; $i++): ?><span class="star <?php echo $i <= $row['rating'] ? 'active' : ''; ?>">★</span><?php endfor; ?></div>
                            <span class="text-sm text-gray-500"><?php echo date('d M Y', strtotime($row['created_at'])); ?></span>
                        </div>
                        <p class="text-gray-300"><?php echo $row['testimoni']; ?></p>
                    </div>
                    <?php endwhile; else: ?>
                    <div class="col-span-full text-center py-8">
                        <div class="text-4xl mb-4">💬</div>
                        <p class="text-gray-500">Belum ada testimoni</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- AI Chat Window -->
    <div class="ai-chat-window" id="aiChatWindow">
        <div class="p-4 bg-neon/10 border-b border-white/5 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-neon to-cyan flex items-center justify-center">🤖</div>
                <div><h3 class="font-bold text-white text-sm">AI Pasar Asisten</h3><p class="text-[10px] text-gray-400">Online • Siap membantu</p></div>
            </div>
            <button onclick="closeAIChat()" class="text-gray-400 hover:text-white">✕</button>
        </div>
        <div class="ai-chat-messages" id="aiChatMessages">
            <div class="message ai">
                <p>Halo! Saya AI Pasar Asisten. Saya bisa membantu Anda:</p>
                <p class="mt-2 text-xs text-gray-400">• Menentukan harga optimal berdasarkan tren pasar</p>
                <p class="text-xs text-gray-400">• Menganalisis permintaan komoditas terkini</p>
                <p class="text-xs text-gray-400">• Memberikan tips strategi pemasaran digital</p>
                <p class="mt-2 text-xs text-gray-400">Apa yang ingin Anda ketahui?</p>
            </div>
        </div>
        <div class="p-3 border-t border-white/5 flex gap-2">
            <input type="text" id="aiChatInput" placeholder="Tanyakan tentang pasar..." class="flex-1 bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-neon" onkeypress="if(event.key==='Enter') sendAIChat()">
            <button onclick="sendAIChat()" class="bg-gradient-to-r from-neon to-cyan text-dark-900 rounded-lg px-4 py-2 text-sm font-bold">Kirim</button>
        </div>
    </div>

    <!-- FAB Buttons -->
    <button class="fab-btn fab-contact" onclick="contactSeller('<?php echo $admin_wa; ?>', '<?php echo $admin_email; ?>', 'Umum')" title="Hubungi Pembeli">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
    </button>
    <button class="fab-btn fab-ai" onclick="toggleAIChat()" title="AI Asisten">🤖</button>

    <!-- JavaScript -->
    <script>
        // Sidebar Toggle
        document.getElementById('toggleSidebar').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('open'));

        // AI Chat Functions
        function toggleAIChat() {
            const win = document.getElementById('aiChatWindow');
            win.style.display = win.style.display === 'none' || win.style.display === '' ? 'flex' : 'none';
        }
        function closeAIChat() { document.getElementById('aiChatWindow').style.display = 'none'; }
        
        function sendAIChat() {
            const input = document.getElementById('aiChatInput');
            const msg = input.value.trim();
            if (!msg) return;
            
            const messages = document.getElementById('aiChatMessages');
            
            // User Message
            const userMsg = document.createElement('div');
            userMsg.className = 'message user';
            userMsg.innerHTML = `<p>${msg}</p>`;
            messages.appendChild(userMsg);
            input.value = '';
            messages.scrollTop = messages.scrollHeight;
            
            // Simulate AI Response (Ultimate Smart Responses)
            setTimeout(() => {
                let response = "";
                const lowerMsg = msg.toLowerCase();
                
                if(lowerMsg.includes('harga') || lowerMsg.includes('mahal')) {
                    response = "Berdasarkan analisis pasar Dompu saat ini, harga optimal untuk komoditas tersebut adalah Rp 15.000 - Rp 22.000/kg tergantung grade kualitas. Grade A bisa dijual lebih tinggi hingga Rp 25.000/kg.";
                } else if(lowerMsg.includes('tren') || lowerMsg.includes('permintaan')) {
                    response = "Tren pasar minggu ini menunjukkan peningkatan permintaan untuk Cabai Rawit dan Tomat sebesar 15%. Disarankan untuk memprioritaskan panen kedua komoditas tersebut.";
                } else if(lowerMsg.includes('tips') || lowerMsg.includes('strategi')) {
                    response = "Tips terbaik: Gunakan foto berkualitas tinggi, berikan deskripsi detail termasuk hari sejak panen, dan tawarkan opsi pembayaran QRIS untuk meningkatkan kepercayaan pembeli.";
                } else {
                    response = "Terima kasih atas pertanyaan Anda! Saya terus belajar dari data pasar RAFLI_FARM. Untuk informasi lebih spesifik, silakan coba tanyakan tentang 'harga cabai', 'tren pasar', atau 'tips jualan'.";
                }
                
                const aiMsg = document.createElement('div');
                aiMsg.className = 'message ai';
                aiMsg.innerHTML = `<p>${response}</p>`;
                messages.appendChild(aiMsg);
                messages.scrollTop = messages.scrollHeight;
            }, 1200);
        }

        // Update Status Function
        function updateStatus(id, status) {
            if (confirm(`Ubah status produk menjadi "${status}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="action" value="update_status"><input type="hidden" name="id" value="${id}"><input type="hidden" name="status" value="${status}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Contact Seller Function
        function contactSeller(wa, email, product) {
            const message = encodeURIComponent(`Halo, saya tertarik dengan produk ${product} di Pasaran Online RAFLI_FARM. Bisa info lebih lanjut?`);
            const waLink = `https://wa.me/${wa}?text=${message}`;
            const mailLink = `mailto:${email}?subject=Pertanyaan Produk ${product}&body=${message}`;
            
            // Open WhatsApp in new tab
            window.open(waLink, '_blank');
            
            // Optional: Also open email client
            // window.location.href = mailLink;
        }

        // Autocomplete for Komoditas Input
        const komoditasInput = document.getElementById('komoditas_input');
        const suggestionsBox = document.getElementById('komoditas_suggestions');
        
        // Database of common commodities (can be expanded or fetched from server)
        const komoditasDB = [
            'Cabai Merah', 'Cabai Rawit', 'Cabai Keriting', 'Tomat', 'Bawang Merah', 'Bawang Putih',
            'Padi', 'Jagung', 'Kedelai', 'Kentang', 'Wortel', 'Kubis', 'Sawi', 'Bayam', 'Kangkung',
            'Melon', 'Semangka', 'Pepaya', 'Pisang', 'Mangga', 'Jeruk', 'Apel', 'Anggur',
            'Tembakau', 'Kelapa', 'Kelapa Sawit', 'Kopi', 'Coklat', 'Vanili', 'Jahe', 'Kunyit',
            'Lengkuas', 'Serai', 'Daun Bawang', 'Seledri', 'Kemangi', 'Mentimun', 'Labu Siam',
            'Terong', 'Okra', 'Brokoli', 'Kembang Kol', 'Asparagus', 'Artichoke', 'Zucchini'
        ];

        komoditasInput.addEventListener('input', function() {
            const value = this.value.toLowerCase();
            suggestionsBox.innerHTML = '';
            
            if (value.length > 0) {
                const filtered = komoditasDB.filter(item => item.toLowerCase().includes(value));
                
                if (filtered.length > 0) {
                    suggestionsBox.style.display = 'block';
                    filtered.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'suggestion-item';
                        div.textContent = item;
                        div.onclick = function() {
                            komoditasInput.value = item;
                            suggestionsBox.style.display = 'none';
                            
                            // Auto-suggest varieties based on commodity
                            suggestVarieties(item);
                        };
                        suggestionsBox.appendChild(div);
                    });
                } else {
                    suggestionsBox.style.display = 'none';
                }
            } else {
                suggestionsBox.style.display = 'none';
            }
        });

        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!komoditasInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                suggestionsBox.style.display = 'none';
            }
        });

        // Auto-suggest varieties based on selected commodity
        function suggestVarieties(komoditas) {
            const varietasInput = document.getElementById('varietas_input');
            const varietyMap = {
                'Cabai Merah': 'Tanjung, Keriting, Besar',
                'Cabai Rawit': 'Rawit Lokal, Rawit Thailand',
                'Cabai Keriting': 'Keriting Hijau, Keriting Merah',
                'Tomat': 'Cherry, Beefsteak, Roma',
                'Bawang Merah': 'Brebes, Probolinggo Kuning',
                'Bawang Putih': 'Lumbu Putih, Lumbu Hijau',
                'Padi': 'IR64, Ciherang, Mentik Wangi',
                'Jagung': 'Bisi-2, NK212, Pioner',
                'Kedelai': 'Anjasmoro, Grobogan',
                'Kentang': 'Granola, Atlantic',
                'Wortel': 'Lokal, Import',
                'Kubis': 'Kubis Bulat, Kubis Keriting',
                'Melon': 'Golden Honey, Rock Melon',
                'Semangka': 'Non Biji, Biji Hitam',
                'Pepaya': 'California, Bangkok',
                'Pisang': 'Ambon, Cavendish, Raja',
                'Mangga': 'Harum Manis, Gedong Gincu',
                'Jeruk': 'Sunkist, Lokal',
                'Tembakau': 'Virginia, Burley',
                'Kelapa': 'Hibrida, Genjah',
                'Kopi': 'Arabika, Robusta',
                'Coklat': 'Criollo, Forastero'
            };
            
            if (varietyMap[komoditas]) {
                varietasInput.placeholder = `Contoh: ${varietyMap[komoditas]}`;
            } else {
                varietasInput.placeholder = 'Contoh: Varietas lokal, unggul';
            }
        }
    </script>
</body>
</html>