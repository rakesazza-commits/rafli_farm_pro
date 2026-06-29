<?php
session_start();
include 'koneksi.php';
require('fpdf/fpdf.php');

if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }

class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'RAFLI_FARM_PRO', 0, 1, 'C');
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 6, 'Sistem Informasi Pertanian Cerdas', 0, 1, 'C');
        $this->Cell(0, 6, 'Laporan Penjualan', 0, 1, 'C');
        $this->Ln(5);
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, 'Dicetak: ' . date('d-m-Y H:i'), 0, 1, 'R');
        $this->Ln(3);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo(), 0, 0, 'C');
    }
}

// Get data dari parameter
$type = $_GET['type'] ?? 'sales';
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-t');

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);

if ($type == 'sales') {
    // Laporan Penjualan
    $pdf->Cell(0, 8, 'Periode: ' . date('d-m-Y', strtotime($start_date)) . ' s/d ' . date('d-m-Y', strtotime($end_date)), 0, 1);
    $pdf->Ln(3);
    
    // Header tabel
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(200, 220, 200);
    $pdf->Cell(10, 6, 'No', 1, 0, 'C', true);
    $pdf->Cell(30, 6, 'Invoice', 1, 0, 'C', true);
    $pdf->Cell(40, 6, 'Pembeli', 1, 0, 'L', true);
    $pdf->Cell(35, 6, 'Produk', 1, 0, 'L', true);
    $pdf->Cell(25, 6, 'Jumlah', 1, 0, 'R', true);
    $pdf->Cell(35, 6, 'Total (Rp)', 1, 0, 'R', true);
    $pdf->Cell(25, 6, 'Status', 1, 1, 'C', true);
    
    // Data
    $pdf->SetFont('Arial', '', 9);
    $sql = "SELECT * FROM penjualan WHERE tanggal_jual BETWEEN '$start_date' AND '$end_date' ORDER BY tanggal_jual DESC";
    $result = $koneksi->query($sql);
    
    $no = 1;
    $grand_total = 0;
    while($row = $result->fetch_assoc()) {
        $pdf->Cell(10, 6, $no++, 1, 0, 'C');
        $pdf->Cell(30, 6, $row['no_invoice'], 1, 0, 'C');
        $pdf->Cell(40, 6, substr($row['nama_pembeli'], 0, 20), 1, 0, 'L');
        $pdf->Cell(35, 6, $row['nama_produk'], 1, 0, 'L');
        $pdf->Cell(25, 6, $row['jumlah'] . ' ' . $row['satuan'], 1, 0, 'R');
        $pdf->Cell(35, 6, number_format($row['total_harga'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(25, 6, strtoupper($row['status_pembayaran']), 1, 1, 'C');
        $grand_total += $row['total_harga'];
    }
    
    // Total
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(145, 7, 'TOTAL PENDAPATAN', 1, 0, 'R');
    $pdf->Cell(35, 7, 'Rp ' . number_format($grand_total, 0, ',', '.'), 1, 0, 'R');
    $pdf->Cell(25, 7, '', 1, 1, 'C');
    
} elseif ($type == 'inventory') {
    // Laporan Stok Gudang
    $pdf->Cell(0, 8, 'Laporan Stok Gudang - ' . date('d-m-Y'), 0, 1);
    $pdf->Ln(3);
    
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(200, 220, 200);
    $pdf->Cell(10, 6, 'No', 1, 0, 'C', true);
    $pdf->Cell(45, 6, 'Nama Barang', 1, 0, 'L', true);
    $pdf->Cell(25, 6, 'Kategori', 1, 0, 'L', true);
    $pdf->Cell(25, 6, 'Stok', 1, 0, 'R', true);
    $pdf->Cell(30, 6, 'Min. Stok', 1, 0, 'R', true);
    $pdf->Cell(30, 6, 'Status', 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 9);
    $sql = "SELECT * FROM inventory ORDER BY kategori, nama_barang";
    $result = $koneksi->query($sql);
    
    $no = 1;
    while($row = $result->fetch_assoc()) {
        $status = $row['stok'] <= $row['min_stok'] ? 'PERLU RESTOCK' : 'AMAN';
        $pdf->Cell(10, 6, $no++, 1, 0, 'C');
        $pdf->Cell(45, 6, $row['nama_barang'], 1, 0, 'L');
        $pdf->Cell(25, 6, ucfirst($row['kategori']), 1, 0, 'L');
        $pdf->Cell(25, 6, $row['stok'] . ' ' . $row['satuan'], 1, 0, 'R');
        $pdf->Cell(30, 6, $row['min_stok'], 1, 0, 'R');
        $pdf->Cell(30, 6, $status, 1, 1, 'C');
    }
}

// Output PDF
$pdf->Output('I', 'Laporan_Rafli_Farm_' . date('Ymd') . '.pdf');
?>