<?php
/**
 * laporan/index.php
 * -----------------------------------------------------------------
 * Halaman Laporan Keuangan & Omset Klinis (Khusus Admin Utama).
 * Menampilkan rincian tagihan per kunjungan: Registrasi, Tindakan Medis,
 * dan Obat/Resep beserta status pembayaran.
 * Dilengkapi dengan fitur Filter, Pagination (100 rows/page), dan Export Excel.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';

wajibLogin();

// AUTH GUARD: HANYA Admin Utama yang berhak mengakses halaman laporan
if (($_SESSION['role'] ?? '') !== ROLE_ADMIN && ($_SESSION['role'] ?? '') !== 'admin') {
    echo '<div style="font-family:sans-serif; padding:20px; color:#721c24; background:#f8d7da; border:1px solid #f5c6cb; border-radius:6px; margin:40px auto; max-width:600px; text-align:center;">'
       . '⛔ <strong>Akses Ditolak:</strong> Halaman Laporan Keuangan hanya dapat diakses oleh Admin Utama. '
       . '<br><br><a href="../dashboard.php" style="color:#721c24; font-weight:bold;">← Kembali ke Dashboard</a>'
       . '</div>';
    exit;
}

$pdo = getKoneksi();

// Filter Parameter (Mendukung tgl_awal/tgl_akhir atau start_date/end_date)
$tglAwal     = $_GET['tgl_awal'] ?? $_GET['start_date'] ?? date('Y-m-01');
$tglAkhir    = $_GET['tgl_akhir'] ?? $_GET['end_date'] ?? date('Y-m-d');
$statusBayar = trim($_GET['status_bayar'] ?? '');

// -----------------------------------------------------------------
// LOGIKA PAGINATION (Batas 100 baris per halaman)
// -----------------------------------------------------------------
$limit  = 100;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// 1. Query Hitung Total Record (COUNT(*)) dengan filter yang sama
$sqlCount = "SELECT COUNT(*) FROM reg_periksa r WHERE r.tgl_registrasi BETWEEN ? AND ?";
$paramsCount = [$tglAwal, $tglAkhir];
if ($statusBayar !== '') {
    $sqlCount .= " AND r.status_bayar = ?";
    $paramsCount[] = $statusBayar;
}
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($paramsCount);
$totalRows  = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $limit));

// Pastikan halaman tidak melebihi totalhalaman
if ($page > $totalPages) {
    $page   = $totalPages;
    $offset = ($page - 1) * $limit;
}

// 2. Query Utama dengan LIMIT & OFFSET
$sqlReg = "SELECT r.no_rawat, r.tgl_registrasi, r.jam_reg, r.kd_pj, r.kd_poli, r.biaya_reg,
                  r.status_bayar, r.stts,
                  p.nm_pasien, p.no_rkm_medis,
                  pol.nm_poli, dok.nm_dokter,
                  pj.png_jawab as nm_pj
           FROM reg_periksa r
           JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
           LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
           LEFT JOIN dokter dok ON r.kd_dokter = dok.kd_dokter
           LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
           WHERE r.tgl_registrasi BETWEEN ? AND ?";

$paramsReg = [$tglAwal, $tglAkhir];

if ($statusBayar !== '') {
    $sqlReg .= " AND r.status_bayar = ?";
    $paramsReg[] = $statusBayar;
}

$sqlReg .= " ORDER BY r.tgl_registrasi DESC, r.jam_reg DESC LIMIT {$limit} OFFSET {$offset}";

$stmtReg = $pdo->prepare($sqlReg);
$stmtReg->execute($paramsReg);
$daftarReg = $stmtReg->fetchAll();

// Prepared Statements untuk Komponen Biaya per Kunjungan
$stmtDr = $pdo->prepare("SELECT j.nm_perawatan, d.biaya_rawat FROM rawat_jl_dr d JOIN jns_perawatan j ON d.kd_jenis_prw = j.kd_jenis_prw WHERE d.no_rawat = ?");
$stmtPr = $pdo->prepare("SELECT j.nm_perawatan, d.biaya_rawat FROM rawat_jl_pr d JOIN jns_perawatan j ON d.kd_jenis_prw = j.kd_jenis_prw WHERE d.no_rawat = ?");
$stmtDrPr = $pdo->prepare("SELECT j.nm_perawatan, d.biaya_rawat FROM rawat_jl_drpr d JOIN jns_perawatan j ON d.kd_jenis_prw = j.kd_jenis_prw WHERE d.no_rawat = ?");
$stmtObat = $pdo->prepare("SELECT db.nama_brng, dpo.jml, dpo.total as subtotal FROM detail_pemberian_obat dpo JOIN databarang db ON dpo.kode_brng = db.kode_brng WHERE dpo.no_rawat = ?");

// Pengumpulan Data & Perhitungan Biaya Halaman Ini
$laporanData     = [];
$pageTotalLunas  = 0;
$pageTotalBelum  = 0;

foreach ($daftarReg as $reg) {
    $noRawat  = $reg['no_rawat'];
    $biayaReg = (float)($reg['biaya_reg'] ?? 0);

    // 1. Tindakan Dokter
    $stmtDr->execute([$noRawat]);
    $tDr = $stmtDr->fetchAll();

    // 2. Tindakan Perawat
    $stmtPr->execute([$noRawat]);
    $tPr = $stmtPr->fetchAll();

    // 3. Tindakan Bersama
    $stmtDrPr->execute([$noRawat]);
    $tDrPr = $stmtDrPr->fetchAll();

    $rincianTindakan = [];
    $totalTindakan   = 0;
    foreach (array_merge($tDr, $tPr, $tDrPr) as $t) {
        $biaya = (float)$t['biaya_rawat'];
        $totalTindakan += $biaya;
        $rincianTindakan[] = htmlspecialchars($t['nm_perawatan']) . ' (Rp ' . number_format($biaya, 0, ',', '.') . ')';
    }

    // 4. Obat/Resep
    $stmtObat->execute([$noRawat]);
    $itemsObat = $stmtObat->fetchAll();
    $rincianObat = [];
    $totalObat   = 0;
    foreach ($itemsObat as $ob) {
        $sub = (float)$ob['subtotal'];
        $totalObat += $sub;
        $rincianObat[] = htmlspecialchars($ob['nama_brng']) . ' x' . (float)$ob['jml'] . ' (Rp ' . number_format($sub, 0, ',', '.') . ')';
    }

    $totalBiaya = $biayaReg + $totalTindakan + $totalObat;
    $isLunas    = ($reg['status_bayar'] === 'Sudah Bayar');

    if ($isLunas) {
        $pageTotalLunas += $totalBiaya;
    } else {
        $pageTotalBelum += $totalBiaya;
    }

    $laporanData[] = [
        'reg'             => $reg,
        'rincianTindakan' => $rincianTindakan,
        'totalTindakan'   => $totalTindakan,
        'rincianObat'     => $rincianObat,
        'totalObat'       => $totalObat,
        'totalBiaya'      => $totalBiaya,
        'isLunas'         => $isLunas,
    ];
}

// Helper Menyusun Link Pagination dengan Mempertahankan Parameter Filter GET
function buildPaginationUrl(int $targetPage, array $getParams): string {
    $params = $getParams;
    $params['page'] = $targetPage;
    return 'index.php?' . http_build_query($params);
}

// Helper URL Export Excel dengan Mempertahankan Filter
$exportParams = $_GET;
$exportParams['tgl_awal']  = $tglAwal;
$exportParams['tgl_akhir'] = $tglAkhir;
unset($exportParams['page']); // Hapus parameter page agar Excel mengambil seluruh data
$excelUrl = 'export-excel.php?' . http_build_query($exportParams);

$halamanAktif = 'laporan';
$judulHalaman = 'Laporan Keuangan & Omset';
$baseAsset    = '../';
require __DIR__ . '/../lib/layout_header.php';
?>
<style>
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}
.stat-card {
    background: #ffffff;
    border: 1px solid var(--color-border);
    border-radius: 10px;
    padding: 14px 18px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
}
.stat-card .val {
    font-size: 20px;
    font-weight: 700;
    color: var(--color-primary);
    margin-top: 4px;
}
.stat-card.success .val { color: #16a34a; }
.stat-card.warning .val { color: #d97706; }
.stat-card.info .val { color: #2563eb; }

.rpt-tbl {
    width: 100%;
    border-collapse: collapse;
    font-size: 12.5px;
}
.rpt-tbl th {
    background: var(--color-primary);
    color: #ffffff;
    padding: 9px 10px;
    text-align: left;
    white-space: nowrap;
}
.rpt-tbl td {
    border-bottom: 1px solid var(--color-border);
    padding: 8px 10px;
    vertical-align: top;
}
.rpt-tbl tr:hover td {
    background: #FDF6F8;
}
.item-list {
    margin: 0;
    padding-left: 14px;
    font-size: 11.5px;
    color: #444;
}
.btn-excel {
    background: #107c41 !important;
    color: #ffffff !important;
    border-color: #107c41 !important;
}
.btn-excel:hover {
    background: #0b5c30 !important;
    border-color: #0b5c30 !important;
}
@media print {
    .sidebar, .topbar, .filter-box, .btn-print, .btn-excel, .btn-back, .pagination-box { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    body { background: #fff !important; font-size: 11px !important; }
    .card { border: none !important; box-shadow: none !important; }
}
</style>

<!-- Header & Aksi Utama -->
<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
    <div>
        <h2 style="font-size:18px; font-weight:700; margin:0; color:var(--color-primary);">📈 Laporan Keuangan &amp; Pendapatan</h2>
        <span class="text-muted" style="font-size:12.5px;">Periode: <strong><?= date('d M Y', strtotime($tglAwal)) ?></strong> s/d <strong><?= date('d M Y', strtotime($tglAkhir)) ?></strong></span>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a href="<?= htmlspecialchars($excelUrl) ?>" class="btn btn-excel" style="font-size:12.5px; text-decoration:none;">📥 Unduh Excel (.xls)</a>
        <button onclick="window.print()" class="btn btn-outline btn-print" style="font-size:12.5px;">🖨️ Cetak Laporan</button>
        <a href="../dashboard.php" class="btn btn-outline btn-back" style="font-size:12.5px;">Dashboard</a>
    </div>
</div>

<!-- Cards Ringkasan Statistik -->
<div class="stat-grid">
    <div class="stat-card info">
        <div class="text-muted" style="font-size:11.5px; text-transform:uppercase; font-weight:600;">Total Kunjungan</div>
        <div class="val"><?= number_format($totalRows, 0, ',', '.') ?> <small style="font-size:12px; font-weight:normal;">pasien</small></div>
    </div>
    <div class="stat-card success">
        <div class="text-muted" style="font-size:11.5px; text-transform:uppercase; font-weight:600;">Omset Lunas (Halaman Ini)</div>
        <div class="val">Rp <?= number_format($pageTotalLunas, 0, ',', '.') ?></div>
    </div>
    <div class="stat-card warning">
        <div class="text-muted" style="font-size:11.5px; text-transform:uppercase; font-weight:600;">Belum Lunas (Halaman Ini)</div>
        <div class="val">Rp <?= number_format($pageTotalBelum, 0, ',', '.') ?></div>
    </div>
    <div class="stat-card">
        <div class="text-muted" style="font-size:11.5px; text-transform:uppercase; font-weight:600;">Total Halaman Ini</div>
        <div class="val">Rp <?= number_format($pageTotalLunas + $pageTotalBelum, 0, ',', '.') ?></div>
    </div>
</div>

<!-- Form Filter Rentang Tanggal & Status -->
<div class="card filter-box" style="margin-bottom:16px; padding:12px 16px;">
    <form method="get" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
        <div>
            <label for="tgl_awal" style="font-size:11.5px; font-weight:600; display:block; margin-bottom:3px;">Tanggal Awal</label>
            <input type="date" id="tgl_awal" name="tgl_awal" value="<?= htmlspecialchars($tglAwal) ?>" style="padding:5px 9px; font-size:12.5px;">
        </div>
        <div>
            <label for="tgl_akhir" style="font-size:11.5px; font-weight:600; display:block; margin-bottom:3px;">Tanggal Akhir</label>
            <input type="date" id="tgl_akhir" name="tgl_akhir" value="<?= htmlspecialchars($tglAkhir) ?>" style="padding:5px 9px; font-size:12.5px;">
        </div>
        <div>
            <label for="status_bayar" style="font-size:11.5px; font-weight:600; display:block; margin-bottom:3px;">Status Pembayaran</label>
            <select id="status_bayar" name="status_bayar" style="padding:6px 9px; font-size:12.5px;">
                <option value="">-- Semua Status --</option>
                <option value="Sudah Bayar" <?= $statusBayar === 'Sudah Bayar' ? 'selected' : '' ?>>Sudah Bayar (Lunas)</option>
                <option value="Belum Bayar" <?= $statusBayar === 'Belum Bayar' ? 'selected' : '' ?>>Belum Bayar</option>
            </select>
        </div>
        <div style="margin-top:16px; display:flex; gap:8px;">
            <button type="submit" class="btn btn-primary" style="padding:6px 16px; font-size:12.5px;">🔍 Filter Data</button>
            <a href="<?= htmlspecialchars($excelUrl) ?>" class="btn btn-excel" style="padding:6px 14px; font-size:12.5px; text-decoration:none;">📥 Unduh Excel</a>
            <a href="index.php" class="btn btn-outline" style="padding:6px 12px; font-size:12.5px;">Reset</a>
        </div>
    </form>
</div>

<!-- Tabel Data Laporan Keuangan -->
<div class="card" style="padding:0; overflow:hidden;">
    <div style="overflow-x:auto;">
    <table class="rpt-tbl">
        <thead>
            <tr>
                <th style="width:30px; text-align:center;">No</th>
                <th>Tgl &amp; Jam Kunjungan</th>
                <th>No. RM &amp; Nama Pasien</th>
                <th>Jenis Bayar</th>
                <th>Dokter Pemeriksa</th>
                <th>Rincian Tindakan (Rp)</th>
                <th>Rincian Obat (Rp)</th>
                <th style="text-align:right;">Total Biaya (Rp)</th>
                <th style="text-align:center;">Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($laporanData)): ?>
            <tr>
                <td colspan="9" style="text-align:center; padding:20px; color:#888;">
                    Tidak ada data transaksi keuangan dalam rentang tanggal ini.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($laporanData as $no => $d): $reg = $d['reg']; ?>
            <tr>
                <td style="text-align:center;"><?= $offset + $no + 1 ?></td>
                <td>
                    <strong><?= date('d-m-Y', strtotime($reg['tgl_registrasi'])) ?></strong><br>
                    <small class="text-muted"><?= htmlspecialchars($reg['jam_reg']) ?></small><br>
                    <code style="font-size:10.5px;"><?= htmlspecialchars($reg['no_rawat']) ?></code>
                </td>
                <td>
                    <strong><?= htmlspecialchars($reg['nm_pasien']) ?></strong><br>
                    <small class="text-muted">RM: <?= htmlspecialchars($reg['no_rkm_medis']) ?></small>
                </td>
                <td>
                    <span class="badge" style="background:#f1f5f9; color:#475569; font-size:11px;">
                        <?= htmlspecialchars($reg['nm_pj'] ?: '-') ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($reg['nm_dokter'] ?: '-') ?></td>
                <td>
                    <?php if (!empty($d['rincianTindakan'])): ?>
                        <ul class="item-list">
                        <?php foreach ($d['rincianTindakan'] as $itemT): ?>
                            <li><?= $itemT ?></li>
                        <?php endforeach; ?>
                        </ul>
                        <div style="font-size:11px; font-weight:700; color:#555; margin-top:3px;">
                            Subtotal: Rp <?= number_format($d['totalTindakan'], 0, ',', '.') ?>
                        </div>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($d['rincianObat'])): ?>
                        <ul class="item-list">
                        <?php foreach ($d['rincianObat'] as $itemO): ?>
                            <li><?= $itemO ?></li>
                        <?php endforeach; ?>
                        </ul>
                        <div style="font-size:11px; font-weight:700; color:#555; margin-top:3px;">
                            Subtotal: Rp <?= number_format($d['totalObat'], 0, ',', '.') ?>
                        </div>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:right; font-weight:700; color:var(--color-primary);">
                    Rp <?= number_format($d['totalBiaya'], 0, ',', '.') ?>
                </td>
                <td style="text-align:center;">
                    <span class="badge <?= $d['isLunas'] ? 'badge-success' : 'badge-warning' ?>" style="font-size:11px;">
                        <?= $d['isLunas'] ? 'LUNAS' : 'Belum Bayar' ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <?php if (!empty($laporanData)): ?>
        <tfoot>
            <tr style="background:#f8fafc; font-weight:700;">
                <td colspan="7" style="text-align:right; padding:10px;">SUBTOTAL OMSET HALAMAN INI:</td>
                <td style="text-align:right; color:var(--color-primary); font-size:14px; padding:10px;">
                    Rp <?= number_format($pageTotalLunas + $pageTotalBelum, 0, ',', '.') ?>
                </td>
                <td></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
    </div>
</div>

<!-- Navigasi UI Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination-box" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-top:16px; padding:12px 16px; background:#fff; border:1px solid var(--color-border); border-radius:8px;">
    <div style="font-size:12.5px; color:#666;">
        Menampilkan <strong><?= $offset + 1 ?></strong> - <strong><?= min($offset + count($daftarReg), $totalRows) ?></strong> dari <strong><?= number_format($totalRows, 0, ',', '.') ?></strong> total data (Halaman <strong><?= $page ?></strong> / <strong><?= $totalPages ?></strong>)
    </div>
    <ul class="pagination" style="display:flex; list-style:none; margin:0; padding:0; gap:4px;">
        <!-- First Page -->
        <li>
            <a href="<?= $page > 1 ? buildPaginationUrl(1, $_GET) : '#' ?>"
               style="padding:6px 12px; border:1px solid #ddd; border-radius:4px; text-decoration:none; font-size:12px; <?= $page <= 1 ? 'color:#ccc; pointer-events:none;' : 'color:var(--color-primary);' ?>">
               « First
            </a>
        </li>
        <!-- Prev Page -->
        <li>
            <a href="<?= $page > 1 ? buildPaginationUrl($page - 1, $_GET) : '#' ?>"
               style="padding:6px 12px; border:1px solid #ddd; border-radius:4px; text-decoration:none; font-size:12px; <?= $page <= 1 ? 'color:#ccc; pointer-events:none;' : 'color:var(--color-primary);' ?>">
               ‹ Prev
            </a>
        </li>

        <?php
        // Smart Pagination Page Range (max 5 page buttons)
        $startP = max(1, $page - 2);
        $endP   = min($totalPages, $page + 2);
        for ($p = $startP; $p <= $endP; $p++):
        ?>
        <li>
            <a href="<?= buildPaginationUrl($p, $_GET) ?>"
               style="padding:6px 12px; border:1px solid #ddd; border-radius:4px; text-decoration:none; font-size:12px; <?= $p === $page ? 'background:var(--color-primary); color:#fff; font-weight:bold; border-color:var(--color-primary);' : 'color:var(--color-primary);' ?>">
                <?= $p ?>
            </a>
        </li>
        <?php endfor; ?>

        <!-- Next Page -->
        <li>
            <a href="<?= $page < $totalPages ? buildPaginationUrl($page + 1, $_GET) : '#' ?>"
               style="padding:6px 12px; border:1px solid #ddd; border-radius:4px; text-decoration:none; font-size:12px; <?= $page >= $totalPages ? 'color:#ccc; pointer-events:none;' : 'color:var(--color-primary);' ?>">
               Next ›
            </a>
        </li>
        <!-- Last Page -->
        <li>
            <a href="<?= $page < $totalPages ? buildPaginationUrl($totalPages, $_GET) : '#' ?>"
               style="padding:6px 12px; border:1px solid #ddd; border-radius:4px; text-decoration:none; font-size:12px; <?= $page >= $totalPages ? 'color:#ccc; pointer-events:none;' : 'color:var(--color-primary);' ?>">
               Last »
            </a>
        </li>
    </ul>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../lib/layout_footer.php'; ?>
