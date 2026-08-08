<?php
require_once __DIR__ . '/../config/koneksi.php';
$pdo = getKoneksi();

$st = $pdo->prepare("SELECT no_rawat, stts FROM reg_periksa ORDER BY tgl_registrasi DESC LIMIT 1");
$st->execute();
$row = $st->fetch(PDO::FETCH_ASSOC);
echo "Latest reg_periksa record: " . json_encode($row) . "\n";
