<?php
session_start();

// Jika sudah login, langsung ke dashboard
if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RAFLI_FARM_PRO</title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        neon: '#00FF88',
                        cyan: '#00E5FF',
                        danger: '#FF3366',
                        dark: {
                            900: '#0A0A0A',
                            800: '#111111',
                            700: '#1A1A2E',
                            600: '#16213E',
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        /* ===== CUSTOM ANIMATIONS ===== */
        body {
            font-family: 'Inter', sans-serif;
            overflow: hidden;
        }

        /* Canvas Background */
        #particleCanvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
        }

        /* Glassmorphism Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }

        /* Glowing Border Effect */
        .glow-border {
            position: relative;
        }
        .glow-border::before {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 1.25rem;
            background: linear-gradient(135deg, #00FF88, #00E5FF, #8B5CF6, #00FF88);
            background-size: 300% 300%;
            animation: borderGlow 4s ease infinite;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.5s ease;
        }
        .glow-border:hover::before {
            opacity: 1;
        }

        @keyframes borderGlow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* Neon Glow Input Focus */
        .input-neon:focus {
            box-shadow: 
                0 0 0 2px rgba(0, 255, 136, 0.3),
                0 0 20px rgba(0, 255, 136, 0.15);
            border-color: #00FF88;
        }

        /* Gradient Text */
        .gradient-text {
            background: linear-gradient(135deg, #00FF88 0%, #00E5FF 50%, #8B5CF6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Button Glow */
        .btn-glow {
            background: linear-gradient(135deg, #00FF88 0%, #00CC6A 100%);
            box-shadow: 0 4px 20px rgba(0, 255, 136, 0.4);
            transition: all 0.3s ease;
        }
        .btn-glow:hover {
            box-shadow: 0 6px 30px rgba(0, 255, 136, 0.6);
            transform: translateY(-2px);
        }
        .btn-glow:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(0, 255, 136, 0.4);
        }

        /* Floating Orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            animation: float 8s ease-in-out infinite;
        }
        .orb-1 {
            width: 400px;
            height: 400px;
            background: rgba(0, 255, 136, 0.15);
            top: -100px;
            left: -100px;
            animation-delay: 0s;
        }
        .orb-2 {
            width: 500px;
            height: 500px;
            background: rgba(0, 229, 255, 0.1);
            bottom: -150px;
            right: -150px;
            animation-delay: -3s;
        }
        .orb-3 {
            width: 300px;
            height: 300px;
            background: rgba(139, 92, 246, 0.1);
            top: 50%;
            left: 50%;
            animation-delay: -5s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(30px, -30px) scale(1.05); }
            50% { transform: translate(-20px, 20px) scale(0.95); }
            75% { transform: translate(20px, 10px) scale(1.02); }
        }

        /* Slide Up Animation */
        .slide-up {
            animation: slideUp 0.8s ease-out forwards;
            opacity: 0;
        }
        .slide-up-delay-1 { animation-delay: 0.1s; }
        .slide-up-delay-2 { animation-delay: 0.2s; }
        .slide-up-delay-3 { animation-delay: 0.3s; }
        .slide-up-delay-4 { animation-delay: 0.4s; }
        .slide-up-delay-5 { animation-delay: 0.5s; }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Pulse Ring on Logo */
        .pulse-ring {
            animation: pulseRing 2s ease-out infinite;
        }
        @keyframes pulseRing {
            0% { box-shadow: 0 0 0 0 rgba(0, 255, 136, 0.4); }
            70% { box-shadow: 0 0 0 20px rgba(0, 255, 136, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 255, 136, 0); }
        }

        /* Loading Spinner */
        .spinner {
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-top: 3px solid #000;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 0.8s linear infinite;
            display: none;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Shake Animation for Error */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        .shake {
            animation: shake 0.5s ease-in-out;
        }
    </style>
</head>
<body class="min-h-screen bg-dark-900 flex items-center justify-center relative">

    <!-- ===== BACKGROUND EFFECTS ===== -->
    
    <!-- Particle Canvas (Sakura / Rain / Snow) -->
    <canvas id="particleCanvas"></canvas>
    
    <!-- Floating Orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <!-- ===== LOGIN CARD ===== -->
    <div class="relative z-10 w-full max-w-md mx-4">
        <div class="glass-card glow-border rounded-2xl p-8 slide-up">
            
            <!-- Logo -->
            <div class="flex justify-center mb-6 slide-up slide-up-delay-1">
                <div class="relative">
                    <div class="w-20 h-20 bg-gradient-to-br from-neon to-cyan rounded-2xl flex items-center justify-center pulse-ring">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-dark-900" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 007.92 12.446A9 9 0 1112 3z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 4a2 2 0 012 2v1a2 2 0 01-2 2h-1a2 2 0 01-2-2V6a2 2 0 012-2h1z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Title -->
            <div class="text-center mb-8 slide-up slide-up-delay-2">
                <h1 class="text-3xl font-extrabold gradient-text mb-2 tracking-tight">
                    RAFLI_FARM_PRO
                </h1>
                <p class="text-gray-500 text-sm font-medium">Sistem Pertanian Cerdas 🌾</p>
            </div>

            <!-- Error Message -->
            <?php if (isset($_GET['error'])): ?>
            <div id="errorMsg" class="mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400 text-sm text-center slide-up">
                <?php
                    if ($_GET['error'] == 'invalid') echo '⚠️ Username atau password salah!';
                    elseif ($_GET['error'] == 'empty') echo '⚠️ Semua field wajib diisi!';
                    else echo '⚠️ Terjadi kesalahan!';
                ?>
            </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="proses_login.php" method="POST" id="loginForm" class="space-y-5">
                
                <!-- Username -->
                <div class="slide-up slide-up-delay-3">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Username</label>
                    <div class="relative">
                        <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <input 
                            type="text" 
                            name="username" 
                            id="username"
                            placeholder="Masukkan username"
                            required
                            class="w-full pl-11 pr-4 py-3.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-600 focus:outline-none input-neon transition-all duration-300 text-sm"
                        >
                    </div>
                </div>

                <!-- Password -->
                <div class="slide-up slide-up-delay-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Password</label>
                    <div class="relative">
                        <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <input 
                            type="password" 
                            name="password" 
                            id="password"
                            placeholder="Masukkan password"
                            required
                            class="w-full pl-11 pr-12 py-3.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-600 focus:outline-none input-neon transition-all duration-300 text-sm"
                        >
                        <!-- Toggle Password -->
                        <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 transition-colors">
                            <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Login Button -->
                <div class="slide-up slide-up-delay-5">
                    <button 
                        type="submit" 
                        id="btnLogin"
                        class="btn-glow w-full py-3.5 rounded-xl text-dark-900 font-bold text-sm tracking-wide flex items-center justify-center gap-2"
                    >
                        <span id="btnText">MASUK KE SISTEM</span>
                        <div id="btnSpinner" class="spinner"></div>
                    </button>
                </div>
            </form>

            <!-- Demo Credentials -->
            <div class="mt-6 p-3 bg-neon/5 border border-neon/20 rounded-xl slide-up slide-up-delay-5">
                <p class="text-xs text-gray-500 mb-1 text-center">🔐 Demo Credentials</p>
                <div class="flex justify-center gap-4">
                    <code class="text-xs text-neon/80 bg-neon/10 px-2 py-1 rounded">rafli</code>
                    <code class="text-xs text-cyan/80 bg-cyan/10 px-2 py-1 rounded">07052003</code>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <p class="text-center text-gray-600 text-xs mt-6 slide-up slide-up-delay-5">
            &copy; 2026 RAFLI_FARM_PRO. All rights reserved.
        </p>
    </div>

    <!-- ===== JAVASCRIPT ===== -->
    <script>
        // ==========================================
        // 1. PARTICLE SYSTEM (Sakura + Rain + Snow)
        // ==========================================
        const canvas = document.getElementById('particleCanvas');
        const ctx = canvas.getContext('2d');
        let particles = [];
        let currentEffect = 'sakura'; // sakura, rain, snow
        let animationId;

        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        // Particle Class
        class Particle {
            constructor(type) {
                this.type = type;
                this.reset();
            }

            reset() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * -canvas.height;
                this.speed = 0;
                this.opacity = 0;
                this.size = 0;
                this.rotation = Math.random() * 360;
                this.rotationSpeed = (Math.random() - 0.5) * 2;
                this.wobbleX = 0;
                this.wobbleSpeed = Math.random() * 0.02;
                this.wobbleOffset = Math.random() * Math.PI * 2;

                if (this.type === 'sakura') {
                    this.speed = 1 + Math.random() * 2;
                    this.size = 8 + Math.random() * 10;
                    this.opacity = 0.4 + Math.random() * 0.4;
                    this.color = Math.random() > 0.5 ? '#FFB7D5' : '#FF69B4';
                } else if (this.type === 'rain') {
                    this.speed = 8 + Math.random() * 8;
                    this.size = 1 + Math.random() * 1.5;
                    this.opacity = 0.2 + Math.random() * 0.3;
                    this.length = 15 + Math.random() * 20;
                    this.color = '#00E5FF';
                } else if (this.type === 'snow') {
                    this.speed = 0.5 + Math.random() * 1.5;
                    this.size = 2 + Math.random() * 4;
                    this.opacity = 0.4 + Math.random() * 0.5;
                    this.color = '#FFFFFF';
                }
            }

            update() {
                this.y += this.speed;
                this.rotation += this.rotationSpeed;
                this.wobbleX += this.wobbleSpeed;

                if (this.type === 'sakura') {
                    this.x += Math.sin(this.wobbleX + this.wobbleOffset) * 1.5;
                } else if (this.type === 'snow') {
                    this.x += Math.sin(this.wobbleX + this.wobbleOffset) * 0.8;
                } else if (this.type === 'rain') {
                    this.x += -1; // Angin sedikit
                }

                if (this.y > canvas.height + 20) {
                    this.reset();
                    this.y = -20;
                }
            }

            draw() {
                ctx.save();
                ctx.globalAlpha = this.opacity;

                if (this.type === 'sakura') {
                    ctx.translate(this.x, this.y);
                    ctx.rotate((this.rotation * Math.PI) / 180);
                    
                    // Draw petal shape
                    ctx.beginPath();
                    ctx.moveTo(0, 0);
                    ctx.bezierCurveTo(
                        this.size / 2, -this.size / 2,
                        this.size, 0,
                        0, this.size
                    );
                    ctx.bezierCurveTo(
                        -this.size, 0,
                        -this.size / 2, -this.size / 2,
                        0, 0
                    );
                    ctx.fillStyle = this.color;
                    ctx.fill();
                    
                    // Glow effect
                    ctx.shadowColor = this.color;
                    ctx.shadowBlur = 10;
                    ctx.fill();

                } else if (this.type === 'rain') {
                    ctx.beginPath();
                    ctx.moveTo(this.x, this.y);
                    ctx.lineTo(this.x + 1, this.y + this.length);
                    ctx.strokeStyle = this.color;
                    ctx.lineWidth = this.size;
                    ctx.lineCap = 'round';
                    ctx.stroke();

                } else if (this.type === 'snow') {
                    ctx.beginPath();
                    ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                    ctx.fillStyle = this.color;
                    ctx.shadowColor = '#FFFFFF';
                    ctx.shadowBlur = 15;
                    ctx.fill();
                }

                ctx.restore();
            }
        }

        // Initialize particles
        function initParticles(type, count) {
            particles = [];
            currentEffect = type;
            for (let i = 0; i < count; i++) {
                const p = new Particle(type);
                p.y = Math.random() * canvas.height; // Spread across screen
                particles.push(p);
            }
        }

        // Animation loop
        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            particles.forEach(p => {
                p.update();
                p.draw();
            });
            animationId = requestAnimationFrame(animate);
        }

        // Start with Sakura effect
        initParticles('sakura', 40);
        animate();

        // ==========================================
        // 2. TOGGLE PASSWORD VISIBILITY
        // ==========================================
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
            }
        }

        // ==========================================
        // 3. FORM SUBMIT ANIMATION
        // ==========================================
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('btnLogin');
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');
            
            btn.disabled = true;
            btnText.textContent = 'Memverifikasi...';
            btnSpinner.style.display = 'block';
            btn.style.opacity = '0.8';
        });

        // ==========================================
        // 4. SHAKE ON ERROR
        // ==========================================
        const errorMsg = document.getElementById('errorMsg');
        if (errorMsg) {
            const card = errorMsg.closest('.glass-card');
            card.classList.add('shake');
            setTimeout(() => card.classList.remove('shake'), 500);
        }

        // ==========================================
        // 5. KEYBOARD SHORTCUT (Effect Switcher)
        // ==========================================
        document.addEventListener('keydown', function(e) {
            if (e.key === '1') initParticles('sakura', 40);
            if (e.key === '2') initParticles('rain', 150);
            if (e.key === '3') initParticles('snow', 80);
        });
    </script>

</body>
</html>