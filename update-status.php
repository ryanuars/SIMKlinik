<?php
/**
 * update-status.php
 * -----------------------------------------------------------------
 * AJAX endpoint untuk mengupdate status kunjungan pasien (reg_periksa.stts)
 * menjadi 'Sudah' (atau status terpilih) saat tombol aksi/tindakan diklik
 * dari dashboard.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/lib/auth.php';

wajibLogin();

header('Content-Type: application/json');

$noRawat = trim($_POST['no_rawat'] ?? $_GET['no_rawat'] ?? '');
$status  = trim($_POST['stts'] ?? $_GET['stts'] ?? 'Sudah');

// Validasi enum stts yang valid di tabel reg_periksa Khanza
$allowedStts = ['Belum', 'Sudah', 'Batal', 'Berkas Diterima', 'Dirujuk', 'Meninggal', 'Dirawat', 'Pulang Paksa'];
if (!in_array($status, $allowedStts, true)) {
    $status = 'Sudah';
}

if ($noRawat === '') {
    echo json_encode(['sukses' => false, 'pesan' => 'No. Rawat tidak boleh kosong.']);
    exit;
}

try {
    $pdo = getKoneksi();
    
    // Simpan no_rawat ke session sebagai pasien aktif terakhir
    $_SESSION['last_no_rawat'] = $noRawat;

    // Update status kunjungan di tabel reg_periksa
    $stmt = $pdo->prepare("UPDATE reg_periksa SET stts = ? WHERE no_rawat = ?");
    $stmt->execute([$status, $noRawat]);

    echo json_encode(['sukses' => true, 'no_rawat' => $noRawat, 'stts' => $status]);
} catch (Throwable $e) {
    echo json_encode(['sukses' => false, 'pesan' => $e->getMessage()]);
}
