<?php
require_once __DIR__ . '/../config/koneksi.php';
$pdo = getKoneksi();

// Check schema of usg tables
foreach (['hasil_pemeriksaan_usg', 'hasil_pemeriksaan_usg_gynecologi',
          'penilaian_medis_ralan_kandungan', 'penilaian_awal_keperawatan_kebidanan',
          'pemeriksaan_obstetri_ralan', 'pemeriksaan_ginekologi_ralan'] as $tbl) {
    echo "=== $tbl ===\n";
    $stmt = $pdo->query("DESCRIBE `$tbl`");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo "  " . $col['Field'] . " | " . $col['Type'] . "\n";
    }
    echo "\n";
}
