<?php
/**
 * dashboard.php
 * -----------------------------------------------------------------
 * Landing page & Dashboard Dokter & Perawat.
 * Menampilkan:
 *  - 4 Widget Statistik Kunjungan Hari Ini (Total, Menunggu, Diperiksa, Selesai)
 *  - Banner Pasien Aktif terpilih (jika ada)
 *  - Tabel Antrean Kunjungan Hari Ini lengkap dengan shortcut tindakan
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/lib/auth.php';

wajibLogin();

$pdo = getKoneksi();

// Kelola parameter set/reset pasien aktif dari URL
if (!empty($_GET['set_no_rawat'])) {
    $_SESSION['last_no_rawat'] = trim($_GET['set_no_rawat']);
    if (isset($_GET['goto']) && $_GET['goto'] === 'asesmen') {
        header('Location: asesmen/pilih.php?no_rawat=' . urlencode($_SESSION['last_no_rawat']));
        exit;
    }
}
if (isset($_GET['reset_pasien'])) {
    unset($_SESSION['last_no_rawat']);
}

// Ambil info pasien aktif terpilih (jika ada di session)
$activeNoRawat = $_SESSION['last_no_rawat'] ?? '';
$activePasien = null;
if ($activeNoRawat !== '') {
    $stmtActive = $pdo->prepare(
        "SELECT r.no_rawat, p.nm_pasien, p.no_rkm_medis, pol.nm_poli, d.nm_dokter
         FROM reg_periksa r
         JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
         LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
         LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
         WHERE r.no_rawat = ?"
    );
    $stmtActive->execute([$activeNoRawat]);
    $activePasien = $stmtActive->fetch();
}

// ── 1. Query Statistik Kunjungan Hari Ini ─────────────────────────
$stmtStat = $pdo->prepare(
    "SELECT 
        COUNT(*) as total_kunjungan,
        SUM(CASE WHEN stts IN ('Belum', 'Belum Diperiksa') THEN 1 ELSE 0 END) as pasien_menunggu,
        SUM(CASE WHEN stts IN ('Sudah', 'Sedang Diperiksa', 'Pemeriksaan') THEN 1 ELSE 0 END) as sedang_diperiksa,
        SUM(CASE WHEN stts NOT IN ('Belum', 'Belum Diperiksa', 'Sudah', 'Sedang Diperiksa', 'Pemeriksaan') THEN 1 ELSE 0 END) as pasien_selesai
     FROM reg_periksa
     WHERE tgl_registrasi = CURDATE()"
);
$stmtStat->execute();
$stat = $stmtStat->fetch();

$total_kunjungan  = (int)($stat['total_kunjungan'] ?? 0);
$pasien_menunggu  = (int)($stat['pasien_menunggu'] ?? 0);
$sedang_diperiksa = (int)($stat['sedang_diperiksa'] ?? 0);
$pasien_selesai   = (int)($stat['pasien_selesai'] ?? 0);

// ── 2. Query Tabel Antrean Kunjungan Hari Ini ──────────────────────
$stmtKunjungan = $pdo->prepare(
    "SELECT r.no_rawat, r.no_reg, r.no_rkm_medis, p.nm_pasien, pol.nm_poli,
            d.nm_dokter, r.stts, r.status_bayar
     FROM reg_periksa r
     INNER JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
     INNER JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
     LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
     WHERE r.tgl_registrasi = CURDATE()
     ORDER BY r.no_reg ASC, r.no_rawat DESC"
);
$stmtKunjungan->execute();
$kunjunganHariIni = $stmtKunjungan->fetchAll();

// Penentuan nama sapaan user di greeting card
$idUserRaw   = strtolower(trim($_SESSION['id_user'] ?? ''));
$userRoleRaw = sessionRole() ?? '';

if ($userRoleRaw === ROLE_ADMIN || in_array($idUserRaw, ['admin', 'ryan', 'root', 'superadmin', 'administrator'])) {
    $greetingName = 'Admin Utama';
} else {
    $greetingName = sessionNama() ?: $idUserRaw;
    $stD = $pdo->prepare("SELECT nm_dokter FROM dokter WHERE kd_dokter = ? LIMIT 1");
    $stD->execute([$idUserRaw]);
    $dName = $stD->fetchColumn();
    if ($dName) {
        $greetingName = $dName;
    } else {
        $stP = $pdo->prepare("SELECT nama FROM pegawai WHERE nik = ? LIMIT 1");
        $stP->execute([$idUserRaw]);
        $pName = $stP->fetchColumn();
        if ($pName) $greetingName = $pName;
    }
}

$halamanAktif = 'dashboard';
$judulHalaman = 'Dashboard Operasional';
require __DIR__ . '/lib/layout_header.php';
?>

<style>
/* Stat Widgets Grid */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}
@media (max-width: 992px) {
    .stat-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 576px) {
    .stat-grid { grid-template-columns: 1fr; }
}

.stat-card {
    border-radius: 10px;
    padding: 16px 18px;
    background: #fff;
    border: 1.5px solid var(--color-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}

.stat-card.blue { background: #F0F7FF; border-color: #BEE3F8; }
.stat-card.yellow { background: #FFFDF0; border-color: #FEEBC8; }
.stat-card.cyan { background: #EBF8FF; border-color: #BEE3F8; }
.stat-card.green { background: #F0FDF4; border-color: #C6F6D5; }

.stat-number {
    font-size: 26px;
    font-weight: 800;
    line-height: 1.1;
    margin-top: 4px;
}
.stat-card.blue .stat-number { color: #2B6CB0; }
.stat-card.yellow .stat-number { color: #DD6B20; }
.stat-card.cyan .stat-number { color: #319795; }
.stat-card.green .stat-number { color: #2F855A; }

.stat-label {
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--color-text-mute);
}

.stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}
.stat-card.blue .stat-icon { background: #EBF8FF; color: #2B6CB0; }
.stat-card.yellow .stat-icon { background: #FEFCBF; color: #DD6B20; }
.stat-card.cyan .stat-icon { background: #E6FFFA; color: #319795; }
.stat-card.green .stat-icon { background: #DCFCE7; color: #2F855A; }

/* Table styling */
.dash-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.dash-table th {
    background: var(--color-primary);
    color: #fff;
    padding: 9px 12px;
    text-align: left;
    font-size: 12px;
    white-space: nowrap;
}
.dash-table td {
    border-bottom: 1px solid var(--color-border);
    padding: 8px 12px;
    vertical-align: middle;
}
.dash-table tr:hover td {
    background: #FDF6F8;
}
.btn-action-group {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}
.btn-act {
    padding: 3px 8px;
    font-size: 11.5px;
    border-radius: 4px;
    text-decoration: none;
    font-weight: 600;
    transition: all .15s;
}
</style>

<!-- Header Greeting Bar -->
<div class="card" style="margin-bottom:16px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div>
            <p class="card-title" style="margin:0 0 4px; font-size:17px;">Selamat Datang, <?= htmlspecialchars($greetingName) ?> 👋</p>
            <p class="text-muted" style="margin:0; font-size:13px;">Ringkasan operasional pelayanan medis &amp; kunjungan pasien hari ini (<?= date('d-m-Y') ?>).</p>
        </div>
        <div>
            <span class="badge badge-success" style="font-size:12px; padding:6px 12px;">Hari Ini: <?= date('d F Y') ?></span>
        </div>
    </div>
</div>

<!-- Section 1: Widget Statistik Kunjungan Hari Ini -->
<div class="stat-grid">
    <div class="stat-card blue">
        <div>
            <div class="stat-label">Total Kunjungan</div>
            <div class="stat-number"><?= number_format($total_kunjungan) ?></div>
            <small style="font-size:11px; color:#4A5568;">Pasien terdaftar hari ini</small>
        </div>
        <div class="stat-icon">👥</div>
    </div>

    <div class="stat-card yellow">
        <div>
            <div class="stat-label">Pasien Menunggu</div>
            <div class="stat-number"><?= number_format($pasien_menunggu) ?></div>
            <small style="font-size:11px; color:#4A5568;">Belum diperiksa</small>
        </div>
        <div class="stat-icon">⏳</div>
    </div>

    <div class="stat-card cyan">
        <div>
            <div class="stat-label">Sedang Diperiksa</div>
            <div class="stat-number"><?= number_format($sedang_diperiksa) ?></div>
            <small style="font-size:11px; color:#4A5568;">Dalam pelayanan</small>
        </div>
        <div class="stat-icon">🩺</div>
    </div>

    <div class="stat-card green">
        <div>
            <div class="stat-label">Pemeriksaan Selesai</div>
            <div class="stat-number"><?= number_format($pasien_selesai) ?></div>
            <small style="font-size:11px; color:#4A5568;">Selesai / Lunas</small>
        </div>
        <div class="stat-icon">✅</div>
    </div>
</div>

<!-- Pasien Aktif Banner (jika dipilih) -->
<?php if ($activePasien): ?>
<div class="card" style="border-left: 4px solid var(--color-primary); background: #FAF5F7; margin-bottom:16px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <span class="badge badge-success" style="font-size:11px; margin-bottom:4px;">Pasien Aktif Terpilih</span>
            <p style="margin:4px 0 0; font-size:15px; font-weight:700;">
                <?= htmlspecialchars($activePasien['nm_pasien']) ?>
                <span style="font-size:13px; font-weight:400; color:var(--color-text-mute); font-family:monospace;">(No. RM: <?= htmlspecialchars($activePasien['no_rkm_medis']) ?>)</span>
            </p>
            <p style="margin:2px 0 0; font-size:12.5px; color:var(--color-text-mute);">
                No. Rawat: <code><?= htmlspecialchars($activePasien['no_rawat']) ?></code> &bull; Poli: <?= htmlspecialchars($activePasien['nm_poli'] ?? '-') ?> &bull; Dokter: <?= htmlspecialchars($activePasien['nm_dokter'] ?? '-') ?>
            </p>
        </div>
        <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
            <a href="asesmen/pilih.php?no_rawat=<?= urlencode($activePasien['no_rawat']) ?>" class="btn btn-primary" style="padding:5px 12px; font-size:12px; text-decoration:none;">Asesmen</a>
            <a href="usg/kandungan.php?no_rawat=<?= urlencode($activePasien['no_rawat']) ?>" class="btn btn-outline" style="padding:5px 12px; font-size:12px; text-decoration:none; border-color:var(--color-primary); color:var(--color-primary);">USG</a>
            <a href="tindakan/input.php?no_rawat=<?= urlencode($activePasien['no_rawat']) ?>" class="btn btn-outline" style="padding:5px 12px; font-size:12px; text-decoration:none; border-color:var(--color-primary); color:var(--color-primary);">Tindakan</a>
            <a href="resep/tulis.php?no_rawat=<?= urlencode($activePasien['no_rawat']) ?>" class="btn btn-outline" style="padding:5px 12px; font-size:12px; text-decoration:none; border-color:var(--color-primary); color:var(--color-primary);">Resep</a>
            <a href="billing/tagihan.php?no_rawat=<?= urlencode($activePasien['no_rawat']) ?>" class="btn btn-outline" style="padding:5px 12px; font-size:12px; text-decoration:none; border-color:var(--color-primary); color:var(--color-primary);">Billing</a>
            <a href="dashboard.php?reset_pasien=1" class="btn btn-outline" style="padding:5px 12px; font-size:12px; text-decoration:none; border-color:#d32f2f; color:#d32f2f;">Reset Pasien</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Section 2: Tabel Antrean Kunjungan Hari Ini -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
        <p class="card-title" style="margin:0;">Antrean Kunjungan Hari Ini</p>
        <span class="text-muted" style="font-size:12.5px;">Total: <strong><?= count($kunjunganHariIni) ?></strong> pasien</span>
    </div>

    <?php if (empty($kunjunganHariIni)): ?>
        <p class="text-muted" style="text-align:center; padding:24px 0;">Belum ada antrean kunjungan tercatat hari ini.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="dash-table">
            <thead>
                <tr>
                    <th style="width:70px; text-align:center;">No. Antrean</th>
                    <th style="width:100px;">No. RM</th>
                    <th>Nama Pasien</th>
                    <th>Layanan / Poli</th>
                    <th>Dokter Pemeriksa</th>
                    <th style="width:110px; text-align:center;">Status</th>
                    <th style="width:280px; text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kunjunganHariIni as $r): ?>
                <?php 
                $nrEnc = urlencode($r['no_rawat']);
                $nrJs  = htmlspecialchars($r['no_rawat'], ENT_QUOTES);
                ?>
                <tr>
                    <td style="text-align:center;">
                        <span style="font-weight:700; font-size:13px; color:var(--color-primary);">
                            <?= htmlspecialchars($r['no_reg'] ?? '-') ?>
                        </span>
                    </td>
                    <td><code><?= htmlspecialchars($r['no_rkm_medis'] ?? '') ?></code></td>
                    <td>
                        <a href="asesmen/pilih.php?no_rawat=<?= $nrEnc ?>"
                           onclick="pilihPasien(event, '<?= $nrJs ?>', 'asesmen/pilih.php?no_rawat=<?= $nrEnc ?>')"
                           class="patient-link"
                           title="Klik untuk memilih pasien dan buka menu asesmen">
                            <?= htmlspecialchars($r['nm_pasien']) ?>
                        </a>
                        <br><small class="text-muted" style="font-size:11px;">No.Rawat: <?= htmlspecialchars($r['no_rawat']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($r['nm_poli']) ?></td>
                    <td><?= htmlspecialchars($r['nm_dokter'] ?? '-') ?></td>
                    <td style="text-align:center;">
                        <?php
                        $sttsVal = $r['stts'];
                        $badgeClass = match ($sttsVal) {
                            'Sudah', 'Sedang Diperiksa' => 'badge-info',
                            'Dirawat', 'Lunas', 'Sudah Bayar', 'Selesai' => 'badge-success',
                            'Batal' => 'badge-danger',
                            default => 'badge-warning',
                        };
                        ?>
                        <span class="badge <?= $badgeClass ?>" style="font-size:11px; padding:3px 8px;">
                            <?= htmlspecialchars($sttsVal) ?>
                        </span>
                    </td>
                    <td>
                        <div class="btn-action-group" style="justify-content:center;">
                            <a href="asesmen/pilih.php?no_rawat=<?= $nrEnc ?>"
                               onclick="pilihPasien(event, '<?= $nrJs ?>', 'asesmen/pilih.php?no_rawat=<?= $nrEnc ?>')"
                               class="btn btn-primary btn-act">Asesmen</a>
                            <a href="usg/index.php?no_rawat=<?= $nrEnc ?>"
                               onclick="pilihPasien(event, '<?= $nrJs ?>', 'usg/index.php?no_rawat=<?= $nrEnc ?>')"
                               class="btn btn-outline btn-act">USG</a>
                            <a href="tindakan/input.php?no_rawat=<?= $nrEnc ?>"
                               onclick="pilihPasien(event, '<?= $nrJs ?>', 'tindakan/input.php?no_rawat=<?= $nrEnc ?>')"
                               class="btn btn-outline btn-act">Tindakan</a>
                            <a href="resep/tulis.php?no_rawat=<?= $nrEnc ?>"
                               onclick="pilihPasien(event, '<?= $nrJs ?>', 'resep/tulis.php?no_rawat=<?= $nrEnc ?>')"
                               class="btn btn-outline btn-act">Resep</a>
                            <a href="billing/tagihan.php?no_rawat=<?= $nrEnc ?>"
                               onclick="pilihPasien(event, '<?= $nrJs ?>', 'billing/tagihan.php?no_rawat=<?= $nrEnc ?>')"
                               class="btn btn-outline btn-act">Billing</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<script>
function pilihPasien(evt, noRawat, targetUrl) {
    if (evt && evt.preventDefault) evt.preventDefault();
    fetch('update-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'no_rawat=' + encodeURIComponent(noRawat) + '&stts=Sudah'
    })
    .then(function() { window.location.href = targetUrl; })
    .catch(function() { window.location.href = targetUrl; });
}
</script>

<?php require __DIR__ . '/lib/layout_footer.php'; ?>
