<?php
require_once '../config/koneksi.php';
require_once '../core/auth.php';

check_access('siswa');

$nis = $_SESSION['nis'];

// Ambil user_id berdasarkan nis
$stmt = $pdo->prepare("SELECT user_id FROM siswa WHERE nis = ?");
$stmt->execute([$nis]);
$data_siswa = $stmt->fetch();

if (!$data_siswa) {
    die("Data siswa tidak ditemukan!");
}

$user_id = $data_siswa['user_id'];

// Ambil data user
$stmt_user = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt_user->execute([$user_id]);
$user = $stmt_user->fetch();

if (!$user) {
    die("Data user tidak ditemukan!");
}

// Ambil data siswa untuk sidebar
$stmt_siswa = $pdo->prepare("SELECT * FROM siswa WHERE nis = ?");
$stmt_siswa->execute([$nis]);
$siswa = $stmt_siswa->fetch();

$pesan = "";
$error = "";

// =============================
// PROSES UBAH PASSWORD
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password_lama     = $_POST['password_lama'];
    $password_baru     = $_POST['password_baru'];
    $password_konfirmasi = $_POST['password_konfirmasi'];

    // Cek password lama
    if (!password_verify($password_lama, $user['PASSWORD'])) {
        $error = "Password lama salah!";
    } elseif ($password_baru !== $password_konfirmasi) {
        $error = "Password baru dan konfirmasi tidak cocok!";
    } elseif (strlen($password_baru) < 6) {
        $error = "Password baru minimal 6 karakter!";
    } else {
        // Hash password baru
        $hashed = password_hash($password_baru, PASSWORD_BCRYPT);

        // Update ke database
        $update = $pdo->prepare("UPDATE users SET PASSWORD = ? WHERE user_id = ?");
        $update->execute([$hashed, $user_id]);

        $pesan = "Password berhasil diperbarui!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Ubah Password - SMK Bina Profesi</title>
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

        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-sm);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.5s ease;
            border-left: 4px solid transparent;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success);
            border-left-color: var(--success);
        }

        .alert-error {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger);
            border-left-color: var(--danger);
        }

        .alert i {
            font-size: 1.2rem;
        }

        /* Password Change Container */
        .password-container {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
            overflow: hidden;
            max-width: 600px;
            margin: 0 auto;
        }

        .password-header {
            background: linear-gradient(135deg, var(--blue-dark), var(--blue));
            color: var(--white);
            padding: 20px 25px;
        }

        .password-header h2 {
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .password-header h2 i {
            font-size: 1.3rem;
        }

        /* Form Styling */
        .password-form {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--blue-dark);
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-label i {
            color: var(--blue);
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            color: var(--blue-dark);
            background: var(--white-off);
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(62, 146, 204, 0.1);
            background: var(--white);
        }

        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: var(--gray-light);
            border-radius: 2px;
            overflow: hidden;
            position: relative;
        }

        .strength-meter {
            height: 100%;
            width: 0%;
            background: var(--danger);
            transition: var(--transition);
        }

        .strength-meter.weak {
            width: 30%;
            background: var(--danger);
        }

        .strength-meter.medium {
            width: 60%;
            background: var(--warning);
        }

        .strength-meter.strong {
            width: 100%;
            background: var(--success);
        }

        .strength-text {
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 4px;
            text-align: right;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 42px;
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            font-size: 1rem;
            transition: var(--transition);
        }

        .password-toggle:hover {
            color: var(--blue);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 28px;
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
            min-width: 160px;
            justify-content: center;
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

        .btn-success {
            background: var(--success);
            color: var(--white);
        }

        .btn-success:hover {
            background: #3d8b40;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }

        /* Navigation Links */
        .nav-links {
            margin-top: 30px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .nav-link {
            color: var(--blue);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            font-weight: 500;
            padding: 8px 0;
        }

        .nav-link:hover {
            color: var(--blue-dark);
            transform: translateX(-5px);
        }

        .nav-link i {
            font-size: 0.9rem;
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
            
            .password-container {
                margin: 0;
            }
            
            .password-form {
                padding: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .nav-links {
                flex-direction: column;
                gap: 15px;
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
            
            .password-header {
                padding: 15px 20px;
            }
            
            .password-header h2 {
                font-size: 1.2rem;
            }
            
            .form-input {
                padding: 12px 14px;
            }
            
            .btn {
                padding: 12px 20px;
                min-width: auto;
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

        /* Password Requirements */
        .password-requirements {
            background: var(--white-off);
            border-radius: var(--radius-sm);
            padding: 15px;
            margin-top: 20px;
            border-left: 4px solid var(--blue-light);
        }

        .requirements-title {
            font-weight: 600;
            color: var(--blue-dark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .requirement-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            font-size: 0.9rem;
            color: var(--gray);
        }

        .requirement-item.valid {
            color: var(--success);
        }

        .requirement-item.invalid {
            color: var(--gray);
        }

        .requirement-item i {
            font-size: 0.8rem;
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
                
                <a href="biodata.php" class="menu-item">
                    <i class="fas fa-user-circle"></i>
                    <span>Biodata Siswa</span>
                </a>
                
                <a href="ubah_password.php" class="menu-item active">
                    <i class="fas fa-key"></i>
                    <span>Ubah Password</span>
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
                <h1><i class="fas fa-key"></i> Ubah Password</h1>
                <p>Perbarui password Anda untuk menjaga keamanan akun. Pastikan password baru kuat dan mudah diingat.</p>
            </div>

            <!-- Alert Messages -->
            <?php if ($pesan): ?>
                <div class="alert alert-success animate-in" style="animation-delay: 0.1s">
                    <i class="fas fa-check-circle"></i>
                    <span><?= $pesan ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error animate-in" style="animation-delay: 0.1s">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>

            <!-- Password Change Container -->
            <div class="password-container animate-in" style="animation-delay: 0.2s">
                <!-- Form Header -->
                <div class="password-header">
                    <h2><i class="fas fa-lock"></i> Form Ubah Password</h2>
                </div>

                <!-- Password Form -->
                <form method="POST" class="password-form">
                    <!-- Password Lama -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i>
                            <span>Password Lama</span>
                        </label>
                        <div style="position: relative;">
                            <input type="password" 
                                   name="password_lama" 
                                   class="form-input" 
                                   required 
                                   placeholder="Masukkan password lama Anda"
                                   id="oldPassword">
                            <button type="button" class="password-toggle" data-target="oldPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Password Baru -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-key"></i>
                            <span>Password Baru</span>
                        </label>
                        <div style="position: relative;">
                            <input type="password" 
                                   name="password_baru" 
                                   class="form-input" 
                                   required 
                                   placeholder="Masukkan password baru (minimal 6 karakter)"
                                   id="newPassword"
                                   minlength="6">
                            <button type="button" class="password-toggle" data-target="newPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="strength-meter" id="strengthMeter"></div>
                        </div>
                        <div class="strength-text" id="strengthText">Kekuatan password: -</div>
                    </div>

                    <!-- Konfirmasi Password -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-key"></i>
                            <span>Konfirmasi Password Baru</span>
                        </label>
                        <div style="position: relative;">
                            <input type="password" 
                                   name="password_konfirmasi" 
                                   class="form-input" 
                                   required 
                                   placeholder="Konfirmasi password baru"
                                   id="confirmPassword">
                            <button type="button" class="password-toggle" data-target="confirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Password Requirements -->
                    <div class="password-requirements">
                        <div class="requirements-title">
                            <i class="fas fa-info-circle"></i>
                            <span>Persyaratan Password:</span>
                        </div>
                        <div class="requirement-item invalid" id="reqLength">
                            <i class="fas fa-circle"></i>
                            <span>Minimal 6 karakter</span>
                        </div>
                        <div class="requirement-item invalid" id="reqMatch">
                            <i class="fas fa-circle"></i>
                            <span>Password harus cocok</span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Password
                        </button>
                        
                        <button type="reset" class="btn btn-secondary" id="resetBtn">
                            <i class="fas fa-redo"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>

            <!-- Navigation Links -->
            <div class="nav-links animate-in" style="animation-delay: 0.3s">
                <a href="biodata.php" class="nav-link">
                    <i class="fas fa-arrow-left"></i>
                    <span>Kembali ke Biodata</span>
                </a>
                
                <a href="index.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span>Kembali ke Dashboard</span>
                </a>
            </div>
        </main>
    </div>

    <script>
        // Password toggle visibility
        document.querySelectorAll('.password-toggle').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        // Password strength checker
        const newPasswordInput = document.getElementById('newPassword');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const strengthMeter = document.getElementById('strengthMeter');
        const strengthText = document.getElementById('strengthText');
        const reqLength = document.getElementById('reqLength');
        const reqMatch = document.getElementById('reqMatch');

        function checkPasswordStrength(password) {
            let strength = 0;
            
            // Check length
            if (password.length >= 6) {
                strength += 1;
                reqLength.classList.remove('invalid');
                reqLength.classList.add('valid');
                reqLength.querySelector('i').className = 'fas fa-check-circle';
            } else {
                reqLength.classList.remove('valid');
                reqLength.classList.add('invalid');
                reqLength.querySelector('i').className = 'fas fa-circle';
            }
            
            // Check for lowercase
            if (/[a-z]/.test(password)) strength += 1;
            
            // Check for uppercase
            if (/[A-Z]/.test(password)) strength += 1;
            
            // Check for numbers
            if (/[0-9]/.test(password)) strength += 1;
            
            // Check for special characters
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            // Update strength meter
            strengthMeter.className = 'strength-meter';
            
            if (password.length === 0) {
                strengthMeter.style.width = '0%';
                strengthText.textContent = 'Kekuatan password: -';
            } else if (password.length < 6) {
                strengthMeter.style.width = '20%';
                strengthText.textContent = 'Kekuatan password: Sangat Lemah';
                strengthMeter.classList.add('weak');
            } else if (strength <= 2) {
                strengthMeter.style.width = '40%';
                strengthText.textContent = 'Kekuatan password: Lemah';
                strengthMeter.classList.add('weak');
            } else if (strength <= 3) {
                strengthMeter.style.width = '70%';
                strengthText.textContent = 'Kekuatan password: Cukup';
                strengthMeter.classList.add('medium');
            } else {
                strengthMeter.style.width = '100%';
                strengthText.textContent = 'Kekuatan password: Kuat';
                strengthMeter.classList.add('strong');
            }
        }

        function checkPasswordMatch() {
            const password = newPasswordInput.value;
            const confirm = confirmPasswordInput.value;
            
            if (confirm.length === 0) {
                reqMatch.classList.remove('valid', 'invalid');
                reqMatch.querySelector('i').className = 'fas fa-circle';
                return;
            }
            
            if (password === confirm && password.length >= 6) {
                reqMatch.classList.remove('invalid');
                reqMatch.classList.add('valid');
                reqMatch.querySelector('i').className = 'fas fa-check-circle';
            } else {
                reqMatch.classList.remove('valid');
                reqMatch.classList.add('invalid');
                reqMatch.querySelector('i').className = 'fas fa-circle';
            }
        }

        newPasswordInput.addEventListener('input', function() {
            checkPasswordStrength(this.value);
            checkPasswordMatch();
        });

        confirmPasswordInput.addEventListener('input', checkPasswordMatch);

        // Reset form button
        document.getElementById('resetBtn').addEventListener('click', function() {
            document.querySelectorAll('.form-input').forEach(input => {
                input.value = '';
            });
            
            strengthMeter.style.width = '0%';
            strengthText.textContent = 'Kekuatan password: -';
            strengthMeter.className = 'strength-meter';
            
            // Reset requirements
            [reqLength, reqMatch].forEach(req => {
                req.classList.remove('valid');
                req.classList.add('invalid');
                req.querySelector('i').className = 'fas fa-circle';
            });
            
            // Reset password visibility
            document.querySelectorAll('.password-toggle i').forEach(icon => {
                icon.className = 'fas fa-eye';
            });
            document.querySelectorAll('input[type="password"], input[type="text"]').forEach(input => {
                if (input.id.includes('Password')) {
                    input.type = 'password';
                }
            });
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = newPasswordInput.value;
            const confirm = confirmPasswordInput.value;
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password baru minimal 6 karakter!');
                newPasswordInput.focus();
                return;
            }
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Password baru dan konfirmasi tidak cocok!');
                confirmPasswordInput.focus();
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            submitBtn.disabled = true;
            
            // Re-enable after 3 seconds (in case of error)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });

        // Add hover effects
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.btn, .nav-link');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>