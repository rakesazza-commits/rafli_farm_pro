<?php
session_start();
include 'koneksi.php';
include 'track_visitor.php';
if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }

// ==========================================
// SIMPAN KE JURNAL TANAMAN + KURANGI STOK
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_journal') {
    $tanaman_id = (int)$_POST['tanaman_id'];
    $pupuk = mysqli_real_escape_string($koneksi, $_POST['pupuk']);
    $dosis = (float)$_POST['dosis'];
    $satuan = mysqli_real_escape_string($koneksi, $_POST['satuan']);
    $waktu = mysqli_real_escape_string($koneksi, $_POST['waktu']);
    
    // Cek stok gudang
    $gudang = $koneksi->query("SELECT id, stok FROM inventory WHERE LOWER(nama_barang) = LOWER('$pupuk') LIMIT 1");
    if ($gudang->num_rows > 0) {
        $g = $gudang->fetch_assoc();
        if ($g['stok'] < $dosis) {
            header("Location: kalkulator.php?error=stok_habis&tid=$tanaman_id");
            exit;
        }
        // Kurangi stok
        $koneksi->query("UPDATE inventory SET stok = stok - $dosis WHERE id = {$g['id']}");
    } else {
        header("Location: kalkulator.php?error=pupuk_tidak_ada&tid=$tanaman_id");
        exit;
    }

    // Simpan ke jurnal tanaman
    $desc = "Aplikasi pupuk {$pupuk} - Dosis: {$dosis} {$satuan}. Waktu: {$waktu}";
    $sql = "INSERT INTO plant_activities (tanaman_id, activity_type, description, quantity, unit, activity_date) 
            VALUES ($tanaman_id, 'fertilizer', '$desc', $dosis, '$satuan', CURDATE())";
    $koneksi->query($sql);
    
    header("Location: kalkulator.php?saved=1&tid=$tanaman_id");
    exit;
}

// ==========================================
// AMBIL DATA TANAMAN
// ==========================================
$tanaman_list = $koneksi->query("SELECT * FROM tanaman WHERE status != 'panen' ORDER BY nama_lahan");

// Cek apakah ada tanaman_id dari URL (dari halaman tanaman)
$selected_tanaman_id = isset($_GET['tanaman_id']) ? (int)$_GET['tanaman_id'] : null;

// Jika POST, ambil dari POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tanaman_id_post'])) {
    $selected_tanaman_id = (int)$_POST['tanaman_id_post'];
}

$hasil = null;
$tanaman_terpilih = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'hitung') {
    $selected_tanaman_id = (int)$_POST['tanaman_id'];
}

if ($selected_tanaman_id) {
    $tanaman = $koneksi->query("SELECT * FROM tanaman WHERE id=$selected_tanaman_id")->fetch_assoc();
    
    if ($tanaman) {
        $tanaman_terpilih = $tanaman;
        $nama_tanaman = $tanaman['nama_tanaman'];
        $luas = (float)$tanaman['luas_lahan'];
        
        // Ambil rekomendasi pupuk
        $sql = "SELECT * FROM fertilizer_recommendations WHERE plant_type='$nama_tanaman'";
        $result = $koneksi->query($sql);
        $hasil = [];
        
        while($row = $result->fetch_assoc()) {
            $total = $row['dosage_per_hectare'] * $luas;
            
            // Cek stok di gudang
            $stok_gudang = 0;
            $gudang = $koneksi->query("SELECT stok, satuan FROM gudang_inventaris WHERE LOWER(nama_barang) = LOWER('{$row['fertilizer_name']}') LIMIT 1");
            if ($gudang->num_rows > 0) {
                $stok_gudang = $gudang->fetch_assoc()['stok'];
            }
            
            $hasil[] = [
                'nama' => $row['fertilizer_name'],
                'dosis' => $row['dosage_per_hectare'],
                'total' => $total,
                'unit' => $row['unit'],
                'waktu' => $row['application_time'],
                'stok_gudang' => $stok_gudang,
                'status_stok' => $stok_gudang >= $total ? 'cukup' : ($stok_gudang > 0 ? 'kurang' : 'habis')
            ];
        }
    }
}

// Ambil riwayat aktivitas tanaman yang dipilih
$activities = [];
if ($selected_tanaman_id) {
    $act_result = $koneksi->query("SELECT * FROM plant_activities WHERE tanaman_id=$selected_tanaman_id ORDER BY activity_date DESC LIMIT 10");
    while($a = $act_result->fetch_assoc()) {
        $activities[] = $a;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kalkulator Pupuk Smart - RAFLI_FARM_PRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { neon: '#00FF88', cyan: '#00E5FF', danger: '#FF3366', warning: '#FFB300', purple: '#A855F7', dark: { 900: '#0A0A0A', 800: '#111111' } } } } }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0A0A0A; color: #fff; }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .gradient-text { background: linear-gradient(135deg, #00FF88, #00E5FF); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-thumb { background: #00FF88; border-radius: 10px; }
        
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 20px rgba(0,255,136,0.3); }
            50% { box-shadow: 0 0 40px rgba(0,255,136,0.6); }
        }
        .glow-pulse { animation: pulseGlow 2s infinite; }
        
        @keyframes slideIn { from { opacity: 0; transform: translateX(-20px); } to { opacity: 1; transform: translateX(0); } }
        .slide-in { animation: slideIn 0.5s ease-out forwards; }
        
        .tanaman-card { transition: all 0.3s; cursor: pointer; }
        .tanaman-card:hover { transform: translateY(-2px); }
        .tanaman-card.selected { border-color: #00FF88 !important; background: rgba(0,255,136,0.05); }
    </style>
</head>
<body class="min-h-screen flex">
    <canvas id="weatherCanvas" class="fixed inset-0 z-0 pointer-events-none"></canvas>

    <!-- Sidebar -->
    <aside class="w-64 bg-dark-800/90 backdrop-blur-xl border-r border-white/5 flex flex-col h-screen fixed hidden lg:flex z-40">
        <div class="p-6 border-b border-white/5"><h1 class="text-xl font-bold gradient-text">RAFLI_FARM</h1></div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">Dashboard</a>
            <a href="tanaman.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">🌱 Tanaman</a>
            <a href="gudang.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">📦 Gudang</a>
            <a href="penjualan.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">💰 Penjualan</a>
            <a href="kalkulator.php" class="flex items-center gap-3 px-4 py-3 bg-neon/10 text-neon border-l-4 border-neon rounded-r-xl font-bold">🧮 Kalkulator Pupuk</a>
            <a href="ai-voice.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">🎤 AI Voice</a>
            <a href="keuangan.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">💵 Keuangan</a>
            <a href="jadwal.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">📅 Jadwal</a>
        </nav>
        <div class="p-4 border-t border-white/5"><a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-danger hover:bg-danger/10 rounded-xl">Keluar</a></div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 lg:ml-64 p-6 relative z-10">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <a href="tanaman.php" class="text-xs text-gray-500 hover:text-neon transition-colors">← Kembali ke Tanaman</a>
                </div>
                <h1 class="text-3xl font-bold gradient-text">🧮 Kalkulator Pupuk Smart</h1>
                <p class="text-gray-500 text-sm mt-1">Terintegrasi dengan <b class="text-neon">Tanaman</b> • <b class="text-cyan">Gudang</b> • <b class="text-purple">Jurnal Aktivitas</b></p>
            </div>
        </div>

        <!-- Notifikasi Sukses -->
        <?php if(isset($_GET['saved'])): ?>
            <div class="mb-4 p-4 bg-gradient-to-r from-neon/20 to-cyan/20 border border-neon/50 rounded-xl text-neon text-sm flex items-center justify-between shadow-lg shadow-neon/20">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                    ✅ Rencana pemupukan tersimpan di jurnal tanaman & stok gudang terkurangi otomatis!
                </div>
                <a href="tanaman.php" class="px-4 py-1.5 bg-neon text-dark-900 font-bold rounded-lg hover:shadow-lg transition-all text-xs">
                    Lihat Tanaman →
                </a>
            </div>
        <?php endif; ?>

        <!-- Notifikasi Error -->
        <?php if(isset($_GET['error'])): ?>
            <div class="mb-4 p-4 bg-danger/10 border border-danger/30 rounded-xl text-danger text-sm">
                <?php 
                if($_GET['error'] == 'stok_habis') echo '❌ Stok gudang tidak cukup untuk rencana pemupukan.';
                else if($_GET['error'] == 'pupuk_tidak_ada') echo '❌ Pupuk tidak tersedia di gudang.';
                ?>
                <a href="gudang.php" class="underline ml-2">Restock →</a>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- KOLOM 1: Pilih Tanaman -->
            <div class="glass rounded-2xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-neon/10 flex items-center justify-center">
                        <span class="text-2xl">🌱</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white">Pilih Tanaman</h3>
                        <p class="text-xs text-gray-500">Dari database tanaman aktif</p>
                    </div>
                </div>

                <form method="POST" id="hitungForm">
                    <input type="hidden" name="action" value="hitung">
                    
                    <?php if($tanaman_list->num_rows > 0): ?>
                        <div class="space-y-2 max-h-[500px] overflow-y-auto pr-2">
                            <?php while($t = $tanaman_list->fetch_assoc()): 
                                $selected = ($tanaman_terpilih && $tanaman_terpilih['id'] == $t['id']);
                                $border = $selected ? 'border-neon bg-neon/5' : 'border-white/10 hover:border-neon/30';
                            ?>
                            <label class="block cursor-pointer">
                                <input type="radio" name="tanaman_id" value="<?php echo $t['id']; ?>" <?php echo $selected ? 'checked' : ''; ?> class="hidden peer" required onchange="this.form.submit()">
                                <div class="tanaman-card p-4 rounded-xl border-2 <?php echo $border; ?> transition-all peer-checked:border-neon peer-checked:bg-neon/5">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-neon/20 to-cyan/20 flex items-center justify-center">
                                                <span class="text-xl">🌾</span>
                                            </div>
                                            <div>
                                                <p class="font-bold text-white text-sm"><?php echo $t['nama_tanaman']; ?></p>
                                                <p class="text-xs text-gray-400"><?php echo $t['varietas']; ?></p>
                                                <p class="text-[10px] text-gray-500"><?php echo $t['nama_lahan']; ?></p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-lg font-bold text-neon"><?php echo $t['luas_lahan']; ?></p>
                                            <p class="text-[10px] text-gray-500">Hektar</p>
                                        </div>
                                    </div>
                                </div>
                            </label>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-10 text-gray-500">
                            <p class="text-4xl mb-2">🌱</p>
                            <p class="text-sm">Belum ada tanaman aktif.</p>
                            <a href="tanaman.php" class="text-neon underline mt-2 inline-block text-sm">Tambah Tanaman →</a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- KOLOM 2: Hasil Kalkulasi -->
            <div class="lg:col-span-2 glass rounded-2xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-cyan/10 flex items-center justify-center">
                        <span class="text-2xl">📊</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white">Hasil Kalkulasi</h3>
                        <p class="text-xs text-gray-500">Dengan cek stok gudang real-time</p>
                    </div>
                </div>

                <?php if ($hasil && $tanaman_terpilih): ?>
                    <!-- Info Tanaman Terpilih -->
                    <div class="mb-4 p-4 bg-gradient-to-r from-neon/10 to-cyan/10 border border-neon/20 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-400">Tanaman Terpilih:</p>
                                <p class="font-bold text-white text-lg">🌾 <?php echo $tanaman_terpilih['nama_tanaman']; ?> - <?php echo $tanaman_terpilih['varietas']; ?></p>
                                <p class="text-sm text-gray-400">📍 <?php echo $tanaman_terpilih['nama_lahan']; ?> • 📏 <?php echo $tanaman_terpilih['luas_lahan']; ?> Ha</p>
                            </div>
                            <a href="tanaman.php" class="text-xs text-neon hover:underline">Lihat Detail →</a>
                        </div>
                    </div>

                    <!-- List Rekomendasi Pupuk -->
                    <div class="space-y-3">
                        <?php foreach($hasil as $h): 
                            $status_color = $h['status_stok'] == 'cukup' ? 'neon' : ($h['status_stok'] == 'kurang' ? 'warning' : 'danger');
                            $status_text = $h['status_stok'] == 'cukup' ? '✅ Stok Cukup' : ($h['status_stok'] == 'kurang' ? '⚠️ Stok Kurang' : '❌ Stok Habis');
                        ?>
                        <div class="slide-in p-4 bg-white/5 rounded-xl border-l-4 border-<?php echo $status_color; ?>">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h4 class="font-bold text-white text-lg">🧪 <?php echo $h['nama']; ?></h4>
                                    <p class="text-xs text-gray-400">Dosis: <?php echo $h['dosis']; ?> <?php echo $h['unit']; ?>/Ha</p>
                                </div>
                                <span class="text-xs text-<?php echo $status_color; ?> bg-<?php echo $status_color; ?>/10 px-2 py-1 rounded-full"><?php echo $status_text; ?></span>
                            </div>
                            
                            <div class="flex justify-between items-center mt-3 p-3 bg-black/30 rounded-lg">
                                <div>
                                    <p class="text-xs text-gray-400">Total Kebutuhan:</p>
                                    <p class="text-2xl font-bold text-neon"><?php echo number_format($h['total'], 1); ?> <span class="text-sm text-gray-400"><?php echo $h['unit']; ?></span></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-400">Stok Gudang:</p>
                                    <p class="text-lg font-bold text-<?php echo $status_color; ?>"><?php echo number_format($h['stok_gudang'], 1); ?> <span class="text-sm text-gray-400"><?php echo $h['unit']; ?></span></p>
                                </div>
                            </div>

                            <div class="mt-3 p-3 bg-cyan/5 border border-cyan/20 rounded-lg">
                                <p class="text-xs text-cyan mb-1">💡 Waktu Aplikasi:</p>
                                <p class="text-sm text-gray-300"><?php echo $h['waktu']; ?></p>
                            </div>

                            <?php if($h['status_stok'] == 'cukup'): ?>
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="action" value="save_journal">
                                <input type="hidden" name="tanaman_id" value="<?php echo $tanaman_terpilih['id']; ?>">
                                <input type="hidden" name="pupuk" value="<?php echo $h['nama']; ?>">
                                <input type="hidden" name="dosis" value="<?php echo $h['total']; ?>">
                                <input type="hidden" name="satuan" value="<?php echo $h['unit']; ?>">
                                <input type="hidden" name="waktu" value="<?php echo htmlspecialchars($h['waktu']); ?>">
                                <button type="submit" class="w-full py-2 bg-neon/20 hover:bg-neon/30 text-neon border border-neon/30 rounded-lg text-sm font-bold transition-all flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
                                    💾 Simpan ke Jurnal & Kurangi Stok
                                </button>
                            </form>
                            <?php elseif($h['status_stok'] == 'kurang'): ?>
                            <a href="gudang.php" class="block mt-3 py-2 bg-warning/20 hover:bg-warning/30 text-warning border border-warning/30 rounded-lg text-sm font-bold text-center transition-all">
                                📦 Restock di Gudang
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Riwayat Aktivitas Tanaman -->
                    <?php if(!empty($activities)): ?>
                    <div class="mt-6 p-4 bg-purple-500/5 border border-purple-500/20 rounded-xl">
                        <h4 class="text-sm font-bold text-purple-400 mb-3 flex items-center gap-2">
                            📜 Riwayat Aktivitas Tanaman Ini
                        </h4>
                        <div class="space-y-2 max-h-60 overflow-y-auto">
                            <?php foreach($activities as $act): ?>
                            <div class="flex items-start gap-3 p-2 bg-white/5 rounded-lg">
                                <div class="w-8 h-8 rounded-lg bg-purple-500/20 flex items-center justify-center flex-shrink-0">
                                    <span class="text-sm"><?php echo $act['activity_type'] == 'fertilizer' ? '🧪' : '🌱'; ?></span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs text-gray-300"><?php echo $act['description']; ?></p>
                                    <p class="text-[10px] text-gray-500 mt-1"><?php echo date('d M Y', strtotime($act['activity_date'])); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="text-center text-gray-500 py-16">
                        <p class="text-6xl mb-4">🧪</p>
                        <p class="text-lg">Pilih tanaman di sebelah kiri</p>
                        <p class="text-xs mt-2">Sistem akan otomatis menghitung kebutuhan pupuk berdasarkan luas lahan</p>
                        <div class="mt-6 p-4 bg-white/5 rounded-xl text-left max-w-md mx-auto">
                            <p class="text-xs text-gray-400 mb-2">💡 <b>Tips:</b></p>
                            <ul class="text-xs text-gray-500 space-y-1 list-disc list-inside">
                                <li>Atau buka dari halaman <a href="tanaman.php" class="text-neon underline">Tanaman</a> dengan klik tombol "🧮 Hitung Pupuk"</li>
                                <li>Data luas lahan otomatis dari database</li>
                                <li>Stok gudang dicek real-time</li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        const canvas = document.getElementById('weatherCanvas'); const ctx = canvas.getContext('2d'); let particles = [];
        function resizeCanvas() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
        resizeCanvas(); window.addEventListener('resize', resizeCanvas);
        class Particle { constructor() { this.x = Math.random() * canvas.width; this.y = Math.random() * canvas.height; this.size = 8 + Math.random() * 6; this.speedY = 1 + Math.random() * 1.5; this.wobble = Math.random() * Math.PI * 2; this.rotation = Math.random() * 360; } update() { this.y += this.speedY; this.wobble += 0.02; this.x += Math.sin(this.wobble) * 1; this.rotation += 1; if (this.y > canvas.height + 20) { this.y = -20; this.x = Math.random() * canvas.width; } } draw() { ctx.save(); ctx.translate(this.x, this.y); ctx.rotate((this.rotation * Math.PI) / 180); ctx.globalAlpha = 0.4; ctx.beginPath(); ctx.moveTo(0, 0); ctx.bezierCurveTo(this.size/2, -this.size/2, this.size, 0, 0, this.size); ctx.bezierCurveTo(-this.size, 0, -this.size/2, -this.size/2, 0, 0); ctx.fillStyle = '#FFB7D5'; ctx.fill(); ctx.restore(); } }
        for(let i=0; i<30; i++) particles.push(new Particle());
        function animate() { ctx.clearRect(0, 0, canvas.width, canvas.height); particles.forEach(p => { p.update(); p.draw(); }); requestAnimationFrame(animate); }
        animate();
    </script>
</body>
</html>