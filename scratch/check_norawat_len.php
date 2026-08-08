<?php
require_once __DIR__ . '/../config/koneksi.php';
$pdo = getKoneksi();

$st = $pdo->query("SHOW COLUMNS FROM reg_periksa LIKE 'no_rawat'");
echo "reg_periksa.no_rawat: " . json_encode($st->fetch(PDO::FETCH_ASSOC)) . "\n";

$st2 = $pdo->query("SHOW COLUMNS FROM penilaian_treatment_wajah LIKE 'no_rawat'");
echo "penilaian_treatment_wajah.no_rawat: " . json_encode($st2->fetch(PDO::FETCH_ASSOC)) . "\n";

$st3 = $pdo->query("SHOW COLUMNS FROM penilaian_treatment_wajah_titik LIKE 'no_rawat'");
echo "penilaian_treatment_wajah_titik.no_rawat: " . json_encode($st3->fetch(PDO::FETCH_ASSOC)) . "\n";
