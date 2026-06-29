<?php
session_start();
include 'koneksi.php';
include 'track_visitor.php';
if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add' || $action === 'edit') {
        $nama = mysqli_real_escape_string($koneksi, $_POST['nama_barang']);
        $kategori = $_POST['kategori'];
        $stok = (float)$_POST['stok'];
        $satuan = mysqli_real_escape_string($koneksi, $_POST['satuan']);
        $min_stok = (float)$_POST['min_stok'];
        $harga = (float)$_POST['harga_satuan'];
        
        if ($action === 'add') {
            $sql = "INSERT INTO gudang_inventaris (nama_barang, kategori, stok, satuan, min_stok, harga_satuan) VALUES ('$nama', '$kategori', $stok, '$satuan', $min_stok, $harga)";
        } else {
            $id = (int)$_POST['id'];
            $sql = "UPDATE gudang_inventaris SET nama_barang='$nama', kategori='$kategori', stok=$stok, satuan='$satuan', min_stok=$min_stok, harga_satuan=$harga WHERE id=$id";
        }
        $koneksi->query($sql);
        header("Location: gudang.php?success=1");
        exit;
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $koneksi->query("DELETE FROM gudang_inventaris WHERE id=$id");
        header("Location: gudang.php?deleted=1");
        exit;
    }
    
    if ($action === 'pakai') {
        $id = (int)$_POST['id'];
        $jumlah = (float)$_POST['jumlah'];
        $tanaman_id = !empty($_POST['tanaman_id']) ? (int)$_POST['tanaman_id'] : "NULL";
        $tujuan = mysqli_real_escape_string($koneksi, $_POST['tujuan']);
        
        // Kurangi stok
        $koneksi->query("UPDATE gudang_inventaris SET stok = stok - $jumlah WHERE id=$id AND stok >= $jumlah");
        // Catat penggunaan
        $koneksi->query("INSERT INTO penggunaan_inventaris (inventaris_id, tanaman_id, jumlah, tujuan, tanggal_pakai) VALUES ($id, $tanaman_id, $jumlah, '$tujuan', CURDATE())");
        header("Location: gudang.php?used=1");
        exit;
    }
}

// Stats
$total_item = $koneksi->query("SELECT COUNT(*) as c FROM gudang_inventaris")->fetch_assoc()['c'];
$total_stok = $koneksi->query("SELECT COALESCE(SUM(stok),0) as t FROM gudang_inventaris")->fetch_assoc()['t'];
$stok_rendah = $koneksi->query("SELECT COUNT(*) as c FROM gudang_inventaris WHERE stok <= min_stok")->fetch_assoc()['c'];
$total_nilai = $koneksi->query("SELECT COALESCE(SUM(stok * harga_satuan),0) as t FROM gudang_inventaris")->fetch_assoc()['t'];

// Filter
$filter_kategori = isset($_GET['kategori']) ? $_GET['kategori'] : 'all';
$sql = "SELECT * FROM gudang_inventaris WHERE 1=1";
if ($filter_kategori !== 'all') $sql .= " AND kategori = '$filter_kategori'";
$sql .= " ORDER BY kategori, nama_barang";
$items = $koneksi->query($sql);

$tanaman_list = $koneksi->query("SELECT id, nama_tanaman, nama_lahan FROM tanaman WHERE status != 'panen' ORDER BY nama_lahan");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Gudang Inventaris - RAFLI_FARM_PRO</title>
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
        
        .kategori-pupuk { background: rgba(0,255,136,0.2); color: #00FF88; }
        .kategori-pestisida { background: rgba(255,51,102,0.2); color: #FF3366; }
        .kategori-benih { background: rgba(0,229,255,0.2); color: #00E5FF; }
        .kategori-alat { background: rgba(255,179,0,0.2); color: #FFB300; }
        .kategori-mesin { background: rgba(168,85,247,0.2); color: #A855F7; }
        .kategori-bahan_bakar { background: rgba(251,146,60,0.2); color: #FB923C; }
        .kategori-lainnya { background: rgba(156,163,175,0.2); color: #9CA3AF; }
    </style>
</head>
<body class="min-h-screen flex">
    <aside class="w-64 bg-dark-800/90 backdrop-blur-xl border-r border-white/5 flex flex-col h-screen fixed hidden lg:flex z-40">
        <div class="p-6 border-b border-white/5"><h1 class="text-xl font-bold gradient-text">RAFLI_FARM</h1></div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">📊 Dashboard</a>
            <a href="tanaman.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">🌱 Tanaman</a>
            <a href="gudang.php" class="flex items-center gap-3 px-4 py-3 bg-neon/10 text-neon border-l-4 border-neon rounded-r-xl font-bold">📦 Gudang Inventaris</a>
            <a href="gudang-panen.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl">🌾 Gudang Panen</a>
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
                <h1 class="text-3xl font-bold gradient-text">📦 Gudang Inventaris</h1>
                <p class="text-gray-500 text-sm mt-1">Kelola pupuk, pestisida, alat, mesin & kebutuhan petani lainnya</p>
            </div>
            <button onclick="openModal('add')" class="px-6 py-3 bg-gradient-to-r from-neon to-cyan text-dark-900 font-bold rounded-xl hover:shadow-lg hover:shadow-neon/30 transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                + Tambah Barang
            </button>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="glass rounded-2xl p-5 border-l-4 border-neon">
                <p class="text-xs text-gray-400">Total Item</p>
                <p class="text-2xl font-bold text-white mt-1"><?php echo $total_item; ?></p>
            </div>
            <div class="glass rounded-2xl p-5 border-l-4 border-cyan">
                <p class="text-xs text-gray-400">Total Stok</p>
                <p class="text-2xl font-bold text-cyan mt-1"><?php echo number_format($total_stok, 0); ?></p>
            </div>
            <div class="glass rounded-2xl p-5 border-l-4 border-danger">
                <p class="text-xs text-gray-400">Stok Rendah</p>
                <p class="text-2xl font-bold text-danger mt-1"><?php echo $stok_rendah; ?> ⚠️</p>
            </div>
            <div class="glass rounded-2xl p-5 border-l-4 border-purple">
                <p class="text-xs text-gray-400">Total Nilai</p>
                <p class="text-2xl font-bold text-purple-400 mt-1">Rp <?php echo number_format($total_nilai/1000000, 1); ?><span class="text-sm text-gray-400"> Jt</span></p>
            </div>
        </div>

        <?php if(isset($_GET['success'])): ?><div class="mb-4 p-4 bg-neon/10 border border-neon/30 rounded-xl text-neon text-sm">✅ Data berhasil disimpan!</div><?php endif; ?>
        <?php if(isset($_GET['deleted'])): ?><div class="mb-4 p-4 bg-danger/10 border border-danger/30 rounded-xl text-danger text-sm">🗑️ Data berhasil dihapus.</div><?php endif; ?>
        <?php if(isset($_GET['used'])): ?><div class="mb-4 p-4 bg-cyan/10 border border-cyan/30 rounded-xl text-cyan text-sm">✅ Barang berhasil digunakan & stok terkurangi.</div><?php endif; ?>

        <!-- Filter -->
        <form method="GET" class="glass rounded-2xl p-4 mb-6 flex flex-wrap items-center gap-4">
            <select name="kategori" onchange="this.form.submit()" class="bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white text-sm focus:outline-none focus:border-neon">
                <option value="all">Semua Kategori</option>
                <option value="pupuk" <?php echo $filter_kategori == 'pupuk' ? 'selected' : ''; ?>>🧪 Pupuk</option>
                <option value="pestisida" <?php echo $filter_kategori == 'pestisida' ? 'selected' : ''; ?>>🧴 Pestisida</option>
                <option value="benih" <?php echo $filter_kategori == 'benih' ? 'selected' : ''; ?>>🌱 Benih</option>
                <option value="alat" <?php echo $filter_kategori == 'alat' ? 'selected' : ''; ?>>🔧 Alat</option>
                <option value="mesin" <?php echo $filter_kategori == 'mesin' ? 'selected' : ''; ?>>⚙️ Mesin</option>
                <option value="bahan_bakar" <?php echo $filter_kategori == 'bahan_bakar' ? 'selected' : ''; ?>>⛽ Bahan Bakar</option>
                <option value="lainnya" <?php echo $filter_kategori == 'lainnya' ? 'selected' : ''; ?>>📦 Lainnya</option>
            </select>
            <a href="gudang.php" class="px-4 py-2 bg-white/5 border border-white/10 text-gray-300 rounded-lg hover:bg-white/10 text-sm">Reset</a>
        </form>

        <!-- Grid Items -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php if($items->num_rows > 0): while($row = $items->fetch_assoc()): 
                $is_low = $row['stok'] <= $row['min_stok'];
                $kategori_icon = ['pupuk'=>'🧪','pestisida'=>'🧴','benih'=>'🌱','alat'=>'🔧','mesin'=>'⚙️','bahan_bakar'=>'⛽','lainnya'=>'📦'][$row['kategori']] ?? '📦';
            ?>
            <div class="glass rounded-2xl p-5 hover:border-neon/30 transition-all relative <?php echo $is_low ? 'border-danger/30' : ''; ?>">
                <?php if($is_low): ?>
                <div class="absolute top-3 right-3 px-2 py-1 bg-danger/20 text-danger text-xs font-bold rounded-full animate-pulse">⚠️ STOK RENDAH</div>
                <?php endif; ?>
                
                <div class="flex items-start gap-4 mb-3">
                    <div class="w-12 h-12 rounded-xl bg-white/5 flex items-center justify-center text-2xl"><?php echo $kategori_icon; ?></div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-bold text-white truncate"><?php echo $row['nama_barang']; ?></h3>
                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold kategori-<?php echo $row['kategori']; ?> mt-1">
                            <?php echo strtoupper(str_replace('_', ' ', $row['kategori'])); ?>
                        </span>
                    </div>
                </div>

                <div class="space-y-2 mb-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Stok:</span>
                        <span class="font-bold <?php echo $is_low ? 'text-danger' : 'text-neon'; ?>"><?php echo number_format($row['stok'], 1); ?> <?php echo $row['satuan']; ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Min. Stok:</span>
                        <span class="text-gray-300"><?php echo number_format($row['min_stok'], 1); ?> <?php echo $row['satuan']; ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Harga:</span>
                        <span class="text-cyan font-bold">Rp <?php echo number_format($row['harga_satuan'], 0, ',', '.'); ?></span>
                    </div>
                    <div class="flex justify-between text-sm pt-2 border-t border-white/5">
                        <span class="text-gray-400">Nilai:</span>
                        <span class="text-white font-bold">Rp <?php echo number_format($row['stok'] * $row['harga_satuan'], 0, ',', '.'); ?></span>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button onclick='openPakaiModal(<?php echo json_encode($row); ?>)' class="flex-1 py-2 bg-cyan/20 text-cyan rounded-lg hover:bg-cyan/30 text-xs font-medium border border-cyan/20">📤 Pakai</button>
                    <button onclick='openModal("edit", <?php echo json_encode($row); ?>)' class="flex-1 py-2 bg-blue-500/20 text-blue-400 rounded-lg hover:bg-blue-500/30 text-xs font-medium border border-blue-500/20">✏️ Edit</button>
                    <form method="POST" onsubmit="return confirm('Hapus?')" class="flex-1">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                        <button class="w-full py-2 bg-danger/20 text-danger rounded-lg hover:bg-danger/30 text-xs font-medium border border-danger/20">🗑️</button>
                    </form>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div class="col-span-full text-center py-12 text-gray-500 glass rounded-2xl">
                <p class="text-4xl mb-2">📦</p><p>Belum ada barang di gudang.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Tambah/Edit -->
    <div id="modalForm" class="modal-overlay modal-hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div class="modal-content glass rounded-2xl w-full max-w-lg p-6 border border-neon/30">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold gradient-text" id="modalTitle">📦 Tambah Barang</h3>
                <button onclick="closeModal('modalForm')" class="text-gray-400 hover:text-white">✖️</button>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="editId">
                <div><label class="block text-xs text-gray-400 mb-1">Nama Barang *</label><input type="text" name="nama_barang" id="f_nama" required class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none"></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-xs text-gray-400 mb-1">Kategori</label>
                        <select name="kategori" id="f_kategori" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none">
                            <option value="pupuk">🧪 Pupuk</option><option value="pestisida">🧴 Pestisida</option><option value="benih">🌱 Benih</option>
                            <option value="alat">🔧 Alat</option><option value="mesin">⚙️ Mesin</option><option value="bahan_bakar">⛽ Bahan Bakar</option><option value="lainnya">📦 Lainnya</option>
                        </select>
                    </div>
                    <div><label class="block text-xs text-gray-400 mb-1">Satuan</label><input type="text" name="satuan" id="f_satuan" value="pcs" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none"></div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="block text-xs text-gray-400 mb-1">Stok</label><input type="number" step="0.1" name="stok" id="f_stok" required class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none"></div>
                    <div><label class="block text-xs text-gray-400 mb-1">Min. Stok</label><input type="number" step="0.1" name="min_stok" id="f_min" value="10" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none"></div>
                    <div><label class="block text-xs text-gray-400 mb-1">Harga</label><input type="number" name="harga_satuan" id="f_harga" value="0" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-neon focus:outline-none"></div>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 py-3 bg-gradient-to-r from-neon to-cyan text-dark-900 font-bold rounded-xl">💾 Simpan</button>
                    <button type="button" onclick="closeModal('modalForm')" class="flex-1 py-3 bg-white/5 border border-white/10 text-gray-300 rounded-xl">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Pakai Barang -->
    <div id="modalPakai" class="modal-overlay modal-hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div class="modal-content glass rounded-2xl w-full max-w-md p-6 border border-cyan/30">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold gradient-text">📤 Gunakan Barang</h3>
                <button onclick="closeModal('modalPakai')" class="text-gray-400 hover:text-white">✖️</button>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="pakai">
                <input type="hidden" name="id" id="pakaiId">
                <div class="p-3 bg-cyan/5 border border-cyan/20 rounded-lg">
                    <p class="text-xs text-gray-400">Barang:</p>
                    <p class="font-bold text-white" id="pakaiNama">-</p>
                    <p class="text-xs text-cyan">Stok tersedia: <span id="pakaiStok">-</span></p>
                </div>
                <div><label class="block text-xs text-gray-400 mb-1">Jumlah Dipakai *</label><input type="number" step="0.1" name="jumlah" required class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-cyan focus:outline-none"></div>
                <div><label class="block text-xs text-gray-400 mb-1">Untuk Tanaman (Opsional)</label>
                    <select name="tanaman_id" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-cyan focus:outline-none">
                        <option value="">-- Umum --</option>
                        <?php $tanaman_list->data_seek(0); while($t = $tanaman_list->fetch_assoc()): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo $t['nama_tanaman']; ?> (<?php echo $t['nama_lahan']; ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div><label class="block text-xs text-gray-400 mb-1">Tujuan/Keterangan</label><input type="text" name="tujuan" placeholder="Contoh: Pemupukan Lahan A" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm focus:border-cyan focus:outline-none"></div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 py-3 bg-gradient-to-r from-cyan to-neon text-dark-900 font-bold rounded-xl">✅ Gunakan</button>
                    <button type="button" onclick="closeModal('modalPakai')" class="flex-1 py-3 bg-white/5 border border-white/10 text-gray-300 rounded-xl">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(mode, data = null) {
            document.getElementById('modalForm').classList.remove('modal-hidden');
            if (mode === 'add') {
                document.getElementById('modalTitle').innerText = '📦 Tambah Barang';
                document.getElementById('formAction').value = 'add';
                document.getElementById('editId').value = '';
                ['f_nama','f_stok'].forEach(id => document.getElementById(id).value = '');
                document.getElementById('f_kategori').value = 'pupuk';
                document.getElementById('f_satuan').value = 'pcs';
                document.getElementById('f_min').value = '10';
                document.getElementById('f_harga').value = '0';
            } else if (data) {
                document.getElementById('modalTitle').innerText = '✏️ Edit Barang';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('editId').value = data.id;
                document.getElementById('f_nama').value = data.nama_barang;
                document.getElementById('f_kategori').value = data.kategori;
                document.getElementById('f_satuan').value = data.satuan;
                document.getElementById('f_stok').value = data.stok;
                document.getElementById('f_min').value = data.min_stok;
                document.getElementById('f_harga').value = data.harga_satuan;
            }
        }
        function openPakaiModal(data) {
            document.getElementById('modalPakai').classList.remove('modal-hidden');
            document.getElementById('pakaiId').value = data.id;
            document.getElementById('pakaiNama').innerText = data.nama_barang;
            document.getElementById('pakaiStok').innerText = data.stok + ' ' + data.satuan;
        }
        function closeModal(id) { document.getElementById(id).classList.add('modal-hidden'); }
        ['modalForm','modalPakai'].forEach(id => {
            document.getElementById(id).addEventListener('click', e => { if(e.target.id === id) closeModal(id); });
        });
    </script>
</body>
</html>