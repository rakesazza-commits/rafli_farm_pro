<?php
session_start();
include 'koneksi.php';
include 'track_visitor.php'; // Lacak admin juga saat membuka halaman ini

if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }

// Ambil Statistik
$total_visits = $koneksi->query("SELECT COUNT(*) as total FROM visitor_logs")->fetch_assoc()['total'];
$today_visits = $koneksi->query("SELECT COUNT(*) as total FROM visitor_logs WHERE DATE(visit_time) = CURDATE()")->fetch_assoc()['total'];
$unique_ips = $koneksi->query("SELECT COUNT(DISTINCT ip_address) as total FROM visitor_logs")->fetch_assoc()['total'];

// Ambil 20 Kunjungan Terakhir
$recent_logs = $koneksi->query("SELECT * FROM visitor_logs ORDER BY visit_time DESC LIMIT 20");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Statistik Pengunjung - RAFLI_FARM_PRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'], mono: ['JetBrains Mono', 'monospace'] }, colors: { neon: '#00FF88', cyan: '#00E5FF', danger: '#FF3366', warning: '#FFB300', dark: { 900: '#0A0A0A', 800: '#111111' } } } } }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0A0A0A; color: #fff; }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .gradient-text { background: linear-gradient(135deg, #00FF88, #00E5FF); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-thumb { background: #00FF88; border-radius: 10px; }
    </style>
</head>
<body class="min-h-screen flex relative">
    <canvas id="weatherCanvas" class="fixed inset-0 z-0 pointer-events-none"></canvas>

    <!-- Sidebar -->
    <aside class="w-64 bg-dark-800/90 backdrop-blur-xl border-r border-white/5 flex flex-col h-screen fixed z-40 hidden lg:flex">
        <div class="p-6 border-b border-white/5"><h1 class="text-xl font-bold gradient-text">RAFLI_FARM</h1></div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">Dashboard</a>
            <a href="statistik.php" class="flex items-center gap-3 px-4 py-3 bg-neon/10 text-neon border-l-4 border-neon rounded-r-xl font-bold">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                Statistik Pengunjung
            </a>
            <a href="tanaman.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">Tanaman</a>
            <a href="gudang.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">Gudang</a>
            <a href="penjualan.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">Penjualan</a>
            <a href="ai-voice.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">Konsultan AI</a>
        </nav>
        <div class="p-4 border-t border-white/5"><a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-danger hover:bg-danger/10 rounded-xl">Keluar</a></div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 lg:ml-64 relative z-10 p-6">
        <div class="mb-8">
            <h1 class="text-3xl font-bold gradient-text">📊 Statistik & Pantauan Pengunjung</h1>
            <p class="text-gray-500 text-sm mt-1">Pantau siapa saja yang mengakses website RAFLI_FARM_PRO secara real-time</p>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="glass rounded-2xl p-6 relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-neon/10 rounded-full blur-2xl"></div>
                <div class="relative z-10">
                    <p class="text-gray-400 text-sm mb-1">Total Kunjungan</p>
                    <p class="text-4xl font-bold text-white font-mono"><?php echo number_format($total_visits); ?></p>
                    <p class="text-xs text-neon mt-2"> Sejak website dibuat</p>
                </div>
            </div>
            <div class="glass rounded-2xl p-6 relative overflow-hidden">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-cyan/10 rounded-full blur-2xl"></div>
                <div class="relative z-10">
                    <p class="text-gray-400 text-sm mb-1">Pengunjung Hari Ini</p>
                    <p class="text-4xl font-bold text-white font-mono"><?php echo number_format($today_visits); ?></p>
                    <p class="text-xs text-cyan mt-2">📅 <?php echo date('d M Y'); ?></p>
                </div>
            </div>
            <div class="glass rounded-2xl p-6 relative overflow-hidden">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-500/10 rounded-full blur-2xl"></div>
                <div class="relative z-10">
                    <p class="text-gray-400 text-sm mb-1">IP Unik (Pengunjung Berbeda)</p>
                    <p class="text-4xl font-bold text-white font-mono"><?php echo number_format($unique_ips); ?></p>
                    <p class="text-xs text-purple-400 mt-2">🌍 Perangkat/Jaringan Berbeda</p>
                </div>
            </div>
        </div>

        <!-- Recent Logs Table -->
        <div class="glass rounded-2xl p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-neon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    20 Kunjungan Terakhir
                </h3>
                <span class="text-xs text-gray-500">Diupdate secara otomatis</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-white/5 text-gray-400 text-xs uppercase tracking-wider">
                        <tr>
                            <th class="p-4">Waktu</th>
                            <th class="p-4">IP Address</th>
                            <th class="p-4">Halaman yang Dikunjungi</th>
                            <th class="p-4">Browser / Perangkat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-sm">
                        <?php if($recent_logs->num_rows > 0): ?>
                            <?php while($log = $recent_logs->fetch_assoc()): 
                                // Deteksi perangkat sederhana
                                $ua = $log['user_agent'];
                                $device = 'Desktop';
                                if (strpos($ua, 'Mobile') !== false || strpos($ua, 'Android') !== false || strpos($ua, 'iPhone') !== false) $device = ' Mobile';
                                elseif (strpos($ua, 'Tablet') !== false || strpos($ua, 'iPad') !== false) $device = ' Tablet';
                                
                                // Deteksi Browser
                                $browser = 'Unknown';
                                if (strpos($ua, 'Chrome') !== false) $browser = '🌐 Chrome';
                                elseif (strpos($ua, 'Firefox') !== false) $browser = '🦊 Firefox';
                                elseif (strpos($ua, 'Safari') !== false) $browser = '🧭 Safari';
                                elseif (strpos($ua, 'Edge') !== false) $browser = '🔷 Edge';
                            ?>
                            <tr class="hover:bg-white/5 transition-colors">
                                <td class="p-4 text-gray-300 font-mono text-xs">
                                    <?php echo date('d/m/Y H:i', strtotime($log['visit_time'])); ?>
                                </td>
                                <td class="p-4">
                                    <span class="px-2 py-1 bg-cyan/10 text-cyan rounded-md text-xs font-mono">
                                        <?php echo $log['ip_address']; ?>
                                    </span>
                                </td>
                                <td class="p-4 text-white">
                                    <span class="px-2 py-1 bg-white/5 rounded text-xs"><?php echo $log['page_visited']; ?></span>
                                </td>
                                <td class="p-4 text-gray-400 text-xs">
                                    <?php echo $device; ?> • <?php echo $browser; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="p-10 text-center text-gray-500">
                                    <p class="text-4xl mb-2">👀</p>
                                    <p>Belum ada data pengunjung. Bagikan link website Anda!</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Weather Canvas (Sakura) -->
    <script>
        const canvas = document.getElementById('weatherCanvas'); const ctx = canvas.getContext('2d'); let particles = [];
        function resizeCanvas() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; } resizeCanvas(); window.addEventListener('resize', resizeCanvas);
        class Particle { constructor() { this.x = Math.random() * canvas.width; this.y = Math.random() * canvas.height; this.size = 8 + Math.random() * 6; this.speedY = 1 + Math.random() * 1.5; this.wobble = Math.random() * Math.PI * 2; this.rotation = Math.random() * 360; } update() { this.y += this.speedY; this.wobble += 0.02; this.x += Math.sin(this.wobble) * 1; this.rotation += 1; if (this.y > canvas.height + 20) { this.y = -20; this.x = Math.random() * canvas.width; } } draw() { ctx.save(); ctx.translate(this.x, this.y); ctx.rotate((this.rotation * Math.PI) / 180); ctx.globalAlpha = 0.4; ctx.beginPath(); ctx.moveTo(0, 0); ctx.bezierCurveTo(this.size/2, -this.size/2, this.size, 0, 0, this.size); ctx.bezierCurveTo(-this.size, 0, -this.size/2, -this.size/2, 0, 0); ctx.fillStyle = '#FFB7D5'; ctx.fill(); ctx.restore(); } }
        for(let i=0; i<30; i++) particles.push(new Particle()); function animate() { ctx.clearRect(0, 0, canvas.width, canvas.height); particles.forEach(p => { p.update(); p.draw(); }); requestAnimationFrame(animate); } animate();
    </script>
</body>
</html>