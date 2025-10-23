<?php
require_once '../config/koneksi.php';
require_once '../core/auth.php';

// Cek hak akses: hanya siswa yang boleh masuk
check_access('siswa');

// Ambil data nama Siswa dari tabel siswa
$stmt = $pdo->prepare("SELECT nama_siswa FROM siswa WHERE nis = ?");
$stmt->execute([$_SESSION['nis']]);
$siswa = $stmt->fetch();
$siswa_name = $siswa['nama_siswa'] ?? 'Siswa';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Dashboard Siswa</title>
</head>
<body>
    <h2>Dashboard Siswa</h2>
    <p>Selamat datang, **<?= htmlspecialchars($siswa_name) ?>** (NIS: <?= $_SESSION['nis'] ?>).</p>
    
    <h3>Menu:</h3>
    <ul>
        <li><a href="tagihan.php">Transkrip Tagihan & Pembayaran</a></li>
        <li><a href="#">Upload Bukti Pembayaran SPP</a></li>
        <li><a href="#">Ubah Biodata Siswa</a></li>
        <li><a href="../logout.php">Logout</a></li>
    </ul>
</body>
</html>