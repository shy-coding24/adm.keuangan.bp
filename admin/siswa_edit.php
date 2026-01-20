<?php

require_once '../config/koneksi.php';
require_once '../core/auth.php';

check_access('admin');

$error = '';
$success = '';
$nis = $_GET['nis'] ?? null; 


try {
   
    $stmt_jurusan = $pdo->query("SELECT nama_jurusan FROM master_jurusan ORDER BY nama_jurusan");
    $jurusan_list = $stmt_jurusan->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {

    $error = "Gagal memuat data master Kelas/Jurusan: " . $e->getMessage();
    $kelas_list = [];
    $jurusan_list = [];
}



if ($nis) {
    try {
        $stmt_get = $pdo->prepare("SELECT * FROM siswa WHERE nis = ?");
        $stmt_get->execute([$nis]);
        $siswa = $stmt_get->fetch();
        
        if (!$siswa) {
            $error = "Data siswa dengan NIS $nis tidak ditemukan.";
            $nis = null; 
        }
    } catch (PDOException $e) {
        $error = "Gagal mengambil data: " . $e->getMessage();
    }
} else {
    $error = "NIS tidak ditentukan.";
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
 
    $nis_update = trim($_POST['nis'] ?? null); 
    
    
    if (!$nis_update) {
        $error = "NIS tidak ditemukan dalam data POST, gagal update.";
    }

    $nisn = trim($_POST['nisn'] ?? '');
    $nama_siswa = trim($_POST['nama_siswa'] ?? '');
    $tgl_lahir = trim($_POST['tgl_lahir'] ?? null);
    $kelas = trim($_POST['kelas'] ?? ''); 
    $jurusan = trim($_POST['jurusan'] ?? ''); 
    $wali_kelas = trim($_POST['wali_kelas'] ?? null);
    $tahun_pelajaran = trim($_POST['tahun_pelajaran'] ?? null);
    $no_telp = trim($_POST['no_telp'] ?? null);
    $email = trim($_POST['email'] ?? null);

    
    if (empty($nama_siswa) || empty($kelas) || empty($jurusan)) {
        $error = "Nama, Kelas, dan Jurusan wajib diisi.";
    }

    if (!$error && $nis_update) { 
        try {
           
            $stmt_update = $pdo->prepare("
                UPDATE siswa 
                SET nisn=?, nama_siswa=?, tgl_lahir=?, kelas=?, jurusan=?, wali_kelas=?, tahun_pelajaran=?, no_telp=?, email=?
                WHERE nis=?
            ");
            $stmt_update->execute([
                $nisn, $nama_siswa, $tgl_lahir, $kelas, $jurusan, 
                $wali_kelas, $tahun_pelajaran, $no_telp, $email, $nis_update
            ]);

            $success = "Data siswa $nama_siswa berhasil diperbarui.";
            
   
            $stmt_get = $pdo->prepare("SELECT * FROM siswa WHERE nis = ?");
            $stmt_get->execute([$nis_update]);
            $siswa = $stmt_get->fetch();
          
            $nis = $nis_update;


        } catch (PDOException $e) {
            $error = "Gagal memperbarui data: " . $e->getMessage() . ". Mohon periksa NISN/Email (mungkin sudah ada).";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Siswa - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --dark: #212529;
            --light: #f8f9fa;
            --gray: #6c757d;
            --border: #dee2e6;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fb;
            color: var(--dark);
            line-height: 1.6;
            padding: 0;
            margin: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header Styles */
        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 20px 0;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header h1 i {
            font-size: 1.5rem;
        }

        .breadcrumb {
            display: flex;
            gap: 10px;
            font-size: 0.9rem;
        }

        .breadcrumb a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb a:hover {
            color: white;
        }

        .breadcrumb span {
            color: white;
        }

        /* Card Styles */
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
            overflow: hidden;
        }

        .card-header {
            background-color: var(--light);
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            font-size: 1.4rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 20px;
        }

        /* Alert Styles */
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background-color: rgba(76, 201, 240, 0.15);
            color: #0c5460;
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background-color: rgba(247, 37, 133, 0.15);
            color: #721c24;
            border-left: 4px solid var(--danger);
        }

        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 16px;
            padding-right: 40px;
        }

        fieldset {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }

        legend {
            font-weight: 600;
            color: var(--primary);
            padding: 0 10px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .btn-outline:hover {
            background-color: var(--primary);
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            justify-content: flex-end;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

    
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-info {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="header-content">
                <h1><i class="fas fa-user-edit"></i> Edit Data Siswa</h1>
                <div class="breadcrumb">
                    <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
                    <span>></span>
                    <a href="siswa_data.php"><i class="fas fa-users"></i> Daftar Siswa</a>
                    <span>></span>
                    <span>Edit Siswa</span>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($siswa): ?>
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-user-graduate"></i> Form Edit Data Siswa</h2>
                <div class="badge badge-info">NIS: <?= htmlspecialchars($nis ?? '') ?></div>
            </div>
            <div class="card-body">
                <form method="post" action="" id="editSiswaForm">
                    <input type="hidden" name="nis" value="<?= htmlspecialchars($nis ?? '') ?>">
                    
                    <fieldset>
                        <legend><i class="fas fa-id-card"></i> Data Pokok Siswa</legend>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nisn">NISN</label>
                                <input type="text" id="nisn" name="nisn" class="form-control" value="<?= htmlspecialchars($siswa['nisn'] ?? '') ?>" placeholder="Masukkan NISN">
                            </div>
                            
                            <div class="form-group">
                                <label for="nama_siswa">Nama Lengkap Siswa <span style="color: var(--danger)">*</span></label>
                                <input type="text" id="nama_siswa" name="nama_siswa" class="form-control" required value="<?= htmlspecialchars($siswa['nama_siswa'] ?? '') ?>" placeholder="Masukkan nama lengkap">
                            </div>
                            
                            <div class="form-group">
                                <label for="tgl_lahir">Tanggal Lahir</label>
                                <input type="date" id="tgl_lahir" name="tgl_lahir" class="form-control" value="<?= htmlspecialchars($siswa['tgl_lahir'] ?? '') ?>">
                            </div>
                        </div>
                    </fieldset>
                    
                    <fieldset>
                        <legend><i class="fas fa-graduation-cap"></i> Data Akademik & Kontak</legend>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="kelas">Kelas <span style="color: var(--danger)">*</span></label>
                                <select id="kelas" name="kelas" class="form-control" required>
                                    <option value="">Pilih Kelas</option>
                                    <?php 
                                        $selected_kelas = $_POST['kelas'] ?? $siswa['kelas']; 
                                    ?>
                                    <option value="X" <?= ($selected_kelas == 'X') ? 'selected' : '' ?>>X</option>
                                    <option value="XI" <?= ($selected_kelas == 'XI') ? 'selected' : '' ?>>XI</option>
                                    <option value="XII" <?= ($selected_kelas == 'XII') ? 'selected' : '' ?>>XII</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="jurusan">Jurusan <span style="color: var(--danger)">*</span></label>
                                <select id="jurusan" name="jurusan" class="form-control" required>
                                    <option value="">Pilih Jurusan</option>
                                    <?php 
                                        $selected_jurusan = $_POST['jurusan'] ?? $siswa['jurusan'];
                                        foreach ($jurusan_list as $j): 
                                    ?>
                                        <option value="<?= htmlspecialchars($j) ?>" <?= ($selected_jurusan == $j) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($j) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="wali_kelas">Wali Kelas</label>
                                <input type="text" id="wali_kelas" name="wali_kelas" class="form-control" value="<?= htmlspecialchars($siswa['wali_kelas'] ?? '') ?>" placeholder="Nama wali kelas">
                            </div>
                            
                            <div class="form-group">
                                <label for="tahun_pelajaran">Tahun Pelajaran</label>
                                <input type="text" id="tahun_pelajaran" name="tahun_pelajaran" class="form-control" value="<?= htmlspecialchars($siswa['tahun_pelajaran'] ?? '') ?>" placeholder="Contoh: 2024/2025">
                            </div>
                            
                            <div class="form-group">
                                <label for="no_telp">No. Telepon (WhatsApp)</label>
                                <input type="text" id="no_telp" name="no_telp" class="form-control" value="<?= htmlspecialchars($siswa['no_telp'] ?? '') ?>" placeholder="Contoh: 08123456789">
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($siswa['email'] ?? '') ?>" placeholder="Contoh: siswa@email.com">
                            </div>
                        </div>
                    </fieldset>
                    
                    <div class="action-buttons">
                        <a href="siswa_data.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Menambahkan efek loading saat form disubmit
        document.getElementById('editSiswaForm').addEventListener('submit', function() {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<div class="loading"></div> Menyimpan...';
        });

        // Validasi form sederhana
        document.getElementById('editSiswaForm').addEventListener('submit', function(e) {
            const namaSiswa = document.getElementById('nama_siswa').value.trim();
            const kelas = document.getElementById('kelas').value;
            const jurusan = document.getElementById('jurusan').value;
            
            if (!namaSiswa || !kelas || !jurusan) {
                e.preventDefault();
                alert('Mohon lengkapi semua field yang wajib diisi (Nama, Kelas, dan Jurusan)');
                document.getElementById('submitBtn').disabled = false;
                document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Simpan Perubahan';
            }
        });
    </script>
</body>
</html>