<?php
require_once __DIR__ . '/../config/koneksi.php';
$pdo = getKoneksi();

try {
    // Foreign key constraint needs to be temporarily dropped or directly altered
    $pdo->exec("ALTER TABLE penilaian_treatment_wajah_titik DROP FOREIGN KEY fk_ptwt_no_rawat");
    $pdo->exec("ALTER TABLE penilaian_treatment_wajah DROP FOREIGN KEY fk_ptw_no_rawat");

    $pdo->exec("ALTER TABLE penilaian_treatment_wajah MODIFY COLUMN no_rawat VARCHAR(17) NOT NULL");
    $pdo->exec("ALTER TABLE penilaian_treatment_wajah_titik MODIFY COLUMN no_rawat VARCHAR(17) NOT NULL");

    $pdo->exec("ALTER TABLE penilaian_treatment_wajah ADD CONSTRAINT fk_ptw_no_rawat FOREIGN KEY (no_rawat) REFERENCES reg_periksa(no_rawat) ON DELETE CASCADE ON UPDATE CASCADE");
    $pdo->exec("ALTER TABLE penilaian_treatment_wajah_titik ADD CONSTRAINT fk_ptwt_no_rawat FOREIGN KEY (no_rawat) REFERENCES penilaian_treatment_wajah(no_rawat) ON DELETE CASCADE ON UPDATE CASCADE");

    echo "SUCCESS: Column no_rawat in penilaian_treatment_wajah and penilaian_treatment_wajah_titik updated to VARCHAR(17)!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
