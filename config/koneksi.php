<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Konfigurasi Database
$host = 'localhost';
$db   = 'adm.keuangan'; 
$user = 'root';
$pass = ''; 

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
   
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     
} catch (\PDOException $e) {
     // Pesan error jika koneksi gagal
     die("Koneksi Database Gagal: " . $e->getMessage());
}