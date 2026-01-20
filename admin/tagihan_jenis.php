<?php
// admin/tagihan_jenis.php (Manajemen Jenis Tagihan Non-SPP)
require_once '../config/koneksi.php';
require_once '../core/auth.php';

check_access('admin');

$error = '';
$success = '';
$action = $_GET['action'] ?? '';
$tagihan_id = $_GET['id'] ?? null;

// --- 1. Proses CRUD ---

// A. Tambah/Edit Tagihan (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_tagihan = trim($_POST['nama_tagihan'] ?? '');
    $jumlah_default = filter_var($_POST['jumlah_default'] ?? 0, FILTER_VALIDATE_FLOAT);
    $tagihan_id_post = $_POST['tagihan_id'] ?? null;

    if (empty($nama_tagihan) || $jumlah_default <= 0) {
        $error = "Nama tagihan dan jumlah biaya wajib diisi dengan benar.";
    } else {
        try {
            if ($tagihan_id_post) {
                // EDIT (UPDATE)
                $sql = "UPDATE jenis_tagihan SET nama_tagihan = ?, jumlah_default = ? WHERE tagihan_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nama_tagihan, $jumlah_default, $tagihan_id_post]);
                $success = "Jenis tagihan '{$nama_tagihan}' berhasil diperbarui.";
            } else {
                // TAMBAH (INSERT)
                // Asumsi tipe_tagihan untuk non-SPP adalah 'Administrasi' atau 'Lain'
                $sql = "INSERT INTO jenis_tagihan (nama_tagihan, tipe_tagihan, jumlah_default) VALUES (?, 'Administrasi', ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nama_tagihan, $jumlah_default]);
                $success = "Jenis tagihan '{$nama_tagihan}' berhasil ditambahkan.";
            }
            // Redirect untuk membersihkan POST dan GET
            header("Location: tagihan_jenis.php?success=" . urlencode($success));
            exit();
        } catch (PDOException $e) {
            $error = "Gagal menyimpan data: " . $e->getMessage();
        }
    }
}

// B. Hapus Tagihan (GET action=hapus)
if ($action === 'hapus' && $tagihan_id) {
    try {
        // PENCEGAHAN: Cek apakah tagihan ini sudah digunakan di tagihan_lain
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM tagihan_lain WHERE tagihan_id = ?");
        $check_stmt->execute([$tagihan_id]);
        
        if ($check_stmt->fetchColumn() > 0) {
            $error = "Gagal menghapus! Tagihan ini sudah terikat dengan data siswa. Silakan hapus tagihan siswa terlebih dahulu.";
        } else {
            $sql = "DELETE FROM jenis_tagihan WHERE tagihan_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tagihan_id]);
            $success = "Jenis tagihan berhasil dihapus.";
        }
        
        // Redirect untuk membersihkan GET
        header("Location: tagihan_jenis.php?success=" . urlencode($success));
        exit();

    } catch (PDOException $e) {
        $error = "Gagal menghapus: " . $e->getMessage();
    }
}

// C. Ambil Data untuk Form Edit
$data_edit = null;
if ($action === 'edit' && $tagihan_id) {
    try {
        $stmt_edit = $pdo->prepare("SELECT * FROM jenis_tagihan WHERE tagihan_id = ?");
        $stmt_edit->execute([$tagihan_id]);
        $data_edit = $stmt_edit->fetch(PDO::FETCH_ASSOC);
        if (!$data_edit) {
            $error = "Data tagihan tidak ditemukan.";
            $action = '';
        }
    } catch (PDOException $e) {
        $error = "Gagal memuat data edit: " . $e->getMessage();
    }
}

// D. Ambil Semua Data Jenis Tagihan
try {
    $stmt_list = $pdo->query("SELECT * FROM jenis_tagihan WHERE tipe_tagihan = 'Administrasi' OR tipe_tagihan = 'Lain' ORDER BY nama_tagihan");
    $tagihan_list = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Gagal memuat daftar jenis tagihan: " . $e->getMessage());
}

// Ambil pesan sukses dari URL (setelah redirect)
if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Manajemen Jenis Tagihan Administrasi - Admin</title>
    <style>
        /* CSS Dasar - Silakan ganti dengan Bootstrap */
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .form-container { margin-bottom: 30px; padding: 20px; border: 1px solid #ccc; max-width: 600px; }
        label { display: block; margin-top: 10px; }
        input[type="text"], input[type="number"] { width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box; }
        .btn-action { text-decoration: none; padding: 5px 10px; margin: 2px; border-radius: 3px; }
        .btn-edit { background-color: #ffc107; color: black; }
        .btn-hapus { background-color: #dc3545; color: white; }
    </style>
</head>
<body>
    <h2>Tagihan Administrasi</h2>
    <p><a href="index.php">← Dashboard</a></p>
    <hr>
    
    <?php if ($success): ?>
        <p class="success"><?= $success ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <div class="form-container">
        <h3><?= ($action == 'edit' && $data_edit) ? 'Edit Jenis Tagihan' : 'Tambah Jenis Tagihan Baru' ?></h3>
        <form method="POST" action="tagihan_jenis.php">
            <input type="hidden" name="tagihan_id" value="<?= htmlspecialchars($data_edit['tagihan_id'] ?? '') ?>">
            
            <label for="nama_tagihan">Nama Tagihan (e.g., Uang Gedung):</label>
            <input type="text" id="nama_tagihan" name="nama_tagihan" required value="<?= htmlspecialchars($data_edit['nama_tagihan'] ?? '') ?>">

            <label for="jumlah_default">Jumlah Biaya Default (Rp):</label>
            <input type="number" id="jumlah_default" name="jumlah_default" step="1000" min="1000" required value="<?= htmlspecialchars($data_edit['jumlah_default'] ?? '') ?>">
            
            <button type="submit" style="margin-top: 20px;">
                <?= ($action == 'edit' && $data_edit) ? 'Simpan Perubahan' : 'Tambahkan Jenis Tagihan' ?>
            </button>
            <?php if ($action == 'edit'): ?>
                <a href="tagihan_jenis.php">Batalkan Edit</a>
            <?php endif; ?>
        </form>
    </div>

    <h3>Daftar Jenis Tagihan Administrasi</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama Tagihan</th>
                <th>Tipe</th>
                <th>Jumlah Default (Rp)</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tagihan_list)): ?>
                <tr><td colspan="5" style="text-align: center;">Belum ada jenis tagihan Administrasi yang ditambahkan.</td></tr>
            <?php else: ?>
                <?php foreach ($tagihan_list as $tagihan): ?>
                    <tr>
                        <td><?= htmlspecialchars($tagihan['tagihan_id']) ?></td>
                        <td><?= htmlspecialchars($tagihan['nama_tagihan']) ?></td>
                        <td><?= htmlspecialchars($tagihan['tipe_tagihan']) ?></td>
                        <td>Rp <?= number_format($tagihan['jumlah_default'], 0, ',', '.') ?></td>
                        <td>
                            <a href="tagihan_jenis.php?action=edit&id=<?= $tagihan['tagihan_id'] ?>" class="btn-action btn-edit">Edit</a>
                            <a href="tagihan_jenis.php?action=hapus&id=<?= $tagihan['tagihan_id'] ?>" class="btn-action btn-hapus" onclick="return confirm('Yakin ingin menghapus jenis tagihan ini? Pastikan tidak ada siswa yang terikat tagihan ini.')">Hapus</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>