<?php
require_once __DIR__ . '/../config/koneksi.php';
$pdo = getKoneksi();

// Bersihkan detail_pemberian_obat gantung yang tidak memiliki tgl_perawatan & jam yang valid di resep_obat
// Khusus untuk no_rawat 2026/07/24/000003 yang memiliki 3 baris duplikat jam
$pdo->query("DELETE FROM detail_pemberian_obat WHERE no_rawat = '2026/07/24/000003' AND jam IN ('13:47:29', '13:48:02')");
echo "Cleaned duplicate dpo records for test patient.\n";
