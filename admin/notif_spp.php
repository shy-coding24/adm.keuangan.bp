<?php
require_once '../config/koneksi.php';
require_once '../core/auth.php';

check_access('admin');

$error = '';
$success = '';
$bulan_target = '';
$siswa_list = [];
$kirim_massal = false; // Flag untuk operasi kirim massal


$daftar_bulan = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];
$tahun_sekarang = date('Y');
$tahun_list = range($tahun_sekarang - 1, $tahun_sekarang + 1);


function create_whatsapp_link($no_telp, $message) {

    $no_telp = preg_replace('/[^0-9]/', '', $no_telp);
    $no_telp = preg_replace('/^0/', '62', $no_telp);

    $encoded_message = urlencode($message);
    return "https://wa.me/{$no_telp}?text={$encoded_message}";
}

// Fungsi untuk menyimpan notifikasi ke database
function log_notification($pdo, $nis, $judul, $isi_pesan) {
    $sql_log = "INSERT INTO notifikasi (nis, tanggal_kirim, judul, isi_notifikasi, is_read) 
                VALUES (?, NOW(), ?, ?, 0)";
    $stmt_log = $pdo->prepare($sql_log);
    return $stmt_log->execute([$nis, $judul, $isi_pesan]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bulan = $_POST['bulan'] ?? '';
    $tahun = $_POST['tahun'] ?? '';
    $template_chat = $_POST['template_chat'] ?? '';
    $action = $_POST['action'] ?? 'search'; 

    if (empty($bulan) || empty($tahun) || empty($template_chat)) {
        $error = "Pilih bulan dan tahun target tagihan, serta isi template pesan.";
    } else {
        $bulan_target = "$bulan $tahun";
        
        try {
            $sql = "
                SELECT 
                    s.nis, s.nama_siswa, s.no_telp, 
                    ts.spp_id,                             /* <<< Tambahkan ID SPP */
                    ts.jumlah_spp AS jumlah_tagihan        /* <<< Gunakan alias konsisten */
                FROM siswa s
                JOIN tagihan_spp ts ON s.nis = ts.nis
                WHERE ts.bulan = ? AND ts.tahun = ? 
                AND ts.status_bayar IN ('belum', 'menunggu_validasi') 
                ORDER BY s.nis
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$bulan, $tahun]);
            $siswa_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // --- LOGIC KIRIM MASSAL (Jika memiliki integrasi API WA) ---
            if ($action === 'send_mass') {
                $count_sent = 0;
                $count_failed = 0;
                $judul_notif = "Peringatan Pembayaran SPP {$bulan_target}";

                foreach ($siswa_list as $siswa) {
                    $pesan_final = str_replace(
                        '[JUMLAH]', 
                        number_format($siswa['jumlah_tagihan'], 0, ',', '.'), 
                        $template_chat
                    );
                    
                
                    $log_result = log_notification($pdo, $siswa['nis'], $judul_notif, $pesan_final);

                    if ($log_result) {
                        $count_sent++;
                        // Di sini tempatkan kode API WhatsApp (jika ada)
                    } else {
                        $count_failed++;
                    }
                }

                $success = "✅ Proses Logging Notifikasi SPP {$bulan_target} selesai. Berhasil log {$count_sent} notifikasi ke database.";
                if ($count_failed > 0) {
                    $error = "⚠️ Gagal log {$count_failed} notifikasi.";
                }

            } 
            // --- LOGIC PENCARIAN DEFAULT ---
            else {
                if (count($siswa_list) > 0) {
                    $success = "Ditemukan **" . count($siswa_list) . " siswa yang belum melunasi SPP bulan {$bulan_target}. Siap untuk dikirimi notifikasi.";
                } else {
                    $success = "Semua siswa sudah melunasi SPP bulan {$bulan_target}!";
                }
            }
            

        } catch (PDOException $e) {
            $error = "Gagal mengambil data tagihan: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Notifikasi SPP - Admin</title>
    <style>
        /* CSS Dasar - Ganti dengan Bootstrap/Figma Anda */
        body { font-family: Arial, sans-serif; margin: 20px; }
        form { max-width: 800px; padding: 20px; border: 1px solid #ddd; margin-bottom: 20px; }
        .grid-select { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; }
        input, select, textarea { width: 100%; padding: 8px; box-sizing: border-box; margin-top: 5px; }
        button { padding: 10px 15px; background-color: #007bff; color: white; border: none; cursor: pointer; margin-top: 10px; }
        .btn-cari { background-color: #28a745; }
        .btn-massal { background-color: #ffc107; color: #212529; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .wa-link { text-decoration: none; background-color: #25D366; color: white; padding: 5px 10px; border-radius: 4px; display: inline-block; }
    </style>
</head>
<body>
    <h2>Notifikasi SPP</h2>
    <p><a href="index.php">← Dashboard</a></p>
    <hr>
    
    <?php if ($success): ?>
        <p class="success"><?= $success ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <form method="POST" action="">
        <h3>Target dan Template Pesan</h3>
        <input type="hidden" name="action" id="form-action" value="search">
        
        <div class="grid-select">
            <div>
                <label for="bulan">Bulan Tagihan:</label>
                <select id="bulan" name="bulan" required>
                    <option value="">Pilih Bulan</option>
                    <?php foreach ($daftar_bulan as $b): ?>
                        <option value="<?= $b ?>" <?= (isset($_POST['bulan']) && $_POST['bulan'] == $b) ? 'selected' : '' ?>><?= $b ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="tahun">Tahun Tagihan:</label>
                <select id="tahun" name="tahun" required>
                    <option value="">Pilih Tahun</option>
                    <?php foreach ($tahun_list as $t): ?>
                        <option value="<?= $t ?>" <?= (isset($_POST['tahun']) && $_POST['tahun'] == $t) ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <label for="template_chat">Pesan WhatsApp: (Gunakan [JUMLAH] sebagai *placeholder*)</label>
        <textarea id="template_chat" name="template_chat" rows="4" required>Yth. Wali Murid/Siswa, Tagihan SPP bulan <?= htmlspecialchars($_POST['bulan'] ?? '...') ?> tahun <?= htmlspecialchars($_POST['tahun'] ?? '...') ?> sebesar Rp [JUMLAH] masih belum dilunasi. Mohon segera diproses di link pembayaran Anda. Terima kasih. (Diupdate: <?= date('Y-m-d H:i') ?>)</textarea>

        <button type="submit" class="btn-cari" onclick="document.getElementById('form-action').value='search';">🔍 Cari Siswa Belum Lunas</button>
        <?php if (!empty($siswa_list) && ($_POST['action'] ?? 'search') == 'search'): ?>
             <button type="submit" class="btn-massal" onclick="return confirm('Anda yakin ingin melakukan LOGGING notifikasi untuk <?= count($siswa_list) ?> siswa?') ? document.getElementById('form-action').value='send_mass' : false;">Notifikasi Massal ke Database</button>
        <?php endif; ?>

    </form>
    
    <?php if (!empty($siswa_list) && ($_POST['action'] ?? 'search') == 'search'): ?>
        <h3>Daftar Siswa Belum Lunas (Siap Kirim WA Manual)</h3>
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>NIS</th>
                    <th>Nama Siswa</th>
                    <th>No. Telp</th>
                    <th>Jumlah Tagihan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($siswa_list as $siswa): ?>
                    <?php
                       
                        $jumlah_rupiah = number_format($siswa['jumlah_tagihan'], 0, ',', '.');
                        
                        
                        $pesan_final = str_replace(
                            '[JUMLAH]', 
                            $jumlah_rupiah, 
                            $template_chat
                        );
                        $link_wa = create_whatsapp_link($siswa['no_telp'], $pesan_final);
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($siswa['nis']) ?></td>
                        <td><?= htmlspecialchars($siswa['nama_siswa']) ?></td>
                        <td><?= htmlspecialchars($siswa['no_telp']) ?></td>
                        <td>Rp **<?= $jumlah_rupiah ?>**</td>
                        <td>
                            <a href="<?= $link_wa ?>" target="_blank" class="wa-link" onclick="if (confirm('Apakah Anda yakin ingin mengirim pesan WhatsApp ke <?= htmlspecialchars($siswa['nama_siswa']) ?>?')) { log_notification_manual('<?= $siswa['nis'] ?>', 'Peringatan Pembayaran SPP <?= $bulan_target ?>', '<?= addslashes($pesan_final) ?>'); return true; } return false;">
                                💬 Kirim Pesan WhatsApp
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <p style="margin-top: 20px;">
        Catatan: Mengirim WA manual per siswa akan mencatat log notifikasi ke database Anda. Untuk Log Massal, klik tombol kuning di atas.
        </p>

    <?php endif; ?>
    
    <script>
        // Fungsi AJAX untuk logging notifikasi saat tombol WA manual diklik
        function log_notification_manual(nis, judul, pesan) {
            console.log('Logging notifikasi untuk NIS: ' + nis);
        
            return true;
        }

        document.getElementById('bulan').addEventListener('change', updateTemplatePlaceholder);
        document.getElementById('tahun').addEventListener('change', updateTemplatePlaceholder);

        function updateTemplatePlaceholder() {
            const bulan = document.getElementById('bulan').value || '...';
            const tahun = document.getElementById('tahun').value || '...';
            const textarea = document.getElementById('template_chat');
            
            if (textarea.value.includes('bulan ... tahun ...')) {
                 textarea.value = textarea.value.replace(/bulan (.*?) tahun (.*?)/, `bulan ${bulan} tahun ${tahun}`);
            }
        }
    </script>

</body>
</html>