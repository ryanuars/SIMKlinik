<?php
/**
 * asesmen/kebidanan-medis.php
 * -----------------------------------------------------------------
 * Form Asesmen Medis Kebidanan (dokter) →
 * tabel: penilaian_medis_ralan_kandungan
 * PK: no_rawat (satu rawat jalan satu asesmen awal medis)
 * -----------------------------------------------------------------
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

// Ambil data kunjungan
$stmt = $pdo->prepare(
    "SELECT r.no_rawat, r.tgl_registrasi, r.kd_dokter,
            p.nm_pasien, p.no_rkm_medis, p.jk, p.tgl_lahir,
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

// Ambil data existing asesmen medis (1 Rawat Jalan = 1 entry)
$stmtGet = $pdo->prepare("SELECT * FROM penilaian_medis_ralan_kandungan WHERE no_rawat = ?");
$stmtGet->execute([$noRawat]);
$prefill = $stmtGet->fetch() ?: [];
$hasData = !empty($prefill);

// Ambil riwayat asesmen medis kebidanan & kandungan pasien ini
$stmtRiwayat = $pdo->prepare(
    "SELECT m.no_rawat, m.tanggal, m.keluhan_utama, m.diagnosis, m.tata, dok.nm_dokter
     FROM penilaian_medis_ralan_kandungan m
     INNER JOIN reg_periksa r ON m.no_rawat = r.no_rawat
     LEFT JOIN dokter dok ON m.kd_dokter = dok.kd_dokter
     WHERE r.no_rkm_medis = ?
     ORDER BY m.tanggal DESC"
);
$stmtRiwayat->execute([$kunjungan['no_rkm_medis']]);
$daftarRiwayat = $stmtRiwayat->fetchAll();

$error  = '';
$sukses = false;

$sudahBayar = isSudahBayar($noRawat, $pdo);

$valTgl = !empty($prefill['tanggal']) ? date('Y-m-d', strtotime($prefill['tanggal'])) : date('Y-m-d');
$valJam = !empty($prefill['tanggal']) ? date('H:i', strtotime($prefill['tanggal'])) : date('H:i');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($sudahBayar) {
        $error = 'Peringatan: Data tidak dapat disimpan karena pasien sudah melakukan pembayaran (LUNAS).';
    } else {
        $tglP    = trim($_POST['tgl_perawatan'] ?? date('Y-m-d'));
        $jamP    = trim($_POST['jam_rawat'] ?? date('H:i:s'));
        if (strlen($jamP) === 5) $jamP .= ':00';
        $tanggal = $tglP . ' ' . $jamP;

        $kdDokter     = $_SESSION['kd_dokter'] ?? $kunjungan['kd_dok'] ?? '';
    
    $anamnesis    = $_POST['anamnesis'] ?? 'Autoanamnesis';
    $hubungan     = trim($_POST['hubungan'] ?? '');
    $keluhan_utama= trim($_POST['keluhan_utama'] ?? '');
    $rps          = trim($_POST['rps'] ?? '');
    $rpk          = trim($_POST['rpk'] ?? '');
    $rpd          = trim($_POST['rpd'] ?? '');
    $rpo          = trim($_POST['rpo'] ?? '');
    $alergi       = trim($_POST['alergi'] ?? '');
    $keadaan      = $_POST['keadaan'] ?? 'Sehat';
    $gcs          = trim($_POST['gcs'] ?? '');
    $kesadaran    = $_POST['kesadaran'] ?? 'Composmentis';
    $td           = trim($_POST['td'] ?? '');
    $nadi         = trim($_POST['nadi'] ?? '');
    $rr           = trim($_POST['rr'] ?? '');
    $suhu         = trim($_POST['suhu'] ?? '');
    $spo          = trim($_POST['spo'] ?? '');
    $bb           = trim($_POST['bb'] ?? '');
    $tb           = trim($_POST['tb'] ?? '');
    $kepala       = $_POST['kepala'] ?? 'Normal';
    $mata         = $_POST['mata'] ?? 'Normal';
    $gigi         = $_POST['gigi'] ?? 'Normal';
    $tht          = $_POST['tht'] ?? 'Normal';
    $thoraks      = $_POST['thoraks'] ?? 'Normal';
    $abdomen      = $_POST['abdomen'] ?? 'Normal';
    $ekstremitas  = $_POST['ekstremitas'] ?? 'Normal';
    $genital      = $_POST['genital'] ?? 'Normal';
    $kulit        = $_POST['kulit'] ?? 'Normal';
    $ket_fisik    = trim($_POST['ket_fisik'] ?? '');
    $tfu          = trim($_POST['tfu'] ?? '');
    $tbj          = trim($_POST['tbj'] ?? '');
    $his          = trim($_POST['his'] ?? '');
    $kontraksi    = $_POST['kontraksi'] ?? 'Ada';
    $djj          = trim($_POST['djj'] ?? '');
    $inspeksi     = trim($_POST['inspeksi'] ?? '');
    $inspekulo    = trim($_POST['inspekulo'] ?? '');
    $vt           = trim($_POST['vt'] ?? '');
    $rt           = trim($_POST['rt'] ?? '');
    $ultra        = trim($_POST['ultra'] ?? '');
    $kardio       = trim($_POST['kardio'] ?? '');
    $lab          = trim($_POST['lab'] ?? '');
    $diagnosis    = trim($_POST['diagnosis'] ?? '');
    $tata         = trim($_POST['tata'] ?? '');
    $konsul       = trim($_POST['konsul'] ?? '');

    if ($keluhan_utama === '') {
        $error = 'Keluhan Utama tidak boleh kosong.';
    } else {
        try {
            if ($hasData) {
                // Update
                $stmtUp = $pdo->prepare(
                    "UPDATE penilaian_medis_ralan_kandungan SET
                        tanggal=?, kd_dokter=?, anamnesis=?, hubungan=?, keluhan_utama=?,
                        rps=?, rpk=?, rpd=?, rpo=?, alergi=?, keadaan=?, gcs=?, kesadaran=?,
                        td=?, nadi=?, rr=?, suhu=?, spo=?, bb=?, tb=?, kepala=?, mata=?,
                        gigi=?, tht=?, thoraks=?, abdomen=?, ekstremitas=?, genital=?, kulit=?,
                        ket_fisik=?, tfu=?, tbj=?, his=?, kontraksi=?, djj=?, inspeksi=?,
                        inspekulo=?, vt=?, rt=?, ultra=?, kardio=?, lab=?, diagnosis=?,
                        tata=?, konsul=?
                    WHERE no_rawat=?"
                );
                $stmtUp->execute([
                    $tanggal, $kdDokter, $anamnesis, $hubungan, $keluhan_utama,
                    $rps, $rpk, $rpd, $rpo, $alergi, $keadaan, $gcs, $kesadaran,
                    $td, $nadi, $rr, $suhu, $spo, $bb, $tb, $kepala, $mata,
                    $gigi, $tht, $thoraks, $abdomen, $ekstremitas, $genital, $kulit,
                    $ket_fisik, $tfu, $tbj, $his, $kontraksi, $djj, $inspeksi,
                    $inspekulo, $vt, $rt, $ultra, $kardio, $lab, $diagnosis,
                    $tata, $konsul, $noRawat
                ]);
            } else {
                // Insert
                $colsIns = [
                    'no_rawat', 'tanggal', 'kd_dokter', 'anamnesis', 'hubungan', 'keluhan_utama',
                    'rps', 'rpk', 'rpd', 'rpo', 'alergi', 'keadaan', 'gcs', 'kesadaran',
                    'td', 'nadi', 'rr', 'suhu', 'spo', 'bb', 'tb', 'kepala', 'mata',
                    'gigi', 'tht', 'thoraks', 'abdomen', 'ekstremitas', 'genital', 'kulit',
                    'ket_fisik', 'tfu', 'tbj', 'his', 'kontraksi', 'djj', 'inspeksi',
                    'inspekulo', 'vt', 'rt', 'ultra', 'kardio', 'lab', 'diagnosis',
                    'tata', 'konsul'
                ];
                $valsIns = [
                    $noRawat, $tanggal, $kdDokter, $anamnesis, $hubungan, $keluhan_utama,
                    $rps, $rpk, $rpd, $rpo, $alergi, $keadaan, $gcs, $kesadaran,
                    $td, $nadi, $rr, $suhu, $spo, $bb, $tb, $kepala, $mata,
                    $gigi, $tht, $thoraks, $abdomen, $ekstremitas, $genital, $kulit,
                    $ket_fisik, $tfu, $tbj, $his, $kontraksi, $djj, $inspeksi,
                    $inspekulo, $vt, $rt, $ultra, $kardio, $lab, $diagnosis,
                    $tata, $konsul
                ];
                $sqlIns = "INSERT INTO penilaian_medis_ralan_kandungan (`" . implode('`,`', $colsIns) . "`) VALUES (" . implode(',', array_fill(0, count($colsIns), '?')) . ")";
                $pdo->prepare($sqlIns)->execute($valsIns);
            }
            header('Location: kebidanan-medis.php?no_rawat=' . urlencode($noRawat) . '&status=success');
            exit;
        } catch (Throwable $e) {
            error_log('[kebidanan-medis.php] ' . $e->getMessage());
            $error = 'Gagal menyimpan: ' . $e->getMessage();
        }
        }
    }
}

// Ambil info vital sign dari SOAP perawat/bidan untuk referensi jika ada
$stmtRef = $pdo->prepare(
    "SELECT suhu_tubuh, tensi, nadi, respirasi, tinggi, berat, spo2, gcs, kesadaran, keluhan 
     FROM pemeriksaan_ralan 
     WHERE no_rawat = ? 
     ORDER BY tgl_perawatan DESC, jam_rawat DESC LIMIT 1"
);
$stmtRef->execute([$noRawat]);
$refSoap = $stmtRef->fetch();

$halamanAktif = 'asesmen';
$judulHalaman = 'Asesmen Awal Medis Kebidanan & Kandungan';
$baseAsset    = '../';
require __DIR__ . '/../lib/layout_header.php';
?>
<style>
.fm2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.fm3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
.fm4 { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:12px; }
.comp-box {
    background: #FDF6F8;
    border: 1.5px solid var(--color-border);
    border-radius: 8px;
    padding: 14px 16px;
    margin-bottom: 12px;
}
.sec-title {
    font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.08em;
    color:var(--color-primary); margin:18px 0 10px; padding-bottom:5px;
    border-bottom:1.5px solid var(--color-border);
}
.badge-ref {
    display: inline-block; background: #e0f2fe; color: #0369a1; 
    font-size: 11px; padding: 2px 6px; border-radius: 4px; margin-bottom: 10px;
    cursor: pointer; transition: 0.2s;
}
.badge-ref:hover { background: #bae6fd; }
    /* ─── CETAK / PRINT STYLING (A4 Portrait Standar) ───────────────── */
    @media print {
        @page {
            size: A4 portrait;
            margin: 12mm 15mm 12mm 15mm;
        }

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
        .badge-ref {
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

        input[type="text"], input[type="number"], select, textarea {
            border: none !important;
            border-bottom: 1px dashed #333 !important;
            background: transparent !important;
            color: #000 !important;
            box-shadow: none !important;
        }
    }
</style>

<!-- Breadcrumb Navigasi -->
<div style="margin-bottom:14px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; font-size:13px;" class="no-print">
    <div style="display:flex; align-items:center; gap:10px;">
        <a href="pilih.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-back">← Kembali ke Menu Asesmen</a>
        <span class="text-muted">&bull; Kunjungan: <code><?= htmlspecialchars($noRawat) ?></code> &bull; Pasien: <strong><?= htmlspecialchars($kunjungan['nm_pasien']) ?></strong></span>
    </div>
    <div>
        <a href="cetak_asesmen.php?type=kebidanan-medis&no_rawat=<?= urlencode($noRawat) ?>" target="_blank"
           class="btn btn-outline" style="font-size:12.5px; padding:6px 14px; display:inline-flex; align-items:center; gap:6px; text-decoration:none;">
            🖨️ Cetak Hasil Asesmen
        </a>
    </div>
</div>

<?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
    <div class="alert alert-success no-print" id="alert-simpan-sukses">✔ Data Asesmen Awal Medis berhasil disimpan.</div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger no-print"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($daftarRiwayat): ?>
<div class="card card-riwayat-container" style="margin-bottom:15px;">
    <p class="card-title">Riwayat Asesmen Medis Kebidanan &amp; Kandungan</p>
    <div style="overflow-x:auto;">
    <table style="width:100%; border-collapse:collapse; font-size:12.5px;">
        <thead>
            <tr style="background:var(--color-primary); color:#fff;">
                <th style="padding:7px 10px; text-align:left;">Tanggal</th>
                <th style="padding:7px 10px; text-align:left;">No. Rawat</th>
                <th style="padding:7px 10px; text-align:left;">Dokter</th>
                <th style="padding:7px 10px; text-align:left;">Keluhan Utama</th>
                <th style="padding:7px 10px; text-align:left;">Diagnosis</th>
                <th style="padding:7px 10px; text-align:left;">Tata Laksana</th>
                <th style="padding:7px 10px; text-align:center; width:130px;" class="col-aksi">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($daftarRiwayat as $r): ?>
            <tr style="border-bottom:1px solid var(--color-border);">
                <td style="padding:6px 10px;"><?= htmlspecialchars(date('d-m-Y H:i', strtotime($r['tanggal']))) ?></td>
                <td style="padding:6px 10px;"><code><?= htmlspecialchars($r['no_rawat']) ?></code></td>
                <td style="padding:6px 10px;"><?= htmlspecialchars($r['nm_dokter'] ?? '-') ?></td>
                <td style="padding:6px 10px;"><?= htmlspecialchars(mb_substr($r['keluhan_utama'] ?? '-', 0, 40)) ?><?= mb_strlen($r['keluhan_utama'] ?? '') > 40 ? '…' : '' ?></td>
                <td style="padding:6px 10px;"><?= htmlspecialchars(mb_substr($r['diagnosis'] ?? '-', 0, 40)) ?><?= mb_strlen($r['diagnosis'] ?? '') > 40 ? '…' : '' ?></td>
                <td style="padding:6px 10px;"><?= htmlspecialchars(mb_substr($r['tata'] ?? '-', 0, 40)) ?><?= mb_strlen($r['tata'] ?? '') > 40 ? '…' : '' ?></td>
                <td style="padding:6px 10px; text-align:center;" class="col-aksi">
                    <a href="kebidanan-medis.php?no_rawat=<?= urlencode($r['no_rawat']) ?>"
                       class="btn btn-outline" style="font-size:12px; padding:3px 8px; text-decoration:none;">Edit</a>
                    <a href="cetak_asesmen.php?type=kebidanan-medis&no_rawat=<?= urlencode($r['no_rawat']) ?>" target="_blank"
                        class="btn btn-outline btn-print-act"
                        style="font-size:11.5px; padding:3px 8px; margin-left:4px; border-color:var(--color-primary); color:var(--color-primary); text-decoration:none;" title="Cetak Asesmen">🖨️ Cetak</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <p class="card-title"><?= $hasData ? 'Edit Asesmen Awal Medis' : 'Isi Asesmen Awal Medis Baru' ?></p>
    <p class="text-mute" style="margin-top:-8px; margin-bottom: 15px;">Dokter Pemeriksa: <strong><?= htmlspecialchars($kunjungan['nm_dokter']) ?></strong></p>

    <?php if ($refSoap): ?>
        <div class="badge-ref" onclick="copyFromSoap()">
            ⚡ Klik di sini untuk memuat data vital sign dan keluhan dari SOAP Perawat terakhir
        </div>
    <?php endif; ?>

    <?php if ($sudahBayar): ?>
        <div class="alert alert-danger" style="margin-bottom:15px; font-size:12.5px;">
            🔒 <strong>Data dikunci:</strong> Pembayaran telah diselesaikan. Anda tidak dapat menambah atau mengubah asesmen medis ini.
        </div>
    <?php endif; ?>

    <form method="post" id="formMedis">
        <fieldset <?= $sudahBayar ? 'disabled' : '' ?> style="border:0; padding:0; margin:0;">
        <input type="hidden" name="no_rawat" value="<?= htmlspecialchars($noRawat) ?>">

        <!-- TANGGAL & JAM ASESMEN -->
        <div class="fm2" style="max-width:480px; margin-bottom:16px;">
            <div>
                <label for="tgl_perawatan">Tanggal Asesmen *</label>
                <input type="date" id="tgl_perawatan" name="tgl_perawatan" value="<?= $valTgl ?>" required>
            </div>
            <div>
                <label for="jam_rawat">Jam Asesmen *</label>
                <input type="time" id="jam_rawat" name="jam_rawat" value="<?= $valJam ?>" required>
            </div>
        </div>

        <!-- ANAMNESIS -->
        <p class="sec-title">1. Anamnesis</p>
        <div class="fm2">
            <div>
                <label for="anamnesis">Anamnesis *</label>
                <select id="anamnesis" name="anamnesis">
                    <option value="Autoanamnesis" <?= ($prefill['anamnesis'] ?? 'Autoanamnesis') === 'Autoanamnesis' ? 'selected' : '' ?>>Autoanamnesis (Wawancara Pasien Sendiri)</option>
                    <option value="Alloanamnesis" <?= ($prefill['anamnesis'] ?? '') === 'Alloanamnesis' ? 'selected' : '' ?>>Alloanamnesis (Wawancara dengan Keluarga/Pengantar)</option>
                </select>
            </div>
            <div>
                <label for="hubungan">Hubungan (Jika Alloanamnesis)</label>
                <input type="text" id="hubungan" name="hubungan" placeholder="Suami, Ibu, Teman, dll."
                       value="<?= htmlspecialchars($prefill['hubungan'] ?? '') ?>">
            </div>
        </div>

        <label for="keluhan_utama" style="margin-top:10px;">Keluhan Utama *</label>
        <textarea id="keluhan_utama" name="keluhan_utama" rows="2" required><?= htmlspecialchars($prefill['keluhan_utama'] ?? '') ?></textarea>

        <div class="fm2">
            <div>
                <label for="rps">Riwayat Penyakit Sekarang (RPS)</label>
                <textarea id="rps" name="rps" rows="3" placeholder="Jelaskan perjalanan penyakit pasien..."><?= htmlspecialchars($prefill['rps'] ?? '') ?></textarea>
            </div>
            <div>
                <label for="rpk">Riwayat Penyakit Keluarga (RPK)</label>
                <textarea id="rpk" name="rpk" rows="3" placeholder="Diabetes, darah tinggi, keturunan kembar, dll."><?= htmlspecialchars($prefill['rpk'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="fm2" style="margin-top:10px;">
            <div>
                <label for="rpd">Riwayat Penyakit Dahulu (RPD)</label>
                <textarea id="rpd" name="rpd" rows="2" placeholder="Operasi sebelumnya, rawat inap, radang panggul..."><?= htmlspecialchars($prefill['rpd'] ?? '') ?></textarea>
            </div>
            <div>
                <label for="rpo">Riwayat Pengobatan (RPO)</label>
                <textarea id="rpo" name="rpo" rows="2" placeholder="Sebutkan obat-obatan yang dikonsumsi akhir-akhir ini..."><?= htmlspecialchars($prefill['rpo'] ?? '') ?></textarea>
            </div>
        </div>

        <label for="alergi" style="margin-top:10px;">Riwayat Alergi</label>
        <input type="text" id="alergi" name="alergi" placeholder="Makanan, obat, dingin, debu, dll."
               value="<?= htmlspecialchars($prefill['alergi'] ?? '') ?>">

        <!-- STATUS FISIK & TANDA VITAL -->
        <p class="sec-title">2. Keadaan Umum & Tanda Vital</p>
        <div class="comp-box">
            <div class="fm4">
                <div>
                    <label for="keadaan">Keadaan Umum</label>
                    <select id="keadaan" name="keadaan">
                        <?php foreach (['Sehat','Sakit Ringan','Sakit Sedang','Sakit Berat'] as $v): ?>
                            <option value="<?= $v ?>" <?= ($prefill['keadaan'] ?? 'Sehat') === $v ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="kesadaran">Kesadaran</label>
                    <select id="kesadaran" name="kesadaran">
                        <?php foreach (['Composmentis','Apatis','Somnolen','Sopor','Koma','Delirium'] as $v): ?>
                            <option value="<?= $v ?>" <?= ($prefill['kesadaran'] ?? 'Composmentis') === $v ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="gcs">GCS (E, V, M)</label>
                    <input type="text" id="gcs" name="gcs" placeholder="15"
                           value="<?= htmlspecialchars($prefill['gcs'] ?? '') ?>">
                </div>
                <div>
                    <label for="td">Tekanan Darah (TD)</label>
                    <input type="text" id="td" name="td" placeholder="120/80"
                           value="<?= htmlspecialchars($prefill['td'] ?? '') ?>">
                </div>
            </div>

            <div class="fm4" style="margin-top:10px;">
                <div>
                    <label for="nadi">Nadi (x/mnt)</label>
                    <input type="text" id="nadi" name="nadi" placeholder="80"
                           value="<?= htmlspecialchars($prefill['nadi'] ?? '') ?>">
                </div>
                <div>
                    <label for="rr">Respirasi (x/mnt)</label>
                    <input type="text" id="rr" name="rr" placeholder="20"
                           value="<?= htmlspecialchars($prefill['rr'] ?? '') ?>">
                </div>
                <div>
                    <label for="suhu">Suhu (°C)</label>
                    <input type="text" id="suhu" name="suhu" placeholder="36.5"
                           value="<?= htmlspecialchars($prefill['suhu'] ?? '') ?>">
                </div>
                <div>
                    <label for="spo">SpO₂ (%)</label>
                    <input type="text" id="spo" name="spo" placeholder="98"
                           value="<?= htmlspecialchars($prefill['spo'] ?? '') ?>">
                </div>
            </div>

            <div class="fm2" style="max-width:400px; margin-top:10px;">
                <div>
                    <label for="bb">Berat Badan (kg)</label>
                    <input type="text" id="bb" name="bb" placeholder="60"
                           value="<?= htmlspecialchars($prefill['bb'] ?? '') ?>">
                </div>
                <div>
                    <label for="tb">Tinggi Badan (cm)</label>
                    <input type="text" id="tb" name="tb" placeholder="158"
                           value="<?= htmlspecialchars($prefill['tb'] ?? '') ?>">
                </div>
            </div>
        </div>

        <!-- PEMERIKSAAN FISIK UMUM -->
        <p class="sec-title">3. Pemeriksaan Fisik (Head-to-Toe)</p>
        <div class="fm4">
            <div>
                <label for="kepala">Kepala</label>
                <select id="kepala" name="kepala">
                    <?php foreach (['Normal','Abnormal','Tidak Diperiksa'] as $o): ?>
                        <option value="<?= $o ?>" <?= ($prefill['kepala'] ?? 'Normal') === $o ? 'selected' : '' ?>><?= $o ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="mata">Mata</label>
                <select id="mata" name="mata">
                    <?php foreach (['Normal','Abnormal','Tidak Diperiksa'] as $o): ?>
                        <option value="<?= $o ?>" <?= ($prefill['mata'] ?? 'Normal') === $o ? 'selected' : '' ?>><?= $o ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="gigi">Gigi / Mulut</label>
                <select id="gigi" name="gigi">
                    <?php foreach (['Normal','Abnormal','Tidak Diperiksa'] as $o): ?>
                        <option value="<?= $o ?>" <?= ($prefill['gigi'] ?? 'Normal') === $o ? 'selected' : '' ?>><?= $o ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="tht">THT</label>
                <select id="tht" name="tht">
                    <?php foreach (['Normal','Abnormal','Tidak Diperiksa'] as $o): ?>
                        <option value="<?= $o ?>" <?= ($prefill['tht'] ?? 'Normal') === $o ? 'selected' : '' ?>><?= $o ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="fm4" style="margin-top:10px;">
            <div>
                <label for="thoraks">Thoraks (Dada)</label>
                <select id="thoraks" name="thoraks">
                    <?php foreach (['Normal','Abnormal','Tidak Diperiksa'] as $o): ?>
                        <option value="<?= $o ?>" <?= ($prefill['thoraks'] ?? 'Normal') === $o ? 'selected' : '' ?>><?= $o ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="abdomen">Abdomen</label>
                <select id="abdomen" name="abdomen">
                    <?php foreach (['Normal','Abnormal','Tidak Diperiksa'] as $o): ?>
                        <option value="<?= $o ?>" <?= ($prefill['abdomen'] ?? 'Normal') === $o ? 'selected' : '' ?>><?= $o ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="genital">Genital luar</label>
                <select id="genital" name="genital">
                    <?php foreach (['Normal','Abnormal','Tidak Diperiksa'] as $o): ?>
                        <option value="<?= $o ?>" <?= ($prefill['genital'] ?? 'Normal') === $o ? 'selected' : '' ?>><?= $o ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="ekstremitas">Ekstremitas</label>
                <select id="ekstremitas" name="ekstremitas">
                    <?php foreach (['Normal','Abnormal','Tidak Diperiksa'] as $o): ?>
                        <option value="<?= $o ?>" <?= ($prefill['ekstremitas'] ?? 'Normal') === $o ? 'selected' : '' ?>><?= $o ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="fm2" style="margin-top:10px;">
            <div>
                <label for="kulit">Kulit & Integumen</label>
                <select id="kulit" name="kulit">
                    <?php foreach (['Normal','Abnormal','Tidak Diperiksa'] as $o): ?>
                        <option value="<?= $o ?>" <?= ($prefill['kulit'] ?? 'Normal') === $o ? 'selected' : '' ?>><?= $o ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="ket_fisik">Keterangan Khusus Pemeriksaan Fisik</label>
                <input type="text" id="ket_fisik" name="ket_fisik" placeholder="Sebutkan temuan abnormal..."
                       value="<?= htmlspecialchars($prefill['ket_fisik'] ?? '') ?>">
            </div>
        </div>

        <!-- PEMERIKSAAN KEBIDANAN / OBSTETRI -->
        <p class="sec-title">4. Pemeriksaan Kebidanan & Kandungan</p>
        <div class="fm4">
            <div>
                <label for="tfu">TFU (cm)</label>
                <input type="text" id="tfu" name="tfu" placeholder="28 cm"
                       value="<?= htmlspecialchars($prefill['tfu'] ?? '') ?>">
            </div>
            <div>
                <label for="tbj">TBJ (gram)</label>
                <input type="text" id="tbj" name="tbj" placeholder="2500 gr"
                       value="<?= htmlspecialchars($prefill['tbj'] ?? '') ?>">
            </div>
            <div>
                <label for="his">His (Frekuensi & Durasi)</label>
                <input type="text" id="his" name="his" placeholder="3x10' / 40 dtk"
                       value="<?= htmlspecialchars($prefill['his'] ?? '') ?>">
            </div>
            <div>
                <label for="kontraksi">Kontraksi</label>
                <select id="kontraksi" name="kontraksi">
                    <option value="Ada" <?= ($prefill['kontraksi'] ?? 'Ada') === 'Ada' ? 'selected' : '' ?>>Ada</option>
                    <option value="Tidak" <?= ($prefill['kontraksi'] ?? '') === 'Tidak' ? 'selected' : '' ?>>Tidak</option>
                </select>
            </div>
        </div>

        <div class="fm3" style="margin-top:10px;">
            <div>
                <label for="djj">FHR / djj (x/menit)</label>
                <input type="text" id="djj" name="djj" placeholder="140"
                       value="<?= htmlspecialchars($prefill['djj'] ?? '') ?>">
            </div>
            <div>
                <label for="inspeksi">Inspeksi Abdomen</label>
                <input type="text" id="inspeksi" name="inspeksi" placeholder="Striae, Linea nigra, bekas SC..."
                       value="<?= htmlspecialchars($prefill['inspeksi'] ?? '') ?>">
            </div>
            <div>
                <label for="inspekulo">Inspekulo (Pemeriksaan Spekulum)</label>
                <input type="text" id="inspekulo" name="inspekulo" placeholder="Portio licin, livid, fluksus..."
                       value="<?= htmlspecialchars($prefill['inspekulo'] ?? '') ?>">
            </div>
        </div>

        <div class="fm2" style="margin-top:10px;">
            <div>
                <label for="vt">Vaginal Toucher (VT / VT Dalam)</label>
                <input type="text" id="vt" name="vt" placeholder="Pembukaan, penipisan, ketuban..."
                       value="<?= htmlspecialchars($prefill['vt'] ?? '') ?>">
            </div>
            <div>
                <label for="rt">Rectal Toucher (RT - jika diindikasikan)</label>
                <input type="text" id="rt" name="rt" placeholder="Sphincter tonus, ampula, dll."
                       value="<?= htmlspecialchars($prefill['rt'] ?? '') ?>">
            </div>
        </div>

        <!-- PEMERIKSAAN PENUNJANG -->
        <p class="sec-title">5. Pemeriksaan Penunjang (USG, CTG, Lab)</p>
        <div class="fm3">
            <div>
                <label for="ultra">Ultrasonografi (USG)</label>
                <textarea id="ultra" name="ultra" rows="3" placeholder="Fetal biometri, letak plasenta, cairan ketuban..."><?= htmlspecialchars($prefill['ultra'] ?? '') ?></textarea>
            </div>
            <div>
                <label for="kardio">Kardiotokografi (CTG)</label>
                <textarea id="kardio" name="kardio" rows="3" placeholder="Baseline djj, variabilitas, deselerasi..."><?= htmlspecialchars($prefill['kardio'] ?? '') ?></textarea>
            </div>
            <div>
                <label for="lab">Pemeriksaan Lab / Rontgen / Penunjang Lain</label>
                <textarea id="lab" name="lab" rows="3" placeholder="Hb, urine rutin, rapid test, dll."><?= htmlspecialchars($prefill['lab'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- DIAGNOSIS & RENCANA -->
        <p class="sec-title">6. Diagnosis & Tata Laksana</p>
        <label for="diagnosis">Asesmen Medis / Diagnosis Kerja *</label>
        <textarea id="diagnosis" name="diagnosis" rows="2" required placeholder="G1P0A0 Hamil 38 mgg Inpartu kala I fase aktif..."><?= htmlspecialchars($prefill['diagnosis'] ?? '') ?></textarea>

        <div class="fm2">
            <div>
                <label for="tata">Tata Laksana / Rencana Pengobatan</label>
                <textarea id="tata" name="tata" rows="3" placeholder="Infus, obat, observasi, siapkan persalinan..."><?= htmlspecialchars($prefill['tata'] ?? '') ?></textarea>
            </div>
            <div>
                <label for="konsul">Rencana Konsul / Rujukan / Edukasi Pasien</label>
                <textarea id="konsul" name="konsul" rows="3" placeholder="Konsul SpOG lain, rencana SC elektif, rujuk RS tipe B..."><?= htmlspecialchars($prefill['konsul'] ?? '') ?></textarea>
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:20px;">
            <button type="submit" class="btn btn-primary">
                <?= $hasData ? 'Simpan Perubahan Asesmen' : 'Simpan Asesmen Medis' ?>
            </button>
            <a href="pilih.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-outline">Batal</a>
        </div>
        </fieldset>
    </form>
</div>


<script>
function copyFromSoap() {
    <?php if ($refSoap): ?>
    document.getElementById('keluhan_utama').value = <?= json_encode($refSoap['keluhan'] ?? '') ?>;
    document.getElementById('td').value = <?= json_encode($refSoap['tensi'] ?? '') ?>;
    document.getElementById('nadi').value = <?= json_encode($refSoap['nadi'] ?? '') ?>;
    document.getElementById('rr').value = <?= json_encode($refSoap['respirasi'] ?? '') ?>;
    document.getElementById('suhu').value = <?= json_encode($refSoap['suhu_tubuh'] ?? '') ?>;
    document.getElementById('spo').value = <?= json_encode($refSoap['spo2'] ?? '') ?>;
    document.getElementById('bb').value = <?= json_encode($refSoap['berat'] ?? '') ?>;
    document.getElementById('tb').value = <?= json_encode($refSoap['tinggi'] ?? '') ?>;
    document.getElementById('gcs').value = <?= json_encode($refSoap['gcs'] ?? '') ?>;
    document.getElementById('kesadaran').value = <?= json_encode($refSoap['kesadaran'] ?? 'Composmentis') ?>;
    alert("Berhasil menyalin data pemeriksaan awal perawat. Silakan periksa kembali sebelum menyimpan.");
    <?php endif; ?>
}

// UX: Auto-SweetAlert + redirect setelah simpan berhasil
(function () {
    const params = new URLSearchParams(window.location.search);
    if (params.get('status') !== 'success') return;
    const noRawat = params.get('no_rawat') || '';
    const baseUrl = 'kebidanan-medis.php?no_rawat=' + encodeURIComponent(noRawat);
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil Disimpan!',
            text: 'Data Asesmen Awal Medis berhasil disimpan.',
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
