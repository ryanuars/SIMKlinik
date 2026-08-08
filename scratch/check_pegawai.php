<?php
require_once __DIR__ . '/../config/koneksi.php';
$pdo = getKoneksi();

echo "=== Sample pegawai (nik, nama) ===\n";
$stmt = $pdo->query("SELECT nik, nama FROM pegawai LIMIT 10");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    print_r($r);
}

echo "=== Sample petugas (nip, nama) ===\n";
$stmt = $pdo->query("SELECT nip, nama FROM petugas LIMIT 10");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    print_r($r);
}

echo "=== Sample dokter (kd_dokter, nm_dokter) ===\n";
$stmt = $pdo->query("SELECT kd_dokter, nm_dokter FROM dokter LIMIT 10");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    print_r($r);
}

echo "=== Sample user (id_user) ===\n";
$stmt = $pdo->query("SELECT AES_DECRYPT(id_user,'nur') as id_user FROM user LIMIT 10");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    print_r($r);
}
