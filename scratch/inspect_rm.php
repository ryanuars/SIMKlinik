<?php
require_once 'c:/xampp/htdocs/SIMKlinik/config/koneksi.php';
$pdo = getKoneksi();

echo "=== set_no_rkm_medis TABLE ===" . PHP_EOL;
try {
    $stmt = $pdo->query('SELECT * FROM set_no_rkm_medis LIMIT 5');
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) { echo 'ERROR: ' . $e->getMessage() . PHP_EOL; }

echo PHP_EOL . "=== set_urut_no_rkm_medis TABLE ===" . PHP_EOL;
try {
    $stmt2 = $pdo->query('SELECT * FROM set_urut_no_rkm_medis LIMIT 5');
    print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) { echo 'ERROR: ' . $e->getMessage() . PHP_EOL; }

echo PHP_EOL . "=== LAST 5 no_rkm_medis di pasien ===" . PHP_EOL;
$stmt3 = $pdo->query('SELECT no_rkm_medis, nm_pasien, tgl_daftar FROM pasien ORDER BY CAST(no_rkm_medis AS UNSIGNED) DESC LIMIT 5');
print_r($stmt3->fetchAll(PDO::FETCH_ASSOC));
