<?php

require_once '../config/koneksi.php';
require_once '../core/auth.php';

check_access('admin');

$error = '';
$success = '';
$action = $_GET['action'] ?? '';
$jurusan_id = $_GET['id'] ?? null;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_jurusan = trim($_POST['nama_jurusan'] ?? '');
    $jurusan_id_post = $_POST['jurusan_id'] ?? null;

    if (empty($nama_jurusan)) {
        $error = "Nama Jurusan wajib diisi.";
    } else {
        try {
            if ($jurusan_id_post) {
               
                $sql = "UPDATE master_jurusan SET nama_jurusan = ? WHERE jurusan_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nama_jurusan, $jurusan_id_post]);
                $success = "Jurusan '{$nama_jurusan}' berhasil diperbarui.";
            } else {
                
                $sql = "INSERT INTO master_jurusan (nama_jurusan) VALUES (?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nama_jurusan]);
                $success = "Jurusan '{$nama_jurusan}' berhasil ditambahkan.";
            }
            header("Location: master_jurusan.php?success=" . urlencode($success));
            exit();
        } catch (PDOException $e) {
            
            if ($e->getCode() == '23000') { 
                $error = "Gagal menyimpan: Jurusan '{$nama_jurusan}' sudah ada.";
            } else {
                $error = "Gagal menyimpan data: " . $e->getMessage();
            }
        }
    }
}


if ($action === 'hapus' && $jurusan_id) {
    try {
     
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE jurusan = (SELECT nama_jurusan FROM master_jurusan WHERE jurusan_id = ?)");
        $check_stmt->execute([$jurusan_id]);
        
        if ($check_stmt->fetchColumn() > 0) {
            $error = "Gagal menghapus! Jurusan ini sedang digunakan oleh beberapa siswa.";
        } else {
            $sql = "DELETE FROM master_jurusan WHERE jurusan_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$jurusan_id]);
            $success = "Jurusan berhasil dihapus.";
        }
        
        header("Location: master_jurusan.php?success=" . urlencode($success));
        exit();

    } catch (PDOException $e) {
        $error = "Gagal menghapus: " . $e->getMessage();
    }
}


$data_edit = null;
if ($action === 'edit' && $jurusan_id) {
    try {
        $stmt_edit = $pdo->prepare("SELECT * FROM master_jurusan WHERE jurusan_id = ?");
        $stmt_edit->execute([$jurusan_id]);
        $data_edit = $stmt_edit->fetch(PDO::FETCH_ASSOC);
        if (!$data_edit) {
            $error = "Data jurusan tidak ditemukan.";
            $action = '';
        }
    } catch (PDOException $e) {
        $error = "Gagal memuat data edit: " . $e->getMessage();
    }
}


try {
    $stmt_list = $pdo->query("SELECT * FROM master_jurusan ORDER BY nama_jurusan");
    $jurusan_list = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Gagal memuat daftar jurusan: " . $e->getMessage());
}

if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Jurusan - Admin</title>
    <style>
        /* CSS Reset dan Variabel - Konsisten dengan halaman tambah siswa */
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

        /* Layout - Konsisten dengan halaman tambah siswa */
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

        /* Cards - Konsisten dengan halaman tambah siswa */
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

        /* Form Styles - Konsisten dengan halaman tambah siswa */
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

        /* Button Styles - Konsisten dengan halaman tambah siswa */
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
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--gray), #5a6268);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
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

        /* Table Container - Konsisten dengan tema */
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-top: 1rem;
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

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-edit {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        .btn-edit:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: rgba(247, 37, 133, 0.1);
            color: var(--danger);
        }

        .btn-delete:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-2px);
        }

        /* Alert Styles - Konsisten dengan halaman tambah siswa */
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

        /* Empty State */
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

        /* Stats Badge */
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

        /* Badge */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge-primary {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
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

        /* Animations - Konsisten dengan halaman tambah siswa */
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

        /* Responsive - Konsisten dengan halaman tambah siswa */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .btn-group {
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

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
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
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="header-content">
                <div>
                    <h1 class="page-title">
                        <svg class="icon" viewBox="0 0 24 24" width="24" height="24">
                            <path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9h-4v4h-2v-4H9V9h4V5h2v4h4v2z"/>
                        </svg>
                        Manajemen Jurusan
                    </h1>
                    <div class="breadcrumb">
                        <a href="index.php">Dashboard</a>
                        <span>›</span>
                        <a href="siswa_data.php">Daftar Siswa</a>
                        <span>></span>
                        <span>Jurusan</span>
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

            <!-- Form Card -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <svg class="icon" viewBox="0 0 24 24" width="20" height="20">
                            <path d="<?= ($action == 'edit' && $data_edit) ? 'M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z' : 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z' ?>"/>
                        </svg>
                        <?= ($action == 'edit' && $data_edit) ? 'Edit Jurusan' : 'Tambah Jurusan Baru' ?>
                    </h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="master_jurusan.php">
                        <input type="hidden" name="jurusan_id" value="<?= htmlspecialchars($data_edit['jurusan_id'] ?? '') ?>">
                        
                        <div class="form-group">
                            <label for="nama_jurusan" class="form-label">Nama Jurusan</label>
                            <input type="text" id="nama_jurusan" name="nama_jurusan" class="form-control" required 
                                   placeholder="Contoh: TKJ, AKL, RPL, MM" 
                                   value="<?= htmlspecialchars($data_edit['nama_jurusan'] ?? '') ?>">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <svg class="icon" viewBox="0 0 24 24" width="18" height="18">
                                    <path d="<?= ($action == 'edit' && $data_edit) ? 'M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z' : 'M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11z' ?>"/>
                                </svg>
                                <?= ($action == 'edit' && $data_edit) ? 'Simpan Perubahan' : 'Tambahkan Jurusan' ?>
                            </button>
                            
                            <?php if ($action == 'edit'): ?>
                                <a href="master_jurusan.php" class="btn btn-secondary">
                                    <svg class="icon" viewBox="0 0 24 24" width="18" height="18">
                                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                                    </svg>
                                    Batalkan Edit
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Data Table Card -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <svg class="icon" viewBox="0 0 24 24" width="20" height="20">
                            <path d="M4 13h6c.55 0 1-.45 1-1V4c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v8c0 .55.45 1 1 1zm0 8h6c.55 0 1-.45 1-1v-4c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v4c0 .55.45 1 1 1zm10 0h6c.55 0 1-.45 1-1v-8c0-.55-.45-1-1-1h-6c-.55 0-1 .45-1 1v8c0 .55.45 1 1 1zM13 4v4c0 .55.45 1 1 1h6c.55 0 1-.45 1-1V4c0-.55-.45-1-1-1h-6c-.55 0-1 .45-1 1z"/>
                        </svg>
                        Daftar Jurusan Aktif
                        <span class="stats-badge">
                            <svg class="icon" viewBox="0 0 24 24" width="14" height="14">
                                <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/>
                                <path d="M12.5 7H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                            </svg>
                            <?= count($jurusan_list) ?> Jurusan
                        </span>
                    </h2>
                </div>
                <div class="card-body">
                    <?php if (empty($jurusan_list)): ?>
                        <div class="empty-state">
                            <svg class="icon empty-state-icon" viewBox="0 0 24 24" width="60" height="60">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                            <h3>Belum ada Jurusan yang ditambahkan</h3>
                            <p>Mulai dengan menambahkan jurusan pertama Anda</p>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama Jurusan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jurusan_list as $jurusan): ?>
                                    <tr>
                                        <td><strong>#<?= htmlspecialchars($jurusan['jurusan_id']) ?></strong></td>
                                        <td>
                                            <div class="info-value"><?= htmlspecialchars($jurusan['nama_jurusan']) ?></div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="master_jurusan.php?action=edit&id=<?= $jurusan['jurusan_id'] ?>" 
                                                   class="btn-action btn-edit" 
                                                   title="Edit Jurusan">
                                                    <svg class="icon" viewBox="0 0 24 24" width="14" height="14">
                                                        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                                                    </svg>
                                                </a>
                                                <a href="master_jurusan.php?action=hapus&id=<?= $jurusan['jurusan_id'] ?>" 
                                                   class="btn-action btn-delete" 
                                                   title="Hapus Jurusan"
                                                   onclick="return confirm('Yakin ingin menghapus jurusan \"<?= htmlspecialchars(addslashes($jurusan['nama_jurusan'])) ?>\"? Pastikan tidak ada siswa yang menggunakan jurusan ini.')">
                                                    <svg class="icon" viewBox="0 0 24 24" width="14" height="14">
                                                        <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    
                    <div class="info-box">
                        <strong>Informasi:</strong> Pastikan jurusan tidak sedang digunakan oleh siswa sebelum menghapus. 
                        Edit jurusan akan mengubah data di seluruh siswa yang menggunakan jurusan tersebut.
                    </div>
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
            
            // Form validation enhancement
            const formInput = document.getElementById('nama_jurusan');
            if (formInput) {
                formInput.addEventListener('input', function() {
                    if (this.value.trim() !== '') {
                        this.style.borderColor = '#4361ee';
                    } else {
                        this.style.borderColor = '';
                    }
                });
            }
            
            // Add focus effect to form controls
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
            });
        });
    </script>
</body>
</html>