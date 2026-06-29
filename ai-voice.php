<?php
session_start();
include 'koneksi.php';
include 'track_visitor.php';
if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }

// ==========================================
// LOGIKA BACKEND
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'save') {
        $query = mysqli_real_escape_string($koneksi, $_POST['user_query']);
        $response = mysqli_real_escape_string($koneksi, $_POST['ai_response']);
        $intent = mysqli_real_escape_string($koneksi, $_POST['intent']);
        $is_consultation = (int)$_POST['is_consultation'];
        $komoditas = mysqli_real_escape_string($koneksi, $_POST['komoditas']);

        $sql = "INSERT INTO voice_interactions (user_query, ai_response, intent, is_consultation, komoditas) 
                VALUES ('$query', '$response', '$intent', $is_consultation, '$komoditas')";
        $koneksi->query($sql);
        echo "OK";
        exit;
    }

    if ($action === 'clear') {
        $koneksi->query("DELETE FROM voice_interactions");
        header("Location: ai-voice.php?cleared=1");
        exit;
    }
}

$komoditas_list = $koneksi->query("SELECT komoditas, emoji, kategori FROM farming_knowledge ORDER BY komoditas");
$history = $koneksi->query("SELECT * FROM voice_interactions ORDER BY id DESC LIMIT 20");
$nama_user = $_SESSION['nama_lengkap'] ?? 'Rafli';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konsultan Pertanian AI - RAFLI_FARM_PRO</title>
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
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .gradient-text { background: linear-gradient(135deg, #00FF88, #00E5FF); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        
        .mic-pulse { animation: micPulse 1.5s infinite; }
        @keyframes micPulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 51, 102, 0.7); }
            70% { box-shadow: 0 0 0 20px rgba(255, 51, 102, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 51, 102, 0); }
        }

        .typing-dot { animation: typing 1.4s infinite ease-in-out both; }
        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }
        @keyframes typing {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #00FF88; border-radius: 10px; }
        
        .chat-bubble { animation: slideUp 0.3s ease-out forwards; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .consult-card {
            background: linear-gradient(135deg, rgba(0,255,136,0.05) 0%, rgba(0,229,255,0.05) 100%);
            border: 1px solid rgba(0,255,136,0.2);
            border-radius: 16px;
            overflow: hidden;
        }
        .consult-tab {
            transition: all 0.3s;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }
        .consult-tab.active {
            border-bottom-color: #00FF88;
            color: #00FF88;
            background: rgba(0,255,136,0.1);
        }
        .consult-content { display: none; animation: fadeIn 0.3s; }
        .consult-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .autocomplete-list {
            position: absolute;
            bottom: 100%;
            left: 0;
            right: 0;
            background: rgba(10,10,10,0.98);
            border: 1px solid rgba(0,255,136,0.3);
            border-radius: 12px;
            max-height: 250px;
            overflow-y: auto;
            margin-bottom: 8px;
            display: none;
            backdrop-filter: blur(20px);
        }
        .autocomplete-item {
            padding: 10px 16px;
            cursor: pointer;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            transition: all 0.2s;
        }
        .autocomplete-item:hover {
            background: rgba(0,255,136,0.1);
            color: #00FF88;
        }

        .sub-tab {
            transition: all 0.2s;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        .sub-tab.active {
            background: rgba(0,255,136,0.2);
            color: #00FF88;
        }
        .sub-tab:not(.active) {
            background: rgba(255,255,255,0.05);
            color: #888;
        }
        .sub-tab:hover:not(.active) {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        .sub-content { display: none; animation: fadeIn 0.3s; }
        .sub-content.active { display: block; }

        /* ===== ULTIMATE VOICE SYSTEM STYLES ===== */
        
        /* Voice Indicator - Floating */
        .voice-indicator {
            position: fixed;
            top: 80px;
            right: 20px;
            background: rgba(10,10,10,0.95);
            border: 2px solid rgba(0,255,136,0.5);
            border-radius: 16px;
            padding: 16px 20px;
            z-index: 9999;
            display: none;
            align-items: center;
            gap: 12px;
            box-shadow: 0 10px 40px rgba(0,255,136,0.3);
            backdrop-filter: blur(20px);
            animation: slideInRight 0.3s ease-out;
        }
        .voice-indicator.active { display: flex; }
        @keyframes slideInRight {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Voice Avatar */
        .voice-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #00FF88, #00E5FF);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            position: relative;
        }
        .voice-avatar.speaking {
            animation: voicePulse 0.6s ease-in-out infinite;
        }
        @keyframes voicePulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 20px rgba(0,255,136,0.5); }
            50% { transform: scale(1.1); box-shadow: 0 0 40px rgba(0,255,136,0.8); }
        }

        /* Audio Waveform */
        .voice-waveform {
            display: flex;
            align-items: center;
            gap: 3px;
            height: 30px;
        }
        .wave-bar {
            width: 3px;
            background: linear-gradient(to top, #00FF88, #00E5FF);
            border-radius: 2px;
            height: 5px;
            transition: height 0.1s ease;
        }
        .voice-avatar.speaking + .voice-info .wave-bar {
            animation: waveAnim 0.5s ease-in-out infinite;
        }
        .wave-bar:nth-child(1) { animation-delay: 0s; }
        .wave-bar:nth-child(2) { animation-delay: 0.1s; }
        .wave-bar:nth-child(3) { animation-delay: 0.2s; }
        .wave-bar:nth-child(4) { animation-delay: 0.3s; }
        .wave-bar:nth-child(5) { animation-delay: 0.4s; }
        .wave-bar:nth-child(6) { animation-delay: 0.3s; }
        .wave-bar:nth-child(7) { animation-delay: 0.2s; }
        .wave-bar:nth-child(8) { animation-delay: 0.1s; }
        .wave-bar:nth-child(9) { animation-delay: 0s; }
        @keyframes waveAnim {
            0%, 100% { height: 5px; }
            50% { height: 25px; }
        }

        /* Voice Controls */
        .voice-controls {
            display: flex;
            gap: 8px;
            margin-left: 12px;
        }
        .voice-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 1px solid rgba(0,255,136,0.3);
            background: rgba(0,255,136,0.1);
            color: #00FF88;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .voice-btn:hover {
            background: rgba(0,255,136,0.2);
            transform: scale(1.1);
        }
        .voice-btn.active {
            background: #00FF88;
            color: #000;
        }

        /* Voice Settings Panel */
        .voice-settings {
            position: fixed;
            top: 80px;
            right: 20px;
            background: rgba(10,10,10,0.98);
            border: 2px solid rgba(0,255,136,0.3);
            border-radius: 16px;
            padding: 20px;
            z-index: 9998;
            width: 320px;
            display: none;
            backdrop-filter: blur(20px);
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        .voice-settings.active { display: block; animation: fadeIn 0.3s ease-out; }

        .setting-group {
            margin-bottom: 16px;
        }
        .setting-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
            color: #888;
        }
        .setting-value {
            color: #00FF88;
            font-weight: bold;
        }
        .setting-slider {
            width: 100%;
            height: 6px;
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
            outline: none;
            -webkit-appearance: none;
        }
        .setting-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            background: #00FF88;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 0 10px rgba(0,255,136,0.5);
        }

        /* Transcript Sync */
        .transcript-sync {
            background: rgba(0,255,136,0.05);
            border: 1px solid rgba(0,255,136,0.2);
            border-radius: 12px;
            padding: 12px;
            margin-top: 12px;
            max-height: 150px;
            overflow-y: auto;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            line-height: 1.6;
        }
        .transcript-word {
            color: #888;
            transition: all 0.2s;
        }
        .transcript-word.active {
            color: #00FF88;
            font-weight: bold;
            text-shadow: 0 0 10px rgba(0,255,136,0.5);
        }
        .transcript-word.spoken {
            color: #fff;
        }

        /* Response Actions */
        .response-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .action-btn {
            padding: 6px 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 6px;
            color: #888;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .action-btn:hover {
            background: rgba(0,255,136,0.1);
            border-color: rgba(0,255,136,0.3);
            color: #00FF88;
        }

        /* Keyboard Shortcut Badge */
        .kbd {
            display: inline-block;
            padding: 2px 6px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 4px;
            font-size: 10px;
            font-family: 'JetBrains Mono', monospace;
            color: #888;
        }

        /* Voice Queue Indicator */
        .queue-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #FF3366;
            color: #fff;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body class="min-h-screen flex relative h-screen overflow-hidden">

    <canvas id="weatherCanvas" class="fixed inset-0 z-0 pointer-events-none"></canvas>

    <!-- Sidebar -->
    <aside class="w-64 bg-dark-800/90 backdrop-blur-xl border-r border-white/5 flex flex-col h-screen fixed z-40 hidden lg:flex">
        <div class="p-6 border-b border-white/5"><h1 class="text-xl font-bold gradient-text">RAFLI_FARM</h1></div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">Dashboard</a>
            <a href="tanaman.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">Tanaman</a>
            <a href="gudang.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">Gudang</a>
            <a href="penjualan.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">Penjualan</a>
            <a href="ai-deteksi.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">AI Deteksi</a>
            <a href="ai-voice.php" class="flex items-center gap-3 px-4 py-3 bg-neon/10 text-neon border-l-4 border-neon rounded-r-xl font-bold">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>
                Konsultan AI
            </a>
            <a href="kalkulator.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">Kalkulator Pupuk</a>
        </nav>
        <div class="p-4 border-t border-white/5"><a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-danger hover:bg-danger/10 rounded-xl">Keluar</a></div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 lg:ml-64 relative z-10 flex flex-col h-full">
        
        <!-- Header -->
        <header class="glass border-b border-white/5 px-6 py-4 flex items-center justify-between flex-shrink-0">
            <div>
                <h1 class="text-2xl font-bold gradient-text">🌱 Konsultan Pertanian AI</h1>
                <p class="text-xs text-gray-500">Panduan lengkap + <b class="text-neon">Text-to-Speech Otomatis</b></p>
            </div>
            <div class="flex items-center gap-3">
                <!-- Voice Settings Toggle -->
                <button onclick="toggleVoiceSettings()" class="px-4 py-2 bg-white/5 border border-white/10 text-gray-400 rounded-lg hover:bg-neon/10 hover:text-neon hover:border-neon/30 transition-all text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    Pengaturan Suara
                </button>
                <!-- Clear History -->
                <form method="POST" onsubmit="return confirm('Hapus semua riwayat percakapan?')">
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="px-4 py-2 bg-white/5 border border-white/10 text-gray-400 rounded-lg hover:bg-danger/10 hover:text-danger hover:border-danger/30 transition-all text-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        Hapus Riwayat
                    </button>
                </form>
            </div>
        </header>

        <!-- Chat Container -->
        <main id="chatContainer" class="flex-1 overflow-y-auto p-6 space-y-6 scroll-smooth">
            
            <!-- Welcome Message -->
            <div class="flex gap-4 chat-bubble">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-neon to-cyan flex items-center justify-center flex-shrink-0">
                    <span class="text-xl">🌱</span>
                </div>
                <div class="glass rounded-2xl rounded-tl-none p-5 max-w-3xl">
                    <p class="text-gray-200 mb-3">Halo Pak <?php echo explode(' ', $nama_user)[0]; ?>! 👋 Saya <span class="text-neon font-bold">Konsultan Pertanian AI</span> dengan fitur <b class="text-cyan">Text-to-Speech</b>.</p>
                    <p class="text-gray-400 text-sm mb-3">Sekarang saya bisa bantu dengan <b class="text-neon">31 komoditas</b> lengkap dan <b class="text-cyan">membacakan jawaban secara otomatis</b>!</p>
                    
                    <div class="p-3 bg-gradient-to-r from-warning/10 to-danger/10 border border-warning/20 rounded-xl mb-4">
                        <p class="text-xs text-warning font-bold mb-1">🔊 FITUR BARU: AUTO VOICE</p>
                        <p class="text-xs text-gray-300">Setiap jawaban AI akan <b class="text-white">dibacakan otomatis</b> dengan suara natural Indonesia. Anda bisa pause, stop, atau atur kecepatan suara!</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <button onclick="quickAsk('hama pada cabai dan cara mengatasinya')" class="text-left p-3 bg-white/5 hover:bg-warning/10 border border-white/10 hover:border-warning/30 rounded-lg transition-all text-sm">
                            🐛 <b>Hama cabai + pencegahan</b>
                        </button>
                        <button onclick="quickAsk('penyakit pada tomat')" class="text-left p-3 bg-white/5 hover:bg-danger/10 border border-white/10 hover:border-danger/30 rounded-lg transition-all text-sm">
                            🦠 <b>Penyakit tomat + solusi</b>
                        </button>
                        <button onclick="quickAsk('cara tanam tembakau')" class="text-left p-3 bg-white/5 hover:bg-neon/10 border border-white/10 hover:border-neon/30 rounded-lg transition-all text-sm">
                            🌿 <b>Cara tanam tembakau</b>
                        </button>
                        <button onclick="quickAsk('hama pada padi')" class="text-left p-3 bg-white/5 hover:bg-warning/10 border border-white/10 hover:border-warning/30 rounded-lg transition-all text-sm">
                            🌾 <b>Hama padi + solusi</b>
                        </button>
                        <button onclick="quickAsk('penyakit pada bawang merah')" class="text-left p-3 bg-white/5 hover:bg-danger/10 border border-white/10 hover:border-danger/30 rounded-lg transition-all text-sm">
                            🧅 <b>Penyakit bawang merah</b>
                        </button>
                        <button onclick="quickAsk('semua tentang melon')" class="text-left p-3 bg-white/5 hover:bg-cyan/10 border border-white/10 hover:border-cyan/30 rounded-lg transition-all text-sm">
                            🍈 <b>Panduan lengkap melon</b>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Load History -->
            <?php if($history->num_rows > 0): ?>
                <?php while($row = $history->fetch_assoc()): ?>
                    <div class="flex gap-4 justify-end chat-bubble">
                        <div class="bg-neon/10 border border-neon/20 rounded-2xl rounded-tr-none p-4 max-w-2xl">
                            <p class="text-white"><?php echo htmlspecialchars($row['user_query']); ?></p>
                            <p class="text-[10px] text-neon/60 mt-1 text-right"><?php echo date('H:i', strtotime($row['created_at'])); ?></p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        </div>
                    </div>
                    <div class="flex gap-4 chat-bubble">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-neon to-cyan flex items-center justify-center flex-shrink-0">
                            <span class="text-xl">🌱</span>
                        </div>
                        <div class="glass rounded-2xl rounded-tl-none p-4 max-w-3xl">
                            <?php if($row['is_consultation'] && $row['komoditas']): ?>
                                <div class="consultation-placeholder" data-komoditas="<?php echo htmlspecialchars($row['komoditas']); ?>"></div>
                            <?php else: ?>
                                <p class="text-gray-200"><?php echo nl2br(htmlspecialchars($row['ai_response'])); ?></p>
                            <?php endif; ?>
                            <p class="text-[10px] text-gray-500 mt-2">Intent: <span class="text-cyan font-mono"><?php echo $row['intent']; ?></span></p>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>

            <!-- Typing Indicator -->
            <div id="typingIndicator" class="hidden flex gap-4">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-neon to-cyan flex items-center justify-center flex-shrink-0">
                    <span class="text-xl">🌱</span>
                </div>
                <div class="glass rounded-2xl rounded-tl-none p-4 flex items-center gap-1">
                    <div class="w-2 h-2 bg-gray-400 rounded-full typing-dot"></div>
                    <div class="w-2 h-2 bg-gray-400 rounded-full typing-dot"></div>
                    <div class="w-2 h-2 bg-gray-400 rounded-full typing-dot"></div>
                </div>
            </div>
        </main>

        <!-- Input Area -->
        <div class="glass border-t border-white/5 p-4 flex-shrink-0">
            <div class="max-w-4xl mx-auto">
                <div class="flex flex-wrap gap-2 mb-3">
                    <button onclick="setPrefix('cara tanam ')" class="px-3 py-1 text-xs bg-white/5 hover:bg-neon/10 border border-white/10 hover:border-neon/30 rounded-full">🌱 Cara Tanam</button>
                    <button onclick="setPrefix('penyiraman ')" class="px-3 py-1 text-xs bg-white/5 hover:bg-cyan/10 border border-white/10 hover:border-cyan/30 rounded-full">💧 Penyiraman</button>
                    <button onclick="setPrefix('hama pada ')" class="px-3 py-1 text-xs bg-white/5 hover:bg-warning/10 border border-white/10 hover:border-warning/30 rounded-full">🐛 Hama</button>
                    <button onclick="setPrefix('penyakit pada ')" class="px-3 py-1 text-xs bg-white/5 hover:bg-danger/10 border border-white/10 hover:border-danger/30 rounded-full">🦠 Penyakit</button>
                    <button onclick="setPrefix('pupuk untuk ')" class="px-3 py-1 text-xs bg-white/5 hover:bg-purple-500/10 border border-white/10 hover:border-purple-500/30 rounded-full">🧪 Pupuk</button>
                    <button onclick="setPrefix('kapan panen ')" class="px-3 py-1 text-xs bg-white/5 hover:bg-neon/10 border border-white/10 hover:border-neon/30 rounded-full">🌾 Panen</button>
                </div>

                <div class="flex items-end gap-3 relative">
                    <div id="autocompleteList" class="autocomplete-list"></div>

                    <button id="micBtn" onclick="toggleMic()" class="w-12 h-12 rounded-full bg-white/5 border border-white/10 text-gray-400 hover:text-white hover:bg-white/10 transition-all flex items-center justify-center flex-shrink-0">
                        <svg id="micIcon" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" /></svg>
                    </button>

                    <div class="flex-1 relative">
                        <textarea id="chatInput" rows="1" placeholder="Tanya: 'hama pada padi', 'penyakit tomat', dll..." class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-neon resize-none overflow-hidden" oninput="autoResize(this); showAutocomplete(this.value)" onkeydown="handleEnter(event)"></textarea>
                    </div>

                    <button onclick="sendMessage()" id="sendBtn" class="w-12 h-12 rounded-xl bg-neon text-dark-900 hover:shadow-lg hover:shadow-neon/30 transition-all flex items-center justify-center flex-shrink-0 disabled:opacity-50">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                    </button>
                </div>
                <p class="text-center text-[10px] text-gray-600 mt-2">31 komoditas termasuk <b>🌿 Tembakau</b> • 🔊 <b>Auto Voice</b> untuk setiap jawaban • Tekan <span class="kbd">Ctrl+Shift+V</span> untuk toggle suara</p>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- VOICE INDICATOR (Floating) -->
    <!-- ========================================== -->
    <div id="voiceIndicator" class="voice-indicator">
        <div class="voice-avatar" id="voiceAvatar">
            🤖
            <span class="queue-badge" id="queueBadge" style="display:none;">0</span>
        </div>
        <div class="voice-info">
            <p class="text-xs text-gray-400 mb-1">AI sedang berbicara...</p>
            <div class="voice-waveform">
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
            </div>
        </div>
        <div class="voice-controls">
            <button class="voice-btn" onclick="pauseVoice()" title="Pause/Resume (Space)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </button>
            <button class="voice-btn" onclick="stopVoice()" title="Stop (Esc)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" /></svg>
            </button>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- VOICE SETTINGS PANEL -->
    <!-- ========================================== -->
    <div id="voiceSettings" class="voice-settings">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold gradient-text">🔊 Pengaturan Suara AI</h3>
            <button onclick="toggleVoiceSettings()" class="text-gray-400 hover:text-white">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <!-- Auto Voice Toggle -->
        <div class="setting-group">
            <label class="flex items-center justify-between cursor-pointer">
                <span class="text-sm text-gray-300">🔊 Auto Voice (Baca Otomatis)</span>
                <input type="checkbox" id="autoVoiceToggle" checked onchange="toggleAutoVoice()" class="w-5 h-5 accent-[#00FF88]">
            </label>
            <p class="text-[10px] text-gray-500 mt-1">AI akan otomatis membacakan setiap jawaban</p>
        </div>

        <!-- Voice Speed -->
        <div class="setting-group">
            <div class="setting-label">
                <span>⚡ Kecepatan Suara</span>
                <span class="setting-value" id="speedValue">1.0x</span>
            </div>
            <input type="range" id="voiceSpeed" min="0.5" max="2" step="0.1" value="1" class="setting-slider" oninput="updateVoiceSpeed(this.value)">
            <p class="text-[10px] text-gray-500 mt-1">0.5x (lambat) → 2.0x (cepat)</p>
        </div>

        <!-- Voice Pitch -->
        <div class="setting-group">
            <div class="setting-label">
                <span>🎵 Pitch Suara</span>
                <span class="setting-value" id="pitchValue">1.0</span>
            </div>
            <input type="range" id="voicePitch" min="0.5" max="2" step="0.1" value="1" class="setting-slider" oninput="updateVoicePitch(this.value)">
            <p class="text-[10px] text-gray-500 mt-1">0.5 (rendah) → 2.0 (tinggi)</p>
        </div>

        <!-- Voice Volume -->
        <div class="setting-group">
            <div class="setting-label">
                <span>🔉 Volume</span>
                <span class="setting-value" id="volumeValue">100%</span>
            </div>
            <input type="range" id="voiceVolume" min="0" max="1" step="0.1" value="1" class="setting-slider" oninput="updateVoiceVolume(this.value)">
        </div>

        <!-- Voice Selection -->
        <div class="setting-group">
            <div class="setting-label">
                <span>🗣️ Pilih Voice</span>
            </div>
            <select id="voiceSelect" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none" onchange="updateVoiceSelection()">
                <option value="">Memuat voices...</option>
            </select>
            <p class="text-[10px] text-gray-500 mt-1">Pilih voice Indonesia jika tersedia</p>
        </div>

        <!-- Keyboard Shortcuts -->
        <div class="setting-group pt-4 border-t border-white/10">
            <p class="text-xs text-gray-400 font-bold mb-2">⌨️ Keyboard Shortcuts</p>
            <div class="space-y-1 text-[10px] text-gray-500">
                <div class="flex justify-between"><span>Toggle Auto Voice</span><span class="kbd">Ctrl+Shift+V</span></div>
                <div class="flex justify-between"><span>Pause/Resume</span><span class="kbd">Space</span></div>
                <div class="flex justify-between"><span>Stop Voice</span><span class="kbd">Esc</span></div>
                <div class="flex justify-between"><span>Replay Last</span><span class="kbd">Ctrl+R</span></div>
            </div>
        </div>
    </div>

    <!-- Knowledge Database -->
    <script>
        const farmingDB = {
            <?php 
            $komoditas_data = $koneksi->query("SELECT * FROM farming_knowledge");
            $first = true;
            while($k = $komoditas_data->fetch_assoc()):
                if(!$first) echo ",";
                $first = false;
            ?>
            "<?php echo strtolower($k['komoditas']); ?>": {
                nama: "<?php echo $k['komoditas']; ?>",
                emoji: "<?php echo $k['emoji']; ?>",
                kategori: "<?php echo $k['kategori']; ?>",
                cara_tanam: `<?php echo addslashes($k['cara_tanam']); ?>`,
                waktu_tanam: "<?php echo addslashes($k['waktu_tanam']); ?>",
                penyiraman: `<?php echo addslashes($k['penyiraman']); ?>`,
                pupuk: `<?php echo addslashes($k['pupuk']); ?>`,
                hama: `<?php echo addslashes($k['hama']); ?>`,
                hama_pencegahan: `<?php echo addslashes($k['hama_pencegahan']); ?>`,
                penyakit: `<?php echo addslashes($k['penyakit']); ?>`,
                penyakit_pencegahan: `<?php echo addslashes($k['penyakit_pencegahan']); ?>`,
                panen: `<?php echo addslashes($k['panen']); ?>`,
                tips: `<?php echo addslashes($k['tips_khusus']); ?>`,
                suhu: "<?php echo $k['suhu_ideal']; ?>",
                ph: "<?php echo $k['ph_tanah']; ?>"
            }<?php endwhile; ?>
        };

        const komoditasNames = Object.keys(farmingDB).map(k => farmingDB[k].emoji + ' ' + farmingDB[k].nama);

        function showAutocomplete(value) {
            const list = document.getElementById('autocompleteList');
            if (!value || value.length < 2) { list.style.display = 'none'; return; }
            const matches = komoditasNames.filter(k => k.toLowerCase().includes(value.toLowerCase())).slice(0, 6);
            if (matches.length === 0) { list.style.display = 'none'; return; }
            list.innerHTML = matches.map(m => `<div class="autocomplete-item" onclick="selectKomoditas('${m}')">${m}</div>`).join('');
            list.style.display = 'block';
        }

        function selectKomoditas(name) {
            document.getElementById('chatInput').value = name;
            document.getElementById('autocompleteList').style.display = 'none';
            document.getElementById('chatInput').focus();
        }

        function setPrefix(prefix) {
            document.getElementById('chatInput').value = prefix;
            document.getElementById('chatInput').focus();
        }

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#chatInput') && !e.target.closest('#autocompleteList')) {
                document.getElementById('autocompleteList').style.display = 'none';
            }
        });
    </script>

    <!-- Main Logic -->
    <script>
        // ==========================================
        // ULTIMATE VOICE SYSTEM
        // ==========================================
        
        // Voice Configuration
        const voiceConfig = {
            autoVoice: true,
            speed: 1.0,
            pitch: 1.0,
            volume: 1.0,
            selectedVoice: null,
            queue: [],
            isSpeaking: false,
            isPaused: false,
            currentUtterance: null,
            lastResponse: null
        };

        // Speech Synthesis Setup
        let speechSynthesis = window.speechSynthesis;
        let availableVoices = [];

        // Load voices
        function loadVoices() {
            availableVoices = speechSynthesis.getVoices();
            const voiceSelect = document.getElementById('voiceSelect');
            voiceSelect.innerHTML = '';
            
            // Prioritize Indonesian voices
            const idVoices = availableVoices.filter(v => v.lang.includes('id') || v.lang.includes('ID'));
            const otherVoices = availableVoices.filter(v => !v.lang.includes('id') && !v.lang.includes('ID'));
            
            if (idVoices.length > 0) {
                const optgroup = document.createElement('optgroup');
                optgroup.label = '🇮🇩 Bahasa Indonesia';
                idVoices.forEach((voice, i) => {
                    const option = document.createElement('option');
                    option.value = voice.name;
                    option.textContent = `${voice.name} (${voice.lang})`;
                    if (i === 0 && !voiceConfig.selectedVoice) {
                        option.selected = true;
                        voiceConfig.selectedVoice = voice;
                    }
                    optgroup.appendChild(option);
                });
                voiceSelect.appendChild(optgroup);
            }
            
            if (otherVoices.length > 0) {
                const optgroup = document.createElement('optgroup');
                optgroup.label = '🌍 Bahasa Lain';
                otherVoices.forEach(voice => {
                    const option = document.createElement('option');
                    option.value = voice.name;
                    option.textContent = `${voice.name} (${voice.lang})`;
                    optgroup.appendChild(option);
                });
                voiceSelect.appendChild(optgroup);
            }
        }

        speechSynthesis.onvoiceschanged = loadVoices;
        loadVoices();

        // ==========================================
        // VOICE CONTROL FUNCTIONS
        // ==========================================

        function toggleAutoVoice() {
            voiceConfig.autoVoice = document.getElementById('autoVoiceToggle').checked;
            console.log('Auto Voice:', voiceConfig.autoVoice ? 'ON' : 'OFF');
        }

        function updateVoiceSpeed(value) {
            voiceConfig.speed = parseFloat(value);
            document.getElementById('speedValue').textContent = value + 'x';
            console.log('Voice Speed:', value);
        }

        function updateVoicePitch(value) {
            voiceConfig.pitch = parseFloat(value);
            document.getElementById('pitchValue').textContent = value;
            console.log('Voice Pitch:', value);
        }

        function updateVoiceVolume(value) {
            voiceConfig.volume = parseFloat(value);
            document.getElementById('volumeValue').textContent = Math.round(value * 100) + '%';
            console.log('Voice Volume:', Math.round(value * 100) + '%');
        }

        function updateVoiceSelection() {
            const selectedName = document.getElementById('voiceSelect').value;
            voiceConfig.selectedVoice = availableVoices.find(v => v.name === selectedName);
            console.log('Selected Voice:', voiceConfig.selectedVoice?.name || 'Default');
        }

        function toggleVoiceSettings() {
            const panel = document.getElementById('voiceSettings');
            panel.classList.toggle('active');
        }

        // ==========================================
        // SPEAK FUNCTION (ULTIMATE)
        // ==========================================

        function speakText(text, options = {}) {
            if (!voiceConfig.autoVoice && !options.force) {
                console.log('Auto voice disabled, skipping...');
                return;
            }

            // Clean text (remove HTML, emojis for better TTS)
            let cleanText = text.replace(/<[^>]*>/g, '');
            cleanText = cleanText.replace(/[\u{1F300}-\u{1F9FF}]/gu, '');
            cleanText = cleanText.replace(/\s+/g, ' ').trim();

            if (!cleanText || cleanText.length < 3) return;

            // Add to queue
            voiceConfig.queue.push({ text: cleanText, options });
            updateQueueBadge();

            // If not currently speaking, start
            if (!voiceConfig.isSpeaking) {
                processVoiceQueue();
            }
        }

        function processVoiceQueue() {
            if (voiceConfig.queue.length === 0) {
                voiceConfig.isSpeaking = false;
                hideVoiceIndicator();
                return;
            }

            voiceConfig.isSpeaking = true;
            const { text, options } = voiceConfig.queue.shift();
            updateQueueBadge();

            // Show voice indicator
            showVoiceIndicator();

            // Create utterance
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.rate = voiceConfig.speed;
            utterance.pitch = voiceConfig.pitch;
            utterance.volume = voiceConfig.volume;
            
            if (voiceConfig.selectedVoice) {
                utterance.voice = voiceConfig.selectedVoice;
            } else {
                utterance.lang = 'id-ID';
            }

            voiceConfig.currentUtterance = utterance;
            voiceConfig.lastResponse = text;

            // Event handlers
            utterance.onstart = () => {
                console.log('Voice started');
                document.getElementById('voiceAvatar').classList.add('speaking');
                if (options.onStart) options.onStart();
            };

            utterance.onend = () => {
                console.log('Voice ended');
                document.getElementById('voiceAvatar').classList.remove('speaking');
                if (options.onEnd) options.onEnd();
                
                // Process next in queue
                setTimeout(() => processVoiceQueue(), 300);
            };

            utterance.onerror = (e) => {
                console.error('Voice error:', e);
                document.getElementById('voiceAvatar').classList.remove('speaking');
                processVoiceQueue();
            };

            // Speak!
            speechSynthesis.speak(utterance);
        }

        function pauseVoice() {
            if (voiceConfig.isPaused) {
                speechSynthesis.resume();
                voiceConfig.isPaused = false;
                document.getElementById('voiceAvatar').classList.add('speaking');
                console.log('Voice resumed');
            } else {
                speechSynthesis.pause();
                voiceConfig.isPaused = true;
                document.getElementById('voiceAvatar').classList.remove('speaking');
                console.log('Voice paused');
            }
        }

        function stopVoice() {
            speechSynthesis.cancel();
            voiceConfig.queue = [];
            voiceConfig.isSpeaking = false;
            voiceConfig.isPaused = false;
            document.getElementById('voiceAvatar').classList.remove('speaking');
            hideVoiceIndicator();
            updateQueueBadge();
            console.log('Voice stopped');
        }

        function replayLast() {
            if (voiceConfig.lastResponse) {
                speakText(voiceConfig.lastResponse, { force: true });
            }
        }

        // ==========================================
        // UI HELPERS
        // ==========================================

        function showVoiceIndicator() {
            document.getElementById('voiceIndicator').classList.add('active');
        }

        function hideVoiceIndicator() {
            document.getElementById('voiceIndicator').classList.remove('active');
        }

        function updateQueueBadge() {
            const badge = document.getElementById('queueBadge');
            const queueLength = voiceConfig.queue.length;
            if (queueLength > 0) {
                badge.textContent = queueLength;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }

        // ==========================================
        // KEYBOARD SHORTCUTS
        // ==========================================

        document.addEventListener('keydown', (e) => {
            // Ctrl+Shift+V - Toggle Auto Voice
            if (e.ctrlKey && e.shiftKey && e.key === 'V') {
                e.preventDefault();
                const toggle = document.getElementById('autoVoiceToggle');
                toggle.checked = !toggle.checked;
                toggleAutoVoice();
            }
            
            // Space - Pause/Resume (when not in input)
            if (e.code === 'Space' && !e.target.matches('input, textarea, select')) {
                e.preventDefault();
                if (voiceConfig.isSpeaking) pauseVoice();
            }
            
            // Escape - Stop Voice
            if (e.key === 'Escape') {
                stopVoice();
            }
            
            // Ctrl+R - Replay Last
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                replayLast();
            }
        });

        // ==========================================
        // SPEECH RECOGNITION (Mic Input)
        // ==========================================
        let recognition;
        let isListening = false;
        const micBtn = document.getElementById('micBtn');
        const micIcon = document.getElementById('micIcon');
        const chatInput = document.getElementById('chatInput');

        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            recognition = new SpeechRecognition();
            recognition.lang = 'id-ID';
            recognition.continuous = false;
            recognition.interimResults = false;

            recognition.onstart = function() {
                isListening = true;
                micBtn.classList.add('bg-danger', 'border-danger', 'text-white', 'mic-pulse');
                micBtn.classList.remove('bg-white/5', 'border-white/10', 'text-gray-400');
                micIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />';
            };

            recognition.onend = function() {
                isListening = false;
                micBtn.classList.remove('bg-danger', 'border-danger', 'text-white', 'mic-pulse');
                micBtn.classList.add('bg-white/5', 'border-white/10', 'text-gray-400');
                micIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />';
            };

            recognition.onresult = function(event) {
                chatInput.value = event.results[0][0].transcript;
                autoResize(chatInput);
            };

            recognition.onerror = function(event) { recognition.stop(); };
        } else {
            micBtn.disabled = true;
        }

        function toggleMic() {
            if (isListening) recognition.stop();
            else recognition.start();
        }

        // ==========================================
        // CHAT FUNCTIONS
        // ==========================================

        function autoResize(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        }

        function handleEnter(e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
        }

        function quickAsk(question) {
            chatInput.value = question;
            sendMessage();
        }

        function sendMessage() {
            const text = chatInput.value.trim();
            if (!text) return;

            appendMessage('user', text);
            chatInput.value = '';
            autoResize(chatInput);
            document.getElementById('autocompleteList').style.display = 'none';

            const typing = document.getElementById('typingIndicator');
            typing.classList.remove('hidden');
            scrollToBottom();

            setTimeout(() => {
                const response = getAIResponse(text);
                typing.classList.add('hidden');
                appendMessage('ai', response.html, response.intent, response.isConsultation, response.komoditas, response.text);
                saveToDatabase(text, response.text, response.intent, response.isConsultation, response.komoditas);
            }, 1200);
        }

        function appendMessage(role, text, intent = '', isConsultation = 0, komoditas = '', rawText = '') {
            const container = document.getElementById('chatContainer');
            const time = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            
            let html = '';
            if (role === 'user') {
                html = `
                    <div class="flex gap-4 justify-end chat-bubble">
                        <div class="bg-neon/10 border border-neon/20 rounded-2xl rounded-tr-none p-4 max-w-2xl">
                            <p class="text-white">${text}</p>
                            <p class="text-[10px] text-neon/60 mt-1 text-right">${time}</p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        </div>
                    </div>`;
            } else {
                html = `
                    <div class="flex gap-4 chat-bubble">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-neon to-cyan flex items-center justify-center flex-shrink-0">
                            <span class="text-xl">🌱</span>
                        </div>
                        <div class="glass rounded-2xl rounded-tl-none p-4 max-w-3xl w-full">
                            ${text}
                            ${intent ? `<p class="text-[10px] text-gray-500 mt-2">Intent: <span class="text-cyan font-mono">${intent}</span></p>` : ''}
                            <div class="response-actions">
                                <button class="action-btn" onclick="speakText(\`${rawText.replace(/`/g, '\\`').replace(/\$/g, '\\$')}\`, {force: true})">
                                    🔊 Baca Ulang
                                </button>
                                <button class="action-btn" onclick="copyToClipboard(\`${rawText.replace(/`/g, '\\`').replace(/\$/g, '\\$')}\`)">
                                    📋 Copy
                                </button>
                            </div>
                        </div>
                    </div>`;
            }
            
            const typing = document.getElementById('typingIndicator');
            typing.insertAdjacentHTML('beforebegin', html);
            scrollToBottom();

            if (isConsultation && komoditas) {
                setTimeout(() => initConsultationTabs(), 100);
            }

            // AUTO VOICE - Speak the response!
            if (role === 'ai' && rawText) {
                speakText(rawText);
            }
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Teks berhasil disalin!');
            });
        }

        function scrollToBottom() {
            const container = document.getElementById('chatContainer');
            container.scrollTop = container.scrollHeight;
        }

        // ==========================================
        // AI RESPONSE LOGIC (Same as before)
        // ==========================================
        function getAIResponse(query) {
            const q = query.toLowerCase();
            
            let foundKomoditas = null;
            for (let key in farmingDB) {
                if (q.includes(key)) {
                    foundKomoditas = farmingDB[key];
                    break;
                }
            }

            let intent = 'general';
            let specificTopic = null;
            
            if (q.match(/cara tanam|cara menanam|budidaya|menanam|planting/)) {
                intent = 'cara_tanam'; specificTopic = 'cara_tanam';
            } else if (q.match(/penyiraman|siraml?|air|irigasi|watering/)) {
                intent = 'penyiraman'; specificTopic = 'penyiraman';
            } else if (q.match(/hama|serangga|ulat|kutu|wereng|pest|mengusir|mencegah hama|atasi hama/)) {
                intent = 'hama'; specificTopic = 'hama';
            } else if (q.match(/penyakit|jamur|bakteri|virus|layu|busuk|disease|mencegah penyakit|halau penyakit/)) {
                intent = 'penyakit'; specificTopic = 'penyakit';
            } else if (q.match(/pupuk|pemupukan|nutrisi|fertilizer/)) {
                intent = 'pupuk'; specificTopic = 'pupuk';
            } else if (q.match(/panen|memanen|harvest|ciri.?ciri panen/)) {
                intent = 'panen'; specificTopic = 'panen';
            } else if (q.match(/tips|rahasia|rahasia sukses|trik/)) {
                intent = 'tips'; specificTopic = 'tips';
            } else if (q.match(/suhu|temperatur|iklim|cuaca ideal/)) {
                intent = 'suhu'; specificTopic = 'suhu';
            } else if (q.match(/ph|keasaman|tanah/)) {
                intent = 'ph'; specificTopic = 'ph';
            } else if (q.match(/waktu tanam|kapan tanam|musim tanam/)) {
                intent = 'waktu_tanam'; specificTopic = 'waktu_tanam';
            } else if (q.match(/semua|lengkap|detail|profil/)) {
                intent = 'semua'; specificTopic = 'semua';
            }

            if (foundKomoditas) {
                if (specificTopic && specificTopic !== 'semua') {
                    return buildSpecificResponse(foundKomoditas, specificTopic, intent);
                }
                return buildFullConsultationCard(foundKomoditas, intent);
            }

            return buildGeneralResponse(q, intent);
        }

        function buildSpecificResponse(komoditas, topic, intent) {
            let content = '';
            let title = '';
            let icon = '';
            let color = '';
            let preventionContent = '';

            switch(topic) {
                case 'cara_tanam':
                    title = 'Cara Tanam'; icon = '🌱'; color = 'neon';
                    content = komoditas.cara_tanam;
                    break;
                case 'penyiraman':
                    title = 'Panduan Penyiraman'; icon = '💧'; color = 'cyan';
                    content = komoditas.penyiraman;
                    break;
                case 'hama':
                    title = 'Hama + Cara Mencegah & Mengatasi'; 
                    icon = '🐛'; 
                    color = 'warning';
                    content = komoditas.hama;
                    preventionContent = komoditas.hama_pencegahan;
                    break;
                case 'penyakit':
                    title = 'Penyakit + Cara Mencegah & Menghalau'; 
                    icon = '🦠'; 
                    color = 'danger';
                    content = komoditas.penyakit;
                    preventionContent = komoditas.penyakit_pencegahan;
                    break;
                case 'pupuk':
                    title = 'Rekomendasi Pupuk'; icon = '🧪'; color = 'purple';
                    content = komoditas.pupuk;
                    break;
                case 'panen':
                    title = 'Waktu & Ciri Panen'; icon = '🌾'; color = 'neon';
                    content = komoditas.panen;
                    break;
                case 'tips':
                    title = 'Tips Sukses'; icon = '💡'; color = 'cyan';
                    content = komoditas.tips;
                    break;
                case 'suhu':
                    title = 'Suhu Ideal'; icon = '🌡️'; color = 'warning';
                    content = komoditas.suhu;
                    break;
                case 'ph':
                    title = 'pH Tanah Ideal'; icon = '🧪'; color = 'purple';
                    content = komoditas.ph;
                    break;
                case 'waktu_tanam':
                    title = 'Waktu Tanam Terbaik'; icon = '📅'; color = 'cyan';
                    content = komoditas.waktu_tanam;
                    break;
            }

            let html = `
                <div class="mb-3">
                    <p class="text-gray-300 mb-3">Berikut panduan <b class="text-${color}">${title.toLowerCase()}</b> untuk <b class="text-white">${komoditas.emoji} ${komoditas.nama}</b>:</p>
                    <div class="p-4 bg-${color}/5 border border-${color}/20 rounded-xl mb-3">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-2xl">${icon}</span>
                            <h4 class="font-bold text-${color}">${title}</h4>
                        </div>
                        <p class="text-gray-200 text-sm leading-relaxed whitespace-pre-line">${content}</p>
                    </div>`;
            
            if (preventionContent) {
                html += `
                    <div class="p-4 bg-gradient-to-br from-neon/10 to-cyan/10 border border-neon/30 rounded-xl">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-2xl">🛡️</span>
                            <h4 class="font-bold text-neon">Cara Mencegah & Mengendalikan</h4>
                        </div>
                        <p class="text-gray-200 text-sm leading-relaxed whitespace-pre-line">${preventionContent}</p>
                    </div>`;
            }
            
            html += `</div>`;

            const fullText = `${title} ${komoditas.nama}: ${content} ${preventionContent}`;

            return {
                html: html,
                text: fullText,
                intent: intent,
                isConsultation: 0,
                komoditas: komoditas.nama
            };
        }

        function buildFullConsultationCard(komoditas, intent) {
            const cardId = 'consult-' + Date.now();
            
            const html = `
                <div class="mb-3">
                    <p class="text-gray-300 mb-3">Berikut panduan lengkap untuk <b class="text-white">${komoditas.emoji} ${komoditas.nama}</b> (${komoditas.kategori}):</p>
                    
                    <div class="consult-card" id="${cardId}">
                        <div class="p-4 bg-gradient-to-r from-neon/10 to-cyan/10 border-b border-white/10">
                            <div class="flex items-center gap-3">
                                <span class="text-4xl">${komoditas.emoji}</span>
                                <div>
                                    <h3 class="text-lg font-bold text-white">${komoditas.nama}</h3>
                                    <p class="text-xs text-gray-400">${komoditas.kategori} • Suhu ${komoditas.suhu} • pH ${komoditas.ph}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex overflow-x-auto border-b border-white/10 bg-black/30">
                            <div class="consult-tab active px-4 py-3 text-xs font-semibold whitespace-nowrap" data-tab="tanam">🌱 Tanam</div>
                            <div class="consult-tab px-4 py-3 text-xs font-semibold whitespace-nowrap" data-tab="siram">💧 Siram</div>
                            <div class="consult-tab px-4 py-3 text-xs font-semibold whitespace-nowrap" data-tab="pupuk">🧪 Pupuk</div>
                            <div class="consult-tab px-4 py-3 text-xs font-semibold whitespace-nowrap" data-tab="hama">🐛 Hama</div>
                            <div class="consult-tab px-4 py-3 text-xs font-semibold whitespace-nowrap" data-tab="penyakit">🦠 Penyakit</div>
                            <div class="consult-tab px-4 py-3 text-xs font-semibold whitespace-nowrap" data-tab="panen">🌾 Panen</div>
                            <div class="consult-tab px-4 py-3 text-xs font-semibold whitespace-nowrap" data-tab="tips">💡 Tips</div>
                        </div>

                        <div class="p-4">
                            <div class="consult-content active" data-content="tanam">
                                <p class="text-xs text-cyan font-bold mb-2">📅 Waktu Tanam:</p>
                                <p class="text-sm text-gray-300 mb-3">${komoditas.waktu_tanam}</p>
                                <p class="text-xs text-neon font-bold mb-2">🌱 Cara Tanam:</p>
                                <p class="text-sm text-gray-200 whitespace-pre-line">${komoditas.cara_tanam}</p>
                            </div>
                            <div class="consult-content" data-content="siram">
                                <p class="text-sm text-gray-200 whitespace-pre-line">${komoditas.penyiraman}</p>
                            </div>
                            <div class="consult-content" data-content="pupuk">
                                <p class="text-sm text-gray-200 whitespace-pre-line">${komoditas.pupuk}</p>
                            </div>
                            <div class="consult-content" data-content="hama">
                                <div class="flex gap-2 mb-3 flex-wrap" data-subtab-group>
                                    <div class="sub-tab active" data-subtab="daftar">📋 Daftar Hama</div>
                                    <div class="sub-tab" data-subtab="pencegahan">🛡️ Pencegahan & Pengendalian</div>
                                </div>
                                <div class="sub-content active" data-subcontent="daftar">
                                    <p class="text-sm text-gray-200 whitespace-pre-line">${komoditas.hama}</p>
                                </div>
                                <div class="sub-content" data-subcontent="pencegahan">
                                    <p class="text-sm text-gray-200 whitespace-pre-line">${komoditas.hama_pencegahan}</p>
                                </div>
                            </div>
                            <div class="consult-content" data-content="penyakit">
                                <div class="flex gap-2 mb-3 flex-wrap" data-subtab-group>
                                    <div class="sub-tab active" data-subtab="daftar">📋 Daftar Penyakit</div>
                                    <div class="sub-tab" data-subtab="pencegahan">🛡️ Pencegahan & Pengendalian</div>
                                </div>
                                <div class="sub-content active" data-subcontent="daftar">
                                    <p class="text-sm text-gray-200 whitespace-pre-line">${komoditas.penyakit}</p>
                                </div>
                                <div class="sub-content" data-subcontent="pencegahan">
                                    <p class="text-sm text-gray-200 whitespace-pre-line">${komoditas.penyakit_pencegahan}</p>
                                </div>
                            </div>
                            <div class="consult-content" data-content="panen">
                                <p class="text-sm text-gray-200 whitespace-pre-line">${komoditas.panen}</p>
                            </div>
                            <div class="consult-content" data-content="tips">
                                <p class="text-sm text-gray-200 whitespace-pre-line">${komoditas.tips}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            const fullText = `Panduan lengkap ${komoditas.nama}. ${komoditas.cara_tanam}. ${komoditas.penyiraman}. ${komoditas.pupuk}. ${komoditas.panen}.`;

            return {
                html: html,
                text: fullText,
                intent: 'konsultasi_lengkap',
                isConsultation: 1,
                komoditas: komoditas.nama
            };
        }

        function buildGeneralResponse(q, intent) {
            if (q.match(/daftar|list|semua komoditas|komoditas apa/)) {
                const list = Object.values(farmingDB).map(k => `${k.emoji} ${k.nama}`).join(', ');
                const text = `Saya bisa konsultasi untuk ${Object.keys(farmingDB).length} komoditas: ${list}`;
                return {
                    html: `<p class="text-gray-200 mb-2">Saya bisa konsultasi untuk <b class="text-neon">${Object.keys(farmingDB).length} komoditas</b>:</p>
                           <p class="text-sm text-gray-300">${list}</p>
                           <p class="text-sm text-gray-400 mt-2">💡 Coba tanya: "hama pada cabai" atau "penyakit padi"</p>`,
                    text: text,
                    intent: 'list_komoditas',
                    isConsultation: 0,
                    komoditas: ''
                };
            }

            if (q.match(/halo|hai|pagi|siang|sore|malam/)) {
                const text = `Halo Pak <?php echo explode(' ', $nama_user)[0]; ?>! Ada yang bisa saya bantu? Coba tanya tentang cara mencegah hama atau penyakit komoditas favorit Anda.`;
                return {
                    html: `<p class="text-gray-200">${text}</p>`,
                    text: text,
                    intent: 'greeting',
                    isConsultation: 0,
                    komoditas: ''
                };
            }

            const text = `Saya belum paham. Coba tanya dengan format: hama pada padi dan cara mengatasinya, penyakit tomat dan pencegahannya, cara tanam tembakau, atau semua tentang cabai. Atau ketik daftar komoditas untuk lihat semua.`;
            return {
                html: `<p class="text-gray-200 mb-2">🤔 Saya belum paham. Coba tanya dengan format:</p>
                       <ul class="text-sm text-gray-300 space-y-1 list-disc list-inside">
                           <li>"hama pada <b>padi</b> dan cara mengatasinya"</li>
                           <li>"penyakit <b>tomat</b> dan pencegahannya"</li>
                           <li>"cara tanam <b>tembakau</b>"</li>
                           <li>"semua tentang <b>cabai</b>"</li>
                       </ul>
                       <p class="text-xs text-gray-500 mt-2">Atau ketik "daftar komoditas" untuk lihat semua.</p>`,
                text: text,
                intent: 'unknown',
                isConsultation: 0,
                komoditas: ''
            };
        }

        function initConsultationTabs() {
            document.querySelectorAll('.consult-card').forEach(card => {
                const tabs = card.querySelectorAll('.consult-tab');
                const contents = card.querySelectorAll('.consult-content');
                tabs.forEach(tab => {
                    tab.onclick = () => {
                        tabs.forEach(t => t.classList.remove('active'));
                        contents.forEach(c => c.classList.remove('active'));
                        tab.classList.add('active');
                        card.querySelector(`[data-content="${tab.dataset.tab}"]`).classList.add('active');
                    };
                });

                const subTabGroups = card.querySelectorAll('[data-subtab-group]');
                subTabGroups.forEach(group => {
                    const subTabs = group.querySelectorAll('.sub-tab');
                    const parentContent = group.closest('.consult-content');
                    const subContents = parentContent.querySelectorAll('.sub-content');
                    
                    subTabs.forEach(subTab => {
                        subTab.onclick = () => {
                            subTabs.forEach(st => st.classList.remove('active'));
                            subContents.forEach(sc => sc.classList.remove('active'));
                            subTab.classList.add('active');
                            parentContent.querySelector(`[data-subcontent="${subTab.dataset.subtab}"]`).classList.add('active');
                        };
                    });
                });
            });
        }

        document.addEventListener('DOMContentLoaded', () => initConsultationTabs());

        function saveToDatabase(query, response, intent, isConsultation, komoditas) {
            const cleanResponse = response.replace(/<[^>]*>?/gm, '');
            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('user_query', query);
            formData.append('ai_response', cleanResponse);
            formData.append('intent', intent);
            formData.append('is_consultation', isConsultation);
            formData.append('komoditas', komoditas);

            fetch('ai-voice.php', { method: 'POST', body: formData })
                .then(res => res.text())
                .then(data => console.log('Saved:', data))
                .catch(err => console.error('Error:', err));
        }

        // ==========================================
        // WEATHER CANVAS (Sakura)
        // ==========================================
        const canvas = document.getElementById('weatherCanvas');
        const ctx = canvas.getContext('2d');
        let particles = [];
        function resizeCanvas() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);
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
                if (this.y > canvas.height + 20) {
                    this.y = -20;
                    this.x = Math.random() * canvas.width;
                }
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
        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            particles.forEach(p => { p.update(); p.draw(); });
            requestAnimationFrame(animate);
        }
        animate();
    </script>
</body>
</html>