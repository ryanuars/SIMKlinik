<?php
require_once 'c:/xampp/htdocs/SIMKlinik/config/koneksi.php';
require_once 'c:/xampp/htdocs/SIMKlinik/lib/nomor.php';

echo "=== VERIFIKASI SINKRONISASI RM ===" . PHP_EOL;

$pdo = getKoneksi();

$stmtC = $pdo->query("SELECT no_rkm_medis FROM set_no_rkm_medis LIMIT 1");
echo "set_no_rkm_medis (Java counter) : " . $stmtC->fetchColumn() . PHP_EOL;

$stmtP = $pdo->query("SELECT IFNULL(MAX(CAST(no_rkm_medis AS UNSIGNED)),0) AS m FROM pasien WHERE no_rkm_medis REGEXP '^[0-9]{6}$'");
echo "MAX pasien.no_rkm_medis (6-digit): " . $stmtP->fetchColumn() . PHP_EOL;

echo "Nomor RM berikutnya (PHP/Java)  : " . generateNoRkmMedis() . PHP_EOL;
