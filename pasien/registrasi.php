<?php
/**
 * pasien/registrasi.php
 * -----------------------------------------------------------------
 * Form registrasi kunjungan -> INSERT ke `reg_periksa`.
 * Dropdown poli & dokter bersifat GENERAL (menampilkan semua data
 * aktif), dipilih manual oleh petugas — TIDAK dibatasi/hardcode ke
 * poli/dokter tertentu, sesuai permintaan eksplisit (poli kebidanan
 * bisa punya beberapa dokter, poli kecantikan bisa punya banyak
 * dokter juga).
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/nomor.php';

wajibLogin();

$pdo = getKoneksi();

$noRkmMedis = trim($_GET['no_rkm_medis'] ?? $_POST['no_rkm_medis'] ?? '');

if ($noRkmMedis === '') {
    header('Location: cari.php');
    exit;
}

// Ambil data pasien (untuk ditampilkan sebagai konfirmasi)
$stmt = $pdo->prepare("SELECT no_rkm_medis, nm_pasien, jk, tgl_lahir FROM pasien WHERE no_rkm_medis = ?");
$stmt->execute([$noRkmMedis]);
$pasien = $stmt->fetch();

if (!$pasien) {
    header('Location: cari.php');
    exit;
}

// Dropdown poli — semua poli AKTIF (status = '1'), general, tidak dibatasi
$stmtPoli = $pdo->query("SELECT kd_poli, nm_poli FROM poliklinik WHERE status = '1' ORDER BY nm_poli ASC");
$daftarPoli = $stmtPoli->fetchAll();

// Dropdown dokter — semua dokter AKTIF (status = '1'), general, tidak dibatasi.
// Disertakan nama spesialisasi (jika ada) supaya petugas lebih mudah memilih
// di antara banyak dokter (misal beberapa dokter kandungan / kecantikan).
$stmtDokter = $pdo->query(
    "SELECT dokter.kd_dokter, dokter.nm_dokter, spesialis.nm_sps
     FROM dokter
     LEFT JOIN spesialis ON dokter.kd_sps = spesialis.kd_sps
     WHERE dokter.status = '1'
     ORDER BY dokter.nm_dokter ASC"
);
$daftarDokter = $stmtDokter->fetchAll();

// Dropdown penjab (cara bayar)
$stmtPenjab = $pdo->query("SELECT kd_pj, png_jawab FROM penjab WHERE status = '1' ORDER BY png_jawab ASC");
$daftarPenjab = $stmtPenjab->fetchAll();

$error = '';
$sukses = false;
$noRawatBaru = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kdPoli = $_POST['kd_poli'] ?? '';
    $kdDokter = $_POST['kd_dokter'] ?? '';
    $kdPj = $_POST['kd_pj'] ?? '';
    $tglRegistrasi = $_POST['tgl_registrasi'] ?? date('Y-m-d');

    if ($kdPoli === '' || $kdDokter === '' || $kdPj === '') {
        $error = 'Poliklinik, dokter, dan cara bayar wajib dipilih.';
    } else {
        try {
            $noRawatBaru = generateNoRawat($tglRegistrasi);
            $noRegBaru   = generateNoReg($kdDokter, $tglRegistrasi);

            // Hitung umur saat registrasi (kolom umurdaftar + sttsumur di reg_periksa)
            $lahir = new DateTime($pasien['tgl_lahir']);
            $tglReg = new DateTime($tglRegistrasi);
            $selisih = $tglReg->diff($lahir);
            $umurTahun = $selisih->y;

            $stmt = $pdo->prepare(
                "INSERT INTO reg_periksa (
                    no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis,
                    kd_poli, p_jawab, almt_pj, hubunganpj, biaya_reg, stts, stts_daftar,
                    status_lanjut, kd_pj, umurdaftar, sttsumur, status_bayar, status_poli
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?
                )"
            );

            // NOTE keputusan desain: stts_daftar & status_poli diisi 'Lama' untuk
            // semua kasus di MVP ini. Alasan: alur kita selalu lewat cari.php atau
            // daftar-baru.php dulu sebelum sampai ke sini, jadi no_rkm_medis SUDAH
            // pasti ada di tabel `pasien` saat insert reg_periksa ini terjadi —
            // konsep "pasien baru" sudah ditangani terpisah di daftar-baru.php.
            // Jika nanti dibutuhkan pembedaan "kunjungan pertama ke poli ini",
            // bisa ditambah logika cek riwayat reg_periksa per kd_poli di Fase lanjutan.
            $stmt->execute([
                $noRegBaru, $noRawatBaru, $tglRegistrasi, date('H:i:s'), $kdDokter, $noRkmMedis,
                $kdPoli, $pasien['nm_pasien'], '', 'DIRI SENDIRI', 0, 'Belum', 'Lama',
                'Ralan', $kdPj, $umurTahun, 'Th', 'Belum Bayar', 'Lama',
            ]);

            $sukses = true;
        } catch (Throwable $e) {
            error_log('[registrasi.php] Gagal insert reg_periksa: ' . $e->getMessage());
            $error = 'Gagal menyimpan registrasi. Hubungi admin sistem jika masalah berulang.';
        }
    }
}

$halamanAktif = 'pasien';
$judulHalaman = 'Registrasi Kunjungan';
$baseAsset = '../';
require __DIR__ . '/../lib/layout_header.php';
?>

<div class="card">
    <p class="card-title">Data Pasien</p>
    <table class="table">
        <tr>
            <td style="width:160px;"><strong>No. RM</strong></td>
            <td><code><?= htmlspecialchars($pasien['no_rkm_medis']) ?></code></td>
        </tr>
        <tr>
            <td><strong>Nama</strong></td>
            <td><?= htmlspecialchars($pasien['nm_pasien']) ?></td>
        </tr>
        <tr>
            <td><strong>Jenis Kelamin</strong></td>
            <td><?= $pasien['jk'] === 'P' ? 'Perempuan' : 'Laki-laki' ?></td>
        </tr>
        <tr>
            <td><strong>Tanggal Lahir</strong></td>
            <td><?= htmlspecialchars(date('d-m-Y', strtotime($pasien['tgl_lahir']))) ?></td>
        </tr>
    </table>
</div>

<?php if ($sukses): ?>
    <div class="card">
        <div class="alert alert-success">
            ✔ Registrasi kunjungan berhasil dibuat dengan No. Rawat
            <strong><?= htmlspecialchars($noRawatBaru) ?></strong>.
        </div>
        <a href="../dashboard.php" class="btn btn-primary">Ke Dashboard</a>
        <a href="registrasi.php?no_rkm_medis=<?= urlencode($noRkmMedis) ?>" class="btn btn-outline" style="margin-left:8px;">
            Daftarkan Kunjungan Lain untuk Pasien Ini
        </a>
    </div>
<?php else: ?>

<div class="card">
    <p class="card-title">Form Registrasi Kunjungan</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="no_rkm_medis" value="<?= htmlspecialchars($noRkmMedis) ?>">

        <label for="tgl_registrasi">Tanggal Registrasi</label>
        <input type="date" id="tgl_registrasi" name="tgl_registrasi"
               value="<?= htmlspecialchars($_POST['tgl_registrasi'] ?? date('Y-m-d')) ?>"
               style="max-width:220px;">

        <label for="kd_poli">Poliklinik *</label>
        <select id="kd_poli" name="kd_poli" required>
            <option value="">-- Pilih Poliklinik --</option>
            <?php foreach ($daftarPoli as $poli): ?>
                <option value="<?= htmlspecialchars($poli['kd_poli']) ?>"
                    <?= ($_POST['kd_poli'] ?? '') === $poli['kd_poli'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($poli['nm_poli']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="kd_dokter">Dokter *</label>
        <select id="kd_dokter" name="kd_dokter" required>
            <option value="">-- Pilih Dokter --</option>
            <?php foreach ($daftarDokter as $dok): ?>
                <option value="<?= htmlspecialchars($dok['kd_dokter']) ?>"
                    <?= ($_POST['kd_dokter'] ?? '') === $dok['kd_dokter'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dok['nm_dokter']) ?><?= $dok['nm_sps'] ? ' — ' . htmlspecialchars($dok['nm_sps']) : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="text-muted" style="margin-top:-10px;margin-bottom:16px;">
            Daftar menampilkan semua dokter aktif beserta spesialisasinya — pilih sesuai kebutuhan kunjungan.
        </p>

        <label for="kd_pj">Cara Bayar / Penjamin *</label>
        <select id="kd_pj" name="kd_pj" required>
            <option value="">-- Pilih Cara Bayar --</option>
            <?php foreach ($daftarPenjab as $pj): ?>
                <option value="<?= htmlspecialchars($pj['kd_pj']) ?>"
                    <?= ($_POST['kd_pj'] ?? 'A09') === $pj['kd_pj'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($pj['png_jawab']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-primary">Simpan Registrasi</button>
        <a href="cari.php" class="btn btn-outline" style="margin-left:8px;">Batal</a>
    </form>
</div>

<?php endif; ?>

<?php require __DIR__ . '/../lib/layout_footer.php'; ?>
