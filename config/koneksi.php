<?php
// Mulai sesi (harus selalu di awal)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Konfigurasi Database
$host = 'localhost';
$db   = 'adm.keuangan'; // PASTIKAN NAMA DATABASE SAMA
$user = 'root';
$pass = ''; // Ganti jika Anda menggunakan password untuk root

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    // Error mode: Melempar Exception jika terjadi error
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Mengambil hasil query sebagai array asosiatif
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Mematikan emulasi prepared statement (keamanan)
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     // echo "Koneksi berhasil!"; // Hapus baris ini setelah pengujian
} catch (\PDOException $e) {
     // Pesan error jika koneksi gagal
     die("Koneksi Database Gagal: " . $e->getMessage());
}