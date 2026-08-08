<?php
require_once __DIR__ . '/../config/koneksi.php';

try {
    $pdo = getKoneksi();
    
    // Create penilaian_treatment_wajah
    $sql1 = "CREATE TABLE IF NOT EXISTS `penilaian_treatment_wajah` (
      `no_rawat` varchar(15) NOT NULL,
      `tgl_perawatan` datetime NOT NULL,
      `bb` decimal(5,2) DEFAULT NULL,
      `tb` decimal(5,2) DEFAULT NULL,
      `email` varchar(50) DEFAULT NULL,
      `jenis_kulit` enum('Normal','Kering','Berminyak','Kombinasi','Sensitif') NOT NULL DEFAULT 'Normal',
      `jerawat` enum('Ada','Tidak Ada') NOT NULL DEFAULT 'Tidak Ada',
      `jerawat_area` varchar(100) DEFAULT NULL,
      `jerawat_derajat` enum('Ringan','Sedang','Berat') DEFAULT NULL,
      `cacat_bekas_jerawat` enum('Ada','Tidak Ada') NOT NULL DEFAULT 'Tidak Ada',
      `cacat_bekas_jerawat_area` varchar(100) DEFAULT NULL,
      `cacat_bekas_jerawat_derajat` enum('Ringan','Sedang','Berat') DEFAULT NULL,
      `fleks_hitam_cokelat` enum('Ada','Tidak Ada') NOT NULL DEFAULT 'Tidak Ada',
      `fleks_area` varchar(100) DEFAULT NULL,
      `fleks_derajat` enum('Ringan','Sedang','Berat') DEFAULT NULL,
      `keriput_wajah` enum('Ada','Tidak Ada') NOT NULL DEFAULT 'Tidak Ada',
      `keriput_area` varchar(100) DEFAULT NULL,
      `area_sensitif` enum('Ada','Tidak Ada') NOT NULL DEFAULT 'Tidak Ada',
      `area_sensitif_ket` varchar(100) DEFAULT NULL,
      `kondisi_hamil` enum('Ya','Tidak') NOT NULL DEFAULT 'Tidak',
      `kondisi_menyusui` enum('Ya','Tidak') NOT NULL DEFAULT 'Tidak',
      `menggunakan_kontrasepsi` enum('Ya','Tidak') NOT NULL DEFAULT 'Tidak',
      `jenis_kontrasepsi` varchar(50) DEFAULT NULL,
      `diet_khusus` enum('Ya','Tidak') NOT NULL DEFAULT 'Tidak',
      `jenis_diet` varchar(100) DEFAULT NULL,
      `alergi` enum('Ya','Tidak') NOT NULL DEFAULT 'Tidak',
      `alergi_ket` varchar(100) DEFAULT NULL,
      `produk_perawatan_terakhir` varchar(150) DEFAULT NULL,
      `keluhan` text,
      `riwayat_penyakit_dahulu` text,
      `riwayat_penyakit_keluarga` text,
      `fokus_pijatan_area` text,
      `tingkat_pijatan` enum('Tekanan Ringan','Tekanan Sedang','Tekanan Kuat') DEFAULT NULL,
      `persetujuan_pasien` enum('Ya','Tidak') NOT NULL DEFAULT 'Tidak',
      `nama_ttd_pasien` varchar(100) DEFAULT NULL,
      `nip` varchar(20) DEFAULT NULL,
      PRIMARY KEY (`no_rawat`),
      CONSTRAINT `fk_ptw_no_rawat` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
    
    $pdo->exec($sql1);
    echo "Tabel penilaian_treatment_wajah berhasil dipastikan.\n";
    
    // Create penilaian_treatment_wajah_titik
    $sql2 = "CREATE TABLE IF NOT EXISTS `penilaian_treatment_wajah_titik` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `no_rawat` varchar(15) NOT NULL,
      `pos_x` decimal(6,2) NOT NULL,
      `pos_y` decimal(6,2) NOT NULL,
      `keterangan` varchar(100) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_ptwt_no_rawat` (`no_rawat`),
      CONSTRAINT `fk_ptwt_no_rawat` FOREIGN KEY (`no_rawat`) REFERENCES `penilaian_treatment_wajah` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
    
    $pdo->exec($sql2);
    echo "Tabel penilaian_treatment_wajah_titik berhasil dipastikan.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
