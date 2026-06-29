<?php
/**
 * test-generator-nomor.php
 * -----------------------------------------------------------------
 * Halaman uji generateNoRawat() & generateNoRkmMedis() — READ ONLY,
 * TIDAK melakukan INSERT apapun. Tujuannya memverifikasi bahwa kedua
 * fungsi menghasilkan nomor yang masuk akal (lanjutan dari nomor
 * terakhir) sebelum dipakai sungguhan di form pendaftaran (Fase 2).
 *
 * HAPUS atau lindungi file ini sebelum go-live produksi.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/nomor.php';

wajibLogin();

$halamanAktif = '';
$judulHalaman = 'Uji Generator Nomor (Fase 1)';

$errorRm = null;
$nomorRmBaru = null;
try {
    $nomorRmBaru = generateNoRkmMedis();
} catch (Throwable $e) {
    $errorRm = $e->getMessage();
}

$tglUji = $_GET['tgl'] ?? date('Y-m-d');
$nomorRawatBaru = generateNoRawat($tglUji);

require __DIR__ . '/lib/layout_header.php';
?>

<div class="card">
    <p class="card-title">Uji generateNoRkmMedis()</p>
    <p class="text-muted">Read-only — tidak melakukan INSERT apapun ke tabel <code>pasien</code>.</p>
    <?php if ($errorRm): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorRm) ?></div>
    <?php else: ?>
        <p>Nomor RM berikutnya yang akan dipakai jika ada pasien baru daftar sekarang:</p>
        <p style="font-size:24px;font-weight:700;color:var(--color-primary);">
            <code><?= htmlspecialchars($nomorRmBaru) ?></code>
        </p>
    <?php endif; ?>
</div>

<div class="card">
    <p class="card-title">Uji generateNoRawat()</p>
    <p class="text-muted">Read-only — tidak melakukan INSERT apapun ke tabel <code>reg_periksa</code>.</p>

    <form method="get" style="margin-bottom:16px;">
        <label for="tgl">Tanggal registrasi yang diuji</label>
        <input type="date" id="tgl" name="tgl" value="<?= htmlspecialchars($tglUji) ?>"
               style="max-width:220px;display:inline-block;">
        <button type="submit" class="btn btn-outline" style="vertical-align:top;">Cek Ulang</button>
    </form>

    <p>Nomor rawat berikutnya untuk tanggal <strong><?= htmlspecialchars($tglUji) ?></strong>:</p>
    <p style="font-size:24px;font-weight:700;color:var(--color-primary);">
        <code><?= htmlspecialchars($nomorRawatBaru) ?></code>
    </p>
</div>

<?php require __DIR__ . '/lib/layout_footer.php'; ?>
