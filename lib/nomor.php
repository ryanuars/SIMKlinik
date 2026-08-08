<?php
/**
 * lib/nomor.php
 * -----------------------------------------------------------------
 * Generator nomor yang WAJIB identik logikanya dengan Java Khanza,
 * supaya tidak ada collision saat PHP & Java berjalan paralel.
 * Lihat docs/KEPUTUSAN-TEKNIS.md #1 untuk sumber verifikasi.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';

/**
 * Generate no_rawat baru untuk tanggal registrasi tertentu.
 *
 * TERVERIFIKASI dari source asli:
 *   KhanzaHMSAnjunganFingerPrint/src/khanzahmsanjungan/DlgRegistrasi.java:976
 *   Query asli:
 *     SELECT IFNULL(MAX(CONVERT(RIGHT(no_rawat,6),SIGNED)),0)
 *     FROM reg_periksa WHERE tgl_registrasi = '<tgl>'
 *   Lalu: hasil + 1, padding ke 6 digit leading zero,
 *   prefix = tanggal format YYYY/MM/DD + '/'
 *
 * @param string $tglRegistrasi Format 'YYYY-MM-DD'
 * @return string contoh: '2026/06/29/000124'
 */
function generateNoRawat(string $tglRegistrasi): string
{
    $pdo = getKoneksi();

    $stmt = $pdo->prepare(
        "SELECT IFNULL(MAX(CONVERT(RIGHT(no_rawat, 6), SIGNED)), 0) AS maxnomor
         FROM reg_periksa
         WHERE tgl_registrasi = :tgl"
    );
    $stmt->execute(['tgl' => $tglRegistrasi]);
    $maxNomor = (int) $stmt->fetch()['maxnomor'];

    $nomorBaru = $maxNomor + 1;
    $nomorPadded = str_pad((string) $nomorBaru, 6, '0', STR_PAD_LEFT);

    $prefixTanggal = str_replace('-', '/', $tglRegistrasi); // YYYY/MM/DD

    return $prefixTanggal . '/' . $nomorPadded;
}

/**
 * Generate no_reg baru untuk kombinasi dokter + tanggal registrasi.
 *
 * TERVERIFIKASI dari:
 *   1. Source Java: KhanzaHMSAnjunganFingerPrint/.../DlgRegistrasi.java baris 960-975
 *      — saat BASENOREG != 'booking', mode default:
 *        `SELECT IFNULL(MAX(CONVERT(no_reg,SIGNED)),0) FROM reg_periksa
 *         WHERE kd_dokter='...' AND tgl_registrasi='...'`
 *        lalu +1, padding ke 3 digit (autoNomer3 artinya 3 digit).
 *   2. Data riil RSU Al-Arif (screenshot 2026-06-29):
 *        - OBG03 jam 05:00 -> no_reg = '001'
 *        - PD01  jam 01:54 -> no_reg = '001'
 *        Setiap dokter mulai dari '001' per hari = reset harian PER DOKTER.
 *        Mode URUTNOREG yang berlaku di RSU Al-Arif adalah 'dokter'.
 *
 * @param string $kdDokter       kode dokter (dari tabel dokter)
 * @param string $tglRegistrasi  format 'YYYY-MM-DD'
 * @return string contoh: '001', '002', '003', dst
 */
function generateNoReg(string $kdDokter, string $tglRegistrasi): string
{
    $pdo = getKoneksi();

    $stmt = $pdo->prepare(
        "SELECT IFNULL(MAX(CONVERT(no_reg, SIGNED)), 0) AS maxnomor
         FROM reg_periksa
         WHERE kd_dokter = ?
           AND tgl_registrasi = ?"
    );
    $stmt->execute([$kdDokter, $tglRegistrasi]);
    $maxNomor = (int) $stmt->fetch()['maxnomor'];

    return str_pad((string) ($maxNomor + 1), 3, '0', STR_PAD_LEFT);
}

/**
 * Generate no_rkm_medis baru.
 *
 * STATUS: ✅ FINAL — dikonfirmasi langsung oleh RSU Al-Arif (2026-06-29).
 *
 * Konfirmasi dari pengguna:
 * - Pola resmi: mode "Straight" bawaan Khanza, tahun=No, bulan=No
 *   (cocok dengan default `set_urut_no_rkm_medis` di repo resmi:
 *   urutan='Straight', tahun='No', bulan='No', posisi_tahun_bulan='Depan').
 * - Format: 6 digit urut polos, naik 1 per pasien baru, TANPA reset,
 *   tanpa prefix tanggal. Contoh: 000001 -> 000002 -> 000003 dst.
 * - Nomor RM MAKSIMAL 6 digit.
 * - Data dengan panjang 7 digit (contoh: 0435945) dan 5 digit
 *   (contoh: 05232) adalah DATA KOTOR hasil upload/migrasi manual —
 *   BUKAN pola yang harus diikuti, dan harus DIABAIKAN saat mencari
 *   nilai MAX agar nomor baru tidak melompat jauh atau salah urutan.
 * - Data dengan panjang 1 digit (contoh: '1') sengaja dibuat (akun
 *   uji/dummy) — juga diabaikan dari perhitungan MAX.
 *
 * Implementasi: hanya mempertimbangkan baris yang PERSIS 6 digit
 * dan murni numerik saat mencari MAX, supaya data kotor di atas
 * tidak ikut memengaruhi nomor RM baru.
 *
 * @return string contoh: '083743'
 */
function generateNoRkmMedis(): string
{
    $pdo = getKoneksi();

    // Ambil nilai tertinggi dari KEDUANYA:
    //   1. set_no_rkm_medis — counter resmi Java SIMRS (Khanza).
    //      Java selalu UPDATE tabel ini saat mendaftarkan pasien baru,
    //      sehingga ini adalah "last known RM" versi Java.
    //   2. MAX(pasien.no_rkm_medis) — data riil yang sudah tersimpan
    //      termasuk yang didaftarkan PHP sendiri.
    //
    // Mengambil nilai TERTINGGI dari keduanya menjamin:
    //   - PHP tidak menghasilkan nomor yang sudah dipakai Java.
    //   - Java tidak menghasilkan nomor yang sudah dipakai PHP
    //     (asalkan PHP selalu UPDATE set_no_rkm_medis setelah INSERT).

    $maxFromCounter = 0;
    try {
        $stmtC = $pdo->query("SELECT IFNULL(MAX(CAST(no_rkm_medis AS UNSIGNED)), 0) AS m FROM set_no_rkm_medis");
        $maxFromCounter = (int)($stmtC->fetch()['m'] ?? 0);
    } catch (Throwable $t) {
        // Tabel tidak ada — abaikan, gunakan MAX dari pasien saja
    }

    $stmtP = $pdo->query(
        "SELECT IFNULL(MAX(CAST(no_rkm_medis AS UNSIGNED)), 0) AS maxnomor
         FROM pasien
         WHERE no_rkm_medis REGEXP '^[0-9]{6}$'"
    );
    $maxFromPasien = (int)($stmtP->fetch()['maxnomor'] ?? 0);

    $maxNomor  = max($maxFromCounter, $maxFromPasien);
    $nomorBaru = $maxNomor + 1;

    if ($nomorBaru > 999999) {
        throw new RuntimeException(
            'Nomor rekam medis 6 digit sudah mencapai batas maksimum (999999). ' .
            'Hubungi admin sistem untuk menentukan kebijakan lanjutan.'
        );
    }

    return str_pad((string) $nomorBaru, 6, '0', STR_PAD_LEFT);
}

/**
 * Setelah pasien baru berhasil di-INSERT, panggil fungsi ini agar
 * tabel set_no_rkm_medis ikut diperbarui.  Dengan demikian Java SIMRS
 * (Khanza) yang membaca set_no_rkm_medis akan mendapat nilai yang sudah
 * memperhitungkan pendaftaran dari PHP — sehingga tidak terjadi duplikat.
 *
 * @param string $noRkmMedisBaru  Nomor RM yang baru saja dipakai (6 digit)
 */
function syncSetNoRkmMedis(string $noRkmMedisBaru): void
{
    $pdo = getKoneksi();
    try {
        // set_no_rkm_medis hanya punya 1 baris. Java SIMRS melakukan
        // UPDATE langsung pada baris itu. Kita lakukan hal yang sama.
        $count = (int)$pdo->query("SELECT COUNT(*) FROM set_no_rkm_medis")->fetchColumn();
        if ($count === 0) {
            $pdo->prepare("INSERT INTO set_no_rkm_medis (no_rkm_medis) VALUES (?)")->execute([$noRkmMedisBaru]);
        } else {
            // Hanya update jika nomor baru lebih besar dari yang tersimpan
            $pdo->prepare(
                "UPDATE set_no_rkm_medis SET no_rkm_medis = ?
                 WHERE CAST(no_rkm_medis AS UNSIGNED) < CAST(? AS UNSIGNED)"
            )->execute([$noRkmMedisBaru, $noRkmMedisBaru]);
        }
    } catch (Throwable $t) {
        // Tabel tidak ada, abaikan
        error_log('[syncSetNoRkmMedis] ' . $t->getMessage());
    }
}
