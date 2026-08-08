<?php
require_once __DIR__ . '/../config/koneksi.php';
$pdo = getKoneksi();
echo "=== Columns in resep_dokter ===\n";
$stmt = $pdo->query("DESCRIBE resep_dokter");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo $c['Field'] . " (" . $c['Type'] . ")\n";
}
