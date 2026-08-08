<?php
require_once __DIR__ . '/../config/koneksi.php';

$pdo = getKoneksi();
echo "=== Data resep_obat 10 terbaru ===\n";
$stmt = $pdo->query("SELECT * FROM resep_obat ORDER BY no_resep DESC LIMIT 10");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    print_r($r);
}

echo "=== Data detail_pemberian_obat 10 terbaru ===\n";
$stmt2 = $pdo->query("SELECT * FROM detail_pemberian_obat ORDER BY tgl_perawatan DESC, jam DESC LIMIT 10");
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $r) {
    print_r($r);
}
