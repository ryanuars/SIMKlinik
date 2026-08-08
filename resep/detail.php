<?php
/**
 * resep/detail.php
 * -----------------------------------------------------------------
 * Tampilan detail satu resep: header + daftar item
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';

wajibLogin();

$pdo = getKoneksi();

$noResep = trim($_GET['no_resep'] ?? '');
$noRawat = trim($_GET['no_rawat'] ?? '');
if ($noResep === '') {
    header('Location: tulis.php' . ($noRawat ? '?no_rawat=' . urlencode($noRawat) : ''));
    exit;
}

// Header resep
$stmtHdr = $pdo->prepare(
    "SELECT ro.*, dok.nm_dokter, p.nm_pasien
     FROM resep_obat ro
     LEFT JOIN dokter dok ON ro.kd_dokter = dok.kd_dokter
     LEFT JOIN reg_periksa rp ON ro.no_rawat = rp.no_rawat
     LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
     WHERE ro.no_resep = ?"
);
$stmtHdr->execute([$noResep]);
$header = $stmtHdr->fetch();
if (!$header) {
    echo '<p>Resep tidak ditemukan.</p>';
    exit;
}
if (!$noRawat) $noRawat = $header['no_rawat'];

// Item resep
$stmtItem = $pdo->prepare(
    "SELECT rd.*, db.nama_brng, db.kode_satbesar, db.ralan as harga_satuan
     FROM resep_dokter rd
     LEFT JOIN databarang db ON rd.kode_brng = db.kode_brng
     WHERE rd.no_resep = ?"
);
$stmtItem->execute([$noResep]);
$items = $stmtItem->fetchAll();

$halamanAktif = 'resep';
$judulHalaman = 'Detail Resep';
$baseAsset    = '../';
require __DIR__ . '/../lib/layout_header.php';
?>

<div style="margin-bottom:14px;">
    <a href="tulis.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-back">← Kembali ke Daftar Resep</a>
    <span class="text-muted" style="margin-left:8px; font-size:13px;">Resep: <code><?= htmlspecialchars($noResep) ?></code></span>
</div>

<div class="card">
    <p class="card-title">Informasi Resep</p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13.5px;">
        <div>
            <p><span class="text-muted">No. Resep:</span> <code><?= htmlspecialchars($header['no_resep']) ?></code></p>
            <p><span class="text-muted">No. Rawat:</span> <code><?= htmlspecialchars($header['no_rawat']) ?></code></p>
            <p><span class="text-muted">Pasien:</span> <strong><?= htmlspecialchars($header['nm_pasien'] ?? '-') ?></strong></p>
        </div>
        <div>
            <p><span class="text-muted">Dokter:</span> <?= htmlspecialchars($header['nm_dokter'] ?? '-') ?></p>
            <p><span class="text-muted">Tgl Peresepan:</span> <?= $header['tgl_peresepan'] ? date('d-m-Y', strtotime($header['tgl_peresepan'])) : '-' ?></p>
            <p><span class="text-muted">Status Penyerahan:</span>
                <?php if ($header['tgl_penyerahan'] && $header['tgl_penyerahan'] !== '0000-00-00'): ?>
                    <span class="badge badge-success">Sudah Diserahkan — <?= date('d-m-Y', strtotime($header['tgl_penyerahan'])) ?></span>
                <?php else: ?>
                    <span class="badge badge-warning">Belum Diserahkan</span>
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>

<div class="card">
    <p class="card-title">Item Obat / Barang (<?= count($items) ?> item)</p>
    <?php if ($items): ?>
    <div style="overflow-x:auto;">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Kode</th>
                <th>Nama Obat / Barang</th>
                <th style="text-align:center;">Jumlah</th>
                <th>Satuan</th>
                <th>Aturan Pakai</th>
                <th style="text-align:right;">Harga/Unit</th>
                <th style="text-align:right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
        <?php $total = 0; $i = 1; foreach ($items as $item): ?>
            <?php $sub = (float)($item['harga_satuan'] ?? 0) * (float)($item['jml'] ?? 0); $total += $sub; ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><code><?= htmlspecialchars($item['kode_brng']) ?></code></td>
                <td><?= htmlspecialchars($item['nama_brng'] ?? '-') ?></td>
                <td style="text-align:center;"><?= htmlspecialchars($item['jml']) ?></td>
                <td><?= htmlspecialchars($item['kode_satbesar'] ?? '-') ?></td>
                <td><?= htmlspecialchars($item['aturan_pakai'] ?? '-') ?></td>
                <td style="text-align:right;font-family:monospace;">Rp <?= number_format((float)($item['harga_satuan'] ?? 0), 0, ',', '.') ?></td>
                <td style="text-align:right;font-family:monospace;">Rp <?= number_format($sub, 0, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f9f9f9;font-weight:700;">
                <td colspan="7" style="text-align:right;padding:10px;">Total Estimasi:</td>
                <td style="text-align:right;font-family:monospace;padding:10px;">
                    Rp <?= number_format($total, 0, ',', '.') ?>
                </td>
            </tr>
        </tfoot>
    </table>
    </div>
    <?php else: ?>
        <p class="text-muted">Tidak ada item obat pada resep ini.</p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../lib/layout_footer.php'; ?>
