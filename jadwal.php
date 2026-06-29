<?php
session_start();
include 'koneksi.php';
include 'track_visitor.php';
if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }

// ==========================================
// HANDLE UPDATE STATUS
// ==========================================
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $current = $koneksi->query("SELECT status FROM jadwal_kegiatan WHERE id=$id")->fetch_assoc()['status'];
    $new_status = $current == 'pending' ? 'done' : 'pending';
    $koneksi->query("UPDATE jadwal_kegiatan SET status='$new_status' WHERE id=$id");
    header("Location: jadwal.php");
    exit;
}

// HANDLE HAPUS
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $koneksi->query("DELETE FROM jadwal_kegiatan WHERE id=$id");
    header("Location: jadwal.php");
    exit;
}

// HANDLE TAMBAH JADWAL MANUAL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_manual') {
    $tanaman_id = (int)$_POST['tanaman_id'];
    $judul = mysqli_real_escape_string($koneksi, $_POST['judul']);
    $deskripsi = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
    $tanggal = $_POST['tanggal'];
    
    $sql = "INSERT INTO jadwal_kegiatan (tanaman_id, judul, deskripsi, tanggal_jadwal) VALUES ($tanaman_id, '$judul', '$deskripsi', '$tanggal')";
    $koneksi->query($sql);
    header("Location: jadwal.php?success=1");
    exit;
}

// ==========================================
// AUTO-GENERATE JADWAL CERDAS
// ==========================================
// Aturan jadwal berdasarkan jenis tanaman
$rules_by_crop = [
    'Padi' => [
        7 => ['Pemupukan Tahap 1', 'Berikan pupuk Urea 50kg/ha + SP-36 50kg/ha'],
        14 => ['Penyemprotan Pestisida', 'Semprot untuk cegah wereng dan penggerek batang'],
        21 => ['Pengairan Berselang', 'Mulai sistem pengairan macak-macak'],
        28 => ['Pemupukan Tahap 2', 'Berikan pupuk Urea susulan 100kg/ha'],
        35 => ['Penyiangan Gulma', 'Cabut rumput liar di sekitar tanaman'],
        45 => ['Pengamatan Hama', 'Cek intensitas serangan wereng coklat'],
        60 => ['Pengeringan Lahan', 'Keringkan sawah untuk percepat pematangan'],
        90 => ['Panen', 'Panen saat 90% gabah menguning']
    ],
    'Jagung' => [
        7 => ['Penyulaman', 'Ganti bibit yang mati/tidak tumbuh'],
        14 => ['Pemupukan Tahap 1', 'Berikan Urea 50kg/ha'],
        28 => ['Pemupukan Tahap 2', 'Berikan Urea 100kg/ha + NPK'],
        35 => ['Penyiangan & Pembumbunan', 'Bersihkan gulma dan timbun pangkal batang'],
        50 => ['Pengamatan Hama', 'Cek serangan ulat grayak dan kutu daun'],
        70 => ['Panen', 'Panen saat kelopak kering dan biji keras']
    ],
    'Cabai' => [
        7 => ['Penyulaman', 'Ganti bibit yang mati'],
        14 => ['Pemupukan Dasar', 'Berikan NPK 15-15-15 dosis awal'],
        21 => ['Penyemprotan Fungisida', 'Cegah penyakit layu dan antraknosa'],
        30 => ['Pemupukan Susulan', 'Berikan NPK dosis kedua'],
        45 => ['Pemasangan Ajir', 'Pasang turus bambu untuk penyangga'],
        60 => ['Panen Pertama', 'Mulai panen cabai merah/hijau'],
        75 => ['Panen Rutin', 'Panen setiap 3-5 hari sekali']
    ],
    'Tomat' => [
        7 => ['Penyulaman', 'Ganti bibit yang mati'],
        14 => ['Pemupukan Dasar', 'Berikan NPK dosis awal'],
        21 => ['Penyemprotan Preventif', 'Cegah penyakit layu bakteri dan virus'],
        30 => ['Pemasangan Ajir', 'Pasang turus untuk penyangga tanaman'],
        45 => ['Pemangkasan Tunas', 'Buang tunas ketiak untuk fokus pembuahan'],
        60 => ['Panen Pertama', 'Panen tomat yang mulai matang'],
        75 => ['Panen Rutin', 'Panen setiap 2-3 hari']
    ],
    'default' => [
        7 => ['Pemupukan Tahap 1', 'Berikan pupuk dasar sesuai dosis'],
        14 => ['Penyemprotan Pestisida', 'Semprot preventif untuk cegah hama'],
        28 => ['Pemupukan Tahap 2', 'Berikan pupuk susulan'],
        45 => ['Penyiangan Gulma', 'Bersihkan rumput liar'],
        60 => ['Evaluasi Pertumbuhan', 'Cek kesehatan dan perkembangan tanaman']
    ]
];

// Generate jadwal untuk semua tanaman aktif
$tanamans = $koneksi->query("SELECT * FROM tanaman WHERE status != 'panen' AND status != 'gagal'");
while($t = $tanamans->fetch_assoc()) {
    $umur = floor((strtotime(date('Y-m-d')) - strtotime($t['tanggal_tanam'])) / 86400);
    $crop_name = $t['nama_tanaman'];
    
    // Pilih rules berdasarkan jenis tanaman
    $rules = isset($rules_by_crop[$crop_name]) ? $rules_by_crop[$crop_name] : $rules_by_crop['default'];
    
    foreach($rules as $hari => $info) {
        if ($umur >= $hari) {
            $tgl_jadwal = date('Y-m-d', strtotime($t['tanggal_tanam'] . " + $hari days"));
            // Cek apakah sudah ada
            $cek = $koneksi->query("SELECT id FROM jadwal_kegiatan WHERE tanaman_id={$t['id']} AND judul='{$info[0]}'");
            if ($cek->num_rows == 0) {
                $koneksi->query("INSERT INTO jadwal_kegiatan (tanaman_id, judul, deskripsi, tanggal_jadwal) VALUES ({$t['id']}, '{$info[0]}', '{$info[1]}', '$tgl_jadwal')");
            }
        }
    }
}

// ==========================================
// AMBIL DATA & FILTER
// ==========================================
$filter_tanaman = isset($_GET['tanaman_id']) ? (int)$_GET['tanaman_id'] : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

$sql = "SELECT j.*, t.nama_tanaman, t.nama_lahan, t.varietas, t.tanggal_tanam, t.estimasi_panen 
        FROM jadwal_kegiatan j 
        JOIN tanaman t ON j.tanaman_id = t.id 
        WHERE 1=1";

if ($filter_tanaman > 0) $sql .= " AND j.tanaman_id = $filter_tanaman";
if ($filter_status !== 'all') $sql .= " AND j.status = '$filter_status'";
$sql .= " ORDER BY j.tanggal_jadwal ASC";

$jadwal = $koneksi->query($sql);

// Statistik
$total_tugas = $koneksi->query("SELECT COUNT(*) as c FROM jadwal_kegiatan")->fetch_assoc()['c'];
$tugas_selesai = $koneksi->query("SELECT COUNT(*) as c FROM jadwal_kegiatan WHERE status='done'")->fetch_assoc()['c'];
$tugas_pending = $koneksi->query("SELECT COUNT(*) as c FROM jadwal_kegiatan WHERE status='pending'")->fetch_assoc()['c'];
$tugas_overdue = $koneksi->query("SELECT COUNT(*) as c FROM jadwal_kegiatan WHERE status='pending' AND tanggal_jadwal < CURDATE()")->fetch_assoc()['c'];
$completion_rate = $total_tugas > 0 ? ($tugas_selesai / $total_tugas) * 100 : 0;

// Daftar tanaman untuk filter
$daftar_tanaman = $koneksi->query("SELECT id, nama_tanaman, nama_lahan FROM tanaman ORDER BY nama_lahan");

// Tugas hari ini & besok
$tugas_hari_ini = $koneksi->query("SELECT COUNT(*) as c FROM jadwal_kegiatan WHERE tanggal_jadwal = CURDATE() AND status='pending'")->fetch_assoc()['c'];
$tugas_besok = $koneksi->query("SELECT COUNT(*) as c FROM jadwal_kegiatan WHERE tanggal_jadwal = DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND status='pending'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Kegiatan Cerdas - RAFLI_FARM_PRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { neon: '#00FF88', cyan: '#00E5FF', danger: '#FF3366', warning: '#FFB300', purple: '#A855F7', dark: { 900: '#0A0A0A', 800: '#111111' } } } } }</script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0A0A0A; color: #fff; }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .gradient-text { background: linear-gradient(135deg, #00FF88, #00E5FF); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-thumb { background: #00FF88; border-radius: 10px; }
        
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-up { animation: slideUp 0.5s ease-out forwards; }
        
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 20px rgba(0,255,136,0.3); }
            50% { box-shadow: 0 0 40px rgba(0,255,136,0.6); }
        }
        .glow-pulse { animation: pulseGlow 2s infinite; }
        
        .task-card { transition: all 0.3s ease; }
        .task-card:hover { transform: translateX(5px); }
        
        .checkbox-custom {
            width: 24px;
            height: 24px;
            border: 2px solid #00FF88;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .checkbox-custom:hover { background: rgba(0,255,136,0.2); }
        .checkbox-custom.done { background: #00FF88; border-color: #00FF88; }
        
        .timeline-line {
            position: absolute;
            left: 12px;
            top: 24px;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, #00FF88, transparent);
        }
    </style>
</head>
<body class="min-h-screen flex">
    <!-- Sidebar -->
    <aside class="w-64 bg-dark-800/90 backdrop-blur-xl border-r border-white/5 flex flex-col h-screen fixed hidden lg:flex z-40">
        <div class="p-6 border-b border-white/5"><h1 class="text-xl font-bold gradient-text">RAFLI_FARM</h1></div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">Dashboard</a>
            <a href="tanaman.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">Tanaman</a>
            <a href="gudang.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">Gudang</a>
            <a href="penjualan.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">Penjualan</a>
            <a href="keuangan.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">Keuangan</a>
            <a href="jadwal.php" class="flex items-center gap-3 px-4 py-3 bg-neon/10 text-neon border-l-4 border-neon rounded-r-xl font-bold">📅 Jadwal</a>
            <a href="kalkulator.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">Kalkulator</a>
            <a href="ai-voice.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">AI Voice</a>
        </nav>
        <div class="p-4 border-t border-white/5"><a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-danger hover:bg-danger/10 rounded-xl">Keluar</a></div>
    </aside>

    <div class="flex-1 lg:ml-64 p-6 relative z-10">
        <div class="mb-8 animate-up">
            <h1 class="text-3xl font-bold gradient-text">📅 Jadwal Kegiatan Otomatis</h1>
            <p class="text-gray-500 text-sm mt-1">Sistem cerdas yang otomatis membuat reminder berdasarkan umur dan jenis tanaman</p>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div class="mb-4 p-4 bg-neon/10 border border-neon/30 rounded-xl text-neon text-sm">✅ Jadwal berhasil ditambahkan!</div>
        <?php endif; ?>

        <!-- STATS CARDS -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8 animate-up">
            <div class="glass rounded-2xl p-5 border-l-4 border-neon">
                <p class="text-xs text-gray-400">Total Tugas</p>
                <p class="text-2xl font-bold text-white mt-1"><?php echo $total_tugas; ?></p>
            </div>
            <div class="glass rounded-2xl p-5 border-l-4 border-cyan">
                <p class="text-xs text-gray-400">Selesai</p>
                <p class="text-2xl font-bold text-cyan mt-1"><?php echo $tugas_selesai; ?></p>
            </div>
            <div class="glass rounded-2xl p-5 border-l-4 border-warning">
                <p class="text-xs text-gray-400">Pending</p>
                <p class="text-2xl font-bold text-warning mt-1"><?php echo $tugas_pending; ?></p>
            </div>
            <div class="glass rounded-2xl p-5 border-l-4 border-danger">
                <p class="text-xs text-gray-400">Overdue</p>
                <p class="text-2xl font-bold text-danger mt-1"><?php echo $tugas_overdue; ?></p>
            </div>
            <div class="glass rounded-2xl p-5 border-l-4 border-purple">
                <p class="text-xs text-gray-400">Completion Rate</p>
                <p class="text-2xl font-bold text-purple-400 mt-1"><?php echo number_format($completion_rate, 1); ?>%</p>
            </div>
        </div>

        <!-- NOTIFICATION CARDS -->
        <?php if($tugas_hari_ini > 0 || $tugas_besok > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8 animate-up">
            <?php if($tugas_hari_ini > 0): ?>
            <div class="glass rounded-2xl p-5 bg-gradient-to-r from-neon/10 to-cyan/10 border border-neon/30 glow-pulse">
                <div class="flex items-center gap-3">
                    <div class="text-3xl">🔥</div>
                    <div>
                        <p class="text-sm text-gray-400">Tugas Hari Ini</p>
                        <p class="text-xl font-bold text-neon"><?php echo $tugas_hari_ini; ?> tugas harus diselesaikan!</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php if($tugas_besok > 0): ?>
            <div class="glass rounded-2xl p-5 bg-gradient-to-r from-warning/10 to-orange/10 border border-warning/30">
                <div class="flex items-center gap-3">
                    <div class="text-3xl"></div>
                    <div>
                        <p class="text-sm text-gray-400">Tugas Besok</p>
                        <p class="text-xl font-bold text-warning"><?php echo $tugas_besok; ?> tugas akan datang</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- FILTER & TAMBAH MANUAL -->
            <div class="space-y-6">
                <!-- Filter -->
                <div class="glass rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-white mb-4"> Filter Jadwal</h3>
                    <form method="GET" class="space-y-3">
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Berdasarkan Tanaman</label>
                            <select name="tanaman_id" onchange="this.form.submit()" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                                <option value="0">Semua Tanaman</option>
                                <?php 
                                $daftar_tanaman->data_seek(0);
                                while($t = $daftar_tanaman->fetch_assoc()): ?>
                                    <option value="<?php echo $t['id']; ?>" <?php echo $filter_tanaman == $t['id'] ? 'selected' : ''; ?>>
                                        <?php echo $t['nama_tanaman']; ?> (<?php echo $t['nama_lahan']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Status</label>
                            <select name="status" onchange="this.form.submit()" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                                <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>Semua Status</option>
                                <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="done" <?php echo $filter_status == 'done' ? 'selected' : ''; ?>>Selesai</option>
                            </select>
                        </div>
                        <a href="jadwal.php" class="block text-center px-4 py-2 bg-white/5 border border-white/10 text-gray-300 rounded-lg hover:bg-white/10 transition-all text-sm">Reset Filter</a>
                    </form>
                </div>

                <!-- Tambah Manual -->
                <div class="glass rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-white mb-4">+ Tambah Jadwal Manual</h3>
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="add_manual">
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Untuk Tanaman</label>
                            <select name="tanaman_id" required class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                                <?php 
                                $daftar_tanaman->data_seek(0);
                                while($t = $daftar_tanaman->fetch_assoc()): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo $t['nama_tanaman']; ?> (<?php echo $t['nama_lahan']; ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Judul Kegiatan</label>
                            <input type="text" name="judul" required placeholder="Contoh: Semprot Pestisida" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Deskripsi</label>
                            <textarea name="deskripsi" rows="2" placeholder="Detail kegiatan..." class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Tanggal</label>
                            <input type="date" name="tanggal" required value="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                        </div>
                        <button type="submit" class="w-full py-3 bg-gradient-to-r from-neon to-cyan text-dark-900 font-bold rounded-lg hover:shadow-lg hover:shadow-neon/30 transition-all">Tambah Jadwal</button>
                    </form>
                </div>

                <!-- Chart Completion -->
                <div class="glass rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-white mb-4">📊 Statistik</h3>
                    <div class="h-48">
                        <canvas id="completionChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- TIMELINE JADWAL -->
            <div class="lg:col-span-2 glass rounded-2xl p-6">
                <h3 class="text-lg font-bold text-white mb-6">📋 Timeline Kegiatan</h3>
                <div class="space-y-4 max-h-[700px] overflow-y-auto pr-2">
                    <?php if($jadwal->num_rows > 0): 
                        $last_date = '';
                        while($row = $jadwal->fetch_assoc()): 
                            $is_done = $row['status'] == 'done';
                            $is_overdue = strtotime($row['tanggal_jadwal']) < strtotime(date('Y-m-d')) && !$is_done;
                            $is_today = $row['tanggal_jadwal'] == date('Y-m-d');
                            $is_tomorrow = $row['tanggal_jadwal'] == date('Y-m-d', strtotime('+1 day'));
                            
                            // Hitung umur tanaman saat tugas
                            $umur_tugas = floor((strtotime($row['tanggal_jadwal']) - strtotime($row['tanggal_tanam'])) / 86400);
                    ?>
                    <?php if($last_date !== $row['tanggal_jadwal']): 
                        $last_date = $row['tanggal_jadwal'];
                        $date_label = date('d M Y', strtotime($row['tanggal_jadwal']));
                        $day_name = date('l', strtotime($row['tanggal_jadwal']));
                        $is_weekend = in_array($day_name, ['Saturday', 'Sunday']);
                    ?>
                    <div class="relative pt-4">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-3 h-3 rounded-full bg-neon glow-pulse"></div>
                            <div class="flex-1">
                                <p class="text-sm font-bold text-white"><?php echo $date_label; ?></p>
                                <p class="text-xs text-gray-500"><?php echo $day_name; ?> <?php echo $is_weekend ? '• 🏖️ Weekend' : ''; ?></p>
                            </div>
                            <?php if($is_today): ?>
                            <span class="px-3 py-1 bg-neon/20 text-neon text-xs font-bold rounded-full">HARI INI</span>
                            <?php elseif($is_tomorrow): ?>
                            <span class="px-3 py-1 bg-warning/20 text-warning text-xs font-bold rounded-full">BESOK</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="task-card flex items-start gap-4 p-4 rounded-xl border transition-all <?php 
                        echo $is_done ? 'bg-white/5 border-white/10 opacity-60' : 
                             ($is_overdue ? 'bg-danger/5 border-danger/30' : 
                             ($is_today ? 'bg-neon/5 border-neon/30' : 'bg-white/5 border-white/10')); 
                    ?>">
                        <a href="?toggle=<?php echo $row['id']; ?>" class="checkbox-custom flex-shrink-0 <?php echo $is_done ? 'done' : ''; ?>">
                            <?php if($is_done): ?>
                            <svg class="w-4 h-4 text-dark-900" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            <?php endif; ?>
                        </a>
                        
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-bold text-white <?php echo $is_done ? 'line-through' : ''; ?>"><?php echo $row['judul']; ?></p>
                                    <p class="text-xs text-gray-400 mt-1"><?php echo $row['deskripsi']; ?></p>
                                </div>
                                <a href="?delete=<?php echo $row['id']; ?>" onclick="return confirm('Hapus jadwal ini?')" class="text-gray-500 hover:text-danger transition-colors flex-shrink-0">🗑️</a>
                            </div>
                            
                            <div class="flex items-center gap-3 mt-3 text-xs">
                                <span class="px-2 py-1 bg-cyan/10 text-cyan rounded">🌱 <?php echo $row['nama_tanaman']; ?></span>
                                <span class="text-gray-500">📍 <?php echo $row['nama_lahan']; ?></span>
                                <span class="text-gray-500">⏱️ Hari ke-<?php echo $umur_tugas; ?></span>
                            </div>
                            
                            <?php if($is_overdue): ?>
                            <p class="text-xs text-danger mt-2 font-bold">⚠️ TERLEWAT! Segera selesaikan.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; else: ?>
                        <div class="text-center py-20 text-gray-500">
                            <p class="text-6xl mb-4">📅</p>
                            <p class="text-lg">Belum ada jadwal kegiatan.</p>
                            <p class="text-sm mt-2">Tambahkan tanaman terlebih dahulu untuk auto-generate jadwal.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPT CHART.JS -->
    <script>
        const ctx = document.getElementById('completionChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Selesai', 'Pending', 'Overdue'],
                datasets: [{
                    data: [<?php echo $tugas_selesai; ?>, <?php echo $tugas_pending - $tugas_overdue; ?>, <?php echo $tugas_overdue; ?>],
                    backgroundColor: ['#00FF88', '#FFB300', '#FF3366'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#fff', padding: 15, usePointStyle: true } }
                },
                cutout: '70%'
            }
        });
    </script>
</body>
</html>