<?php
require_once __DIR__ . '/../config/koneksi.php';
$pdo = getKoneksi();

echo "=== RESEP OBAT ===\n";
$stmt = $pdo->query("SELECT * FROM resep_obat WHERE no_rawat IN ('2026/07/24/000003', '2026/07/24/000004') ORDER BY no_resep");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    print_r($r);
}

echo "=== RESEP DOKTER ===\n";
$stmt = $pdo->query("SELECT rd.* FROM resep_dokter rd JOIN resep_obat ro ON rd.no_resep=ro.no_resep WHERE ro.no_rawat IN ('2026/07/24/000003', '2026/07/24/000004')");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    print_r($r);
}

echo "=== DETAIL PEMBERIAN OBAT ===\n";
$stmt = $pdo->query("SELECT * FROM detail_pemberian_obat WHERE no_rawat IN ('2026/07/24/000003', '2026/07/24/000004')");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    print_r($r);
}
