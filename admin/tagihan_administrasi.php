<?php
// admin/tagihan_administrasi.php (Penerapan Tagihan Administrasi ke Siswa)
require_once '../config/koneksi.php';
require_once '../core/auth.php';

check_access('admin');

$error = '';
$success = '';
$tagihan_dikenakan_list = []; 

// Fungsi untuk membuat link WhatsApp (Deep Link) - (Kode sama seperti sebelumnya)
function create_whatsapp_link($no_telp, $message) {
    $no_telp = preg_replace('/[^0-9]/', '', $no_telp);
    $no_telp = preg_replace('/^0/', '62', $no_telp);
    $encoded_message = urlencode($message);
    return "https://wa.me/{$no_telp}?text={$encoded_message}";
}

// 1. Ambil daftar Jenis Tagihan Non-SPP
try {
    $stmt_jenis = $pdo->query("SELECT tagihan_id, nama_tagihan, jumlah_default FROM jenis_tagihan ORDER BY nama_tagihan");
    $jenis_tagihan_list = $stmt_jenis->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handling error jika table jenis_tagihan belum lengkap
    $error = "Gagal memuat jenis tagihan: Pastikan tabel jenis_tagihan sudah memiliki kolom 'jumlah_default' dan 'tipe_tagihan'.";
    $jenis_tagihan_list = [];
}

// 2. Proses Penerapan Tagihan (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tagihan_id = filter_var($_POST['tagihan_id'] ?? null, FILTER_VALIDATE_INT);
    $target_type = $_POST['target_type'] ?? 'individual'; // <-- BARU: Tipe target
    $nis_list_input = trim($_POST['nis_list'] ?? '');
    $jumlah_tagihan_input = filter_var($_POST['jumlah_tagihan'] ?? 0, FILTER_VALIDATE_FLOAT);
    $tenggat_bayar = trim($_POST['tenggat_bayar'] ?? null);
    $template_chat = trim($_POST['template_chat'] ?? '');
    
    $nis_array = [];

    // Logika Pengambilan NIS Berdasarkan Target Type
    if ($target_type === 'all') {
        // Ambil SEMUA NIS dari tabel siswa
        try {
            $stmt_all_siswa = $pdo->query("SELECT nis FROM siswa");
            $nis_array = $stmt_all_siswa->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            $error = "Gagal mengambil daftar semua siswa.";
        }
    } else {
        // Ambil NIS dari input teks (individual/manual list)
        $nis_array = array_filter(array_map('trim', preg_split("/[\s,]+/", $nis_list_input)));
    }
    
    // Validasi Dasar
    if (!$tagihan_id || empty($nis_array) || $jumlah_tagihan_input <= 0 || empty($tenggat_bayar)) {
        if (empty($nis_array) && $target_type === 'individual') {
             $error = "NIS/No. Telp siswa target wajib diisi.";
        } else if (empty($nis_array) && $target_type === 'all') {
             $error = "Gagal. Tidak ada siswa yang ditemukan di database.";
        } else {
            $error = "Semua field wajib diisi.";
        }
    }

    if (!$error) {
        // ... (Sisa logika INSERT ke tagihan_lain, sama seperti sebelumnya) ...
        // START: Sisa logika INSERT
        $tahun_ajaran = date('Y') . '/' . (date('Y') + 1);
        $count_success = 0;
        
        try {
            $nama_tagihan_stmt = $pdo->prepare("SELECT nama_tagihan FROM jenis_tagihan WHERE tagihan_id = ?");
            $nama_tagihan_stmt->execute([$tagihan_id]);
            $nama_tagihan = $nama_tagihan_stmt->fetchColumn();

            $sql_insert = "
                INSERT INTO tagihan_lain 
                (nis, tagihan_id, jumlah_tagihan, tahun_ajaran, tenggat_bayar, status_bayar) 
                VALUES (?, ?, ?, ?, ?, 'belum')
            ";
            $stmt_insert = $pdo->prepare($sql_insert);

            $sql_siswa = "SELECT nis, nama_siswa, no_telp FROM siswa WHERE nis = ?";
            $stmt_siswa = $pdo->prepare($sql_siswa);

            foreach ($nis_array as $nis_target) {
                $stmt_siswa->execute([$nis_target]);
                $siswa = $stmt_siswa->fetch(PDO::FETCH_ASSOC);

                if ($siswa) {
                    $check_sql = "SELECT COUNT(*) FROM tagihan_lain WHERE nis = ? AND tagihan_id = ? AND status_bayar = 'belum'";
                    $check_stmt = $pdo->prepare($check_sql);
                    $check_stmt->execute([$nis_target, $tagihan_id]);

                    if ($check_stmt->fetchColumn() == 0) {
                        $stmt_insert->execute([
                            $nis_target, $tagihan_id, $jumlah_tagihan_input, $tahun_ajaran, $tenggat_bayar
                        ]);
                        $count_success++;
                        
                        $siswa['tagihan_nama'] = $nama_tagihan;
                        $siswa['tagihan_jumlah'] = $jumlah_tagihan_input;
                        $siswa['tagihan_tenggat'] = $tenggat_bayar;
                        $tagihan_dikenakan_list[] = $siswa;
                    } 
                }
            }

            $success = "Berhasil mengenakan tagihan '{$nama_tagihan}' kepada {$count_success} siswa.";
            if (count($nis_array) > $count_success) {
                $error = (empty($error) ? "" : $error . " ") . "Beberapa siswa sudah memiliki tagihan ini, NIS tidak ditemukan, atau target siswa tidak valid.";
            }

        } catch (PDOException $e) {
            $error = "Gagal menerapkan tagihan: " . $e->getMessage();
        }
        // END: Sisa logika INSERT
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Tagihan Administrasi - Admin</title>
    <style>
        /* CSS Dasar */
        body { font-family: Arial, sans-serif; margin: 20px; }
        form { max-width: 900px; padding: 20px; border: 1px solid #ccc; margin-bottom: 20px; }
        input[type="text"], input[type="number"], input[type="date"], select, textarea { width: 100%; padding: 8px; box-sizing: border-box; margin-top: 5px; margin-bottom: 10px; }
        button { padding: 10px 15px; background-color: #007bff; color: white; border: none; cursor: pointer; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .wa-link { text-decoration: none; background-color: #25D366; color: white; padding: 5px 10px; border-radius: 4px; display: inline-block; }
        .grid-form { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        /* Style untuk menyembunyikan/menampilkan NIS list */
        #nis_list_container.hidden {
            display: none;
        }
    </style>
</head>
<body>
    <h2>Tagihan Administrasi (Non-SPP)</h2>
    <p><a href="index.php">← Dashboard</a> | <a href="tagihan_jenis.php">Manajemen Jenis Tagihan</a></p>
    <hr>
    
    <?php if ($success): ?>
        <p class="success"><?= $success ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <form method="POST" action="">
        <h3>Detail Tagihan yang Dikenakan</h3>
        <div class="grid-form">
            <div>
                <label for="tagihan_id">Pilih Jenis Tagihan:</label>
                <select id="tagihan_id" name="tagihan_id" required>
                    <option value=""> Pilih Jenis Tagihan </option>
                    <?php foreach ($jenis_tagihan_list as $jt): ?>
                        <option 
                            value="<?= $jt['tagihan_id'] ?>" 
                            data-jumlah="<?= $jt['jumlah_default'] ?>"
                            <?= (isset($_POST['tagihan_id']) && $_POST['tagihan_id'] == $jt['tagihan_id']) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($jt['nama_tagihan']) ?> (Rp <?= number_format($jt['jumlah_default'], 0, ',', '.') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <label for="jumlah_tagihan">Jumlah Tagihan Akhir (Rp):</label>
                <input type="number" id="jumlah_tagihan" name="jumlah_tagihan" step="1000" min="1000" required 
                       value="<?= htmlspecialchars($_POST['jumlah_tagihan'] ?? '') ?>">

                <label for="tenggat_bayar">Tenggat Pembayaran:</label>
                <input type="date" id="tenggat_bayar" name="tenggat_bayar" required 
                       value="<?= htmlspecialchars($_POST['tenggat_bayar'] ?? date('Y-m-d', strtotime('+1 month'))) ?>">
            </div>
            <div>
                <label for="target_type">Pilih Target Siswa:</label>
                <select id="target_type" name="target_type" onchange="toggleNisInput()">
                    <option value="individual" <?= (isset($_POST['target_type']) && $_POST['target_type'] == 'individual') ? 'selected' : '' ?>>Target Beberapa Siswa</option>
                    <option value="all" <?= (isset($_POST['target_type']) && $_POST['target_type'] == 'all') ? 'selected' : '' ?>>SEMUA SISWA</option>
                </select>
                
                <div id="nis_list_container" class="<?= (isset($_POST['target_type']) && $_POST['target_type'] == 'all') ? 'hidden' : '' ?>">
                    <label for="nis_list">NIS Siswa (Pisahkan dengan koma atau baris baru):</label>
                    <textarea id="nis_list" name="nis_list" rows="6"><?= htmlspecialchars($_POST['nis_list'] ?? '') ?></textarea>
                    <p style="font-size: 0.8em; color: #666;">Contoh: 10121001, 103455, 105678</p>
                </div>
            </div>
        </div>

        <h3 style="margin-top: 30px;">Notifikasi WhatsApp</h3>
        <label for="template_chat">Pesan WhatsApp (gunakan [NAMA_TAGIHAN], [JUMLAH], [TENGGAT]):</label>
        <textarea id="template_chat" name="template_chat" rows="5" required>Yth. Wali Murid/Siswa, Anda dikenakan tagihan [NAMA_TAGIHAN] sebesar Rp [JUMLAH]. Batas pembayaran: [TENGGAT]. Mohon segera dicek di portal siswa. Terima kasih.</textarea>

        <button type="submit" style="margin-top: 20px;">Simpan & Siapkan Notifikasi</button>
    </form>
    
    <?php if (!empty($tagihan_dikenakan_list)): ?>
        <?php endif; ?>

    <script>
        // JS untuk mengisi Jumlah Tagihan Otomatis
        document.getElementById('tagihan_id').addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            var defaultAmount = selectedOption.getAttribute('data-jumlah');
            if (defaultAmount) {
                document.getElementById('jumlah_tagihan').value = defaultAmount;
            }
        });
        
        // JS untuk menyembunyikan/menampilkan input NIS
        function toggleNisInput() {
            var targetType = document.getElementById('target_type').value;
            var nisContainer = document.getElementById('nis_list_container');
            var allInfo = document.getElementById('target_all_info');

            if (targetType === 'all') {
                nisContainer.classList.add('hidden');
                allInfo.classList.remove('hidden');
            } else {
                nisContainer.classList.remove('hidden');
                allInfo.classList.add('hidden');
            }
        }
        
        // Panggil saat load untuk memastikan tampilan awal benar
        window.onload = toggleNisInput; 
    </script>
</body>
</html>