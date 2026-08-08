<?php
require_once __DIR__ . '/../config/koneksi.php';
$pdo = getKoneksi();

$st = $pdo->prepare(
    "SELECT dpo.no_rawat, dpo.tgl_perawatan, dpo.jam,
            dpo.kode_brng, db.nama_brng AS nama_obat,
            dpo.jml AS jumlah, db.kode_sat,
            COALESCE(rd.aturan_pakai, '') AS aturan_pakai
     FROM detail_pemberian_obat dpo
     INNER JOIN databarang db ON dpo.kode_brng = db.kode_brng
     LEFT JOIN resep_obat ro ON dpo.no_rawat = ro.no_rawat AND dpo.tgl_perawatan = ro.tgl_perawatan AND dpo.jam = ro.jam
     LEFT JOIN resep_dokter rd ON ro.no_resep = rd.no_resep AND dpo.kode_brng = rd.kode_brng
     LIMIT 5"
);
$st->execute();
$results = $st->fetchAll(PDO::FETCH_ASSOC);
echo "Query successful! Fetched " . count($results) . " rows.\n";
print_r($results);
