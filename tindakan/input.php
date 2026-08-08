<?php
/**
 * tindakan/input.php
 * -----------------------------------------------------------------
 * Form input tindakan medis per kunjungan (rawat jalan).
 * Mendukung tiga tabel untuk sesuai dengan sistem Java:
 *   - rawat_jl_dr    : tindakan oleh dokter saja
 *   - rawat_jl_pr    : tindakan oleh perawat/petugas saja
 *   - rawat_jl_drpr  : tindakan bersama dokter + perawat
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';

wajibLogin();

$pdo = getKoneksi();

$noRawat = trim($_GET['no_rawat'] ?? $_POST['no_rawat'] ?? '');
if ($noRawat === '') {
    $noRawat = $_SESSION['last_no_rawat'] ?? '';
}
if ($noRawat === '') {
    header('Location: index.php');
    exit;
}

// Simpan ke session untuk sidebar
$_SESSION['last_no_rawat'] = $noRawat;

// Ambil data kunjungan (termasuk kd_pj dan kd_poli untuk filter tarif)
$stmt = $pdo->prepare(
    "SELECT r.no_rawat, r.tgl_registrasi, r.kd_dokter, r.kd_pj, r.kd_poli,
            p.nm_pasien, p.no_rkm_medis,
            dok.nm_dokter, dok.kd_dokter as kd_dok
     FROM reg_periksa r
     JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
     LEFT JOIN dokter dok ON r.kd_dokter = dok.kd_dokter
     WHERE r.no_rawat = ?"
);
$stmt->execute([$noRawat]);
$kunjungan = $stmt->fetch();
if (!$kunjungan) {
    header('Location: index.php');
    exit;
}

$error  = '';
$sukses = $_GET['sukses'] ?? '';

$sudahBayar = isSudahBayar($noRawat, $pdo);

// === FITUR HAPUS TINDAKAN ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    if ($sudahBayar) {
        $error = 'Peringatan: Tidak dapat menghapus tindakan karena pasien sudah melakukan pembayaran (LUNAS).';
    } else {
    $type = $_POST['type'] ?? '';
    $kdJenisPrw = $_POST['kd_jenis_prw'] ?? '';
    $tgl = $_POST['tgl'] ?? '';
    $jam = $_POST['jam'] ?? '';

    try {
        if ($type === 'dr') {
            $kdDok = $_POST['kd_dokter'] ?? '';
            $pdo->prepare("DELETE FROM rawat_jl_dr WHERE no_rawat=? AND kd_jenis_prw=? AND kd_dokter=? AND tgl_perawatan=? AND jam_rawat=?")->execute([$noRawat, $kdJenisPrw, $kdDok, $tgl, $jam]);
            $sukses = 'Tindakan Dokter berhasil dihapus.';
        } elseif ($type === 'pr') {
            $nP = $_POST['nip'] ?? '';
            $pdo->prepare("DELETE FROM rawat_jl_pr WHERE no_rawat=? AND kd_jenis_prw=? AND nip=? AND tgl_perawatan=? AND jam_rawat=?")->execute([$noRawat, $kdJenisPrw, $nP, $tgl, $jam]);
            $sukses = 'Tindakan Perawat berhasil dihapus.';
        } elseif ($type === 'drpr') {
            $kdDok = $_POST['kd_dokter'] ?? '';
            $nP = $_POST['nip'] ?? '';
            $pdo->prepare("DELETE FROM rawat_jl_drpr WHERE no_rawat=? AND kd_jenis_prw=? AND kd_dokter=? AND nip=? AND tgl_perawatan=? AND jam_rawat=?")->execute([$noRawat, $kdJenisPrw, $kdDok, $nP, $tgl, $jam]);
            $sukses = 'Tindakan Bersama berhasil dihapus.';
        }
        header("Location: input.php?no_rawat=" . urlencode($noRawat) . "&sukses=" . urlencode($sukses));
        exit;
    } catch (Throwable $e) {
        $error = 'Gagal menghapus tindakan: ' . $e->getMessage();
    }
    }
}

// === FITUR SIMPAN TINDAKAN ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan') {
    if ($sudahBayar) {
        $error = 'Peringatan: Tidak dapat menambah tindakan karena pasien sudah melakukan pembayaran (LUNAS).';
    } else {
    $jenisTindakan = $_POST['jenis_tindakan'] ?? 'dr'; 
    $kdJenisPrw    = trim($_POST['kd_jenis_prw'] ?? '');
    $kdDokter      = trim($_POST['kd_dokter'] ?? '');
    $nip           = trim($_POST['nip'] ?? '');
    $tglPerawatan  = $_POST['tgl_perawatan'] ?? date('Y-m-d');
    $jamRawat      = $_POST['jam_rawat'] ?? date('H:i');
    if (strlen($jamRawat) === 5) $jamRawat .= ':00';

    if ($kdJenisPrw === '') {
        $error = 'Jenis Tindakan wajib dipilih.';
    } elseif ($jenisTindakan === 'dr' && $kdDokter === '') {
        $error = 'Dokter wajib dipilih untuk Tindakan Dokter.';
    } elseif ($jenisTindakan === 'pr' && $nip === '') {
        $error = 'Perawat wajib dipilih untuk Tindakan Perawat.';
    } elseif ($jenisTindakan === 'drpr' && ($kdDokter === '' || $nip === '')) {
        $error = 'Dokter dan Perawat wajib dipilih untuk Tindakan Bersama.';
    } else {
        $stmtTarif = $pdo->prepare("SELECT * FROM jns_perawatan WHERE kd_jenis_prw = ?");
        $stmtTarif->execute([$kdJenisPrw]);
        $tarif = $stmtTarif->fetch();

        if (!$tarif) {
            $error = 'Jenis tindakan tidak ditemukan.';
        } else {
            try {
                if ($jenisTindakan === 'dr') {
                    $biayaRawat = $tarif['total_byrdr'] ?? ($tarif['tarif_tindakandr'] + $tarif['material'] + $tarif['bhp']);
                    $pdo->prepare("INSERT INTO rawat_jl_dr (no_rawat, kd_jenis_prw, kd_dokter, tgl_perawatan, jam_rawat, material, bhp, tarif_tindakandr, kso, menejemen, biaya_rawat, stts_bayar) VALUES (?,?,?,?,?,?,?,?,?,?,?,'Belum')")
                        ->execute([$noRawat, $kdJenisPrw, $kdDokter, $tglPerawatan, $jamRawat, (float)($tarif['material']??0), (float)($tarif['bhp']??0), (float)($tarif['tarif_tindakandr']??0), (float)($tarif['kso']??0), (float)($tarif['menejemen']??0), (float)$biayaRawat]);
                    $sukses = 'Tindakan Dokter berhasil ditambahkan.';
                } elseif ($jenisTindakan === 'pr') {
                    $biayaRawat = $tarif['total_byrpr'] ?? ($tarif['tarif_tindakanpr'] + $tarif['material'] + $tarif['bhp']);
                    $pdo->prepare("INSERT INTO rawat_jl_pr (no_rawat, kd_jenis_prw, nip, tgl_perawatan, jam_rawat, material, bhp, tarif_tindakanpr, kso, menejemen, biaya_rawat, stts_bayar) VALUES (?,?,?,?,?,?,?,?,?,?,?,'Belum')")
                        ->execute([$noRawat, $kdJenisPrw, $nip, $tglPerawatan, $jamRawat, (float)($tarif['material']??0), (float)($tarif['bhp']??0), (float)($tarif['tarif_tindakanpr']??0), (float)($tarif['kso']??0), (float)($tarif['menejemen']??0), (float)$biayaRawat]);
                    $sukses = 'Tindakan Perawat berhasil ditambahkan.';
                } elseif ($jenisTindakan === 'drpr') {
                    $biayaRawat = $tarif['total_byrdrpr'] ?? ($tarif['tarif_tindakandr'] + $tarif['tarif_tindakanpr'] + $tarif['material'] + $tarif['bhp']);
                    $pdo->prepare("INSERT INTO rawat_jl_drpr (no_rawat, kd_jenis_prw, kd_dokter, nip, tgl_perawatan, jam_rawat, material, bhp, tarif_tindakandr, tarif_tindakanpr, kso, menejemen, biaya_rawat, stts_bayar) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'Belum')")
                        ->execute([$noRawat, $kdJenisPrw, $kdDokter, $nip, $tglPerawatan, $jamRawat, (float)($tarif['material']??0), (float)($tarif['bhp']??0), (float)($tarif['tarif_tindakandr']??0), (float)($tarif['tarif_tindakanpr']??0), (float)($tarif['kso']??0), (float)($tarif['menejemen']??0), (float)$biayaRawat]);
                    $sukses = 'Tindakan Bersama berhasil ditambahkan.';
                }
                header("Location: input.php?no_rawat=" . urlencode($noRawat) . "&sukses=" . urlencode($sukses));
                exit;
            } catch (Throwable $e) {
                // Ignore constraint violation (duplicate) to show friendly error without throwing
                if (strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), '1062') !== false) {
                    $error = 'Tindakan ini sudah tercatat pada waktu yang sama. Pilih waktu berbeda.';
                } else {
                    $error = 'Gagal menyimpan tindakan: ' . $e->getMessage();
                }
            }
        }
        }
    }
}

// 1. Fetch riwayat Tindakan Dokter
$stmtDr = $pdo->prepare(
    "SELECT d.kd_jenis_prw, j.nm_perawatan, d.kd_dokter, dok.nm_dokter,
            d.tgl_perawatan, d.jam_rawat, d.biaya_rawat, d.stts_bayar
     FROM rawat_jl_dr d JOIN jns_perawatan j ON d.kd_jenis_prw = j.kd_jenis_prw
     JOIN dokter dok ON d.kd_dokter = dok.kd_dokter WHERE d.no_rawat = ?
     ORDER BY d.tgl_perawatan DESC, d.jam_rawat DESC"
);
$stmtDr->execute([$noRawat]);
$tindakanDr = $stmtDr->fetchAll();

// 2. Fetch riwayat Tindakan Perawat
$stmtPr = $pdo->prepare(
    "SELECT d.kd_jenis_prw, j.nm_perawatan, d.nip, ptg.nama as nm_petugas,
            d.tgl_perawatan, d.jam_rawat, d.biaya_rawat, d.stts_bayar
     FROM rawat_jl_pr d JOIN jns_perawatan j ON d.kd_jenis_prw = j.kd_jenis_prw
     JOIN petugas ptg ON d.nip = ptg.nip WHERE d.no_rawat = ?
     ORDER BY d.tgl_perawatan DESC, d.jam_rawat DESC"
);
$stmtPr->execute([$noRawat]);
$tindakanPr = $stmtPr->fetchAll();

// 3. Fetch riwayat Tindakan Bersama
$stmtDrPr = $pdo->prepare(
    "SELECT d.kd_jenis_prw, j.nm_perawatan, d.kd_dokter, dok.nm_dokter,
            d.nip, ptg.nama as nm_petugas, d.tgl_perawatan, d.jam_rawat, d.biaya_rawat, d.stts_bayar
     FROM rawat_jl_drpr d JOIN jns_perawatan j ON d.kd_jenis_prw = j.kd_jenis_prw
     JOIN dokter dok ON d.kd_dokter = dok.kd_dokter JOIN petugas ptg ON d.nip = ptg.nip
     WHERE d.no_rawat = ? ORDER BY d.tgl_perawatan DESC, d.jam_rawat DESC"
);
$stmtDrPr->execute([$noRawat]);
$tindakanDrPr = $stmtDrPr->fetchAll();

// Data Master: jns_perawatan (Filtered by kd_pj and kd_poli seperti di Java)
$stmtJns = $pdo->prepare(
    "SELECT kd_jenis_prw, nm_perawatan, total_byrdr, total_byrpr, total_byrdrpr
     FROM jns_perawatan
     WHERE status = '1' 
       AND (kd_pj = ? OR kd_pj = '-')
       AND (kd_poli = ? OR kd_poli = '-')
     ORDER BY nm_perawatan ASC"
);
$stmtJns->execute([$kunjungan['kd_pj'], $kunjungan['kd_poli']]);
$jenisPerawatan = $stmtJns->fetchAll();

// Data Dokter & Petugas aktif
$dokters = $pdo->query("SELECT kd_dokter, nm_dokter FROM dokter WHERE status='1' ORDER BY nm_dokter ASC")->fetchAll();
$petugases = $pdo->query("SELECT nip, nama FROM petugas WHERE status='1' ORDER BY nama ASC")->fetchAll();

$halamanAktif = 'tindakan';
$judulHalaman = 'Input Tindakan Medis';
$baseAsset    = '../';
require __DIR__ . '/../lib/layout_header.php';

// Prepare data for JS
$tarifJson = json_encode(array_column($jenisPerawatan, null, 'kd_jenis_prw'));
?>
<style>
.tindakan-table th { background: var(--color-primary); color:#fff; }
.tindakan-table td, .tindakan-table th { padding: 7px 10px; font-size:13px; }
.tindakan-table tr:hover td { background: #fdf6f8; }
.tab-btn { padding:9px 16px; background:none; border:none; font-weight:600; font-size:13px; color:var(--color-text-mute); cursor:pointer; border-bottom:3px solid transparent; transition:all .2s; }
.tab-btn.active { color:var(--color-primary); border-bottom-color:var(--color-primary); }
.tabs { display:flex; border-bottom:2px solid var(--color-border); margin-bottom:16px; }
.tab-content { display:none; }
.tab-content.active { display:block; }
</style>

<div style="margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; font-size:13px;">
    <div>
        <a href="../asesmen/pilih.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-back">← Kembali ke Menu Asesmen</a>
        <span class="text-muted" style="margin-left:8px;">&bull; <code><?= htmlspecialchars($noRawat) ?></code> &bull; <strong><?= htmlspecialchars($kunjungan['nm_pasien']) ?></strong></span>
    </div>
    <div style="display:flex; gap:6px;">
        <a href="../asesmen/index.php" class="btn btn-outline" style="font-size:12px; padding:4px 10px; text-decoration:none;">Daftar Pasien</a>
        <a href="../dashboard.php" class="btn btn-outline" style="font-size:12px; padding:4px 10px; text-decoration:none;">Dashboard</a>
    </div>
</div>

<?php if ($sukses): ?>
    <div class="alert alert-success" id="alert-simpan-sukses">✔ <?= htmlspecialchars($sukses) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Riwayat Tindakan -->
<?php if ($tindakanDr || $tindakanPr || $tindakanDrPr): ?>
<div class="card">
    <p class="card-title">Riwayat Tindakan Kunjungan Ini</p>
    <div class="tabs">
        <button class="tab-btn active" onclick="sw(event,'rt-dr')">Tindakan Dokter (<?= count($tindakanDr) ?>)</button>
        <button class="tab-btn" onclick="sw(event,'rt-pr')">Tindakan Perawat (<?= count($tindakanPr) ?>)</button>
        <button class="tab-btn" onclick="sw(event,'rt-drpr')">Tindakan Bersama (<?= count($tindakanDrPr) ?>)</button>
    </div>
    <small class="text-muted" style="display:block;margin-bottom:10px;font-size:11px;">* Status Bayar = apakah biaya tindakan ini sudah dibayarkan ke billing (Sudah/Belum/Suspen). Dikelola oleh modul Billing.</small>

    <div id="rt-dr" class="tab-content active">
        <?php if ($tindakanDr): ?>
        <div style="overflow-x:auto;">
        <table class="table tindakan-table">
            <thead><tr>
                <th>Tindakan</th><th>Petugas Pelaksana</th><th>Tanggal &amp; Jam</th>
                <th style="text-align:right;">Biaya</th><th>Status Bayar *</th><th>Aksi</th>
            </tr></thead>
            <tbody>
            <?php foreach ($tindakanDr as $t): ?>
            <tr>
                <td><?= htmlspecialchars($t['nm_perawatan']) ?></td>
                <td><small>Dokter: <?= htmlspecialchars($t['nm_dokter']) ?></small></td>
                <td><?= date('d-m-Y', strtotime($t['tgl_perawatan'])) ?> <br/><small><?= htmlspecialchars($t['jam_rawat']) ?></small></td>
                <td style="text-align:right;font-family:monospace;">Rp <?= number_format((float)$t['biaya_rawat'], 0, ',', '.') ?></td>
                <td><span class="badge <?= $t['stts_bayar']==='Sudah'?'badge-success':'badge-warning' ?>"><?= htmlspecialchars($t['stts_bayar'] ?? 'Belum') ?></span></td>
                <td>
                    <?php if (!$sudahBayar && $t['stts_bayar'] !== 'Sudah'): ?>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Hapus tindakan ini?')">
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="type" value="dr">
                        <input type="hidden" name="kd_jenis_prw" value="<?= htmlspecialchars($t['kd_jenis_prw']) ?>">
                        <input type="hidden" name="kd_dokter" value="<?= htmlspecialchars($t['kd_dokter']) ?>">
                        <input type="hidden" name="tgl" value="<?= htmlspecialchars($t['tgl_perawatan']) ?>">
                        <input type="hidden" name="jam" value="<?= htmlspecialchars($t['jam_rawat']) ?>">
                        <button type="submit" class="btn btn-outline" style="font-size:12px;padding:3px 8px;color:#D62839;border-color:#D62839;">Hapus</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
            <p class="text-muted">Belum ada tindakan dokter tercatat.</p>
        <?php endif; ?>
    </div>

    <div id="rt-pr" class="tab-content">
        <?php if ($tindakanPr): ?>
        <div style="overflow-x:auto;">
        <table class="table tindakan-table">
            <thead><tr>
                <th>Tindakan</th><th>Petugas Pelaksana</th><th>Tanggal &amp; Jam</th>
                <th style="text-align:right;">Biaya</th><th>Status Bayar *</th><th>Aksi</th>
            </tr></thead>
            <tbody>
            <?php foreach ($tindakanPr as $t): ?>
            <tr>
                <td><?= htmlspecialchars($t['nm_perawatan']) ?></td>
                <td><small>Perawat: <?= htmlspecialchars($t['nm_petugas']) ?></small></td>
                <td><?= date('d-m-Y', strtotime($t['tgl_perawatan'])) ?> <br/><small><?= htmlspecialchars($t['jam_rawat']) ?></small></td>
                <td style="text-align:right;font-family:monospace;">Rp <?= number_format((float)$t['biaya_rawat'], 0, ',', '.') ?></td>
                <td><span class="badge <?= $t['stts_bayar']==='Sudah'?'badge-success':'badge-warning' ?>"><?= htmlspecialchars($t['stts_bayar'] ?? 'Belum') ?></span></td>
                <td>
                    <?php if (!$sudahBayar && $t['stts_bayar'] !== 'Sudah'): ?>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Hapus tindakan ini?')">
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="type" value="pr">
                        <input type="hidden" name="kd_jenis_prw" value="<?= htmlspecialchars($t['kd_jenis_prw']) ?>">
                        <input type="hidden" name="nip" value="<?= htmlspecialchars($t['nip']) ?>">
                        <input type="hidden" name="tgl" value="<?= htmlspecialchars($t['tgl_perawatan']) ?>">
                        <input type="hidden" name="jam" value="<?= htmlspecialchars($t['jam_rawat']) ?>">
                        <button type="submit" class="btn btn-outline" style="font-size:12px;padding:3px 8px;color:#D62839;border-color:#D62839;">Hapus</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
            <p class="text-muted">Belum ada tindakan perawat tercatat.</p>
        <?php endif; ?>
    </div>

    <div id="rt-drpr" class="tab-content">
        <?php if ($tindakanDrPr): ?>
        <div style="overflow-x:auto;">
        <table class="table tindakan-table">
            <thead><tr>
                <th>Tindakan</th><th>Petugas Pelaksana</th><th>Tanggal &amp; Jam</th>
                <th style="text-align:right;">Biaya</th><th>Status Bayar *</th><th>Aksi</th>
            </tr></thead>
            <tbody>
            <?php foreach ($tindakanDrPr as $t): ?>
            <tr>
                <td><?= htmlspecialchars($t['nm_perawatan']) ?></td>
                <td><small>Dokter: <?= htmlspecialchars($t['nm_dokter']) ?><br/>Perawat: <?= htmlspecialchars($t['nm_petugas']) ?></small></td>
                <td><?= date('d-m-Y', strtotime($t['tgl_perawatan'])) ?> <br/><small><?= htmlspecialchars($t['jam_rawat']) ?></small></td>
                <td style="text-align:right;font-family:monospace;">Rp <?= number_format((float)$t['biaya_rawat'], 0, ',', '.') ?></td>
                <td><span class="badge <?= $t['stts_bayar']==='Sudah'?'badge-success':'badge-warning' ?>"><?= htmlspecialchars($t['stts_bayar'] ?? 'Belum') ?></span></td>
                <td>
                    <?php if (!$sudahBayar && $t['stts_bayar'] !== 'Sudah'): ?>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Hapus tindakan ini?')">
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="type" value="drpr">
                        <input type="hidden" name="kd_jenis_prw" value="<?= htmlspecialchars($t['kd_jenis_prw']) ?>">
                        <input type="hidden" name="kd_dokter" value="<?= htmlspecialchars($t['kd_dokter']) ?>">
                        <input type="hidden" name="nip" value="<?= htmlspecialchars($t['nip']) ?>">
                        <input type="hidden" name="tgl" value="<?= htmlspecialchars($t['tgl_perawatan']) ?>">
                        <input type="hidden" name="jam" value="<?= htmlspecialchars($t['jam_rawat']) ?>">
                        <button type="submit" class="btn btn-outline" style="font-size:12px;padding:3px 8px;color:#D62839;border-color:#D62839;">Hapus</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
            <p class="text-muted">Belum ada tindakan bersama tercatat.</p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Form Tambah Tindakan -->
<div class="card">
    <p class="card-title">Tambah Tindakan</p>

    <?php if ($sudahBayar): ?>
        <div class="alert alert-danger" style="margin-bottom:15px; font-size:12.5px;">
            🔒 <strong>Data dikunci:</strong> Pembayaran telah diselesaikan. Anda tidak dapat menambah atau menghapus tindakan.
        </div>
    <?php endif; ?>

    <form method="post" id="formTindakan">
        <fieldset <?= $sudahBayar ? 'disabled' : '' ?> style="border:0; padding:0; margin:0;">
        <input type="hidden" name="aksi" value="simpan">
        
        <div style="margin-bottom:14px;">
            <label style="font-weight:600;font-size:12.5px;">Kategori Pengerjaan *</label>
            <div style="display:flex;gap:16px;margin-top:6px;">
                <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;">
                    <input type="radio" name="jenis_tindakan" value="dr" checked onchange="updateFormDisplay()">
                    Tindakan Dokter Saja
                </label>
                <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;">
                    <input type="radio" name="jenis_tindakan" value="pr" onchange="updateFormDisplay()">
                    Tindakan Perawat Saja
                </label>
                <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;">
                    <input type="radio" name="jenis_tindakan" value="drpr" onchange="updateFormDisplay()">
                    Tindakan Bersama (Dr+Pr)
                </label>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div>
                <label for="kd_jenis_prw">Jenis Tindakan / Perawatan *</label>
                <select id="kd_jenis_prw" name="kd_jenis_prw" required onchange="updateTarif()">
                    <option value="">-- Pilih Tindakan --</option>
                    <?php foreach ($jenisPerawatan as $jp): ?>
                    <option value="<?= htmlspecialchars($jp['kd_jenis_prw']) ?>"
                            data-dr="<?= (float)($jp['total_byrdr'] ?? 0) ?>"
                            data-pr="<?= (float)($jp['total_byrpr'] ?? 0) ?>"
                            data-drpr="<?= (float)($jp['total_byrdrpr'] ?? 0) ?>">
                        <?= htmlspecialchars($jp['nm_perawatan']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted" id="tarif-hint" style="display:block; margin-top:4px; font-size:11px;"></small>
            </div>
            
            <div id="container-dokter">
                <label for="kd_dokter">Dokter Pelaksana *</label>
                <select id="kd_dokter" name="kd_dokter">
                    <option value="">-- Pilih Dokter --</option>
                    <?php foreach ($dokters as $d): ?>
                    <option value="<?= htmlspecialchars($d['kd_dokter']) ?>"
                        <?= (($kunjungan['kd_dok'] ?? '') === $d['kd_dokter']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['nm_dokter']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div id="container-perawat" style="display:none; margin-top:14px;">
            <label for="nip">Perawat/Petugas Pelaksana *</label>
            <select id="nip" name="nip" style="width: 50%;">
                <option value="">-- Pilih Perawat/Petugas --</option>
                <?php foreach ($petugases as $p): ?>
                <option value="<?= htmlspecialchars($p['nip']) ?>"
                    <?= (($_SESSION['nip'] ?? '') === $p['nip']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['nama']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:14px;">
            <div>
                <label for="tgl_perawatan">Tanggal Tindakan</label>
                <input type="date" id="tgl_perawatan" name="tgl_perawatan" value="<?= date('Y-m-d') ?>">
            </div>
            <div>
                <label for="jam_rawat">Jam Tindakan</label>
                <input type="time" id="jam_rawat" name="jam_rawat" value="<?= date('H:i') ?>">
            </div>
            <div>
                <label>Estimasi Biaya</label>
                <input type="text" id="estimasi_biaya" readonly
                       style="background:#f5f5f5;font-weight:600;color:var(--color-primary);"
                       placeholder="Otomatis dari tarif">
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:18px;">
            <button type="submit" class="btn btn-primary">Simpan Tindakan</button>
            <a href="../asesmen/pilih.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-outline">Kembali</a>
        </div>
        </fieldset>
    </form>
</div>

<script>
const tarifData = <?= $tarifJson ?>;

function updateFormDisplay() {
    const jenis = document.querySelector('input[name=jenis_tindakan]:checked').value;
    const contDokter = document.getElementById('container-dokter');
    const contPerawat = document.getElementById('container-perawat');
    const inputDokter = document.getElementById('kd_dokter');
    const inputPerawat = document.getElementById('nip');
    
    if (jenis === 'dr') {
        contDokter.style.display = 'block';
        contPerawat.style.display = 'none';
        inputDokter.required = true;
        inputPerawat.required = false;
    } else if (jenis === 'pr') {
        contDokter.style.display = 'none';
        contPerawat.style.display = 'block';
        inputDokter.required = false;
        inputPerawat.required = true;
    } else if (jenis === 'drpr') {
        contDokter.style.display = 'block';
        contPerawat.style.display = 'block';
        inputDokter.required = true;
        inputPerawat.required = true;
    }
    
    updateTarif(); // Re-calculate based on type
    filterSelectOptions(jenis);
}

// Option filtering to only show valid items for the selected category based on tarif > 0
function filterSelectOptions(jenis) {
    const select = document.getElementById('kd_jenis_prw');
    const options = select.options;
    
    let hasValidSelection = false;
    
    for (let i = 1; i < options.length; i++) {
        const opt = options[i];
        const valDr = parseFloat(opt.getAttribute('data-dr') || 0);
        const valPr = parseFloat(opt.getAttribute('data-pr') || 0);
        const valDrPr = parseFloat(opt.getAttribute('data-drpr') || 0);
        
        let valid = false;
        if (jenis === 'dr' && valDr > 0) valid = true;
        if (jenis === 'pr' && valPr > 0) valid = true;
        if (jenis === 'drpr' && valDrPr > 0) valid = true;
        
        if (valid) {
            opt.style.display = '';
            opt.disabled = false;
            if (opt.selected) hasValidSelection = true;
        } else {
            opt.style.display = 'none';
            opt.disabled = true;
            if (opt.selected) {
                opt.selected = false; 
            }
        }
    }
    
    if (!hasValidSelection && select.value !== '') {
        select.value = '';
        updateTarif();
    }
}

function updateTarif() {
    const kd = document.getElementById('kd_jenis_prw').value;
    const isDr = document.querySelector('input[name=jenis_tindakan][value=dr]').checked;
    const isPr = document.querySelector('input[name=jenis_tindakan][value=pr]').checked;
    const tarif = tarifData[kd];
    
    if (tarif) {
        let biaya = 0;
        if (isDr) biaya = parseFloat(tarif.total_byrdr||0);
        else if (isPr) biaya = parseFloat(tarif.total_byrpr||0);
        else biaya = parseFloat(tarif.total_byrdrpr||0);
        
        document.getElementById('estimasi_biaya').value = 'Rp ' + biaya.toLocaleString('id-ID', {minimumFractionDigits:0});
    } else {
        document.getElementById('estimasi_biaya').value = '';
    }
}

function sw(evt, id) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    evt.currentTarget.classList.add('active');
}

// Initial Run
window.addEventListener('DOMContentLoaded', () => {
    updateFormDisplay();
});

// UX: Auto-SweetAlert + clean URL setelah simpan/hapus berhasil
(function () {
    const params = new URLSearchParams(window.location.search);
    if (!params.get('sukses')) return;
    const pesan = params.get('sukses');
    const noRawat = params.get('no_rawat') || '';
    const baseUrl = 'input.php?no_rawat=' + encodeURIComponent(noRawat);
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: pesan,
            confirmButtonText: 'OK',
            confirmButtonColor: 'var(--color-primary, #7C3AED)',
            timer: 3000,
            timerProgressBar: true
        }).then(function () { window.location.href = baseUrl; });
    } else {
        const alertEl = document.getElementById('alert-simpan-sukses');
        if (alertEl) {
            setTimeout(function () {
                alertEl.style.transition = 'opacity 0.5s';
                alertEl.style.opacity = '0';
                setTimeout(function () { alertEl.style.display = 'none'; }, 500);
            }, 4000);
        }
        if (window.history && window.history.replaceState) window.history.replaceState({}, '', baseUrl);
    }
})();
</script>

<?php require __DIR__ . '/../lib/layout_footer.php'; ?>
