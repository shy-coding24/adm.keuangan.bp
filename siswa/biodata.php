<?php
require_once '../config/koneksi.php';
require_once '../core/auth.php';

check_access('siswa');

$nis = $_SESSION['nis'];

// Ambil data siswa
$stmt = $pdo->prepare("SELECT * FROM siswa WHERE nis = ?");
$stmt->execute([$nis]);
$siswa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$siswa) {
    die("Data siswa tidak ditemukan.");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Biodata Siswa - SMK Bina Profesi</title>
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
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-section h1 i {
            color: var(--blue);
        }

        .header-section p {
            color: var(--gray);
            font-size: 1rem;
            line-height: 1.6;
            max-width: 800px;
        }

        /* Notification Card */
        .notification-card {
            background: linear-gradient(135deg, var(--blue-light), var(--white));
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 30px;
            border-left: 5px solid var(--blue);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .notification-card i {
            font-size: 1.5rem;
            color: var(--blue);
        }

        .notification-card p {
            color: var(--blue-dark);
            font-size: 0.95rem;
            flex: 1;
        }

        /* Biodata Container */
        .biodata-container {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
            overflow: hidden;
        }

        /* Section Header */
        .section-header {
            background: linear-gradient(135deg, var(--blue-dark), var(--blue));
            color: var(--white);
            padding: 20px 25px;
        }

        .section-header h2 {
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h2 i {
            font-size: 1.3rem;
        }

        /* Data Grid */
        .data-grid {
            padding: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        @media (max-width: 768px) {
            .data-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Data Item */
        .data-item {
            background: var(--white-off);
            border-radius: var(--radius-sm);
            padding: 20px;
            border-left: 4px solid var(--blue);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .data-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }

        .data-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--blue-light);
            opacity: 0;
            transition: var(--transition);
            z-index: 1;
        }

        .data-item:hover::before {
            opacity: 0.1;
        }

        .data-label {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .data-label i {
            font-size: 0.9rem;
            color: var(--blue);
        }

        .data-value {
            font-size: 1.1rem;
            color: var(--blue-dark);
            font-weight: 600;
            position: relative;
            z-index: 2;
            padding: 8px 0;
            min-height: 40px;
            display: flex;
            align-items: center;
        }

        .data-value.empty {
            color: var(--gray);
            font-style: italic;
        }

        /* Action Buttons */
        .action-buttons {
            padding: 25px;
            background: var(--white-off);
            border-top: 1px solid var(--gray-light);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 25px;
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

        .btn-back {
            color: var(--blue);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
            transition: var(--transition);
            font-weight: 500;
        }

        .btn-back:hover {
            color: var(--blue-dark);
            transform: translateX(-5px);
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
            
            .data-grid {
                padding: 20px;
                gap: 15px;
            }
            
            .data-item {
                padding: 15px;
            }
            
            .action-buttons {
                padding: 20px;
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
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
            
            .section-header {
                padding: 15px 20px;
            }
            
            .section-header h2 {
                font-size: 1.2rem;
            }
            
            .data-grid {
                padding: 15px;
            }
            
            .notification-card {
                padding: 15px;
                flex-direction: column;
                text-align: center;
            }
            
            .notification-card i {
                margin-bottom: 10px;
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
                
                <a href="biodata.php" class="menu-item active">
                    <i class="fas fa-user-circle"></i>
                    <span>Biodata Siswa</span>
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
                <h1><i class="fas fa-id-card"></i> Biodata Siswa</h1>
                <p>Informasi biodata Anda. Untuk perubahan data, silakan hubungi pihak Tata Usaha / Admin.</p>
            </div>

            <!-- Notification Card -->
            <div class="notification-card animate-in" style="animation-delay: 0.1s">
                <i class="fas fa-info-circle"></i>
                <p>Data yang ditampilkan adalah informasi resmi dari sekolah. Pastikan data Anda selalu diperbarui dan akurat.</p>
            </div>

            <!-- Biodata Container -->
            <div class="biodata-container animate-in" style="animation-delay: 0.2s">
                <!-- Section Header -->
                <div class="section-header">
                    <h2><i class="fas fa-user-graduate"></i> Informasi Pribadi</h2>
                </div>

                <!-- Data Grid -->
                <div class="data-grid">
                    <!-- Baris 1 -->
                    <div class="data-item">
                        <div class="data-label"><i class="fas fa-id-card"></i> NIS</div>
                        <div class="data-value"><?= htmlspecialchars($siswa['nis']) ?></div>
                    </div>

                    <div class="data-item">
                        <div class="data-label"><i class="fas fa-id-badge"></i> NISN</div>
                        <div class="data-value"><?= htmlspecialchars($siswa['nisn'] ?? '-') ?></div>
                    </div>

                    <!-- Baris 2 -->
                    <div class="data-item">
                        <div class="data-label"><i class="fas fa-user"></i> Nama Siswa</div>
                        <div class="data-value"><?= htmlspecialchars($siswa['nama_siswa']) ?></div>
                    </div>

                    <div class="data-item">
                        <div class="data-label"><i class="fas fa-birthday-cake"></i> Tanggal Lahir</div>
                        <div class="data-value"><?= htmlspecialchars($siswa['tgl_lahir'] ?? '-') ?></div>
                    </div>

                    <!-- Baris 3 -->
                    <div class="data-item">
                        <div class="data-label"><i class="fas fa-school"></i> Kelas</div>
                        <div class="data-value"><?= htmlspecialchars($siswa['kelas'] ?? '-') ?></div>
                    </div>

                    <div class="data-item">
                        <div class="data-label"><i class="fas fa-graduation-cap"></i> Jurusan</div>
                        <div class="data-value"><?= htmlspecialchars($siswa['jurusan'] ?? '-') ?></div>
                    </div>

                    <!-- Baris 4 -->
                    <div class="data-item">
                        <div class="data-label"><i class="fas fa-chalkboard-teacher"></i> Wali Kelas</div>
                        <div class="data-value"><?= htmlspecialchars($siswa['wali_kelas'] ?? '-') ?></div>
                    </div>

                    <div class="data-item">
                        <div class="data-label"><i class="fas fa-calendar-alt"></i> Tahun Pelajaran</div>
                        <div class="data-value"><?= htmlspecialchars($siswa['tahun_pelajaran'] ?? '-') ?></div>
                    </div>

                    <!-- Baris 5 -->
                    <div class="data-item">
                        <div class="data-label"><i class="fas fa-phone"></i> Nomor Telepon</div>
                        <div class="data-value"><?= htmlspecialchars($siswa['no_telp'] ?? '-') ?></div>
                    </div>

                    <div class="data-item">
                        <div class="data-label"><i class="fas fa-envelope"></i> Email</div>
                        <div class="data-value"><?= htmlspecialchars($siswa['email'] ?? '-') ?></div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="ubah_password.php" class="btn btn-primary">
                        <i class="fas fa-key"></i> Ubah Password
                    </a>
                    
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Add animations
        document.addEventListener('DOMContentLoaded', function() {
            const dataItems = document.querySelectorAll('.data-item');
            dataItems.forEach((item, index) => {
                item.style.animationDelay = `${index * 0.05}s`;
                item.classList.add('animate-in');
            });

            // Add hover effect to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Add click effect to data items
            dataItems.forEach(item => {
                item.addEventListener('click', function() {
                    this.style.transform = 'scale(0.98)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
        });

        // Responsive sidebar toggle for mobile
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar-menu');
            sidebar.classList.toggle('mobile-hidden');
        }
    </script>
</body>
</html>