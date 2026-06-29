<?php
/**
 * pasien/daftar-baru.php
 * -----------------------------------------------------------------
 * Form pendaftaran pasien baru -> INSERT ke tabel `pasien`.
 *
 * Kolom WAJIB (NOT NULL) di skema `pasien` yang TIDAK ditampilkan di
 * form ini (terlalu berat untuk alur klinik kebidanan/kecantikan)
 * diisi dengan nilai default AMAN yang sudah diverifikasi TIDAK
 * melanggar FK constraint (lihat docs/KEPUTUSAN-TEKNIS.md):
 *   - kd_kel, kd_kec, kd_kab, kd_prop -> 1 (berlabel '-' di tabel
 *     referensi kelurahan/kecamatan/kabupaten/propinsi)
 *   - suku_bangsa, bahasa_pasien, cacat_fisik -> 1 (berlabel '-')
 *   - perusahaan_pasien -> '-' (berlabel '-')
 *   - kd_pj -> diisi dari pilihan user (dropdown penjab, default 'A09' UMUM)
 * Semua ini AMAN untuk diedit kembali nanti dari aplikasi Java Khanza
 * jika RSU Al-Arif butuh data administratif lebih lengkap.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/nomor.php';

wajibLogin();

$pdo = getKoneksi();

// Default aman terverifikasi (lihat docstring di atas).
// Dipakai sebagai variabel biasa (bukan const) supaya aman dari risiko
// "Cannot redeclare constant" jika file ini ter-include lebih dari sekali.
$kodeDefaultDashInt = 1;   // untuk kd_kel/kd_kec/kd_kab/kd_prop/suku_bangsa/bahasa_pasien/cacat_fisik
$kodeDefaultDashStr = '-'; // untuk perusahaan_pasien

$error = '';
$sukses = false;
$noRkmMedisBaru = '';

// Ambil daftar penjab (cara bayar) untuk dropdown
$stmtPenjab = $pdo->query("SELECT kd_pj, png_jawab FROM penjab WHERE status = '1' ORDER BY png_jawab ASC");
$daftarPenjab = $stmtPenjab->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nm_pasien'] ?? '');
    $noKtp = trim($_POST['no_ktp'] ?? '');
    $jk = $_POST['jk'] ?? '';
    $tglLahir = $_POST['tgl_lahir'] ?? '';
    $tmpLahir = trim($_POST['tmp_lahir'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $noTlp = trim($_POST['no_tlp'] ?? '');
    $namaIbu = trim($_POST['nm_ibu'] ?? '');
    $pekerjaan = trim($_POST['pekerjaan'] ?? '');
    $kdPj = $_POST['kd_pj'] ?? 'A09';
    $golDarah = $_POST['gol_darah'] ?? '-';
    $sttsNikah = $_POST['stts_nikah'] ?? 'BELUM MENIKAH';
    $agama = trim($_POST['agama'] ?? '-');

    if ($nama === '' || $jk === '' || $tglLahir === '') {
        $error = 'Nama, jenis kelamin, dan tanggal lahir wajib diisi.';
    } else {
        try {
            // Hitung umur sederhana dari tgl_lahir (format Khanza: "X Th Y Bl Z Hr")
            $lahir = new DateTime($tglLahir);
            $sekarang = new DateTime();
            $selisih = $sekarang->diff($lahir);
            $umurString = "{$selisih->y} Th {$selisih->m} Bl {$selisih->d} Hr";

            $noRkmMedisBaru = generateNoRkmMedis();
            $tglDaftar = date('Y-m-d');

            $stmt = $pdo->prepare(
                "INSERT INTO pasien (
                    no_rkm_medis, nm_pasien, no_ktp, jk, tmp_lahir, tgl_lahir, nm_ibu,
                    alamat, gol_darah, pekerjaan, stts_nikah, agama, tgl_daftar, no_tlp,
                    umur, pnd, keluarga, namakeluarga, kd_pj, no_peserta,
                    kd_kel, kd_kec, kd_kab, pekerjaanpj, alamatpj, kelurahanpj, kecamatanpj,
                    kabupatenpj, perusahaan_pasien, suku_bangsa, bahasa_pasien, cacat_fisik,
                    email, nip, kd_prop, propinsipj
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?
                )"
            );

            $stmt->execute([
                $noRkmMedisBaru, $nama, $noKtp, $jk, $tmpLahir, $tglLahir, $namaIbu ?: '-',
                $alamat, $golDarah, $pekerjaan, $sttsNikah, $agama, $tglDaftar, $noTlp,
                $umurString, '-', 'DIRI SENDIRI', $nama, $kdPj, '',
                $kodeDefaultDashInt, $kodeDefaultDashInt, $kodeDefaultDashInt, '', '', '', '',
                '', $kodeDefaultDashStr, $kodeDefaultDashInt, $kodeDefaultDashInt, $kodeDefaultDashInt,
                '', '', $kodeDefaultDashInt, '',
            ]);

            $sukses = true;
        } catch (Throwable $e) {
            error_log('[daftar-baru.php] Gagal insert pasien: ' . $e->getMessage());
            $error = 'Gagal menyimpan data pasien. Hubungi admin sistem jika masalah berulang.';
        }
    }
}

$halamanAktif = 'pasien';
$judulHalaman = 'Pendaftaran Pasien Baru';
$baseAsset = '../';
require __DIR__ . '/../lib/layout_header.php';
?>

<?php if ($sukses): ?>
    <div class="card">
        <div class="alert alert-success">
            ✔ Pasien baru berhasil didaftarkan dengan No. RM <strong><?= htmlspecialchars($noRkmMedisBaru) ?></strong>.
        </div>
        <a href="registrasi.php?no_rkm_medis=<?= urlencode($noRkmMedisBaru) ?>" class="btn btn-primary">
            Lanjut ke Registrasi Kunjungan →
        </a>
        <a href="daftar-baru.php" class="btn btn-outline" style="margin-left:8px;">Daftarkan Pasien Lain</a>
    </div>
<?php else: ?>

<div class="card">
    <p class="card-title">Form Pendaftaran Pasien Baru</p>
    <p class="text-muted">
        No. rekam medis akan dibuat otomatis (urut, 6 digit) setelah form disimpan.
    </p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-row">
            <div>
                <label for="nm_pasien">Nama Lengkap *</label>
                <input type="text" id="nm_pasien" name="nm_pasien" required
                       value="<?= htmlspecialchars($_POST['nm_pasien'] ?? '') ?>">
            </div>
            <div>
                <label for="no_ktp">No. KTP</label>
                <input type="text" id="no_ktp" name="no_ktp" maxlength="20"
                       value="<?= htmlspecialchars($_POST['no_ktp'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div>
                <label for="jk">Jenis Kelamin *</label>
                <select id="jk" name="jk" required>
                    <option value="">-- Pilih --</option>
                    <option value="P">Perempuan</option>
                    <option value="L">Laki-laki</option>
                </select>
            </div>
            <div>
                <label for="tgl_lahir">Tanggal Lahir *</label>
                <input type="date" id="tgl_lahir" name="tgl_lahir" required
                       value="<?= htmlspecialchars($_POST['tgl_lahir'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div>
                <label for="tmp_lahir">Tempat Lahir</label>
                <input type="text" id="tmp_lahir" name="tmp_lahir"
                       value="<?= htmlspecialchars($_POST['tmp_lahir'] ?? '') ?>">
            </div>
            <div>
                <label for="nm_ibu">Nama Ibu Kandung</label>
                <input type="text" id="nm_ibu" name="nm_ibu"
                       value="<?= htmlspecialchars($_POST['nm_ibu'] ?? '') ?>">
            </div>
        </div>

        <label for="alamat">Alamat</label>
        <textarea id="alamat" name="alamat" rows="2"><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>

        <div class="form-row">
            <div>
                <label for="no_tlp">No. Telepon / HP</label>
                <input type="text" id="no_tlp" name="no_tlp"
                       value="<?= htmlspecialchars($_POST['no_tlp'] ?? '') ?>">
            </div>
            <div>
                <label for="pekerjaan">Pekerjaan</label>
                <input type="text" id="pekerjaan" name="pekerjaan"
                       value="<?= htmlspecialchars($_POST['pekerjaan'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div>
                <label for="gol_darah">Golongan Darah</label>
                <select id="gol_darah" name="gol_darah">
                    <option value="-">Tidak tahu</option>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="O">O</option>
                    <option value="AB">AB</option>
                </select>
            </div>
            <div>
                <label for="stts_nikah">Status Pernikahan</label>
                <select id="stts_nikah" name="stts_nikah">
                    <option value="BELUM MENIKAH">Belum Menikah</option>
                    <option value="MENIKAH">Menikah</option>
                    <option value="JANDA">Janda</option>
                    <option value="DUDHA">Duda</option>
                </select>
            </div>
        </div>

        <label for="kd_pj">Cara Bayar / Penjamin</label>
        <select id="kd_pj" name="kd_pj">
            <?php foreach ($daftarPenjab as $pj): ?>
                <option value="<?= htmlspecialchars($pj['kd_pj']) ?>"
                    <?= $pj['kd_pj'] === 'A09' ? 'selected' : '' ?>>
                    <?= htmlspecialchars($pj['png_jawab']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-primary">Simpan Pasien Baru</button>
        <a href="cari.php" class="btn btn-outline" style="margin-left:8px;">Batal</a>
    </form>
</div>

<?php endif; ?>

<?php require __DIR__ . '/../lib/layout_footer.php'; ?>
