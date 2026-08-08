<?php
/**
 * asesmen/kecantikan.php
 * Form Penilaian Awal & Rencana Treatment Face Massage (Kecantikan)
 * tabel: penilaian_treatment_wajah & penilaian_treatment_wajah_titik
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';

wajibLogin();
$pdo = getKoneksi();

$noRawat = trim($_GET['no_rawat'] ?? $_POST['no_rawat'] ?? '');
if ($noRawat === '') {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT r.no_rawat, r.tgl_registrasi, r.kd_dokter,
            p.nm_pasien, p.no_rkm_medis, p.jk, p.tgl_lahir, p.alamat, p.no_tlp,
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
$noRawat = $kunjungan['no_rawat'];

$sudahBayar = isSudahBayar($noRawat, $pdo);

$stmtGet = $pdo->prepare("SELECT * FROM penilaian_treatment_wajah WHERE no_rawat = ?");
$stmtGet->execute([$noRawat]);
$prefill = $stmtGet->fetch() ?: [];
$hasData = !empty($prefill);

$stmtTitik = $pdo->prepare("SELECT pos_x, pos_y, keterangan FROM penilaian_treatment_wajah_titik WHERE no_rawat = ?");
$stmtTitik->execute([$noRawat]);
$titikTersimpan = $stmtTitik->fetchAll();

$stmtPrev = $pdo->prepare(
    "SELECT kc.*, r.tgl_registrasi
     FROM penilaian_treatment_wajah kc
     INNER JOIN reg_periksa r ON kc.no_rawat = r.no_rawat
     WHERE r.no_rkm_medis = ?
     ORDER BY kc.tgl_perawatan DESC"
);
$stmtPrev->execute([$kunjungan['no_rkm_medis']]);
$riwayatKecantikanPrev = $stmtPrev->fetchAll();

$error = '';
$sukses = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($sudahBayar) {
        $error = 'Peringatan: Data tidak dapat disimpan karena pasien sudah melakukan pembayaran (LUNAS).';
    } else {
        $bb = ($_POST['bb'] ?? '') !== '' ? (float) $_POST['bb'] : null;
        $tb = ($_POST['tb'] ?? '') !== '' ? (float) $_POST['tb'] : null;
        $jenisKulit = $_POST['jenis_kulit'] ?? 'Normal';

        $jerawat = $_POST['jerawat'] ?? 'Tidak Ada';
        $jerawatArea = trim($_POST['jerawat_area'] ?? '');
        $jerawatDerajat = (($_POST['jerawat_derajat'] ?? '') !== '') ? $_POST['jerawat_derajat'] : null;

        $cacatJerawat = $_POST['cacat_bekas_jerawat'] ?? 'Tidak Ada';
        $cacatJerawatArea = trim($_POST['cacat_bekas_jerawat_area'] ?? '');
        $cacatJerawatDerajat = (($_POST['cacat_bekas_jerawat_derajat'] ?? '') !== '') ? $_POST['cacat_bekas_jerawat_derajat'] : null;

        $fleks = $_POST['fleks_hitam_cokelat'] ?? 'Tidak Ada';
        $fleksArea = trim($_POST['fleks_area'] ?? '');
        $fleksDerajat = (($_POST['fleks_derajat'] ?? '') !== '') ? $_POST['fleks_derajat'] : null;

        $keriput = $_POST['keriput_wajah'] ?? 'Tidak Ada';
        $keriputArea = trim($_POST['keriput_area'] ?? '');
        $sensitif = $_POST['area_sensitif'] ?? 'Tidak Ada';
        $sensitifKet = trim($_POST['area_sensitif_ket'] ?? '');

        $hamil = isset($_POST['kondisi_hamil']) ? 'Ya' : 'Tidak';
        $menyusui = isset($_POST['kondisi_menyusui']) ? 'Ya' : 'Tidak';

        $kontrasepsi = $_POST['menggunakan_kontrasepsi'] ?? 'Tidak';
        $jenisKontrasepsi = trim($_POST['jenis_kontrasepsi'] ?? '');
        $diet = $_POST['diet_khusus'] ?? 'Tidak';
        $jenisDiet = trim($_POST['jenis_diet'] ?? '');
        $alergi = $_POST['alergi'] ?? 'Tidak';
        $alergiKet = trim($_POST['alergi_ket'] ?? '');

        $produkTerakhir = trim($_POST['produk_perawatan_terakhir'] ?? '');
        $keluhan = trim($_POST['keluhan'] ?? '');
        $riwayatDahulu = trim($_POST['riwayat_penyakit_dahulu'] ?? '');
        $riwayatKeluarga = trim($_POST['riwayat_penyakit_keluarga'] ?? '');

        $fokusPijatan = trim($_POST['fokus_pijatan_area'] ?? '');
        $tingkatPijatan = (($_POST['tingkat_pijatan'] ?? '') !== '') ? $_POST['tingkat_pijatan'] : null;

        $persetujuan = isset($_POST['persetujuan_pasien']) ? 'Ya' : 'Tidak';
        $ttdPasien = trim($_POST['nama_ttd_pasien'] ?? '');
        $nip = $_SESSION['nip'] ?? $_SESSION['id_user'] ?? null;
        $ttdData = trim($_POST['ttd_data'] ?? '');

        $data = [
            'no_rawat' => $noRawat,
            'tgl_perawatan' => date('Y-m-d H:i:s'),
            'bb' => $bb,
            'tb' => $tb,
            'jenis_kulit' => $jenisKulit,
            'jerawat' => $jerawat,
            'jerawat_area' => $jerawatArea,
            'jerawat_derajat' => $jerawatDerajat,
            'cacat_bekas_jerawat' => $cacatJerawat,
            'cacat_bekas_jerawat_area' => $cacatJerawatArea,
            'cacat_bekas_jerawat_derajat' => $cacatJerawatDerajat,
            'fleks_hitam_cokelat' => $fleks,
            'fleks_area' => $fleksArea,
            'fleks_derajat' => $fleksDerajat,
            'keriput_wajah' => $keriput,
            'keriput_area' => $keriputArea,
            'area_sensitif' => $sensitif,
            'area_sensitif_ket' => $sensitifKet,
            'kondisi_hamil' => $hamil,
            'kondisi_menyusui' => $menyusui,
            'menggunakan_kontrasepsi' => $kontrasepsi,
            'jenis_kontrasepsi' => $jenisKontrasepsi,
            'diet_khusus' => $diet,
            'jenis_diet' => $jenisDiet,
            'alergi' => $alergi,
            'alergi_ket' => $alergiKet,
            'produk_perawatan_terakhir' => $produkTerakhir,
            'keluhan' => $keluhan,
            'riwayat_penyakit_dahulu' => $riwayatDahulu,
            'riwayat_penyakit_keluarga' => $riwayatKeluarga,
            'fokus_pijatan_area' => $fokusPijatan,
            'tingkat_pijatan' => $tingkatPijatan,
            'persetujuan_pasien' => $persetujuan,
            'nama_ttd_pasien' => $ttdPasien,
            'ttd_pasien' => $ttdData,
            'nip' => $nip,
        ];

        try {
            $pdo->beginTransaction();

            // Ambil struktur kolom yang benar-benar ada di tabel database (Anti-SQL Error 1054)
            $stmtCols = $pdo->query("SHOW COLUMNS FROM penilaian_treatment_wajah");
            $existingCols = $stmtCols ? $stmtCols->fetchAll(PDO::FETCH_COLUMN) : [];
            if (!empty($existingCols)) {
                $data = array_intersect_key($data, array_flip($existingCols));
            }

            $columns = array_keys($data);
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $updates = [];
            foreach ($columns as $col) {
                if ($col !== 'no_rawat')
                    $updates[] = "`$col` = VALUES(`$col`)";
            }
            $sql = "INSERT INTO penilaian_treatment_wajah (`" . implode('`,`', $columns) . "`) VALUES ($placeholders) ON DUPLICATE KEY UPDATE " . implode(', ', $updates);
            $pdo->prepare($sql)->execute(array_values($data));

            $pdo->prepare("DELETE FROM penilaian_treatment_wajah_titik WHERE no_rawat = ?")->execute([$noRawat]);
            if (!empty($_POST['titik_x']) && is_array($_POST['titik_x'])) {
                $stmtT = $pdo->prepare("INSERT INTO penilaian_treatment_wajah_titik (no_rawat, pos_x, pos_y, keterangan) VALUES (?,?,?,?)");
                foreach ($_POST['titik_x'] as $i => $x) {
                    $stmtT->execute([$noRawat, $x, $_POST['titik_y'][$i] ?? 0, $_POST['titik_ket'][$i] ?? '']);
                }
            }

            $pdo->commit();
            header('Location: kecantikan.php?no_rawat=' . urlencode($noRawat) . '&status=success');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction())
                $pdo->rollBack();
            error_log('[kecantikan.php] ' . $e->getMessage());
            $error = 'Gagal menyimpan data: ' . $e->getMessage();
        }
    }
}

$halamanAktif = 'asesmen';
$judulHalaman = 'Asesmen Kecantikan & Face Massage';
$baseAsset = '../';
require __DIR__ . '/../lib/layout_header.php';

function ov($arr, $key, $def = '')
{
    return htmlspecialchars($arr[$key] ?? $def);
}
function chk($v, $t)
{
    return $v == $t ? 'checked' : '';
}
?>
<style>
    .fm2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .fm3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 16px;
    }

    .fm4 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr;
        gap: 16px;
    }

    @media (max-width: 768px) {

        .fm2,
        .fm3,
        .fm4 {
            grid-template-columns: 1fr;
        }
    }

    .sec-title {
        font-size: 12.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--color-primary);
        margin: 24px 0 12px;
        padding-bottom: 6px;
        border-bottom: 2px solid var(--color-border);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .sec-title::before {
        content: '';
        display: inline-block;
        width: 4px;
        height: 14px;
        background: var(--color-primary);
        border-radius: 2px;
    }

    .comp-box {
        background: #FFFDFE;
        border: 1px solid #F0DADE;
        border-radius: 10px;
        padding: 16px 18px;
        margin-bottom: 14px;
        box-shadow: 0 1px 3px rgba(160, 57, 106, 0.03);
    }

    .cond-card {
        background: #FAFAFA;
        border: 1px solid var(--color-border);
        border-radius: 8px;
        padding: 14px 16px;
        margin-bottom: 12px;
        transition: border-color 0.2s, background-color 0.2s;
    }

    .cond-card:hover {
        border-color: #DDA2BE;
        background: #FFFBFD;
    }

    .cond-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        flex-wrap: wrap;
        gap: 8px;
    }

    .cond-card-title {
        font-size: 13.5px;
        font-weight: 700;
        color: #333;
    }

    .pill-group {
        display: inline-flex;
        gap: 6px;
        align-items: center;
    }

    .pill-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        padding: 5px 12px;
        border: 1.5px solid #DDD;
        border-radius: 20px;
        background: #FFF;
        color: #555;
        transition: all 0.15s ease;
        user-select: none;
    }

    .pill-btn input[type="radio"],
    .pill-btn input[type="checkbox"] {
        display: none;
    }

    .pill-btn:has(input:checked) {
        border-color: var(--color-primary);
        background: var(--color-primary);
        color: #FFF;
        box-shadow: 0 2px 6px rgba(160, 57, 106, 0.25);
    }

    .face-wrap {
        position: relative;
        display: inline-block;
        cursor: crosshair;
        border: 2px solid var(--color-border);
        border-radius: 10px;
        overflow: hidden;
        user-select: none;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        background: #fff;
    }

    .face-wrap img {
        display: block;
        width: 220px;
        height: auto;
    }

    .marker-svg {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
    }

    .titik-item {
        font-size: 12px;
        padding: 4px 8px;
        margin-bottom: 4px;
        background: #FFF8FB;
        border: 1px solid #F0D3E1;
        border-radius: 6px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .btn-del-t {
        cursor: pointer;
        color: #D62839;
        font-weight: 700;
        font-size: 12px;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #FFEAEB;
    }

    .btn-del-t:hover {
        background: #D62839;
        color: #FFF;
    }
    /* ─── CETAK / PRINT STYLING (A4 Portrait Standar) ───────────────── */
    @media print {
        @page {
            size: A4 portrait;
            margin: 12mm 15mm 12mm 15mm;
        }

        /* Sembunyikan elemen non-cetak */
        .sidebar,
        .topbar,
        .btn,
        .btn-back,
        .btn-toggle-sidebar,
        .sidebar-overlay,
        .alert,
        .card-riwayat-container,
        .no-print,
        .form-check,
        .col-aksi,
        button[type="submit"],
        button[type="button"],
        a.btn,
        nav,
        .brand,
        .btn-del-t,
        .btn-danger {
            display: none !important;
        }

        body, html {
            background: #ffffff !important;
            color: #000000 !important;
            font-size: 11pt !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .app-shell {
            display: block !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .main-content {
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        .card {
            border: 1px solid #999 !important;
            box-shadow: none !important;
            padding: 12px !important;
            margin-bottom: 12px !important;
            background: #ffffff !important;
            page-break-inside: avoid;
        }

        .comp-box, .cond-card {
            border: 1px solid #bbb !important;
            background: #ffffff !important;
            box-shadow: none !important;
        }

        input[type="text"], input[type="number"], select, textarea {
            border: none !important;
            border-bottom: 1px dashed #333 !important;
            background: transparent !important;
            color: #000 !important;
            box-shadow: none !important;
            appearance: none !important;
            -webkit-appearance: none !important;
        }

        .canvas-drawing-container {
            border: 1px solid #333 !important;
            background: #ffffff !important;
        }

        canvas {
            display: block !important;
            pointer-events: none !important;
        }
    }
</style>

<div style="margin-bottom:14px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; font-size:13px;" class="no-print">
    <div style="display:flex; align-items:center; gap:10px;">
        <a href="pilih.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-back">← Kembali ke Menu Asesmen</a>
        <span class="text-muted">&bull; Kunjungan: <code><?= htmlspecialchars($noRawat) ?></code> &bull; Pasien:
            <strong><?= htmlspecialchars($kunjungan['nm_pasien']) ?></strong></span>
    </div>
    <div>
        <a href="cetak_asesmen.php?type=kecantikan&no_rawat=<?= urlencode($noRawat) ?>" target="_blank"
           class="btn btn-outline" style="font-size:12.5px; padding:6px 14px; display:inline-flex; align-items:center; gap:6px; text-decoration:none;">
            🖨️ Cetak Hasil Asesmen
        </a>
    </div>
</div>

<?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
    <div class="alert alert-success no-print" id="alert-simpan-sukses">✔ Data Asesmen Kecantikan berhasil disimpan.</div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger no-print"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!empty($riwayatKecantikanPrev)): ?>
    <div class="card card-riwayat-container" style="margin-bottom:16px;">
        <p class="card-title" style="margin-bottom:12px;">Riwayat Asesmen Kecantikan
            <span class="text-muted" style="font-size:12px; font-weight:normal; margin-left:10px;">Total:
                <strong><?= count($riwayatKecantikanPrev) ?></strong> kunjungan</span>
        </p>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:12.5px;">
                <thead>
                    <tr style="background:var(--color-primary); color:#fff;">
                        <th style="padding:8px 10px; text-align:left;">Tgl Perawatan</th>
                        <th style="padding:8px 10px; text-align:left;">No. Rawat</th>
                        <th style="padding:8px 10px; text-align:left;">Jenis Kulit</th>
                        <th style="padding:8px 10px; text-align:left;">Jerawat</th>
                        <th style="padding:8px 10px; text-align:left;">Bekas Jerawat</th>
                        <th style="padding:8px 10px; text-align:left;">Fleks</th>
                        <th style="padding:8px 10px; text-align:left;">Keluhan Utama</th>
                        <th style="padding:8px 10px; text-align:left;">Fokus Pijatan</th>
                        <th style="padding:8px 10px; text-align:center;">Persetujuan</th>
                        <th style="padding:8px 10px; text-align:center; width:130px;" class="col-aksi">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($riwayatKecantikanPrev as $rk): ?>
                        <tr
                            style="border-bottom:1px solid var(--color-border); <?= $rk['no_rawat'] === $noRawat ? 'background:#FFF8FB;' : '' ?>">
                            <td style="padding:6px 10px;">
                                <?= htmlspecialchars(date('d-m-Y H:i', strtotime($rk['tgl_perawatan']))) ?>
                                <?php if ($rk['no_rawat'] === $noRawat): ?>
                                    <span
                                        style="font-size:10px; background:var(--color-primary); color:#fff; border-radius:3px; padding:1px 5px; margin-left:4px;">Sekarang</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:6px 10px;"><code><?= htmlspecialchars($rk['no_rawat']) ?></code></td>
                            <td style="padding:6px 10px;"><strong><?= htmlspecialchars($rk['jenis_kulit']) ?></strong></td>
                            <td style="padding:6px 10px;">
                                <?= htmlspecialchars($rk['jerawat']) ?>        <?= $rk['jerawat_area'] ? ' (' . $rk['jerawat_area'] . ')' : '' ?>
                            </td>
                            <td style="padding:6px 10px;"><?= htmlspecialchars($rk['cacat_bekas_jerawat']) ?></td>
                            <td style="padding:6px 10px;"><?= htmlspecialchars($rk['fleks_hitam_cokelat']) ?></td>
                            <td style="padding:6px 10px;">
                                <?= htmlspecialchars(mb_substr($rk['keluhan'] ?? '-', 0, 40)) ?>        <?= mb_strlen($rk['keluhan'] ?? '') > 40 ? '…' : '' ?>
                            </td>
                            <td style="padding:6px 10px;"><?= htmlspecialchars($rk['fokus_pijatan_area'] ?? '-') ?></td>
                            <td style="padding:6px 10px; text-align:center;">
                                <span class="badge <?= $rk['persetujuan_pasien'] === 'Ya' ? 'badge-success' : 'badge-warning' ?>"
                                    style="font-size:11px;">
                                    <?= htmlspecialchars($rk['persetujuan_pasien']) ?>
                                </span>
                            </td>
                            <td style="padding:6px 10px; text-align:center;" class="col-aksi">
                                <a href="kecantikan.php?no_rawat=<?= urlencode($rk['no_rawat']) ?>" class="btn btn-outline"
                                    style="font-size:12px; padding:3px 8px; text-decoration:none;">Edit</a>
                                <a href="cetak_asesmen.php?type=kecantikan&no_rawat=<?= urlencode($rk['no_rawat']) ?>" target="_blank"
                                    class="btn btn-outline btn-print-act"
                                    style="font-size:11.5px; padding:3px 8px; margin-left:4px; border-color:var(--color-primary); color:var(--color-primary); text-decoration:none;" title="Cetak Asesmen">🖨️ Cetak</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card card-riwayat-container" style="margin-bottom:16px;">
        <p class="card-title" style="margin-bottom:8px;">Riwayat Asesmen Kecantikan</p>
        <p class="text-muted" style="text-align:center; padding:14px 0; font-size:13px;">Belum ada riwayat asesmen
            kecantikan.</p>
    </div>
<?php endif; ?>

<div class="card">
    <p class="card-title">
        <?= $hasData ? 'Edit Asesmen Kecantikan &amp; Face Massage' : 'Form Penilaian Awal Kecantikan &amp; Face Massage' ?>
    </p>
    <p class="text-mute" style="margin-top:-8px; margin-bottom:18px;">Dokter Pemeriksa:
        <strong><?= htmlspecialchars($kunjungan['nm_dokter'] ?? '-') ?></strong></p>

    <?php if ($sudahBayar): ?>
        <div class="alert alert-danger" style="margin-bottom:14px;font-size:12.5px;">
            🔒 <strong>Data dikunci:</strong> Pembayaran telah diselesaikan. Anda tidak dapat menambah atau mengubah asesmen
            kecantikan ini.
        </div>
    <?php endif; ?>

    <form method="post" id="formKc">
        <fieldset <?= $sudahBayar ? 'disabled' : '' ?> style="border:0;padding:0;margin:0;">
            <input type="hidden" name="no_rawat" value="<?= htmlspecialchars($noRawat) ?>">
            <input type="hidden" name="ttd_data" id="ttdData"
                value="<?= htmlspecialchars($prefill['ttd_pasien'] ?? '') ?>">

            <p class="sec-title" style="margin-top:0;">1. Data Fisik Pasien &amp; Jenis Kulit</p>
            <div class="comp-box">
                <div class="fm3">
                    <div>
                        <label for="bb">Berat Badan (BB)</label>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <input type="number" id="bb" name="bb" step="0.01" min="20" max="250" placeholder="62.5"
                                value="<?= ov($prefill, 'bb') ?>">
                            <span class="text-muted" style="font-size:13px; font-weight:600;">kg</span>
                        </div>
                    </div>
                    <div>
                        <label for="tb">Tinggi Badan (TB)</label>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <input type="number" id="tb" name="tb" step="0.1" min="100" max="220" placeholder="160"
                                value="<?= ov($prefill, 'tb') ?>">
                            <span class="text-muted" style="font-size:13px; font-weight:600;">cm</span>
                        </div>
                    </div>
                    <div>
                        <label for="jenis_kulit">Jenis Kulit Pasien *</label>
                        <select id="jenis_kulit" name="jenis_kulit"
                            style="font-weight:600; color:var(--color-primary);">
                            <?php foreach (['Normal', 'Kering', 'Berminyak', 'Kombinasi', 'Sensitif'] as $jk): ?>
                                <option value="<?= $jk ?>" <?= ($prefill['jenis_kulit'] ?? 'Normal') === $jk ? 'selected' : '' ?>><?= $jk ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <p class="sec-title">2. Analisis Kondisi Kulit Wajah</p>

            <div class="cond-card">
                <div class="cond-card-header">
                    <span class="cond-card-title">🌋 Jerawat di Wajah</span>
                    <div class="pill-group">
                        <label class="pill-btn">
                            <input type="radio" name="jerawat" value="Ada" <?= chk($prefill['jerawat'] ?? 'Tidak Ada', 'Ada') ?>>
                            <span>Ada</span>
                        </label>
                        <label class="pill-btn">
                            <input type="radio" name="jerawat" value="Tidak Ada" <?= chk($prefill['jerawat'] ?? 'Tidak Ada', 'Tidak Ada') ?>>
                            <span>Tidak Ada</span>
                        </label>
                    </div>
                </div>
                <div class="fm2">
                    <div>
                        <label for="jerawat_area"
                            style="font-size:11px; text-transform:uppercase; font-weight:700; color:#888;">Area
                            Jerawat</label>
                        <input type="text" id="jerawat_area" name="jerawat_area"
                            placeholder="Misal: Pipi kiri, dahi, dagu..." value="<?= ov($prefill, 'jerawat_area') ?>">
                    </div>
                    <div>
                        <label for="jerawat_derajat"
                            style="font-size:11px; text-transform:uppercase; font-weight:700; color:#888;">Derajat
                            Keparahan</label>
                        <select id="jerawat_derajat" name="jerawat_derajat">
                            <option value="">-- Pilih Derajat --</option>
                            <?php foreach (['Ringan', 'Sedang', 'Berat'] as $d): ?>
                                <option value="<?= $d ?>" <?= ($prefill['jerawat_derajat'] ?? '') === $d ? 'selected' : '' ?>>
                                    <?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="cond-card">
                <div class="cond-card-header">
                    <span class="cond-card-title">🩹 Bekas Jerawat / Cacat Kulit</span>
                    <div class="pill-group">
                        <label class="pill-btn">
                            <input type="radio" name="cacat_bekas_jerawat" value="Ada"
                                <?= chk($prefill['cacat_bekas_jerawat'] ?? 'Tidak Ada', 'Ada') ?>>
                            <span>Ada</span>
                        </label>
                        <label class="pill-btn">
                            <input type="radio" name="cacat_bekas_jerawat" value="Tidak Ada"
                                <?= chk($prefill['cacat_bekas_jerawat'] ?? 'Tidak Ada', 'Tidak Ada') ?>>
                            <span>Tidak Ada</span>
                        </label>
                    </div>
                </div>
                <div class="fm2">
                    <div>
                        <label for="cacat_bekas_jerawat_area"
                            style="font-size:11px; text-transform:uppercase; font-weight:700; color:#888;">Area Bekas
                            Jerawat</label>
                        <input type="text" id="cacat_bekas_jerawat_area" name="cacat_bekas_jerawat_area"
                            placeholder="Misal: Pipi kanan..." value="<?= ov($prefill, 'cacat_bekas_jerawat_area') ?>">
                    </div>
                    <div>
                        <label for="cacat_bekas_jerawat_derajat"
                            style="font-size:11px; text-transform:uppercase; font-weight:700; color:#888;">Derajat
                            Keparahan</label>
                        <select id="cacat_bekas_jerawat_derajat" name="cacat_bekas_jerawat_derajat">
                            <option value="">-- Pilih Derajat --</option>
                            <?php foreach (['Ringan', 'Sedang', 'Berat'] as $d): ?>
                                <option value="<?= $d ?>" <?= ($prefill['cacat_bekas_jerawat_derajat'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="cond-card">
                <div class="cond-card-header">
                    <span class="cond-card-title">🟤 Fleks Hitam / Cokelat</span>
                    <div class="pill-group">
                        <label class="pill-btn">
                            <input type="radio" name="fleks_hitam_cokelat" value="Ada"
                                <?= chk($prefill['fleks_hitam_cokelat'] ?? 'Tidak Ada', 'Ada') ?>>
                            <span>Ada</span>
                        </label>
                        <label class="pill-btn">
                            <input type="radio" name="fleks_hitam_cokelat" value="Tidak Ada"
                                <?= chk($prefill['fleks_hitam_cokelat'] ?? 'Tidak Ada', 'Tidak Ada') ?>>
                            <span>Tidak Ada</span>
                        </label>
                    </div>
                </div>
                <div class="fm2">
                    <div>
                        <label for="fleks_area"
                            style="font-size:11px; text-transform:uppercase; font-weight:700; color:#888;">Area
                            Fleks</label>
                        <input type="text" id="fleks_area" name="fleks_area" placeholder="Misal: Tulang pipi..."
                            value="<?= ov($prefill, 'fleks_area') ?>">
                    </div>
                    <div>
                        <label for="fleks_derajat"
                            style="font-size:11px; text-transform:uppercase; font-weight:700; color:#888;">Derajat
                            Keparahan</label>
                        <select id="fleks_derajat" name="fleks_derajat">
                            <option value="">-- Pilih Derajat --</option>
                            <?php foreach (['Ringan', 'Sedang', 'Berat'] as $d): ?>
                                <option value="<?= $d ?>" <?= ($prefill['fleks_derajat'] ?? '') === $d ? 'selected' : '' ?>>
                                    <?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="fm2">
                <div class="cond-card">
                    <div class="cond-card-header">
                        <span class="cond-card-title">〰️ Keriput Wajah</span>
                        <div class="pill-group">
                            <label class="pill-btn">
                                <input type="radio" name="keriput_wajah" value="Ada" <?= chk($prefill['keriput_wajah'] ?? 'Tidak Ada', 'Ada') ?>>
                                <span>Ada</span>
                            </label>
                            <label class="pill-btn">
                                <input type="radio" name="keriput_wajah" value="Tidak Ada"
                                    <?= chk($prefill['keriput_wajah'] ?? 'Tidak Ada', 'Tidak Ada') ?>>
                                <span>Tidak Ada</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <input type="text" name="keriput_area"
                            placeholder="Keterangan area keriput (sekitar mata, dahi...)"
                            value="<?= ov($prefill, 'keriput_area') ?>">
                    </div>
                </div>

                <div class="cond-card">
                    <div class="cond-card-header">
                        <span class="cond-card-title">⚡ Area Sensitif</span>
                        <div class="pill-group">
                            <label class="pill-btn">
                                <input type="radio" name="area_sensitif" value="Ada" <?= chk($prefill['area_sensitif'] ?? 'Tidak Ada', 'Ada') ?>>
                                <span>Ada</span>
                            </label>
                            <label class="pill-btn">
                                <input type="radio" name="area_sensitif" value="Tidak Ada"
                                    <?= chk($prefill['area_sensitif'] ?? 'Tidak Ada', 'Tidak Ada') ?>>
                                <span>Tidak Ada</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <input type="text" name="area_sensitif_ket"
                            placeholder="Keterangan area sensitif (cuping hidung, dll...)"
                            value="<?= ov($prefill, 'area_sensitif_ket') ?>">
                    </div>
                </div>
            </div>

            <p class="sec-title">3. Riwayat Kesehatan &amp; Kondisi Umum</p>
            <div class="comp-box">
                <div class="fm3" style="margin-bottom:14px;">
                    <div>
                        <label style="display:block; margin-bottom:6px;">Kondisi Hamil</label>
                        <div class="pill-group">
                            <label class="pill-btn">
                                <input type="radio" name="kondisi_hamil" value="Ya" <?= chk($prefill['kondisi_hamil'] ?? 'Tidak', 'Ya') ?>>
                                <span>Ya</span>
                            </label>
                            <label class="pill-btn">
                                <input type="radio" name="kondisi_hamil" value="Tidak" <?= chk($prefill['kondisi_hamil'] ?? 'Tidak', 'Tidak') ?>>
                                <span>Tidak</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:6px;">Kondisi Menyusui</label>
                        <div class="pill-group">
                            <label class="pill-btn">
                                <input type="radio" name="kondisi_menyusui" value="Ya"
                                    <?= chk($prefill['kondisi_menyusui'] ?? 'Tidak', 'Ya') ?>>
                                <span>Ya</span>
                            </label>
                            <label class="pill-btn">
                                <input type="radio" name="kondisi_menyusui" value="Tidak"
                                    <?= chk($prefill['kondisi_menyusui'] ?? 'Tidak', 'Tidak') ?>>
                                <span>Tidak</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:6px;">Menggunakan Kontrasepsi</label>
                        <div class="pill-group">
                            <label class="pill-btn">
                                <input type="radio" name="menggunakan_kontrasepsi" value="Ya"
                                    <?= chk($prefill['menggunakan_kontrasepsi'] ?? 'Tidak', 'Ya') ?>>
                                <span>Ya</span>
                            </label>
                            <label class="pill-btn">
                                <input type="radio" name="menggunakan_kontrasepsi" value="Tidak"
                                    <?= chk($prefill['menggunakan_kontrasepsi'] ?? 'Tidak', 'Tidak') ?>>
                                <span>Tidak</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="fm3" style="margin-bottom:14px;">
                    <div>
                        <label for="jenis_kontrasepsi">Jenis Kontrasepsi</label>
                        <input type="text" id="jenis_kontrasepsi" name="jenis_kontrasepsi"
                            placeholder="Pil, Suntik, IUD, dll" value="<?= ov($prefill, 'jenis_kontrasepsi') ?>">
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:6px;">Diet Khusus</label>
                        <div class="pill-group">
                            <label class="pill-btn">
                                <input type="radio" name="diet_khusus" value="Ya" <?= chk($prefill['diet_khusus'] ?? 'Tidak', 'Ya') ?>>
                                <span>Ya</span>
                            </label>
                            <label class="pill-btn">
                                <input type="radio" name="diet_khusus" value="Tidak" <?= chk($prefill['diet_khusus'] ?? 'Tidak', 'Tidak') ?>>
                                <span>Tidak</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label for="jenis_diet">Keterangan Diet Khusus</label>
                        <input type="text" id="jenis_diet" name="jenis_diet" placeholder="Diet tertentu..."
                            value="<?= ov($prefill, 'jenis_diet') ?>">
                    </div>
                </div>

                <div class="fm3">
                    <div>
                        <label style="display:block; margin-bottom:6px;">Riwayat Alergi</label>
                        <div class="pill-group">
                            <label class="pill-btn">
                                <input type="radio" name="alergi" value="Ya" <?= chk($prefill['alergi'] ?? 'Tidak', 'Ya') ?>>
                                <span>Ada</span>
                            </label>
                            <label class="pill-btn">
                                <input type="radio" name="alergi" value="Tidak" <?= chk($prefill['alergi'] ?? 'Tidak', 'Tidak') ?>>
                                <span>Tidak Ada</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label for="alergi_ket">Keterangan Alergi</label>
                        <input type="text" id="alergi_ket" name="alergi_ket" placeholder="Alergi kosmetik/obat tertentu"
                            value="<?= ov($prefill, 'alergi_ket') ?>">
                    </div>
                    <div>
                        <label for="produk_perawatan_terakhir">Produk Skincare Terakhir</label>
                        <input type="text" id="produk_perawatan_terakhir" name="produk_perawatan_terakhir"
                            placeholder="Merk / jenis produk terakhir"
                            value="<?= ov($prefill, 'produk_perawatan_terakhir') ?>">
                    </div>
                </div>
            </div>

            <div class="fm2" style="margin-bottom:14px;">
                <div>
                    <label for="riwayat_dahulu">Riwayat Penyakit Dahulu</label>
                    <textarea id="riwayat_dahulu" name="riwayat_penyakit_dahulu" rows="2"
                        placeholder="Hipertensi, DM, penyakit kulit sebelumnya..."><?= ov($prefill, 'riwayat_penyakit_dahulu') ?></textarea>
                </div>
                <div>
                    <label for="riwayat_keluarga">Riwayat Penyakit Keluarga</label>
                    <textarea id="riwayat_keluarga" name="riwayat_penyakit_keluarga" rows="2"
                        placeholder="Riwayat penyakit dalam keluarga..."><?= ov($prefill, 'riwayat_penyakit_keluarga') ?></textarea>
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label for="keluhan">Keluhan Utama Pasien *</label>
                <textarea id="keluhan" name="keluhan" rows="3"
                    placeholder="Tuliskan keluhan utama pasien mengenai kondisi kulit wajah atau perawatan yang diinginkan..."><?= ov($prefill, 'keluhan') ?></textarea>
            </div>

            <p class="sec-title">4. Rencana Treatment &amp; Diagram Titik Pijat</p>
            <div class="fm2" style="margin-bottom:14px; align-items:start;">
                <div>
                    <div style="margin-bottom:14px;">
                        <label for="fokus_pijatan_area">Area Fokus Pijatan</label>
                        <textarea id="fokus_pijatan_area" name="fokus_pijatan_area" rows="4"
                            placeholder="Tuliskan area fokus pijatan: Dahi, pipi kiri, leher, sekitar mata..."><?= ov($prefill, 'fokus_pijatan_area') ?></textarea>
                    </div>
                    <div>
                        <label for="tingkat_pijatan">Tingkat Tekanan Pijatan</label>
                        <select id="tingkat_pijatan" name="tingkat_pijatan">
                            <option value="">-- Pilih Tingkat Tekanan --</option>
                            <?php foreach (['Tekanan Ringan', 'Tekanan Sedang', 'Tekanan Kuat'] as $tp): ?>
                                <option value="<?= $tp ?>" <?= ($prefill['tingkat_pijatan'] ?? '') === $tp ? 'selected' : '' ?>><?= $tp ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <div style="background:#FFF9FC; border:1px solid #F0D3E1; border-radius:10px; padding:14px;">
                        <label
                            style="font-size:12.5px; font-weight:700; color:var(--color-primary); margin-bottom:4px; display:block;">Peta
                            Titik Lokasi Pijatan</label>
                        <small class="text-muted" style="display:block; margin-bottom:10px; font-size:11.5px;">💡 Klik
                            langsung pada diagram wajah di bawah untuk menambahkan titik penanda lokasi pijatan.</small>

                        <div style="display:flex; gap:16px; align-items:flex-start; flex-wrap:wrap;">
                            <div>
                                <div class="face-wrap" id="faceWrap"
                                    style="position: relative; width: 220px; height: 280px;">
                                    <img src="../assets/img/area_pijatan.png" alt="Diagram Wajah" id="faceImg"
                                        style="width:220px; height:auto; display:block; position:relative; z-index:1;">
                                    <svg class="marker-svg" id="markerLayer"
                                        style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 60; pointer-events: none;"></svg>
                                </div>
                                <?php if (!$sudahBayar): ?>
                                    <div style="margin-top:8px;">
                                        <button type="button" class="btn btn-sm btn-outline"
                                            style="font-size:11px; padding:4px 10px; color:#D62839; border-color:#D62839; width:100%;"
                                            onclick="hapusSemua()">Clear All Markers</button>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div style="flex:1; min-width:140px;">
                                <div
                                    style="font-size:11px; font-weight:700; text-transform:uppercase; color:#888; margin-bottom:6px;">
                                    Daftar Titik Penanda</div>
                                <div id="titikListView" style="font-size:12px; max-height:190px; overflow-y:auto;">
                                </div>
                                <div id="titikInputs"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <p class="sec-title">5. Persetujuan Tindakan &amp; Tanda Tangan</p>
            <div class="comp-box">
                <p style="font-size:13px; line-height:1.6; margin-bottom:14px; color:#555;">
                    Saya menyatakan telah memberikan informasi riwayat kesehatan dengan jujur dan memahami penjelasan
                    prosedur penilaian awal serta rencana treatment face massage yang akan dilakukan.
                </p>

                <div class="fm2" style="align-items:start;">
                    <div>
                        <label
                            style="display:inline-flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; font-weight:700; color:var(--color-primary); background:#FFF8FB; padding:8px 12px; border:1px solid #F0D3E1; border-radius:8px; width:100%;">
                            <input type="checkbox" name="persetujuan_pasien" value="Ya"
                                <?= ($prefill['persetujuan_pasien'] ?? 'Tidak') === 'Ya' ? 'checked' : '' ?>
                                style="width:18px; height:18px; accent-color:var(--color-primary);">
                            <span>Pasien / Keluarga Menyetujui *</span>
                        </label>

                        <div style="margin-top:14px;">
                            <label for="nama_ttd_pasien">Nama Terang Penandatangan Pasien / Wali *</label>
                            <input type="text" id="nama_ttd_pasien" name="nama_ttd_pasien"
                                placeholder="Tuliskan nama lengkap pasien / wali..."
                                value="<?= ov($prefill, 'nama_ttd_pasien') ?>">
                        </div>
                    </div>

                    <div>
                        <label style="font-size:12px; font-weight:600; margin-bottom:6px; display:block;">Tanda Tangan
                            Digital Pasien / Wali (Langsung Corat-Coret)</label>
                        <div class="canvas-drawing-container"
                            style="position: relative; width: 300px; height: 120px; border: 1px solid #ccc; margin: 0 auto; touch-action: none; background-color: #fff; border-radius: 8px; overflow: hidden;">
                            <canvas id="sigCanvas" width="300" height="120"
                                style="position: absolute; top: 0; left: 0; z-index: 50; cursor: crosshair; touch-action: none; display: block;"></canvas>
                        </div>
                        <?php if (!$sudahBayar): ?>
                            <div style="margin-top:6px;">
                                <button type="button" class="btn btn-sm btn-danger mt-1" onclick="clearSig()">Hapus
                                    TTD</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div
                style="display:flex; gap:12px; margin-top:24px; padding-top:16px; border-top:1.5px solid var(--color-border); align-items:center;">
                <?php if (!$sudahBayar): ?>
                    <button type="submit" class="btn btn-primary" style="padding:10px 28px; font-size:13.5px;">
                        <?= $hasData ? 'Simpan Perubahan Asesmen' : 'Simpan Asesmen Kecantikan' ?>
                    </button>
                <?php endif; ?>
                <a href="pilih.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-outline"
                    style="padding:10px 20px;">Batal</a>
            </div>

        </fieldset>
    </form>
</div>
<script>
    var sudahBayar = <?= $sudahBayar ? 'true' : 'false' ?>;
    var titik = <?= json_encode(array_map(function ($t) {
        return ['pos_x' => (float) $t['pos_x'], 'pos_y' => (float) $t['pos_y'], 'keterangan' => $t['keterangan']]; }, $titikTersimpan)) ?>;

    var faceWrap = document.getElementById('faceWrap');
    var markerLayer = document.getElementById('markerLayer');

    function renderTitik() {
        if (!markerLayer) return;
        markerLayer.innerHTML = '';
        var w = faceWrap ? (faceWrap.offsetWidth || 220) : 220;
        var h = faceWrap ? (faceWrap.offsetHeight || 280) : 280;
        var html = '';
        titik.forEach(function (t, i) {
            var cx = t.pos_x / 100 * w;
            var cy = t.pos_y / 100 * h;
            var c = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            c.setAttribute('cx', cx); c.setAttribute('cy', cy);
            c.setAttribute('r', 7);
            c.setAttribute('fill', '#A0396A'); c.setAttribute('stroke', '#fff'); c.setAttribute('stroke-width', '2');
            c.style.cursor = 'pointer';
            if (!sudahBayar) { (function (idx) { c.onclick = function (e) { e.stopPropagation(); hapusTitik(idx); }; })(i); }
            markerLayer.appendChild(c);
            html += '<div class="titik-item"><span>Titik ' + (i + 1) + ' (' + parseFloat(t.pos_x).toFixed(0) + '%, ' + parseFloat(t.pos_y).toFixed(0) + '%)</span>'
                + (sudahBayar ? '' : '<span class="btn-del-t" onclick="hapusTitik(' + i + ')">✕</span>') + '</div>';
        });
        var lv = document.getElementById('titikListView');
        if (lv) lv.innerHTML = html || '<em style="color:#aaa; font-size:12px;">Belum ada titik penanda</em>';
        var inp = document.getElementById('titikInputs');
        if (inp) {
            inp.innerHTML = '';
            titik.forEach(function (t) {
                inp.innerHTML += '<input type="hidden" name="titik_x[]" value="' + t.pos_x + '">'
                    + '<input type="hidden" name="titik_y[]" value="' + t.pos_y + '">'
                    + '<input type="hidden" name="titik_ket[]" value="' + (t.keterangan || '').replace(/"/g, '&quot;') + '">';
            });
        }
    }
    function tandaiTitik(e) {
        if (sudahBayar) return;
        var r = faceWrap.getBoundingClientRect();
        var clientX = e.touches ? e.touches[0].clientX : e.clientX;
        var clientY = e.touches ? e.touches[0].clientY : e.clientY;
        titik.push({ pos_x: ((clientX - r.left) / r.width * 100).toFixed(2), pos_y: ((clientY - r.top) / r.height * 100).toFixed(2), keterangan: '' });
        renderTitik();
    }
    function hapusTitik(i) { if (!sudahBayar) { titik.splice(i, 1); renderTitik(); } }
    function hapusSemua() { if (!sudahBayar && confirm('Hapus semua titik penanda?')) { titik = []; renderTitik(); } }

    if (faceWrap) {
        faceWrap.addEventListener('click', tandaiTitik);
        var faceImg = document.getElementById('faceImg');
        if (faceImg) {
            if (faceImg.complete) renderTitik();
            else faceImg.addEventListener('load', renderTitik);
        }
        window.addEventListener('resize', renderTitik);
    }

    /* Validasi persetujuan sebelum submit */
    var formKc = document.getElementById('formKc');
    if (formKc) {
        formKc.addEventListener('submit', function (e) {
            var chk = document.querySelector('input[name="persetujuan_pasien"]');
            if (!chk || !chk.checked) {
                e.preventDefault();
                alert('⚠️ PERHATIAN:\n\nHarap centang persetujuan pasien terlebih dahulu sebelum menyimpan data!');
                chk.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
            var namaTtd = document.getElementById('nama_ttd_pasien');
            if (!namaTtd || !namaTtd.value.trim()) {
                e.preventDefault();
                alert('⚠️ PERHATIAN:\n\nHarap isi Nama Terang Penandatangan Pasien/Wali terlebih dahulu!');
                namaTtd.focus();
                namaTtd.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
        });
    }

    /* SCRIPT JAVASCRIPT CANVAS TANDA TANGAN OTOMATIS */
    function initSimpleCanvas(canvasId) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        let isDrawing = false;

        // Load existing base64 image if available for signature
        <?php if (!empty($prefill['ttd_pasien'])): ?>
            if (canvasId === 'sigCanvas') {
                const img = new Image();
                img.onload = function () { ctx.drawImage(img, 0, 0); };
                img.src = '<?= $prefill['ttd_pasien'] ?>';
            }
        <?php endif; ?>

        function getMousePos(e) {
            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width / (rect.width || 1);
            const scaleY = canvas.height / (rect.height || 1);

            let clientX = e.clientX;
            let clientY = e.clientY;

            if (e.touches && e.touches.length > 0) {
                clientX = e.touches[0].clientX;
                clientY = e.touches[0].clientY;
            }

            return {
                x: (clientX - rect.left) * scaleX,
                y: (clientY - rect.top) * scaleY
            };
        }

        function startDraw(e) {
            if (sudahBayar) return;
            if (e.cancelable) e.preventDefault();
            isDrawing = true;
            const pos = getMousePos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
        }

        function drawing(e) {
            if (!isDrawing) return;
            if (e.cancelable) e.preventDefault();
            const pos = getMousePos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.strokeStyle = "#1a1a2e";
            ctx.lineWidth = 2.5;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.stroke();
        }

        function stopDraw(e) {
            if (!isDrawing) return;
            isDrawing = false;
            ctx.closePath();

            if (canvasId === 'sigCanvas') {
                const input = document.getElementById('ttdData');
                if (input) input.value = canvas.toDataURL('image/png');
            }
        }

        canvas.addEventListener('mousedown', startDraw);
        canvas.addEventListener('mousemove', drawing);
        canvas.addEventListener('mouseup', stopDraw);
        canvas.addEventListener('mouseout', stopDraw);

        canvas.addEventListener('touchstart', startDraw, { passive: false });
        canvas.addEventListener('touchmove', drawing, { passive: false });
        canvas.addEventListener('touchend', stopDraw);
    }

    document.addEventListener("DOMContentLoaded", function () {
        setTimeout(function () {
            initSimpleCanvas('sigCanvas');
        }, 300);
    });

    /* Fungsi Global Hapus Canvas Spesifik */
    window.clearCanvasManual = function (canvasId, inputHiddenId = null) {
        const canvas = document.getElementById(canvasId);
        if (canvas) {
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
        if (inputHiddenId) {
            const input = document.getElementById(inputHiddenId);
            if (input) input.value = '';
        }
    };

    window.clearSig = function () {
        window.clearCanvasManual('sigCanvas', 'ttdData');
    };

    // Trigger Ulang saat Tab / Accordion Dibuka
    if (typeof $ !== 'undefined') {
        $('.collapse').on('shown.bs.collapse', function () {
            window.dispatchEvent(new Event('resize'));
        });
        $('a[data-toggle="tab"], button[data-bs-toggle="tab"], a[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
            window.dispatchEvent(new Event('resize'));
        });
    }

    // UX: Auto-SweetAlert + redirect setelah simpan berhasil
    (function () {
        const params = new URLSearchParams(window.location.search);
        if (params.get('status') !== 'success') return;
        const noRawat = params.get('no_rawat') || '';
        const baseUrl = 'kecantikan.php?no_rawat=' + encodeURIComponent(noRawat);
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil Disimpan!',
                text: 'Data Asesmen Kecantikan berhasil disimpan.',
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