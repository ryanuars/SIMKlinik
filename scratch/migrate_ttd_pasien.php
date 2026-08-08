<?php
require_once 'c:/xampp/htdocs/SIMKlinik/config/koneksi.php';
$pdo = getKoneksi();

try {
    // Cek apakah kolom ttd_pasien sudah ada
    $check = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='penilaian_treatment_wajah' AND COLUMN_NAME='ttd_pasien'");
    if (!$check->fetchColumn()) {
        $pdo->exec("ALTER TABLE penilaian_treatment_wajah ADD COLUMN `ttd_pasien` MEDIUMTEXT DEFAULT NULL COMMENT 'Tanda tangan digital pasien (data:image/png;base64,...)' AFTER `nama_ttd_pasien`");
        echo "OK: Kolom ttd_pasien berhasil ditambahkan.\n";
    } else {
        echo "INFO: Kolom ttd_pasien sudah ada, tidak perlu migrasi.\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
