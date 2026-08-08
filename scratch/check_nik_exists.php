<?php
require_once __DIR__ . '/../config/koneksi.php';
$pdo = getKoneksi();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM pegawai WHERE nik = ?");
foreach (['PD01', 'admin', '-', 'OBG01', '56001977072'] as $cand) {
    $stmt->execute([$cand]);
    echo "$cand in pegawai.nik: " . $stmt->fetchColumn() . "\n";
}
