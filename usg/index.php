<?php
/**
 * usg/index.php
 * -----------------------------------------------------------------
 * Halaman utama daftar pasien terdaftar (registrasi kunjungan)
 * untuk mengakses modul USG Kandungan & Ginekologi.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';

wajibLogin();

$pdo = getKoneksi();

// Tangkap parameter no_rawat jika dioper dari dashboard
if (!empty($_GET['no_rawat'])) {
    $_SESSION['last_no_rawat'] = trim($_GET['no_rawat']);
    header('Location: kandungan.php?no_rawat=' . urlencode($_SESSION['last_no_rawat']));
    exit;
}

// Filter Tanggal Registrasi (default hari ini)
$tanggalFilter = $_GET['tanggal'] ?? date('Y-m-d');
$searchQuery = trim($_GET['q'] ?? '');

// Query dasar
$sql = "SELECT r.no_rawat, r.no_reg, r.tgl_registrasi, r.jam_reg, r.stts,
               p.no_rkm_medis, p.nm_pasien, p.jk, p.tgl_lahir,
               pol.nm_poli, d.nm_dokter,
               (SELECT COUNT(*) FROM hasil_pemeriksaan_usg WHERE no_rawat = r.no_rawat) as count_usg_kandungan,
               (SELECT COUNT(*) FROM hasil_pemeriksaan_usg_gynecologi WHERE no_rawat = r.no_rawat) as count_usg_ginekologi
        FROM reg_periksa r
        INNER JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
        INNER JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
        LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
        WHERE r.tgl_registrasi = ?";

$params = [$tanggalFilter];

// Jika mencari pasien tertentu
if ($searchQuery !== '') {
    $sql .= " AND (p.nm_pasien LIKE ? OR p.no_rkm_medis LIKE ? OR r.no_rawat LIKE ?)";
    $likeParam = '%' . $searchQuery . '%';
    $params[] = $likeParam;
    $params[] = $likeParam;
    $params[] = $likeParam;
}

$sql .= " ORDER BY r.no_rawat DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarRegistrasi = $stmt->fetchAll();

$halamanAktif = 'usg';
$judulHalaman = 'Hasil Pemeriksaan USG';
$baseAsset    = '../';
require __DIR__ . '/../lib/layout_header.php';
?>
<style>
.filter-bar {
    display: flex;
    gap: 12px;
    align-items: flex-end;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.filter-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.badge-indicator {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 99px;
    white-space: nowrap;
}
.badge-indicator.filled {
    background-color: #E6F4EE;
    color: #2F6B4F;
    border: 1px solid #ccebe0;
}
.badge-indicator.empty {
    background-color: #FFF0F0;
    color: #D62839;
    border: 1px solid #ffd6d9;
}
.indikator-container {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    align-items: center;
}
.btn-group {
    display: flex;
    gap: 6px;
}
</style>

<div class="card">
    <p class="card-title">Daftar Pasien Kunjungan</p>
    <p class="text-muted" style="margin-bottom: 15px;">
        Pilih pasien di bawah ini untuk mengisi, mengedit, atau melihat hasil pemeriksaan USG Kandungan (Obstetri) dan USG Ginekologi.
    </p>

    <!-- Filter Form -->
    <form method="get" class="filter-bar">
        <div class="filter-item">
            <label for="tanggal" style="font-weight: 600; font-size: 12px;">Tanggal Registrasi</label>
            <input type="date" id="tanggal" name="tanggal" value="<?= htmlspecialchars($tanggalFilter) ?>" style="margin-bottom:0; max-width:180px;">
        </div>
        <div class="filter-item" style="flex: 1; min-width: 200px;">
            <label for="q" style="font-weight: 600; font-size: 12px;">Cari Nama / No. RM / No. Rawat</label>
            <input type="text" id="q" name="q" placeholder="Cari..." value="<?= htmlspecialchars($searchQuery) ?>" style="margin-bottom:0;">
        </div>
        <button type="submit" class="btn btn-primary" style="height: 38px;">Terapkan Filter</button>
        <?php if ($tanggalFilter !== date('Y-m-d') || $searchQuery !== ''): ?>
            <a href="index.php" class="btn btn-outline" style="height: 38px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">Hari Ini</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <p class="card-title">Daftar Kunjungan Pasien (Total: <?= count($daftarRegistrasi) ?>)</p>
    <?php if (empty($daftarRegistrasi)): ?>
        <div class="alert alert-warning" style="margin-bottom:0;">
            Tidak ditemukan kunjungan pasien terdaftar pada tanggal <strong><?= htmlspecialchars(date('d-m-Y', strtotime($tanggalFilter))) ?></strong>.
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 150px;">No. Rawat / RM</th>
                        <th>Identitas Pasien</th>
                        <th>Poliklinik / Dokter</th>
                        <th style="width: 250px;">Status Pemeriksaan USG</th>
                        <th style="width: 200px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftarRegistrasi as $r): ?>
                    <tr>
                        <td>
                            <code><?= htmlspecialchars($r['no_rawat']) ?></code><br>
                            <small class="text-muted" style="font-family: monospace;">RM: <?= htmlspecialchars($r['no_rkm_medis']) ?></small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($r['nm_pasien']) ?></strong><br>
                            <small class="text-muted"><?= $r['jk'] === 'P' ? 'Perempuan' : 'Laki-laki' ?> &bull; Lahir: <?= date('d-m-Y', strtotime($r['tgl_lahir'])) ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($r['nm_poli']) ?><br>
                            <small class="text-muted"><?= htmlspecialchars($r['nm_dokter'] ?? '-') ?></small>
                        </td>
                        <td>
                            <div class="indikator-container">
                                <!-- USG Kandungan -->
                                <span class="badge-indicator <?= $r['count_usg_kandungan'] > 0 ? 'filled' : 'empty' ?>" title="Hasil Pemeriksaan USG Kandungan">
                                    <?= $r['count_usg_kandungan'] > 0 ? '✔ USG Kandungan' : '✘ USG Kandungan' ?>
                                </span>
                                
                                <!-- USG Ginekologi -->
                                <span class="badge-indicator <?= $r['count_usg_ginekologi'] > 0 ? 'filled' : 'empty' ?>" title="Hasil Pemeriksaan USG Ginekologi">
                                    <?= $r['count_usg_ginekologi'] > 0 ? '✔ USG Ginekologi' : '✘ USG Ginekologi' ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="kandungan.php?no_rawat=<?= urlencode($r['no_rawat']) ?>" class="btn btn-primary" style="padding:6px 12px; font-size: 13px; text-decoration: none; display: inline-block;">
                                    USG Kandungan
                                </a>
                                <a href="ginekologi.php?no_rawat=<?= urlencode($r['no_rawat']) ?>" class="btn btn-outline" style="padding:6px 12px; font-size: 13px; text-decoration: none; display: inline-block; border-color: var(--color-primary); color: var(--color-primary);">
                                    USG Ginekologi
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../lib/layout_footer.php'; ?>
