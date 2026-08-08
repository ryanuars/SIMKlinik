-- =====================================================================
-- Skema tabel: Penilaian Awal & Rencana Treatment Face Massage
-- Mengikuti pola penamaan & relasi SIMRS Khanza
--   - reg_periksa.no_rawat  : nomor kunjungan (sudah ada di sistem)
--   - pasien.no_rkm_medis   : nomor rekam medis (sudah ada di sistem)
-- Sesuaikan nama tabel/kolom bila skema di instalasi Anda berbeda.
-- =====================================================================

-- 1. TABEL UTAMA -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `penilaian_treatment_wajah` (
  `no_rawat` varchar(17) NOT NULL,
  `tgl_perawatan` datetime NOT NULL,

  -- data yang diambil ulang saat kunjungan (bukan data master pasien)
  `bb` decimal(5,2) DEFAULT NULL,
  `tb` decimal(5,2) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,

  -- ANALISIS KONDISI PASIEN DAN KULIT WAJAH
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

  -- RENCANA TREATMENT FACE MASSAGE
  `fokus_pijatan_area` text,
  `tingkat_pijatan` enum('Tekanan Ringan','Tekanan Sedang','Tekanan Kuat') DEFAULT NULL,

  -- persetujuan pasien
  `persetujuan_pasien` enum('Ya','Tidak') NOT NULL DEFAULT 'Tidak',
  `nama_ttd_pasien` varchar(100) DEFAULT NULL,
  `ttd_pasien` MEDIUMTEXT DEFAULT NULL COMMENT 'Tanda tangan digital pasien (data:image/png;base64,...)',

  -- petugas pengisi
  `nip` varchar(20) DEFAULT NULL,

  PRIMARY KEY (`no_rawat`),
  CONSTRAINT `fk_ptw_no_rawat` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- 2. TABEL DETAIL TITIK PIJATAN (klik langsung pada gambar wajah) -----
-- Satu baris = satu titik yang ditandai user di atas diagram wajah.
-- pos_x, pos_y disimpan dalam PERSEN (0-100) relatif terhadap lebar/tinggi
-- gambar, supaya tetap akurat walau gambar ditampilkan di ukuran berbeda.
CREATE TABLE IF NOT EXISTS `penilaian_treatment_wajah_titik` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_rawat` varchar(17) NOT NULL,
  `pos_x` decimal(6,2) NOT NULL COMMENT 'posisi X dalam persen (0-100)',
  `pos_y` decimal(6,2) NOT NULL COMMENT 'posisi Y dalam persen (0-100)',
  `keterangan` varchar(100) DEFAULT NULL COMMENT 'label opsional titik, mis. dahi/pipi kiri',
  PRIMARY KEY (`id`),
  KEY `idx_ptwt_no_rawat` (`no_rawat`),
  CONSTRAINT `fk_ptwt_no_rawat` FOREIGN KEY (`no_rawat`) REFERENCES `penilaian_treatment_wajah` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
