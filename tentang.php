<?php session_start(); include 'koneksi.php'; include 'track_visitor.php'; if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; } ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tentang Kami - RAFLI_FARM_PRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { neon: '#00FF88', cyan: '#00E5FF', danger: '#FF3366', warning: '#FFB300', dark: { 900: '#0A0A0A', 800: '#111111', 700: '#1A1A2E' } } } } }
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

    <!-- Sidebar (Sama seperti profil.php) -->
    <aside class="w-64 bg-dark-800/90 backdrop-blur-xl border-r border-white/5 flex flex-col h-screen fixed z-40 hidden lg:flex">
        <div class="p-6 border-b border-white/5"><h1 class="text-xl font-bold gradient-text">RAFLI_FARM</h1></div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors">Dashboard</a>
            <a href="tanaman.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors">Tanaman</a>
            <a href="gudang.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors">Gudang</a>
            <a href="penjualan.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors">Penjualan</a>
            <a href="ai-deteksi.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors">AI Deteksi</a>
            <a href="ai-voice.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors">AI Voice</a>
            <a href="maps.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors">Peta Lahan</a>
            <a href="profil.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors">Profil Saya</a>
            <a href="tentang.php" class="flex items-center gap-3 px-4 py-3 bg-neon/10 text-neon border-l-4 border-neon rounded-r-xl font-bold">Tentang Kami</a>
            <a href="pengaturan.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors">Pengaturan</a>
            <a href="keuangan.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">💵 Keuangan</a>
            <a href="jadwal.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">📅 Jadwal</a>
        </nav>
        <div class="p-4 border-t border-white/5"><a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-danger hover:bg-danger/10 rounded-xl transition-colors">Keluar</a></div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 lg:ml-64 relative z-10 p-6">
        <!-- Hero Section -->
        <div class="glass rounded-3xl p-10 mb-8 text-center relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-full bg-gradient-to-br from-neon/5 to-cyan/5"></div>
            <div class="relative z-10">
                <div class="w-20 h-20 bg-gradient-to-br from-neon to-cyan rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg shadow-neon/30">
                    <svg class="w-10 h-10 text-dark-900" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 007.92 12.446A9 9 0 1112 3z" /></svg>
                </div>
                <h1 class="text-4xl md:text-5xl font-extrabold gradient-text mb-4">RAFLI_FARM_PRO</h1>
                <p class="text-xl text-gray-300 max-w-2xl mx-auto">Sistem Informasi Pertanian Cerdas Berbasis Web untuk Memajukan Pertanian Indonesia menuju Era Digital 4.0</p>
            </div>
        </div>

        <!-- Visi Misi -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="glass rounded-2xl p-6 border-l-4 border-neon">
                <h3 class="text-2xl font-bold text-neon mb-3 flex items-center gap-2"> Visi Kami</h3>
                <p class="text-gray-300 leading-relaxed">Menjadi platform teknologi pertanian terdepan di Indonesia yang memberdayakan petani lokal dengan solusi digital, cerdas, dan berkelanjutan untuk meningkatkan hasil panen dan kesejahteraan.</p>
            </div>
            <div class="glass rounded-2xl p-6 border-l-4 border-cyan">
                <h3 class="text-2xl font-bold text-cyan mb-3 flex items-center gap-2"> Misi Kami</h3>
                <ul class="text-gray-300 space-y-2 list-disc list-inside">
                    <li>Menyediakan alat manajemen lahan yang mudah digunakan.</li>
                    <li>Mengintegrasikan AI untuk deteksi dini penyakit tanaman.</li>
                    <li>Membantu petani mendapatkan harga pasar yang adil.</li>
                </ul>
            </div>
        </div>

        <!-- Fitur Unggulan -->
        <h2 class="text-2xl font-bold text-white mb-6">✨ Fitur Unggulan</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <?php 
            $features = [
                ['icon' => '🌱', 'title' => 'Manajemen Tanaman', 'desc' => 'Pantau pertumbuhan dari semai hingga panen.'],
                ['icon' => '📦', 'title' => 'Gudang & Stok', 'desc' => 'Kelola pupuk dan pestisida dengan alert otomatis.'],
                ['icon' => '💰', 'title' => 'Pencatatan Keuangan', 'desc' => 'Laporan penjualan dan HPP yang rapi.'],
                ['icon' => '🧠', 'title' => 'AI Deteksi Penyakit', 'desc' => 'Scan daun dengan AI untuk diagnosis cepat.'],
                ['icon' => '🎤', 'title' => 'AI Voice Assistant', 'desc' => 'Kontrol aplikasi hanya dengan suara.'],
                ['icon' => '🗺️', 'title' => 'Peta Lahan Interaktif', 'desc' => 'Visualisasi lokasi lahan dengan GPS.'],
            ];
            foreach($features as $f): ?>
            <div class="glass rounded-xl p-5 hover:border-neon/30 transition-all group">
                <div class="text-3xl mb-3 group-hover:scale-110 transition-transform inline-block"><?php echo $f['icon']; ?></div>
                <h4 class="text-lg font-bold text-white mb-1"><?php echo $f['title']; ?></h4>
                <p class="text-sm text-gray-400"><?php echo $f['desc']; ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Tech Stack -->
        <div class="glass rounded-2xl p-6">
            <h3 class="text-lg font-bold text-white mb-4">️ Dibangun Dengan Teknologi Terbaik</h3>
            <div class="flex flex-wrap gap-3">
                <?php 
                $techs = ['PHP Native', 'MySQL (XAMPP)', 'Tailwind CSS', 'JavaScript (ES6)', 'Leaflet.js', 'Web Speech API', 'HTML5 Canvas'];
                foreach($techs as $t): ?>
                <span class="px-4 py-2 bg-white/5 border border-white/10 rounded-full text-sm text-gray-300 font-mono"><?php echo $t; ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        
        <p class="text-center text-gray-600 text-xs mt-10">&copy; 2026 RAFLI_FARM_PRO. Dikembangkan dengan ❤️ untuk Petani Indonesia.</p>
    </div>

    <script>
        const canvas = document.getElementById('weatherCanvas'); const ctx = canvas.getContext('2d'); let particles = [];
        function resizeCanvas() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; } resizeCanvas(); window.addEventListener('resize', resizeCanvas);
        class Particle { constructor() { this.x = Math.random() * canvas.width; this.y = Math.random() * canvas.height; this.size = 8 + Math.random() * 6; this.speedY = 1 + Math.random() * 1.5; this.wobble = Math.random() * Math.PI * 2; this.rotation = Math.random() * 360; } update() { this.y += this.speedY; this.wobble += 0.02; this.x += Math.sin(this.wobble) * 1; this.rotation += 1; if (this.y > canvas.height + 20) { this.y = -20; this.x = Math.random() * canvas.width; } } draw() { ctx.save(); ctx.translate(this.x, this.y); ctx.rotate((this.rotation * Math.PI) / 180); ctx.globalAlpha = 0.4; ctx.beginPath(); ctx.moveTo(0, 0); ctx.bezierCurveTo(this.size/2, -this.size/2, this.size, 0, 0, this.size); ctx.bezierCurveTo(-this.size, 0, -this.size/2, -this.size/2, 0, 0); ctx.fillStyle = '#FFB7D5'; ctx.fill(); ctx.restore(); } }
        for(let i=0; i<30; i++) particles.push(new Particle()); function animate() { ctx.clearRect(0, 0, canvas.width, canvas.height); particles.forEach(p => { p.update(); p.draw(); }); requestAnimationFrame(animate); } animate();
    </script>
</body>
</html>