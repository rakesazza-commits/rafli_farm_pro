<?php
session_start();
include 'koneksi.php';
include 'track_visitor.php';
if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }

// ==========================================
// HANDLE TAMBAH TRANSAKSI
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $tipe = $_POST['tipe'];
    $kategori = mysqli_real_escape_string($koneksi, $_POST['kategori']);
    $deskripsi = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
    $jumlah = (float)$_POST['jumlah'];
    $tanggal = $_POST['tanggal'];
    $tanaman_id = !empty($_POST['tanaman_id']) ? (int)$_POST['tanaman_id'] : "NULL";

    $sql = "INSERT INTO keuangan (tipe, kategori, deskripsi, jumlah, tanggal, tanaman_id) 
            VALUES ('$tipe', '$kategori', '$deskripsi', '$jumlah', '$tanggal', $tanaman_id)";
    $koneksi->query($sql);
    header("Location: keuangan.php?success=1");
    exit;
}

// HANDLE HAPUS
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $koneksi->query("DELETE FROM keuangan WHERE id=$id");
    header("Location: keuangan.php");
    exit;
}

// ==========================================
// KALKULASI CERDAS (TERKONEKSI PENJUALAN)
// ==========================================
// 1. Total Pendapatan dari Penjualan (Lunas)
$pendapatan_penjualan = $koneksi->query("SELECT COALESCE(SUM(total_harga), 0) as t FROM penjualan WHERE status_pembayaran='lunas'")->fetch_assoc()['t'];

// 2. Total Pemasukan Lain (Manual di keuangan)
$pendapatan_lain = $koneksi->query("SELECT COALESCE(SUM(jumlah), 0) as t FROM keuangan WHERE tipe='pemasukan'")->fetch_assoc()['t'];

// 3. Total Pengeluaran Operasional
$total_pengeluaran = $koneksi->query("SELECT COALESCE(SUM(jumlah), 0) as t FROM keuangan WHERE tipe='pengeluaran'")->fetch_assoc()['t'];

// 4. Laba Bersih & ROI
$total_pendapatan = $pendapatan_penjualan + $pendapatan_lain;
$laba_bersih = $total_pendapatan - $total_pengeluaran;
$roi = $total_pengeluaran > 0 ? (($laba_bersih / $total_pengeluaran) * 100) : 0;

// Ambil Data Tanaman untuk Dropdown & Analisis
$daftar_tanaman = $koneksi->query("SELECT id, nama_tanaman, nama_lahan FROM tanaman ORDER BY nama_lahan");

// Ambil Riwayat Transaksi
$riwayat = $koneksi->query("SELECT k.*, t.nama_tanaman, t.nama_lahan 
                            FROM keuangan k 
                            LEFT JOIN tanaman t ON k.tanaman_id = t.id 
                            ORDER BY k.tanggal DESC, k.id DESC LIMIT 50");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Keuangan Cerdas - RAFLI_FARM_PRO</title>
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
            <a href="keuangan.php" class="flex items-center gap-3 px-4 py-3 bg-neon/10 text-neon border-l-4 border-neon rounded-r-xl font-bold">💰 Keuangan</a>
            <a href="jadwal.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">📅 Jadwal</a>
            <a href="kalkulator.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl"> Kalkulator</a>
            <a href="ai-voice.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">🎤 AI Voice</a>
        </nav>
        <div class="p-4 border-t border-white/5"><a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-danger hover:bg-danger/10 rounded-xl">Keluar</a></div>
    </aside>

    <div class="flex-1 lg:ml-64 p-6 relative z-10">
        <div class="mb-8 animate-up">
            <h1 class="text-3xl font-bold gradient-text">💰 Keuangan & Profitabilitas Cerdas</h1>
            <p class="text-gray-500 text-sm mt-1">Terintegrasi otomatis dengan Data Penjualan & Biaya Operasional per Tanaman</p>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div class="mb-4 p-4 bg-neon/10 border border-neon/30 rounded-xl text-neon text-sm">✅ Transaksi berhasil dicatat!</div>
        <?php endif; ?>

        <!-- SUMMARY CARDS (ULTIMATE) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 animate-up">
            <div class="glass rounded-2xl p-6 border-l-4 border-neon relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-neon/10 rounded-full blur-2xl group-hover:bg-neon/20 transition-all"></div>
                <p class="text-xs text-gray-400 uppercase tracking-wider relative z-10">Total Pendapatan</p>
                <p class="text-2xl font-bold text-neon mt-2 relative z-10">Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></p>
                <p class="text-[10px] text-gray-500 mt-1 relative z-10">📈 Penjualan: Rp <?php echo number_format($pendapatan_penjualan, 0, ',', '.'); ?></p>
            </div>
            
            <div class="glass rounded-2xl p-6 border-l-4 border-danger relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-danger/10 rounded-full blur-2xl group-hover:bg-danger/20 transition-all"></div>
                <p class="text-xs text-gray-400 uppercase tracking-wider relative z-10">Total Pengeluaran</p>
                <p class="text-2xl font-bold text-danger mt-2 relative z-10">Rp <?php echo number_format($total_pengeluaran, 0, ',', '.'); ?></p>
                <p class="text-[10px] text-gray-500 mt-1 relative z-10">📉 Biaya Operasional</p>
            </div>

            <div class="glass rounded-2xl p-6 border-l-4 border-cyan relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-cyan/10 rounded-full blur-2xl group-hover:bg-cyan/20 transition-all"></div>
                <p class="text-xs text-gray-400 uppercase tracking-wider relative z-10">Laba Bersih</p>
                <p class="text-2xl font-bold text-cyan mt-2 relative z-10">Rp <?php echo number_format($laba_bersih, 0, ',', '.'); ?></p>
                <p class="text-[10px] <?php echo $laba_bersih >= 0 ? 'text-neon' : 'text-danger'; ?> mt-1 relative z-10">
                    <?php echo $laba_bersih >= 0 ? '🚀 Profit' : '⚠️ Defisit'; ?>
                </p>
            </div>

            <div class="glass rounded-2xl p-6 border-l-4 border-purple relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-500/10 rounded-full blur-2xl group-hover:bg-purple-500/20 transition-all"></div>
                <p class="text-xs text-gray-400 uppercase tracking-wider relative z-10">ROI (Return on Investment)</p>
                <p class="text-2xl font-bold text-purple-400 mt-2 relative z-10"><?php echo number_format($roi, 1); ?>%</p>
                <p class="text-[10px] text-gray-500 mt-1 relative z-10">📊 Efisiensi Modal</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8 animate-up">
            <!-- FORM TAMBAH -->
            <div class="glass rounded-2xl p-6 h-fit">
                <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-neon/10 flex items-center justify-center text-neon">+</span>
                    Catat Transaksi
                </h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Tipe</label>
                            <select name="tipe" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                                <option value="pengeluaran">Pengeluaran</option>
                                <option value="pemasukan">Pemasukan Lain</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Untuk Tanaman</label>
                            <select name="tanaman_id" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                                <option value="">-- Umum / Lain --</option>
                                <?php 
                                $daftar_tanaman->data_seek(0);
                                while($t = $daftar_tanaman->fetch_assoc()): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo $t['nama_tanaman']; ?> (<?php echo $t['nama_lahan']; ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Kategori</label>
                        <input type="text" name="kategori" required placeholder="Contoh: Pupuk, Bibit, Upah Buruh" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Deskripsi</label>
                        <input type="text" name="deskripsi" placeholder="Keterangan singkat" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Jumlah (Rp)</label>
                            <input type="number" name="jumlah" required class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Tanggal</label>
                            <input type="date" name="tanggal" required value="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3 bg-gradient-to-r from-neon to-cyan text-dark-900 font-bold rounded-lg hover:shadow-lg hover:shadow-neon/30 transition-all">Simpan Transaksi</button>
                </form>
            </div>

            <!-- CHART & ANALISIS -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Chart -->
                <div class="glass rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-white mb-4">📊 Analisis Arus Kas</h3>
                    <div class="h-64 flex items-center justify-center">
                        <canvas id="financeChart"></canvas>
                    </div>
                </div>

                <!-- Breakdown Penjualan vs Pengeluaran -->
                <div class="glass rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-white mb-4"> Rincian Perhitungan Laba</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between items-center p-3 bg-neon/5 rounded-lg border border-neon/20">
                            <span class="text-gray-300">💰 Total Penjualan (Lunas)</span>
                            <span class="font-bold text-neon">+ Rp <?php echo number_format($pendapatan_penjualan, 0, ',', '.'); ?></span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-purple-500/5 rounded-lg border border-purple-500/20">
                            <span class="text-gray-300"> Pemasukan Lain-lain</span>
                            <span class="font-bold text-purple-400">+ Rp <?php echo number_format($pendapatan_lain, 0, ',', '.'); ?></span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-danger/5 rounded-lg border border-danger/20">
                            <span class="text-gray-300"> Total Pengeluaran Operasional</span>
                            <span class="font-bold text-danger">- Rp <?php echo number_format($total_pengeluaran, 0, ',', '.'); ?></span>
                        </div>
                        <div class="h-px bg-white/10 my-2"></div>
                        <div class="flex justify-between items-center p-4 bg-cyan/10 rounded-lg border border-cyan/30">
                            <span class="font-bold text-white text-base">🏆 LABA BERSIH</span>
                            <span class="font-black text-cyan text-xl">Rp <?php echo number_format($laba_bersih, 0, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIWAYAT TRANSAKSI -->
        <div class="glass rounded-2xl p-6 animate-up">
            <h3 class="text-lg font-bold text-white mb-4">📜 Riwayat Transaksi Operasional</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs text-gray-400 uppercase border-b border-white/10">
                        <tr>
                            <th class="p-3">Tanggal</th>
                            <th class="p-3">Tipe</th>
                            <th class="p-3">Kategori</th>
                            <th class="p-3">Tanaman Terkait</th>
                            <th class="p-3">Deskripsi</th>
                            <th class="p-3 text-right">Jumlah</th>
                            <th class="p-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php if($riwayat->num_rows > 0): while($row = $riwayat->fetch_assoc()): ?>
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="p-3 text-gray-400"><?php echo date('d M Y', strtotime($row['tanggal'])); ?></td>
                            <td class="p-3">
                                <span class="px-2 py-1 rounded text-xs font-bold <?php echo $row['tipe'] == 'pemasukan' ? 'bg-neon/10 text-neon' : 'bg-danger/10 text-danger'; ?>">
                                    <?php echo $row['tipe'] == 'pemasukan' ? 'MASUK' : 'KELUAR'; ?>
                                </span>
                            </td>
                            <td class="p-3 font-medium text-white"><?php echo $row['kategori']; ?></td>
                            <td class="p-3 text-gray-400"><?php echo $row['nama_tanaman'] ? '<span class="text-cyan">'.$row['nama_tanaman'].'</span> <span class="text-xs text-gray-600">('.$row['nama_lahan'].')</span>' : '<span class="text-gray-600">Umum</span>'; ?></td>
                            <td class="p-3 text-gray-400 text-xs"><?php echo $row['deskripsi']; ?></td>
                            <td class="p-3 text-right font-bold <?php echo $row['tipe'] == 'pemasukan' ? 'text-neon' : 'text-danger'; ?>">
                                <?php echo $row['tipe'] == 'pemasukan' ? '+' : '-'; ?> Rp <?php echo number_format($row['jumlah'], 0, ',', '.'); ?>
                            </td>
                            <td class="p-3 text-center">
                                <a href="?delete=<?php echo $row['id']; ?>" onclick="return confirm('Hapus transaksi ini?')" class="text-gray-500 hover:text-danger transition-colors">🗑️</a>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="7" class="p-8 text-center text-gray-500">Belum ada transaksi operasional.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SCRIPT CHART.JS -->
    <script>
        const ctx = document.getElementById('financeChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Pendapatan Penjualan', 'Pemasukan Lain', 'Pengeluaran'],
                datasets: [{
                    data: [<?php echo $pendapatan_penjualan; ?>, <?php echo $pendapatan_lain; ?>, <?php echo $total_pengeluaran; ?>],
                    backgroundColor: ['#00FF88', '#A855F7', '#FF3366'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#fff', padding: 20, usePointStyle: true } }
                },
                cutout: '70%'
            }
        });
    </script>
</body>
</html>