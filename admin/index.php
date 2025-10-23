<?php
require_once '../config/koneksi.php';
require_once '../core/auth.php';

// Cek hak akses: hanya admin yang boleh masuk
check_access('admin');

// Ambil data nama Admin dari tabel admins (menggunakan related_id/admin_id)
$stmt = $pdo->prepare("SELECT nama_admin FROM admins WHERE admin_id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();
$admin_name = $admin['nama_admin'] ?? 'Admin Keuangan';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Dashboard Admin</title>
</head>
<body>
    <h2>Dashboard Admin Keuangan</h2>
    <p>Selamat datang, **<?= htmlspecialchars($admin_name) ?>**.</p>
    
    <h3>Menu:</h3>
    <ul>
        <li><a href="siswa_data.php">Kelola Data Siswa</a></li>
        <li><a href="#">Validasi Pembayaran</a></li>
        <li><a href="#">Laporan Keuangan</a></li>
        <li><a href="../logout.php">Logout</a></li>
    </ul>
</body>
</html>