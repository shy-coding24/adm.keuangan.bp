<?php
// Mulai session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Fungsi untuk memeriksa apakah user sudah login dan memiliki peran yang sesuai.
 * Jika tidak valid, user akan dialihkan ke halaman login.
 * @param string|null $required_role Peran yang dibutuhkan ('admin', 'siswa', atau null jika hanya perlu login)
 */
function check_access($required_role = null) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: ../index.php');
        exit;
    }

    if ($required_role !== null && $_SESSION['role'] !== $required_role) {
        if ($_SESSION['role'] === 'admin') {
            header('Location: ../admin/index.php');
        } else {
            header('Location: ../siswa/index.php');
        }
        exit;
    }
}
