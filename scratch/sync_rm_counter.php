<?php
/**
 * Sekali pakai: sinkronkan set_no_rkm_medis dengan MAX no_rkm_medis di pasien
 */
require_once 'c:/xampp/htdocs/SIMKlinik/config/koneksi.php';
require_once 'c:/xampp/htdocs/SIMKlinik/lib/nomor.php';
$pdo = getKoneksi();

$stmtP = $pdo->query("SELECT IFNULL(MAX(CAST(no_rkm_medis AS UNSIGNED)), 0) AS m FROM pasien WHERE no_rkm_medis REGEXP '^[0-9]{6}$'");
$maxPasien = $stmtP->fetchColumn();

$stmtC = $pdo->query("SELECT no_rkm_medis FROM set_no_rkm_medis LIMIT 1");
$current = $stmtC->fetchColumn();

echo "set_no_rkm_medis saat ini : $current\n";
echo "MAX no_rkm_medis (pasien) : $maxPasien\n";

$highest = str_pad((string)max((int)$current, (int)$maxPasien), 6, '0', STR_PAD_LEFT);

if ((int)$highest > (int)$current) {
    syncSetNoRkmMedis($highest);
    echo "UPDATED set_no_rkm_medis -> $highest\n";
} else {
    echo "Tidak perlu update.\n";
}

$stmtVerify = $pdo->query("SELECT no_rkm_medis FROM set_no_rkm_medis LIMIT 1");
echo "set_no_rkm_medis sesudah : " . $stmtVerify->fetchColumn() . "\n";
