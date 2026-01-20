<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/koneksi.php';
require_once '../core/auth.php'; 

check_access('admin');

$search_query = $_GET['q'] ?? ''; 

$sql = "SELECT nis, nama_siswa, kelas, jurusan, no_telp 
        FROM siswa";
$params = [];

if (!empty($search_query)) {
   
    $search_term = '%' . $search_query . '%';
    
    $sql .= " WHERE nis LIKE ? OR nama_siswa LIKE ? OR jurusan LIKE ?";
    $params = [$search_term, $search_term, $search_term];
}

$sql .= " ORDER BY kelas, nama_siswa";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data_siswa = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    
    $data_siswa = []; 
    
}

if (isset($_SESSION['admin_id'])) {
    $stmt_admin = $pdo->prepare("SELECT nama_admin FROM admins WHERE admin_id = ?");
    $stmt_admin->execute([$_SESSION['admin_id']]);
    $admin = $stmt_admin->fetch(PDO::FETCH_ASSOC);
    $admin_name = $admin['nama_admin'] ?? 'Admin Keuangan';
} else {
    $admin_name = 'Admin Keuangan'; 
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Kelola Data Siswa - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --light: #f8f9fa;
            --dark: #212529;
            --success: #4cc9f0;
            --warning: #ff9e00;
            --danger: #f72585;
            --info: #2196F3;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --shadow: 0 4px 12px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 30px 20px;
            color: var(--dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1300px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .user-info p {
            margin: 0;
            font-size: 1rem;
        }

        .user-info a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 6px;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.2);
        }

        .user-info a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .content {
            padding: 30px;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 25px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title::before {
            content: "";
            display: block;
            width: 4px;
            height: 24px;
            background: var(--primary);
            border-radius: 2px;
        }

        .action-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
            padding: 20px;
            background: var(--light);
            border-radius: 12px;
            border-left: 4px solid var(--primary);
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(67, 97, 238, 0.3);
        }

        .btn-secondary {
            background: var(--warning);
            color: black;
        }

        .btn-secondary:hover {
            background: #e68a00;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 158, 0, 0.3);
        }

        .search-form {
            display: flex;
            gap: 8px;
        }

        .search-form input[type="text"] {
            padding: 12px 16px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            width: 280px;
            font-size: 0.95rem;
            transition: var(--transition);
            background: white;
        }

        .search-form input[type="text"]:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .search-form button {
            padding: 12px 18px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .search-form button:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .notification {
            padding: 15px 20px;
            margin-bottom: 25px;
            background: #fff3cd;
            border-left: 4px solid var(--warning);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.5s ease;
            color: var(--dark); /* Tambahkan warna teks */
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .notification i {
            color: var(--warning);
            font-size: 1.2rem;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            animation: fadeIn 0.6s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
        }

        tbody tr {
            border-bottom: 1px solid var(--light-gray);
            transition: var(--transition);
        }

        tbody tr:hover {
            background: rgba(67, 97, 238, 0.05);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }

        td {
            padding: 16px 20px;
            font-size: 0.95rem;
        }

        .action-buttons-cell {
            display: flex;
            gap: 8px;
        }

        .aksi-btn {
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-edit {
            background: var(--info);
            color: white;
        }

        .btn-edit:hover {
            background: #1a7fd6;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(33, 150, 243, 0.3);
        }

        .btn-hapus {
            background: var(--danger);
            color: white;
        }

        .btn-hapus:hover {
            background: #e01a6d;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(247, 37, 133, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
            background: var(--light);
            border-radius: 8px;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: var(--light-gray);
        }

        .empty-state h3 {
            font-size: 1.4rem;
            margin-bottom: 10px;
            color: var(--gray);
        }

        /* Responsive Styles */
        @media (max-width: 1100px) {
            .action-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-form {
                width: 100%;
            }
            
            .search-form input[type="text"] {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            
            .user-info {
                justify-content: flex-start;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
            }
            
            .action-buttons {
                width: 100%;
                justify-content: center;
            }
            
            .btn {
                flex: 1;
                justify-content: center;
                min-width: 160px;
            }
        }

        @media (max-width: 576px) {
            body {
                padding: 15px 10px;
            }
            
            .content {
                padding: 20px 15px;
            }
            
            .action-buttons-cell {
                flex-direction: column;
            }
            
            .aksi-btn {
                justify-content: center;
            }
            
            .search-form button span {
                display: none;
            }
            
            .search-form button {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Kelola Data Siswa</h2>
            <div class="user-info">
                <a href="index.php">Dashboard</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
        
        <div class="content">
            <h3 class="section-title">Daftar Siswa Aktif</h3>
            
            <div class="action-header">
                <div class="action-buttons">
                    <a href="siswa_tambah.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Tambah Siswa Baru
                    </a>
                    
                    <a href="master_jurusan.php" class="btn btn-secondary">
                        <i class="fas fa-cogs"></i> Kelola Jurusan
                    </a>
                </div>
                
                <form method="GET" class="search-form">
                    <input 
                        type="text" 
                        name="q" 
                        placeholder="Cari NIS, Nama, Jurusan..." 
                        value="<?= htmlspecialchars($search_query) ?>"
                    >
                    <button type="submit">
                        <i class="fas fa-search"></i>
                        <span>Cari</span>
                    </button>
                </form>
            </div>

            <?php if (!empty($search_query) && count($data_siswa) == 0): ?>
                <div class="notification">
                    <i class="fas fa-info-circle"></i> 
                    <div>
                        <strong>Pencarian tidak ditemukan</strong><br>
                        Hasil pencarian untuk "<?= htmlspecialchars($search_query) ?>" tidak ditemukan.
                    </div>
                </div>
            <?php endif; ?>

            <div class="table-container">
                <?php if (is_array($data_siswa) && count($data_siswa) > 0): ?> 
                <table>
                    <thead>
                        <tr>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th>Jurusan</th>
                            <th>No. Telp</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data_siswa as $siswa): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($siswa['nis']) ?></strong></td>
                            <td><?= htmlspecialchars($siswa['nama_siswa']) ?></td>
                            <td><?= htmlspecialchars($siswa['kelas']) ?></td>
                            <td><span style="background: #e9ecef; padding: 4px 8px; border-radius: 4px;"><?= htmlspecialchars($siswa['jurusan']) ?></span></td>
                            <td><?= htmlspecialchars($siswa['no_telp']) ?></td>
                            <td>
                                <div class="action-buttons-cell">
                                    <a href="siswa_edit.php?nis=<?= $siswa['nis'] ?>" class="aksi-btn btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="siswa_hapus.php?nis=<?= $siswa['nis'] ?>" class="aksi-btn btn-hapus" onclick="return confirm('Yakin ingin menghapus data siswa ini?');">
                                        <i class="fas fa-trash-alt"></i> Hapus
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <?php if (empty($search_query)): ?>
                        <div class="empty-state">
                            <i class="fas fa-user-graduate"></i>
                            <h3>Belum ada data siswa yang terdaftar</h3>
                            <p>Mulai dengan menambahkan siswa baru menggunakan tombol di atas.</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Menambahkan interaktivitas tanpa mengubah fungsi
        document.addEventListener('DOMContentLoaded', function() {
            // Animasi untuk baris tabel
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.05}s`;
                row.style.animation = 'fadeIn 0.5s ease forwards';
            });
            
            // Efek untuk tombol pencarian
            const searchButton = document.querySelector('.search-form button');
            if (searchButton) {
                searchButton.addEventListener('click', function(e) {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            }
            
            // Highlight pada baris yang dihover
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>