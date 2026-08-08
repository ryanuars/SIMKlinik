<?php
require_once __DIR__ . '/../config/koneksi.php';
$pdo = getKoneksi();

echo "Testing Dashboard stats query:\n";
$stmtStat = $pdo->prepare(
    "SELECT 
        COUNT(*) as total_kunjungan,
        SUM(CASE WHEN stts IN ('Belum', 'Belum Diperiksa') THEN 1 ELSE 0 END) as pasien_menunggu,
        SUM(CASE WHEN stts IN ('Sudah', 'Sedang Diperiksa', 'Pemeriksaan') THEN 1 ELSE 0 END) as sedang_diperiksa,
        SUM(CASE WHEN stts NOT IN ('Belum', 'Belum Diperiksa', 'Sudah', 'Sedang Diperiksa', 'Pemeriksaan') THEN 1 ELSE 0 END) as pasien_selesai
     FROM reg_periksa
     WHERE tgl_registrasi = CURDATE()"
);
$stmtStat->execute();
$stat = $stmtStat->fetch(PDO::FETCH_ASSOC);
print_r($stat);

echo "Testing Dashboard antrean table query:\n";
$stmtKunjungan = $pdo->prepare(
    "SELECT r.no_rawat, r.no_reg, r.no_rkm_medis, p.nm_pasien, pol.nm_poli,
            d.nm_dokter, r.stts, r.status_bayar
     FROM reg_periksa r
     INNER JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
     INNER JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
     LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
     WHERE r.tgl_registrasi = CURDATE()
     ORDER BY r.no_reg ASC, r.no_rawat DESC
     LIMIT 5"
);
$stmtKunjungan->execute();
$rows = $stmtKunjungan->fetchAll(PDO::FETCH_ASSOC);
echo "Fetched " . count($rows) . " antrean rows.\n";
