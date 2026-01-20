<?php
require_once '../config/koneksi.php';
require_once '../core/auth.php';

check_access('admin');

$error = '';
$success = '';

$daftar_bulan = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];
$tahun_sekarang = date('Y');
$tahun_list = range($tahun_sekarang - 1, $tahun_sekarang + 50);

$nama_bulan_array = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
    '1' => 'Januari', '2' => 'Februari', '3' => 'Maret', '4' => 'April', '5' => 'Mei', '6' => 'Juni',
    '7' => 'Juli', '8' => 'Agustus', '9' => 'September'
];

// --- Ambil daftar jenis tagihan Administrasi (untuk dropdown) ---
try {
    $stmt_jenis = $pdo->prepare("SELECT tagihan_id, nama_tagihan, jumlah_default, is_active FROM jenis_tagihan WHERE tipe_tagihan IN ('Administrasi', 'Lain') AND is_active = 1 ORDER BY nama_tagihan");
    $stmt_jenis->execute();
    $jenis_tagihan_list = $stmt_jenis->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $jenis_tagihan_list = [];
    $error = "Gagal memuat jenis tagihan: " . $e->getMessage();
}

// --- Handle POST actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ---------- CREATE TAGIHAN SPP MASSAL ----------
    if ($_POST['action'] === 'create_tagihan_spp') {
        $bulan = $_POST['bulan'] ?? '';
        $tahun = $_POST['tahun'] ?? '';
        $jumlah_spp = $_POST['jumlah_spp'] ?? 0;

        if (empty($bulan) || empty($tahun) || !is_numeric($jumlah_spp) || $jumlah_spp <= 0) {
            $error = "Input SPP tidak valid. Pastikan bulan, tahun, dan jumlah SPP diisi dengan benar.";
        } else {
            try {
                $pdo->beginTransaction();

                $stmt_nis = $pdo->query("SELECT nis FROM siswa");
                $all_nis = $stmt_nis->fetchAll(PDO::FETCH_COLUMN);

                $sql_check = "SELECT spp_id FROM tagihan_spp WHERE nis = ? AND bulan = ? AND tahun = ?";
                $sql_insert = "INSERT INTO tagihan_spp (nis, bulan, tahun, jumlah_spp, status_bayar) VALUES (?, ?, ?, ?, 'belum')";

                $count_created = 0; $count_skipped = 0;
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_insert = $pdo->prepare($sql_insert);

                foreach ($all_nis as $nis) {
                    $stmt_check->execute([$nis, $bulan, $tahun]);
                    if ($stmt_check->rowCount() == 0) {
                        $stmt_insert->execute([$nis, $bulan, $tahun, $jumlah_spp]);
                        $count_created++;
                    } else {
                        $count_skipped++;
                    }
                }

                $pdo->commit();
                $success = "✅ Berhasil membuat {$count_created} tagihan SPP untuk bulan {$bulan} {$tahun} (Rp " . number_format($jumlah_spp, 0, ',', '.') . "). ({$count_skipped} tagihan sudah ada dan dilewati).";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "❌ Gagal membuat tagihan SPP: " . $e->getMessage();
            }
        }
    }

    // ---------- CREATE TAGIHAN ADMINISTRASI MASSAL  ----------
    elseif ($_POST['action'] === 'create_tagihan_admin_custom') {

    $nama_tagihan   = trim($_POST['nama_tagihan']);
    $jumlah_tagihan = (int) $_POST['jumlah_tagihan'];
    $tahun_ajaran   = $_POST['tahun_ajaran'];
    $tenggat_bayar  = $_POST['tenggat_bayar'] ?: null;
    $mode           = $_POST['mode'];
    $nis_selected   = $_POST['nis'] ?? [];

    if ($nama_tagihan == '' || $jumlah_tagihan <= 0 || $tahun_ajaran == '') {
        $error = "Input tagihan administrasi tidak valid.";
        return;
    }

    try {
        $pdo->beginTransaction();

        // 1️⃣ Cek / buat jenis_tagihan
        $stmt = $pdo->prepare("SELECT tagihan_id FROM jenis_tagihan WHERE nama_tagihan = ?");
        $stmt->execute([$nama_tagihan]);
        $tagihan_id = $stmt->fetchColumn();

        if (!$tagihan_id) {
            $stmt = $pdo->prepare("
                INSERT INTO jenis_tagihan (nama_tagihan, jumlah_default, tipe_tagihan, is_active)
                VALUES (?, ?, 'Administrasi', 1)
            ");
            $stmt->execute([$nama_tagihan, $jumlah_tagihan]);
            $tagihan_id = $pdo->lastInsertId();
        }

        // 2️⃣ Tentukan target siswa
        if ($mode === 'massal') {
            $stmt = $pdo->query("SELECT nis FROM siswa");
            $target_nis = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            if (empty($nis_selected)) {
                throw new Exception("Tidak ada siswa yang dipilih.");
            }
            $target_nis = $nis_selected;
        }

        // 3️⃣ Insert ke tagihan_lain (cek duplikat A)
        $check = $pdo->prepare("
            SELECT 1 FROM tagihan_lain 
            WHERE nis = ? AND tagihan_id = ? AND tahun_ajaran = ?
        ");

        $insert = $pdo->prepare("
            INSERT INTO tagihan_lain 
            (nis, tagihan_id, jumlah_tagihan, tahun_ajaran, tenggat_bayar, status_bayar)
            VALUES (?, ?, ?, ?, ?, 'belum')
        ");

        $created = 0;
        foreach ($target_nis as $nis) {
            $check->execute([$nis, $tagihan_id, $tahun_ajaran]);
            if (!$check->fetch()) {
                $insert->execute([$nis, $tagihan_id, $jumlah_tagihan, $tahun_ajaran, $tenggat_bayar]);
                $created++;
            }
        }

        $pdo->commit();
        $success = "✅ Tagihan administrasi berhasil dibuat ({$created} siswa).";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "❌ Gagal membuat tagihan administrasi: " . $e->getMessage();
    }
}


    // ---------- EDIT TAGIHAN SPP ----------
    elseif ($_POST['action'] === 'edit_tagihan_spp' && isset($_POST['spp_id'], $_POST['jumlah_spp_edit'])) {
        $spp_id = $_POST['spp_id'];
        $new_jumlah_spp = $_POST['jumlah_spp_edit'];

        if (!is_numeric($new_jumlah_spp) || $new_jumlah_spp <= 0) {
            $error = "Jumlah SPP baru tidak valid.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE tagihan_spp SET jumlah_spp = ? WHERE spp_id = ?");
                $stmt->execute([$new_jumlah_spp, $spp_id]);
                $success = "✅ Berhasil mengubah jumlah SPP tagihan ID {$spp_id} menjadi Rp " . number_format($new_jumlah_spp, 0, ',', '.') . ".";
            } catch (PDOException $e) {
                $error = "❌ Gagal mengedit tagihan SPP: " . $e->getMessage();
            }
        }
    }

    // ---------- EDIT TAGIHAN ADMINISTRASI (tagihan_lain) ----------
    elseif ($_POST['action'] === 'edit_tagihan_admin' && isset($_POST['detail_tagihan_id'], $_POST['jumlah_admin_edit'])) {
        $detail_id = $_POST['detail_tagihan_id'];
        $new_jumlah_admin = $_POST['jumlah_admin_edit'];

        if (!is_numeric($new_jumlah_admin) || $new_jumlah_admin <= 0) {
            $error = "Jumlah tagihan Administrasi baru tidak valid.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE tagihan_lain SET jumlah_tagihan = ? WHERE detail_tagihan_id = ?");
                $stmt->execute([$new_jumlah_admin, $detail_id]);
                $success = "✅ Berhasil mengubah jumlah tagihan Administrasi ID {$detail_id} menjadi Rp " . number_format($new_jumlah_admin, 0, ',', '.') . ".";
            } catch (PDOException $e) {
                $error = "❌ Gagal mengedit tagihan Administrasi: " . $e->getMessage();
            }
        }
    }

    // ---------- DELETE TAGIHAN SPP ----------
    elseif ($_POST['action'] === 'delete_tagihan_spp' && isset($_POST['spp_id'])) {
        $spp_id = $_POST['spp_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM tagihan_spp WHERE spp_id = ?");
            $stmt->execute([$spp_id]);
            $success = "✅ Berhasil menghapus tagihan SPP ID {$spp_id}.";
        } catch (PDOException $e) {
            $error = "❌ Gagal menghapus tagihan SPP: " . $e->getMessage();
        }
    }

    // ---------- DELETE TAGIHAN ADMINISTRASI (tagihan_lain) ----------
    elseif ($_POST['action'] === 'delete_tagihan_admin' && isset($_POST['detail_tagihan_id'])) {
        $detail_id = $_POST['detail_tagihan_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM tagihan_lain WHERE detail_tagihan_id = ?");
            $stmt->execute([$detail_id]);
            $success = "✅ Berhasil menghapus tagihan Administrasi ID {$detail_id}.";
        } catch (PDOException $e) {
            $error = "❌ Gagal menghapus tagihan Administrasi: " . $e->getMessage();
        }
    }


    elseif ($_POST['action'] === 'send_notification') {
        $nis_list = $_POST['nis'] ?? [];

                $fonnte_token = '6K9HoUG9WAqCUfeXVzZ';
        $fonnte_url = 'https://api.fonnte.com/send';

        if (empty($nis_list)) {
            $error = "Pilih setidaknya satu siswa untuk dikirim notifikasi.";
        } elseif (empty($fonnte_token) || $fonnte_token == 'MASUKKAN_TOKEN_API_FONNTE_ANDA_DI_SINI') {
            $error = "Token API Fonnte belum diatur. Mohon isi token yang valid.";
        } else {
            $notif_success_count = 0;
            $notif_fail_count = 0;
            $debug_messages = [];

            // Build tunggakan_data_per_siswa again for current state
            try {
                // Ambil SPP tertunggak
                $sql_spp = "
                    SELECT 
                        s.nis, s.nama_siswa, s.no_telp, s.kelas, s.jurusan,
                        t.spp_id AS tagihan_id, t.bulan, t.tahun, t.jumlah_spp AS jumlah, t.status_bayar,
                        'SPP' AS jenis_tagihan
                    FROM tagihan_spp t
                    JOIN siswa s ON t.nis = s.nis
                    WHERE t.status_bayar IN ('belum', 'menunggu_validasi')
                    ORDER BY s.nis, t.tahun ASC
                ";
                $stmt_spp = $pdo->query($sql_spp);
                $results_spp = $stmt_spp->fetchAll(PDO::FETCH_ASSOC);

                // Ambil Administrasi (tagihan_lain) dengan nama jenis_tagihan
                $sql_admin = "
                    SELECT 
                        s.nis, s.nama_siswa, s.no_telp, s.kelas, s.jurusan,
                        tl.detail_tagihan_id AS tagihan_id, jt.nama_tagihan AS nama_tagihan, tl.tahun_ajaran AS tahun, tl.jumlah_tagihan AS jumlah, tl.status_bayar,
                        'ADMIN' AS jenis_tagihan, tl.tenggat_bayar
                    FROM tagihan_lain tl
                    JOIN siswa s ON tl.nis = s.nis
                    JOIN jenis_tagihan jt ON tl.tagihan_id = jt.tagihan_id
                    WHERE tl.status_bayar IN ('belum', 'menunggu_validasi')
                    ORDER BY s.nis, tl.tahun_ajaran ASC
                ";
                $stmt_admin = $pdo->query($sql_admin);
                $results_admin = $stmt_admin->fetchAll(PDO::FETCH_ASSOC);

                $all_results = array_merge($results_spp, $results_admin);

                $tunggakan_data_per_siswa = [];
                foreach ($all_results as $row) {
                    $nis = $row['nis'];
                    $jenis = $row['jenis_tagihan'];

                    if (!isset($tunggakan_data_per_siswa[$nis])) {
                        $tunggakan_data_per_siswa[$nis] = [
                            'nama_siswa' => $row['nama_siswa'],
                            'no_telp' => $row['no_telp'],
                            'kelas' => $row['kelas'],
                            'jurusan' => $row['jurusan'],
                            'total_tunggakan' => 0,
                            'detail_spp' => [],
                            'detail_admin' => []
                        ];
                    }

                    $tunggakan_data_per_siswa[$nis]['total_tunggakan'] += $row['jumlah'];

                    if ($jenis === 'SPP') {
                        $deskripsi = $nama_bulan_array[$row['bulan']] ?? $row['bulan'];
                        $tunggakan_data_per_siswa[$nis]['detail_spp'][] = [
                            'tagihan_id' => $row['tagihan_id'],
                            'deskripsi' => $deskripsi,
                            'tahun' => $row['tahun'],
                            'jumlah' => $row['jumlah'],
                            'status_bayar' => $row['status_bayar']
                        ];
                    } else { // ADMIN
                        $deskripsi = $row['nama_tagihan'];
                        $tunggakan_data_per_siswa[$nis]['detail_admin'][] = [
                            'tagihan_id' => $row['tagihan_id'], // detail_tagihan_id
                            'deskripsi' => $deskripsi,
                            'tahun' => $row['tahun'],
                            'jumlah' => $row['jumlah'],
                            'status_bayar' => $row['status_bayar']
                        ];
                    }
                }

            } catch (PDOException $e) {
                $error = "Gagal membangun data tunggakan: " . $e->getMessage();
            }

            // Kirim per nomor
            foreach ($nis_list as $nis_to_notify) {
                if (!isset($tunggakan_data_per_siswa[$nis_to_notify])) {
                    $debug_messages[] = "NIS {$nis_to_notify} tidak memiliki tunggakan aktif.";
                    $notif_fail_count++;
                    continue;
                }

                $siswa = $tunggakan_data_per_siswa[$nis_to_notify];
                $nomor_hp_mentah = $siswa['no_telp'];
                $nama_siswa = $siswa['nama_siswa'];
                $kelas_siswa = $siswa['kelas'];
                $jurusan_siswa = $siswa['jurusan'];
                $total_tunggakan = number_format($siswa['total_tunggakan'], 0, ',', '.');

                // Format nomor ke 62...
                $nomor_hp = trim($nomor_hp_mentah);
                $nomor_hp = preg_replace('/[^\d+]/', '', $nomor_hp);

                if (substr($nomor_hp, 0, 1) === '0') {
                    $nomor_hp = '62' . substr($nomor_hp, 1);
                } elseif (substr($nomor_hp, 0, 3) === '+62') {
                    $nomor_hp = '62' . substr($nomor_hp, 3);
                }

                if (substr($nomor_hp, 0, 2) !== '62' || strlen($nomor_hp) < 10) {
                    $debug_messages[] = "Gagal NIS {$nis_to_notify}: Format nomor ({$nomor_hp_mentah}) tidak valid (Harus 62...).";
                    $notif_fail_count++;
                    continue;
                }

                // Build details
                $detail_spp = '';
                $detail_admin = '';

                if (!empty($siswa['detail_spp'])) {
                    $detail_spp .= "* Tagihan SPP:\n";
                    foreach ($siswa['detail_spp'] as $tagihan) {
                        $detail_spp .= "  - {$tagihan['deskripsi']} {$tagihan['tahun']}: Rp " . number_format($tagihan['jumlah'], 0, ',', '.') . " (" . ucfirst(str_replace('_', ' ', $tagihan['status_bayar'])) . ")\n";
                    }
                }

                if (!empty($siswa['detail_admin'])) {
                    $detail_admin .= "* Tagihan Administrasi:\n";
                    foreach ($siswa['detail_admin'] as $tagihan) {
                        $detail_admin .= "  - {$tagihan['deskripsi']} ({$tagihan['tahun']}): Rp " . number_format($tagihan['jumlah'], 0, ',', '.') . " (" . ucfirst(str_replace('_', ' ', $tagihan['status_bayar'])) . ")\n";
                    }
                }

                $detail_tagihan = $detail_spp . ($detail_spp != '' && $detail_admin != '' ? "\n" : "") . $detail_admin;

                $message = "Yth. Siswa/Siswi *{$nama_siswa}* (NIS: {$nis_to_notify}, Kelas: {$kelas_siswa}, Jurusan: {$jurusan_siswa}).\n\nKami informasikan bahwa terdapat tunggakan tagihan dengan rincian:\n\n{$detail_tagihan}\nTotal Tunggakan: *Rp {$total_tunggakan}*.\n\nMohon segera diselesaikan atau segera konfirmasi pembayaran jika sudah bayar.\n\nTerima kasih.\n\n_(Pesan otomatis Admin Keuangan)_";

                $data = [
                    'target' => $nomor_hp,
                    'message' => $message,
                ];

                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL => $fonnte_url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => http_build_query($data),
                    CURLOPT_HTTPHEADER => [
                        "Authorization: " . $fonnte_token
                    ],
                ]);

                $response = curl_exec($curl);
                $err = curl_error($curl);
                curl_close($curl);

                if ($err) {
                    $debug_messages[] = "Gagal NIS {$nis_to_notify}: cURL Error: " . $err;
                    $notif_fail_count++;
                } else {
                    $json_response = json_decode($response, true);
                    if (isset($json_response['status']) && $json_response['status'] == 'success') {
                        $notif_success_count++;
                    } else {
                        $error_detail = $json_response['detail'] ?? $response;
                        $debug_messages[] = "Gagal NIS {$nis_to_notify}: Fonnte Error: " . (is_array($error_detail) ? json_encode($error_detail) : $error_detail);
                        $notif_fail_count++;
                    }
                }
            }

            $success = "✅ Berhasil mengirim notifikasi kepada {$notif_success_count} siswa. ❌ Gagal: {$notif_fail_count}.";
            if (!empty($debug_messages)) {
                $error_combined = "Ditemukan masalah pengiriman:\n- " . implode("\n- ", $debug_messages);
                $error = "{$error_combined}";
            }
        }
    }

} // end POST actions


// ----------------- Ambil data tunggakan untuk tampilan (gabungan SPP + Administrasi) -----------------
$tunggakan_spp_per_tagihan = [];
$tunggakan_admin_per_tagihan = [];
$tunggakan_data_per_siswa = [];
$tunggakan_error = '';

try {
    // SPP
    $sql_spp = "
        SELECT 
            s.nis, s.nama_siswa, s.no_telp, s.kelas, s.jurusan,
            t.spp_id AS tagihan_id, t.bulan, t.tahun, t.jumlah_spp AS jumlah, t.status_bayar,
            'SPP' AS jenis_tagihan
        FROM tagihan_spp t
        JOIN siswa s ON t.nis = s.nis
        WHERE t.status_bayar IN ('belum', 'menunggu_validasi')
        ORDER BY s.nis, t.tahun ASC, FIELD(t.bulan, 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember') ASC
    ";
    $stmt_spp = $pdo->query($sql_spp);
    $results_spp = $stmt_spp->fetchAll(PDO::FETCH_ASSOC);

    // Administrasi (tagihan_lain JOIN jenis_tagihan)
    $sql_admin = "
        SELECT
            s.nis, s.nama_siswa, s.no_telp, s.kelas, s.jurusan,
            tl.detail_tagihan_id AS detail_id, tl.tagihan_id AS jenis_id, jt.nama_tagihan AS nama_tagihan,
            tl.tahun_ajaran AS tahun, tl.jumlah_tagihan AS jumlah, tl.status_bayar, tl.tenggat_bayar
        FROM tagihan_lain tl
        JOIN siswa s ON tl.nis = s.nis
        JOIN jenis_tagihan jt ON tl.tagihan_id = jt.tagihan_id
        WHERE tl.status_bayar IN ('belum', 'menunggu_validasi')
        ORDER BY s.nis, tl.tahun_ajaran ASC, jt.nama_tagihan ASC
    ";
    $stmt_admin = $pdo->query($sql_admin);
    $results_admin = $stmt_admin->fetchAll(PDO::FETCH_ASSOC);

    // Proses hasil untuk tampilan
    foreach ($results_spp as $row) {
        $row_display = $row;
        $row_display['bulan_display'] = $nama_bulan_array[$row['bulan']] ?? $row['bulan'];
        $row_display['tipe_tagihan'] = 'SPP';
        // map keys for consistency with admin rows
        $row_display['tagihan_id'] = $row['tagihan_id']; // spp_id
        $row_display['detail_id'] = $row['tagihan_id']; // to be used as identifier in actions
        $tunggakan_spp_per_tagihan[] = $row_display;

        // agregasi per siswa
        $nis = $row['nis'];
        if (!isset($tunggakan_data_per_siswa[$nis])) {
            $tunggakan_data_per_siswa[$nis] = [
                'nama_siswa' => $row['nama_siswa'],
                'no_telp' => $row['no_telp'],
                'kelas' => $row['kelas'],
                'jurusan' => $row['jurusan'],
                'total_tunggakan' => 0,
                'detail_spp' => [],
                'detail_admin' => []
            ];
        }
        $tunggakan_data_per_siswa[$nis]['total_tunggakan'] += $row['jumlah'];
        $tunggakan_data_per_siswa[$nis]['detail_spp'][] = [
            'tagihan_id' => $row['tagihan_id'],
            'deskripsi' => $row_display['bulan_display'],
            'tahun' => $row['tahun'],
            'jumlah' => $row['jumlah'],
            'status_bayar' => $row['status_bayar']
        ];
    }

    foreach ($results_admin as $row) {
        $row_display = [
            'nis' => $row['nis'],
            'nama_siswa' => $row['nama_siswa'],
            'no_telp' => $row['no_telp'],
            'kelas' => $row['kelas'],
            'jurusan' => $row['jurusan'],
            'tagihan_id' => $row['jenis_id'], // jenis_tagihan id
            'detail_id' => $row['detail_id'], // detail_tagihan_id (unik)
            'bulan_display' => $row['nama_tagihan'],
            'tahun' => $row['tahun'],
            'jumlah' => $row['jumlah'],
            'status_bayar' => $row['status_bayar'],
            'tenggat_bayar' => $row['tenggat_bayar'],
            'tipe_tagihan' => 'ADMIN'
        ];
        $tunggakan_admin_per_tagihan[] = $row_display;

        // agregasi per siswa
        $nis = $row['nis'];
        if (!isset($tunggakan_data_per_siswa[$nis])) {
            $tunggakan_data_per_siswa[$nis] = [
                'nama_siswa' => $row['nama_siswa'],
                'no_telp' => $row['no_telp'],
                'kelas' => $row['kelas'],
                'jurusan' => $row['jurusan'],
                'total_tunggakan' => 0,
                'detail_spp' => [],
                'detail_admin' => []
            ];
        }
        $tunggakan_data_per_siswa[$nis]['total_tunggakan'] += $row['jumlah'];
        $tunggakan_data_per_siswa[$nis]['detail_admin'][] = [
            'tagihan_id' => $row['detail_id'], // detail id
            'deskripsi' => $row['nama_tagihan'],
            'tahun' => $row['tahun'],
            'jumlah' => $row['jumlah'],
            'status_bayar' => $row['status_bayar']
        ];
    }

} catch (PDOException $e) {
    $tunggakan_error = "Gagal mengambil data tunggakan: " . $e->getMessage();
}

if (isset($tunggakan_spp_per_tagihan) && isset($tunggakan_admin_per_tagihan)) {
    $all_tunggakan = array_merge($tunggakan_spp_per_tagihan, $tunggakan_admin_per_tagihan);
} else {
    $all_tunggakan = [];
}


$total_tagihan_tunggak = count($all_tunggakan);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tagihan SPP & Administrasi - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --primary-light: #e0e7ff;
            --secondary: #8b5cf6;
            --danger: #ef4444;
            --warning: #f59e0b;
            --success: #10b981;
            --info: #3b82f6;
            --light: #f8fafc;
            --dark: #1e293b;
            --gray: #64748b;
            --gray-light: #e2e8f0;
            --border-radius: 12px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: var(--dark);
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 28px 40px;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            opacity: 0.1;
        }

        .header-content {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .title-icon {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .title-text h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            color: white;
        }

        .title-text p {
            font-size: 16px;
            opacity: 0.9;
            font-weight: 400;
            color: rgba(255, 255, 255, 0.9);
        }

        .back-link {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            padding: 14px 26px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.12);
            transition: var(--transition);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            font-size: 15px;
        }

        .back-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }

        /* Content Area */
        .content-area {
            padding: 40px;
        }

        /* Alerts */
        .alert {
            padding: 20px 24px;
            border-radius: var(--border-radius);
            margin-bottom: 32px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            animation: slideDown 0.4s ease-out;
            position: relative;
            overflow: hidden;
            border: 1px solid transparent;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            border-radius: 5px 0 0 5px;
        }

        .alert-success {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border-color: #86efac;
            color: #166534;
        }

        .alert-success::before {
            background: var(--success);
        }

        .alert-error {
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
            border-color: #fca5a5;
            color: #991b1b;
        }

        .alert-error::before {
            background: var(--danger);
        }

        .alert-icon {
            font-size: 22px;
            flex-shrink: 0;
        }

        /* Tabs */
        .tabs-container {
            margin-bottom: 40px;
        }

        .tabs {
            display: flex;
            background: var(--light);
            border-radius: 16px;
            padding: 8px;
            margin-bottom: 32px;
            gap: 8px;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .tabs::-webkit-scrollbar {
            display: none;
        }

        .tab-button {
            flex: 1;
            min-width: 240px;
            padding: 18px 24px;
            text-align: center;
            cursor: pointer;
            border: none;
            background: transparent;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: var(--gray);
            border-radius: 12px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
            font-size: 15px;
        }

        .tab-button:hover {
            background: rgba(79, 70, 229, 0.08);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .tab-button.active {
            background: white;
            color: var(--primary);
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }

        .tab-button .badge {
            background: var(--danger);
            color: white;
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 20px;
            margin-left: 8px;
            font-weight: 600;
            min-width: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .tab-content {
            display: none;
            animation: fadeInUp 0.5s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .tab-content.active {
            display: block;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 32px;
            margin-bottom: 32px;
            border: 1px solid var(--gray-light);
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-4px);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--gray-light);
        }

        .card-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
        }

        .card-title {
            font-family: 'Poppins', sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
            line-height: 1.3;
        }

        .card-subtitle {
            color: var(--gray);
            font-size: 15px;
            margin-top: 6px;
            font-weight: 400;
        }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 28px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--dark);
            font-size: 15px;
        }

        .form-control {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid var(--gray-light);
            border-radius: 12px;
            font-size: 15px;
            transition: var(--transition);
            background: var(--light);
            font-family: 'Inter', sans-serif;
            color: var(--dark);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
            background: white;
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        /* Buttons */
        .btn {
            padding: 16px 32px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            position: relative;
            overflow: hidden;
        }

        .btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }

        .btn:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }

        @keyframes ripple {
            0% { transform: scale(0, 0); opacity: 0.5; }
            100% { transform: scale(20, 20); opacity: 0; }
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(79, 70, 229, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(239, 68, 68, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning), #d97706);
            color: white;
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
        }

        .btn-warning:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(245, 158, 11, 0.4);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-outline:hover {
            background: rgba(79, 70, 229, 0.08);
            transform: translateY(-2px);
        }

        /* Action Buttons Group - DI BAWAH TABEL */
        .action-buttons-group {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 32px;
            justify-content: center;
            padding: 24px;
            background: var(--light);
            border-radius: 16px;
            border: 1px solid var(--gray-light);
        }

        /* Tables */
        .table-container {
            overflow-x: auto;
            border-radius: 16px;
            border: 1px solid var(--gray-light);
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
            position: relative;
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 14px;
            min-width: 1200px;
        }

        .data-table thead {
            background: linear-gradient(135deg, var(--primary-light), #e0e7ff);
        }

        .data-table th {
            padding: 20px 16px;
            text-align: left;
            font-weight: 600;
            color: var(--primary-dark);
            border-bottom: 2px solid var(--primary);
            white-space: nowrap;
            position: sticky;
            top: 0;
            background: inherit;
            z-index: 10;
            font-family: 'Poppins', sans-serif;
        }

        .data-table td {
            padding: 18px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            transition: var(--transition);
        }

        .data-table tbody tr {
            transition: var(--transition);
        }

        .data-table tbody tr:hover {
            background: #f8fafc;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            min-width: 140px;
            justify-content: center;
            backdrop-filter: blur(10px);
        }

        .status-belum {
            background: rgba(239, 68, 68, 0.12);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .status-menunggu {
            background: rgba(245, 158, 11, 0.12);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        /* Checkbox Styling */
        .checkbox-container {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .checkbox-custom {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .checkbox-custom input[type="checkbox"] {
            display: none;
        }

        .checkbox-custom .checkmark {
            width: 24px;
            height: 24px;
            border: 2px solid #cbd5e1;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            background: white;
            position: relative;
        }

        .checkbox-custom .checkmark::after {
            content: "✓";
            color: white;
            font-weight: bold;
            opacity: 0;
            transition: var(--transition);
            transform: scale(0.8);
            font-size: 14px;
        }

        .checkbox-custom input[type="checkbox"]:checked + .checkmark {
            background: var(--primary);
            border-color: var(--primary);
        }

        .checkbox-custom input[type="checkbox"]:checked + .checkmark::after {
            opacity: 1;
            transform: scale(1);
        }

        /* Action Links */
        .action-links {
            display: flex;
            gap: 8px;
        }

        .action-link {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            border: none;
            background: none;
            font-family: 'Inter', sans-serif;
        }

        .action-link.edit {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
        }

        .action-link.edit:hover {
            background: rgba(79, 70, 229, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.15);
        }

        .action-link.delete {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .action-link.delete:hover {
            background: rgba(239, 68, 68, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--gray);
            background: var(--light);
            border-radius: 20px;
            margin: 40px 0;
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 24px;
            opacity: 0.7;
        }

        .empty-state h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--dark);
        }

        .empty-state p {
            max-width: 400px;
            margin: 0 auto;
            line-height: 1.7;
            font-size: 16px;
        }

        /* Info Box */
        .info-box {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 32px;
            border-left: 5px solid var(--info);
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }

        .info-box i {
            color: var(--info);
            font-size: 20px;
            margin-top: 3px;
            flex-shrink: 0;
        }

        .info-box p {
            color: var(--dark);
            line-height: 1.7;
            margin: 0;
            flex: 1;
            font-size: 15px;
        }

        /* Tag Type Badges */
        .type-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .type-spp {
            background: rgba(79, 70, 229, 0.12);
            color: var(--primary);
            border: 1px solid rgba(79, 70, 229, 0.2);
        }

        .type-spp::before {
            content: "💰";
            font-size: 14px;
        }

        .type-admin {
            background: rgba(16, 185, 129, 0.12);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .type-admin::before {
            content: "📋";
            font-size: 14px;
        }

        /* Amount Styling */
        .amount {
            font-weight: 700;
            color: var(--primary-dark);
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
        }

        /* Status Indicator */
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .status-indicator.belum {
            background: var(--danger);
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
        }

        .status-indicator.menunggu {
            background: var(--warning);
            box-shadow: 0 0 10px rgba(245, 158, 11, 0.5);
        }

        /* Custom Radio & Checkbox */
        .radio-group {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }

        .radio-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 12px 20px;
            border: 2px solid var(--gray-light);
            border-radius: 12px;
            transition: var(--transition);
            flex: 1;
            max-width: 200px;
        }

        .radio-label:hover {
            border-color: var(--primary);
            background: rgba(79, 70, 229, 0.05);
        }

        .radio-label input[type="radio"] {
            display: none;
        }

        .radio-label .radio-custom {
            width: 20px;
            height: 20px;
            border: 2px solid #cbd5e1;
            border-radius: 50%;
            position: relative;
            transition: var(--transition);
        }

        .radio-label input[type="radio"]:checked + .radio-custom {
            border-color: var(--primary);
            background: var(--primary);
        }

        .radio-label input[type="radio"]:checked + .radio-custom::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
        }

        .student-checkbox-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 12px;
            max-height: 300px;
            overflow-y: auto;
            padding: 16px;
            border: 1px solid var(--gray-light);
            border-radius: 12px;
            margin: 20px 0;
            background: var(--light);
        }

        .student-checkbox-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 8px;
            background: white;
            border: 1px solid var(--gray-light);
            transition: var(--transition);
        }

        .student-checkbox-item:hover {
            border-color: var(--primary);
            transform: translateX(4px);
        }

        .student-checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            border: 2px solid #cbd5e1;
            cursor: pointer;
        }

        /* Table Info */
        .table-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 16px;
            background: var(--light);
            border-radius: 12px;
            border: 1px solid var(--gray-light);
        }

        .table-stats {
            display: flex;
            align-items: center;
            gap: 20px;
            color: var(--gray);
            font-size: 14px;
        }

        .table-stats i {
            color: var(--primary);
        }

        .selection-info {
            color: var(--gray);
            font-size: 14px;
        }

        .selection-info strong {
            color: var(--primary);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .header {
                padding: 24px 32px;
            }
            
            .content-area {
                padding: 32px;
            }
            
            .tab-button {
                min-width: 200px;
                padding: 16px 20px;
            }
        }

        @media (max-width: 992px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab-button {
                min-width: 100%;
                justify-content: flex-start;
                text-align: left;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn {
                width: 100%;
            }
            
            .student-checkbox-container {
                grid-template-columns: 1fr;
            }
            
            .table-info {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 16px;
            }
            
            .container {
                border-radius: 16px;
            }
            
            .header {
                padding: 20px 24px;
            }
            
            .content-area {
                padding: 24px;
            }
            
            .title-icon {
                width: 56px;
                height: 56px;
                font-size: 24px;
            }
            
            .title-text h2 {
                font-size: 24px;
            }
            
            .card {
                padding: 24px;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            
            .data-table {
                font-size: 13px;
            }
            
            .data-table th,
            .data-table td {
                padding: 16px 12px;
            }
            
            .action-links {
                flex-direction: column;
                gap: 8px;
            }
            
            .info-box {
                flex-direction: column;
                gap: 12px;
            }
            
            .radio-group {
                flex-direction: column;
            }
            
            .radio-label {
                max-width: 100%;
            }
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Loader */
        .loader {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(79, 70, 229, 0.2);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div class="page-title">
                    <div class="title-icon">
                        <i class="fas fa-money-check-alt"></i>
                    </div>
                    <div class="title-text">
                        <h2>Kelola Tagihan SPP & Administrasi</h2>
                        <p>Manajemen pembayaran dan notifikasi tagihan sekolah</p>
                    </div>
                </div>
                <a href="index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    <span>Kembali ke Dashboard</span>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle alert-icon"></i>
                    <div><?= $success ?></div>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle alert-icon"></i>
                    <div><?= $error ?></div>
                </div>
            <?php endif; ?>

            <!-- Tabs Container -->
            <div class="tabs-container">
                <!-- Tabs Navigation -->
                <div class="tabs">
                    <button class="tab-button active" data-tab="tabSPP">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Tagihan SPP</span>
                    </button>
                    <button class="tab-button" data-tab="tabAdmin">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Tagihan Administrasi</span>
                    </button>
                    <button class="tab-button" data-tab="tabNotifikasi">
                        <i class="fas fa-bell"></i>
                        <span>Notifikasi Tunggakan</span>
                        <?php if (count($tunggakan_data_per_siswa) > 0): ?>
                            <span class="badge"><?= count($tunggakan_data_per_siswa) ?></span>
                        <?php endif; ?>
                    </button>
                </div>

                <!-- Tab 1: Kelola Tagihan SPP -->
                <div id="tabSPP" class="tab-content active">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <div>
                                <h3 class="card-title">Buat Tagihan SPP Massal</h3>
                                <p class="card-subtitle">Buat tagihan SPP untuk semua siswa dalam satu waktu</p>
                            </div>
                        </div>

                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <p>
                                Proses ini akan membuat tagihan SPP 'belum lunas' untuk semua siswa yang belum memiliki tagihan pada bulan/tahun yang dipilih.
                                Tagihan duplikat (sudah ada) akan dilewati secara otomatis.
                            </p>
                        </div>

                        <form method="POST" action="" id="createTagihanSPPForm">
                            <input type="hidden" name="action" value="create_tagihan_spp">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="bulan">Bulan Tagihan</label>
                                    <select class="form-control" id="bulan" name="bulan" required>
                                        <option value="">Pilih Bulan</option>
                                        <?php foreach ($daftar_bulan as $b): ?>
                                            <option value="<?= $b ?>" <?= (isset($_POST['bulan']) && $_POST['bulan'] == $b) ? 'selected' : '' ?>>
                                                <?= $b ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="tahun">Tahun Tagihan</label>
                                    <select class="form-control" id="tahun" name="tahun" required>
                                        <option value="">Pilih Tahun</option>
                                        <?php foreach ($tahun_list as $t): ?>
                                            <option value="<?= $t ?>" <?= (isset($_POST['tahun']) && $_POST['tahun'] == $t) ? 'selected' : '' ?>>
                                                <?= $t ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="jumlah_spp">Jumlah SPP (Rp)</label>
                                    <input type="number" class="form-control" id="jumlah_spp" name="jumlah_spp" 
                                           required placeholder="Contoh: 250000" 
                                           value="<?= htmlspecialchars($_POST['jumlah_spp'] ?? '') ?>">
                                    <small style="display: block; margin-top: 8px; color: var(--gray); font-size: 13px;">
                                        Masukkan jumlah dalam angka (tanpa titik atau koma)
                                    </small>
                                </div>
                            </div>
                            
                            <div style="text-align: center; margin-top: 32px;">
                                <button type="submit" class="btn btn-primary" id="createSppBtn" style="padding: 16px 48px;">
                                    <i class="fas fa-bolt"></i>
                                    <span>Buat Tagihan SPP Sekarang</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tab 2: Kelola Tagihan Administrasi -->
                <div id="tabAdmin" class="tab-content">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div>
                                <h3 class="card-title">Buat Tagihan Administrasi</h3>
                                <p class="card-subtitle">Buat tagihan administrasi untuk siswa</p>
                            </div>
                        </div>

                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <p>
                                Buat tagihan administrasi custom untuk siswa. Sistem akan mengecek duplikasi berdasarkan NIS, nama tagihan, dan tahun ajaran.
                                Anda dapat memilih membuat untuk semua siswa atau memilih siswa tertentu.
                            </p>
                        </div>

                        <form method="POST" action="" id="createTagihanAdminForm">
                            <input type="hidden" name="action" value="create_tagihan_admin_custom">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="nama_tagihan">Nama Tagihan</label>
                                    <input type="text" class="form-control" id="nama_tagihan" name="nama_tagihan" 
                                           required placeholder="Contoh: Uang Seragam, Buku Paket, dll">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="jumlah_tagihan">Jumlah Tagihan (Rp)</label>
                                    <input type="number" class="form-control" id="jumlah_tagihan" name="jumlah_tagihan" 
                                           required placeholder="Masukkan jumlah tagihan">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="tahun_ajaran">Tahun Ajaran</label>
                                    <select class="form-control" id="tahun_ajaran" name="tahun_ajaran" required>
                                        <option value="">Pilih Tahun Ajaran</option>
                                        <?php foreach ($tahun_list as $t): 
                                            $label = $t . '/' . ($t+1);
                                            $value = $label;
                                        ?>
                                            <option value="<?= $value ?>">
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="tenggat_bayar">Tenggat Bayar (Opsional)</label>
                                    <input type="date" class="form-control" id="tenggat_bayar" name="tenggat_bayar">
                                </div>
                            </div>

                            <div class="radio-group">
                                <label class="radio-label">
                                    <input type="radio" name="mode" value="massal" checked>
                                    <span class="radio-custom"></span>
                                    <div>
                                        <strong>Massal</strong>
                                        <div style="font-size: 13px; color: var(--gray); margin-top: 4px;">Semua siswa</div>
                                    </div>
                                </label>
                                
                                <label class="radio-label">
                                    <input type="radio" name="mode" value="pilih">
                                    <span class="radio-custom"></span>
                                    <div>
                                        <strong>Pilih Siswa</strong>
                                        <div style="font-size: 13px; color: var(--gray); margin-top: 4px;">Siswa tertentu</div>
                                    </div>
                                </label>
                            </div>

                            <div id="pilihSiswa" class="student-checkbox-container" style="display:none;">
                                <?php
                                $siswa = $pdo->query("SELECT nis, nama_siswa FROM siswa ORDER BY nama_siswa")->fetchAll();
                                foreach ($siswa as $s):
                                ?>
                                    <label class="student-checkbox-item">
                                        <input type="checkbox" name="nis[]" value="<?= $s['nis'] ?>">
                                        <div>
                                            <strong style="color: var(--dark);"><?= htmlspecialchars($s['nis']) ?></strong>
                                            <div style="font-size: 13px; color: var(--gray); margin-top: 2px;"><?= htmlspecialchars($s['nama_siswa']) ?></div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div style="text-align: center; margin-top: 32px;">
                                <button type="submit" class="btn btn-primary" id="createAdminBtn" style="padding: 16px 48px;">
                                    <i class="fas fa-file-invoice"></i>
                                    <span>Buat Tagihan Administrasi</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tab 3: Notifikasi Tunggakan -->
                <div id="tabNotifikasi" class="tab-content">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div>
                                <h3 class="card-title">Daftar Tunggakan Aktif</h3>
                                <p class="card-subtitle">
                                    Total <?= count($tunggakan_data_per_siswa) ?> siswa dengan tunggakan
                                    • <?= count($tunggakan_spp_per_tagihan) + count($tunggakan_admin_per_tagihan) ?> tagihan tertunggak
                                </p>
                            </div>
                        </div>

                        <?php if ($tunggakan_error): ?>
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-circle alert-icon"></i>
                                <div><?= $tunggakan_error ?></div>
                            </div>
                        <?php elseif (empty($tunggakan_spp_per_tagihan) && empty($tunggakan_admin_per_tagihan)): ?>
                            <div class="empty-state">
                                <i class="fas fa-check-circle"></i>
                                <h3>Tidak Ada Tunggakan</h3>
                                <p>
                                    Tidak ada tagihan SPP atau Administrasi yang tertunggak saat ini. 
                                    Semua tagihan telah lunas atau tervalidasi.
                                </p>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="" id="notificationForm">
                                <input type="hidden" name="action" value="send_notification">
                                
                                <div class="info-box">
                                    <i class="fas fa-info-circle"></i>
                                    <p>
                                        Tabel ini menampilkan semua tagihan (SPP dan Administrasi) yang masih berstatus "Belum Bayar" atau "Menunggu Validasi". 
                                        Pilih siswa dengan mencentang checkbox pada kolom pertama.
                                    </p>
                                </div>

                                <div class="table-info">
                                    <div class="table-stats">
    <div>
        <i class="fas fa-database"></i>
        <span><?= $total_tagihan_tunggak ?> tagihan ditampilkan</span>
    </div>
    <div>
        <i class="fas fa-users"></i>
        <span><?= count($tunggakan_data_per_siswa) ?> siswa memiliki tunggakan</span>
    </div>
</div>
                                    <div class="selection-info">
                                        <i class="fas fa-user-check"></i>
                                        <span id="selectedCount">0</span> siswa terpilih
                                    </div>
                                </div>

                                <div class="table-container">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 60px; text-align: center;">
                                                    <label class="checkbox-custom">
                                                        <input type="checkbox" id="masterCheckbox">
                                                        <span class="checkmark"></span>
                                                    </label>
                                                </th>
                                                <th>NIS</th>
                                                <th>Nama Siswa</th>
                                                <th>Kelas</th>
                                                <th>Jurusan</th>
                                                <th>Kontak</th>
                                                <th>Tipe</th>
                                                <th>Tagihan</th>
                                                <th>Jumlah</th>
                                                <th>Status</th>
                                                <th style="width: 160px;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $processed_nis = [];
                                            $all_tunggakan = array_merge($tunggakan_spp_per_tagihan, $tunggakan_admin_per_tagihan);

                                            usort($all_tunggakan, function($a, $b) {
                                                if ($a['nis'] !== $b['nis']) return strcmp($a['nis'], $b['nis']);
                                                if (($a['tipe_tagihan'] ?? '') !== ($b['tipe_tagihan'] ?? '')) return strcmp($a['tipe_tagihan'], $b['tipe_tagihan']);
                                                if (($a['tahun'] ?? '') !== ($b['tahun'] ?? '')) return strcmp($a['tahun'], $b['tahun']);
                                                return strcmp(($a['bulan_display'] ?? $a['bulan_display']), ($b['bulan_display'] ?? $b['bulan_display']));
                                            });

                                            foreach ($all_tunggakan as $row):
                                                $nis = $row['nis'];
                                                $tipe = $row['tipe_tagihan'];
                                                $detail_id = $row['detail_id'] ?? $row['tagihan_id'];
                                                $is_new_nis = !isset($processed_nis[$nis]);
                                                $processed_nis[$nis] = true;
                                            ?>
                                                <tr>
                                                    <td class="checkbox-container">
                                                        <?php if ($is_new_nis): ?>
                                                            <label class="checkbox-custom">
                                                                <input type="checkbox" name="nis[]" value="<?= htmlspecialchars($nis) ?>" class="student-checkbox" id="nis_<?= htmlspecialchars($nis) ?>">
                                                                <span class="checkmark"></span>
                                                            </label>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <strong style="color: var(--primary-dark);"><?= htmlspecialchars($nis) ?></strong>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($row['nama_siswa']) ?></strong>
                                                    </td>
                                                    <td><?= htmlspecialchars($row['kelas']) ?></td>
                                                    <td><?= htmlspecialchars($row['jurusan']) ?></td>
                                                    <td>
                                                        <i class="fas fa-phone" style="color: var(--gray); margin-right: 8px; font-size: 12px;"></i>
                                                        <span style="font-family: monospace; font-size: 13px;"><?= htmlspecialchars($row['no_telp']) ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="type-badge <?= $tipe === 'SPP' ? 'type-spp' : 'type-admin' ?>">
                                                            <?= $tipe ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div style="font-weight: 500; font-size: 14px;"><?= htmlspecialchars($row['bulan_display']) ?></div>
                                                        <div style="font-size: 12px; color: var(--gray);"><?= htmlspecialchars($row['tahun'] ?? '') ?></div>
                                                    </td>
                                                    <td>
                                                        <div class="amount">Rp <?= number_format($row['jumlah'], 0, ',', '.') ?></div>
                                                    </td>
                                                    <td>
                                                        <?php
                                                            $status = ucfirst(str_replace('_', ' ', $row['status_bayar']));
                                                            $class = ($row['status_bayar'] == 'belum') ? 'status-badge status-belum' : 'status-badge status-menunggu';
                                                            $indicator = ($row['status_bayar'] == 'belum') ? 'belum' : 'menunggu';
                                                        ?>
                                                        <div class="<?= $class ?>">
                                                            <span class="status-indicator <?= $indicator ?>"></span>
                                                            <?= $status ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="action-links">
                                                            <button class="action-link edit edit-tagihan-btn"
                                                                    data-id="<?= htmlspecialchars($detail_id) ?>"
                                                                    data-tipe="<?= htmlspecialchars($tipe) ?>"
                                                                    data-jumlah="<?= htmlspecialchars($row['jumlah']) ?>"
                                                                    title="Edit Jumlah Tagihan">
                                                                <i class="fas fa-edit"></i>
                                                                <span>Edit</span>
                                                            </button>

                                                            <button class="action-link delete" 
                                                                    onclick="deleteTagihan('<?= htmlspecialchars($tipe) ?>', <?= htmlspecialchars($detail_id) ?>)"
                                                                    title="Hapus Tagihan">
                                                                <i class="fas fa-trash"></i>
                                                                <span>Hapus</span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- BUTTONS DI BAWAH TABEL (SETELAH TABEL) -->
                                <div class="action-buttons-group">
                                    <button type="button" class="btn btn-warning" id="selectAllBtn">
                                        <i class="fas fa-check-double"></i>
                                        <span id="selectAllText">Pilih Semua Siswa</span>
                                    </button>
                                    <button type="submit" class="btn btn-danger" id="sendNotificationBtn">
                                        <i class="fab fa-whatsapp"></i>
                                        <span>Kirim Notifikasi WhatsApp</span>
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab Functionality
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    // Activate tab based on URL parameter or default
    const urlParams = new URLSearchParams(window.location.search);
    const initialTab = urlParams.get('tab') || 'tabSPP';
    
    const activateTab = (tabId) => {
        // Update tab buttons
        tabButtons.forEach(btn => {
            btn.classList.remove('active');
            if (btn.getAttribute('data-tab') === tabId) {
                btn.classList.add('active');
            }
        });
        
        // Update tab contents
        tabContents.forEach(content => {
            content.classList.remove('active');
            if (content.id === tabId) {
                content.classList.add('active');
            }
        });
        
        // Update URL without reload
        history.pushState(null, '', `?tab=${tabId}`);
    };
    
    // Set initial tab
    activateTab(initialTab);
    
    // Tab button click handlers
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tabId = button.getAttribute('data-tab');
            activateTab(tabId);
        });
    });

    // Form Confirmation - SPP
    const createSppForm = document.getElementById('createTagihanSPPForm');
    if (createSppForm) {
        createSppForm.addEventListener('submit', function(e) {
            const bulan = document.getElementById('bulan').value;
            const tahun = document.getElementById('tahun').value;
            const jumlah = document.getElementById('jumlah_spp').value;
            
            if (!confirm(`Konfirmasi Buat Tagihan SPP:\n\n• Bulan: ${bulan} ${tahun}\n• Jumlah: Rp ${parseInt(jumlah).toLocaleString('id-ID')}\n\nTagihan akan dibuat untuk SEMUA siswa yang belum memiliki tagihan ini.\n\nLanjutkan?`)) {
                e.preventDefault();
            } else {
                const btn = document.getElementById('createSppBtn');
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<span class="loader"></span> Memproses...';
                btn.disabled = true;
                
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                }, 3000);
            }
        });
    }

    // Form Confirmation - Administrasi
    const createAdminForm = document.getElementById('createTagihanAdminForm');
    if (createAdminForm) {
        createAdminForm.addEventListener('submit', function(e) {
            const namaTagihan = document.getElementById('nama_tagihan').value;
            const tahun = document.getElementById('tahun_ajaran').value;
            const jumlah = document.getElementById('jumlah_tagihan').value;
            const mode = document.querySelector('input[name="mode"]:checked').value;
            
            // Validasi jika mode pilih siswa tapi tidak ada yang dipilih
            if (mode === 'pilih') {
                const checkedStudents = document.querySelectorAll('input[name="nis[]"]:checked');
                if (checkedStudents.length === 0) {
                    alert('❌ Pilih setidaknya satu siswa untuk membuat tagihan.');
                    e.preventDefault();
                    return;
                }
            }
            
            if (!confirm(`Konfirmasi Buat Tagihan Administrasi:\n\n• Nama: ${namaTagihan}\n• Tahun: ${tahun}\n• Jumlah: Rp ${parseInt(jumlah).toLocaleString('id-ID')}\n• Mode: ${mode === 'massal' ? 'Semua Siswa' : 'Siswa Terpilih'}\n\nLanjutkan?`)) {
                e.preventDefault();
            } else {
                const btn = document.getElementById('createAdminBtn');
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<span class="loader"></span> Memproses...';
                btn.disabled = true;
                
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                }, 3000);
            }
        });
    }
    
    // Checkbox Selection Logic - Hanya jika ada di tab Notifikasi
    const masterCheckbox = document.getElementById('masterCheckbox');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const selectAllText = document.getElementById('selectAllText');
    const selectedCount = document.getElementById('selectedCount');
    const studentCheckboxes = document.querySelectorAll('.student-checkbox');
    
    if (studentCheckboxes.length > 0) {
        // Function to update counts
        function updateCounts() {
            const checkedCount = document.querySelectorAll('.student-checkbox:checked').length;
            const totalCount = studentCheckboxes.length;
            
            // Update selected count display
            if (selectedCount) {
                selectedCount.textContent = checkedCount;
            }
            
            // Update select all button text
            if (selectAllText) {
                if (checkedCount === totalCount && totalCount > 0) {
                    selectAllText.textContent = 'Batal Pilih Semua';
                } else {
                    selectAllText.textContent = `Pilih Semua Siswa (${checkedCount}/${totalCount})`;
                }
            }
            
            // Update master checkbox
            if (masterCheckbox) {
                masterCheckbox.checked = checkedCount === totalCount && totalCount > 0;
                masterCheckbox.indeterminate = checkedCount > 0 && checkedCount < totalCount;
            }
        }
        
        // Master checkbox toggle
        if (masterCheckbox) {
            masterCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                studentCheckboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
                updateCounts();
            });
        }
        
        // Select All button
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                let allChecked = true;
                
                // Check if all checkboxes are already checked
                studentCheckboxes.forEach(checkbox => {
                    if (!checkbox.checked) {
                        allChecked = false;
                    }
                });
                
                // Toggle all checkboxes
                const newState = !allChecked;
                studentCheckboxes.forEach(checkbox => {
                    checkbox.checked = newState;
                });
                
                updateCounts();
                
                // Add animation effect
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 200);
            });
        }
        
        // Update when individual checkboxes change
        studentCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateCounts);
        });
        
        // Initialize counts
        updateCounts();
    }
    
    // Send Notification Confirmation
    const notificationForm = document.getElementById('notificationForm');
    if (notificationForm) {
        notificationForm.addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
            
            if (checkedBoxes.length === 0) {
                alert('❌ Pilih setidaknya satu siswa untuk dikirim notifikasi.');
                e.preventDefault();
                return;
            }
            
            if (!confirm(`Kirim Notifikasi WhatsApp:\n\nNotifikasi tunggakan akan dikirim ke ${checkedBoxes.length} siswa.\n\nNotifikasi akan berisi detail semua tagihan tunggak masing-masing siswa.\n\nLanjutkan?`)) {
                e.preventDefault();
            } else {
                const btn = document.getElementById('sendNotificationBtn');
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<span class="loader"></span> Mengirim...';
                btn.disabled = true;
                
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                }, 5000);
            }
        });
    }
    
    // Edit Tagihan Functionality
    document.querySelectorAll('.edit-tagihan-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const tagihanId = this.getAttribute('data-id');
            const tipe = this.getAttribute('data-tipe');
            const currentJumlah = this.getAttribute('data-jumlah');
            
            // Format current amount for display
            const formattedCurrent = parseInt(currentJumlah).toLocaleString('id-ID');
            
            const newJumlah = prompt(`Edit Jumlah Tagihan ${tipe}\n\nID Tagihan: ${tagihanId}\nJumlah saat ini: Rp ${formattedCurrent}\n\nMasukkan jumlah baru:`, currentJumlah);

            if (newJumlah !== null && newJumlah.trim() !== '') {
                const jumlahNum = parseFloat(newJumlah);
                
                if (isNaN(jumlahNum) || jumlahNum <= 0) {
                    alert("❌ Input jumlah tidak valid. Pastikan Anda memasukkan angka yang lebih besar dari 0.");
                    return;
                }
                
                // Create and submit edit form
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `?tab=tabNotifikasi`;
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = (tipe === 'SPP') ? 'edit_tagihan_spp' : 'edit_tagihan_admin';
                form.appendChild(actionInput);

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = (tipe === 'SPP') ? 'spp_id' : 'detail_tagihan_id';
                idInput.value = tagihanId;
                form.appendChild(idInput);

                const jumlahInput = document.createElement('input');
                jumlahInput.type = 'hidden';
                jumlahInput.name = (tipe === 'SPP') ? 'jumlah_spp_edit' : 'jumlah_admin_edit';
                jumlahInput.value = jumlahNum;
                form.appendChild(jumlahInput);

                document.body.appendChild(form);
                form.submit();
            }
        });
    });
    
    // Mode selection for Administrasi form
    const modeRadios = document.querySelectorAll('input[name="mode"]');
    const pilihSiswaContainer = document.getElementById('pilihSiswa');
    
    if (modeRadios.length > 0) {
        modeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'pilih') {
                    pilihSiswaContainer.style.display = 'grid';
                    pilihSiswaContainer.style.animation = 'fadeInUp 0.3s ease';
                } else {
                    pilihSiswaContainer.style.display = 'none';
                }
            });
        });
    }
    
    // Add animation to buttons on click
    document.querySelectorAll('.btn, .action-link').forEach(button => {
        button.addEventListener('click', function() {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
});

// Delete Tagihan Function (global)
window.deleteTagihan = function(tipe, tagihanId) {
    const tipeText = tipe === 'SPP' ? 'SPP' : 'Administrasi';
    
    if (confirm(`Hapus Tagihan ${tipeText}?\n\nID Tagihan: ${tagihanId}\n\nTindakan ini akan menghapus tagihan secara permanen dan tidak dapat dibatalkan.\n\nYakin ingin melanjutkan?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `?tab=tabNotifikasi`;
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = (tipe === 'SPP') ? 'delete_tagihan_spp' : 'delete_tagihan_admin';
        form.appendChild(actionInput);

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = (tipe === 'SPP') ? 'spp_id' : 'detail_tagihan_id';
        idInput.value = tagihanId;
        form.appendChild(idInput);

        document.body.appendChild(form);
        form.submit();
    }
};
</script>
</body>
</html>