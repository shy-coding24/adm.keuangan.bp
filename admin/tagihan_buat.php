<?php
// admin/tagihan_buat.php
require_once '../config/koneksi.php';
require_once '../core/auth.php';

check_access('admin');

$error = '';
$success = '';

// Ambil data Jurusan dan Kelas untuk filter target siswa
try {
    $stmt_kelas = $pdo->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas");
    $kelas_list = $stmt_kelas->fetchAll(PDO::FETCH_COLUMN);

    $stmt_jurusan = $pdo->query("SELECT DISTINCT jurusan FROM siswa ORDER BY jurusan");
    $jurusan_list = $stmt_jurusan->fetchAll(PDO::FETCH_COLUMN);

    $stmt_jenis = $pdo->query("SELECT detail_tagihan_id, nama_tagihan FROM jenis_tagihan WHERE is_active = 1");
    $jenis_tagihan_list = $stmt_jenis->fetchAll();
} catch (PDOException $e) {
    $error = "Gagal memuat data master: " . $e->getMessage();
}


// --- FUNGSI UTAMA: PROSES BATCH INSERTION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipe_tagihan = $_POST['tipe_tagihan'] ?? '';
    $kelas_target = $_POST['kelas'] ?? 'all';
    $jurusan_target = $_POST['jurusan'] ?? 'all';

    // Query dasar untuk mengambil NIS target
    $sql_nis = "SELECT nis FROM siswa WHERE 1=1";
    $params = [];

    if ($kelas_target !== 'all') {
        $sql_nis .= " AND kelas = ?";
        $params[] = $kelas_target;
    }
    if ($jurusan_target !== 'all') {
        $sql_nis .= " AND jurusan = ?";
        $params[] = $jurusan_target;
    }
    
    // Ambil daftar NIS siswa target
    $stmt_nis = $pdo->prepare($sql_nis);
    $stmt_nis->execute($params);
    $nis_targets = $stmt_nis->fetchAll(PDO::FETCH_COLUMN);

    if (empty($nis_targets)) {
        $error = "Tidak ada siswa yang ditemukan dengan kriteria tersebut.";
    } elseif ($tipe_tagihan == 'spp') {
        
        // --- PROSES TAGIHAN SPP ---
        $bulan = $_POST['bulan'] ?? null;
        $tahun = $_POST['tahun'] ?? null;
        $jumlah_spp = (float)($_POST['jumlah_spp'] ?? 0);
        
        if (!$bulan || !$tahun || $jumlah_spp <= 0) {
            $error = "Semua field SPP harus diisi.";
        } else {
            try {
                $pdo->beginTransaction();
                $count = 0;
                
                // Cek duplikasi: mencegah SPP bulan yang sama dimasukkan 2x
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM tagihan_spp WHERE nis = ? AND bulan = ? AND tahun = ?");
                $stmt_insert = $pdo->prepare("
                    INSERT INTO tagihan_spp (nis, bulan, tahun, jumlah_spp, status_bayar) 
                    VALUES (?, ?, ?, ?, 'belum')
                ");

                foreach ($nis_targets as $nis) {
                    $stmt_check->execute([$nis, $bulan, $tahun]);
                    if ($stmt_check->fetchColumn() == 0) {
                        $stmt_insert->execute([$nis, $bulan, $tahun, $jumlah_spp]);
                        $count++;
                    }
                }
                
                $pdo->commit();
                $success = "Berhasil membuat $count tagihan SPP untuk bulan $bulan/$tahun. (Tagihan duplikat diabaikan).";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Gagal membuat tagihan SPP: " . $e->getMessage();
            }
        }
        
    } elseif ($tipe_tagihan == 'lain') {
        
        // --- PROSES TAGIHAN LAIN (Non-SPP) ---
        $tagihan_id = $_POST['tagihan_id'] ?? null;
        $jumlah_tagihan = (float)($_POST['jumlah_tagihan'] ?? 0);
        $tahun_ajaran = $_POST['tahun_ajaran'] ?? null;
        $tenggat_bayar = $_POST['tenggat_bayar'] ?? null;
        
        if (!$tagihan_id || $jumlah_tagihan <= 0 || !$tahun_ajaran || !$tenggat_bayar) {
             $error = "Semua field Tagihan Lain harus diisi.";
        } else {
            try {
                $pdo->beginTransaction();
                $count = 0;
                
                // Cek duplikasi: mencegah tagihan non-SPP yang sama dimasukkan 2x
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM tagihan_lain WHERE nis = ? AND tagihan_id = ? AND tahun_ajaran = ?");
                $stmt_insert = $pdo->prepare("
                    INSERT INTO tagihan_lain (nis, tagihan_id, jumlah_tagihan, tahun_ajaran, tenggat_bayar, status_bayar) 
                    VALUES (?, ?, ?, ?, ?, 'belum')
                ");

                foreach ($nis_targets as $nis) {
                    $stmt_check->execute([$nis, $tagihan_id, $tahun_ajaran]);
                    if ($stmt_check->fetchColumn() == 0) {
                        $stmt_insert->execute([$nis, $tagihan_id, $jumlah_tagihan, $tahun_ajaran, $tenggat_bayar]);
                        $count++;
                    }
                }
                
                $pdo->commit();
                $success = "Berhasil membuat $count tagihan non-SPP.";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Gagal membuat tagihan non-SPP: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Buat Tagihan Massal - Admin</title>
</head>
<body>
    <h2>Buat Tagihan Massal</h2>
    <p><a href="index.php">← Dashboard</a> | <a href="siswa_data.php">Data Siswa</a></p>
    
    <hr>
    
    <?php if ($success): ?>
        <p style="color: green; font-weight: bold;"><?= $success ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p style="color: red; font-weight: bold;"><?= $error ?></p>
    <?php endif; ?>

    <div style="display: flex; gap: 20px;">
        
        <fieldset style="flex: 1;">
            <legend>Target Siswa (Filter)</legend>
            <label for="kelas_target">Filter Kelas:</label>
            <select id="kelas_target" name="kelas_target" form="tagihan_spp_form,tagihan_lain_form">
                <option value="all">-- Semua Kelas --</option>
                <?php foreach ($kelas_list as $k): ?>
                    <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($k) ?></option>
                <?php endforeach; ?>
            </select><br><br>

            <label for="jurusan_target">Filter Jurusan:</label>
            <select id="jurusan_target" name="jurusan_target" form="tagihan_spp_form,tagihan_lain_form">
                <option value="all">-- Semua Jurusan --</option>
                <?php foreach ($jurusan_list as $j): ?>
                    <option value="<?= htmlspecialchars($j) ?>"><?= htmlspecialchars($j) ?></option>
                <?php endforeach; ?>
            </select>
        </fieldset>

        <fieldset style="flex: 1;">
            <legend>Tagihan SPP Bulanan</legend>
            <form id="tagihan_spp_form" method="post" action="">
                <input type="hidden" name="tipe_tagihan" value="spp">
                
                <label for="bulan">Bulan Tagihan:</label>
                <input type="number" id="bulan" name="bulan" min="1" max="12" required value="<?= date('n') ?>"><br><br>
                
                <label for="tahun">Tahun Tagihan:</label>
                <input type="number" id="tahun" name="tahun" min="<?= date('Y') - 1 ?>" max="<?= date('Y') + 1 ?>" required value="<?= date('Y') ?>"><br><br>

                <label for="jumlah_spp">Jumlah SPP (Rp):</label>
                <input type="number" id="jumlah_spp" name="jumlah_spp" step="1000" required><br><br>

                <button type="submit">Buat SPP Massal</button>
            </form>
        </fieldset>

        <fieldset style="flex: 1;">
            <legend>Tagihan Lain (Non-SPP)</legend>
            <form id="tagihan_lain_form" method="post" action="">
                <input type="hidden" name="tipe_tagihan" value="lain">
                
                <label for="tagihan_id">Jenis Tagihan:</label>
                <select id="tagihan_id" name="tagihan_id" required>
                    <option value="">-- Pilih Jenis --</option>
                    <?php foreach ($jenis_tagihan_list as $jt): ?>
                        <option value="<?= $jt['tagihan_id'] ?>"><?= htmlspecialchars($jt['nama_tagihan']) ?></option>
                    <?php endforeach; ?>
                </select><br><br>

                <label for="jumlah_tagihan">Jumlah Tagihan (Rp):</label>
                <input type="number" id="jumlah_tagihan" name="jumlah_tagihan" step="1000" required><br><br>
                
                <label for="tahun_ajaran">Tahun Ajaran:</label>
                <input type="text" id="tahun_ajaran" name="tahun_ajaran" value="<?= date('Y') . '/' . (date('Y') + 1) ?>" required><br><br>

                <label for="tenggat_bayar">Tenggat Bayar:</label>
                <input type="date" id="tenggat_bayar" name="tenggat_bayar" required><br><br>

                <button type="submit">Buat Tagihan Lain Massal</button>
            </form>
        </fieldset>
    </div>
</body>
</html>