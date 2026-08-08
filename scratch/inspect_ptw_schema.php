<?php
require_once __DIR__ . '/../config/koneksi.php';
$pdo = getKoneksi();

echo "--- COLUMNS in penilaian_treatment_wajah ---\n";
$st = $pdo->query("SHOW COLUMNS FROM penilaian_treatment_wajah");
while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    echo "{$r['Field']} | {$r['Type']} | Null: {$r['Null']} | Key: {$r['Key']} | Default: {$r['Default']}\n";
}

echo "\n--- FOREIGN KEYS in penilaian_treatment_wajah ---\n";
$st2 = $pdo->query("SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'penilaian_treatment_wajah' AND REFERENCED_TABLE_NAME IS NOT NULL");
while ($r2 = $st2->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($r2) . "\n";
}

echo "\n--- FOREIGN KEYS in penilaian_treatment_wajah_titik ---\n";
$st3 = $pdo->query("SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'penilaian_treatment_wajah_titik' AND REFERENCED_TABLE_NAME IS NOT NULL");
while ($r3 = $st3->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($r3) . "\n";
}
