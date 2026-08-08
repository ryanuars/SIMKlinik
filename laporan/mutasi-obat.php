<?php
/**
 * laporan/mutasi-obat.php
 * -----------------------------------------------------------------
 * Halaman Detail Kartu Mutasi Obat (Khusus Admin Utama).
 * Menampilkan rincian transaksi kronologis masuk & keluar (penerimaan supplier
 * & pengeluaran resep pasien) beserta perhitungan sisa saldo running balance.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';

wajibLogin();

// AUTH GUARD: HANYA Admin Utama yang berhak mengakses kartu mutasi obat
if (($_SESSION['role'] ?? '') !== ROLE_ADMIN && ($_SESSION['role'] ?? '') !== 'admin') {
    echo '<div style="font-family:sans-serif; padding:20px; color:#721c24; background:#f8d7da; border:1px solid #f5c6cb; border-radius:6px; margin:40px auto; max-width:600px; text-align:center;">'
       . '⛔ <strong>Akses Ditolak:</strong> Halaman Kartu Mutasi Obat hanya dapat diakses oleh Admin Utama. '
       . '<br><br><a href="../dashboard.php" style="color:#721c24; font-weight:bold;">← Kembali ke Dashboard</a>'
       . '</div>';
    exit;
}

$pdo = getKoneksi();

$kodeBrng = trim($_GET['kode_brng'] ?? '');
if ($kodeBrng === '') {
    header('Location: stok-obat.php');
    exit;
}

// Parameter Filter Tanggal (Mendukung start_date/end_date atau tgl_awal/tgl_akhir)
$tglAwal   = $_GET['tgl_awal'] ?? $_GET['start_date'] ?? date('Y-m-01');
$tglAkhir  = $_GET['tgl_akhir'] ?? $_GET['end_date'] ?? date('Y-m-d');
$isExport  = ($_GET['export'] ?? '') === 'excel';

// 1. Fetch Informasi Detail Barang / Obat
$stmtBrng = $pdo->prepare(
    "SELECT db.kode_brng, db.nama_brng, db.kode_sat, db.stokminimal, db.h_beli, db.ralan,
            COALESCE(SUM(gb.stok), 0) AS stok_saat_ini
     FROM databarang db
     LEFT JOIN gudangbarang gb ON db.kode_brng = gb.kode_brng
     WHERE db.kode_brng = ?
     GROUP BY db.kode_brng, db.nama_brng, db.kode_sat, db.stokminimal, db.h_beli, db.ralan"
);
$stmtBrng->execute([$kodeBrng]);
$obatInfo = $stmtBrng->fetch();

if (!$obatInfo) {
    echo '<div style="font-family:sans-serif; padding:20px; color:#721c24; background:#f8d7da; border:1px solid #f5c6cb; border-radius:6px; margin:40px auto; max-width:600px; text-align:center;">'
       . '⚠️ <strong>Data Tidak Ditemukan:</strong> Kode obat <code>' . htmlspecialchars($kodeBrng) . '</code> tidak terdaftar di database.'
       . '<br><br><a href="stok-obat.php" style="color:#721c24; font-weight:bold;">← Kembali ke Ringkasan Stok</a>'
       . '</div>';
    exit;
}

// 2. Fetch Riwayat Transaksi Mutasi (UNION ALL Penerimaan/Masuk & Pengeluaran/Keluar)
$sqlMutasi = "
    (
        SELECT r.tanggal AS tgl, r.jam, 'MASUK' AS jenis_transaksi,
               COALESCE(NULLIF(r.no_faktur, ''), '-') AS no_ref,
               r.masuk AS qty_masuk, 0 AS qty_keluar,
               COALESCE(NULLIF(r.keterangan, ''), 'Penerimaan / Pengadaan Supplier') AS keterangan
        FROM riwayat_barang_medis r
        WHERE r.kode_brng = ? AND r.masuk > 0 AND r.tanggal BETWEEN ? AND ?
    )
    UNION ALL
    (
        SELECT dpo.tgl_perawatan AS tgl, dpo.jam, 'KELUAR' AS jenis_transaksi,
               COALESCE(dpo.no_rawat, '-') AS no_ref,
               0 AS qty_masuk, dpo.jml AS qty_keluar,
               CONCAT('Penyerahan Resep Pasien: ', p.nm_pasien, ' (RM: ', p.no_rkm_medis, ')') AS keterangan
        FROM detail_pemberian_obat dpo
        JOIN reg_periksa rp ON dpo.no_rawat = rp.no_rawat
        JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        WHERE dpo.kode_brng = ? AND dpo.tgl_perawatan BETWEEN ? AND ?
    )
    ORDER BY tgl ASC, jam ASC
";

$stmtMutasi = $pdo->prepare($sqlMutasi);
$stmtMutasi->execute([
    $kodeBrng, $tglAwal, $tglAkhir,
    $kodeBrng, $tglAwal, $tglAkhir
]);
$rowsMutasi = $stmtMutasi->fetchAll();

// Hitung total masuk & keluar dalam rentang tanggal
$totalMasuk  = array_sum(array_column($rowsMutasi, 'qty_masuk'));
$totalKeluar = array_sum(array_column($rowsMutasi, 'qty_keluar'));

// Handle Export Excel
if ($isExport) {
    header("Content-Type: application/vnd-ms-excel");
    header("Content-Disposition: attachment; filename=Mutasi_Obat_" . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $kodeBrng) . "_" . $tglAwal . "_sd_" . $tglAkhir . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    ?>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr style="background:#f2f2f2;">
                <th colspan="7" style="font-size:16px; font-weight:bold; text-align:center;">
                    KARTU MUTASI STOK OBAT - <?= htmlspecialchars($obatInfo['nama_brng']) ?> (<?= htmlspecialchars($kodeBrng) ?>)
                </th>
            </tr>
            <tr style="background:#f2f2f2;">
                <th colspan="7" style="text-align:center;">
                    Periode: <?= htmlspecialchars($tglAwal) ?> s/d <?= htmlspecialchars($tglAkhir) ?> | Satuan: <?= htmlspecialchars($obatInfo['kode_sat']) ?> | Stok Saat Ini: <?= (float)$obatInfo['stok_saat_ini'] ?>
                </th>
            </tr>
            <tr style="background:#4CAF50; color:#fff; font-weight:bold;">
                <th>No</th>
                <th>Tanggal &amp; Jam</th>
                <th>Jenis Transaksi</th>
                <th>No. Referensi / Faktur / Rawat</th>
                <th>Qty Masuk</th>
                <th>Qty Keluar</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rowsMutasi as $idx => $m): ?>
            <tr>
                <td style="text-align:center;"><?= $idx + 1 ?></td>
                <td><?= date('d-m-Y', strtotime($m['tgl'])) ?> <?= htmlspecialchars($m['jam']) ?></td>
                <td style="text-align:center; font-weight:bold; color: <?= $m['jenis_transaksi'] === 'MASUK' ? 'green' : 'red' ?>;"><?= $m['jenis_transaksi'] ?></td>
                <td>'<?= htmlspecialchars($m['no_ref']) ?></td>
                <td style="text-align:center;"><?= (float)$m['qty_masuk'] ?></td>
                <td style="text-align:center;"><?= (float)$m['qty_keluar'] ?></td>
                <td><?= htmlspecialchars($m['keterangan']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    exit;
}

$exportUrl = 'mutasi-obat.php?' . http_build_query(array_merge($_GET, ['export' => 'excel']));

$halamanAktif = 'laporan';
$judulHalaman = 'Kartu Mutasi Obat — ' . $obatInfo['nama_brng'];
$baseAsset    = '../';
require __DIR__ . '/../lib/layout_header.php';
?>
<style>
.mut-tbl {
    width: 100%;
    border-collapse: collapse;
    font-size: 12.5px;
}
.mut-tbl th {
    background: var(--color-primary);
    color: #ffffff;
    padding: 9px 10px;
    text-align: left;
    white-space: nowrap;
}
.mut-tbl td {
    border-bottom: 1px solid var(--color-border);
    padding: 8px 10px;
    vertical-align: middle;
}
.mut-tbl tr:hover td {
    background: #FDF6F8;
}
.btn-excel {
    background: #107c41 !important;
    color: #ffffff !important;
    border-color: #107c41 !important;
}
@media print {
    .sidebar, .topbar, .filter-box, .btn-print, .btn-excel, .btn-back { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    body { background: #fff !important; font-size: 11px !important; }
    .card { border: none !important; box-shadow: none !important; }
}
</style>

<!-- Header & Navigasi -->
<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
    <div>
        <a href="stok-obat.php" class="btn btn-back" style="font-size:12.5px; margin-bottom:6px; display:inline-block;">← Kembali ke Ringkasan Stok</a>
        <h2 style="font-size:18px; font-weight:700; margin:0; color:var(--color-primary);">📋 Kartu Mutasi Stok Obat</h2>
    </div>
    <div style="display:flex; gap:8px;">
        <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-excel" style="font-size:12.5px; text-decoration:none;">📥 Unduh Excel (.xls)</a>
        <button onclick="window.print()" class="btn btn-outline btn-print" style="font-size:12.5px;">🖨️ Cetak Kartu Mutasi</button>
    </div>
</div>

<!-- Header Info Obat -->
<div class="card" style="margin-bottom:16px; background:#FAF5F8; border-color:#E8CDD8;">
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:14px;">
        <div>
            <span class="text-muted" style="font-size:11.5px; font-weight:600; text-transform:uppercase;">Nama Obat</span>
            <div style="font-size:16px; font-weight:700; color:var(--color-primary);"><?= htmlspecialchars($obatInfo['nama_brng']) ?></div>
            <code style="font-size:11.5px;"><?= htmlspecialchars($obatInfo['kode_brng']) ?></code>
        </div>
        <div>
            <span class="text-muted" style="font-size:11.5px; font-weight:600; text-transform:uppercase;">Satuan &amp; Stok Min</span>
            <div style="font-size:14px; font-weight:600;"><?= htmlspecialchars($obatInfo['kode_sat']) ?> (Min: <?= (float)$obatInfo['stokminimal'] ?>)</div>
            <small class="text-muted">Harga Beli: Rp <?= number_format((float)$obatInfo['h_beli'], 0, ',', '.') ?></small>
        </div>
        <div>
            <span class="text-muted" style="font-size:11.5px; font-weight:600; text-transform:uppercase;">Stok Real-Time Saat Ini</span>
            <div style="font-size:18px; font-weight:700; color: <?= (float)$obatInfo['stok_saat_ini'] <= (float)$obatInfo['stokminimal'] ? '#dc2626' : '#16a34a' ?>;">
                <?= number_format((float)$obatInfo['stok_saat_ini'], 0, ',', '.') ?> <small style="font-size:12px; font-weight:normal;"><?= htmlspecialchars($obatInfo['kode_sat']) ?></small>
            </div>
        </div>
        <div>
            <span class="text-muted" style="font-size:11.5px; font-weight:600; text-transform:uppercase;">Total Pergerakan (Periode ini)</span>
            <div style="font-size:12.5px; margin-top:2px;">
                <span style="color:#16a34a; font-weight:700;">+<?= number_format($totalMasuk, 0, ',', '.') ?> masuk</span> | 
                <span style="color:#dc2626; font-weight:700;">-<?= number_format($totalKeluar, 0, ',', '.') ?> keluar</span>
            </div>
        </div>
    </div>
</div>

<!-- Form Filter Rentang Tanggal -->
<div class="card filter-box" style="margin-bottom:16px; padding:12px 16px;">
    <form method="get" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
        <input type="hidden" name="kode_brng" value="<?= htmlspecialchars($kodeBrng) ?>">
        <div>
            <label for="start_date" style="font-size:11.5px; font-weight:600; display:block; margin-bottom:3px;">Tanggal Awal</label>
            <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($tglAwal) ?>" style="padding:5px 9px; font-size:12.5px;">
        </div>
        <div>
            <label for="end_date" style="font-size:11.5px; font-weight:600; display:block; margin-bottom:3px;">Tanggal Akhir</label>
            <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($tglAkhir) ?>" style="padding:5px 9px; font-size:12.5px;">
        </div>
        <div style="margin-top:16px; display:flex; gap:8px;">
            <button type="submit" class="btn btn-primary" style="padding:6px 16px; font-size:12.5px;">🔍 Filter Mutasi</button>
            <a href="mutasi-obat.php?kode_brng=<?= urlencode($kodeBrng) ?>" class="btn btn-outline" style="padding:6px 12px; font-size:12.5px;">Reset</a>
        </div>
    </form>
</div>

<!-- Tabel Kronologis Mutasi Obat -->
<div class="card" style="padding:0; overflow:hidden;">
    <div style="overflow-x:auto;">
    <table class="mut-tbl">
        <thead>
            <tr>
                <th style="width:30px; text-align:center;">No</th>
                <th>Tanggal &amp; Jam</th>
                <th style="text-align:center;">Jenis Transaksi</th>
                <th>No. Referensi / Rawat / Faktur</th>
                <th style="text-align:center;">Qty Masuk</th>
                <th style="text-align:center;">Qty Keluar</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rowsMutasi)): ?>
            <tr>
                <td colspan="7" style="text-align:center; padding:20px; color:#888;">
                    Belum ada riwayat mutasi masuk/keluar untuk obat ini dalam rentang tanggal yang dipilih.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($rowsMutasi as $idx => $m):
                $isMasuk = ($m['jenis_transaksi'] === 'MASUK');
            ?>
            <tr>
                <td style="text-align:center;"><?= $idx + 1 ?></td>
                <td>
                    <strong><?= date('d-m-Y', strtotime($m['tgl'])) ?></strong><br>
                    <small class="text-muted"><?= htmlspecialchars($m['jam']) ?></small>
                </td>
                <td style="text-align:center;">
                    <span class="badge <?= $isMasuk ? 'badge-success' : 'badge-danger' ?>" style="font-size:11px;">
                        <?= $isMasuk ? '📥 MASUK' : '📤 KELUAR' ?>
                    </span>
                </td>
                <td><code><?= htmlspecialchars($m['no_ref']) ?></code></td>
                <td style="text-align:center; font-weight:700; color:#16a34a;">
                    <?= $isMasuk ? '+' . number_format((float)$m['qty_masuk'], 0, ',', '.') : '-' ?>
                </td>
                <td style="text-align:center; font-weight:700; color:#dc2626;">
                    <?= !$isMasuk ? '-' . number_format((float)$m['qty_keluar'], 0, ',', '.') : '-' ?>
                </td>
                <td><?= htmlspecialchars($m['keterangan']) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <?php if (!empty($rowsMutasi)): ?>
        <tfoot>
            <tr style="background:#f8fafc; font-weight:700;">
                <td colspan="4" style="text-align:right; padding:10px;">TOTAL TRANSAKSI MUTASI (PERIODE INI):</td>
                <td style="text-align:center; color:#16a34a; font-size:13px;">+<?= number_format($totalMasuk, 0, ',', '.') ?></td>
                <td style="text-align:center; color:#dc2626; font-size:13px;">-<?= number_format($totalKeluar, 0, ',', '.') ?></td>
                <td></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
    </div>
</div>

<?php require __DIR__ . '/../lib/layout_footer.php'; ?>
