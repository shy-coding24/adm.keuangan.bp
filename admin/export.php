<?php
// admin/export.php (FINAL DENGAN SKEMA DATABASE)
require_once '../config/koneksi.php';
require_once '../core/auth.php';

// Pastikan admin sudah login dan memiliki akses
check_access('admin');

// --- 1. Ambil Filter dari URL ---
$status_filter = $_GET['status'] ?? 'semua';
$tgl_mulai = $_GET['tgl_mulai'] ?? '';
$tgl_akhir = $_GET['tgl_akhir'] ?? '';

// --- 2. Buat Query SQL dengan JOIN Akurat dan SELECT Spesifik ---
$sql_filter = "
    SELECT 
        tp.tgl_transfer, 
        tp.nis, 
        tp.jumlah_bayar,
        s.nama_siswa,
        s.kelas,          -- Diambil langsung dari tabel siswa
        s.jurusan,        -- Diambil langsung dari tabel siswa
        tp.tipe_tagihan,  -- Tipe tagihan ('spp' atau 'lain')
        tp.related_tagihan_id, -- ID tagihan terkait
        jt.nama_tagihan   -- Nama tagihan (Hanya valid jika tipe_tagihan='lain')
    FROM transaksi_pembayaran tp
    JOIN siswa s ON tp.nis = s.nis
    
    /* LEFT JOIN ke jenis_tagihan diperlukan untuk mendapatkan nama tagihan. 
       Ini akan bekerja untuk tipe_tagihan 'lain'. */
    LEFT JOIN jenis_tagihan jt ON tp.tipe_tagihan = 'lain' AND tp.related_tagihan_id = jt.tagihan_id
    
    WHERE 1=1
";

$params = [];

// --- Logika Filter ---
if ($status_filter != 'semua') {
    $sql_filter .= " AND tp.status_validasi = ?";
    $params[] = $status_filter;
}

if ($tgl_mulai) {
    // tp.tgl_transfer hanya DATE, jadi cukup perbandingan tanggal
    $sql_filter .= " AND tp.tgl_transfer >= ?"; 
    $params[] = $tgl_mulai;
}

if ($tgl_akhir) {
    // tp.tgl_transfer hanya DATE, jadi cukup perbandingan tanggal
    $sql_filter .= " AND tp.tgl_transfer <= ?";
    $params[] = $tgl_akhir;
}

$sql_filter .= " ORDER BY tp.tgl_transfer DESC";

// --- 3. Eksekusi Query ---
try {
    $stmt_transaksi = $pdo->prepare($sql_filter);
    $stmt_transaksi->execute($params);
    $transaksi_list = $stmt_transaksi->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Lebih baik tampilkan error PDO untuk debugging
    die("Gagal mengambil data untuk export: " . $e->getMessage());
}

// --- 4. Mengatur Header HTTP untuk File CSV ---
$filename_status = ($status_filter == 'semua') ? 'semua' : strtolower($status_filter);
$filename = "laporan_keuangan_ringkas_" . $filename_status . "_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

ob_end_clean(); 
$output = fopen('php://output', 'w');
// Tambah BOM (Byte Order Mark) untuk compatibility Excel/Unicode
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// --- 5. Menulis Header Kolom CSV (Sesuai permintaan) ---
$header = [
    'Tgl Upload Bukti (Bayar)', 
    'NIS Siswa', 
    'Nama Siswa', 
    'Kelas', 
    'Jurusan', 
    'Nama Tagihan', 
    'Jumlah Dibayarkan (Rp)'
];
fputcsv($output, $header); 

// --- Menulis Data Transaksi ke CSV ---
foreach ($transaksi_list as $t) {
    
    // Logika untuk Nama Tagihan: Jika tipe 'spp', tampilkan SPP. Jika 'lain', gunakan nama_tagihan.
    if ($t['tipe_tagihan'] == 'spp') {
        $nama_tagihan = 'SPP (Bulan/Tahun ID: ' . $t['related_tagihan_id'] . ')';
    } else {
        $nama_tagihan = $t['nama_tagihan'] ?? 'Tagihan Lain (ID: ' . $t['related_tagihan_id'] . ')';
    }
    
    $row = [
        // 1. Tgl Upload Bukti (tgl_transfer hanya menyimpan tanggal, bukan waktu)
        $t['tgl_transfer'],
        // 2. NIS
        $t['nis'],
        // 3. Nama Siswa
        $t['nama_siswa'],
        // 4. Kelas
        $t['kelas'],
        // 5. Jurusan
        $t['jurusan'],
        // 6. Nama Tagihan
        $nama_tagihan,
        // 7. Jumlah Dibayarkan
        $t['jumlah_bayar']
    ];
    fputcsv($output, $row);
}

fclose($output);
exit();
?>