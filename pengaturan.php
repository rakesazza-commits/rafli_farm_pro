<?php
session_start();
include 'koneksi.php';
include 'track_visitor.php';
if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];

// Handle Update Settings & Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_settings') {
        $notif = isset($_POST['notif']) ? 1 : 0;
        $weather = isset($_POST['weather']) ? 1 : 0;
        $price = isset($_POST['price']) ? 1 : 0;
        
        $koneksi->query("INSERT INTO user_settings (user_id, notif_enabled, weather_alert, price_alert) VALUES ($user_id, $notif, $weather, $price) ON DUPLICATE KEY UPDATE notif_enabled=$notif, weather_alert=$weather, price_alert=$price");
        header("Location: pengaturan.php?success=settings");
        exit;
    }
    if ($_POST['action'] === 'change_password') {
        $old_pass = $_POST['old_password'];
        $new_pass = $_POST['new_password'];
        $user = $koneksi->query("SELECT password FROM users WHERE id=$user_id")->fetch_assoc();
        
        if (password_verify($old_pass, $user['password'])) {
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $koneksi->query("UPDATE users SET password='$new_hash' WHERE id=$user_id");
            header("Location: pengaturan.php?success=password");
            exit;
        } else {
            header("Location: pengaturan.php?error=wrong_pass");
            exit;
        }
    }
    if ($_POST['action'] === 'delete_account') {
        // Hapus user dan semua datanya (Cascade)
        $koneksi->query("DELETE FROM users WHERE id=$user_id");
        session_destroy();
        header("Location: login.php?deleted=1");
        exit;
    }
}

// Ambil Settings
$settings = $koneksi->query("SELECT * FROM user_settings WHERE user_id=$user_id")->fetch_assoc();
if (!$settings) { $settings = ['notif_enabled'=>1, 'weather_alert'=>1, 'price_alert'=>1]; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pengaturan - RAFLI_FARM_PRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { neon: '#00FF88', cyan: '#00E5FF', danger: '#FF3366', warning: '#FFB300', dark: { 900: '#0A0A0A', 800: '#111111', 700: '#1A1A2E' } } } } }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0A0A0A; color: #fff; }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .gradient-text { background: linear-gradient(135deg, #00FF88, #00E5FF); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        /* Toggle Switch */
        .toggle-checkbox:checked { right: 0; border-color: #00FF88; } .toggle-checkbox:checked + .toggle-label { background-color: #00FF88; }
        ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-thumb { background: #00FF88; border-radius: 10px; }
    </style>
</head>
<body class="min-h-screen flex relative">
    <canvas id="weatherCanvas" class="fixed inset-0 z-0 pointer-events-none"></canvas>

    <!-- Sidebar -->
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
            <a href="tentang.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors">Tentang Kami</a>
            <a href="pengaturan.php" class="flex items-center gap-3 px-4 py-3 bg-neon/10 text-neon border-l-4 border-neon rounded-r-xl font-bold">Pengaturan</a>
            <a href="keuangan.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">💵 Keuangan</a>
            <a href="jadwal.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">📅 Jadwal</a>
        </nav>
        <div class="p-4 border-t border-white/5"><a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-danger hover:bg-danger/10 rounded-xl transition-colors">Keluar</a></div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 lg:ml-64 relative z-10 p-6 max-w-4xl mx-auto w-full">
        <div class="mb-8"><h1 class="text-3xl font-bold gradient-text">Pengaturan Sistem</h1><p class="text-gray-500 text-sm mt-1">Konfigurasi notifikasi, keamanan, dan preferensi akun</p></div>

        <?php if(isset($_GET['success'])): ?><div class="mb-4 p-4 bg-neon/10 border border-neon/30 rounded-xl text-neon text-sm">✅ <?php echo $_GET['success']=='password' ? 'Password berhasil diubah!' : 'Pengaturan berhasil disimpan!'; ?></div><?php endif; ?>
        <?php if(isset($_GET['error'])): ?><div class="mb-4 p-4 bg-danger/10 border border-danger/30 rounded-xl text-danger text-sm">⚠️ Password lama salah!</div><?php endif; ?>

        <!-- Notifikasi Settings -->
        <div class="glass rounded-2xl p-6 mb-6">
            <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 text-cyan" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                Preferensi Notifikasi
            </h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_settings">
                
                <div class="flex items-center justify-between p-4 bg-white/5 rounded-xl">
                    <div><p class="font-medium text-white">Notifikasi Sistem</p><p class="text-xs text-gray-500">Terima update aktivitas harian</p></div>
                    <div class="relative inline-block w-12 mr-2 align-middle select-none">
                        <input type="checkbox" name="notif" id="notif" <?php echo $settings['notif_enabled'] ? 'checked' : ''; ?> class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer transition-all duration-300 left-0 top-0 border-gray-300"/>
                        <label for="notif" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-600 cursor-pointer transition-colors duration-300"></label>
                    </div>
                </div>

                <div class="flex items-center justify-between p-4 bg-white/5 rounded-xl">
                    <div><p class="font-medium text-white">Alert Cuaca Ekstrem</p><p class="text-xs text-gray-500">Peringatan badai/kekeringan</p></div>
                    <div class="relative inline-block w-12 mr-2 align-middle select-none">
                        <input type="checkbox" name="weather" id="weather" <?php echo $settings['weather_alert'] ? 'checked' : ''; ?> class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer transition-all duration-300 left-0 top-0 border-gray-300"/>
                        <label for="weather" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-600 cursor-pointer transition-colors duration-300"></label>
                    </div>
                </div>

                <div class="flex items-center justify-between p-4 bg-white/5 rounded-xl">
                    <div><p class="font-medium text-white">Alert Perubahan Harga Pasar</p><p class="text-xs text-gray-500">Notifikasi jika harga komoditas fluktuatif</p></div>
                    <div class="relative inline-block w-12 mr-2 align-middle select-none">
                        <input type="checkbox" name="price" id="price" <?php echo $settings['price_alert'] ? 'checked' : ''; ?> class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer transition-all duration-300 left-0 top-0 border-gray-300"/>
                        <label for="price" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-600 cursor-pointer transition-colors duration-300"></label>
                    </div>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="submit" class="flex-1 py-3 bg-neon text-dark-900 font-bold rounded-xl hover:shadow-lg hover:shadow-neon/30 transition-all">Simpan Pengaturan</button>
                    <a href="dashboard.php" class="flex-1 py-3 bg-white/5 border border-white/10 text-gray-300 rounded-xl hover:bg-white/10 transition-all text-center font-bold">Batal</a>
                </div>
            </form>
        </div>

        <!-- Change Password -->
        <div class="glass rounded-2xl p-6 mb-6">
            <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 text-warning" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                Ubah Password
            </h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="change_password">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Password Lama</label>
                    <input type="password" name="old_password" required class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Password Baru</label>
                    <input type="password" name="new_password" required minlength="6" class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon">
                </div>
                <button type="submit" class="w-full py-3 bg-warning text-dark-900 font-bold rounded-xl hover:shadow-lg hover:shadow-warning/30 transition-all">Update Password</button>
            </form>
        </div>

        <!-- Danger Zone -->
        <div class="glass rounded-2xl p-6 border border-danger/20">
            <h3 class="text-lg font-bold text-danger mb-2">⚠️ Zona Berbahaya</h3>
            <p class="text-sm text-gray-400 mb-6">Tindakan di bawah ini tidak dapat dibatalkan. Semua data lahan, tanaman, dan riwayat Anda akan dihapus permanen.</p>
            <form method="POST" onsubmit="return confirm('PERINGATAN: Yakin ingin menghapus akun dan SEMUA data Anda? Tindakan ini tidak bisa dibatalkan!')">
                <input type="hidden" name="action" value="delete_account">
                <!-- TOMBOL HAPUS -->
                <button type="submit" class="w-full py-3 bg-danger/10 border border-danger/30 text-danger font-bold rounded-xl hover:bg-danger hover:text-white transition-all flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    Hapus Akun Saya Secara Permanen
                </button>
            </form>
        </div>
    </div>

    <script>
        const canvas = document.getElementById('weatherCanvas'); const ctx = canvas.getContext('2d'); let particles = [];
        function resizeCanvas() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; } resizeCanvas(); window.addEventListener('resize', resizeCanvas);
        class Particle { constructor() { this.x = Math.random() * canvas.width; this.y = Math.random() * canvas.height; this.size = 8 + Math.random() * 6; this.speedY = 1 + Math.random() * 1.5; this.wobble = Math.random() * Math.PI * 2; this.rotation = Math.random() * 360; } update() { this.y += this.speedY; this.wobble += 0.02; this.x += Math.sin(this.wobble) * 1; this.rotation += 1; if (this.y > canvas.height + 20) { this.y = -20; this.x = Math.random() * canvas.width; } } draw() { ctx.save(); ctx.translate(this.x, this.y); ctx.rotate((this.rotation * Math.PI) / 180); ctx.globalAlpha = 0.4; ctx.beginPath(); ctx.moveTo(0, 0); ctx.bezierCurveTo(this.size/2, -this.size/2, this.size, 0, 0, this.size); ctx.bezierCurveTo(-this.size, 0, -this.size/2, -this.size/2, 0, 0); ctx.fillStyle = '#FFB7D5'; ctx.fill(); ctx.restore(); } }
        for(let i=0; i<30; i++) particles.push(new Particle()); function animate() { ctx.clearRect(0, 0, canvas.width, canvas.height); particles.forEach(p => { p.update(); p.draw(); }); requestAnimationFrame(animate); } animate();
    </script>
</body>
</html>