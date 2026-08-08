<?php
/**
 * usg/cetak_usg.php
 * -----------------------------------------------------------------
 * Halaman Cetak Hasil Pemeriksaan USG (Kandungan / Ginekologi)
 * Format disamakan 100% dengan cetak_asesmen.php (Kop Instansi,
 * Identitas Pasien, Burgundy Theme #8B1538, Toolbar, & Layout).
 *
 * Parameter GET:
 *   - type     : kandungan | ginekologi
 *   - no_rawat : nomor kunjungan
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';

wajibLogin();

$pdo = getKoneksi();

$type    = trim($_GET['type'] ?? '');
$noRawat = trim($_GET['no_rawat'] ?? '');

if (!in_array($type, ['kandungan', 'ginekologi']) || $noRawat === '') {
    http_response_code(400);
    die('<p style="font-family:sans-serif;color:red;">Parameter tidak valid. Pastikan <code>type</code> (kandungan|ginekologi) dan <code>no_rawat</code> tersedia.</p>');
}

/* ── Data Instansi dari tabel setting Khanza ──────────────────── */
$setting = [];
try {
    $stSetting = $pdo->query(
        "SELECT nama_instansi, alamat_instansi, kabupaten, propinsi,
                kontak, email,
                CASE WHEN logo IS NOT NULL AND LENGTH(logo) > 100
                     THEN CONCAT('data:image/png;base64,', TO_BASE64(logo))
                     ELSE '' END AS logo_b64
         FROM setting LIMIT 1"
    );
    $setting = $stSetting->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $setting = [];
}
$namaInstansi   = $setting['nama_instansi'] ?? 'Klinik / Rumah Sakit';
$alamatInstansi = trim(($setting['alamat_instansi'] ?? '') . ', ' . ($setting['kabupaten'] ?? '') . ', ' . ($setting['propinsi'] ?? ''), ', ');
$kontakInstansi = $setting['kontak'] ?? '';
$emailInstansi  = $setting['email']  ?? '';
$logoB64        = $setting['logo_b64'] ?? '';

/* ── Data Kunjungan ─────────────────────────────────────────── */
$stmt = $pdo->prepare(
    "SELECT r.no_rawat, r.tgl_registrasi, r.kd_dokter,
            p.nm_pasien, p.no_rkm_medis, p.jk, p.tgl_lahir, p.alamat, p.no_tlp,
            dok.nm_dokter
     FROM reg_periksa r
     JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
     LEFT JOIN dokter dok ON r.kd_dokter = dok.kd_dokter
     WHERE r.no_rawat = ?"
);
$stmt->execute([$noRawat]);
$kunjungan = $stmt->fetch();
if (!$kunjungan) {
    die('<p style="font-family:sans-serif;color:red;">Data kunjungan tidak ditemukan.</p>');
}

$usgUploadUrl = '/webapps/berkasrawat/pages/upload/';

if ($type === 'kandungan') {
    // Ambil data USG kandungan
    $stmtU = $pdo->prepare("SELECT u.*, dok.nm_dokter AS nm_dok_usg
        FROM hasil_pemeriksaan_usg u
        LEFT JOIN dokter dok ON u.kd_dokter = dok.kd_dokter
        WHERE u.no_rawat = ?");
    $stmtU->execute([$noRawat]);
    $usg = $stmtU->fetch();
    if (!$usg) die('<p style="font-family:sans-serif;color:red;">Data USG Kandungan tidak ditemukan.</p>');

    $stmtImg = $pdo->prepare("SELECT photo FROM hasil_pemeriksaan_usg_gambar WHERE no_rawat = ?");
    $stmtImg->execute([$noRawat]);
    $foto = $stmtImg->fetchColumn() ?: '';
    $judul = 'Hasil Pemeriksaan USG Kandungan (Obstetri)';
} else {
    // Ambil data USG ginekologi
    $stmtU = $pdo->prepare("SELECT ug.*, dok.nm_dokter AS nm_dok_usg
        FROM hasil_pemeriksaan_usg_gynecologi ug
        LEFT JOIN dokter dok ON ug.kd_dokter = dok.kd_dokter
        WHERE ug.no_rawat = ?");
    $stmtU->execute([$noRawat]);
    $usg = $stmtU->fetch();
    if (!$usg) die('<p style="font-family:sans-serif;color:red;">Data USG Ginekologi tidak ditemukan.</p>');

    $stmtImg = $pdo->prepare("SELECT photo FROM hasil_pemeriksaan_usg_gynecologi_gambar WHERE no_rawat = ?");
    $stmtImg->execute([$noRawat]);
    $foto = $stmtImg->fetchColumn() ?: '';
    $judul = 'Hasil Pemeriksaan USG Ginekologi';
}

function val($arr, $key, $default = '-') {
    $v = $arr[$key] ?? '';
    return ($v !== '' && $v !== null) ? htmlspecialchars($v) : $default;
}
function row2($label, $value) {
    return '<tr><td class="lbl">' . htmlspecialchars($label) . '</td><td class="val">' . $value . '</td></tr>';
}
function row4($l1, $v1, $l2, $v2) {
    return '<tr>'
        . '<td class="lbl">' . htmlspecialchars($l1) . '</td><td class="val">' . $v1 . '</td>'
        . '<td class="lbl">' . htmlspecialchars($l2) . '</td><td class="val">' . $v2 . '</td>'
        . '</tr>';
}

/* ── Umur pasien ─────────────────────────────────────────── */
$tglLahir = $kunjungan['tgl_lahir'] ?? '';
$umur = '-';
if ($tglLahir) {
    $diff = (new DateTime())->diff(new DateTime($tglLahir));
    $umur = $diff->y . ' th ' . $diff->m . ' bln';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cetak <?= htmlspecialchars($judul) ?> — <?= htmlspecialchars($kunjungan['nm_pasien']) ?></title>
<style>
/* ─── GOOGLE FONT ───────────────────────────────────── */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap');

/* ─── RESET ────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ─── BASE ─────────────────────────────────────────── */
body {
    font-family: 'Inter', 'Arial', 'Helvetica Neue', sans-serif;
    color: #111;
    line-height: 1.55;
}

/* ─── TOOLBAR (screen only) ─────────────────────────── */
.toolbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    background: #8B1538;
    color: #fff;
    padding: 9px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    z-index: 999;
    box-shadow: 0 2px 8px rgba(0,0,0,0.25);
}
.toolbar .tb-title { font-size: 14px; font-weight: 700; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.toolbar .tb-sub   { font-size: 12px; opacity: .8; white-space: nowrap; }
.btn-print {
    background: #fff;
    color: #8B1538;
    border: none;
    padding: 7px 18px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    white-space: nowrap;
    flex-shrink: 0;
}
.btn-print:hover { background: #ffe4ef; }
.btn-back {
    background: rgba(255,255,255,.18);
    color: #fff;
    border: 1px solid rgba(255,255,255,.45);
    padding: 6px 13px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
    flex-shrink: 0;
}
.btn-back:hover { background: rgba(255,255,255,.3); }

/* ─── KOP SURAT ─────────────────────────────────────── */
.kop {
    display: flex;
    align-items: center;
    gap: 14px;
    border-bottom: 3px solid #8B1538;
    padding-bottom: 10px;
    margin-bottom: 14px;
}
.kop-logo {
    width: 60px; height: 60px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: #f5e8ee;
    border: 1px solid #E0B0C8;
}
.kop-logo img { width: 100%; height: 100%; object-fit: contain; }
.kop-logo-fallback {
    width: 60px; height: 60px;
    border-radius: 8px;
    background: #8B1538;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 22px; font-weight: 900;
    flex-shrink: 0;
}
.kop-text { flex: 1; min-width: 0; }
.kop-text h1 {
    font-weight: 900;
    color: #8B1538;
    line-height: 1.2;
}
.kop-text .kop-sub { color: #555; margin-top: 3px; }
.kop-right { text-align: right; color: #555; line-height: 1.7; flex-shrink: 0; }

/* ─── JUDUL DOKUMEN ─────────────────────────────────── */
.doc-title {
    text-align: center;
    font-weight: 900;
    color: #8B1538;
    text-transform: uppercase;
    letter-spacing: .06em;
    border: 2px solid #8B1538;
    padding: 7px 12px;
    margin-bottom: 14px;
    border-radius: 4px;
}

/* ─── IDENTITAS PASIEN ──────────────────────────────── */
.identitas {
    background: #FFF8FB;
    border: 1px solid #E0B0C8;
    border-radius: 6px;
    padding: 10px 14px;
    margin-bottom: 14px;
}
.identitas-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2px 16px;
}
.id-row { display: flex; gap: 6px; padding: 2px 0; }
.id-key { font-weight: 700; min-width: 110px; color: #5a1a32; }
.id-val { color: #111; }

/* ─── SECTION ───────────────────────────────────────── */
.section {
    margin-bottom: 12px;
    page-break-inside: avoid;
}
.section-title {
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: #fff;
    background: #8B1538;
    padding: 4px 10px;
    border-radius: 3px 3px 0 0;
    margin-bottom: 0;
}
.section-body {
    border: 1px solid #DDD;
    border-top: none;
    border-radius: 0 0 4px 4px;
}

/* ─── DATA TABLE ────────────────────────────────────── */
.dtable {
    width: 100%;
    border-collapse: collapse;
}
.dtable td { padding: 5px 10px; border-bottom: 1px solid #EEE; vertical-align: top; }
.dtable tr:last-child td { border-bottom: none; }
.dtable .lbl {
    width: 33%;
    font-weight: 600;
    color: #5a1a32;
    background: #FFF4F8;
}
.dtable .val { color: #111; }

/* ─── FOTO LAMPIRAN USG ─────────────────────────────── */
.foto-wrap {
    text-align: center;
    padding: 12px;
}
.foto-wrap img {
    max-width: 380px;
    max-height: 280px;
    border-radius: 6px;
    border: 1px solid #E0B0C8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.foto-caption {
    font-size: 11px;
    color: #666;
    margin-top: 6px;
}

/* ─── TTD AREA ────────────────────────────────────────── */
.ttd-section {
    display: flex;
    justify-content: flex-end;
    gap: 20px;
    margin-top: 20px;
    page-break-inside: avoid;
}
.ttd-box {
    width: 230px;
    text-align: center;
    border: 1px solid #DDD;
    border-radius: 6px;
    padding: 10px;
}
.ttd-box .ttd-label { font-weight: 700; color: #5a1a32; margin-bottom: 6px; }
.ttd-blank { height: 65px; border-bottom: 1px solid #333; margin: 10px 15px 4px; }
.ttd-box .ttd-name  { font-weight: 600; margin-top: 4px; }

/* ─── FOOTER ──────────────────────────────────────────── */
.print-footer {
    margin-top: 20px;
    border-top: 1px solid #DDD;
    padding-top: 8px;
    color: #888;
    text-align: center;
}

/* =================================================================
   @media screen — Preview Monitor
   ================================================================= */
@media screen {
    body {
        font-size: 13px;
        background: #edf0f5;
        padding-top: 52px;
    }
    .print-page {
        max-width: 800px;
        width: 100%;
        background: #fff;
        margin: 20px auto;
        padding: 24px 28px;
        box-shadow: 0 4px 24px rgba(0,0,0,0.12);
        border-radius: 8px;
    }
    .kop-text h1   { font-size: 17px; }
    .kop-text .kop-sub { font-size: 12px; }
    .kop-right     { font-size: 12px; }
    .doc-title     { font-size: 15px; }
    .identitas     { font-size: 12.5px; }
    .section-title { font-size: 11.5px; }
    .dtable td     { font-size: 12px; }
    .ttd-box .ttd-label { font-size: 12px; }
    .ttd-box .ttd-name  { font-size: 12px; }
    .print-footer  { font-size: 11px; }

    @media (max-width: 640px) {
        .print-page { padding: 16px; margin: 10px; border-radius: 6px; }
        .identitas-grid { grid-template-columns: 1fr; }
        .kop { flex-wrap: wrap; }
        .kop-right { text-align: left; }
    }
}

/* =================================================================
   @media print — Kertas A4
   ================================================================= */
@media print {
    @page {
        size: A4 portrait;
        margin: 12mm 14mm 12mm 14mm;
    }
    .toolbar { display: none !important; }
    body {
        font-size: 10pt;
        background: #fff !important;
        padding-top: 0 !important;
        color: #000;
    }
    .print-page {
        width: 100%;
        max-width: 100%;
        padding: 0;
        margin: 0;
        box-shadow: none;
    }
    .kop-text h1   { font-size: 14pt; }
    .kop-text .kop-sub { font-size: 8.5pt; }
    .kop-right     { font-size: 8pt; }
    .doc-title     { font-size: 12pt; }
    .identitas     { font-size: 9pt; }
    .section-title { font-size: 8.5pt; }
    .dtable td     { font-size: 9pt; padding: 3px 6px; }
    .ttd-box .ttd-label { font-size: 8.5pt; }
    .ttd-box .ttd-name  { font-size: 8.5pt; }
    .print-footer  { font-size: 7.5pt; }
    .section       { page-break-inside: avoid; }
    .ttd-section   { page-break-inside: avoid; }
    .identitas     { page-break-inside: avoid; }
}
</style>
</head>
<body>

<!-- ─── TOOLBAR (Hidden on Print) ──────────────────────────── -->
<div class="toolbar no-print">
    <span class="tb-title">🖨️ <?= htmlspecialchars($judul) ?></span>
    <span class="tb-sub"><?= htmlspecialchars($kunjungan['nm_pasien']) ?> / <?= htmlspecialchars($noRawat) ?></span>
    <a href="javascript:history.back()" class="btn-back">← Kembali</a>
    <button class="btn-print" onclick="window.print()">🖨️ Cetak / Print</button>
</div>

<!-- ─── PRINT PAGE ─────────────────────────────────────────── -->
<div class="print-page">

    <!-- KOP SURAT DINAMIS -->
    <div class="kop">
        <?php if ($logoB64): ?>
            <div class="kop-logo">
                <img src="<?= $logoB64 ?>" alt="Logo <?= htmlspecialchars($namaInstansi) ?>">
            </div>
        <?php else: ?>
            <div class="kop-logo-fallback"><?= mb_strtoupper(mb_substr($namaInstansi, 0, 1)) ?></div>
        <?php endif; ?>

        <div class="kop-text">
            <h1><?= htmlspecialchars($namaInstansi) ?></h1>
            <div class="kop-sub">
                <?php if ($alamatInstansi): ?>
                    📍 <?= htmlspecialchars($alamatInstansi) ?>
                <?php endif; ?>
                <?php if ($kontakInstansi): ?>
                    &nbsp;&bull;&nbsp;☎ <?= htmlspecialchars($kontakInstansi) ?>
                <?php endif; ?>
                <?php if ($emailInstansi): ?>
                    &nbsp;&bull;&nbsp;✉ <?= htmlspecialchars($emailInstansi) ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="kop-right">
            <div><strong>No. Kunjungan:</strong> <?= htmlspecialchars($noRawat) ?></div>
            <div><strong>Tgl. Registrasi:</strong> <?= htmlspecialchars(date('d/m/Y', strtotime($kunjungan['tgl_registrasi']))) ?></div>
            <div><strong>Dokter:</strong> <?= htmlspecialchars($usg['nm_dok_usg'] ?? $kunjungan['nm_dokter'] ?? '-') ?></div>
            <div><strong>Dicetak:</strong> <?= date('d/m/Y H:i') ?></div>
        </div>
    </div>

    <!-- JUDUL DOKUMEN -->
    <div class="doc-title"><?= htmlspecialchars($judul) ?></div>

    <!-- IDENTITAS PASIEN -->
    <div class="identitas">
        <div style="font-weight:700; font-size:10pt; color:#8B1538; margin-bottom:7px;">📋 Identitas Pasien</div>
        <div class="identitas-grid">
            <div>
                <div class="id-row"><span class="id-key">Nama Pasien</span><span class="id-val">: <strong><?= htmlspecialchars($kunjungan['nm_pasien']) ?></strong></span></div>
                <div class="id-row"><span class="id-key">No. RM</span><span class="id-val">: <?= htmlspecialchars($kunjungan['no_rkm_medis']) ?></span></div>
                <div class="id-row"><span class="id-key">Jenis Kelamin</span><span class="id-val">: <?= $kunjungan['jk'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></span></div>
            </div>
            <div>
                <div class="id-row"><span class="id-key">Tanggal Lahir</span><span class="id-val">: <?= $tglLahir ? date('d/m/Y', strtotime($tglLahir)) : '-' ?></span></div>
                <div class="id-row"><span class="id-key">Umur</span><span class="id-val">: <?= $umur ?></span></div>
                <div class="id-row"><span class="id-key">No. Telp</span><span class="id-val">: <?= htmlspecialchars($kunjungan['no_tlp'] ?? '-') ?></span></div>
            </div>
        </div>
    </div>

<?php if ($type === 'kandungan'): ?>
    <!-- ─── USG KANDUNGAN ──────────────────────────────────────── -->
    <div class="section">
        <div class="section-title">Informasi Klinis & Rujukan</div>
        <div class="section-body">
            <table class="dtable">
                <?= row4('Dokter Pemeriksa', val($usg, 'nm_dok_usg'), 'Tgl USG', date('d/m/Y H:i', strtotime($usg['tanggal']))) ?>
                <?= row4('Diagnosa Klinis', val($usg, 'diagnosa_klinis'), 'Kiriman Dari', val($usg, 'kiriman_dari')) ?>
                <?= row4('HTA / HPHT', val($usg, 'hta'), 'Jenis Presentasi', val($usg, 'jenis_prestasi')) ?>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Biometri & Pengukuran Janin (USG Biometry)</div>
        <div class="section-body">
            <table class="dtable">
                <?= row4('Kantong Gestasi (GS)', val($usg, 'kantong_gestasi') . ' mm', 'Crown-Rump Length (CRL)', val($usg, 'ukuran_bokongkepala') . ' mm') ?>
                <?= row4('Diameter Biparietal (BPD)', val($usg, 'diameter_biparietal') . ' mm', 'Panjang Femur (FL)', val($usg, 'panjang_femur') . ' mm') ?>
                <?= row4('Lingkar Abdomen (AC)', val($usg, 'lingkar_abdomen') . ' mm', 'Tafsiran Berat Janin (EFW)', val($usg, 'tafsiran_berat_janin') . ' gram') ?>
                <?= row2('Usia Kehamilan (GA)', val($usg, 'usia_kehamilan')) ?>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Kondisi Lingkungan & Anatomi Janin</div>
        <div class="section-body">
            <table class="dtable">
                <?= row4('Implantasi Plasenta', val($usg, 'plasenta_berimplatansi'), 'Maturitas Plasenta', 'Grade ' . val($usg, 'derajat_maturitas')) ?>
                <?= row4('Jumlah Air Ketuban', val($usg, 'jumlah_air_ketuban'), 'Indeks Cairan Ketuban (AFI)', val($usg, 'indek_cairan_ketuban')) ?>
                <?= row4('Kelainan Kongenital', val($usg, 'kelainan_kongenital'), 'Peluang Jenis Kelamin', val($usg, 'peluang_sex')) ?>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- ─── USG GINEKOLOGI ────────────────────────────────────── -->
    <div class="section">
        <div class="section-title">Informasi Klinis & Rujukan</div>
        <div class="section-body">
            <table class="dtable">
                <?= row4('Dokter Pemeriksa', val($usg, 'nm_dok_usg'), 'Tgl USG', date('d/m/Y H:i', strtotime($usg['tanggal']))) ?>
                <?= row4('Diagnosa Klinis', val($usg, 'diagnosa_klinis'), 'Kiriman Dari', val($usg, 'kiriman_dari')) ?>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Hasil Pemeriksaan Organ Ginekologi</div>
        <div class="section-body">
            <table class="dtable">
                <?= row2('Uterus', nl2br(val($usg, 'uterus'))) ?>
                <?= row2('Parametrium', nl2br(val($usg, 'parametrium'))) ?>
                <?= row2('Ovarium', nl2br(val($usg, 'ovarium'))) ?>
                <?= row2('Doppler', nl2br(val($usg, 'doppler'))) ?>
            </table>
        </div>
    </div>
<?php endif; ?>

    <!-- ─── KESIMPULAN ────────────────────────────────────────── -->
    <div class="section">
        <div class="section-title">Kesimpulan Hasil USG</div>
        <div class="section-body">
            <table class="dtable">
                <?= row2('Kesimpulan', nl2br(val($usg, 'kesimpulan'))) ?>
            </table>
        </div>
    </div>

    <!-- ─── FOTO LAMPIRAN USG ──────────────────────────────────── -->
    <?php if ($foto !== ''): ?>
    <div class="section">
        <div class="section-title">Foto Lampiran USG</div>
        <div class="section-body">
            <div class="foto-wrap">
                <img src="<?= $usgUploadUrl . htmlspecialchars(basename($foto)) ?>" alt="Foto USG">
                <div class="foto-caption">Foto Lampiran USG — <?= htmlspecialchars($kunjungan['nm_pasien']) ?> (<?= date('d/m/Y H:i', strtotime($usg['tanggal'])) ?>)</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ─── TANDA TANGAN ───────────────────────────────────────── -->
    <div class="ttd-section">
        <div class="ttd-box">
            <div class="ttd-label">Dokter Pemeriksa USG</div>
            <div class="ttd-blank"></div>
            <div class="ttd-name"><strong><?= htmlspecialchars($usg['nm_dok_usg'] ?? $kunjungan['nm_dokter'] ?? '-') ?></strong></div>
        </div>
    </div>

    <!-- ─── FOOTER ────────────────────────────────────────────── -->
    <div class="print-footer">
        Dokumen ini dicetak secara otomatis melalui Sistem Informasi SIMKlinik pada <?= date('d/m/Y H:i:s') ?>.
    </div>

</div>

</body>
</html>
