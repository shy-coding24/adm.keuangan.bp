<?php
session_start();
require_once 'config/koneksi.php';


$error_message = '';
$input_nis = '';
$password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input_nis = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$input_nis]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['PASSWORD'])) {

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] == 'admin') {
            $_SESSION['admin_id'] = $user['related_id'];
            header("Location: admin/index.php");
            exit;
        }

        if ($user['role'] == 'siswa') {
            $_SESSION['nis'] = $user['related_id'];
            header("Location: siswa/index.php");
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Sistem Keuangan - SMK Bina Profesi</title>
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --success-color: #2ecc71;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('gedungbp.jpeg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .login-container {
            display: flex;
            max-width: 1000px;
            width: 100%;
            background-color: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            animation: fadeIn 0.8s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-left {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-right {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: 120px;
            height: 120px;
            background-color: white;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .logo img {
            width: 70px;
            height: 70px;
            object-fit: contain;
        }

        .welcome-text h1 {
            font-size: 28px;
            margin-bottom: 10px;
            text-align: center;
        }

        .welcome-text p {
            font-size: 16px;
            opacity: 0.9;
            text-align: center;
            line-height: 1.6;
        }

        .welcome-header {
            margin-bottom: 30px;
        }

        .welcome-header h3 {
            color: var(--dark-color);
            font-size: 22px;
            margin-bottom: 10px;
            text-align: center;
        }

        .welcome-header p {
            color: #666;
            text-align: center;
            line-height: 1.5;
        }

        .login-form h2 {
            color: var(--dark-color);
            margin-bottom: 25px;
            text-align: center;
            font-size: 24px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-color);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
            outline: none;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 40px;
            cursor: pointer;
            color: #777;
        }

        .btn-login {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .error-message {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--accent-color);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
            border-left: 4px solid var(--accent-color);
        }

        /* Responsive Design - PERUBAHAN UTAMA DISINI */
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 500px;
            }
            
            .login-left, .login-right {
                padding: 30px;
            }
            
    
            .logo {
                width: 100px;
                height: 100px;
            }
            
            .logo img {
                width: 60px;
                height: 60px;
            }
            
        @media (max-width: 480px) {
            .login-left, .login-right {
                padding: 25px 20px;
            }
            
            .logo {
                width: 90px;
                height: 90px;
                margin-bottom: 15px;
            }
            
            .logo img {
                width: 55px;
                height: 55px;
            }
            
            .welcome-text h1 {
                font-size: 24px;
            }
            
            .welcome-header h3 {
                font-size: 20px;
            }
            
            .login-form h2 {
                font-size: 22px;
                margin-bottom: 20px;
            }
        }

        @media (max-width: 360px) {
            .logo {
                width: 80px;
                height: 80px;
            }
            
            .logo img {
                width: 50px;
                height: 50px;
            }
        }
    }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <!-- Bagian Kiri (Logo dan Deskripsi) -->
        <div class="login-left">
            <div class="logo-container">
                <div class="logo">
                    <img src="logo.bp.png" alt="Logo SMK Bina Profesi"> 
                </div>
                <div class="welcome-text">
                    <h1>SMK Bina Profesi</h1>
                    <p>Sistem Informasi Keuangan Sekolah</p>
                </div>
            </div>
            <div class="welcome-text">
                <p>Selamat datang di sistem keuangan SMK Bina Profesi. Silakan masuk dengan akun Anda untuk mengakses berbagai layanan keuangan sekolah.</p>
            </div>
        </div>
        
       
        <div class="login-right">
           
            
            <form class="login-form" action="" method="post">
                <h2>Masuk ke Akun Anda</h2>
                
                <?php if ($error_message): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="username">Username (NIS/Bendahara)</label>
                    <input type="text" id="username" name="username" class="form-control" required placeholder="Masukkan username Anda" value="<?= htmlspecialchars($input_nis) ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="Masukkan password Anda">
                    <span class="password-toggle" id="togglePassword">
                        <i class="far fa-eye"></i>
                    </span>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Masuk
                </button>
            </form>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
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

        document.addEventListener('DOMContentLoaded', function() {
            const usernameInput = document.getElementById('username');
            if (usernameInput.value) {
                usernameInput.focus();
            }
        });
    </script>
</body>
</html>