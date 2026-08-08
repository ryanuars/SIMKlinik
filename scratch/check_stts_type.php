<?php
require_once __DIR__ . '/../config/koneksi.php';
$pdo = getKoneksi();
$stmt = $pdo->query("DESCRIBE reg_periksa");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    if ($col['Field'] === 'stts') {
        echo "stts column definition: " . $col['Type'] . "\n";
    }
}
