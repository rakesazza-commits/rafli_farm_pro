<?php
session_start();
include 'koneksi.php';
include 'track_visitor.php';
// Cek Login
if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }

// ==========================================
// LOGIKA BACKEND (PROSES FORM)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add' || $action === 'edit') {
        $pembeli = mysqli_real_escape_string($koneksi, $_POST['nama_pembeli']);
        $produk = mysqli_real_escape_string($koneksi, $_POST['nama_produk']);
        $jumlah = (float)$_POST['jumlah'];
        $satuan = $_POST['satuan'];
        $harga = (float)$_POST['harga_satuan'];
        $tanggal = $_POST['tanggal_jual'];
        $status = $_POST['status_pembayaran'];
        $catatan = mysqli_real_escape_string($koneksi, $_POST['catatan']);
        
        // Hitung total otomatis
        $total = $jumlah * $harga;

        // ===== INTEGRASI GUDANG =====
        $inventory_id = null;
        $stok_sebelum = null;
        $stok_sesudah = null;
        
        // Cari produk di gudang (case-insensitive matching)
        $cek_gudang = $koneksi->query("SELECT id, stok FROM inventory WHERE LOWER(nama_barang) = LOWER('$produk') LIMIT 1");
        
        if ($cek_gudang->num_rows > 0) {
            $gudang = $cek_gudang->fetch_assoc();
            $inventory_id = $gudang['id'];
            $stok_sebelum = $gudang['stok'];
            
            // Cek stok cukup atau tidak
            if ($gudang['stok'] < $jumlah) {
                header("Location: penjualan.php?error=stok_tidak_cukup&produk=" . urlencode($produk));
                exit;
            }
        }

        if ($action === 'add') {
            // Generate Invoice Number
            $inv = "INV-" . date('Ymd') . "-" . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $sql = "INSERT INTO penjualan (no_invoice, nama_pembeli, nama_produk, jumlah, satuan, harga_satuan, total_harga, tanggal_jual, status_pembayaran, catatan, inventory_id, stok_sebelum, stok_sesudah) 
                    VALUES ('$inv', '$pembeli', '$produk', '$jumlah', '$satuan', '$harga', '$total', '$tanggal', '$status', '$catatan', " . ($inventory_id ?? 'NULL') . ", " . ($stok_sebelum ?? 'NULL') . ", NULL)";
            
            if ($koneksi->query($sql)) {
                $penjualan_id = $koneksi->insert_id;
                
                // Kurangi stok gudang jika produk ditemukan
                if ($inventory_id !== null) {
                    $stok_baru = $stok_sebelum - $jumlah;
                    $koneksi->query("UPDATE inventory SET stok = $stok_baru WHERE id = $inventory_id");
                    $koneksi->query("UPDATE penjualan SET stok_sesudah = $stok_baru WHERE id = $penjualan_id");
                }
                
                header("Location: penjualan.php?success=1");
                exit;
            }
        } else {
            $id = (int)$_POST['id'];
            $inv = $_POST['no_invoice'];
            
            // Untuk edit, kembalikan stok lama dulu jika ada
            $old_data = $koneksi->query("SELECT inventory_id, jumlah, stok_sebelum FROM penjualan WHERE id=$id")->fetch_assoc();
            if ($old_data['inventory_id']) {
                $koneksi->query("UPDATE inventory SET stok = stok + {$old_data['jumlah']} WHERE id = {$old_data['inventory_id']}");
            }
            
            $sql = "UPDATE penjualan SET no_invoice='$inv', nama_pembeli='$pembeli', nama_produk='$produk', jumlah='$jumlah', satuan='$satuan', 
                    harga_satuan='$harga', total_harga='$total', tanggal_jual='$tanggal', status_pembayaran='$status', catatan='$catatan',
                    inventory_id=" . ($inventory_id ?? 'NULL') . ", stok_sebelum=" . ($stok_sebelum ?? 'NULL') . ", stok_sesudah=NULL WHERE id=$id";
            
            if ($koneksi->query($sql)) {
                // Kurangi stok baru
                if ($inventory_id !== null) {
                    $stok_baru = $stok_sebelum - $jumlah;
                    $koneksi->query("UPDATE inventory SET stok = $stok_baru WHERE id = $inventory_id");
                    $koneksi->query("UPDATE penjualan SET stok_sesudah = $stok_baru WHERE id = $id");
                }
                
                header("Location: penjualan.php?success=1");
                exit;
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        // Kembalikan stok ke gudang jika ada
        $data = $koneksi->query("SELECT inventory_id, jumlah FROM penjualan WHERE id=$id")->fetch_assoc();
        if ($data && $data['inventory_id']) {
            $koneksi->query("UPDATE inventory SET stok = stok + {$data['jumlah']} WHERE id = {$data['inventory_id']}");
        }
        
        $koneksi->query("DELETE FROM penjualan WHERE id=$id");
        header("Location: penjualan.php?deleted=1");
        exit;
    }
}

// Ambil Data & Filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$sql = "SELECT * FROM penjualan WHERE (nama_pembeli LIKE '%$search%' OR no_invoice LIKE '%$search%' OR nama_produk LIKE '%$search%')";
if ($filter !== 'all') {
    $sql .= " AND status_pembayaran = '$filter'";
}
$sql .= " ORDER BY tanggal_jual DESC";
$result = $koneksi->query($sql);

// Hitung Total Pendapatan
$total_pendapatan = $koneksi->query("SELECT SUM(total_harga) as total FROM penjualan WHERE status_pembayaran='lunas'")->fetch_assoc()['total'] ?? 0;
$total_pending = $koneksi->query("SELECT COUNT(*) as c FROM penjualan WHERE status_pembayaran='pending'")->fetch_assoc()['c'] ?? 0;

// Ambil produk dari gudang untuk dropdown
$products_gudang = $koneksi->query("SELECT nama_barang, stok, satuan FROM gudang_inventaris ORDER BY nama_barang");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penjualan - RAFLI_FARM_PRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], mono: ['JetBrains Mono', 'monospace'] },
                    colors: {
                        neon: '#00FF88', cyan: '#00E5FF', danger: '#FF3366', warning: '#FFB300', purple: '#A855F7',
                        dark: { 900: '#0A0A0A', 800: '#111111', 700: '#1A1A2E' }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0A0A0A; color: #fff; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .gradient-text { background: linear-gradient(135deg, #00FF88, #00E5FF); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .modal-overlay { transition: opacity 0.3s ease; }
        .modal-content { transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .modal-hidden { opacity: 0; pointer-events: none; }
        .modal-hidden .modal-content { transform: scale(0.9) translateY(20px); }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #00FF88; border-radius: 10px; }
    </style>
</head>
<body class="min-h-screen flex relative">

    <canvas id="weatherCanvas" class="fixed inset-0 z-0 pointer-events-none"></canvas>

    <!-- Sidebar -->
    <aside class="w-64 bg-dark-800/90 backdrop-blur-xl border-r border-white/5 flex flex-col h-screen fixed z-40 hidden lg:flex">
        <div class="p-6 border-b border-white/5">
            <h1 class="text-xl font-bold gradient-text">RAFLI_FARM</h1>
        </div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6z" /></svg>
                Dashboard
            </a>
            <a href="tanaman.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg>
                Tanaman
            </a>
            <a href="gudang.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                Gudang
            </a>
            <a href="penjualan.php" class="flex items-center gap-3 px-4 py-3 bg-neon/10 text-neon border-l-4 border-neon rounded-r-xl font-bold">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Penjualan
            </a>
            <a href="ai-deteksi.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors">AI Deteksi</a>
            <a href="ai-voice.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors">AI Voice</a>
            <a href="kalkulator.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors">Kalkulator Pupuk</a>
            <a href="keuangan.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">💵 Keuangan</a>
            <a href="jadwal.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">📅 Jadwal</a>
        </nav>
        <div class="p-4 border-t border-white/5">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-danger hover:bg-danger/10 rounded-xl transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                Keluar
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 lg:ml-64 relative z-10 p-6">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold gradient-text">Manajemen Penjualan</h1>
                <p class="text-gray-500 text-sm mt-1">Catat transaksi dan pantau pendapatan hasil panen</p>
                <p class="text-xs text-cyan mt-2 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Terintegrasi dengan Gudang - Stok otomatis berkurang saat penjualan
                </p>
            </div>
            <!-- TOMBOL TAMBAH -->
            <button onclick="openModal('modalForm', 'add')" class="px-6 py-3 bg-neon text-dark-900 font-bold rounded-xl hover:shadow-lg hover:shadow-neon/30 transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                Catat Penjualan
            </button>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="glass rounded-2xl p-4 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-neon/10 flex items-center justify-center text-neon">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Total Pendapatan (Lunas)</p>
                    <p class="text-xl font-bold text-white font-mono">Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></p>
                </div>
            </div>
            <div class="glass rounded-2xl p-4 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-cyan/10 flex items-center justify-center text-cyan">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Total Transaksi</p>
                    <p class="text-xl font-bold text-white font-mono"><?php echo $result->num_rows; ?> Data</p>
                </div>
            </div>
            <div class="glass rounded-2xl p-4 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-warning/10 flex items-center justify-center text-warning">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Menunggu Pembayaran</p>
                    <p class="text-xl font-bold text-white font-mono"><?php echo $total_pending; ?> Transaksi</p>
                </div>
            </div>
        </div>

        <!-- Filter & Reset Bar -->
        <form method="GET" class="glass rounded-2xl p-4 mb-6 flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[200px] relative">
                <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                <input type="text" name="search" value="<?php echo $search; ?>" placeholder="Cari invoice, pembeli, atau produk..." class="w-full pl-10 pr-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon">
            </div>
            <select name="filter" class="bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-neon">
                <option value="all" <?php if($filter=='all') echo 'selected'; ?>>Semua Status</option>
                <option value="lunas" <?php if($filter=='lunas') echo 'selected'; ?>>Lunas</option>
                <option value="pending" <?php if($filter=='pending') echo 'selected'; ?>>Pending</option>
                <option value="batal" <?php if($filter=='batal') echo 'selected'; ?>>Batal</option>
            </select>
            
            <!-- TOMBOL RESET -->
            <a href="penjualan.php" class="px-4 py-2 bg-white/5 border border-white/10 text-gray-300 rounded-lg hover:bg-white/10 hover:text-white transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                Reset
            </a>
        </form>

        <!-- Alerts -->
        <?php if(isset($_GET['success'])): ?>
            <div class="mb-4 p-4 bg-neon/10 border border-neon/30 rounded-xl text-neon text-sm flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                Transaksi berhasil disimpan & stok gudang otomatis diperbarui!
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['error'])): ?>
            <div class="mb-4 p-4 bg-danger/10 border border-danger/30 rounded-xl text-danger text-sm flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                <?php 
                if($_GET['error'] == 'stok_tidak_cukup') {
                    echo 'Stok gudang tidak mencukupi untuk produk: <b>' . htmlspecialchars($_GET['produk']) . '</b>. Silakan tambah stok di gudang terlebih dahulu.';
                } else {
                    echo 'Terjadi kesalahan!';
                }
                ?>
            </div>
        <?php endif; ?>

        <!-- Table Container -->
        <div class="glass rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-white/5 text-gray-400 text-xs uppercase tracking-wider">
                        <tr>
                            <th class="p-4">Invoice</th>
                            <th class="p-4">Pembeli & Produk</th>
                            <th class="p-4">Jumlah</th>
                            <th class="p-4">Total Harga</th>
                            <th class="p-4">Tanggal</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php 
                        if ($result->num_rows > 0):
                            while($row = $result->fetch_assoc()): 
                                $status_map = [
                                    'lunas' => ['bg-neon/10 text-neon', 'Lunas'],
                                    'pending' => ['bg-warning/10 text-warning', 'Pending'],
                                    'batal' => ['bg-danger/10 text-danger', 'Batal']
                                ];
                                $s = $status_map[$row['status_pembayaran']] ?? $status_map['pending'];
                        ?>
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="p-4 font-mono text-xs text-cyan"><?php echo $row['no_invoice']; ?></td>
                            <td class="p-4">
                                <p class="font-medium text-white"><?php echo $row['nama_pembeli']; ?></p>
                                <p class="text-xs text-gray-500"><?php echo $row['nama_produk']; ?></p>
                                <?php if($row['inventory_id']): ?>
                                    <p class="text-[10px] text-cyan mt-1">
                                        Stok: <?php echo $row['stok_sebelum']; ?> → <?php echo $row['stok_sesudah']; ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-gray-300"><?php echo $row['jumlah']; ?> <?php echo $row['satuan']; ?></td>
                            <td class="p-4 font-bold text-white font-mono">Rp <?php echo number_format($row['total_harga'], 0, ',', '.'); ?></td>
                            <td class="p-4 text-sm text-gray-400"><?php echo date('d M Y', strtotime($row['tanggal_jual'])); ?></td>
                            <td class="p-4">
                                <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $s[0]; ?>">
                                    <?php echo $s[1]; ?>
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Tombol Edit -->
                                    <button onclick='openModal("modalForm", "edit", <?php echo json_encode($row); ?>)' class="p-2 bg-blue-500/10 text-blue-400 rounded-lg hover:bg-blue-500/20 transition-colors" title="Edit">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                    </button>
                                    <!-- Tombol Hapus -->
                                    <form method="POST" onsubmit="return confirm('Yakin ingin menghapus transaksi ini? Stok gudang akan dikembalikan.')" class="inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="p-2 bg-danger/10 text-danger rounded-lg hover:bg-danger/20 transition-colors" title="Hapus">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="p-10 text-center text-gray-500">
                                    <p class="text-4xl mb-2">🧾</p>
                                    Belum ada data penjualan.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL: TAMBAH / EDIT PENJUALAN -->
    <!-- ========================================== -->
    <div id="modalForm" class="modal-overlay modal-hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div class="modal-content glass rounded-2xl w-full max-w-2xl p-6 border border-white/10 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold gradient-text" id="modalTitle">Catat Penjualan Baru</h3>
                <button onclick="closeModal('modalForm')" class="text-gray-400 hover:text-white">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="no_invoice" id="editInvoice">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Nama Pembeli</label>
                        <input type="text" name="nama_pembeli" id="f_pembeli" required class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Produk (dari Gudang)</label>
                        <select name="nama_produk" id="f_produk" required class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon" onchange="updateSatuan()">
                            <option value="">Pilih Produk...</option>
                            <?php 
                            $products_gudang->data_seek(0);
                            while($p = $products_gudang->fetch_assoc()): 
                            ?>
                                <option value="<?php echo htmlspecialchars($p['nama_barang']); ?>" data-satuan="<?php echo $p['satuan']; ?>" data-stok="<?php echo $p['stok']; ?>">
                                    <?php echo htmlspecialchars($p['nama_barang']); ?> (Stok: <?php echo $p['stok']; ?> <?php echo $p['satuan']; ?>)
                                </option>
                            <?php endwhile; ?>
                            <option value="__custom__">+ Produk Custom (tidak di gudang)</option>
                        </select>
                        <p class="text-xs text-cyan mt-1" id="stokInfo"></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Jumlah</label>
                        <input type="number" step="0.01" name="jumlah" id="f_jumlah" required class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon" oninput="calcTotal()">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Satuan</label>
                        <input type="text" name="satuan" id="f_satuan" placeholder="kg/ton/pcs" required class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Harga Satuan (Rp)</label>
                        <input type="number" name="harga_satuan" id="f_harga" required class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon" oninput="calcTotal()">
                    </div>
                </div>

                <!-- Auto Calculated Total -->
                <div class="p-4 bg-neon/5 border border-neon/20 rounded-xl flex justify-between items-center">
                    <span class="text-gray-400 font-medium">Total Harga:</span>
                    <span class="text-2xl font-bold text-neon font-mono" id="displayTotal">Rp 0</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Tanggal Jual</label>
                        <input type="date" name="tanggal_jual" id="f_tanggal" required class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Status Pembayaran</label>
                        <select name="status_pembayaran" id="f_status" class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon">
                            <option value="pending">Pending (Belum Lunas)</option>
                            <option value="lunas">Lunas</option>
                            <option value="batal">Batal</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Catatan (Opsional)</label>
                    <textarea name="catatan" id="f_catatan" rows="2" class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon"></textarea>
                </div>
                
                <div class="flex gap-3 pt-4">
                    <!-- TOMBOL SIMPAN -->
                    <button type="submit" class="flex-1 py-3 bg-neon text-dark-900 font-bold rounded-xl hover:shadow-lg hover:shadow-neon/30 transition-all">
                        Simpan Transaksi
                    </button>
                    <!-- TOMBOL BATAL -->
                    <button type="button" onclick="closeModal('modalForm')" class="flex-1 py-3 bg-white/5 border border-white/10 text-gray-300 rounded-xl hover:bg-white/10 transition-all">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script>
        // Auto Calculate Total
        function calcTotal() {
            const qty = parseFloat(document.getElementById('f_jumlah').value) || 0;
            const price = parseFloat(document.getElementById('f_harga').value) || 0;
            const total = qty * price;
            document.getElementById('displayTotal').innerText = 'Rp ' + total.toLocaleString('id-ID');
        }

        // Update satuan otomatis dari gudang
        function updateSatuan() {
            const select = document.getElementById('f_produk');
            const selected = select.options[select.selectedIndex];
            const stokInfo = document.getElementById('stokInfo');
            
            if (selected.value === '__custom__') {
                document.getElementById('f_satuan').value = '';
                document.getElementById('f_satuan').readOnly = false;
                stokInfo.innerText = '';
            } else if (selected.value) {
                document.getElementById('f_satuan').value = selected.dataset.satuan;
                document.getElementById('f_satuan').readOnly = true;
                stokInfo.innerText = 'Stok tersedia: ' + selected.dataset.stok + ' ' + selected.dataset.satuan;
            }
        }

        // Modal Functions
        function openModal(id, mode, data = null) {
            document.getElementById(id).classList.remove('modal-hidden');
            if (mode === 'add') {
                document.getElementById('modalTitle').innerText = 'Catat Penjualan Baru';
                document.getElementById('formAction').value = 'add';
                document.getElementById('editId').value = '';
                document.getElementById('editInvoice').value = '';
                document.getElementById('f_pembeli').value = '';
                document.getElementById('f_produk').value = '';
                document.getElementById('f_jumlah').value = '';
                document.getElementById('f_satuan').value = '';
                document.getElementById('f_satuan').readOnly = false;
                document.getElementById('f_harga').value = '';
                document.getElementById('f_tanggal').value = new Date().toISOString().split('T')[0];
                document.getElementById('f_status').value = 'pending';
                document.getElementById('f_catatan').value = '';
                document.getElementById('stokInfo').innerText = '';
                calcTotal();
            } else if (mode === 'edit' && data) {
                document.getElementById('modalTitle').innerText = 'Edit Transaksi: ' + data.no_invoice;
                document.getElementById('formAction').value = 'edit';
                document.getElementById('editId').value = data.id;
                document.getElementById('editInvoice').value = data.no_invoice;
                document.getElementById('f_pembeli').value = data.nama_pembeli;
                document.getElementById('f_produk').value = data.nama_produk;
                document.getElementById('f_jumlah').value = data.jumlah;
                document.getElementById('f_satuan').value = data.satuan;
                document.getElementById('f_satuan').readOnly = false;
                document.getElementById('f_harga').value = data.harga_satuan;
                document.getElementById('f_tanggal').value = data.tanggal_jual;
                document.getElementById('f_status').value = data.status_pembayaran;
                document.getElementById('f_catatan').value = data.catatan;
                document.getElementById('stokInfo').innerText = '';
                calcTotal();
            }
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('modal-hidden');
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.add('modal-hidden');
            }
        }

        // Weather Canvas (Sakura default)
        const canvas = document.getElementById('weatherCanvas');
        const ctx = canvas.getContext('2d');
        let particles = [];
        function resizeCanvas() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
        resizeCanvas(); window.addEventListener('resize', resizeCanvas);
        class Particle {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.size = 8 + Math.random() * 6;
                this.speedY = 1 + Math.random() * 1.5;
                this.wobble = Math.random() * Math.PI * 2;
                this.rotation = Math.random() * 360;
            }
            update() {
                this.y += this.speedY;
                this.wobble += 0.02;
                this.x += Math.sin(this.wobble) * 1;
                this.rotation += 1;
                if (this.y > canvas.height + 20) { this.y = -20; this.x = Math.random() * canvas.width; }
            }
            draw() {
                ctx.save();
                ctx.translate(this.x, this.y);
                ctx.rotate((this.rotation * Math.PI) / 180);
                ctx.globalAlpha = 0.4;
                ctx.beginPath();
                ctx.moveTo(0, 0);
                ctx.bezierCurveTo(this.size/2, -this.size/2, this.size, 0, 0, this.size);
                ctx.bezierCurveTo(-this.size, 0, -this.size/2, -this.size/2, 0, 0);
                ctx.fillStyle = '#FFB7D5';
                ctx.fill();
                ctx.restore();
            }
        }
        for(let i=0; i<30; i++) particles.push(new Particle());
        function animate() { ctx.clearRect(0, 0, canvas.width, canvas.height); particles.forEach(p => { p.update(); p.draw(); }); requestAnimationFrame(animate); }
        animate();
    </script>
</body>
</html>