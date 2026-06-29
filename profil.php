<?php
session_start();
include 'koneksi.php';
include 'track_visitor.php';
if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];

// Handle Update Profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $nama = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
        $email = mysqli_real_escape_string($koneksi, $_POST['email']);
        $phone = mysqli_real_escape_string($koneksi, $_POST['phone']);
        $address = mysqli_real_escape_string($koneksi, $_POST['address']);
        
        // Handle Foto Upload
        $photo_path = $_SESSION['photo'] ?? 'default-avatar.png';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $new_name = "user_" . $user_id . "_" . time() . "." . $ext;
            $dest = "uploads/" . $new_name;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                // Hapus foto lama jika bukan default
                if ($photo_path !== 'default-avatar.png' && file_exists("uploads/" . $photo_path)) {
                    unlink("uploads/" . $photo_path);
                }
                $photo_path = $new_name;
            }
        }

        $sql = "UPDATE users SET nama_lengkap='$nama', email='$email', phone='$phone', address='$address', photo='$photo_path' WHERE id=$user_id";
        $koneksi->query($sql);
        
        $_SESSION['nama_lengkap'] = $nama;
        $_SESSION['photo'] = $photo_path;
        header("Location: profil.php?success=1");
        exit;
    }
}

// Ambil Data User Terbaru
$user = $koneksi->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Profil Saya - RAFLI_FARM_PRO</title>
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
            <a href="profil.php" class="flex items-center gap-3 px-4 py-3 bg-neon/10 text-neon border-l-4 border-neon rounded-r-xl font-bold">Profil Saya</a>
            <a href="tentang.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors">Tentang Kami</a>
            <a href="pengaturan.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors">Pengaturan</a>
            <a href="keuangan.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">💵 Keuangan</a>
            <a href="jadwal.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">📅 Jadwal</a>
        </nav>
        <div class="p-4 border-t border-white/5"><a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-danger hover:bg-danger/10 rounded-xl transition-colors">Keluar</a></div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 lg:ml-64 relative z-10 p-6">
        <div class="mb-8"><h1 class="text-3xl font-bold gradient-text">Profil Pengguna</h1><p class="text-gray-500 text-sm mt-1">Kelola informasi pribadi dan akun Anda</p></div>

        <?php if(isset($_GET['success'])): ?><div class="mb-4 p-4 bg-neon/10 border border-neon/30 rounded-xl text-neon text-sm">✅ Profil berhasil diperbarui!</div><?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Avatar Card -->
            <div class="glass rounded-2xl p-6 text-center h-fit">
                <div class="relative w-32 h-32 mx-auto mb-4">
                    <img src="<?php echo $user['photo'] !== 'default-avatar.png' ? 'uploads/'.$user['photo'] : 'https://ui-avatars.com/api/?name='.urlencode($user['nama_lengkap']).'&background=00FF88&color=000&size=128'; ?>" class="w-full h-full rounded-full object-cover border-4 border-neon/30 shadow-lg shadow-neon/20">
                    <div class="absolute bottom-0 right-0 w-8 h-8 bg-neon rounded-full flex items-center justify-center cursor-pointer hover:scale-110 transition-transform" onclick="document.getElementById('photoInput').click()">
                        <svg class="w-4 h-4 text-dark-900" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </div>
                </div>
                <h2 class="text-xl font-bold text-white"><?php echo $user['nama_lengkap']; ?></h2>
                <p class="text-sm text-gray-500">@<?php echo $user['username']; ?></p>
                <span class="inline-block mt-2 px-3 py-1 bg-neon/10 text-neon text-xs font-bold rounded-full uppercase"><?php echo $user['role']; ?></span>
            </div>

            <!-- Form Edit Profil -->
            <div class="lg:col-span-2 glass rounded-2xl p-6">
                <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                    <svg class="w-5 h-5 text-cyan" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    Edit Informasi Pribadi
                </h3>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="file" id="photoInput" name="photo" class="hidden" accept="image/*" onchange="this.form.submit()">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" value="<?php echo $user['nama_lengkap']; ?>" required class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Username (Read Only)</label>
                            <input type="text" value="<?php echo $user['username']; ?>" disabled class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-gray-500 cursor-not-allowed">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Email</label>
                            <input type="email" name="email" value="<?php echo $user['email']; ?>" class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">No. Telepon</label>
                            <input type="text" name="phone" value="<?php echo $user['phone'] ?? ''; ?>" placeholder="0812..." class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Alamat Lengkap</label>
                        <textarea name="address" rows="3" class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-neon"><?php echo $user['address'] ?? ''; ?></textarea>
                    </div>
                    
                    <div class="flex gap-3 pt-4">
                        <!-- TOMBOL SIMPAN -->
                        <button type="submit" class="flex-1 py-3 bg-neon text-dark-900 font-bold rounded-xl hover:shadow-lg hover:shadow-neon/30 transition-all flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
                            Simpan Perubahan
                        </button>
                        <!-- TOMBOL BATAL -->
                        <a href="dashboard.php" class="flex-1 py-3 bg-white/5 border border-white/10 text-gray-300 rounded-xl hover:bg-white/10 transition-all text-center font-bold">Batal</a>
                    </div>
                </form>
            </div>
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