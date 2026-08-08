<?php
/**
 * billing/index.php
 * -----------------------------------------------------------------
 * Halaman Billing Rawat Jalan per kunjungan.
 * Menampilkan ringkasan semua biaya dari:
 *   - Tindakan Dokter       (rawat_jl_dr)
 *   - Tindakan Perawat      (rawat_jl_pr)
 *   - Tindakan Bersama      (rawat_jl_drpr)
 *   - Resep/Obat            (resep_obat + resep_dokter + databarang)
 *   - Registrasi            (reg_periksa)
 * Referensi tabel: billing, nota_jalan, detail_nota_jalan
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';

wajibLogin();

$pdo = getKoneksi();

// Ambil daftar pasien hari ini (tidak ada no_rawat spesifik = halaman daftar)
$tanggalFilter = $_GET['tanggal'] ?? date('Y-m-d');
$searchQuery   = trim($_GET['q'] ?? '');

$sql = "SELECT r.no_rawat, r.tgl_registrasi, r.jam_reg, r.stts, r.status_bayar,
               p.no_rkm_medis, p.nm_pasien, p.jk, p.tgl_lahir,
               pol.nm_poli, d.nm_dokter,
               (SELECT COUNT(*) FROM nota_jalan WHERE no_rawat = r.no_rawat) as has_nota
        FROM reg_periksa r
        INNER JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
        INNER JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
        LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
        WHERE r.tgl_registrasi = ?";
$params = [$tanggalFilter];

if ($searchQuery !== '') {
    $sql .= " AND (p.nm_pasien LIKE ? OR p.no_rkm_medis LIKE ? OR r.no_rawat LIKE ?)";
    $likeParam = '%' . $searchQuery . '%';
    $params = array_merge($params, [$likeParam, $likeParam, $likeParam]);
}
$sql .= " ORDER BY r.no_rawat DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarRegistrasi = $stmt->fetchAll();

$halamanAktif = 'billing';
$judulHalaman = 'Billing Rawat Jalan';
$baseAsset    = '../';
require __DIR__ . '/../lib/layout_header.php';
?>
<style>
.filter-bar { display:flex; gap:12px; align-items:flex-end; margin-bottom:20px; flex-wrap:wrap; }
.filter-item { display:flex; flex-direction:column; gap:4px; }
.badge-nota-done { background:#E6F4EE; color:#2F6B4F; border:1px solid #ccebe0; font-size:11px; font-weight:600; padding:3px 8px; border-radius:99px; }
.badge-nota-pending { background:#FFF0F0; color:#D62839; border:1px solid #ffd6d9; font-size:11px; font-weight:600; padding:3px 8px; border-radius:99px; }
</style>

<div class="card">
    <p class="card-title">Pilih Kunjungan untuk Billing</p>
    <p class="text-muted" style="margin-bottom:15px;">
        Pilih kunjungan pasien untuk melihat ringkasan tagihan dan mencetak nota pembayaran.
    </p>
    <form method="get" class="filter-bar">
        <div class="filter-item">
            <label for="tanggal" style="font-weight:600;font-size:12px;">Tanggal Registrasi</label>
            <input type="date" id="tanggal" name="tanggal" value="<?= htmlspecialchars($tanggalFilter) ?>" style="margin-bottom:0;max-width:180px;">
        </div>
        <div class="filter-item" style="flex:1;min-width:200px;">
            <label for="q" style="font-weight:600;font-size:12px;">Cari Nama / No.RM / No.Rawat</label>
            <input type="text" id="q" name="q" placeholder="Cari..." value="<?= htmlspecialchars($searchQuery) ?>" style="margin-bottom:0;">
        </div>
        <button type="submit" class="btn btn-primary" style="height:38px;">Terapkan Filter</button>
        <?php if ($tanggalFilter !== date('Y-m-d') || $searchQuery !== ''): ?>
            <a href="index.php" class="btn btn-outline" style="height:38px;display:inline-flex;align-items:center;">Hari Ini</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <p class="card-title">Daftar Kunjungan (Total: <?= count($daftarRegistrasi) ?>)</p>
    <?php if (empty($daftarRegistrasi)): ?>
        <div class="alert alert-warning" style="margin-bottom:0;">
            Tidak ditemukan kunjungan pada tanggal <strong><?= htmlspecialchars(date('d-m-Y', strtotime($tanggalFilter))) ?></strong>.
        </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="table">
        <thead>
            <tr>
                <th>No. Rawat / RM</th>
                <th>Identitas Pasien</th>
                <th>Poliklinik / Dokter</th>
                <th>Status Pembayaran</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($daftarRegistrasi as $r): ?>
        <tr>
            <td>
                <code><?= htmlspecialchars($r['no_rawat']) ?></code><br>
                <small class="text-muted">RM: <?= htmlspecialchars($r['no_rkm_medis']) ?></small>
            </td>
            <td>
                <strong><?= htmlspecialchars($r['nm_pasien']) ?></strong><br>
                <small class="text-muted"><?= $r['jk']==='P'?'Perempuan':'Laki-laki' ?> · Lahir: <?= date('d-m-Y', strtotime($r['tgl_lahir'])) ?></small>
            </td>
            <td>
                <?= htmlspecialchars($r['nm_poli']) ?><br>
                <small class="text-muted"><?= htmlspecialchars($r['nm_dokter'] ?? '-') ?></small>
            </td>
            <td>
                <?php if ($r['status_bayar'] === 'Sudah Bayar'): ?>
                    <span class="badge-nota-done">✔ LUNAS (Sudah Bayar)</span>
                <?php else: ?>
                    <span class="badge-nota-pending">BELUM BAYAR</span>
                <?php endif; ?>
                <?php if ($r['has_nota'] > 0): ?>
                    <br><small class="text-muted" style="margin-top:4px;display:inline-block;">Nota: dibuat</small>
                <?php endif; ?>
            </td>
            <td>
                <a href="tagihan.php?no_rawat=<?= urlencode($r['no_rawat']) ?>"
                   class="btn btn-primary" style="padding:6px 12px;font-size:13px;text-decoration:none;">
                    Lihat Tagihan
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../lib/layout_footer.php'; ?>
