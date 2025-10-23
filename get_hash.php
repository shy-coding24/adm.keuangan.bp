<?php
// Password default siswa
$password_siswa = '123bp6'; 

// Hasilkan hash
$hash_siswa = password_hash($password_siswa, PASSWORD_DEFAULT);

echo "Hash Baru untuk '123bp6' adalah: <br>";
echo "<strong>" . $hash_siswa . "</strong>"; 
?>