<?php
require_once '../config/koneksi.php';
require_once '../core/auth.php';

check_access('siswa');

$nis  = $_SESSION['nis'];
$tahun_sekarang = (int)date("Y");

$stmt_siswa = $pdo->prepare("SELECT nis, nama_siswa FROM siswa WHERE nis = ?");
$stmt_siswa->execute([$nis]);
$ds = $stmt_siswa->fetch();

if (!$ds) {
    die("Data siswa tidak ditemukan.");
}

$nama_siswa = $ds['nama_siswa'];

$stmt_min_tahun = $pdo->prepare("SELECT MIN(tahun) as min_tahun FROM tagihan_spp WHERE nis = ?");
$stmt_min_tahun->execute([$nis]);
$min_tahun = (int)($stmt_min_tahun->fetch()['min_tahun'] ?? $tahun_sekarang);

$tahun_mulai = max(2025, $min_tahun);
$tahun_akhir = $tahun_sekarang + 2;

$tahun_list = range($tahun_mulai, $tahun_akhir);

$tahun_target = isset($_GET['tahun']) ? (int)$_GET['tahun'] : $tahun_sekarang;
if (!in_array($tahun_target, $tahun_list)) {
    $tahun_target = $tahun_sekarang;
}

$stmt_tagihan = $pdo->prepare("SELECT * FROM tagihan_spp WHERE nis = ? AND tahun = ?");
$stmt_tagihan->execute([$nis, $tahun_target]);
$spp_list = $stmt_tagihan->fetchAll(PDO::FETCH_ASSOC);

$status_spp = [];
foreach ($spp_list as $spp) {
    $status_spp[$spp['bulan']] = [
        'id'     => $spp['spp_id'],
        'status' => $spp['status_bayar'],
        'jumlah' => $spp['jumlah_spp']
    ];
}

$stmt_non = $pdo->prepare("
    SELECT tl.detail_tagihan_id, tl.jumlah_tagihan, tl.status_bayar, jt.nama_tagihan
    FROM tagihan_lain tl
    JOIN jenis_tagihan jt ON tl.tagihan_id = jt.tagihan_id
    WHERE tl.nis = ?
");
$stmt_non->execute([$nis]);
$tagihan_non = $stmt_non->fetchAll(PDO::FETCH_ASSOC);

$daftar_bulan = [
    'Januari','Februari','Maret','April','Mei','Juni',
    'Juli','Agustus','September','Oktober','November','Desember'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Dashboard Siswa - SMK Bina Profesi</title>
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
            background: var(--white-off);
            color: var(--blue-dark);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Dashboard Container */
        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar - Mobile Friendly */
        .sidebar {
            width: 280px;
            background: var(--blue-dark);
            position: fixed;
            height: 100vh;
            z-index: 1000;
            transform: translateX(-100%);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 5px 0 25px rgba(10, 36, 99, 0.2);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .sidebar.active {
            transform: translateX(0);
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

        /* Mobile Header - Lebar Penuh */
        .mobile-header {
            display: none;
            background: linear-gradient(135deg, var(--blue-dark), var(--blue));
            color: var(--white);
            padding: 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 900;
            box-shadow: 0 4px 12px rgba(10, 36, 99, 0.2);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .header-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .header-avatar {
            width: 45px;
            height: 45px;
            background: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: var(--blue-dark);
            font-size: 1.2rem;
        }

        .header-info h3 {
            font-size: 1.1rem;
            margin-bottom: 2px;
        }

        .header-info p {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .mobile-menu-toggle {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            color: var(--white);
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Welcome Card - Lebar Penuh di Mobile */
        .welcome-section {
            width: 100%;
            background: linear-gradient(135deg, var(--blue-dark), var(--blue));
            color: var(--white);
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 0;
            box-shadow: 0 4px 12px rgba(10, 36, 99, 0.2);
        }

        .welcome-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .welcome-content h1 {
            font-size: clamp(1.4rem, 5vw, 2rem);
            margin-bottom: 10px;
            font-weight: 700;
            line-height: 1.3;
        }

        .welcome-content p {
            font-size: clamp(0.95rem, 3vw, 1.1rem);
            opacity: 0.95;
            line-height: 1.6;
            max-width: 800px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 0;
            min-height: 100vh;
            width: 100%;
        }

        .content-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 25px;
            padding-top: 0;
        }

        /* Section Cards */
        .section-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--blue-light);
        }

        .section-title {
            font-size: clamp(1.2rem, 4vw, 1.5rem);
            color: var(--blue-dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            color: var(--blue);
            font-size: 1.3rem;
        }

        /* Tagihan Non-SPP Slider */
        .slider-wrapper {
            position: relative;
            overflow: hidden;
            border-radius: var(--radius-sm);
        }

        .slider-container {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            padding: 20px 10px;
            margin: -10px;
            scrollbar-width: thin;
            scrollbar-color: var(--blue) var(--blue-light);
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }

        .slider-container::-webkit-scrollbar {
            height: 6px;
        }

        .slider-container::-webkit-scrollbar-track {
            background: var(--blue-light);
            border-radius: 10px;
        }

        .slider-container::-webkit-scrollbar-thumb {
            background: var(--blue);
            border-radius: 10px;
        }

        .tagihan-card {
            min-width: 280px;
            background: var(--white);
            border-radius: var(--radius);
            padding: 20px;
            border: 2px solid var(--gray-light);
            transition: var(--transition);
            cursor: pointer;
            box-shadow: var(--shadow);
            flex-shrink: 0;
        }

        .tagihan-card:hover {
            transform: translateY(-5px);
            border-color: var(--blue);
            box-shadow: var(--shadow-lg);
        }

        .tagihan-card.lunas {
            border-left: 5px solid var(--success);
        }

        .tagihan-card.belum {
            border-left: 5px solid var(--danger);
        }

        .tagihan-card.pending {
            border-left: 5px solid var(--warning);
        }

        .tagihan-card h4 {
            color: var(--blue-dark);
            margin-bottom: 10px;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .tagihan-amount {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--blue);
            margin-bottom: 12px;
        }

        .tagihan-status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-lunas {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success);
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .status-belum {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger);
            border: 1px solid rgba(244, 67, 54, 0.3);
        }

        .status-pending {
            background: rgba(255, 152, 0, 0.1);
            color: var(--warning);
            border: 1px solid rgba(255, 152, 0, 0.3);
        }

        /* SPP Controls */
        .spp-controls {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 25px;
            padding: 20px;
            background: var(--blue-light);
            border-radius: var(--radius);
            border: 1px solid rgba(62, 146, 204, 0.2);
        }

        .year-selector {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .year-selector label {
            font-weight: 600;
            color: var(--blue-dark);
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .year-selector select {
            padding: 12px 18px;
            border: 2px solid rgba(62, 146, 204, 0.3);
            border-radius: var(--radius-sm);
            background: var(--white);
            color: var(--blue-dark);
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            font-weight: 500;
        }

        .year-selector select:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(62, 146, 204, 0.1);
        }

        .current-year-display {
            background: var(--blue);
            color: var(--white);
            padding: 12px 20px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-align: center;
        }

        /* SPP Grid - Mobile Optimized */
        .spp-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        @media (min-width: 480px) {
            .spp-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 15px;
            }
        }

        @media (min-width: 768px) {
            .spp-grid {
                grid-template-columns: repeat(6, 1fr);
                gap: 15px;
            }
        }

        .bulan-card {
            background: var(--white);
            border-radius: var(--radius-sm);
            padding: 15px 10px;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            box-shadow: var(--shadow);
            border: 2px solid transparent;
            aspect-ratio: 1/1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 85px;
        }

        .bulan-card:hover {
            transform: translateY(-3px);
            border-color: var(--blue);
            box-shadow: 0 6px 15px rgba(62, 146, 204, 0.2);
        }

        .bulan-card.lunas {
            border-color: rgba(76, 175, 80, 0.3);
            background: rgba(76, 175, 80, 0.05);
        }

        .bulan-card.belum {
            border-color: rgba(244, 67, 54, 0.3);
            background: rgba(244, 67, 54, 0.05);
        }

        .bulan-card.pending {
            border-color: rgba(255, 152, 0, 0.3);
            background: rgba(255, 152, 0, 0.05);
        }

        .bulan-card.no-tagihan {
            border-color: rgba(108, 117, 125, 0.3);
            background: rgba(108, 117, 125, 0.05);
            cursor: default;
        }

        .bulan-card.no-tagihan:hover {
            transform: none;
            border-color: rgba(108, 117, 125, 0.3);
            box-shadow: var(--shadow);
        }

        .bulan-name {
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 6px;
            color: var(--blue-dark);
            text-transform: uppercase;
        }

        .bulan-amount {
            font-size: 0.75rem;
            color: var(--gray);
            margin-bottom: 6px;
            font-weight: 500;
        }

        .bulan-status {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: 10px;
            display: inline-block;
        }

        .bulan-card.lunas .bulan-status {
            background: rgba(76, 175, 80, 0.15);
            color: var(--success);
        }

        .bulan-card.belum .bulan-status {
            background: rgba(244, 67, 54, 0.15);
            color: var(--danger);
        }

        .bulan-card.pending .bulan-status {
            background: rgba(255, 152, 0, 0.15);
            color: var(--warning);
        }

        .bulan-card.no-tagihan .bulan-status {
            background: rgba(108, 117, 125, 0.15);
            color: var(--gray);
            font-size: 0.65rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--blue);
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--blue-dark);
            font-size: 1.2rem;
        }

        .empty-state p {
            font-size: 0.95rem;
            max-width: 300px;
            margin: 0 auto;
        }

        /* Responsive Design - Mobile First */
        @media (min-width: 1024px) {
            .dashboard-wrapper {
                flex-direction: row;
            }
            
            .sidebar {
                width: 300px;
                transform: translateX(0);
                position: relative;
            }
            
            .mobile-header {
                display: none;
            }
            
            .welcome-section {
                border-radius: 0 0 var(--radius) var(--radius);
                margin: 0 0 30px 0;
                padding: 40px 30px;
            }
            
            .content-container {
                padding: 30px;
                padding-top: 0;
            }
            
            .main-content {
                flex: 1;
                overflow-y: auto;
            }
        }

        @media (max-width: 1023px) {
            .sidebar {
                width: 300px;
            }
            
            .mobile-header {
                display: block;
            }
            
            .welcome-section {
                margin-top: 80px;
                padding: 30px 20px;
            }
            
            .content-container {
                padding: 20px;
            }
            
            .tagihan-card {
                min-width: 260px;
            }
        }

        @media (max-width: 768px) {
            .welcome-section {
                margin-top: 70px;
                padding: 25px 15px;
            }
            
            .content-container {
                padding: 15px;
            }
            
            .section-card {
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .spp-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }
            
            .bulan-card {
                padding: 12px 8px;
                min-height: 80px;
            }
            
            .bulan-name {
                font-size: 0.8rem;
            }
            
            .tagihan-card {
                min-width: 240px;
                padding: 18px;
            }
        }

        @media (max-width: 480px) {
            .mobile-header {
                padding: 15px;
            }
            
            .header-avatar {
                width: 40px;
                height: 40px;
                font-size: 1.1rem;
            }
            
            .header-info h3 {
                font-size: 1rem;
            }
            
            .welcome-section {
                margin-top: 70px;
                padding: 20px 15px;
            }
            
            .welcome-content h1 {
                font-size: 1.3rem;
            }
            
            .welcome-content p {
                font-size: 0.9rem;
            }
            
            .content-container {
                padding: 12px;
            }
            
            .section-card {
                padding: 18px;
                border-radius: var(--radius-sm);
            }
            
            .spp-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
            }
            
            .bulan-card {
                min-height: 75px;
                padding: 10px 6px;
            }
            
            .bulan-name {
                font-size: 0.75rem;
            }
            
            .spp-controls {
                padding: 15px;
            }
        }

        @media (max-width: 360px) {
            .spp-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .bulan-card {
                min-height: 70px;
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

        /* Loading Skeleton */
        .skeleton {
            background: linear-gradient(90deg, var(--gray-light) 25%, var(--white) 50%, var(--gray-light) 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body>
    <!-- Mobile Header - Lebar Penuh -->
    <header class="mobile-header">
        <div class="header-content">
            <div class="header-profile">
                <div class="header-avatar">
                    <?= strtoupper(substr($nama_siswa, 0, 1)) ?>
                </div>
                <div class="header-info">
                    <h3><?= $nama_siswa ?></h3>
                    <p>NIS: <?= $nis ?></p>
                </div>
            </div>
            <button class="mobile-menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <div class="dashboard-wrapper">
        <!-- Sidebar - Profil & Menu -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="profile-avatar">
                    <?= strtoupper(substr($nama_siswa, 0, 1)) ?>
                </div>
                <div class="user-info">
                    <h3><?= $nama_siswa ?></h3>
                    <p>NIS: <?= $nis ?></p>
                    <span class="student-badge">
                        <i class="fas fa-user-graduate"></i> Siswa Aktif
                    </span>
                </div>
            </div>

            <nav class="sidebar-menu">
                <a href="#" class="menu-item active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="biodata.php" class="menu-item">
                    <i class="fas fa-user-circle"></i>
                    <span>Biodata Siswa</span>
                </a>
                
                <div style="flex: 1;"></div>
                
                <a href="../logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Welcome Section - Lebar Penuh -->
            <section class="welcome-section">
                <div class="welcome-content animate-in">
                    <h1>Selamat Datang, <?= $nama_siswa ?>!</h1>
                    <p>SMK Bina Profesi - Sistem Keuangan Siswa. Pantau dan kelola pembayaran SPP serta tagihan administrasi Anda dengan mudah dan efisien.</p>
                </div>
            </section>

            <div class="content-container">
                <!-- Non-SPP Section -->
                <div class="section-card animate-in" style="animation-delay: 0.1s">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-file-invoice-dollar"></i>
                            Tagihan Administrasi Lainnya
                        </h2>
                    </div>
                    
                    <div class="slider-wrapper">
                        <div class="slider-container">
                            <?php if (count($tagihan_non) == 0): ?>
                                <div class="empty-state">
                                    <i class="fas fa-check-circle"></i>
                                    <h3>Tidak Ada Tagihan</h3>
                                    <p>Semua tagihan administrasi telah lunas</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($tagihan_non as $t): 
                                    $status_class = '';
                                    $badge_class = '';
                                    
                                    if ($t['status_bayar'] === 'lunas') {
                                        $status_class = 'lunas';
                                        $badge_class = 'status-lunas';
                                    } elseif ($t['status_bayar'] === 'menunggu_validasi') {
                                        $status_class = 'pending';
                                        $badge_class = 'status-pending';
                                    } else {
                                        $status_class = 'belum';
                                        $badge_class = 'status-belum';
                                    }
                                ?>
                                    <div class="tagihan-card <?= $status_class ?>" 
                                         onclick="window.location='upload_pembayaran.php?jenis=nonspp&id=<?= $t['detail_tagihan_id'] ?>'">
                                        <h4><?= $t['nama_tagihan'] ?></h4>
                                        <div class="tagihan-amount">
                                            Rp <?= number_format($t['jumlah_tagihan'], 0, ',', '.') ?>
                                        </div>
                                        <span class="tagihan-status <?= $badge_class ?>">
                                            <?= strtoupper($t['status_bayar']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- SPP Section -->
                <div class="section-card animate-in" style="animation-delay: 0.2s">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-calendar-check"></i>
                            Pembayaran SPP
                        </h2>
                    </div>
                    
                    <div class="spp-controls">
                        <div class="year-selector">
                            <label for="tahun-select">
                                <i class="fas fa-calendar-alt"></i> Pilih Tahun:
                            </label>
                            <form method="GET" style="display: block;">
                                <select name="tahun" id="tahun-select" onchange="this.form.submit()">
                                    <?php foreach($tahun_list as $t): ?>
                                        <option value="<?= $t ?>" <?= ($t == $tahun_target ? 'selected' : '') ?>>
                                            Tahun <?= $t ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                        
                        <div class="current-year-display">
                            <i class="fas fa-calendar-check"></i>
                            SPP Tahun <?= $tahun_target ?>
                        </div>
                    </div>

                    <div class="spp-grid">
                        <?php foreach ($daftar_bulan as $index => $bulan): 
                            if (isset($status_spp[$bulan])) {
                                $d = $status_spp[$bulan];
                                
                                $class = 'belum';
                                if ($d['status'] === "lunas") $class = "lunas";
                                else if ($d['status'] === "menunggu_validasi") $class = "pending";
                                
                                $onclick = "window.location='upload_pembayaran.php?jenis=spp&id={$d['id']}'";
                            } else {
                                $class = "no-tagihan";
                                $onclick = "";
                            }
                        ?>
                            <div class="bulan-card <?= $class ?>" onclick="<?= $onclick ?>" 
                                 style="animation-delay: <?= $index * 0.05 ?>s">
                                <div class="bulan-name"><?= substr($bulan, 0, 3) ?></div>
                                <?php if (isset($d)): ?>
                                    <div class="bulan-amount" title="Rp <?= number_format($d['jumlah'], 0, ',', '.') ?>">
                                        Rp <?= number_format($d['jumlah'], 0, ',', '.') ?>
                                    </div>
                                    <div class="bulan-status" title="<?= strtoupper($d['status']) ?>">
                                        <?php 
                                            $statusText = strtoupper($d['status']);
                                            echo strlen($statusText) > 10 ? substr($statusText, 0, 8) . '..' : $statusText;
                                        ?>
                                    </div>
                                <?php else: ?>
                                    <div class="bulan-status">-</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Toggle sidebar
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            menuToggle.innerHTML = sidebar.classList.contains('active') ? 
                '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
        });

        // Close sidebar when clicking outside
        document.addEventListener('click', function(event) {
            if (!sidebar.contains(event.target) && 
                !menuToggle.contains(event.target) && 
                sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            }
        });

        // Swipe gestures for mobile sidebar
        let touchStartX = 0;
        let touchEndX = 0;
        const swipeThreshold = 50;

        document.addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
        });

        document.addEventListener('touchend', e => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        });

        function handleSwipe() {
            if (touchStartX - touchEndX > swipeThreshold) {
                // Swipe left - close sidebar
                sidebar.classList.remove('active');
                menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            }
            if (touchEndX - touchStartX > swipeThreshold) {
                // Swipe right - open sidebar
                sidebar.classList.add('active');
                menuToggle.innerHTML = '<i class="fas fa-times"></i>';
            }
        }

        // Add animations on load
        document.addEventListener('DOMContentLoaded', function() {
            const bulanCards = document.querySelectorAll('.bulan-card');
            bulanCards.forEach(card => {
                card.classList.add('animate-in');
            });

            const tagihanCards = document.querySelectorAll('.tagihan-card');
            tagihanCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('animate-in');
            });

            // Improve touch scrolling for slider
            const slider = document.querySelector('.slider-container');
            if (slider) {
                slider.addEventListener('touchstart', (e) => {
                    const touch = e.touches[0];
                    slider.dataset.startX = touch.clientX;
                    slider.dataset.scrollLeft = slider.scrollLeft;
                });

                slider.addEventListener('touchmove', (e) => {
                    e.preventDefault();
                    const touch = e.touches[0];
                    const deltaX = touch.clientX - slider.dataset.startX;
                    slider.scrollLeft = slider.dataset.scrollLeft - deltaX;
                });
            }
        });

        // Auto-hide sidebar on mobile when clicking a link
        const menuLinks = document.querySelectorAll('.menu-item');
        menuLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 1024) {
                    sidebar.classList.remove('active');
                    menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
                }
            });
        });

        // Handle orientation change
        window.addEventListener('orientationchange', function() {
            setTimeout(() => {
                if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
                }
            }, 300);
        });
    </script>
</body>
</html>