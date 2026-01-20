<?php
require_once '../config/koneksi.php';
require_once '../core/auth.php';

check_access('admin');

if (!isset($_GET['id'])) {
    header("Location: transaksi_riwayat.php?error=ID tidak valid");
    exit;
}

$id = $_GET['id'];

// Ambil data transaksi (untuk hapus file bukti)
$stmt = $pdo->prepare("SELECT bukti_bayar FROM transaksi_pembayaran WHERE transaksi_id = ?");
$stmt->execute([$id]);
$trans = $stmt->fetch();

if (!$trans) {
    header("Location: transaksi_riwayat.php?error=Data tidak ditemukan");
    exit;
}

// Hapus file bukti jika ada
if (!empty($trans['bukti_bayar'])) {
    $file_path = "../uploads/bukti_bayar/" . $trans['bukti_bayar'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

// Hapus transaksi
$stmt_del = $pdo->prepare("DELETE FROM transaksi_pembayaran WHERE transaksi_id = ?");
$stmt_del->execute([$id]);

header("Location: transaksi_riwayat.php?success=Transaksi berhasil dihapus");
exit;
