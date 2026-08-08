<?php
/**
 * asesmen/cetak_asesmen.php
 * -----------------------------------------------------------------
 * Halaman Cetak / Print Preview Universal untuk semua jenis asesmen.
 * Parameter GET:
 *   - type   : jenis asesmen (kecantikan|kebidanan-medis|kebidanan-keperawatan|soap|obstetri|ginekologi)
 *   - no_rawat : no rawat pasien
 *   - tgl    : (opsional) filter tanggal untuk asesmen multi-entry (soap/obstetri/ginekologi)
 *   - jam    : (opsional) filter jam untuk asesmen multi-entry
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';

wajibLogin();

$pdo      = getKoneksi();
$type     = trim($_GET['type']     ?? '');
$noRawat  = trim($_GET['no_rawat'] ?? '');
$filterTgl= trim($_GET['tgl']      ?? '');
$filterJam= trim($_GET['jam']      ?? '');

$allowedTypes = ['kecantikan','kebidanan-medis','kebidanan-keperawatan','soap','obstetri','ginekologi','usg-kandungan','usg-ginekologi'];
if ($noRawat === '' || !in_array($type, $allowedTypes)) {
    http_response_code(400);
    die('<p style="font-family:sans-serif;color:red;">Parameter tidak valid. Pastikan <code>type</code> dan <code>no_rawat</code> tersedia.</p>');
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
    // tabel setting tidak ada — fallback aman
    $setting = [];
}
$namaInstansi = $setting['nama_instansi'] ?? 'Klinik / Rumah Sakit';
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

/* ── Ambil data asesmen sesuai type ────────────────────────── */
$data     = [];        // row utama (single-entry types)
$dataList = [];        // banyak row (multi-entry types: soap, obstetri, ginekologi)
$judul    = '';
$titik    = [];        // titik pijatan (kecantikan)

switch ($type) {

    /* ── KECANTIKAN ──────────────────────────────────────────── */
    case 'kecantikan':
        $judul = 'Asesmen Kecantikan & Face Massage';
        $s = $pdo->prepare("SELECT * FROM penilaian_treatment_wajah WHERE no_rawat = ?");
        $s->execute([$noRawat]);
        $data = $s->fetch() ?: [];
        $sT = $pdo->prepare("SELECT pos_x, pos_y, keterangan FROM penilaian_treatment_wajah_titik WHERE no_rawat = ? ORDER BY id ASC");
        $sT->execute([$noRawat]);
        $titik = $sT->fetchAll();
        break;

    /* ── KEBIDANAN MEDIS ─────────────────────────────────────── */
    case 'kebidanan-medis':
        $judul = 'Asesmen Medis Kebidanan & Kandungan';
        $s = $pdo->prepare("SELECT m.*, dok.nm_dokter FROM penilaian_medis_ralan_kandungan m LEFT JOIN dokter dok ON m.kd_dokter=dok.kd_dokter WHERE m.no_rawat = ?");
        $s->execute([$noRawat]);
        $data = $s->fetch() ?: [];
        break;

    /* ── KEBIDANAN KEPERAWATAN ───────────────────────────────── */
    case 'kebidanan-keperawatan':
        $judul = 'Asesmen Keperawatan Kebidanan';
        $s = $pdo->prepare("SELECT k.*, p.nama as nm_petugas FROM penilaian_awal_keperawatan_kebidanan k LEFT JOIN petugas p ON k.nip=p.nip WHERE k.no_rawat = ?");
        $s->execute([$noRawat]);
        $data = $s->fetch() ?: [];
        break;

    /* ── SOAP ────────────────────────────────────────────────── */
    case 'soap':
        $judul = 'Catatan SOAP Perawatan';
        if ($filterTgl !== '' && $filterJam !== '') {
            $s = $pdo->prepare("SELECT s.*, p.nama as nm_petugas FROM pemeriksaan_ralan s LEFT JOIN petugas p ON s.nip=p.nip WHERE s.no_rawat=? AND s.tgl_perawatan=? AND s.jam_rawat=?");
            $s->execute([$noRawat, $filterTgl, $filterJam]);
            $row = $s->fetch();
            $dataList = $row ? [$row] : [];
        } else {
            $s = $pdo->prepare("SELECT s.*, p.nama as nm_petugas FROM pemeriksaan_ralan s LEFT JOIN petugas p ON s.nip=p.nip WHERE s.no_rawat=? ORDER BY s.tgl_perawatan DESC, s.jam_rawat DESC");
            $s->execute([$noRawat]);
            $dataList = $s->fetchAll();
        }
        break;

    /* ── OBSTETRI ────────────────────────────────────────────── */
    case 'obstetri':
        $judul = 'Pemeriksaan Obstetri';
        if ($filterTgl !== '' && $filterJam !== '') {
            $s = $pdo->prepare("SELECT * FROM pemeriksaan_obstetri_ralan WHERE no_rawat=? AND tgl_perawatan=? AND jam_rawat=?");
            $s->execute([$noRawat, $filterTgl, $filterJam]);
            $row = $s->fetch();
            $dataList = $row ? [$row] : [];
        } else {
            $s = $pdo->prepare("SELECT * FROM pemeriksaan_obstetri_ralan WHERE no_rawat=? ORDER BY tgl_perawatan DESC, jam_rawat DESC");
            $s->execute([$noRawat]);
            $dataList = $s->fetchAll();
        }
        break;

    /* ── GINEKOLOGI ──────────────────────────────────────────── */
    case 'ginekologi':
        $judul = 'Pemeriksaan Ginekologi';
        if ($filterTgl !== '' && $filterJam !== '') {
            $s = $pdo->prepare("SELECT * FROM pemeriksaan_ginekologi_ralan WHERE no_rawat=? AND tgl_perawatan=? AND jam_rawat=?");
            $s->execute([$noRawat, $filterTgl, $filterJam]);
            $row = $s->fetch();
            $dataList = $row ? [$row] : [];
        } else {
            $s = $pdo->prepare("SELECT * FROM pemeriksaan_ginekologi_ralan WHERE no_rawat=? ORDER BY tgl_perawatan DESC, jam_rawat DESC");
            $s->execute([$noRawat]);
            $dataList = $s->fetchAll();
        }
        break;

    /* ── USG KANDUNGAN ───────────────────────────────────────── */
    case 'usg-kandungan':
        $judul = 'Hasil Pemeriksaan USG Kandungan (Obstetri)';
        $s = $pdo->prepare("SELECT u.*, dok.nm_dokter AS nm_dok_usg FROM hasil_pemeriksaan_usg u LEFT JOIN dokter dok ON u.kd_dokter=dok.kd_dokter WHERE u.no_rawat=?");
        $s->execute([$noRawat]);
        $data = $s->fetch() ?: [];
        $sImg = $pdo->prepare("SELECT photo FROM hasil_pemeriksaan_usg_gambar WHERE no_rawat=?");
        $sImg->execute([$noRawat]);
        $data['photo'] = $sImg->fetchColumn() ?: '';
        break;

    /* ── USG GINEKOLOGI ──────────────────────────────────────── */
    case 'usg-ginekologi':
        $judul = 'Hasil Pemeriksaan USG Ginekologi';
        $s = $pdo->prepare("SELECT ug.*, dok.nm_dokter AS nm_dok_usg FROM hasil_pemeriksaan_usg_gynecologi ug LEFT JOIN dokter dok ON ug.kd_dokter=dok.kd_dokter WHERE ug.no_rawat=?");
        $s->execute([$noRawat]);
        $data = $s->fetch() ?: [];
        $sImg = $pdo->prepare("SELECT photo FROM hasil_pemeriksaan_usg_gynecologi_gambar WHERE no_rawat=?");
        $sImg->execute([$noRawat]);
        $data['photo'] = $sImg->fetchColumn() ?: '';
        break;
}

/* ── Helpers ───────────────────────────────────────────────── */
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
function yesno($val, $ya='Ya', $tidak='Tidak') {
    return $val === $ya ? '<span class="badge-y">' . htmlspecialchars($ya) . '</span>'
                        : '<span class="badge-n">' . htmlspecialchars($tidak) . '</span>';
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

/* Pastikan background color (titik pijatan) ikut dicetak */
* { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }


/* ─── BASE (shared) ────────────────────────────────── */
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

/* ─── KOP ───────────────────────────────────────────── */
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
.dtable td { padding: 4px 8px; border-bottom: 1px solid #EEE; vertical-align: top; }
.dtable tr:last-child td { border-bottom: none; }
.dtable .lbl {
    width: 33%;
    font-weight: 600;
    color: #5a1a32;
    background: #FFF4F8;
}
.dtable .val { color: #111; }

/* ─── BADGE ──────────────────────────────────────────── */
.badge-y { background: #d1fae5; color: #065f46; padding: 1px 7px; border-radius: 10px; font-weight: 700; }
.badge-n { background: #fee2e2; color: #991b1b; padding: 1px 7px; border-radius: 10px; font-weight: 700; }

/* ─── TTD AREA ────────────────────────────────────────── */
.ttd-section {
    display: flex;
    gap: 20px;
    margin-top: 20px;
    page-break-inside: avoid;
}
.ttd-box {
    flex: 1;
    text-align: center;
    border: 1px solid #DDD;
    border-radius: 6px;
    padding: 10px;
}
.ttd-box .ttd-label { font-weight: 700; color: #5a1a32; margin-bottom: 6px; }
.ttd-box img { max-width: 200px; max-height: 90px; border: 1px dashed #999; background: #fafafa; }
.ttd-blank { height: 70px; border-bottom: 1px solid #333; margin: 10px 20px 4px; }
.ttd-box .ttd-name { margin-top: 4px; }

/* ─── TITIK PIJATAN ────────────────────────────────────── */
.titik-list { display: flex; flex-wrap: wrap; gap: 4px; padding: 6px; }
.titik-chip { background: #FFF0F6; border: 1px solid #E0B0C8; border-radius: 12px; padding: 2px 8px; color: #8B1538; }

/* ─── FACE MAP OVERLAY ─────────────────────────────────── */
.face-map-wrap {
    position: relative;
    display: inline-block;
    width: 160px;
    flex-shrink: 0;
}
.face-map-img {
    width: 160px;
    height: auto;
    display: block;
    border: 1px solid #DDD;
    border-radius: 4px;
    background: #fff;
}
.face-dot {
    position: absolute;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid #fff;
    color: #fff;
    font-size: 9px;
    font-weight: 700;
    line-height: 16px;
    text-align: center;
    transform: translate(-50%, -50%);
    box-shadow: 0 1px 4px rgba(0,0,0,0.35);
    cursor: default;
    z-index: 10;
}


/* ─── MULTI ENTRY CARD ────────────────────────────────── */
.entry-card {
    border: 1px solid #DDD;
    border-radius: 6px;
    margin-bottom: 12px;
    page-break-inside: avoid;
}
.entry-card .entry-header {
    background: #f3e8ee;
    padding: 6px 10px;
    font-weight: 700;
    color: #8B1538;
    border-radius: 5px 5px 0 0;
}

/* ─── FOOTER ──────────────────────────────────────────── */
.print-footer {
    margin-top: 20px;
    border-top: 1px solid #DDD;
    padding-top: 8px;
    color: #888;
    text-align: center;
}

/* =================================================================
   @media screen — Preview di monitor / tablet
   ================================================================= */
@media screen {
    body {
        font-size: 13px;
        background: #edf0f5;
        padding-top: 52px;  /* toolbar height */
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

    /* Kop */
    .kop-text h1   { font-size: 17px; }
    .kop-text .kop-sub { font-size: 12px; }
    .kop-right     { font-size: 12px; }

    /* Judul dokumen */
    .doc-title     { font-size: 15px; }

    /* Identitas */
    .identitas     { font-size: 12.5px; }

    /* Section title */
    .section-title { font-size: 11.5px; }

    /* Data table */
    .dtable td     { font-size: 12px; }

    /* Badge, titik, ttd */
    .badge-y, .badge-n { font-size: 11px; }
    .titik-chip    { font-size: 11px; }
    .ttd-box .ttd-label { font-size: 12px; }
    .ttd-box .ttd-name  { font-size: 12px; }

    /* Multi-entry header */
    .entry-card .entry-header { font-size: 12.5px; }

    /* Footer */
    .print-footer  { font-size: 11px; }

    /* Tablet narrow (<= 640px): stack identitas grid */
    @media (max-width: 640px) {
        .print-page { padding: 16px; margin: 10px; border-radius: 6px; }
        .identitas-grid { grid-template-columns: 1fr; }
        .kop { flex-wrap: wrap; }
        .kop-right { text-align: left; }
        .ttd-section { flex-wrap: wrap; }
    }
}

/* =================================================================
   @media print — Kertas A4 (presisi pt)
   ================================================================= */
@media print {
    @page {
        size: A4 portrait;
        margin: 12mm 14mm 12mm 14mm;
    }

    /* Sembunyikan toolbar */
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
        min-height: auto;
        padding: 0;
        margin: 0;
        box-shadow: none;
        border-radius: 0;
    }

    /* Kop */
    .kop-text h1   { font-size: 14pt; }
    .kop-text .kop-sub { font-size: 8.5pt; }
    .kop-right     { font-size: 8pt; }

    /* Judul dokumen */
    .doc-title     { font-size: 12pt; }

    /* Identitas */
    .identitas     { font-size: 9pt; }

    /* Section title */
    .section-title { font-size: 8.5pt; }

    /* Data table */
    .dtable td     { font-size: 9pt; padding: 3px 6px; }

    /* Badge, titik, ttd */
    .badge-y, .badge-n { font-size: 8pt; }
    .titik-chip    { font-size: 8pt; }
    .ttd-box .ttd-label { font-size: 8.5pt; }
    .ttd-box .ttd-name  { font-size: 8.5pt; }

    /* Multi-entry header */
    .entry-card .entry-header { font-size: 9pt; }

    /* Footer */
    .print-footer  { font-size: 7.5pt; }

    /* Page break rules */
    .section       { page-break-inside: avoid; }
    .entry-card    { page-break-inside: avoid; }
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
        <!-- Logo Instansi -->
        <?php if ($logoB64): ?>
            <div class="kop-logo">
                <img src="<?= $logoB64 ?>" alt="Logo <?= htmlspecialchars($namaInstansi) ?>">
            </div>
        <?php else: ?>
            <div class="kop-logo-fallback"><?= mb_strtoupper(mb_substr($namaInstansi, 0, 1)) ?></div>
        <?php endif; ?>

        <!-- Nama & Alamat Instansi -->
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

        <!-- Info Kunjungan -->
        <div class="kop-right">
            <div><strong>No. Kunjungan:</strong> <?= htmlspecialchars($noRawat) ?></div>
            <div><strong>Tgl. Registrasi:</strong> <?= htmlspecialchars(date('d/m/Y', strtotime($kunjungan['tgl_registrasi']))) ?></div>
            <div><strong>Dokter:</strong> <?= htmlspecialchars($kunjungan['nm_dokter'] ?? '-') ?></div>
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

<?php /* ═══════════════════════════════════════════════════════════
        KECANTIKAN
       ═══════════════════════════════════════════════════════════ */ ?>
<?php if ($type === 'kecantikan'): ?>

    <?php if (empty($data)): ?>
        <p style="text-align:center;color:#888;padding:30px;">Belum ada data asesmen kecantikan untuk kunjungan ini.</p>
    <?php else: ?>

    <!-- TANGGAL & FISIK -->
    <div class="section">
        <div class="section-title">1. Data Fisik & Jenis Kulit</div>
        <div class="section-body">
            <table class="dtable">
                <?= row4('Tgl. Perawatan', val($data,'tgl_perawatan','-') !== '-' ? date('d/m/Y H:i', strtotime($data['tgl_perawatan'])) : '-', 'Jenis Kulit', val($data,'jenis_kulit')) ?>
                <?= row4('Berat Badan', val($data,'bb') . ' kg', 'Tinggi Badan', val($data,'tb') . ' cm') ?>
            </table>
        </div>
    </div>

    <!-- KONDISI KULIT -->
    <div class="section">
        <div class="section-title">2. Analisis Kondisi Kulit Wajah</div>
        <div class="section-body">
            <table class="dtable">
                <?= row4('Jerawat', val($data,'jerawat'), 'Area Jerawat', val($data,'jerawat_area')) ?>
                <?= row4('Derajat Jerawat', val($data,'jerawat_derajat'), '', '') ?>
                <?= row4('Bekas Jerawat', val($data,'cacat_bekas_jerawat'), 'Area Bekas Jerawat', val($data,'cacat_bekas_jerawat_area')) ?>
                <?= row4('Derajat Bekas', val($data,'cacat_bekas_jerawat_derajat'), '', '') ?>
                <?= row4('Fleks Hitam/Cokelat', val($data,'fleks_hitam_cokelat'), 'Area Fleks', val($data,'fleks_area')) ?>
                <?= row4('Derajat Fleks', val($data,'fleks_derajat'), '', '') ?>
                <?= row4('Keriput Wajah', val($data,'keriput_wajah'), 'Area Keriput', val($data,'keriput_area')) ?>
                <?= row4('Area Sensitif', val($data,'area_sensitif'), 'Keterangan Sensitif', val($data,'area_sensitif_ket')) ?>
            </table>
        </div>
    </div>

    <!-- RIWAYAT KESEHATAN -->
    <div class="section">
        <div class="section-title">3. Riwayat Kesehatan & Kondisi Umum</div>
        <div class="section-body">
            <table class="dtable">
                <?= row4('Kondisi Hamil', val($data,'kondisi_hamil'), 'Kondisi Menyusui', val($data,'kondisi_menyusui')) ?>
                <?= row4('Kontrasepsi', val($data,'menggunakan_kontrasepsi'), 'Jenis Kontrasepsi', val($data,'jenis_kontrasepsi')) ?>
                <?= row4('Diet Khusus', val($data,'diet_khusus'), 'Jenis Diet', val($data,'jenis_diet')) ?>
                <?= row4('Alergi', val($data,'alergi'), 'Keterangan Alergi', val($data,'alergi_ket')) ?>
                <?= row2('Produk Skincare Terakhir', val($data,'produk_perawatan_terakhir')) ?>
                <?= row2('Riwayat Penyakit Dahulu', '<span style="white-space:pre-wrap">' . val($data,'riwayat_penyakit_dahulu') . '</span>') ?>
                <?= row2('Riwayat Penyakit Keluarga', '<span style="white-space:pre-wrap">' . val($data,'riwayat_penyakit_keluarga') . '</span>') ?>
                <?= row2('Keluhan Utama', '<span style="white-space:pre-wrap;font-weight:600;">' . val($data,'keluhan') . '</span>') ?>
            </table>
        </div>
    </div>

    <!-- RENCANA TREATMENT -->
    <div class="section">
        <div class="section-title">4. Rencana Treatment & Fokus Pijatan</div>
        <div class="section-body">
            <table class="dtable">
                <?= row2('Area Fokus Pijatan', '<span style="white-space:pre-wrap">' . val($data,'fokus_pijatan_area') . '</span>') ?>
                <?= row2('Tingkat Tekanan', val($data,'tingkat_pijatan')) ?>
            </table>
            <?php if (!empty($titik)):
                // Embed gambar wajah sebagai base64 agar aman saat print (tidak bergantung path server)
                $imgPath = __DIR__ . '/../assets/img/area_pijatan.png';
                $imgB64  = '';
                if (file_exists($imgPath)) {
                    $imgB64 = 'data:image/png;base64,' . base64_encode(file_get_contents($imgPath));
                }
            ?>
            <div style="padding:8px 10px; border-top:1px solid #EEE;">
                <div style="font-size:8.5pt;font-weight:700;color:#5a1a32;margin-bottom:8px;">
                    Peta Titik Penanda Pijatan:
                </div>

                <?php if ($imgB64): ?>
                <!-- Wajah + Overlay Titik -->
                <div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap;">

                    <!-- Gambar wajah dengan overlay titik -->
                    <div class="face-map-wrap">
                        <img src="<?= $imgB64 ?>" class="face-map-img" alt="Area Pijatan">
                        <?php foreach ($titik as $i => $t):
                            $px = (float)$t['pos_x'];
                            $py = (float)$t['pos_y'];
                            // Warna berbeda setiap titik (siklus 8 warna)
                            $colors = ['#c0392b','#2980b9','#27ae60','#8e44ad','#e67e22','#16a085','#d35400','#2c3e50'];
                            $c = $colors[$i % count($colors)];
                        ?>
                        <div class="face-dot" style="left:<?= $px ?>%;top:<?= $py ?>%;background:<?= $c ?>;border-color:<?= $c ?>;">
                            <?= $i + 1 ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Legenda titik -->
                    <div style="flex:1;min-width:120px;">
                        <table style="width:100%;border-collapse:collapse;font-size:8pt;">
                            <thead>
                                <tr>
                                    <th style="text-align:center;padding:3px 6px;background:#f0e0e8;color:#5a1a32;border:1px solid #ddd;width:28px;">#</th>
                                    <th style="text-align:left;padding:3px 6px;background:#f0e0e8;color:#5a1a32;border:1px solid #ddd;">Keterangan</th>
                                    <th style="text-align:center;padding:3px 6px;background:#f0e0e8;color:#5a1a32;border:1px solid #ddd;">Koordinat</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($titik as $i => $t):
                                $colors = ['#c0392b','#2980b9','#27ae60','#8e44ad','#e67e22','#16a085','#d35400','#2c3e50'];
                                $c = $colors[$i % count($colors)];
                            ?>
                                <tr>
                                    <td style="text-align:center;padding:3px 5px;border:1px solid #eee;">
                                        <span style="display:inline-block;width:16px;height:16px;background:<?= $c ?>;color:#fff;border-radius:50%;font-size:7pt;font-weight:700;line-height:16px;text-align:center;"><?= $i+1 ?></span>
                                    </td>
                                    <td style="padding:3px 5px;border:1px solid #eee;">
                                        <?= $t['keterangan'] ? htmlspecialchars($t['keterangan']) : '<span style="color:#aaa;">—</span>' ?>
                                    </td>
                                    <td style="text-align:center;padding:3px 5px;border:1px solid #eee;color:#888;font-size:7pt;">
                                        <?= number_format($t['pos_x'],0) ?>%, <?= number_format($t['pos_y'],0) ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
                <?php else: ?>
                <!-- Fallback jika gambar tidak tersedia: tampilkan chip -->
                <div class="titik-list">
                    <?php foreach ($titik as $i => $t): ?>
                        <span class="titik-chip">
                            Titik <?= $i+1 ?>: (<?= number_format($t['pos_x'],0) ?>%, <?= number_format($t['pos_y'],0) ?>%)
                            <?= $t['keterangan'] ? ' — ' . htmlspecialchars($t['keterangan']) : '' ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- PERSETUJUAN -->
    <div class="section">
        <div class="section-title">5. Persetujuan & Tanda Tangan</div>
        <div class="section-body">
            <table class="dtable">
                <?= row2('Persetujuan Pasien', val($data,'persetujuan_pasien') === 'Ya' ? '<span class="badge-y">Ya — Menyetujui</span>' : '<span class="badge-n">Belum Menyetujui</span>') ?>
                <?= row2('Nama Penandatangan', '<strong>' . val($data,'nama_ttd_pasien') . '</strong>') ?>
            </table>
        </div>
    </div>

    <!-- TANDA TANGAN DIGITAL -->
    <?php $ttdImg = $data['ttd_pasien'] ?? ''; ?>
    <div class="ttd-section">
        <div class="ttd-box">
            <div class="ttd-label">Tanda Tangan Pasien / Wali</div>
            <?php if ($ttdImg && strpos($ttdImg, 'data:image') === 0): ?>
                <img src="<?= $ttdImg ?>" alt="TTD Pasien"
                     style="max-width:100%;max-height:110px;border:1px solid #ddd;border-radius:4px;background:#fff;display:block;margin:4px auto;">
            <?php else: ?>
                <div class="ttd-blank"></div>
            <?php endif; ?>
            <div class="ttd-name"><?= val($data,'nama_ttd_pasien') ?></div>
        </div>
        <div class="ttd-box">
            <div class="ttd-label">Tanda Tangan Terapis / Petugas</div>
            <div class="ttd-blank"></div>
            <div class="ttd-name">(...........................)</div>
        </div>
        <div class="ttd-box">
            <div class="ttd-label">Mengetahui, Dokter Pemeriksa</div>
            <div class="ttd-blank"></div>
            <div class="ttd-name"><?= htmlspecialchars($kunjungan['nm_dokter'] ?? '-') ?></div>
        </div>
    </div>


    <?php endif; ?>

<?php /* ═══════════════════════════════════════════════════════════
        KEBIDANAN MEDIS
       ═══════════════════════════════════════════════════════════ */ ?>
<?php elseif ($type === 'kebidanan-medis'): ?>

    <?php if (empty($data)): ?>
        <p style="text-align:center;color:#888;padding:30px;">Belum ada data asesmen medis untuk kunjungan ini.</p>
    <?php else: ?>

    <div class="section">
        <div class="section-title">1. Data Anamnesis</div>
        <div class="section-body">
            <table class="dtable">
                <?= row4('Tanggal', isset($data['tanggal']) ? date('d/m/Y H:i', strtotime($data['tanggal'])) : '-', 'Dokter', val($data,'nm_dokter')) ?>
                <?= row4('Informasi dari', val($data,'anamnesis'), 'Hubungan', val($data,'hubungan')) ?>
                <?= row2('Keluhan Utama', '<span style="white-space:pre-wrap;font-weight:600;">' . val($data,'keluhan_utama') . '</span>') ?>
                <?= row2('Riwayat Penyakit Sekarang (RPS)', '<span style="white-space:pre-wrap">' . val($data,'rps') . '</span>') ?>
                <?= row2('Riwayat Penyakit Dahulu (RPD)', '<span style="white-space:pre-wrap">' . val($data,'rpd') . '</span>') ?>
                <?= row2('Riwayat Penyakit Keluarga (RPK)', '<span style="white-space:pre-wrap">' . val($data,'rpk') . '</span>') ?>
                <?= row2('Riwayat Pengobatan (RPO)', '<span style="white-space:pre-wrap">' . val($data,'rpo') . '</span>') ?>
                <?= row2('Alergi', val($data,'alergi')) ?>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">2. Pemeriksaan Fisik & Tanda Vital</div>
        <div class="section-body">
            <table class="dtable">
                <?= row4('Keadaan Umum', val($data,'keadaan'), 'Kesadaran', val($data,'kesadaran')) ?>
                <?= row4('GCS', val($data,'gcs'), 'SpO2', val($data,'spo') . '%') ?>
                <?= row4('Tekanan Darah', val($data,'td') . ' mmHg', 'Nadi', val($data,'nadi') . ' x/mnt') ?>
                <?= row4('Respirasi', val($data,'rr') . ' x/mnt', 'Suhu', val($data,'suhu') . '°C') ?>
                <?= row4('Berat Badan', val($data,'bb') . ' kg', 'Tinggi Badan', val($data,'tb') . ' cm') ?>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">3. Pemeriksaan Fisik Khusus</div>
        <div class="section-body">
            <table class="dtable">
                <?= row4('Kepala', val($data,'kepala'), 'Mata', val($data,'mata')) ?>
                <?= row4('Gigi', val($data,'gigi'), 'THT', val($data,'tht')) ?>
                <?= row4('Thoraks', val($data,'thoraks'), 'Abdomen', val($data,'abdomen')) ?>
                <?= row4('Genital', val($data,'genital'), 'Ekstremitas', val($data,'ekstremitas')) ?>
                <?= row4('Kulit', val($data,'kulit'), 'Catatan Fisik', val($data,'ket_fisik')) ?>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">4. Pemeriksaan Kebidanan</div>
        <div class="section-body">
            <table class="dtable">
                <?= row4('TFU', val($data,'tfu'), 'TBJ', val($data,'tbj')) ?>
                <?= row4('His', val($data,'his'), 'Kontraksi', val($data,'kontraksi')) ?>
                <?= row4('DJJ', val($data,'djj'), '', '') ?>
                <?= row2('Inspeksi', val($data,'inspeksi')) ?>
                <?= row2('Inspekulo', val($data,'inspekulo')) ?>
                <?= row4('VT', val($data,'vt'), 'RT', val($data,'rt')) ?>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">5. Pemeriksaan Penunjang</div>
        <div class="section-body">
            <table class="dtable">
                <?= row2('Ultrasonografi (USG)', val($data,'ultra')) ?>
                <?= row2('Kardiotokografi (CTG)', val($data,'kardio')) ?>
                <?= row2('Laboratorium', val($data,'lab')) ?>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">6. Diagnosis & Tata Laksana</div>
        <div class="section-body">
            <table class="dtable">
                <?= row2('Diagnosis', '<span style="white-space:pre-wrap;font-weight:600;">' . val($data,'diagnosis') . '</span>') ?>
                <?= row2('Tata Laksana', '<span style="white-space:pre-wrap">' . val($data,'tata') . '</span>') ?>
                <?= row2('Konsultasi', val($data,'konsul')) ?>
            </table>
        </div>
    </div>

    <div class="ttd-section">
        <div class="ttd-box">
            <div class="ttd-label">Dokter Pemeriksa</div>
            <div class="ttd-blank"></div>
            <div class="ttd-name"><?= htmlspecialchars($kunjungan['nm_dokter'] ?? '-') ?></div>
        </div>
        <div class="ttd-box">
            <div class="ttd-label">Pasien / Keluarga</div>
            <div class="ttd-blank"></div>
            <div class="ttd-name">(...........................)</div>
        </div>
    </div>

    <?php endif; ?>

<?php /* ═══════════════════════════════════════════════════════════
        KEBIDANAN KEPERAWATAN
       ═══════════════════════════════════════════════════════════ */ ?>
<?php elseif ($type === 'kebidanan-keperawatan'): ?>

    <?php if (empty($data)): ?>
        <p style="text-align:center;color:#888;padding:30px;">Belum ada data asesmen keperawatan untuk kunjungan ini.</p>
    <?php else: ?>

    <div class="section">
        <div class="section-title">1. Tanda Vital & Data Fisik</div>
        <div class="section-body">
            <table class="dtable">
                <?= row4('Tanggal Asesmen', isset($data['tanggal']) ? date('d/m/Y H:i', strtotime($data['tanggal'])) : '-', 'Petugas', val($data,'nm_petugas')) ?>
                <?= row4('Tekanan Darah', val($data,'td') . ' mmHg', 'Nadi', val($data,'nadi') . ' x/mnt') ?>
                <?= row4('Pernapasan', val($data,'rr') . ' x/mnt', 'Suhu', val($data,'suhu') . '°C') ?>
                <?= row4('GCS', val($data,'gcs'), '', '') ?>
                <?= row4('Berat Badan', val($data,'bb') . ' kg', 'Tinggi Badan', val($data,'tb') . ' cm') ?>
                <?= row4('LILA', val($data,'lila') . ' cm', 'BMI', val($data,'bmi')) ?>
                <?= row2('Keluhan Utama', '<span style="white-space:pre-wrap;font-weight:600;">' . val($data,'keluhan_utama') . '</span>') ?>
                <?= row2('Informasi Dari', val($data,'informasi')) ?>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">2. Pemeriksaan Kebidanan</div>
        <div class="section-body">
            <table class="dtable">
                <?= row4('TFU', val($data,'tfu') . ' cm', 'TBJ', val($data,'tbj') . ' gram') ?>
                <?= row4('Letak Janin', val($data,'letak'), 'Presentasi', val($data,'presentasi')) ?>
                <?= row4('Penurunan', val($data,'penurunan'), 'His', val($data,'his')) ?>
                <?= row4('Kekuatan His', val($data,'kekuatan'), 'Lamanya', val($data,'lamanya')) ?>
                <?= row4('BJJ', val($data,'bjj'), 'Keterangan BJJ', val($data,'ket_bjj')) ?>
                <?= row4('Portio', val($data,'portio'), 'Serviks', val($data,'serviks')) ?>
                <?= row4('Ketuban', val($data,'ketuban'), 'Hodge', val($data,'hodge')) ?>
                <?= row4('HPHT', val($data,'hpht'), 'Usia Kehamilan', val($data,'usia_kehamilan') . ' minggu') ?>
                <?= row4('TP / HPL', val($data,'tp'), 'G/P/A', val($data,'g') . '/' . val($data,'p') . '/' . val($data,'a')) ?>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">3. Pengkajian Nyeri</div>
        <div class="section-body">
            <table class="dtable">
                <?= row4('Nyeri', val($data,'nyeri'), 'Skala Nyeri', val($data,'skala_nyeri') . '/10') ?>
                <?= row4('Provokes', val($data,'provokes'), 'Quality', val($data,'quality')) ?>
                <?= row4('Lokasi', val($data,'lokasi'), 'Menyebar ke', val($data,'menyebar')) ?>
                <?= row4('Durasi', val($data,'durasi'), 'Nyeri Hilang', val($data,'nyeri_hilang')) ?>
                <?= row2('Keterangan Nyeri', val($data,'ket_nyeri')) ?>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">4. Masalah & Tindakan Keperawatan</div>
        <div class="section-body">
            <table class="dtable">
                <?= row2('Masalah Keperawatan', '<span style="white-space:pre-wrap;font-weight:600;">' . val($data,'masalah') . '</span>') ?>
                <?= row2('Tindakan', '<span style="white-space:pre-wrap">' . val($data,'tindakan') . '</span>') ?>
                <?= row4('Lapor ke Dokter', val($data,'pada_dokter'), 'Keterangan', val($data,'ket_dokter')) ?>
            </table>
        </div>
    </div>

    <div class="ttd-section">
        <div class="ttd-box">
            <div class="ttd-label">Bidan / Perawat</div>
            <div class="ttd-blank"></div>
            <div class="ttd-name"><?= val($data,'nm_petugas') ?></div>
        </div>
        <div class="ttd-box">
            <div class="ttd-label">Pasien / Keluarga</div>
            <div class="ttd-blank"></div>
            <div class="ttd-name">(...........................)</div>
        </div>
    </div>

    <?php endif; ?>

<?php /* ═══════════════════════════════════════════════════════════
        SOAP
       ═══════════════════════════════════════════════════════════ */ ?>
<?php elseif ($type === 'soap'): ?>

    <?php if (empty($dataList)): ?>
        <p style="text-align:center;color:#888;padding:30px;">Belum ada catatan SOAP untuk kunjungan ini.</p>
    <?php else: ?>

    <?php foreach ($dataList as $idx => $s): ?>
    <div class="entry-card">
        <div class="entry-header">
            📅 Catatan SOAP ke-<?= $idx + 1 ?> — <?= htmlspecialchars(date('d/m/Y', strtotime($s['tgl_perawatan']))) ?> jam <?= htmlspecialchars($s['jam_rawat']) ?>
            <?php if (!empty($s['nm_petugas'])): ?> — Petugas: <?= htmlspecialchars($s['nm_petugas']) ?><?php endif; ?>
        </div>
        <table class="dtable">
            <?= row4('Tekanan Darah', val($s,'tensi') . ' mmHg', 'Nadi', val($s,'nadi') . ' x/mnt') ?>
            <?= row4('Suhu', val($s,'suhu_tubuh') . '°C', 'Respirasi', val($s,'respirasi') . ' x/mnt') ?>
            <?= row4('BB', val($s,'berat') . ' kg', 'TB', val($s,'tinggi') . ' cm') ?>
            <?= row4('SpO2', val($s,'spo2') . '%', 'GCS', val($s,'gcs')) ?>
            <?= row4('Kesadaran', val($s,'kesadaran'), 'Lingkar Perut', val($s,'lingkar_perut') . ' cm') ?>
            <?= row2('Keluhan (S)', '<span style="white-space:pre-wrap;font-weight:600;">' . val($s,'keluhan') . '</span>') ?>
            <?= row2('Pemeriksaan (O)', '<span style="white-space:pre-wrap">' . val($s,'pemeriksaan') . '</span>') ?>
            <?= row2('Alergi', val($s,'alergi')) ?>
            <?= row2('Penilaian/Asesmen (A)', '<span style="white-space:pre-wrap;font-weight:600;">' . val($s,'penilaian') . '</span>') ?>
            <?= row2('RTL / Plan (P)', '<span style="white-space:pre-wrap">' . val($s,'rtl') . '</span>') ?>
            <?= row2('Instruksi', val($s,'instruksi')) ?>
            <?= row2('Evaluasi', val($s,'evaluasi')) ?>
        </table>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>

<?php /* ═══════════════════════════════════════════════════════════
        OBSTETRI
       ═══════════════════════════════════════════════════════════ */ ?>
<?php elseif ($type === 'obstetri'): ?>

    <?php if (empty($dataList)): ?>
        <p style="text-align:center;color:#888;padding:30px;">Belum ada data pemeriksaan obstetri untuk kunjungan ini.</p>
    <?php else: ?>

    <?php foreach ($dataList as $idx => $o): ?>
    <div class="entry-card">
        <div class="entry-header">
            📅 Pemeriksaan Obstetri ke-<?= $idx + 1 ?> — <?= htmlspecialchars(date('d/m/Y', strtotime($o['tgl_perawatan']))) ?> jam <?= htmlspecialchars($o['jam_rawat']) ?>
        </div>
        <table class="dtable">
            <?= row4('TFU (Tinggi Fundus Uteri)', val($o,'tinggi_uteri') . ' cm', 'Janin', val($o,'janin')) ?>
            <?= row4('Letak', val($o,'letak'), 'Panggul', val($o,'panggul')) ?>
            <?= row4('Denyut Jantung Janin', val($o,'denyut') . ' x/mnt', 'Kontraksi', val($o,'kontraksi')) ?>
            <?= row4('Kualitas Kontraksi (mnt)', val($o,'kualitas_mnt'), 'Kualitas (dtk)', val($o,'kualitas_dtk')) ?>
            <?= row4('Fluksus', val($o,'fluksus'), 'Albus', val($o,'albus')) ?>
            <?= row4('Vulva', val($o,'vulva'), 'Portio', val($o,'portio')) ?>
            <?= row4('Dalam', val($o,'dalam'), 'Tebal', val($o,'tebal')) ?>
            <?= row4('Arah', val($o,'arah'), 'Pembukaan', val($o,'pembukaan') . ' cm') ?>
            <?= row4('Penurunan', val($o,'penurunan'), 'Denominator', val($o,'denominator')) ?>
            <?= row4('Ketuban', val($o,'ketuban'), 'Feto', val($o,'feto')) ?>
        </table>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>

<?php /* ═══════════════════════════════════════════════════════════
        GINEKOLOGI
       ═══════════════════════════════════════════════════════════ */ ?>
<?php elseif ($type === 'ginekologi'): ?>

    <?php if (empty($dataList)): ?>
        <p style="text-align:center;color:#888;padding:30px;">Belum ada data pemeriksaan ginekologi untuk kunjungan ini.</p>
    <?php else: ?>

    <?php foreach ($dataList as $idx => $g): ?>
    <div class="entry-card">
        <div class="entry-header">
            📅 Pemeriksaan Ginekologi ke-<?= $idx + 1 ?> — <?= htmlspecialchars(date('d/m/Y', strtotime($g['tgl_perawatan']))) ?> jam <?= htmlspecialchars($g['jam_rawat']) ?>
        </div>
        <table class="dtable">
            <?= row2('Inspeksi Umum', val($g,'inspeksi')) ?>
            <?= row4('Inspeksi Vulva', val($g,'inspeksi_vulva'), 'Inspekulo', val($g,'inspekulo_gine')) ?>
            <?= row4('Fluksus', val($g,'fluxus_gine'), 'Fluor', val($g,'fluor_gine')) ?>
            <?= row4('Vulva (Inspekulo)', val($g,'vulva_inspekulo'), 'Portio (Inspekulo)', val($g,'portio_inspekulo')) ?>
            <?= row4('Sondage', val($g,'sondage'), 'Portio (VT)', val($g,'portio_dalam')) ?>
            <?= row4('Bentuk Uterus', val($g,'bentuk'), 'Cavum Uteri', val($g,'cavum_uteri')) ?>
            <?= row4('Mobilitas', val($g,'mobilitas'), 'Ukuran', val($g,'ukuran')) ?>
            <?= row4('Nyeri Tekan', val($g,'nyeri_tekan'), 'Cavum Douglas', val($g,'cavum_douglas')) ?>
            <?= row4('Adnexa Kanan', val($g,'adnexa_kanan'), 'Adnexa Kiri', val($g,'adnexa_kiri')) ?>
        </table>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>

<?php /* ═══════════════════════════════════════════════════════════
        USG KANDUNGAN
       ═══════════════════════════════════════════════════════════ */ ?>
<?php elseif ($type === 'usg-kandungan'): ?>

    <?php if (empty($data)): ?>
        <p style="text-align:center;color:#888;padding:30px;">Belum ada data pemeriksaan USG Kandungan untuk kunjungan ini.</p>
    <?php else: ?>
        <div class="section">
            <div class="section-title">Informasi Klinis & Rujukan</div>
            <div class="section-body">
                <table class="dtable">
                    <?= row4('Dokter Pemeriksa', val($data, 'nm_dok_usg'), 'Tgl USG', !empty($data['tanggal']) ? date('d/m/Y H:i', strtotime($data['tanggal'])) : '-') ?>
                    <?= row4('Diagnosa Klinis', val($data, 'diagnosa_klinis'), 'Kiriman Dari', val($data, 'kiriman_dari')) ?>
                    <?= row4('HTA / HPHT', val($data, 'hta'), 'Jenis Presentasi', val($data, 'jenis_prestasi')) ?>
                </table>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Biometri & Pengukuran Janin (USG Biometry)</div>
            <div class="section-body">
                <table class="dtable">
                    <?= row4('Kantong Gestasi (GS)', val($data, 'kantong_gestasi') . ' mm', 'Crown-Rump Length (CRL)', val($data, 'ukuran_bokongkepala') . ' mm') ?>
                    <?= row4('Diameter Biparietal (BPD)', val($data, 'diameter_biparietal') . ' mm', 'Panjang Femur (FL)', val($data, 'panjang_femur') . ' mm') ?>
                    <?= row4('Lingkar Abdomen (AC)', val($data, 'lingkar_abdomen') . ' mm', 'Tafsiran Berat Janin (EFW)', val($data, 'tafsiran_berat_janin') . ' gram') ?>
                    <?= row2('Usia Kehamilan (GA)', val($data, 'usia_kehamilan')) ?>
                </table>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Kondisi Lingkungan & Anatomi Janin</div>
            <div class="section-body">
                <table class="dtable">
                    <?= row4('Implantasi Plasenta', val($data, 'plasenta_berimplatansi'), 'Maturitas Plasenta', 'Grade ' . val($data, 'derajat_maturitas')) ?>
                    <?= row4('Jumlah Air Ketuban', val($data, 'jumlah_air_ketuban'), 'Indeks Cairan Ketuban (AFI)', val($data, 'indek_cairan_ketuban')) ?>
                    <?= row4('Kelainan Kongenital', val($data, 'kelainan_kongenital'), 'Peluang Jenis Kelamin', val($data, 'peluang_sex')) ?>
                </table>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Kesimpulan Hasil USG</div>
            <div class="section-body">
                <table class="dtable">
                    <?= row2('Kesimpulan', nl2br(val($data, 'kesimpulan'))) ?>
                </table>
            </div>
        </div>

        <?php if (!empty($data['photo'])): ?>
        <div class="section">
            <div class="section-title">Foto Lampiran USG</div>
            <div class="section-body">
                <div style="text-align:center; padding:12px;">
                    <img src="/webapps/berkasrawat/pages/upload/<?= htmlspecialchars(basename($data['photo'])) ?>" alt="Foto USG" style="max-width:380px; max-height:280px; border-radius:6px; border:1px solid #E0B0C8; box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                    <div style="font-size:11px; color:#666; margin-top:6px;">Foto Lampiran USG — <?= htmlspecialchars($kunjungan['nm_pasien']) ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="ttd-section">
            <div class="ttd-box">
                <div class="ttd-label">Dokter Pemeriksa USG</div>
                <div class="ttd-blank"></div>
                <div class="ttd-name"><strong><?= htmlspecialchars($data['nm_dok_usg'] ?? $kunjungan['nm_dokter'] ?? '-') ?></strong></div>
            </div>
        </div>
    <?php endif; ?>

<?php /* ═══════════════════════════════════════════════════════════
        USG GINEKOLOGI
       ═══════════════════════════════════════════════════════════ */ ?>
<?php elseif ($type === 'usg-ginekologi'): ?>

    <?php if (empty($data)): ?>
        <p style="text-align:center;color:#888;padding:30px;">Belum ada data pemeriksaan USG Ginekologi untuk kunjungan ini.</p>
    <?php else: ?>
        <div class="section">
            <div class="section-title">Informasi Klinis & Rujukan</div>
            <div class="section-body">
                <table class="dtable">
                    <?= row4('Dokter Pemeriksa', val($data, 'nm_dok_usg'), 'Tgl USG', !empty($data['tanggal']) ? date('d/m/Y H:i', strtotime($data['tanggal'])) : '-') ?>
                    <?= row4('Diagnosa Klinis', val($data, 'diagnosa_klinis'), 'Kiriman Dari', val($data, 'kiriman_dari')) ?>
                </table>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Hasil Pemeriksaan Organ Ginekologi</div>
            <div class="section-body">
                <table class="dtable">
                    <?= row2('Uterus', nl2br(val($data, 'uterus'))) ?>
                    <?= row2('Parametrium', nl2br(val($data, 'parametrium'))) ?>
                    <?= row2('Ovarium', nl2br(val($data, 'ovarium'))) ?>
                    <?= row2('Doppler', nl2br(val($data, 'doppler'))) ?>
                </table>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Kesimpulan Hasil USG</div>
            <div class="section-body">
                <table class="dtable">
                    <?= row2('Kesimpulan', nl2br(val($data, 'kesimpulan'))) ?>
                </table>
            </div>
        </div>

        <?php if (!empty($data['photo'])): ?>
        <div class="section">
            <div class="section-title">Foto Lampiran USG</div>
            <div class="section-body">
                <div style="text-align:center; padding:12px;">
                    <img src="/webapps/berkasrawat/pages/upload/<?= htmlspecialchars(basename($data['photo'])) ?>" alt="Foto USG" style="max-width:380px; max-height:280px; border-radius:6px; border:1px solid #E0B0C8; box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                    <div style="font-size:11px; color:#666; margin-top:6px;">Foto Lampiran USG — <?= htmlspecialchars($kunjungan['nm_pasien']) ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="ttd-section">
            <div class="ttd-box">
                <div class="ttd-label">Dokter Pemeriksa USG</div>
                <div class="ttd-blank"></div>
                <div class="ttd-name"><strong><?= htmlspecialchars($data['nm_dok_usg'] ?? $kunjungan['nm_dokter'] ?? '-') ?></strong></div>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

    <!-- FOOTER -->
    <div class="print-footer">
        Dokumen ini dicetak dari Sistem Informasi Klinik &bull; <?= date('d/m/Y H:i:s') ?> &bull; No. Rawat: <?= htmlspecialchars($noRawat) ?>
    </div>

</div><!-- /print-page -->

</body>
</html>
