<?php
require_once '../config/koneksi.php';
require_once '../core/auth.php';

check_access('admin');

$error = '';
$success = '';


try {
    
    $stmt_jurusan = $pdo->query("SELECT nama_jurusan FROM master_jurusan ORDER BY nama_jurusan");
    $jurusan_list = $stmt_jurusan->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    
    $error = "Gagal memuat data master Jurusan: " . $e->getMessage();
    $jurusan_list = [];
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $nis = trim($_POST['nis'] ?? '');
    $nisn = trim($_POST['nisn'] ?? '');
    $nama_siswa = trim($_POST['nama_siswa'] ?? '');
    $tgl_lahir = trim($_POST['tgl_lahir'] ?? null);
    $kelas = trim($_POST['kelas'] ?? ''); 
    $jurusan = trim($_POST['jurusan'] ?? ''); 
    $wali_kelas = trim($_POST['wali_kelas'] ?? null);
    $tahun_pelajaran = trim($_POST['tahun_pelajaran'] ?? null);
    $no_telp = trim($_POST['no_telp'] ?? null);
    $email = trim($_POST['email'] ?? null);
    
$default_raw_password = '123456'; 
$default_password_hash = password_hash($default_raw_password, PASSWORD_DEFAULT);

if ($default_password_hash === false) {
    $error = "Gagal memproses password. Silakan coba lagi.";
}

if (empty($nis) || empty($nama_siswa) || empty($kelas) || empty($jurusan)) {
    $error = "NIS, Nama, Kelas, dan Jurusan wajib diisi.";
}

    
    if (!$error) {
        try {
       
            $pdo->beginTransaction();
 
           $stmt_user = $pdo->prepare("INSERT INTO users (username, password, role, related_id, ) VALUES (?, ?, 'siswa', ?)");

           $stmt_user->execute([$nis, $default_password_hash, $nis]);
            $user_id = $pdo->lastInsertId(); 
            $stmt_siswa = $pdo->prepare("
                INSERT INTO siswa 
                (nis, nisn, nama_siswa, tgl_lahir, kelas, jurusan, wali_kelas, tahun_pelajaran, no_telp, email, user_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_siswa->execute([
                $nis, $nisn, $nama_siswa, $tgl_lahir, $kelas, $jurusan, $wali_kelas, $tahun_pelajaran, $no_telp, $email, $user_id
            ]);
            
            $pdo->commit();
            
            $success = "Data siswa $nama_siswa berhasil ditambahkan. Username login adalah $nis dengan password default: $default_raw_password.";

        
            $_POST = []; 
            
        } catch (PDOException $e) {
            
            $pdo->rollBack();
            
            
            if ($e->getCode() == '23000') {
                $error = "Gagal menambahkan data. **NIS/NISN/Email mungkin sudah terdaftar.**";
            } else {
                $error = "Gagal menambahkan data: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Siswa Baru - Admin</title>
    <style>
    
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --light: #f8f9fa;
            --dark: #212529;
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
            padding: 0 20px;
        }

        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 1.5rem 0;
            box-shadow: var(--shadow);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .page-title i {
            font-size: 1.8rem;
        }

        .breadcrumb {
            display: flex;
            gap: 10px;
            font-size: 0.9rem;
            margin-top: 5px;
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
            color: rgba(255, 255, 255, 0.6);
        }

        
        .main-content {
            padding: 2rem 0;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            background-color: var(--light);
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 1rem;
            transition: var(--transition);
            background-color: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .form-control:required {
            border-left: 3px solid var(--primary);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 16px;
            padding-right: 2.5rem;
        }

        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0.75rem 1.5rem;
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
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .btn-outline:hover {
            background-color: rgba(67, 97, 238, 0.1);
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* Alert Styles */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background-color: rgba(76, 201, 240, 0.15);
            color: #0c5460;
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background-color: rgba(247, 37, 133, 0.15);
            color: #721c24;
            border-left: 4px solid var(--danger);
        }

        .alert-icon {
            font-size: 1.2rem;
        }

        /* Info Box */
        .info-box {
            background-color: rgba(67, 97, 238, 0.05);
            border: 1px solid rgba(67, 97, 238, 0.2);
            border-radius: 6px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: var(--gray);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        .icon {
            display: inline-block;
            width: 1em;
            height: 1em;
            stroke-width: 0;
            stroke: currentColor;
            fill: currentColor;
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card {
            animation: fadeIn 0.5s ease;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="header-content">
                <div>
                    <h1 class="page-title">
                        <svg class="icon" viewBox="0 0 24 24" width="24" height="24">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                        Tambah Siswa Baru
                    </h1>
                    <div class="breadcrumb">
                        <a href="index.php">Dashboard</a>
                        <span>></span>
                        <a href="siswa_data.php">Daftar Siswa</a>
                        <span>></span>
                        <span>Tambah Siswa Baru</span>
                    </div>
                </div>
                <div class="btn-group">
                    <a href="siswa_data.php" class="btn btn-primary">
                        <svg class="icon" viewBox="0 0 24 24" width="16" height="16">
                            <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                        </svg>
                        Kembali ke Daftar Siswa
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="container">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <svg class="icon alert-icon" viewBox="0 0 24 24" width="20" height="20">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    <?= $success ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg class="icon alert-icon" viewBox="0 0 24 24" width="20" height="20">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <svg class="icon" viewBox="0 0 24 24" width="20" height="20">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                            Data Pokok Siswa & Akun
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nis" class="form-label">NIS (Username)</label>
                                <input type="text" id="nis" name="nis" class="form-control" required value="<?= htmlspecialchars($_POST['nis'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="nisn" class="form-label">NISN</label>
                                <input type="text" id="nisn" name="nisn" class="form-control" value="<?= htmlspecialchars($_POST['nisn'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="nama_siswa" class="form-label">Nama Lengkap Siswa</label>
                                <input type="text" id="nama_siswa" name="nama_siswa" class="form-control" required value="<?= htmlspecialchars($_POST['nama_siswa'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="tgl_lahir" class="form-label">Tanggal Lahir</label>
                                <input type="date" id="tgl_lahir" name="tgl_lahir" class="form-control" value="<?= htmlspecialchars($_POST['tgl_lahir'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <svg class="icon" viewBox="0 0 24 24" width="20" height="20">
                                <path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/>
                            </svg>
                            Data Akademik & Kontak
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="kelas" class="form-label">Kelas</label>
                                <select id="kelas" name="kelas" class="form-control" required>
                                    <option value="">Pilih Kelas</option>
                                    <option value="X" <?= (isset($_POST['kelas']) && $_POST['kelas'] == 'X') ? 'selected' : '' ?>>X</option>
                                    <option value="XI" <?= (isset($_POST['kelas']) && $_POST['kelas'] == 'XI') ? 'selected' : '' ?>>XI</option>
                                    <option value="XII" <?= (isset($_POST['kelas']) && $_POST['kelas'] == 'XII') ? 'selected' : '' ?>>XII</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="jurusan" class="form-label">Jurusan</label>
                                <select id="jurusan" name="jurusan" class="form-control" required>
                                    <option value="">Pilih Jurusan</option>
                                    <?php foreach ($jurusan_list as $j): ?>
                                        <option value="<?= htmlspecialchars($j) ?>" <?= (isset($_POST['jurusan']) && $_POST['jurusan'] == $j) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($j) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="wali_kelas" class="form-label">Wali Kelas</label>
                                <input type="text" id="wali_kelas" name="wali_kelas" class="form-control" value="<?= htmlspecialchars($_POST['wali_kelas'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="tahun_pelajaran" class="form-label">Tahun Pelajaran (Contoh: 2025/2026)</label>
                                <input type="text" id="tahun_pelajaran" name="tahun_pelajaran" class="form-control" value="<?= htmlspecialchars($_POST['tahun_pelajaran'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="no_telp" class="form-label">No. Telp</label>
                                <input type="text" id="no_telp" name="no_telp" class="form-control" value="<?= htmlspecialchars($_POST['no_telp'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <button type="submit" class="btn btn-primary">
                            <svg class="icon" viewBox="0 0 24 24" width="18" height="18">
                                <path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
                            </svg>
                            Simpan Data Siswa Baru
                        </button>
                        
                        <div class="info-box">
                            <strong>Informasi:</strong> Akun Siswa dibuat dengan **Username: NIS** dan **Password default: ** <code>123456</code>.
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        
        document.addEventListener('DOMContentLoaded', function() {
            
            const formControls = document.querySelectorAll('.form-control');
            
            formControls.forEach(control => {
                
                control.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                
                control.addEventListener('blur', function() {
                    if (this.value === '') {
                        this.parentElement.classList.remove('focused');
                    }
                });
                
                
                if (control.value !== '') {
                    control.parentElement.classList.add('focused');
                }
            });
            
            
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.style.borderColor = '#f72585';
                    } else {
                        field.style.borderColor = '';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Harap lengkapi semua field yang wajib diisi!');
                }
            });
        });
    </script>
</body>
</html>