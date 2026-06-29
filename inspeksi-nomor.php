<?php
/**
 * inspeksi-nomor.php
 * -----------------------------------------------------------------
 * SCRIPT SEKALI PAKAI — untuk Fase 1, langkah verifikasi blocker #1
 * (format no_rawat & no_rkm_medis). HAPUS setelah selesai dipakai.
 *
 * Cara pakai: buka di browser, lihat hasilnya, lalu salin pola yang
 * ditemukan ke docs/KEPUTUSAN-TEKNIS.md.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/app.php';

header('Content-Type: text/html; charset=utf-8');
$pdo = getKoneksi();

echo "<h2>1. Contoh 10 no_rawat TERBARU</h2><pre>";
$stmt = $pdo->query("SELECT no_rawat, tgl_registrasi FROM reg_periksa ORDER BY tgl_registrasi DESC, no_rawat DESC LIMIT 10");
foreach ($stmt->fetchAll() as $r) {
    echo $r['no_rawat'] . "   (tgl_registrasi: " . $r['tgl_registrasi'] . ")\n";
}
echo "</pre>";

echo "<h2>2. Cek apakah no_rawat reset harian (hitung baris per tanggal terbaru)</h2><pre>";
$stmt = $pdo->query("
    SELECT tgl_registrasi, COUNT(*) AS total, MIN(no_rawat) AS min_no, MAX(no_rawat) AS max_no
    FROM reg_periksa
    GROUP BY tgl_registrasi
    ORDER BY tgl_registrasi DESC
    LIMIT 5
");
foreach ($stmt->fetchAll() as $r) {
    echo "{$r['tgl_registrasi']} | total: {$r['total']} | min: {$r['min_no']} | max: {$r['max_no']}\n";
}
echo "</pre>";

echo "<h2>3. Contoh 10 no_rkm_medis TERBARU (dari tabel pasien, urut tgl_daftar)</h2><pre>";
$stmt = $pdo->query("SELECT no_rkm_medis, tgl_daftar FROM pasien ORDER BY tgl_daftar DESC, no_rkm_medis DESC LIMIT 10");
foreach ($stmt->fetchAll() as $r) {
    echo $r['no_rkm_medis'] . "   (tgl_daftar: " . $r['tgl_daftar'] . ")\n";
}
echo "</pre>";

echo "<h2>4. Cek tabel set_no_rkm_medis (generator sequence RM, jika dipakai)</h2><pre>";
try {
    $stmt = $pdo->query("SELECT * FROM set_no_rkm_medis ORDER BY no_rkm_medis DESC LIMIT 5");
    foreach ($stmt->fetchAll() as $r) {
        print_r($r);
    }
} catch (Throwable $e) {
    echo "Tabel tidak ditemukan / kosong: " . $e->getMessage();
}
echo "</pre>";

echo "<h2>5. Cek panjang & format no_rkm_medis yang paling umum (deteksi pola digit)</h2><pre>";
$stmt = $pdo->query("SELECT no_rkm_medis, LENGTH(no_rkm_medis) AS panjang FROM pasien GROUP BY panjang ORDER BY COUNT(*) DESC LIMIT 5");
foreach ($stmt->fetchAll() as $r) {
    echo "Panjang {$r['panjang']} -> contoh: {$r['no_rkm_medis']}\n";
}
echo "</pre>";

echo "<h2>6. Cek apakah ada konfigurasi pola RM di tabel `setting` (jika ada)</h2><pre>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE '%setting%'");
    foreach ($stmt->fetchAll() as $r) {
        print_r($r);
    }
} catch (Throwable $e) {
    echo $e->getMessage();
}
echo "</pre>";
