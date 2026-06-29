<?php
/**
 * dashboard.php
 * -----------------------------------------------------------------
 * Landing page setelah login. Fase 1: tampilan dasar + ringkasan
 * kunjungan hari ini dari reg_periksa (read-only, query sederhana).
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/lib/auth.php';

wajibLogin();

$pdo = getKoneksi();

// Ringkasan kunjungan hari ini (read-only — aman, tidak mengubah data)
$stmt = $pdo->prepare(
    "SELECT reg_periksa.no_rawat, pasien.nm_pasien, poliklinik.nm_poli,
            dokter.nm_dokter, reg_periksa.stts
     FROM reg_periksa
     INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
     INNER JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
     LEFT JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter
     WHERE reg_periksa.tgl_registrasi = CURDATE()
     ORDER BY reg_periksa.no_rawat DESC
     LIMIT 15"
);
$stmt->execute();
$kunjunganHariIni = $stmt->fetchAll();

$halamanAktif = 'dashboard';
$judulHalaman = 'Dashboard';
require __DIR__ . '/lib/layout_header.php';
?>

<div class="card">
    <p class="card-title">Selamat datang, <?= htmlspecialchars(sessionNama() ?? '') ?></p>
    <p class="text-muted">Ringkasan kunjungan hari ini (<?= date('d-m-Y') ?>).</p>
</div>

<div class="card">
    <p class="card-title">Kunjungan Hari Ini</p>
    <?php if (empty($kunjunganHariIni)): ?>
        <p class="text-muted">Belum ada kunjungan tercatat hari ini.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>No. Rawat</th>
                    <th>Nama Pasien</th>
                    <th>Poli</th>
                    <th>Dokter</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kunjunganHariIni as $r): ?>
                <tr>
                    <td><code><?= htmlspecialchars($r['no_rawat']) ?></code></td>
                    <td><?= htmlspecialchars($r['nm_pasien']) ?></td>
                    <td><?= htmlspecialchars($r['nm_poli']) ?></td>
                    <td><?= htmlspecialchars($r['nm_dokter'] ?? '-') ?></td>
                    <td>
                        <?php
                        $badgeClass = match ($r['stts']) {
                            'Sudah' => 'badge-success',
                            'Batal' => 'badge-danger',
                            default => 'badge-warning',
                        };
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($r['stts']) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/lib/layout_footer.php'; ?>
