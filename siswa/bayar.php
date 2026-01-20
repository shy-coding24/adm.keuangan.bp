<?php
// siswa/bayar.php
require_once '../config/koneksi.php';
require_once '../core/auth.php';

check_access('siswa');

$nis = $_SESSION['nis'];
$tipe = $_GET['tipe'] ?? null;
$id_tagihan = $_GET['id'] ?? null; // ID ini adalah spp_id atau detail_tagihan_id

$error = '';
$success = '';
$tagihan_detail = null;

// --- 1. Ambil Detail Tagihan ---
if (!$tipe || !$id_tagihan) {
    $error = "Tagihan tidak valid atau tidak ditemukan.";
} else {
    try {
        if ($tipe == 'SPP') {
            // Menggunakan spp_id
            $stmt = $pdo->prepare("
                SELECT jumlah_spp AS jumlah, CONCAT('SPP Bulan ', bulan, ' Tahun ', tahun) AS deskripsi
                FROM tagihan_spp 
                WHERE spp_id = ? AND nis = ? AND status_bayar = 'belum'
            ");
            $stmt->execute([$id_tagihan, $nis]);
        } elseif ($tipe == 'Lain') {
            // Menggunakan detail_tagihan_id
            $stmt = $pdo->prepare("
                SELECT tl.jumlah_tagihan AS jumlah, jt.nama_tagihan AS deskripsi
                FROM tagihan_lain tl
                JOIN jenis_tagihan jt ON tl.tagihan_id = jt.tagihan_id
                WHERE tl.detail_tagihan_id = ? AND tl.nis = ? AND tl.status_bayar = 'belum'
            ");
            $stmt->execute([$id_tagihan, $nis]);
        }
        
        $tagihan_detail = $stmt->fetch();

        if (!$tagihan_detail) {
            $error = "Tagihan sudah lunas atau tidak ditemukan.";
        }

    } catch (PDOException $e) {
        $error = "Gagal mengambil detail tagihan: " . $e->getMessage();
    }
}


// --- 2. Proses Form Submission ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $tagihan_detail && !$error) {
    $jumlah_bayar = (float)($_POST['jumlah_bayar'] ?? 0);
    $tgl_transfer = $_POST['tgl_transfer'] ?? date('Y-m-d');
    
    // Validasi jumlah bayar
    if ($jumlah_bayar < $tagihan_detail['jumlah']) {
        $error = "Jumlah bayar tidak boleh kurang dari jumlah tagihan (" . number_format($tagihan_detail['jumlah']) . ").";
    }

    // Proses Upload File
    if (!$error && isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] == 0) {
        $file = $_FILES['bukti_transfer'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $max_size = 5 * 1024 * 1024; // 5 MB

        if (!in_array($file['type'], $allowed_types)) {
            $error = "Format file tidak didukung. Hanya JPEG, PNG, atau JPG.";
        } elseif ($file['size'] > $max_size) {
            $error = "Ukuran file terlalu besar (Maksimal 5MB).";
        }

        if (!$error) {
            // Tentukan lokasi penyimpanan dan nama unik
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $nama_file_unik = $nis . '_' . $tipe . '_' . $id_tagihan . '_' . time() . '.' . $file_extension;
            $upload_dir = '../uploads/bukti_bayar/';
            $target_path = $upload_dir . $nama_file_unik;

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                
                // --- INSERT KE TRANSAKSI PEMBAYARAN ---
                $stmt_insert = $pdo->prepare("
                    INSERT INTO transaksi_pembayaran (nis, tipe_tagihan, related_tagihan_id, tgl_transfer, jumlah_bayar, bukti_bayar, status_validasi) 
                    VALUES (?, ?, ?, ?, ?, ?, 'menunggu')
                ");
                
                if ($stmt_insert->execute([$nis, $tipe, $id_tagihan, $tgl_transfer, $jumlah_bayar, $nama_file_unik])) {
                    $success = "Bukti pembayaran berhasil diunggah dan sedang menunggu validasi Admin. Silakan tunggu konfirmasi.";
                    
                    // Setelah upload, ubah status tagihan menjadi 'menunggu_validasi'
                    $table = ($tipe == 'SPP') ? 'tagihan_spp' : 'tagihan_lain';
                    $id_column = ($tipe == 'SPP') ? 'spp_id' : 'detail_tagihan_id';
                    
                    $stmt_update_tagihan = $pdo->prepare("
                        UPDATE $table SET status_bayar = 'menunggu_validasi' WHERE $id_column = ?
                    ");
                    $stmt_update_tagihan->execute([$id_tagihan]);


                } else {
                    $error = "Gagal menyimpan data transaksi ke database.";
                    // Hapus file yang sudah terupload jika insert gagal
                    unlink($target_path); 
                }

            } else {
                $error = "Gagal memindahkan file yang diunggah.";
            }
        }
    } else if (!$error) {
        $error = "Anda harus mengunggah bukti transfer.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Upload Bukti Pembayaran</title>
</head>
<body>
    <h2>Upload Bukti Pembayaran</h2>
    <p><a href="tagihan.php">← Kembali ke Transkrip Tagihan</a> | <a href="index.php">Dashboard</a></p>
    
    <hr>
    
    <?php if ($success): ?>
        <p style="color: green; font-weight: bold;"><?= $success ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p style="color: red; font-weight: bold;"><?= $error ?></p>
    <?php endif; ?>

    <?php if ($tagihan_detail && !$success): // Hanya tampilkan form jika detail ada dan belum sukses ?>
    <h3>Detail Tagihan yang Dibayar</h3>
    <ul>
        <li>Tagihan: <?= htmlspecialchars($tagihan_detail['deskripsi']) ?></li>
        <li>Jumlah Wajib Bayar: Rp <?= number_format($tagihan_detail['jumlah'], 0, ',', '.') ?></li>
    </ul>

    <form method="post" action="" enctype="multipart/form-data">
        <fieldset>
            <legend>Informasi Pembayaran</legend>
            <label for="jumlah_bayar">Jumlah yang Dibayarkan (Rp):</label>
            <input type="number" id="jumlah_bayar" name="jumlah_bayar" required 
                   value="<?= $tagihan_detail['jumlah'] ?>" min="<?= $tagihan_detail['jumlah'] ?>" step="1000"><br><br>
            
            <label for="tgl_transfer">Tanggal Transfer:</label>
            <input type="date" id="tgl_transfer" name="tgl_transfer" required value="<?= date('Y-m-d') ?>"><br><br>

            <label for="bukti_transfer">Upload Bukti Transfer (JPG/PNG, Max 5MB):</label>
            <input type="file" id="bukti_transfer" name="bukti_transfer" accept="image/jpeg,image/png" required><br><br>
        </fieldset>
        <br>
        <button type="submit">Konfirmasi Pembayaran & Upload Bukti</button>
    </form>
    <?php elseif (!$error && !$success): ?>
        <p>Gagal memuat detail tagihan.</p>
    <?php endif; ?>
</body>
</html>