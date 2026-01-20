<?php
require_once '../config/koneksi.php';
require_once '../core/auth.php';

check_access('admin');

// Ambil data nama Admin dari tabel admins (menggunakan related_id/admin_id)
$stmt = $pdo->prepare("SELECT nama_admin FROM admins WHERE admin_id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();
$admin_name = $admin['nama_admin'] ?? 'Admin Keuangan';

$total_siswa = $pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
$total_pembayaran = $pdo->query("SELECT COUNT(*) FROM transaksi_pembayaran WHERE status_validasi = 'valid'")->fetchColumn();
$pending_validasi = $pdo->query("SELECT COUNT(*) FROM transaksi_pembayaran WHERE status_validasi = 'menunggu'")->fetchColumn();
$total_tagihan = $pdo->query("SELECT COUNT(*) FROM tagihan_lain WHERE status_bayar = 'belum' OR status_bayar = 'menunggu_validasi'")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin Keuangan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --info: #4895ef;
            --warning: #f72585;
            --danger: #e63946;
            --logout-red: #dc3545;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --border-radius: 12px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, var(--primary), var(--secondary));
            color: white;
            transition: var(--transition);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            font-size: 1.3rem;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .sidebar-menu {
            padding: 15px 0;
        }

        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            transition: var(--transition);
            border-left: 4px solid transparent;
        }

        .menu-item:hover, .menu-item.active {
            background-color: rgba(255, 255, 255, 0.1);
            border-left-color: white;
        }

        .menu-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .logout-item {
            margin-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 15px;
            color: #ffcccc;
            border-left: 4px solid var(--logout-red);
        }

        .logout-item:hover {
            background-color: rgba(220, 53, 69, 0.2);
            border-left-color: var(--logout-red);
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            transition: var(--transition);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--gray-light);
        }

        .header h1 {
            color: var(--primary);
            font-size: 1.8rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            display: flex;
            align-items: center;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.5rem;
        }

        .stat-icon.students {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        .stat-icon.payments {
            background-color: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }

        .stat-icon.pending {
            background-color: rgba(247, 37, 133, 0.1);
            color: var(--warning);
        }

        .stat-icon.bills {
            background-color: rgba(230, 57, 70, 0.1);
            color: var(--danger);
        }

        .stat-info h3 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: var(--primary);
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-card {
            background: var(--light);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            border: 1px solid transparent;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .action-card:hover {
            background: white;
            border-color: var(--primary-light);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .action-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .action-card h3 {
            font-size: 1rem;
            margin-bottom: 5px;
        }

        .action-card p {
            font-size: 0.8rem;
            color: var(--gray);
        }

        /* Recent Activity */
        .recent-activity {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--gray-light);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 1rem;
        }

        .activity-icon.success {
            background-color: var(--success);
        }

        .activity-icon.warning {
            background-color: var(--warning);
        }

        .activity-icon.info {
            background-color: var(--info);
        }

        .activity-content {
            flex: 1;
        }

        .activity-content h4 {
            font-size: 0.95rem;
            margin-bottom: 5px;
        }

        .activity-content p {
            font-size: 0.8rem;
            color: var(--gray);
        }

        .activity-time {
            font-size: 0.8rem;
            color: var(--gray);
        }

        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            background: var(--primary);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 5px;
            font-size: 1.2rem;
            cursor: pointer;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                overflow: visible;
            }
            
            .sidebar-header h2, .sidebar-header p, .menu-item span {
                display: none;
            }
            
            .menu-item {
                justify-content: center;
                padding: 15px;
            }
            
            .menu-item i {
                margin-right: 0;
                font-size: 1.2rem;
            }
            
            .main-content {
                margin-left: 70px;
            }
        }

        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-container {
                grid-template-columns: 1fr 1fr;
            }
            
            .actions-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .user-info {
                align-self: flex-end;
            }
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-in {
            animation: fadeIn 0.5s ease forwards;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Sistem Keuangan</h2>
                <p>Dashboard Admin</p>
            </div>
            <div class="sidebar-menu">
                <a href="#" class="menu-item active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="siswa_data.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Kelola Data Siswa</span>
                </a>
                <a href="transaksi_validasi.php" class="menu-item">
                    <i class="fas fa-check-circle"></i>
                    <span>Validasi Pembayaran</span>
                </a>
                <a href="transaksi_riwayat.php" class="menu-item">
                    <i class="fas fa-history"></i>
                    <span>Laporan Keuangan</span>
                </a>
                <a href="tagihan_spp_kelola.php" class="menu-item">
                    <i class="fas fa-bell"></i>
                    <span>Kelola Tagihan</span>
                </a>
                <a href="../logout.php" class="menu-item logout-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1>Dashboard Admin Keuangan</h1>
                <div class="user-info">
                    <div class="user-avatar">
                        <?= strtoupper(substr($admin_name, 0, 1)) ?>
                    </div>
                    <div>
                        <p><strong><?= htmlspecialchars($admin_name) ?></strong></p>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon students">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $total_siswa ?></h3>
                        <p>Total Siswa</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon payments">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $total_pembayaran ?></h3>
                        <p>Pembayaran Valid</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $pending_validasi ?></h3>
                        <p>Menunggu Validasi</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bills">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $total_tagihan ?></h3>
                        <p>Tagihan Aktif</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2 class="section-title">
                    <i class="fas fa-bolt"></i>
                    Akses Cepat
                </h2>
                <div class="actions-grid">
                    <a href="siswa_data.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Data Siswa</h3>
                        <p>Kelola data siswa</p>
                    </a>
                    <a href="transaksi_validasi.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>Validasi</h3>
                        <p>Validasi pembayaran</p>
                    </a>
                    <a href="transaksi_riwayat.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Laporan</h3>
                        <p>Lihat laporan keuangan</p>
                    </a>
                    <a href="tagihan_spp_kelola.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h3>Tagihan</h3>
                        <p>Kelola Tagihan</p>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle sidebar on mobile
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !menuToggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });

        // Add animation to stat cards on load
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('animate-in');
            });
        });
    </script>
</body>
</html>