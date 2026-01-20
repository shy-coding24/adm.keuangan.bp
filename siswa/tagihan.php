<?php
require_once '../config/koneksi.php';
require_once '../core/auth.php';

check_access('siswa');

$nis = $_SESSION['nis']; 

// 1. Ambil data Siswa untuk tampilan
$stmt_siswa = $pdo->prepare("SELECT nama_siswa FROM siswa WHERE nis = ?");
$stmt_siswa->execute([$nis]);
$siswa_data = $stmt_siswa->fetch();
$siswa_name = $siswa_data['nama_siswa'] ?? 'Siswa';

// 2. Ambil data Tagihan SPP (Status: Lunas/Belum)
$sql_spp = "
    SELECT 'SPP' AS tipe, spp_id AS id_tagihan, CONCAT('SPP Bulan ', bulan, ' Tahun ', tahun) AS deskripsi, 
           jumlah_spp AS jumlah, NULL AS tenggat_bayar, status_bayar
    FROM tagihan_spp
    WHERE nis = ?
    ORDER BY tahun ASC, bulan ASC
";
$stmt_spp = $pdo->prepare($sql_spp);
$stmt_spp->execute([$nis]);
$tagihan_spp = $stmt_spp->fetchAll();

// 3. Ambil data Tagihan Lain (Status: Lunas/Belum)
$sql_lain = "
    SELECT 'Lain' AS tipe, tl.detail_tagihan_id AS id_tagihan, jt.nama_tagihan AS deskripsi, 
           tl.jumlah_tagihan AS jumlah, tl.tenggat_bayar, tl.status_bayar
    FROM tagihan_lain tl
    JOIN jenis_tagihan jt ON tl.tagihan_id = jt.tagihan_id
    WHERE tl.nis = ?
    ORDER BY tl.tenggat_bayar ASC
";
$stmt_lain = $pdo->prepare($sql_lain);
$stmt_lain->execute([$nis]);
$tagihan_lain = $stmt_lain->fetchAll();

$semua_tagihan = array_merge($tagihan_spp, $tagihan_lain);


usort($semua_tagihan, function($a, $b) {
    
    if (strtolower($a['status_bayar']) !== strtolower($b['status_bayar'])) {
        return strtolower($a['status_bayar']) == 'belum' ? -1 : 1;
    }
   
    return ($a['tipe'] === 'SPP') ? -1 : 1;
});


$total_belum_lunas = array_sum(array_column(array_filter($semua_tagihan, function($t) {
    // Gunakan strtolower() di sini juga
    return strtolower($t['status_bayar']) == 'belum';
}), 'jumlah'));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Transkrip Tagihan - Siswa</title>
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; }
        .belum { background-color: #ffe0e0; color: #cc0000; font-weight: bold; }
        .lunas { background-color: #e0ffe0; color: #008000; }
        .total { font-size: 1.2em; font-weight: bold; }
    </style>
</head>
<body>
    <h2>Transkrip Tagihan Pembayaran</h2>
    <p>Siswa: <?= htmlspecialchars($siswa_name) ?> (NIS: <?= htmlspecialchars($nis) ?>) | <a href="index.php">Dashboard</a> | <a href="../logout.php">Logout</a></p>
    
    <hr>
    
    <p class="total">Total Kewajiban Belum Lunas: Rp <?= number_format($total_belum_lunas, 0, ',', '.') ?></p>
    
    <table>
        <thead>
            <tr>
                <th>Tipe</th>
                <th>Deskripsi Tagihan</th>
                <th>Jumlah (Rp)</th>
                <th>Tenggat Bayar</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($semua_tagihan)): ?>
                <tr><td colspan="6" style="text-align: center;">Tidak ada data tagihan.</td></tr>
            <?php endif; ?>

            <?php foreach ($semua_tagihan as $tagihan): ?>
            <tr class="<?= strtolower($tagihan['status_bayar']) ?>">
                <td><?= htmlspecialchars($tagihan['tipe']) ?></td>
                <td><?= htmlspecialchars($tagihan['deskripsi']) ?></td>
                <td><?= number_format($tagihan['jumlah'], 0, ',', '.') ?></td>
                <td>
                    <?php 
                        if ($tagihan['tenggat_bayar']) {
                            echo date('d F Y', strtotime($tagihan['tenggat_bayar']));
                        } else {
                            echo '-'; 
                        }
                    ?>
                </td>
                <td><?= ucfirst($tagihan['status_bayar']) ?></td>
                <td>
                    <?php 
                        
                        if (strtolower($tagihan['status_bayar']) == 'belum'): 
                    ?>
                        <a href="bayar.php?tipe=<?= $tagihan['tipe'] ?>&id=<?= $tagihan['id_tagihan'] ?>">Upload Bukti</a>
                    <?php elseif (strtolower($tagihan['status_bayar']) == 'menunggu_validasi'): ?>
                        Menunggu Konfirmasi Admin
                    <?php else: ?>
                        Lunas
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>