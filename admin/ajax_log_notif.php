<?php
// admin/ajax_log_notif.php

// Pastikan hanya request POST yang diterima
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// 1. Sertakan file koneksi
// Asumsi koneksi.php berada di level yang sama dengan folder admin/
require_once '../config/koneksi.php';

// 2. Fungsi Log Notifikasi
// Fungsi ini sama dengan yang ada di notif_spp.php, memastikan logging terpisah.
function log_notification($pdo, $nis, $judul, $isi_pesan) {
    $sql_log = "INSERT INTO notifikasi (nis, tanggal_kirim, judul, isi_notifikasi, is_read) 
                VALUES (?, NOW(), ?, ?, 0)";
    $stmt_log = $pdo->prepare($sql_log);
    return $stmt_log->execute([$nis, $judul, $isi_pesan]);
}

// 3. Ambil data dari AJAX POST
$nis = $_POST['nis'] ?? null;
$judul = $_POST['judul'] ?? 'Notifikasi SPP Manual';
$isi_pesan = $_POST['pesan'] ?? 'Pesan notifikasi tidak tersedia.';

// Set header ke JSON
header('Content-Type: application/json');

// 4. Validasi dan Proses Logging
if (empty($nis)) {
    echo json_encode(['success' => false, 'message' => 'NIS tidak valid.']);
    exit;
}

try {
    // Sanitasi input sebelum disimpan
    $pesan_final_sanitized = htmlspecialchars($isi_pesan);
    
    $result = log_notification($pdo, $nis, $judul, $pesan_final_sanitized);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => "Log notifikasi berhasil dicatat untuk NIS: {$nis}."]);
    } else {
        echo json_encode(['success' => false, 'message' => "Gagal mencatat log notifikasi ke database."]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>