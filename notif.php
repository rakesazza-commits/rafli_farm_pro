<?php
session_start();
include 'koneksi.php';
if (!isset($_SESSION['login'])) { exit; }

header('Content-Type: application/json');

// Auto-generate notification jika stok rendah
$low_stock = $koneksi->query("SELECT * FROM inventory WHERE stok <= min_stok");
while($item = $low_stock->fetch_assoc()) {
    $cek = $koneksi->query("SELECT id FROM notifications WHERE type='stock_alert' AND message LIKE '%{$item['nama_barang']}%' AND DATE(created_at) = CURDATE()");
    if ($cek->num_rows == 0) {
        $koneksi->query("INSERT INTO notifications (type, title, message, icon, color, link) VALUES ('stock_alert', 'Stok Rendah: {$item['nama_barang']}', 'Stok hanya {$item['stok']} {$item['satuan']}. Minimum {$item['min_stok']} {$item['satuan']}. Segera restock!', '⚠️', 'warning', 'gudang.php')");
    }
}

// Auto-generate notifikasi panen (tanaman yang sudah >90% umur)
$harvest_soon = $koneksi->query("
    SELECT * FROM tanaman 
    WHERE status != 'panen' 
    AND DATEDIFF(estimasi_panen, CURDATE()) <= 7 
    AND DATEDIFF(estimasi_panen, CURDATE()) >= 0
");
while($t = $harvest_soon->fetch_assoc()) {
    $days_left = floor((strtotime($t['estimasi_panen']) - time()) / 86400);
    $cek = $koneksi->query("SELECT id FROM notifications WHERE type='harvest_ready' AND message LIKE '%{$t['nama_tanaman']}%' AND DATE(created_at) = CURDATE()");
    if ($cek->num_rows == 0) {
        $koneksi->query("INSERT INTO notifications (type, title, message, icon, color, link) VALUES ('harvest_ready', '{$t['nama_tanaman']} Siap Panen', '{$t['nama_lahan']} - {$t['varietas']} siap panen dalam {$days_left} hari', '🌾', 'neon', 'tanaman.php')");
    }
}

// Ambil notifikasi
$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $result = $koneksi->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 15");
    $notif = [];
    while($r = $result->fetch_assoc()) $notif[] = $r;
    
    $unread = $koneksi->query("SELECT COUNT(*) as c FROM notifications WHERE is_read=0")->fetch_assoc()['c'];
    
    echo json_encode([
        'success' => true,
        'data' => $notif,
        'unread' => (int)$unread
    ]);
} elseif ($action === 'mark_read') {
    $id = (int)$_GET['id'];
    if ($id > 0) {
        $koneksi->query("UPDATE notifications SET is_read=1 WHERE id=$id");
    } else {
        $koneksi->query("UPDATE notifications SET is_read=1");
    }
    echo json_encode(['success' => true]);
} elseif ($action === 'delete') {
    $id = (int)$_GET['id'];
    $koneksi->query("DELETE FROM notifications WHERE id=$id");
    echo json_encode(['success' => true]);
} elseif ($action === 'clear_all') {
    $koneksi->query("DELETE FROM notifications WHERE is_read=1");
    echo json_encode(['success' => true]);
}
?>