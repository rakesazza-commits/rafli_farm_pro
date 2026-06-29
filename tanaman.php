<?php
session_start();
include 'koneksi.php';
include 'track_visitor.php';

if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }

// ==========================================
// LOGIKA BACKEND (CRUD TANAMAN)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add' || $action === 'edit') {
        $nama = mysqli_real_escape_string($koneksi, $_POST['nama_tanaman']);
        $varietas = mysqli_real_escape_string($koneksi, $_POST['varietas']);
        $lahan = mysqli_real_escape_string($koneksi, $_POST['nama_lahan']);
        $luas = (float)$_POST['luas_lahan'];
        $tgl_tanam = $_POST['tanggal_tanam'];
        $tgl_panen = $_POST['estimasi_panen'];
        $status = $_POST['status'];
        $catatan = mysqli_real_escape_string($koneksi, $_POST['catatan']);
        $lat = isset($_POST['lat']) && $_POST['lat'] ? (float)$_POST['lat'] : null;
        $lng = isset($_POST['lng']) && $_POST['lng'] ? (float)$_POST['lng'] : null;
        $polygon = isset($_POST['polygon_coords']) && $_POST['polygon_coords'] ? mysqli_real_escape_string($koneksi, $_POST['polygon_coords']) : null;

        if ($action === 'add') {
            $sql = "INSERT INTO tanaman (nama_tanaman, varietas, nama_lahan, luas_lahan, tanggal_tanam, estimasi_panen, status, catatan, latitude, longitude, polygon_coords) 
                    VALUES ('$nama', '$varietas', '$lahan', '$luas', '$tgl_tanam', '$tgl_panen', '$status', '$catatan', " . ($lat ? "'$lat'" : "NULL") . ", " . ($lng ? "'$lng'" : "NULL") . ", " . ($polygon ? "'$polygon'" : "NULL") . ")";
        } else {
            $id = (int)$_POST['id'];
            $sql = "UPDATE tanaman SET nama_tanaman='$nama', varietas='$varietas', nama_lahan='$lahan', luas_lahan='$luas', 
                    tanggal_tanam='$tgl_tanam', estimasi_panen='$tgl_panen', status='$status', catatan='$catatan',
                    latitude=" . ($lat ? "'$lat'" : "NULL") . ", longitude=" . ($lng ? "'$lng'" : "NULL") . ", polygon_coords=" . ($polygon ? "'$polygon'" : "NULL") . " WHERE id=$id";
        }
        $koneksi->query($sql);
        header("Location: tanaman.php?success=1");
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $koneksi->query("DELETE FROM tanaman WHERE id=$id");
        header("Location: tanaman.php?deleted=1");
        exit;
    }
}

// ==========================================
// AMBIL DATA & FILTER
// ==========================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$sql = "SELECT * FROM tanaman WHERE (nama_tanaman LIKE '%$search%' OR nama_lahan LIKE '%$search%')";
if ($filter !== 'all') $sql .= " AND status = '$filter'";
$sql .= " ORDER BY id DESC";
$result = $koneksi->query($sql);

// ==========================================
// STATISTIK
// ==========================================
$total_tanaman = $koneksi->query("SELECT COUNT(*) as c FROM tanaman")->fetch_assoc()['c'];
$total_luas = $koneksi->query("SELECT SUM(luas_lahan) as t FROM tanaman")->fetch_assoc()['t'] ?? 0;
$siap_panen = $koneksi->query("SELECT COUNT(*) as c FROM tanaman WHERE status='siap_panen'")->fetch_assoc()['c'];
$pertumbuhan = $koneksi->query("SELECT COUNT(*) as c FROM tanaman WHERE status='pertumbuhan'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Tanaman - RAFLI_FARM_PRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
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
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .glass-strong { background: rgba(10,10,10,0.98); backdrop-filter: blur(20px); border: 1px solid rgba(0,255,136,0.3); }
        .gradient-text { background: linear-gradient(135deg, #00FF88, #00E5FF); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        
        /* Modal - FIXED Z-INDEX */
        .modal-overlay { transition: opacity 0.3s ease; z-index: 9999 !important; }
        .modal-content { transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); z-index: 10000 !important; position: relative; }
        .modal-hidden { opacity: 0; pointer-events: none; visibility: hidden; }
        .modal-hidden .modal-content { transform: scale(0.9) translateY(20px); }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #00FF88; border-radius: 10px; }
        
        /* Map Container - ULTIMATE */
        #embedMap { height: 500px; border-radius: 12px; border: 2px solid rgba(0,255,136,0.3); z-index: 1; }
        .leaflet-container { background: #1a1a1a; font-family: 'Inter', sans-serif; }
        
        /* Custom Leaflet Controls */
        .leaflet-control-zoom a { background: rgba(10,10,10,0.9) !important; color: #00FF88 !important; border: 2px solid rgba(0,255,136,0.3) !important; width: 36px !important; height: 36px !important; line-height: 32px !important; font-size: 18px !important; border-radius: 8px !important; margin-bottom: 8px !important; }
        .leaflet-control-zoom a:hover { background: #00FF88 !important; color: #000 !important; }
        .leaflet-control-attribution { background: rgba(0,0,0,0.7) !important; color: #888 !important; font-size: 10px !important; border-radius: 8px !important; padding: 4px 8px !important; }
        .leaflet-control-attribution a { color: #00FF88 !important; }
        
        .leaflet-draw-toolbar a { background-color: rgba(10,10,10,0.9) !important; border: 2px solid rgba(0,255,136,0.3) !important; border-radius: 8px !important; width: 36px !important; height: 36px !important; line-height: 32px !important; margin-bottom: 8px !important; }
        .leaflet-draw-toolbar a:hover { background-color: #00FF88 !important; }
        .leaflet-draw-actions a { background-color: rgba(10,10,10,0.95) !important; color: #fff !important; border: 1px solid rgba(0,255,136,0.3) !important; border-radius: 6px !important; margin-right: 4px !important; }
        
        /* Layer Control Custom */
        .leaflet-control-layers { background: rgba(10,10,10,0.95) !important; border: 2px solid rgba(0,255,136,0.3) !important; border-radius: 12px !important; color: #fff !important; padding: 8px !important; backdrop-filter: blur(10px); }
        .leaflet-control-layers label { display: flex; align-items: center; gap: 8px; padding: 4px 8px; border-radius: 6px; cursor: pointer; transition: all 0.2s; font-size: 12px; }
        .leaflet-control-layers label:hover { background: rgba(0,255,136,0.1); }
        .leaflet-control-layers input[type="radio"] { accent-color: #00FF88; }

        /* Search Box inside Map */
        .map-search-container {
            position: absolute;
            top: 10px;
            left: 60px; /* Avoid overlap with draw toolbar */
            z-index: 1000;
            width: 300px;
        }
        .map-search-input {
            width: 100%;
            padding: 12px 16px;
            background: rgba(10,10,10,0.95);
            border: 2px solid rgba(0,255,136,0.5);
            border-radius: 12px;
            color: #fff;
            font-size: 14px;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .map-search-input:focus { outline: none; border-color: #00FF88; box-shadow: 0 0 20px rgba(0,255,136,0.3); }
        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 5px;
            background: rgba(10,10,10,0.98);
            border: 1px solid rgba(0,255,136,0.3);
            border-radius: 12px;
            max-height: 250px;
            overflow-y: auto;
            backdrop-filter: blur(20px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .search-item { padding: 10px 16px; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.05); transition: all 0.2s; }
        .search-item:hover { background: rgba(0,255,136,0.1); color: #00FF88; }
        .search-item:last-child { border-bottom: none; }
        
        /* Quick Location Buttons */
        .quick-locations {
            position: absolute;
            bottom: 20px;
            left: 60px;
            z-index: 1000;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .quick-loc-btn {
            padding: 6px 12px;
            background: rgba(10,10,10,0.9);
            border: 1px solid rgba(0,255,136,0.3);
            border-radius: 20px;
            color: #fff;
            font-size: 11px;
            cursor: pointer;
            backdrop-filter: blur(10px);
            transition: all 0.2s;
        }
        .quick-loc-btn:hover { background: #00FF88; color: #000; border-color: #00FF88; }

        @keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .slide-in { animation: slideIn 0.4s ease-out forwards; }
        @keyframes glowPulse { 0%, 100% { box-shadow: 0 0 20px rgba(0,255,136,0.3); } 50% { box-shadow: 0 0 40px rgba(0,255,136,0.6); } }
        .glow-pulse { animation: glowPulse 2s infinite; }
        .btn-add { position: relative; z-index: 100; }
    </style>
</head>
<body class="min-h-screen flex relative">
    <canvas id="weatherCanvas" class="fixed inset-0 z-0 pointer-events-none"></canvas>

    <!-- Sidebar -->
    <aside class="w-64 bg-dark-800/90 backdrop-blur-xl border-r border-white/5 flex flex-col h-screen fixed z-40 hidden lg:flex">
        <div class="p-6 border-b border-white/5"><h1 class="text-xl font-bold gradient-text">RAFLI_FARM</h1><p class="text-[10px] text-gray-500 tracking-widest">PROFESSIONAL</p></div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">Dashboard</a>
            <a href="tanaman.php" class="flex items-center gap-3 px-4 py-3 bg-neon/10 text-neon border-l-4 border-neon rounded-r-xl font-bold"> Tanaman</a>
            <a href="gudang.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">📦 Gudang</a>
            <a href="penjualan.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">💰 Penjualan</a>
            <a href="kalkulator.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">🧮 Kalkulator</a>
            <a href="ai-voice.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">🎤 AI Voice</a>
            <a href="keuangan.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">💵 Keuangan</a>
            <a href="jadwal.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">📅 Jadwal</a>
        </nav>
        <div class="p-4 border-t border-white/5"><a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-danger hover:bg-danger/10 rounded-xl">Keluar</a></div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 lg:ml-64 relative z-10 p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div><h1 class="text-3xl font-bold gradient-text">🌱 Manajemen Tanaman</h1><p class="text-gray-500 text-sm mt-1">Pantau pertumbuhan, jadwal panen, dan lokasi lahan Anda</p></div>
            <button type="button" onclick="handleAddPlant(event)" class="btn-add px-6 py-3 bg-gradient-to-r from-neon to-cyan text-dark-900 font-bold rounded-xl hover:shadow-lg hover:shadow-neon/30 transition-all flex items-center gap-2 glow-pulse">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                + Tambah Tanaman
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="glass rounded-2xl p-5 flex items-center gap-4"><div class="w-12 h-12 rounded-xl bg-neon/10 flex items-center justify-center text-2xl">🌱</div><div><p class="text-xs text-gray-500">Total Tanaman</p><p class="text-2xl font-bold text-white"><?php echo $total_tanaman; ?></p></div></div>
            <div class="glass rounded-2xl p-5 flex items-center gap-4"><div class="w-12 h-12 rounded-xl bg-cyan/10 flex items-center justify-center text-2xl">📏</div><div><p class="text-xs text-gray-500">Total Luas</p><p class="text-2xl font-bold text-white"><?php echo number_format($total_luas, 2); ?> <span class="text-sm text-gray-400">Ha</span></p></div></div>
            <div class="glass rounded-2xl p-5 flex items-center gap-4"><div class="w-12 h-12 rounded-xl bg-warning/10 flex items-center justify-center text-2xl">🌾</div><div><p class="text-xs text-gray-500">Siap Panen</p><p class="text-2xl font-bold text-white"><?php echo $siap_panen; ?></p></div></div>
            <div class="glass rounded-2xl p-5 flex items-center gap-4"><div class="w-12 h-12 rounded-xl bg-purple-500/10 flex items-center justify-center text-2xl">🌿</div><div><p class="text-xs text-gray-500">Pertumbuhan</p><p class="text-2xl font-bold text-white"><?php echo $pertumbuhan; ?></p></div></div>
        </div>

        <form method="GET" class="glass rounded-2xl p-4 mb-6 flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[200px] relative">
                <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                <input type="text" name="search" value="<?php echo $search; ?>" placeholder="Cari nama tanaman atau lahan..." class="w-full pl-10 pr-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon">
            </div>
            <select name="filter" class="bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-neon">
                <option value="all" <?php if($filter=='all') echo 'selected'; ?>>Semua Status</option>
                <option value="semai" <?php if($filter=='semai') echo 'selected'; ?>>🌱 Semai</option>
                <option value="pertumbuhan" <?php if($filter=='pertumbuhan') echo 'selected'; ?>>🌿 Pertumbuhan</option>
                <option value="siap_panen" <?php if($filter=='siap_panen') echo 'selected'; ?>>🌾 Siap Panen</option>
                <option value="panen" <?php if($filter=='panen') echo 'selected'; ?>>🚜 Panen</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-neon text-dark-900 font-bold rounded-lg hover:shadow-lg transition-all text-sm">🔍 Filter</button>
            <a href="tanaman.php" class="px-4 py-2 bg-white/5 border border-white/10 text-gray-300 rounded-lg hover:bg-white/10 transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                Reset
            </a>
        </form>

        <?php if(isset($_GET['success'])): ?><div class="mb-4 p-4 bg-neon/10 border border-neon/30 rounded-xl text-neon text-sm">✅ Data tanaman berhasil disimpan!</div><?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php if ($result->num_rows > 0): while($row = $result->fetch_assoc()):
                $hari = max(0, floor((strtotime(date('Y-m-d')) - strtotime($row['tanggal_tanam'])) / 86400));
                $s = ['semai'=>['bg-warning/10 text-warning','Semai','🌱'],'pertumbuhan'=>['bg-neon/10 text-neon','Pertumbuhan','🌿'],'siap_panen'=>['bg-cyan/10 text-cyan','Siap Panen','🌾'],'panen'=>['bg-purple/10 text-purple','Panen','🚜'],'gagal'=>['bg-danger/10 text-danger','Gagal','💀']][$row['status']] ?? ['bg-neon/10 text-neon','Pertumbuhan','🌿'];
                $last_act = $koneksi->query("SELECT description FROM plant_activities WHERE tanaman_id={$row['id']} ORDER BY activity_date DESC LIMIT 1")->fetch_assoc();
            ?>
            <div class="glass rounded-2xl p-6 hover:border-neon/30 transition-all group relative overflow-hidden slide-in">
                <div class="absolute -right-10 -top-10 w-32 h-32 bg-neon/5 rounded-full blur-3xl group-hover:bg-neon/10 transition-all"></div>
                <div class="relative z-10">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-neon/20 to-cyan/20 flex items-center justify-center text-3xl border border-neon/30"><?php echo $s[2]; ?></div>
                            <div><h3 class="text-lg font-bold text-white"><?php echo $row['nama_tanaman']; ?></h3><p class="text-xs text-gray-500"><?php echo $row['varietas']; ?></p></div>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $s[0]; ?>"><?php echo $s[1]; ?></span>
                    </div>
                    <div class="space-y-2 mb-4">
                        <div class="flex items-center gap-2 text-sm text-gray-400"><span class="text-neon">📍</span><span><?php echo $row['nama_lahan']; ?></span><span class="text-gray-600">•</span><span class="text-cyan font-semibold"><?php echo $row['luas_lahan']; ?> Ha</span></div>
                        <div class="flex items-center gap-2 text-sm text-gray-400"><span>📅</span><span>Tanam: <?php echo date('d M Y', strtotime($row['tanggal_tanam'])); ?></span></div>
                        <div class="flex items-center gap-2 text-sm"><span class="text-white font-semibold">⏳ <?php echo $hari; ?> Hari</span><span class="text-gray-600">|</span><span class="text-gray-400 text-xs">Panen: <?php echo date('d M Y', strtotime($row['estimasi_panen'])); ?></span></div>
                        <?php if($last_act): ?><div class="text-xs text-purple-400 bg-purple-500/10 border border-purple-500/20 p-2 rounded-lg truncate">📜 <?php echo $last_act['description']; ?></div><?php endif; ?>
                    </div>
                    <div class="flex gap-2 pt-4 border-t border-white/5">
                        <a href="kalkulator.php?tanaman_id=<?php echo $row['id']; ?>" class="flex-1 py-2 bg-purple-500/10 text-purple-400 rounded-lg hover:bg-purple-500/20 text-xs font-medium text-center border border-purple-500/20">🧮 Pupuk</a>
                        <button type="button" onclick="handleEditPlant(event, <?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)" class="flex-1 py-2 bg-blue-500/10 text-blue-400 rounded-lg hover:bg-blue-500/20 text-xs font-medium border border-blue-500/20">✏️ Edit</button>
                        <form method="POST" onsubmit="return confirm('Yakin hapus?')" class="flex-1">
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <button type="submit" class="w-full py-2 bg-danger/10 text-danger rounded-lg hover:bg-danger/20 text-xs font-medium border border-danger/20">🗑️</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endwhile; else: ?>
                <div class="col-span-full text-center py-20 text-gray-500 glass rounded-2xl"><p class="text-6xl mb-4">🌾</p><p class="text-lg">Belum ada data tanaman.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL: TAMBAH / EDIT TANAMAN (ULTIMATE MAP) -->
    <!-- ========================================== -->
    <div id="modalForm" class="modal-overlay modal-hidden fixed inset-0 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div class="modal-content glass-strong rounded-2xl w-full max-w-5xl p-6 border border-neon/30 max-h-[95vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold gradient-text" id="modalTitle">🌱 Tambah Tanaman Baru</h3>
                <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-white p-2 rounded-lg hover:bg-white/10">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <!-- ULTIMATE MAP SECTION -->
            <div class="mb-6 relative">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-bold text-neon flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /></svg>
                        🛰️ Peta Satelit Canggih - Plot Area Lahan
                    </p>
                    <span id="mapStatus" class="text-xs text-gray-500 bg-white/5 px-3 py-1 rounded-full">Belum ada area</span>
                </div>
                
                <!-- Map Container -->
                <div class="relative rounded-xl overflow-hidden border-2 border-neon/30">
                    <div id="embedMap" class="w-full" style="height: 500px;"></div>
                    
                    <!-- Search Box Overlay -->
                    <div class="map-search-container">
                        <input type="text" id="locationSearch" placeholder="🔍 Cari lokasi (Dompu, Sawe, Hu'u, atau alamat lain)..." 
                               class="map-search-input" oninput="searchLocations(this.value)" onkeydown="if(event.key==='Enter') selectFirstLocation()">
                        <div id="locationSuggestions" class="search-suggestions" style="display: none;"></div>
                    </div>

                    <!-- Quick Location Buttons Overlay -->
                    <div class="quick-locations">
                        <button type="button" class="quick-loc-btn" onclick="flyToLocation(-8.5278, 118.4686, 'Dompu Kota')">📍 Dompu</button>
                        <button type="button" class="quick-loc-btn" onclick="flyToLocation(-8.5167, 118.4833, 'Sawe')">📍 Sawe</button>
                        <button type="button" class="quick-loc-btn" onclick="flyToLocation(-8.4833, 118.4500, 'Hu\'u')">📍 Hu'u</button>
                        <button type="button" class="quick-loc-btn" onclick="flyToLocation(-8.5000, 118.4667, 'Woja')">📍 Woja</button>
                        <button type="button" class="quick-loc-btn" onclick="flyToLocation(-8.5500, 118.4833, 'Pajo')">📍 Pajo</button>
                        <button type="button" class="quick-loc-btn" onclick="flyToLocation(-8.4667, 118.4333, 'Kilo')">📍 Kilo</button>
                    </div>
                </div>

                <div class="flex items-center gap-3 mt-3 text-xs text-gray-400">
                    <span>💡 <b>Cara Plot:</b> Klik ikon ⬡ Polygon di kiri → gambar batas lahan → klik titik pertama untuk selesai. Luas otomatis terhitung!</span>
                    <button type="button" onclick="clearMapPlot()" class="ml-auto px-4 py-2 bg-danger/10 text-danger rounded-lg hover:bg-danger/20 transition-all font-bold">🗑️ Hapus Plot</button>
                </div>
            </div>

            <!-- Form -->
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="lat" id="f_lat">
                <input type="hidden" name="lng" id="f_lng">
                <input type="hidden" name="polygon_coords" id="f_polygon">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="block text-xs text-gray-400 mb-1">Nama Tanaman *</label><input type="text" name="nama_tanaman" id="f_nama" required class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon"></div>
                    <div><label class="block text-xs text-gray-400 mb-1">Varietas *</label><input type="text" name="varietas" id="f_varietas" required placeholder="Contoh: IR64, Bisi-18" class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon"></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="block text-xs text-gray-400 mb-1">Nama Lahan *</label><input type="text" name="nama_lahan" id="f_lahan" required placeholder="Contoh: Lahan A" class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon"></div>
                    <div><label class="block text-xs text-gray-400 mb-1">Luas (Ha) * <span class="text-neon">(Otomatis dari Peta)</span></label><input type="number" step="0.01" name="luas_lahan" id="f_luas" required class="w-full px-4 py-2 bg-neon/10 border border-neon/30 rounded-lg text-neon font-bold focus:outline-none focus:border-neon"></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="block text-xs text-gray-400 mb-1">Tanggal Tanam *</label><input type="date" name="tanggal_tanam" id="f_tgl_tanam" required value="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon"></div>
                    <div><label class="block text-xs text-gray-400 mb-1">Estimasi Panen *</label><input type="date" name="estimasi_panen" id="f_tgl_panen" required class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon"></div>
                </div>
                <div><label class="block text-xs text-gray-400 mb-1">Status</label>
                    <select name="status" id="f_status" class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon">
                        <option value="semai">🌱 Semai</option><option value="pertumbuhan" selected>🌿 Pertumbuhan</option><option value="siap_panen">🌾 Siap Panen</option><option value="panen">🚜 Panen</option><option value="gagal">💀 Gagal</option>
                    </select>
                </div>
                <div><label class="block text-xs text-gray-400 mb-1">Catatan</label><textarea name="catatan" id="f_catatan" rows="2" class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon"></textarea></div>

                <div class="flex gap-3 pt-4">
                    <button type="submit" class="flex-1 py-3 bg-gradient-to-r from-neon to-cyan text-dark-900 font-bold rounded-xl hover:shadow-lg transition-all flex items-center justify-center gap-2">💾 Simpan Tanaman</button>
                    <button type="button" onclick="closeModal()" class="flex-1 py-3 bg-white/5 border border-white/10 text-gray-300 rounded-xl hover:bg-white/10 transition-all">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
    <script>
        // ===== ULTIMATE MAP VARIABLES =====
        var embedMap = null, drawnItems = null, areaLabel = null, searchMarker = null;
        var searchTimeout = null;

        // Predefined locations for quick access & search fallback
        const quickLocations = [
            { name: 'Dompu Kota', lat: -8.5278, lng: 118.4686 },
            { name: 'Sawe, Dompu', lat: -8.5167, lng: 118.4833 },
            { name: "Hu'u, Dompu", lat: -8.4833, lng: 118.4500 },
            { name: 'Woja, Dompu', lat: -8.5000, lng: 118.4667 },
            { name: 'Pajo, Dompu', lat: -8.5500, lng: 118.4833 },
            { name: 'Kilo, Dompu', lat: -8.4667, lng: 118.4333 },
            { name: 'Manggelewa', lat: -8.5333, lng: 118.5000 },
            { name: 'Pekat', lat: -8.5167, lng: 118.4500 }
        ];

        function initEmbedMap() {
            if (embedMap) { setTimeout(function() { embedMap.invalidateSize(); }, 100); return; }
            
            embedMap = L.map('embedMap', { center: [-8.5278, 118.4686], zoom: 13, zoomControl: false });
            
            // Add Zoom Control to top-right
            L.control.zoom({ position: 'topright' }).addTo(embedMap);

            // Base Layers (Satellite, Hybrid, Dark)
            var satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution: 'Esri', maxZoom: 19 });
            var hybrid = L.layerGroup([
                L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution: 'Esri', maxZoom: 19 }),
                L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19 })
            ]);
            var dark = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { attribution: 'CARTO', maxZoom: 19 });

            satellite.addTo(embedMap);

            // Layer Control
            L.control.layers({
                "🛰️ Satelit": satellite,
                "️ Hybrid (Satelit + Label)": hybrid,
                "️ Dark Mode": dark
            }, null, { position: 'topright' }).addTo(embedMap);

            // Draw Controls
            drawnItems = new L.FeatureGroup();
            embedMap.addLayer(drawnItems);
            
            embedMap.addControl(new L.Control.Draw({
                position: 'topleft',
                draw: { 
                    polygon: { shapeOptions: { color: '#00FF88', weight: 4, fillColor: '#00FF88', fillOpacity: 0.4 }, allowIntersection: false, showArea: true }, 
                    polyline: false, 
                    rectangle: { shapeOptions: { color: '#00FF88', weight: 4, fillColor: '#00FF88', fillOpacity: 0.4 } }, 
                    circle: false, marker: false, circlemarker: false 
                },
                edit: { featureGroup: drawnItems, remove: true }
            }));

            // Draw Events
            embedMap.on(L.Draw.Event.CREATED, function(e) {
                drawnItems.clearLayers(); drawnItems.addLayer(e.layer);
                if (e.layerType === 'polygon' || e.layerType === 'rectangle') {
                    var ll = e.layer.getLatLngs()[0], c = e.layer.getBounds().getCenter();
                    var area = calcArea(ll), ha = (area / 10000).toFixed(2);
                    document.getElementById('f_lat').value = c.lat.toFixed(6);
                    document.getElementById('f_lng').value = c.lng.toFixed(6);
                    document.getElementById('f_luas').value = ha;
                    document.getElementById('f_polygon').value = JSON.stringify(ll.map(function(p){return [p.lat,p.lng];}));
                    document.getElementById('mapStatus').innerHTML = '<span class="text-neon font-bold">✅ ' + ha + ' Ha terdeteksi</span>';
                    if (areaLabel) embedMap.removeLayer(areaLabel);
                    areaLabel = L.marker(c, { icon: L.divIcon({ html: '<div style="background:rgba(0,255,136,0.95);color:#000;padding:8px 16px;border-radius:12px;font-weight:bold;font-size:14px;border:2px solid #fff;box-shadow:0 10px 30px rgba(0,255,136,0.5);"> '+ha+' Ha</div>', iconSize: [120,40], iconAnchor: [60,20] }), interactive: false }).addTo(embedMap);
                }
            });

            embedMap.on(L.Draw.Event.EDITED, function(e) {
                e.layers.eachLayer(function(layer) {
                    if (layer instanceof L.Polygon || layer instanceof L.Rectangle) {
                        var ll = layer.getLatLngs()[0], c = layer.getBounds().getCenter();
                        var area = calcArea(ll), ha = (area / 10000).toFixed(2);
                        document.getElementById('f_lat').value = c.lat.toFixed(6);
                        document.getElementById('f_lng').value = c.lng.toFixed(6);
                        document.getElementById('f_luas').value = ha;
                        document.getElementById('f_polygon').value = JSON.stringify(ll.map(function(p){return [p.lat,p.lng];}));
                        document.getElementById('mapStatus').innerHTML = '<span class="text-neon font-bold">✅ ' + ha + ' Ha terdeteksi</span>';
                        if (areaLabel) embedMap.removeLayer(areaLabel);
                        areaLabel = L.marker(c, { icon: L.divIcon({ html: '<div style="background:rgba(0,255,136,0.95);color:#000;padding:8px 16px;border-radius:12px;font-weight:bold;font-size:14px;border:2px solid #fff;box-shadow:0 10px 30px rgba(0,255,136,0.5);">📏 '+ha+' Ha</div>', iconSize: [120,40], iconAnchor: [60,20] }), interactive: false }).addTo(embedMap);
                    }
                });
            });

            embedMap.on(L.Draw.Event.DELETED, function() { clearMapPlot(); });
        }

        function calcArea(ll) {
            if (ll.length < 3) return 0;
            var a = 0, j = ll.length - 1;
            for (var i = 0; i < ll.length; i++) { a += (ll[j].lng - ll[i].lng) * (ll[i].lat + ll[j].lat); j = i; }
            return Math.abs(a / 2) * 111320 * 111320 * Math.cos(ll[0].lat * Math.PI / 180);
        }

        // ===== ADVANCED SEARCH (Nominatim API) =====
        function searchLocations(query) {
            clearTimeout(searchTimeout);
            var suggestionsDiv = document.getElementById('locationSuggestions');
            
            if (!query || query.length < 2) {
                suggestionsDiv.style.display = 'none';
                return;
            }

            // Check local quick locations first
            var localMatches = quickLocations.filter(function(loc) {
                return loc.name.toLowerCase().includes(query.toLowerCase());
            });

            if (localMatches.length > 0) {
                var html = localMatches.map(function(loc) {
                    return '<div class="search-item" onclick="flyToLocation(' + loc.lat + ', ' + loc.lng + ', \'' + loc.name + '\')"><div class="font-bold text-sm">📍 ' + loc.name + '</div><div class="text-xs text-gray-500">Lokasi Cepat</div></div>';
                }).join('');
                suggestionsDiv.innerHTML = html;
                suggestionsDiv.style.display = 'block';
                return;
            }

            // Fetch from Nominatim API (OpenStreetMap)
            searchTimeout = setTimeout(function() {
                fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query) + '&countrycodes=id&limit=5')
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.length === 0) {
                            suggestionsDiv.innerHTML = '<div class="search-item text-gray-500">Lokasi tidak ditemukan</div>';
                        } else {
                            var html = data.map(function(item) {
                                return '<div class="search-item" onclick="flyToLocation(' + item.lat + ', ' + item.lon + ', \'' + item.display_name.replace(/'/g, "\\'") + '\')"><div class="font-bold text-sm truncate"> ' + item.display_name.split(',')[0] + '</div><div class="text-xs text-gray-500 truncate">' + item.display_name + '</div></div>';
                            }).join('');
                            suggestionsDiv.innerHTML = html;
                        }
                        suggestionsDiv.style.display = 'block';
                    })
                    .catch(function(err) {
                        console.error('Search error:', err);
                    });
            }, 500); // Debounce 500ms
        }

        function selectFirstLocation() {
            var firstItem = document.querySelector('.search-item');
            if (firstItem) firstItem.click();
        }

        function flyToLocation(lat, lng, name) {
            if (!embedMap) initEmbedMap();
            embedMap.flyTo([lat, lng], 16, { duration: 2 });
            
            if (searchMarker) embedMap.removeLayer(searchMarker);
            searchMarker = L.marker([lat, lng]).addTo(embedMap)
                .bindPopup('<b>📍 ' + name + '</b>').openPopup();
            
            document.getElementById('locationSuggestions').style.display = 'none';
            document.getElementById('locationSearch').value = '';
            
            // Auto-fill center coordinates
            document.getElementById('f_lat').value = parseFloat(lat).toFixed(6);
            document.getElementById('f_lng').value = parseFloat(lng).toFixed(6);
            document.getElementById('mapStatus').innerHTML = '<span class="text-cyan">📍 ' + name.split(',')[0] + ' dipilih</span>';
        }

        function clearMapPlot() {
            if (drawnItems) drawnItems.clearLayers();
            if (areaLabel && embedMap) embedMap.removeLayer(areaLabel);
            areaLabel = null;
            document.getElementById('f_lat').value = '';
            document.getElementById('f_lng').value = '';
            document.getElementById('f_luas').value = '';
            document.getElementById('f_polygon').value = '';
            document.getElementById('mapStatus').innerHTML = 'Belum ada area';
        }

        // ===== MODAL FUNCTIONS =====
        function handleAddPlant(e) { if (e) { e.preventDefault(); e.stopPropagation(); } openModal('add', null); }
        function handleEditPlant(e, data) { if (e) { e.preventDefault(); e.stopPropagation(); } openModal('edit', data); }

        function openModal(mode, data) {
            var modal = document.getElementById('modalForm');
            modal.classList.remove('modal-hidden');
            setTimeout(function() { initEmbedMap(); }, 400);

            if (mode === 'add') {
                document.getElementById('modalTitle').innerText = '🌱 Tambah Tanaman Baru';
                document.getElementById('formAction').value = 'add';
                document.getElementById('editId').value = '';
                document.getElementById('f_nama').value = '';
                document.getElementById('f_varietas').value = '';
                document.getElementById('f_lahan').value = '';
                document.getElementById('f_luas').value = '';
                document.getElementById('f_tgl_tanam').value = new Date().toISOString().split('T')[0];
                document.getElementById('f_tgl_panen').value = '';
                document.getElementById('f_status').value = 'pertumbuhan';
                document.getElementById('f_catatan').value = '';
                clearMapPlot();
            } else if (mode === 'edit' && data) {
                document.getElementById('modalTitle').innerText = '️ Edit: ' + data.nama_tanaman;
                document.getElementById('formAction').value = 'edit';
                document.getElementById('editId').value = data.id;
                document.getElementById('f_nama').value = data.nama_tanaman;
                document.getElementById('f_varietas').value = data.varietas;
                document.getElementById('f_lahan').value = data.nama_lahan;
                document.getElementById('f_luas').value = data.luas_lahan;
                document.getElementById('f_tgl_tanam').value = data.tanggal_tanam;
                document.getElementById('f_tgl_panen').value = data.estimasi_panen;
                document.getElementById('f_status').value = data.status;
                document.getElementById('f_catatan').value = data.catatan || '';
                document.getElementById('f_lat').value = data.latitude || '';
                document.getElementById('f_lng').value = data.longitude || '';
                document.getElementById('f_polygon').value = data.polygon_coords || '';
                if (data.latitude && data.longitude) {
                    document.getElementById('mapStatus').innerHTML = '<span class="text-cyan">📍 Lokasi tersimpan</span>';
                    setTimeout(function() { if(embedMap) embedMap.flyTo([parseFloat(data.latitude), parseFloat(data.longitude)], 16); }, 600);
                }
            }
        }

        function closeModal() { document.getElementById('modalForm').classList.add('modal-hidden'); }
        document.getElementById('modalForm').addEventListener('click', function(e) { if (e.target === this) closeModal(); });

        // Close suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.map-search-container')) {
                document.getElementById('locationSuggestions').style.display = 'none';
            }
        });

        // ===== WEATHER CANVAS =====
        var canvas = document.getElementById('weatherCanvas'), ctx = canvas.getContext('2d'), particles = [];
        function resizeCanvas() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
        resizeCanvas(); window.addEventListener('resize', resizeCanvas);
        class Particle { constructor() { this.x = Math.random()*canvas.width; this.y = Math.random()*canvas.height; this.size = 8+Math.random()*6; this.speedY = 1+Math.random()*1.5; this.wobble = Math.random()*Math.PI*2; this.rotation = Math.random()*360; } update() { this.y += this.speedY; this.wobble += 0.02; this.x += Math.sin(this.wobble); this.rotation += 1; if(this.y > canvas.height+20){this.y=-20;this.x=Math.random()*canvas.width;} } draw() { ctx.save(); ctx.translate(this.x,this.y); ctx.rotate(this.rotation*Math.PI/180); ctx.globalAlpha=0.4; ctx.beginPath(); ctx.moveTo(0,0); ctx.bezierCurveTo(this.size/2,-this.size/2,this.size,0,0,this.size); ctx.bezierCurveTo(-this.size,0,-this.size/2,-this.size/2,0,0); ctx.fillStyle='#FFB7D5'; ctx.fill(); ctx.restore(); } }
        for(var i=0;i<30;i++) particles.push(new Particle());
        function animate() { ctx.clearRect(0,0,canvas.width,canvas.height); particles.forEach(function(p){p.update();p.draw();}); requestAnimationFrame(animate); }
        animate();
    </script>
</body>
</html>