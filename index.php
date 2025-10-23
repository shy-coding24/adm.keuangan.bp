<?php
require_once 'config/koneksi.php';

// Jika user sudah login, arahkan ke dashboard yang sesuai (Mencegah Loop)
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] == 'admin') {
        header('Location: admin/index.php');
    } elseif ($_SESSION['role'] == 'siswa') {
        header('Location: siswa/index.php');
    }
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Ambil data user dari tabel users (gunakan Prepared Statement)
    $stmt = $pdo->prepare("SELECT user_id, password, role, related_id FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Login Berhasil
        $_SESSION['user_id'] = $user['user_id'];
        // index.php (Bagian Login Berhasil)
if ($user && password_verify($password, $user['password'])) {
    // ...

    $_SESSION['role'] = $user['role']; // ⬅️ Session diatur berdasarkan kolom 'role' dari DB

    // Logika Pengalihan (Redirect)
    if ($user['role'] == 'admin') {
        $_SESSION['admin_id'] = $user['related_id'];
        header('Location: admin/index.php');
    } elseif ($user['role'] == 'siswa') { // ⬅️ PASTIKAN ada pengecekan untuk 'siswa'
        $_SESSION['nis'] = $user['related_id'];
        header('Location: siswa/index.php');
    }
    exit;
}
    } else {
        $error_message = "Username atau password salah.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Login Sistem Keuangan</title>
</head>
<body>
    <h2>Login SMK Bina Profesi</h2>
    <?php if ($error_message): ?>
        <p style="color: red; font-weight: bold;"><?= $error_message ?></p>
    <?php endif; ?>
    <form action="" method="post">
        <label for="username">Username (NIS/Bendahara):</label>
        <input type="text" id="username" name="username" required><br><br>
        
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required><br><br>
        
        <button type="submit">Login</button>
    </form>
</body>
</html>