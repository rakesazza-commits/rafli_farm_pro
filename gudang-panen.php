<?php
session_start();
include 'koneksi.php';
include 'track_visitor.php';
if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }

// ==========================================
// HANDLE CRUD
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        $tanaman_id = !empty($_POST['tanaman_id']) ? (int)$_POST['tanaman_id'] : "NULL";
        $komoditas = mysqli_real_escape_string($koneksi, $_POST['komoditas']);
        $varietas = mysqli_real_escape_string($koneksi, $_POST['varietas']);
        $lahan = mysqli_real_escape_string($koneksi, $_POST['lahan_asal']);
        $berat = (float)$_POST['berat_kg'];
        $kualitas = $_POST['kualitas'];
        $harga = (float)$_POST['harga_per_kg'];
        $total = $berat * $harga;
        $tanggal = $_POST['tanggal_panen'];
        $status = $_POST['status'];
        $catatan = mysqli_real_escape_string($koneksi, $_POST['catatan']);

        $sql = "INSERT INTO gudang_panen (tanaman_id, komoditas, varietas, lahan_asal, berat_kg, kualitas, harga_per_kg, total_nilai, tanggal_panen, status, catatan) 
                VALUES ($tanaman_id, '$komoditas', '$varietas', '$lahan', $berat, '$kualitas', $harga, $total, '$tanggal', '$status', '$catatan')";
        
        if ($koneksi->query($sql)) {
            // Update status tanaman jadi 'panen' jika ada
            if ($tanaman_id !== "NULL") {
                $koneksi->query("UPDATE tanaman SET status='panen' WHERE id=$tanaman_id");
            }
            header("Location: gudang-panen.php?success=1");
            exit;
        }
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $komoditas = mysqli_real_escape_string($koneksi, $_POST['komoditas']);
        $varietas = mysqli_real_escape_string($koneksi, $_POST['varietas']);
        $lahan = mysqli_real_escape_string($koneksi, $_POST['lahan_asal']);
        $berat = (float)$_POST['berat_kg'];
        $kualitas = $_POST['kualitas'];
        $harga = (float)$_POST['harga_per_kg'];
        $total = $berat * $harga;
        $tanggal = $_POST['tanggal_panen'];
        $status = $_POST['status'];
        $catatan = mysqli_real_escape_string($koneksi, $_POST['catatan']);

        $sql = "UPDATE gudang_panen SET komoditas='$komoditas', varietas='$varietas', lahan_asal='$lahan', 
                berat_kg=$berat, kualitas='$kualitas', harga_per_kg=$harga, total_nilai=$total, 
                tanggal_panen='$tanggal', status='$status', catatan='$catatan' WHERE id=$id";
        $koneksi->query($sql);
        header("Location: gudang-panen.php?success=2");
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $koneksi->query("DELETE FROM gudang_panen WHERE id=$id");
        header("Location: gudang-panen.php?deleted=1");
        exit;
    }

    if ($action === 'jual') {
        $id = (int)$_POST['id'];
        $koneksi->query("UPDATE gudang_panen SET status='terjual' WHERE id=$id");
        header("Location: gudang-panen.php?sold=1");
        exit;
    }
}

// ==========================================
// STATISTIK
// ==========================================
$total_panen = $koneksi->query("SELECT COALESCE(SUM(berat_kg),0) as t FROM gudang_panen")->fetch_assoc()['t'];
$total_nilai = $koneksi->query("SELECT COALESCE(SUM(total_nilai),0) as t FROM gudang_panen")->fetch_assoc()['t'];
$stok_segar = $koneksi->query("SELECT COALESCE(SUM(berat_kg),0) as t FROM gudang_panen WHERE status='segar'")->fetch_assoc()['t'];
$stok_siap_jual = $koneksi->query("SELECT COALESCE(SUM(berat_kg),0) as t FROM gudang_panen WHERE status='siap_jual'")->fetch_assoc()['t'];
$terjual = $koneksi->query("SELECT COALESCE(SUM(total_nilai),0) as t FROM gudang_panen WHERE status='terjual'")->fetch_assoc()['t'];

// Data untuk filter
$filter_komoditas = isset($_GET['komoditas']) ? $_GET['komoditas'] : 'all';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

$sql = "SELECT p.*, t.nama_lahan as lahan_tanaman 
        FROM gudang_panen p 
        LEFT JOIN tanaman t ON p.tanaman_id = t.id 
        WHERE 1=1";
if ($filter_komoditas !== 'all') $sql .= " AND p.komoditas = '$filter_komoditas'";
if ($filter_status !== 'all') $sql .= " AND p.status = '$filter_status'";
$sql .= " ORDER BY p.tanggal_panen DESC, p.id DESC";
$hasil = $koneksi->query($sql);

// List komoditas unik
$komoditas_list = $koneksi->query("SELECT DISTINCT komoditas FROM gudang_panen ORDER BY komoditas");

// List tanaman siap panen
$tanaman_siap_panen = $koneksi->query("SELECT * FROM tanaman WHERE status IN ('siap_panen', 'pertumbuhan') ORDER BY nama_lahan");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Gudang Panen - RAFLI_FARM_PRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { neon: '#00FF88', cyan: '#00E5FF', danger: '#FF3366', warning: '#FFB300', purple: '#A855F7', dark: { 900: '#0A0A0A', 800: '#111111' } } } } }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0A0A0A; color: #fff; }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .gradient-text { background: linear-gradient(135deg, #00FF88, #00E5FF); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .modal-overlay { transition: opacity 0.3s ease; }
        .modal-content { transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .modal-hidden { opacity: 0; pointer-events: none; visibility: hidden; }
        .modal-hidden .modal-content { transform: scale(0.9) translateY(20px); }
        ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-thumb { background: #00FF88; border-radius: 10px; }
        
        .quality-A { background: linear-gradient(135deg, #00FF88, #10B981); }
        .quality-B { background: linear-gradient(135deg, #FFB300, #F59E0B); }
        .quality-C { background: linear-gradient(135deg, #FF3366, #EF4444); }
        
        .status-segar { background: rgba(0,255,136,0.2); color: #00FF88; }
        .status-pengolahan { background: rgba(255,179,0,0.2); color: #FFB300; }
        .status-siap_jual { background: rgba(0,229,255,0.2); color: #00E5FF; }
        .status-terjual { background: rgba(168,85,247,0.2); color: #A855F7; }
    </style>
</head>
<body class="min-h-screen flex">
    <aside class="w-64 bg-dark-800/90 backdrop-blur-xl border-r border-white/5 flex flex-col h-screen fixed hidden lg:flex z-40">
        <div class="p-6 border-b border-white/5"><h1 class="text-xl font-bold gradient-text">RAFLI_FARM</h1></div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">📊 Dashboard</a>
            <a href="tanaman.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">🌱 Tanaman</a>
            <a href="gudang.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">📦 Gudang Inventaris</a>
            <a href="gudang-panen.php" class="flex items-center gap-3 px-4 py-3 bg-neon/10 text-neon border-l-4 border-neon rounded-r-xl font-bold">🌾 Gudang Panen</a>
            <a href="penjualan.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">💰 Penjualan</a>
            <a href="keuangan.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">💵 Keuangan</a>
            <a href="jadwal.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">📅 Jadwal</a>
            <a href="kalkulator.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">🧮 Kalkulator</a>
        </nav>
        <div class="p-4 border-t border-white/5"><a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-danger hover:bg-danger/10 rounded-xl">Keluar</a></div>
    </aside>

    <div class="flex-1 lg:ml-64 p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold gradient-text">🌾 Gudang Hasil Panen</h1>
                <p class="text-gray-500 text-sm mt-1">Kelola hasil panen dari semua lahan pertanian Anda</p>
            </div>
            <button onclick="openModal('add')" class="px-6 py-3 bg-gradient-to-r from-neon to-cyan text-dark-900 font-bold rounded-xl hover:shadow-lg hover:shadow-neon/30 transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                + Catat Panen
            </button>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="glass rounded-2xl p-5 border-l-4 border-neon">
                <p class="text-xs text-gray-400">Total Panen</p>
                <p class="text-2xl font-bold text-white mt-1"><?php echo number_format($total_panen, 0); ?> <span class="text-sm text-gray-400">kg</span></p>
            </div>
            <div class="glass rounded-2xl p-5 border-l-4 border-cyan">
                <p class="text-xs text-gray-400">Stok Segar</p>
                <p class="text-2xl font-bold text-cyan mt-1"><?php echo number_format($stok_segar, 0); ?> <span class="text-sm text-gray-400">kg</span></p>
            </div>
            <div class="glass rounded-2xl p-5 border-l-4 border-warning">
                <p class="text-xs text-gray-400">Siap Jual</p>
                <p class="text-2xl font-bold text-warning mt-1"><?php echo number_format($stok_siap_jual, 0); ?> <span class="text-sm text-gray-400">kg</span></p>
            </div>
            <div class="glass rounded-2xl p-5 border-l-4 border-purple">
                <p class="text-xs text-gray-400">Total Nilai</p>
                <p class="text-2xl font-bold text-purple-400 mt-1">Rp <?php echo number_format($total_nilai/1000000, 1); ?><span class="text-sm text-gray-400"> Jt</span></p>
            </div>
            <div class="glass rounded-2xl p-5 border-l-4 border-neon">
                <p class="text-xs text-gray-400">Sudah Terjual</p>
                <p class="text-2xl font-bold text-neon mt-1">Rp <?php echo number_format($terjual/1000000, 1); ?><span class="text-sm text-gray-400"> Jt</span></p>
            </div>
        </div>

        <!-- Alerts -->
        <?php if(isset($_GET['success'])): ?><div class="mb-4 p-4 bg-neon/10 border border-neon/30 rounded-xl text-neon text-sm">✅ Data panen berhasil disimpan!</div><?php endif; ?>
        <?php if(isset($_GET['deleted'])): ?><div class="mb-4 p-4 bg-danger/10 border border-danger/30 rounded-xl text-danger text-sm">🗑️ Data berhasil dihapus.</div><?php endif; ?>
        <?php if(isset($_GET['sold'])): ?><div class="mb-4 p-4 bg-purple-500/10 border border-purple-500/30 rounded-xl text-purple-400 text-sm">💰 Status berhasil diubah jadi TERJUAL!</div><?php endif; ?>

        <!-- Filter -->
        <form method="GET" class="glass rounded-2xl p-4 mb-6 flex flex-wrap items-center gap-4">
            <select name="komoditas" onchange="this.form.submit()" class="bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white text-sm focus:outline-none focus:border-neon">
                <option value="all">Semua Komoditas</option>
                <?php $komoditas_list->data_seek(0); while($k = $komoditas_list->fetch_assoc()): ?>
                    <option value="<?php echo $k['komoditas']; ?>" <?php echo $filter_komoditas == $k['komoditas'] ? 'selected' : ''; ?>><?php echo $k['komoditas']; ?></option>
                <?php endwhile; ?>
            </select>
            <select name="status" onchange="this.form.submit()" class="bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white text-sm focus:outline-none focus:border-neon">
                <option value="all">Semua Status</option>
                <option value="segar" <?php echo $filter_status == 'segar' ? 'selected' : ''; ?>>🌿 Segar</option>
                <option value="pengolahan" <?php echo $filter_status == 'pengolahan' ? 'selected' : ''; ?>>🏭 Pengolahan</option>
                <option value="siap_jual" <?php echo $filter_status == 'siap_jual' ? 'selected' : ''; ?>>📦 Siap Jual</option>
                <option value="terjual" <?php echo $filter_status == 'terjual' ? 'selected' : ''; ?>>💰 Terjual</option>
            </select>
            <a href="gudang-panen.php" class="px-4 py-2 bg-white/5 border border-white/10 text-gray-300 rounded-lg hover:bg-white/10 text-sm">Reset</a>
        </form>

        <!-- Table -->
        <div class="glass rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-white/5 text-gray-400 text-xs uppercase">
                        <tr>
                            <th class="p-4">Tanggal</th>
                            <th class="p-4">Komoditas</th>
                            <th class="p-4">Lahan</th>
                            <th class="p-4">Berat</th>
                            <th class="p-4">Kualitas</th>
                            <th class="p-4">Harga/kg</th>
                            <th class="p-4">Total Nilai</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-sm">
                        <?php if($hasil->num_rows > 0): while($row = $hasil->fetch_assoc()): ?>
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="p-4 text-gray-400"><?php echo date('d M Y', strtotime($row['tanggal_panen'])); ?></td>
                            <td class="p-4">
                                <p class="font-bold text-white"><?php echo $row['komoditas']; ?></p>
                                <p class="text-xs text-gray-500"><?php echo $row['varietas']; ?></p>
                            </td>
                            <td class="p-4 text-gray-400"><?php echo $row['lahan_asal'] ?: ($row['lahan_tanaman'] ?? '-'); ?></td>
                            <td class="p-4 font-bold text-neon"><?php echo number_format($row['berat_kg'], 1); ?> kg</td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-xs font-bold text-white quality-<?php echo $row['kualitas']; ?>">Grade <?php echo $row['kualitas']; ?></span>
                            </td>
                            <td class="p-4 text-gray-300">Rp <?php echo number_format($row['harga_per_kg'], 0, ',', '.'); ?></td>
                            <td class="p-4 font-bold text-cyan">Rp <?php echo number_format($row['total_nilai'], 0, ',', '.'); ?></td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-xs font-bold status-<?php echo $row['status']; ?>">
                                    <?php 
                                    $status_map = ['segar'=>'🌿 Segar', 'pengolahan'=>'🏭 Pengolahan', 'siap_jual'=>'📦 Siap Jual', 'terjual'=>'💰 Terjual'];
                                    echo $status_map[$row['status']];
                                    ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <div class="flex gap-1 justify-center">
                                    <?php if($row['status'] !== 'terjual'): ?>
                                    <form method="POST" onsubmit="return confirm('Tandai sebagai TERJUAL?')">
                                        <input type="hidden" name="action" value="jual">
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <button class="p-1.5 bg-purple-500/20 text-purple-400 rounded hover:bg-purple-500/30 text-xs" title="Tandai Terjual">💰</button>
                                    </form>
                                    <?php endif; ?>
                                    <button onclick='openModal("edit", <?php echo json_encode($row); ?>)' class="p-1.5 bg-blue-500/20 text-blue-400 rounded hover:bg-blue-500/30 text-xs" title="Edit">✏️</button>
                                    <form method="POST" onsubmit="return confirm('Hapus data ini?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <button class="p-1.5 bg-danger/20 text-danger rounded hover:bg-danger/30 text-xs" title="Hapus">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="9" class="p-12 text-center text-gray-500">
                            <p class="text-4xl mb-2">🌾</p>
                            <p>Belum ada data hasil panen.</p>
                            <p class="text-xs mt-1">Klik <b class="text-neon">"+ Catat Panen"</b> untuk memulai.</p>
                        </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="modalForm" class="modal-overlay modal-hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div class="modal-content glass rounded-2xl w-full max-w-2xl p-6 border border-neon/30 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold gradient-text" id="modalTitle">🌾 Catat Hasil Panen</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-white">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="editId">

                <!-- Pilih Tanaman (Optional) -->
                <div class="p-4 bg-cyan/5 border border-cyan/20 rounded-xl">
                    <label class="block text-xs text-cyan font-bold mb-2">🌱 Dari Tanaman (Opsional - Auto-fill data)</label>
                    <select id="selectTanaman" onchange="fillFromTanaman(this.value)" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-cyan focus:outline-none">
                        <option value="">-- Pilih Tanaman (atau isi manual) --</option>
                        <?php $tanaman_siap_panen->data_seek(0); while($t = $tanaman_siap_panen->fetch_assoc()): ?>
                            <option value="<?php echo $t['id']; ?>" 
                                    data-komoditas="<?php echo $t['nama_tanaman']; ?>" 
                                    data-varietas="<?php echo $t['varietas']; ?>" 
                                    data-lahan="<?php echo $t['nama_lahan']; ?>"
                                    data-luas="<?php echo $t['luas_lahan']; ?>">
                                <?php echo $t['nama_tanaman']; ?> - <?php echo $t['varietas']; ?> (<?php echo $t['nama_lahan']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <input type="hidden" name="tanaman_id" id="tanamanId">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Komoditas *</label>
                        <input type="text" name="komoditas" id="f_komoditas" required placeholder="Contoh: Padi, Cabai" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Varietas</label>
                        <input type="text" name="varietas" id="f_varietas" placeholder="Contoh: IR64, Rawit" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Lahan Asal *</label>
                        <input type="text" name="lahan_asal" id="f_lahan" required placeholder="Contoh: Lahan A" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Berat (kg) *</label>
                        <input type="number" step="0.1" name="berat_kg" id="f_berat" required oninput="hitungTotal()" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Kualitas</label>
                        <select name="kualitas" id="f_kualitas" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                            <option value="A">🥇 Grade A (Premium)</option>
                            <option value="B">🥈 Grade B (Standar)</option>
                            <option value="C">🥉 Grade C (Biasa)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Harga/kg (Rp)</label>
                        <input type="number" name="harga_per_kg" id="f_harga" value="0" oninput="hitungTotal()" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Total Nilai</label>
                        <input type="text" id="f_total" readonly class="w-full px-3 py-2 bg-neon/10 border border-neon/30 rounded-lg text-neon font-bold text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Tanggal Panen *</label>
                        <input type="date" name="tanggal_panen" id="f_tanggal" required value="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Status</label>
                        <select name="status" id="f_status" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                            <option value="segar">🌿 Segar (Baru Panen)</option>
                            <option value="pengolahan">🏭 Dalam Pengolahan</option>
                            <option value="siap_jual">📦 Siap Jual</option>
                            <option value="terjual">💰 Sudah Terjual</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-gray-400 mb-1">Catatan</label>
                    <textarea name="catatan" id="f_catatan" rows="2" placeholder="Catatan tambahan..." class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none"></textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 py-3 bg-gradient-to-r from-neon to-cyan text-dark-900 font-bold rounded-xl hover:shadow-lg transition-all">💾 Simpan Data Panen</button>
                    <button type="button" onclick="closeModal()" class="flex-1 py-3 bg-white/5 border border-white/10 text-gray-300 rounded-xl hover:bg-white/10 transition-all">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(mode, data = null) {
            document.getElementById('modalForm').classList.remove('modal-hidden');
            if (mode === 'add') {
                document.getElementById('modalTitle').innerText = '🌾 Catat Hasil Panen';
                document.getElementById('formAction').value = 'add';
                document.getElementById('editId').value = '';
                document.getElementById('selectTanaman').value = '';
                document.getElementById('tanamanId').value = '';
                ['f_komoditas','f_varietas','f_lahan','f_berat','f_catatan'].forEach(id => document.getElementById(id).value = '');
                document.getElementById('f_harga').value = '0';
                document.getElementById('f_total').value = '';
                document.getElementById('f_kualitas').value = 'A';
                document.getElementById('f_status').value = 'segar';
                document.getElementById('f_tanggal').value = new Date().toISOString().split('T')[0];
            } else if (mode === 'edit' && data) {
                document.getElementById('modalTitle').innerText = '✏️ Edit Data Panen';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('editId').value = data.id;
                document.getElementById('tanamanId').value = data.tanaman_id || '';
                document.getElementById('f_komoditas').value = data.komoditas;
                document.getElementById('f_varietas').value = data.varietas || '';
                document.getElementById('f_lahan').value = data.lahan_asal || '';
                document.getElementById('f_berat').value = data.berat_kg;
                document.getElementById('f_kualitas').value = data.kualitas;
                document.getElementById('f_harga').value = data.harga_per_kg;
                document.getElementById('f_total').value = 'Rp ' + Number(data.total_nilai).toLocaleString('id-ID');
                document.getElementById('f_tanggal').value = data.tanggal_panen;
                document.getElementById('f_status').value = data.status;
                document.getElementById('f_catatan').value = data.catatan || '';
            }
        }

        function closeModal() { document.getElementById('modalForm').classList.add('modal-hidden'); }
        document.getElementById('modalForm').addEventListener('click', e => { if(e.target.id === 'modalForm') closeModal(); });

        function fillFromTanaman(id) {
            if (!id) return;
            const opt = document.querySelector(`#selectTanaman option[value="${id}"]`);
            if (!opt) return;
            document.getElementById('tanamanId').value = id;
            document.getElementById('f_komoditas').value = opt.dataset.komoditas;
            document.getElementById('f_varietas').value = opt.dataset.varietas;
            document.getElementById('f_lahan').value = opt.dataset.lahan;
            // Estimasi hasil: luas × 5 ton/ha (untuk padi)
            const luas = parseFloat(opt.dataset.luas);
            const estimasi = (luas * 5000).toFixed(0);
            document.getElementById('f_berat').value = estimasi;
            hitungTotal();
        }

        function hitungTotal() {
            const berat = parseFloat(document.getElementById('f_berat').value) || 0;
            const harga = parseFloat(document.getElementById('f_harga').value) || 0;
            const total = berat * harga;
            document.getElementById('f_total').value = 'Rp ' + total.toLocaleString('id-ID');
        }
    </script>
</body>
</html>