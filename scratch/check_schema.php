<?php
require_once __DIR__ . '/../config/koneksi.php';

$pdo = getKoneksi();
foreach (['resep_obat', 'resep_dokter', 'detail_pemberian_obat'] as $tbl) {
    echo "=== $tbl ===\n";
    $stmt = $pdo->query("DESCRIBE `$tbl`");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo sprintf("%-20s | %-15s | Null: %-3s | Key: %-3s | Default: %s\n",
            $col['Field'], $col['Type'], $col['Null'], $col['Key'], var_export($col['Default'], true)
        );
    }
    echo "\n";
}
