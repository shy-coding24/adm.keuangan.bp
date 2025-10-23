<?php
// Pastikan sesi dimulai sebelum session_destroy
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Hapus semua variabel sesi
session_unset();
// Hancurkan sesi
session_destroy();

// Alihkan kembali ke halaman login
header('Location: index.php');
exit;
?>