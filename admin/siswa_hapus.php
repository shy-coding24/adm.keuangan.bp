<?php
// admin/siswa_hapus.php
require_once '../config/koneksi.php';
require_once '../core/auth.php';

check_access('admin');

$nis = $_GET['nis'] ?? null;
$error = '';
$success = '';

if ($nis) {
    try {
        // 1. Ambil user_id terkait
        $stmt_get_id = $pdo->prepare("SELECT user_id FROM siswa WHERE nis = ?");
        $stmt_get_id->execute([$nis]);
        $siswa = $stmt_get_id->fetch();
        
        if (!$siswa) {
            die("Siswa tidak ditemukan, kembali ke <a href='siswa_data.php'>Daftar Siswa</a>");
        }
        $user_id = $siswa['user_id'];

        // 2. Mulai Transaksi: Pastikan semua penghapusan sukses
        $pdo->beginTransaction();

        // PENTING: Hapus semua data terkait siswa di tabel relasional terlebih dahulu
        // (Misalnya: tagihan, transaksi, notifikasi, dll.)
        // Jika ada tabel lain yang memiliki FOREIGN KEY ke 'siswa' atau 'users',
        // tambahkan DELETE di sini. Kita asumsikan hanya tagihan/transaksi/notifikasi:
        
        // Hapus Tagihan Lain dan SPP
        $pdo->prepare("DELETE FROM tagihan_lain WHERE nis = ?")->execute([$nis]);
        $pdo->prepare("DELETE FROM tagihan_spp WHERE nis = ?")->execute([$nis]);
        // Hapus Transaksi Pembayaran
        $pdo->prepare("DELETE FROM transaksi_pembayaran WHERE nis = ?")->execute([$nis]);
        // Hapus Notifikasi
        $pdo->prepare("DELETE FROM notifikasi WHERE nis = ?")->execute([$nis]);

        // Hapus data siswa dari tabel siswa
        $pdo->prepare("DELETE FROM siswa WHERE nis = ?")->execute([$nis]);

        // Hapus akun login dari tabel users
        $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$user_id]);

        // Commit (simpan permanen) jika semua query sukses
        $pdo->commit();
        $success = "Data siswa dengan NIS $nis beserta akun login dan semua histori tagihannya berhasil dihapus.";

    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Gagal menghapus data: " . $e->getMessage() . ". Periksa keterkaitan data.";
    }
} else {
    $error = "NIS tidak ditentukan.";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hapus Siswa - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #ef4444;
            --primary-dark: #dc2626;
            --primary-light: #fef2f2;
            --secondary: #64748b;
            --danger: #ef4444;
            --warning: #f59e0b;
            --success: #10b981;
            --light: #f8fafc;
            --dark: #1e293b;
            --gray: #64748b;
            --gray-light: #e2e8f0;
            --border-radius: 16px;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: var(--dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            width: 100%;
            max-width: 600px;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Card Styling */
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            border: 1px solid var(--gray-light);
            transition: var(--transition);
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 50px rgba(239, 68, 68, 0.15);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 32px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            opacity: 0.1;
        }

        .warning-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin: 0 auto 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .card-header h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            position: relative;
            z-index: 2;
        }

        .card-header p {
            font-size: 16px;
            opacity: 0.9;
            font-weight: 400;
            position: relative;
            z-index: 2;
        }

        /* Card Body */
        .card-body {
            padding: 40px;
            text-align: center;
        }

        .nis-display {
            background: var(--primary-light);
            border: 2px solid var(--primary);
            border-radius: 12px;
            padding: 16px 24px;
            margin-bottom: 32px;
            display: inline-block;
        }

        .nis-display h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 8px;
        }

        .nis-display code {
            font-family: 'Courier New', monospace;
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
            background: white;
            padding: 8px 16px;
            border-radius: 8px;
            display: inline-block;
            border: 1px solid var(--gray-light);
        }

        /* Alert Messages */
        .alert {
            padding: 20px 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            animation: slideIn 0.4s ease-out;
            position: relative;
            overflow: hidden;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .alert::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            border-radius: 5px 0 0 5px;
        }

        .alert-success {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 1px solid #86efac;
            color: #166534;
        }

        .alert-success::before {
            background: var(--success);
        }

        .alert-error {
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
            border: 1px solid #fca5a5;
            color: #991b1b;
        }

        .alert-error::before {
            background: var(--danger);
        }

        .alert-icon {
            font-size: 22px;
            flex-shrink: 0;
        }

        /* Navigation Buttons */
        .navigation-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 32px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 16px 32px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            text-decoration: none;
            min-width: 180px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(239, 68, 68, 0.4);
        }

        .btn-outline {
            background: transparent;
            color: var(--dark);
            border: 2px solid var(--gray);
        }

        .btn-outline:hover {
            background: rgba(100, 116, 139, 0.08);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(16, 185, 129, 0.4);
        }

        /* Deletion Info */
        .deletion-info {
            background: var(--light);
            border-radius: 12px;
            padding: 24px;
            margin-top: 32px;
            border-left: 4px solid var(--primary);
        }

        .deletion-info h4 {
            font-family: 'Poppins', sans-serif;
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .deletion-info ul {
            text-align: left;
            padding-left: 20px;
            color: var(--gray);
        }

        .deletion-info li {
            margin-bottom: 8px;
            padding-left: 8px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .card-header {
                padding: 24px 20px;
            }
            
            .card-body {
                padding: 24px;
            }
            
            .warning-icon {
                width: 60px;
                height: 60px;
                font-size: 28px;
            }
            
            .card-header h2 {
                font-size: 24px;
            }
            
            .navigation-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .nis-display {
                padding: 12px 20px;
            }
            
            .nis-display h3 {
                font-size: 18px;
            }
            
            .nis-display code {
                font-size: 18px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 16px;
            }
            
            .card-header {
                padding: 20px 16px;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .warning-icon {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }
            
            .card-header h2 {
                font-size: 20px;
            }
        }

        /* Countdown Animation */
        .countdown {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary-dark);
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            margin-left: 10px;
            animation: countdown 1s infinite alternate;
        }

        @keyframes countdown {
            from { transform: scale(1); }
            to { transform: scale(1.1); }
        }

        /* Divider */
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--gray-light), transparent);
            margin: 24px 0;
        }

        /* Status Indicators */
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }

        .status-danger {
            background: var(--danger);
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
        }

        .status-success {
            background: var(--success);
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
        }

        /* Loader */
        .loader {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <div class="warning-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h2>Hapus Data Siswa</h2>
                <p>Tindakan ini akan menghapus data siswa secara permanen</p>
            </div>

            <div class="card-body">
                <?php if ($nis): ?>
                    <div class="nis-display">
                        <h3>NIS yang akan dihapus:</h3>
                        <code><?= htmlspecialchars($nis) ?></code>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle alert-icon"></i>
                        <div>
                            <strong>Berhasil!</strong> <?= htmlspecialchars($success) ?>
                            <br>
                            <small>Halaman akan dialihkan dalam <span class="countdown">3</span> detik...</small>
                        </div>
                    </div>
                    <meta http-equiv="refresh" content="3;url=siswa_data.php">
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle alert-icon"></i>
                        <div>
                            <strong>Peringatan!</strong> <?= htmlspecialchars($error) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$success): ?>
                    <div class="deletion-info">
                        <h4>
                            <i class="fas fa-info-circle"></i>
                            Data yang akan dihapus:
                        </h4>
                        <ul>
                            <li><span class="status-indicator status-danger"></span> Data siswa dari tabel siswa</li>
                            <li><span class="status-indicator status-danger"></span> Akun login dari tabel users</li>
                            <li><span class="status-indicator status-danger"></span> Semua tagihan SPP terkait</li>
                            <li><span class="status-indicator status-danger"></span> Semua tagihan administrasi</li>
                            <li><span class="status-indicator status-danger"></span> Riwayat transaksi pembayaran</li>
                            <li><span class="status-indicator status-danger"></span> Semua notifikasi terkait</li>
                        </ul>
                        <p style="margin-top: 12px; color: var(--primary-dark); font-weight: 500;">
                            <i class="fas fa-exclamation-triangle"></i> Tindakan ini tidak dapat dibatalkan!
                        </p>
                    </div>
                <?php endif; ?>

                <div class="navigation-buttons">
                    <?php if ($success): ?>
                        <a href="siswa_data.php" class="btn btn-success">
                            <i class="fas fa-arrow-left"></i>
                            Kembali ke Daftar Siswa
                        </a>
                        <a href="index.php" class="btn btn-outline">
                            <i class="fas fa-home"></i>
                            Ke Dashboard
                        </a>
                    <?php else: ?>
                        <a href="siswa_data.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i>
                            Kembali ke Daftar Siswa
                        </a>
                        <a href="index.php" class="btn btn-outline">
                            <i class="fas fa-home"></i>
                            Ke Dashboard
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($success): ?>
                    <div class="divider"></div>
                    <p style="color: var(--gray); font-size: 14px;">
                        <i class="fas fa-sync-alt"></i>
                        Mengalihkan ke halaman daftar siswa...
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$success): ?>
            <div style="text-align: center; margin-top: 24px; color: var(--gray); font-size: 14px;">
                <i class="fas fa-shield-alt"></i>
                Sistem Keamanan: Proses penghapusan menggunakan transaksi database untuk menjaga integritas data
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });

            // Countdown animation
            const countdownElement = document.querySelector('.countdown');
            if (countdownElement) {
                let seconds = 3;
                const countdownInterval = setInterval(() => {
                    seconds--;
                    countdownElement.textContent = seconds;
                    if (seconds <= 0) {
                        clearInterval(countdownInterval);
                    }
                }, 1000);
            }

            // Ripple effect for buttons
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size/2;
                    const y = e.clientY - rect.top - size/2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255, 255, 255, 0.7);
                        transform: scale(0);
                        animation: ripple-animation 0.6s linear;
                        width: ${size}px;
                        height: ${size}px;
                        top: ${y}px;
                        left: ${x}px;
                    `;
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });

            // Add CSS for ripple animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple-animation {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
                .btn {
                    position: relative;
                    overflow: hidden;
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>