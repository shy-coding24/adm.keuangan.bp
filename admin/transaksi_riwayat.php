<?php
// admin/transaksi_riwayat.php
require_once '../config/koneksi.php';
require_once '../core/auth.php';

check_access('admin');

// --- Inisialisasi Filter ---
$status_filter = $_GET['status'] ?? 'semua';
$tgl_mulai = $_GET['tgl_mulai'] ?? '';
$tgl_akhir = $_GET['tgl_akhir'] ?? '';

$sql_filter = "
    SELECT 
        tp.transaksi_id, tp.nis, tp.tipe_tagihan, tp.related_tagihan_id, tp.tgl_transfer, tp.jumlah_bayar, tp.bukti_bayar, tp.status_validasi, tp.tgl_validasi,
        s.nama_siswa
    FROM transaksi_pembayaran tp
    JOIN siswa s ON tp.nis = s.nis
    WHERE 1=1
";

$params = [];

// --- Logika Filter ---
if ($status_filter != 'semua') {
    // Parameter akan disiapkan sebagai 'Lunas', 'Menunggu', atau 'Ditolak'
    $sql_filter .= " AND tp.status_validasi = ?";
    $params[] = $status_filter;
}

if ($tgl_mulai) {
    $sql_filter .= " AND DATE(tp.tgl_transfer) >= ?";
    $params[] = $tgl_mulai;
}

if ($tgl_akhir) {
    $sql_filter .= " AND DATE(tp.tgl_transfer) <= ?";
    $params[] = $tgl_akhir;
}

// Urutkan data berdasarkan tanggal validasi/pembayaran terbaru
$sql_filter .= " ORDER BY tp.tgl_validasi DESC, tp.tgl_transfer DESC";

// --- Eksekusi Query ---
try {
    $stmt_transaksi = $pdo->prepare($sql_filter);
    $stmt_transaksi->execute($params);
    $transaksi_list = $stmt_transaksi->fetchAll();
} catch (PDOException $e) {
    $error = "Gagal memuat riwayat transaksi: " . $e->getMessage();
    $transaksi_list = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - Admin</title>
    <style>
        /* CSS Reset dan Variabel */
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

        /* Layout */
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

        /* Cards */
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

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }

        .stat-info h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Filter Form */
        .filter-card {
            margin-bottom: 2rem;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            margin-bottom: 0;
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
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
            background-color: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
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
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
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

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        /* Table Styles */
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

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-lunas {
            background-color: rgba(76, 201, 240, 0.2);
            color: #0c5460;
        }

        .status-menunggu {
            background-color: rgba(248, 150, 30, 0.2);
            color: #856404;
        }

        .status-ditolak {
            background-color: rgba(247, 37, 133, 0.2);
            color: #721c24;
        }

        /* Alert Styles */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background-color: rgba(247, 37, 133, 0.15);
            color: #721c24;
            border-left: 4px solid var(--danger);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .filter-form {
                grid-template-columns: 1fr;
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

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card, .stat-card {
            animation: fadeIn 0.5s ease;
        }

        /* Currency */
        .currency {
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }

        /* Tooltip */
        [data-tooltip] {
            position: relative;
        }

        [data-tooltip]:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--dark);
            color: white;
            padding: 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            white-space: nowrap;
            z-index: 1000;
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
                            <path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/>
                        </svg>
                        Laporan Keuangan
                    </h1>
                    <div class="breadcrumb">
                        <a href="index.php">Dashboard</a>
                        <span>›</span>
                        <span>Riwayat Transaksi</span>
                    </div>
                </div>
                <div class="btn-group">
                    <a href="index.php" class="btn btn-primary">
                        <svg class="icon" viewBox="0 0 24 24" width="16" height="16">
                            <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                        </svg>
                        Dashboard
                    </a>
                    <a href="transaksi_validasi.php" class="btn btn-primary">
                        <svg class="icon" viewBox="0 0 24 24" width="16" height="16">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        </svg>
                        Validasi Menunggu
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="container">
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <svg class="icon" viewBox="0 0 24 24" width="20" height="20">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <!-- Stats Summary -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1); color: var(--primary);">
                        <svg class="icon" viewBox="0 0 24 24" width="30" height="30">
                            <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <h3><?= count($transaksi_list) ?></h3>
                        <p>Total Transaksi</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(76, 201, 240, 0.1); color: var(--success);">
                        <svg class="icon" viewBox="0 0 24 24" width="30" height="30">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <h3>
                            <?= count(array_filter($transaksi_list, function($t) { return $t['status_validasi'] === 'Lunas'; })) ?>
                        </h3>
                        <p>Transaksi Lunas</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(248, 150, 30, 0.1); color: var(--warning);">
                        <svg class="icon" viewBox="0 0 24 24" width="30" height="30">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                    </div>
                    
                    <div class="stat-info">
                        <h3>
                            <?= count(array_filter($transaksi_list, function($t) { return $t['status_validasi'] === 'Menunggu'; })) ?>
                        </h3>
                        <p>Menunggu Validasi</p>
                    </div>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="card filter-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <svg class="icon" viewBox="0 0 24 24" width="20" height="20">
                            <path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/>
                        </svg>
                        Filter Laporan
                    </h2>
                </div>
                <div class="card-body">
                    <form method="get" action="" class="filter-form">
                        <div class="form-group">
                            <label for="status" class="form-label">Status Validasi</label>
                            <select id="status" name="status" class="form-control">
                                <option value="semua" <?= $status_filter == 'semua' ? 'selected' : '' ?>>Semua Status</option>
                                <option value="Lunas" <?= $status_filter == 'Lunas' ? 'selected' : '' ?>>Lunas</option>
                                <option value="Menunggu" <?= $status_filter == 'Menunggu' ? 'selected' : '' ?>>Menunggu</option>
                                <option value="Ditolak" <?= $status_filter == 'Ditolak' ? 'selected' : '' ?>>Ditolak</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="tgl_mulai" class="form-label">Tanggal Mulai</label>
                            <input type="date" id="tgl_mulai" name="tgl_mulai" class="form-control" value="<?= htmlspecialchars($tgl_mulai) ?>">
                        </div>

                        <div class="form-group">
                            <label for="tgl_akhir" class="form-label">Tanggal Akhir</label>
                            <input type="date" id="tgl_akhir" name="tgl_akhir" class="form-control" value="<?= htmlspecialchars($tgl_akhir) ?>">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <svg class="icon" viewBox="0 0 24 24" width="18" height="18">
                                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                                </svg>
                                Terapkan Filter
                            </button>
                        </div>
                    </form>

                    <div class="action-buttons">
                        <a href="transaksi_riwayat.php" class="btn btn-outline">
                            <svg class="icon" viewBox="0 0 24 24" width="16" height="16">
                                <path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
                            </svg>
                            Reset Filter
                        </a>
                        
                        <a href="export.php?status=<?= htmlspecialchars($status_filter) ?>&tgl_mulai=<?= htmlspecialchars($tgl_mulai) ?>&tgl_akhir=<?= htmlspecialchars($tgl_akhir) ?>" 
                           class="btn btn-primary">
                            <svg class="icon" viewBox="0 0 24 24" width="16" height="16">
                                <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                            </svg>
                            Export Sesuai Filter
                        </a>

                        <a href="export.php?status=Lunas&tgl_mulai=<?= htmlspecialchars($tgl_mulai) ?>&tgl_akhir=<?= htmlspecialchars($tgl_akhir) ?>" 
                           class="btn btn-success">
                            <svg class="icon" viewBox="0 0 24 24" width="16" height="16">
                                <path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/>
                            </svg>
                            Export Laporan 'LUNAS'
                        </a>
                    </div>
                </div>
            </div>

            <!-- Results Card -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <svg class="icon" viewBox="0 0 24 24" width="20" height="20">
                            <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                        </svg>
                        Data Transaksi (<?= count($transaksi_list) ?>)
                    </h2>
                </div>
                <div class="card-body">
                    <?php if (empty($transaksi_list)): ?>
                        <div class="empty-state">
                            <svg class="icon" viewBox="0 0 24 24" width="60" height="60">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                            </svg>
                            <h3>Tidak ada riwayat transaksi yang sesuai dengan filter</h3>
                            <p>Coba ubah filter pencarian Anda</p>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID Transaksi</th>
                                        <th>Siswa</th>
                                        <th>NIS</th>
                                        <th>Tipe Tagihan</th>
                                        <th>Tanggal Bayar</th>
                                        <th>Jumlah</th>
                                        <th>Status</th>
                                        <th>Tanggal Validasi</th>
                                        <th>Bukti</th>
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
                                            <small class="text-muted">ID: <?= htmlspecialchars($t['related_tagihan_id']) ?></small>
                                        </td>
                                        <td>
                                            <div><?= date('d M Y', strtotime($t['tgl_transfer'])) ?></div>
                                            <small class="text-muted"><?= date('H:i', strtotime($t['tgl_transfer'])) ?></small>
                                        </td>
                                        <td>
                                            <span class="currency">Rp <?= number_format($t['jumlah_bayar'], 0, ',', '.') ?></span>
                                        </td>
                                        <td>
                                            <?php if ($t['status_validasi'] === 'Lunas'): ?>
                                                <span class="status-badge status-lunas">
                                                    <svg class="icon" viewBox="0 0 24 24" width="14" height="14">
                                                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                                    </svg>
                                                    <?= htmlspecialchars($t['status_validasi']) ?>
                                                </span>
                                            <?php elseif ($t['status_validasi'] === 'Menunggu'): ?>
                                                <span class="status-badge status-menunggu">
                                                    <svg class="icon" viewBox="0 0 24 24" width="14" height="14">
                                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                                                    </svg>
                                                    <?= htmlspecialchars($t['status_validasi']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge status-ditolak">
                                                    <svg class="icon" viewBox="0 0 24 24" width="14" height="14">
                                                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                                                    </svg>
                                                    <?= htmlspecialchars($t['status_validasi']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($t['tgl_validasi']): ?>
                                                <div><?= date('d M Y', strtotime($t['tgl_validasi'])) ?></div>
                                                <small class="text-muted"><?= date('H:i', strtotime($t['tgl_validasi'])) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($t['bukti_bayar']): ?>
                                                <a href="../uploads/bukti_bayar/<?= htmlspecialchars($t['bukti_bayar']) ?>" 
                                                   target="_blank" 
                                                   class="btn btn-outline" 
                                                   style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                                                    <svg class="icon" viewBox="0 0 24 24" width="14" height="14">
                                                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                                    </svg>
                                                    Lihat
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="transaksi_hapus.php?id=<?= $t['transaksi_id'] ?>"
       onclick="return confirm('Yakin ingin menghapus transaksi #<?= $t['transaksi_id'] ?>?')"
       class="btn btn-outline"
       style="padding: 0.4rem 0.8rem; font-size: 0.85rem; color: red; border-color: red;">
        Hapus
    </a>
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
        // Interaksi JavaScript untuk meningkatkan UX
        document.addEventListener('DOMContentLoaded', function() {
            // Update stats secara real-time berdasarkan filter
            const statusFilter = document.getElementById('status');
            const dateInputs = document.querySelectorAll('input[type="date"]');
            
            // Format tanggal untuk display
            dateInputs.forEach(input => {
                input.addEventListener('change', function() {
                    if (this.value) {
                        this.style.borderColor = '#4361ee';
                    } else {
                        this.style.borderColor = '';
                    }
                });
            });
            
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
            
            // Filter form validation
            const filterForm = document.querySelector('.filter-form');
            filterForm.addEventListener('submit', function(e) {
                const startDate = document.getElementById('tgl_mulai').value;
                const endDate = document.getElementById('tgl_akhir').value;
                
                if (startDate && endDate && startDate > endDate) {
                    e.preventDefault();
                    alert('Tanggal mulai tidak boleh lebih besar dari tanggal akhir!');
                    document.getElementById('tgl_mulai').style.borderColor = '#f72585';
                    document.getElementById('tgl_akhir').style.borderColor = '#f72585';
                }
            });
            
            // Reset form validation styles
            const formControls = document.querySelectorAll('.form-control');
            formControls.forEach(control => {
                control.addEventListener('focus', function() {
                    this.style.borderColor = '#4361ee';
                });
                
                control.addEventListener('blur', function() {
                    if (!this.value) {
                        this.style.borderColor = '';
                    }
                });
            });
        });
    </script>
</body>
</html>