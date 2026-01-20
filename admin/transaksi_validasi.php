<?php
require_once '../config/koneksi.php';
require_once '../core/auth.php';

check_access('admin');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {



    $transaksi_id = $_POST['transaksi_id'] ?? null;
    $action = $_POST['action'] ?? null;

    if (!$transaksi_id || !$action) {
        $error = "Data tidak lengkap.";
    } else {
        try {
            $pdo->beginTransaction();

            // Ambil data transaksi
            $stmt_get = $pdo->prepare("SELECT nis, tipe_tagihan, related_tagihan_id 
                                       FROM transaksi_pembayaran 
                                       WHERE transaksi_id = ?");
            $stmt_get->execute([$transaksi_id]);
            $trx = $stmt_get->fetch();

            if (!$trx) {
                throw new Exception("Transaksi tidak ditemukan.");
            }

            $tipe = strtolower(trim($trx['tipe_tagihan']));
            $related_id = $trx['related_tagihan_id'];

            // Tentukan tabel
            if ($tipe === 'spp') {
                $table = "tagihan_spp";
                $id_column = "spp_id";
            } elseif ($tipe === 'lain') {
                $table = "tagihan_lain";
                $id_column = "detail_tagihan_id";
            } else {
                throw new Exception("Tipe tagihan tidak dikenal: $tipe");
            }

            // VALIDASI
            if ($action === 'validasi') {

                $pdo->prepare("UPDATE transaksi_pembayaran 
                               SET status_validasi='lunas', tgl_validasi=NOW() 
                               WHERE transaksi_id=?")
                    ->execute([$transaksi_id]);

                $pdo->prepare("UPDATE $table 
                               SET status_bayar='lunas', tgl_lunas=NOW() 
                               WHERE $id_column=?")
                    ->execute([$related_id]);

                $success = "Transaksi #$transaksi_id berhasil divalidasi.";
            }

            // TOLAK
            if ($action === 'tolak') {

                $pdo->prepare("UPDATE transaksi_pembayaran 
                               SET status_validasi='ditolak', tgl_validasi=NOW() 
                               WHERE transaksi_id=?")
                    ->execute([$transaksi_id]);

                $pdo->prepare("UPDATE $table 
                               SET status_bayar='belum', tgl_lunas=NULL 
                               WHERE $id_column=?")
                    ->execute([$related_id]);

                $success = "Transaksi #$transaksi_id ditolak.";
            }

            $pdo->commit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Ambil transaksi menunggu validasi
$sql = "SELECT tp.*, s.nama_siswa 
        FROM transaksi_pembayaran tp 
        JOIN siswa s ON tp.nis=s.nis 
        WHERE tp.status_validasi='menunggu' 
        ORDER BY tp.tgl_transfer DESC";

$transaksi_list = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validasi Transaksi - Admin</title>
    <style>
        /* CSS Reset dan Variabel - Konsisten dengan halaman laporan keuangan */
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --border: #dee2e6;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
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

        /* Layout - Konsisten dengan halaman laporan keuangan */
        .container {
            max-width: 1400px;
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
            flex-wrap: wrap;
            gap: 15px;
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

        /* Main Content */
        .main-content {
            padding: 2rem 0;
        }

        /* Cards - Konsisten dengan halaman laporan keuangan */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 2rem;
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: white;
            padding: 1.2rem 1.5rem;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Stats Badge - Konsisten dengan halaman laporan keuangan */
        .stats-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-left: 10px;
        }

        /* Table Container - Konsisten dengan halaman laporan keuangan */
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: var(--shadow);
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
        }

        .data-table thead {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: white;
        }

        .data-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            border: none;
        }

        .data-table tbody tr {
            transition: var(--transition);
        }

        .data-table tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Action Buttons - Konsisten dengan halaman laporan keuangan */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-approve {
            background: linear-gradient(135deg, var(--success), #00b4d8);
            color: white;
        }

        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(76, 201, 240, 0.3);
        }

        .btn-reject {
            background: linear-gradient(135deg, var(--danger), #f50057);
            color: white;
        }

        .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(247, 37, 133, 0.3);
        }

        .btn-outline {
    background-color: transparent;
    color: white; 
    border: 1px solid white; 
}

.btn-outline:hover {
    background-color: rgba(255, 255, 255, 0.2); 
    color: white;
}
        .btn-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        /* Bukti Pembayaran Styles */
        .bukti-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 0.4rem 0.8rem;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.85rem;
            transition: var(--transition);
        }

        .bukti-link:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        /* Alert Styles - Konsisten dengan halaman laporan keuangan */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.5s ease;
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

        /* Empty State - Konsisten dengan halaman laporan keuangan */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        /* Status Badge - Konsisten dengan halaman laporan keuangan */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-menunggu {
            background-color: rgba(248, 150, 30, 0.2);
            color: #856404;
        }

        /* Currency Format */
        .currency {
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }

        /* Form Styles */
        .action-form {
            display: inline-block;
            margin: 0;
        }

        /* Icons */
        .icon {
            display: inline-block;
            width: 1em;
            height: 1em;
            stroke-width: 0;
            stroke: currentColor;
            fill: currentColor;
        }

        /* Animations - Konsisten dengan halaman laporan keuangan */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .card {
            animation: fadeIn 0.5s ease;
        }

        /* Responsive - Konsisten dengan halaman laporan keuangan */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .data-table {
                font-size: 0.9rem;
            }

            .data-table th,
            .data-table td {
                padding: 0.75rem 0.5rem;
            }

            .action-buttons {
                flex-direction: column;
                gap: 0.3rem;
            }
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
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        </svg>
                        Validasi Transaksi
                    </h1>
                    <div class="breadcrumb">
                        <a href="index.php">Dashboard</a>
                        <span>›</span>
                        <span>Validasi Pembayaran</span>
                    </div>
                </div>
                <div class="btn-group">
    <a href="index.php" class="btn btn-outline" style="color: white; border-color: white;">
        <svg class="icon" viewBox="0 0 24 24" width="16" height="16" style="color: white;">
            <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
        </svg>
        Dashboard
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

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <svg class="icon" viewBox="0 0 24 24" width="20" height="20">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                        Transaksi Menunggu Validasi
                        <span class="stats-badge">
                            <svg class="icon" viewBox="0 0 24 24" width="14" height="14">
                                <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/>
                                <path d="M12.5 7H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                            </svg>
                            <?= count($transaksi_list) ?> Transaksi
                        </span>
                    </h2>
                </div>
                <div class="card-body">
                    <?php if (empty($transaksi_list)): ?>
                        <div class="empty-state">
                            <svg class="icon empty-state-icon" viewBox="0 0 24 24" width="60" height="60">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                            <h3>Tidak ada transaksi yang menunggu validasi</h3>
                            <p>Semua transaksi pembayaran siswa sudah divalidasi.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Siswa</th>
                                        <th>NIS</th>
                                        <th>Tipe Tagihan</th>
                                        <th>Tanggal Bayar</th>
                                        <th>Jumlah</th>
                                        <th>Bukti Transfer</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transaksi_list as $t): ?>
                                    <tr>
                                        <td><strong>#<?= htmlspecialchars($t['transaksi_id']) ?></strong></td>
                                        <td><?= htmlspecialchars($t['nama_siswa']) ?></td>
                                        <td><code><?= htmlspecialchars($t['nis']) ?></code></td>
                                        <td>
                                            <div><?= htmlspecialchars($t['tipe_tagihan']) ?></div>
                                            <small style="color: var(--gray);">ID: <?= htmlspecialchars($t['related_tagihan_id']) ?></small>
                                        </td>
                                        <td>
                                            <?= date('d M Y', strtotime($t['tgl_transfer'])) ?>
                                        </td>
                                        <td>
                                            <span class="currency">Rp <?= number_format($t['jumlah_bayar'], 0, ',', '.') ?></span>
                                        </td>
                                        <td>
                                            <?php if ($t['bukti_bayar']): ?>
                                                <a href="../uploads/bukti_bayar/<?= htmlspecialchars($t['bukti_bayar']) ?>" 
                                                   target="_blank" 
                                                   class="bukti-link">
                                                    <svg class="icon" viewBox="0 0 24 24" width="14" height="14">
                                                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                                    </svg>
                                                    Lihat Bukti
                                                </a>
                                            <?php else: ?>
                                                <span style="color: var(--gray); font-style: italic;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-menunggu">
                                                <svg class="icon" viewBox="0 0 24 24" width="14" height="14">
                                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                                                </svg>
                                                Menunggu
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <form class="action-form" method="post">
    <input type="hidden" name="transaksi_id" value="<?= $t['transaksi_id'] ?>">
    <input type="hidden" name="action" value="validasi">
    <button type="submit" class="btn btn-approve">
        Validasi
    </button>
</form>

                                                <form class="action-form" method="post">
    <input type="hidden" name="transaksi_id" value="<?= $t['transaksi_id'] ?>">
    <input type="hidden" name="action" value="tolak">
    <button type="submit" class="btn btn-reject">
        Tolak
    </button>
</form>

                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Interaksi JavaScript untuk meningkatkan UX - Konsisten dengan tema
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight row on hover
            const tableRows = document.querySelectorAll('.data-table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.002)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
            
            // Auto-hide alerts setelah beberapa detik
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateX(-20px)';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
            
            // Add confirmation to action buttons
            const actionButtons = document.querySelectorAll('.action-form button');
            actionButtons.forEach(button => {
                const form = button.closest('form');
                if (!form.hasAttribute('onsubmit')) {
                    button.addEventListener('click', function(e) {
                        const action = this.value;
                        const transaksiId = form.querySelector('input[name="transaksi_id"]').value;
                        
                        let message = '';
                        if (action === 'validasi') {
                            message = `Anda yakin ingin menyetujui transaksi #${transaksiId}?`;
                        } else {
                            message = `Anda yakin ingin menolak transaksi #${transaksiId}?`;
                        }
                        
                        if (!confirm(message)) {
                            e.preventDefault();
                        }
                    });
                }
            });
            
            // Add loading state to forms
           const forms = document.querySelectorAll('.action-form');

    forms.forEach(form => {
        form.addEventListener('submit', function() {

            const button = this.querySelector('button[type="submit"]');

            if (button) {
                button.innerHTML = "Memproses...";
                        button.disabled = true;
                        
                        // Reset after 5 seconds if something goes wrong
                        setTimeout(() => {
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }, 5000);
                    }
                });
            });
        });
    </script>
</body>
</html>