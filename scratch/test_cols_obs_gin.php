<?php
require_once __DIR__ . '/../config/koneksi.php';
$pdo = getKoneksi();

echo "Testing Obstetri insert query columns check:\n";
$stmt = $pdo->prepare("SELECT no_rawat, tgl_perawatan, jam_rawat, tinggi_uteri, janin, letak, panggul, denyut, kontraksi, kualitas_mnt, kualitas_dtk, fluksus, albus, vulva, portio, dalam, tebal, arah, pembukaan, penurunan, denominator, ketuban, feto FROM pemeriksaan_obstetri_ralan LIMIT 1");
$stmt->execute();
echo "Obstetri columns OK!\n";

echo "Testing Ginekologi insert query columns check:\n";
$stmt = $pdo->prepare("SELECT no_rawat, tgl_perawatan, jam_rawat, inspeksi, inspeksi_vulva, inspekulo_gine, fluxus_gine, fluor_gine, vulva_inspekulo, portio_inspekulo, sondage, portio_dalam, bentuk, cavum_uteri, mobilitas, ukuran, nyeri_tekan, adnexa_kanan, adnexa_kiri, cavum_douglas FROM pemeriksaan_ginekologi_ralan LIMIT 1");
$stmt->execute();
echo "Ginekologi columns OK!\n";
