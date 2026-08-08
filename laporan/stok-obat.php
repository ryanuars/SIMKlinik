<?php
/**
 * laporan/stok-obat.php
 * -----------------------------------------------------------------
 * Halaman Laporan Ringkasan Stok & Aset Obat (Khusus Admin Utama).
 * Menampilkan real-time stok obat, nilai aset, indikator stok minimal,
 * serta link ke Kartu Mutasi Obat.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';

wajibLogin();

// AUTH GUARD: HANYA Admin Utama yang berhak mengakses laporan stok obat
if (($_SESSION['role'] ?? '') !== ROLE_ADMIN && ($_SESSION['role'] ?? '') !== 'admin') {
    echo '<div style="font-family:sans-serif; padding:20px; color:#721c24; background:#f8d7da; border:1px solid #f5c6cb; border-radius:6px; margin:40px auto; max-width:600px; text-align:center;">'
       . '⛔ <strong>Akses Ditolak:</strong> Halaman Laporan Stok Obat hanya dapat diakses oleh Admin Utama. '
       . '<br><br><a href="../dashboard.php" style="color:#721c24; font-weight:bold;">← Kembali ke Dashboard</a>'
       . '</div>';
    exit;
}

$pdo = getKoneksi();

// Filter & Parameter
$keyword    = trim($_GET['keyword'] ?? '');
$statusStok = trim($_GET['status_stok'] ?? ''); // 'tipis' | 'aman'
$isExport   = ($_GET['export'] ?? '') === 'excel';

// Parameter Pagination
$limit  = 50; // 50 baris per halaman
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Base Query
$whereClause = "WHERE db.status = '1'";
$params = [];

if ($keyword !== '') {
    $whereClause .= " AND (db.nama_brng LIKE ? OR db.kode_brng LIKE ?)";
    $params[] = "%{$keyword}%";
    $params[] = "%{$keyword}%";
}

$havingClause = "";
if ($statusStok === 'tipis') {
    $havingClause = "HAVING stok_saat_ini <= db.stokminimal";
} elseif ($statusStok === 'aman') {
    $havingClause = "HAVING stok_saat_ini > db.stokminimal";
}

// 1. Count Total Items
$sqlCount = "SELECT COUNT(*) FROM (
                SELECT db.kode_brng, COALESCE(SUM(gb.stok), 0) AS stok_saat_ini
                FROM databarang db
                LEFT JOIN gudangbarang gb ON db.kode_brng = gb.kode_brng
                {$whereClause}
                GROUP BY db.kode_brng, db.stokminimal
                {$havingClause}
             ) AS count_tbl";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRows  = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $limit));

if ($page > $totalPages && !$isExport) {
    $page   = $totalPages;
    $offset = ($page - 1) * $limit;
}

// 2. Query Aggregate Summary (Total Jenis Obat, Total Items, Total Nilai Aset Beli)
$sqlSum = "SELECT COUNT(DISTINCT db.kode_brng) as total_jenis,
                  SUM(COALESCE(gb.stok, 0)) as total_fisik_stok,
                  SUM(COALESCE(gb.stok, 0) * db.h_beli) as total_nilai_aset
           FROM databarang db
           LEFT JOIN gudangbarang gb ON db.kode_brng = gb.kode_brng
           WHERE db.status = '1'";
$sumData = $pdo->query($sqlSum)->fetch();

// 3. Query Main Data
$sqlData = "SELECT db.kode_brng, db.nama_brng, db.kode_sat, db.stokminimal, db.h_beli, db.ralan,
                   COALESCE(SUM(gb.stok), 0) AS stok_saat_ini
            FROM databarang db
            LEFT JOIN gudangbarang gb ON db.kode_brng = gb.kode_brng
            {$whereClause}
            GROUP BY db.kode_brng, db.nama_brng, db.kode_sat, db.stokminimal, db.h_beli, db.ralan
            {$havingClause}
            ORDER BY (COALESCE(SUM(gb.stok), 0) <= db.stokminimal) DESC, db.nama_brng ASC";

if (!$isExport) {
    $sqlData .= " LIMIT {$limit} OFFSET {$offset}";
}

$stmtData = $pdo->prepare($sqlData);
$stmtData->execute($params);
$daftarStok = $stmtData->fetchAll();

// Handle Export Excel
if ($isExport) {
    header("Content-Type: application/vnd-ms-excel");
    header("Content-Disposition: attachment; filename=Laporan_Stok_Obat_" . date('Y-m-d') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    ?>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr style="background:#f2f2f2;">
                <th colspan="8" style="font-size:16px; font-weight:bold; text-align:center;">
                    LAPORAN RINGKASAN STOK &amp; NILAI ASET OBAT (<?= htmlspecialchars(NAMA_RS) ?>)
                </th>
            </tr>
            <tr style="background:#4CAF50; color:#fff; font-weight:bold;">
                <th>No</th>
                <th>Kode Obat</th>
                <th>Nama Obat</th>
                <th>Satuan</th>
                <th>Stok Minimal</th>
                <th>Stok Real-Time</th>
                <th>Harga Beli (Rp)</th>
                <th>Estimasi Nilai Aset (Rp)</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($daftarStok as $idx => $r): 
            $stok  = (float)$r['stok_saat_ini'];
            $min   = (float)$r['stokminimal'];
            $hBeli = (float)$r['h_beli'];
            $aset  = $stok * $hBeli;
        ?>
            <tr>
                <td style="text-align:center;"><?= $idx + 1 ?></td>
                <td>'<?= htmlspecialchars($r['kode_brng']) ?></td>
                <td><?= htmlspecialchars($r['nama_brng']) ?></td>
                <td><?= htmlspecialchars($r['kode_sat']) ?></td>
                <td style="text-align:center;"><?= $min ?></td>
                <td style="text-align:center; font-weight:bold; color: <?= $stok <= $min ? 'red' : 'black' ?>;"><?= $stok ?></td>
                <td style="text-align:right;"><?= $hBeli ?></td>
                <td style="text-align:right; font-weight:bold;"><?= $aset ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    exit;
}

// Helper Link Pagination
function buildStokPageUrl(int $targetPage, array $getParams): string {
    $p = $getParams;
    $p['page'] = $targetPage;
    return 'stok-obat.php?' . http_build_query($p);
}

$exportUrl = 'stok-obat.php?' . http_build_query(array_merge($_GET, ['export' => 'excel']));

$halamanAktif = 'laporan';
$judulHalaman = 'Laporan Stok Obat';
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
.stat-card.warning .val { color: #dc2626; }
.stat-card.success .val { color: #16a34a; }

.stk-tbl {
    width: 100%;
    border-collapse: collapse;
    font-size: 12.5px;
}
.stk-tbl th {
    background: var(--color-primary);
    color: #ffffff;
    padding: 9px 10px;
    text-align: left;
    white-space: nowrap;
}
.stk-tbl td {
    border-bottom: 1px solid var(--color-border);
    padding: 8px 10px;
    vertical-align: middle;
}
.stk-tbl tr:hover td {
    background: #FDF6F8;
}
.btn-excel {
    background: #107c41 !important;
    color: #ffffff !important;
    border-color: #107c41 !important;
}
</style>

<!-- Header & Aksi Utama -->
<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
    <div>
        <h2 style="font-size:18px; font-weight:700; margin:0; color:var(--color-primary);">📦 Laporan Ringkasan Stok &amp; Aset Obat</h2>
        <span class="text-muted" style="font-size:12.5px;">Monitoring ketersediaan stok fisik farmasi &amp; estimasi nilai aset.</span>
    </div>
    <div style="display:flex; gap:8px;">
        <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-excel" style="font-size:12.5px; text-decoration:none;">📥 Unduh Excel (.xls)</a>
        <a href="index.php" class="btn btn-outline" style="font-size:12.5px;">← Laporan Keuangan</a>
    </div>
</div>

<!-- Ringkasan Statistik -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="text-muted" style="font-size:11.5px; text-transform:uppercase; font-weight:600;">Total Jenis Obat</div>
        <div class="val"><?= number_format($sumData['total_jenis'] ?? 0, 0, ',', '.') ?> <small style="font-size:12px; font-weight:normal;">item</small></div>
    </div>
    <div class="stat-card success">
        <div class="text-muted" style="font-size:11.5px; text-transform:uppercase; font-weight:600;">Total Fisik Stok</div>
        <div class="val"><?= number_format($sumData['total_fisik_stok'] ?? 0, 0, ',', '.') ?> <small style="font-size:12px; font-weight:normal;">unit</small></div>
    </div>
    <div class="stat-card">
        <div class="text-muted" style="font-size:11.5px; text-transform:uppercase; font-weight:600;">Estimasi Nilai Aset Obat</div>
        <div class="val">Rp <?= number_format($sumData['total_nilai_aset'] ?? 0, 0, ',', '.') ?></div>
    </div>
</div>

<!-- Filter Box -->
<div class="card" style="margin-bottom:16px; padding:12px 16px;">
    <form method="get" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
        <div style="flex:1; min-width:200px;">
            <label for="keyword" style="font-size:11.5px; font-weight:600; display:block; margin-bottom:3px;">Cari Nama / Kode Obat</label>
            <input type="text" id="keyword" name="keyword" placeholder="Ketik nama atau kode obat..." value="<?= htmlspecialchars($keyword) ?>" style="padding:6px 10px; font-size:12.5px; width:100%;">
        </div>
        <div>
            <label for="status_stok" style="font-size:11.5px; font-weight:600; display:block; margin-bottom:3px;">Status Stok</label>
            <select id="status_stok" name="status_stok" style="padding:6px 10px; font-size:12.5px;">
                <option value="">-- Semua Status --</option>
                <option value="tipis" <?= $statusStok === 'tipis' ? 'selected' : '' ?>>⚠️ Stok Tipis (<= Minimal)</option>
                <option value="aman" <?= $statusStok === 'aman' ? 'selected' : '' ?>>✔ Stok Aman (> Minimal)</option>
            </select>
        </div>
        <div style="margin-top:16px; display:flex; gap:8px;">
            <button type="submit" class="btn btn-primary" style="padding:6px 16px; font-size:12.5px;">🔍 Cari</button>
            <a href="stok-obat.php" class="btn btn-outline" style="padding:6px 12px; font-size:12.5px;">Reset</a>
        </div>
    </form>
</div>

<!-- Tabel Ringkasan Stok -->
<div class="card" style="padding:0; overflow:hidden;">
    <div style="overflow-x:auto;">
    <table class="stk-tbl">
        <thead>
            <tr>
                <th style="width:30px; text-align:center;">No</th>
                <th>Kode Obat</th>
                <th>Nama Obat</th>
                <th>Satuan</th>
                <th style="text-align:center;">Stok Min</th>
                <th style="text-align:center;">Stok Real-Time</th>
                <th style="text-align:right;">Harga Beli</th>
                <th style="text-align:right;">Total Nilai Aset</th>
                <th style="text-align:center;">Status</th>
                <th style="text-align:center;">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($daftarStok)): ?>
            <tr>
                <td colspan="10" style="text-align:center; padding:20px; color:#888;">
                    Data stok obat tidak ditemukan.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($daftarStok as $idx => $r):
                $stok  = (float)$r['stok_saat_ini'];
                $min   = (float)$r['stokminimal'];
                $hBeli = (float)$r['h_beli'];
                $aset  = $stok * $hBeli;
                $isTipis = ($stok <= $min);
            ?>
            <tr>
                <td style="text-align:center;"><?= $offset + $idx + 1 ?></td>
                <td><code><?= htmlspecialchars($r['kode_brng']) ?></code></td>
                <td><strong><?= htmlspecialchars($r['nama_brng']) ?></strong></td>
                <td><?= htmlspecialchars($r['kode_sat']) ?></td>
                <td style="text-align:center;"><?= $min ?></td>
                <td style="text-align:center; font-weight:bold; font-size:13px; color: <?= $isTipis ? '#dc2626' : '#16a34a' ?>;">
                    <?= number_format($stok, 0, ',', '.') ?>
                </td>
                <td style="text-align:right;">Rp <?= number_format($hBeli, 0, ',', '.') ?></td>
                <td style="text-align:right; font-weight:600;">Rp <?= number_format($aset, 0, ',', '.') ?></td>
                <td style="text-align:center;">
                    <span class="badge <?= $isTipis ? 'badge-danger' : 'badge-success' ?>" style="font-size:11px;">
                        <?= $isTipis ? '⚠️ Menipis' : '✔ Aman' ?>
                    </span>
                </td>
                <td style="text-align:center;">
                    <a href="mutasi-obat.php?kode_brng=<?= urlencode($r['kode_brng']) ?>" class="btn btn-outline" style="font-size:11.5px; padding:3px 10px; text-decoration:none;">
                        Detail Mutasi →
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Navigasi Pagination -->
<?php if ($totalPages > 1): ?>
<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-top:16px; padding:12px 16px; background:#fff; border:1px solid var(--color-border); border-radius:8px;">
    <div style="font-size:12.5px; color:#666;">
        Menampilkan <strong><?= $offset + 1 ?></strong> - <strong><?= min($offset + count($daftarStok), $totalRows) ?></strong> dari <strong><?= number_format($totalRows, 0, ',', '.') ?></strong> item (Halaman <strong><?= $page ?></strong> / <strong><?= $totalPages ?></strong>)
    </div>
    <ul class="pagination" style="display:flex; list-style:none; margin:0; padding:0; gap:4px;">
        <li><a href="<?= $page > 1 ? buildStokPageUrl(1, $_GET) : '#' ?>" style="padding:6px 12px; border:1px solid #ddd; border-radius:4px; text-decoration:none; font-size:12px; <?= $page <= 1 ? 'color:#ccc; pointer-events:none;' : 'color:var(--color-primary);' ?>">« First</a></li>
        <li><a href="<?= $page > 1 ? buildStokPageUrl($page - 1, $_GET) : '#' ?>" style="padding:6px 12px; border:1px solid #ddd; border-radius:4px; text-decoration:none; font-size:12px; <?= $page <= 1 ? 'color:#ccc; pointer-events:none;' : 'color:var(--color-primary);' ?>">‹ Prev</a></li>
        <?php
        $startP = max(1, $page - 2);
        $endP   = min($totalPages, $page + 2);
        for ($p = $startP; $p <= $endP; $p++):
        ?>
        <li>
            <a href="<?= buildStokPageUrl($p, $_GET) ?>" style="padding:6px 12px; border:1px solid #ddd; border-radius:4px; text-decoration:none; font-size:12px; <?= $p === $page ? 'background:var(--color-primary); color:#fff; font-weight:bold; border-color:var(--color-primary);' : 'color:var(--color-primary);' ?>">
                <?= $p ?>
            </a>
        </li>
        <?php endfor; ?>
        <li><a href="<?= $page < $totalPages ? buildStokPageUrl($page + 1, $_GET) : '#' ?>" style="padding:6px 12px; border:1px solid #ddd; border-radius:4px; text-decoration:none; font-size:12px; <?= $page >= $totalPages ? 'color:#ccc; pointer-events:none;' : 'color:var(--color-primary);' ?>">Next ›</a></li>
        <li><a href="<?= $page < $totalPages ? buildStokPageUrl($totalPages, $_GET) : '#' ?>" style="padding:6px 12px; border:1px solid #ddd; border-radius:4px; text-decoration:none; font-size:12px; <?= $page >= $totalPages ? 'color:#ccc; pointer-events:none;' : 'color:var(--color-primary);' ?>">Last »</a></li>
    </ul>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../lib/layout_footer.php'; ?>
