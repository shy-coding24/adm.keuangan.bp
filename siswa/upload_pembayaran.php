<?php
require_once '../config/koneksi.php';
require_once '../core/auth.php';

check_access('siswa');

$nis = $_SESSION['nis'];

// Ambil data siswa untuk sidebar
$stmt_siswa = $pdo->prepare("SELECT * FROM siswa WHERE nis = ?");
$stmt_siswa->execute([$nis]);
$siswa = $stmt_siswa->fetch();

if (!$siswa) {
    die("Data siswa tidak ditemukan.");
}

// Validasi parameter
if (!isset($_GET['jenis']) || !isset($_GET['id'])) {
    die("Parameter tidak valid.");
}

$jenis = $_GET['jenis']; // spp / nonspp
$id    = intval($_GET['id']);

$data_tagihan = null;

// =========================
// AMBIL DATA TAGIHAN
// =========================
if ($jenis === 'spp') {

    $stmt = $pdo->prepare("
        SELECT spp_id, bulan, tahun, jumlah_spp AS jumlah, status_bayar 
        FROM tagihan_spp
        WHERE spp_id = ? AND nis = ?
    ");
    $stmt->execute([$id, $nis]);
    $data_tagihan = $stmt->fetch();

    if ($data_tagihan) {
        $nama_tagihan = "SPP Bulan " . $data_tagihan['bulan'] . " " . $data_tagihan['tahun'];
    }

} else if ($jenis === 'nonspp') {

    $stmt = $pdo->prepare("
        SELECT tl.detail_tagihan_id, tl.jumlah_tagihan AS jumlah, tl.status_bayar,
               jt.nama_tagihan
        FROM tagihan_lain tl
        JOIN jenis_tagihan jt ON tl.tagihan_id = jt.tagihan_id
        WHERE tl.detail_tagihan_id = ? AND tl.nis = ?
    ");
    $stmt->execute([$id, $nis]);
    $data_tagihan = $stmt->fetch();

    if ($data_tagihan) {
        $nama_tagihan = $data_tagihan['nama_tagihan'];
    }
}

if (!$data_tagihan) {
    die("Tagihan tidak ditemukan.");
}

// =========================
// PROSES UPLOAD
// =========================
$pesan = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $jumlah_bayar = $_POST['jumlah_bayar'];

    // Validasi file
    if (!isset($_FILES['bukti']) || $_FILES['bukti']['error'] !== UPLOAD_ERR_OK) {
        $error = "Harap upload bukti pembayaran.";
    } else {

        $allowed = ['jpg','jpeg','png'];
        $nama_file = $_FILES['bukti']['name'];
        $ext = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "Format file harus JPG atau PNG.";
        } else {

            // Buat nama file unik
            $nama_baru = $nis . "_" . time() . "." . $ext;
            $tujuan = "../uploads/bukti_bayar/" . $nama_baru;

            move_uploaded_file($_FILES['bukti']['tmp_name'], $tujuan);

            // Masukkan transaksi ke database
            $insert = $pdo->prepare("
                INSERT INTO transaksi_pembayaran 
                (nis, tipe_tagihan, related_tagihan_id, tgl_transfer, jumlah_bayar, bukti_bayar, status_validasi) 
                VALUES (?, ?, ?, NOW(), ?, ?, 'menunggu')
            ");
            $insert->execute([$nis, $jenis === 'spp' ? 'spp' : 'lain', $id, $jumlah_bayar, $nama_baru]);

            // Update status tagihan jadi menunggu_validasi
            if ($jenis === 'spp') {
                $pdo->prepare("UPDATE tagihan_spp SET status_bayar = 'menunggu_validasi' WHERE spp_id = ?")
                    ->execute([$id]);
            } else {
                $pdo->prepare("UPDATE tagihan_lain SET status_bayar = 'menunggu_validasi' WHERE detail_tagihan_id = ?")
                    ->execute([$id]);
            }

            $pesan = "Bukti pembayaran berhasil diupload! Menunggu validasi admin.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Upload Pembayaran - SMK Bina Profesi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --blue-dark: #0a2463;
            --blue: #3e92cc;
            --blue-light: #d6eaff;
            --white: #ffffff;
            --white-off: #f8f9fa;
            --success: #4caf50;
            --warning: #ff9800;
            --danger: #f44336;
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --shadow: 0 4px 12px rgba(10, 36, 99, 0.1);
            --shadow-lg: 0 8px 24px rgba(10, 36, 99, 0.15);
            --radius: 12px;
            --radius-sm: 8px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', 'Poppins', system-ui, sans-serif;
        }

        body {
            background: linear-gradient(135deg, var(--blue-light) 0%, var(--white-off) 100%);
            color: var(--blue-dark);
            min-height: 100vh;
            padding: 20px;
        }

        /* Dashboard Container */
        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
            max-width: 1400px;
            margin: 0 auto;
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        /* Sidebar - Sama seperti dashboard */
        .sidebar {
            width: 280px;
            background: var(--blue-dark);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            background: var(--blue);
            padding: 30px 25px;
            text-align: center;
            color: var(--white);
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            background: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            font-weight: bold;
            color: var(--blue-dark);
            border: 4px solid var(--white);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .user-info h3 {
            font-size: 1.2rem;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .user-info p {
            opacity: 0.9;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .student-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .sidebar-menu {
            padding: 25px 20px;
            flex: 1;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            color: var(--white);
            text-decoration: none;
            border-radius: var(--radius-sm);
            margin-bottom: 10px;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.05);
            border-left: 4px solid transparent;
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: var(--blue);
            transform: translateX(5px);
        }

        .menu-item.active {
            background: rgba(62, 146, 204, 0.2);
            border-left-color: var(--blue);
            color: var(--white);
            font-weight: 600;
        }

        .menu-item i {
            width: 24px;
            margin-right: 15px;
            font-size: 1.2rem;
            text-align: center;
        }

        .menu-item span {
            font-size: 1rem;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        /* Header Section */
        .header-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--blue-light);
        }

        .header-section h1 {
            font-size: 1.8rem;
            color: var(--blue-dark);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-section h1 i {
            color: var(--blue);
        }

        /* Tagihan Info Card */
        .tagihan-card {
            background: linear-gradient(135deg, var(--blue-light), var(--white));
            border-radius: var(--radius);
            padding: 25px;
            margin-bottom: 30px;
            border-left: 5px solid var(--blue);
            box-shadow: var(--shadow);
        }

        .tagihan-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(62, 146, 204, 0.2);
        }

        .tagihan-header h2 {
            font-size: 1.3rem;
            color: var(--blue-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tagihan-header h2 i {
            color: var(--blue);
        }

        .tagihan-type {
            background: var(--blue);
            color: var(--white);
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .tagihan-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .detail-item {
            background: var(--white);
            padding: 15px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-light);
        }

        .detail-label {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 8px;
            font-weight: 500;
        }

        .detail-value {
            font-size: 1.2rem;
            color: var(--blue-dark);
            font-weight: 600;
        }

        /* Alert Messages */
        .alert {
            padding: 18px 22px;
            border-radius: var(--radius-sm);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.5s ease;
            border-left: 4px solid transparent;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success);
            border-left-color: var(--success);
        }

        .alert-error {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger);
            border-left-color: var(--danger);
        }

        .alert i {
            font-size: 1.3rem;
        }

        /* Upload Container */
        .upload-container {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .upload-header {
            background: linear-gradient(135deg, var(--blue-dark), var(--blue));
            color: var(--white);
            padding: 20px 25px;
        }

        .upload-header h2 {
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .upload-header h2 i {
            font-size: 1.3rem;
        }

        /* Form Styling */
        .upload-form {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--blue-dark);
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .form-label i {
            color: var(--blue);
            font-size: 1rem;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            color: var(--blue-dark);
            background: var(--white-off);
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(62, 146, 204, 0.1);
            background: var(--white);
        }

        /* File Upload Area */
        .file-upload-area {
            border: 3px dashed var(--blue-light);
            border-radius: var(--radius);
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            background: var(--white-off);
            position: relative;
            overflow: hidden;
        }

        .file-upload-area:hover {
            border-color: var(--blue);
            background: rgba(214, 234, 255, 0.3);
        }

        .file-upload-area.active {
            border-color: var(--success);
            background: rgba(76, 175, 80, 0.05);
        }

        .upload-icon {
            font-size: 3rem;
            color: var(--blue);
            margin-bottom: 15px;
        }

        .upload-text {
            color: var(--blue-dark);
            font-weight: 500;
            margin-bottom: 8px;
        }

        .upload-hint {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .file-input {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0;
            cursor: pointer;
        }

        .file-preview {
            margin-top: 15px;
            padding: 15px;
            background: var(--white);
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-light);
            display: none;
        }

        .file-name {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            color: var(--blue-dark);
        }

        .file-name i {
            color: var(--blue);
        }

        /* Amount Display */
        .amount-display {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .currency {
            font-size: 1.1rem;
            color: var(--gray);
            font-weight: 500;
        }

        .amount-input {
            flex: 1;
            font-size: 1.2rem;
            font-weight: 600;
            text-align: right;
            color: var(--blue-dark);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 28px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
            border: 2px solid transparent;
            cursor: pointer;
            font-size: 1rem;
            min-width: 160px;
            justify-content: center;
        }

        .btn-primary {
            background: var(--blue);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--blue-dark);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(62, 146, 204, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: var(--blue-dark);
            border-color: var(--blue);
        }

        .btn-secondary:hover {
            background: var(--blue-light);
            transform: translateY(-3px);
        }

        .btn-success {
            background: var(--success);
            color: var(--white);
        }

        .btn-success:hover {
            background: #3d8b40;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }

        /* Navigation Links */
        .nav-links {
            margin-top: 30px;
        }

        .nav-link {
            color: var(--blue);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            font-weight: 500;
            padding: 10px 0;
        }

        .nav-link:hover {
            color: var(--blue-dark);
            transform: translateX(-5px);
        }

        .nav-link i {
            font-size: 0.9rem;
        }

        /* Instructions */
        .instructions {
            background: var(--white-off);
            border-radius: var(--radius);
            padding: 20px;
            margin-top: 30px;
            border-left: 4px solid var(--blue);
        }

        .instructions h3 {
            color: var(--blue-dark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .instructions ul {
            padding-left: 20px;
            color: var(--gray);
        }

        .instructions li {
            margin-bottom: 8px;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .dashboard-wrapper {
                flex-direction: column;
                border-radius: 0;
            }
            
            .sidebar {
                width: 100%;
                flex-direction: row;
                padding: 0;
            }
            
            .sidebar-header {
                padding: 20px;
                text-align: left;
                display: flex;
                align-items: center;
                gap: 15px;
                flex: 1;
            }
            
            .profile-avatar {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
                margin: 0;
            }
            
            .user-info h3 {
                font-size: 1.1rem;
                margin-bottom: 2px;
            }
            
            .user-info p {
                font-size: 0.85rem;
                margin-bottom: 0;
            }
            
            .sidebar-menu {
                padding: 20px;
                display: flex;
                gap: 10px;
            }
            
            .menu-item {
                margin-bottom: 0;
                padding: 12px 15px;
            }
            
            .menu-item span {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .header-section h1 {
                font-size: 1.5rem;
            }
            
            .tagihan-details {
                grid-template-columns: 1fr;
            }
            
            .upload-form {
                padding: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .sidebar-menu {
                flex-direction: column;
                gap: 5px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
            
            .header-section h1 {
                font-size: 1.3rem;
            }
            
            .tagihan-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .upload-header {
                padding: 15px 20px;
            }
            
            .upload-header h2 {
                font-size: 1.2rem;
            }
            
            .form-input {
                padding: 12px 14px;
            }
            
            .file-upload-area {
                padding: 30px 15px;
            }
            
            .upload-icon {
                font-size: 2.5rem;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-in {
            animation: fadeIn 0.5s ease forwards;
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: var(--white);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar - Profil & Menu -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="profile-avatar">
                    <?= strtoupper(substr($siswa['nama_siswa'], 0, 1)) ?>
                </div>
                <div class="user-info">
                    <h3><?= $siswa['nama_siswa'] ?></h3>
                    <p>NIS: <?= $siswa['nis'] ?></p>
                    <span class="student-badge">
                        <i class="fas fa-user-graduate"></i> Siswa Aktif
                    </span>
                </div>
            </div>

            <nav class="sidebar-menu">
                <a href="index.php" class="menu-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="biodata.php" class="menu-item">
                    <i class="fas fa-user-circle"></i>
                    <span>Biodata Siswa</span>
                </a>
                
                <a href="ubah_password.php" class="menu-item">
                    <i class="fas fa-key"></i>
                    <span>Ubah Password</span>
                </a>
                
                <a href="../logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header Section -->
            <div class="header-section animate-in">
                <h1><i class="fas fa-upload"></i> Upload Bukti Pembayaran</h1>
            </div>

            <!-- Tagihan Info Card -->
            <div class="tagihan-card animate-in" style="animation-delay: 0.1s">
                <div class="tagihan-header">
                    <h2><i class="fas fa-file-invoice"></i> Informasi Tagihan</h2>
                    <span class="tagihan-type"><?= strtoupper($jenis) ?></span>
                </div>
                
                <p style="color: var(--blue-dark); margin-bottom: 15px; font-size: 1.1rem;">
                    <i class="fas fa-tag"></i> <strong><?= $nama_tagihan ?></strong>
                </p>
                
                <div class="tagihan-details">
                    <div class="detail-item">
                        <div class="detail-label">Status Pembayaran</div>
                        <div class="detail-value" style="color: <?= $data_tagihan['status_bayar'] == 'belum' ? 'var(--danger)' : ($data_tagihan['status_bayar'] == 'menunggu_validasi' ? 'var(--warning)' : 'var(--success)') ?>">
                            <?= strtoupper($data_tagihan['status_bayar']) ?>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Jumlah Tagihan</div>
                        <div class="detail-value">Rp <?= number_format($data_tagihan['jumlah'], 0, ',', '.') ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">NIS</div>
                        <div class="detail-value"><?= $nis ?></div>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($pesan): ?>
                <div class="alert alert-success animate-in" style="animation-delay: 0.2s">
                    <i class="fas fa-check-circle"></i>
                    <span><?= $pesan ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error animate-in" style="animation-delay: 0.2s">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>

            <!-- Upload Container -->
            <div class="upload-container animate-in" style="animation-delay: 0.3s">
                <!-- Form Header -->
                <div class="upload-header">
                    <h2><i class="fas fa-cloud-upload-alt"></i> Upload Bukti Bayar</h2>
                </div>

                <!-- Upload Form -->
                <form method="POST" enctype="multipart/form-data" class="upload-form">
                    <!-- Jumlah Pembayaran -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Nominal Pembayaran</span>
                        </label>
                        <div class="amount-display">
                            <span class="currency">Rp</span>
                            <input type="number" 
                                   name="jumlah_bayar" 
                                   value="<?= $data_tagihan['jumlah'] ?>" 
                                   class="form-input amount-input"
                                   required
                                   min="1">
                        </div>
                    </div>

                    <!-- File Upload -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-file-image"></i>
                            <span>Bukti Pembayaran</span>
                        </label>
                        
                        <div class="file-upload-area" id="fileUploadArea">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">
                                Klik atau seret file ke sini
                            </div>
                            <div class="upload-hint">
                                Format: JPG, JPEG, atau PNG (Maks. 5MB)
                            </div>
                            <input type="file" 
                                   name="bukti" 
                                   class="file-input" 
                                   id="fileInput"
                                   accept=".jpg,.jpeg,.png"
                                   required>
                            <div class="file-preview" id="filePreview">
                                <div class="file-name">
                                    <i class="fas fa-file-image"></i>
                                    <span id="fileName">Nama file akan muncul di sini</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-upload"></i> Upload Bukti
                        </button>
                        
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Batalkan
                        </a>
                    </div>
                </form>
            </div>

            <!-- Instructions -->
            <div class="instructions animate-in" style="animation-delay: 0.4s">
                <h3><i class="fas fa-info-circle"></i> Petunjuk Upload:</h3>
                <ul>
                    <li>Pastikan bukti pembayaran jelas terbaca</li>
                    <li>File harus berformat JPG, JPEG, atau PNG</li>
                    <li>Ukuran maksimal file: 5MB</li>
                    <li>Nominal harus sesuai dengan tagihan</li>
                    <li>Setelah upload, tunggu validasi dari admin</li>
                </ul>
            </div>

            <!-- Navigation Links -->
            <div class="nav-links animate-in" style="animation-delay: 0.5s">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-arrow-left"></i>
                    <span>Kembali ke Dashboard</span>
                </a>
            </div>
        </main>
    </div>

    <script>
        // File upload interactions
        const fileInput = document.getElementById('fileInput');
        const fileUploadArea = document.getElementById('fileUploadArea');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const submitBtn = document.getElementById('submitBtn');

        // Handle file selection
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Check file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('Ukuran file terlalu besar! Maksimal 5MB.');
                    this.value = '';
                    fileUploadArea.classList.remove('active');
                    filePreview.style.display = 'none';
                    return;
                }
                
                // Check file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Format file tidak didukung! Gunakan JPG, JPEG, atau PNG.');
                    this.value = '';
                    fileUploadArea.classList.remove('active');
                    filePreview.style.display = 'none';
                    return;
                }
                
                // Show preview
                fileName.textContent = file.name;
                filePreview.style.display = 'block';
                fileUploadArea.classList.add('active');
            }
        });

        // Drag and drop functionality
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('active');
        });

        fileUploadArea.addEventListener('dragleave', function() {
            if (!fileInput.files.length) {
                this.classList.remove('active');
            }
        });

        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            fileInput.files = e.dataTransfer.files;
            
            // Trigger change event
            const event = new Event('change');
            fileInput.dispatchEvent(event);
        });

        // Form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            // Validate file
            if (!fileInput.files.length) {
                e.preventDefault();
                alert('Harap pilih file bukti pembayaran!');
                fileUploadArea.style.borderColor = 'var(--danger)';
                setTimeout(() => {
                    fileUploadArea.style.borderColor = '';
                }, 1000);
                return;
            }
            
            // Validate amount
            const amountInput = document.querySelector('input[name="jumlah_bayar"]');
            const amount = parseFloat(amountInput.value);
            const tagihanAmount = <?= $data_tagihan['jumlah'] ?>;
            
            if (amount < 1) {
                e.preventDefault();
                alert('Nominal pembayaran tidak valid!');
                amountInput.focus();
                return;
            }
            
            if (amount !== tagihanAmount) {
                const confirmMsg = `Anda mengisi nominal Rp ${amount.toLocaleString()}, sedangkan tagihan adalah Rp ${tagihanAmount.toLocaleString()}. Lanjutkan?`;
                if (!confirm(confirmMsg)) {
                    e.preventDefault();
                    return;
                }
            }
            
            // Show loading state
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<div class="loading"></div> Memproses...';
            submitBtn.disabled = true;
            
            // Re-enable after 5 seconds (in case of error)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 5000);
        });

        // Add hover effects
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.btn, .nav-link');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Highlight amount input
            const amountInput = document.querySelector('input[name="jumlah_bayar"]');
            amountInput.addEventListener('focus', function() {
                this.style.background = 'var(--white)';
                this.style.borderColor = 'var(--blue)';
            });
            
            amountInput.addEventListener('blur', function() {
                this.style.background = 'var(--white-off)';
                this.style.borderColor = 'var(--gray-light)';
            });
        });

        // Format amount input
        const amountInput = document.querySelector('input[name="jumlah_bayar"]');
        amountInput.addEventListener('input', function() {
            // Remove non-numeric characters
            this.value = this.value.replace(/[^\d]/g, '');
            
            // Add thousand separators
            const rawValue = this.value.replace(/\D/g, '');
            const formattedValue = rawValue.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            
            // Update display
            const display = document.querySelector('.amount-input');
            display.value = formattedValue;
        });
    </script>
</body>
</html>