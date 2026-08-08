<?php
/**
 * billing/cetak.php
 * -----------------------------------------------------------------
 * Halaman Cetak Nota Rawat Jalan (Print-ready layout).
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';

wajibLogin();

$pdo = getKoneksi();

$noRawat = trim($_GET['no_rawat'] ?? '');
if ($noRawat === '') {
    echo "No Rawat tidak valid.";
    exit;
}

// Data kunjungan
$stmt = $pdo->prepare(
    "SELECT r.no_rawat, r.tgl_registrasi, r.jam_reg, r.biaya_reg,
            p.nm_pasien, p.no_rkm_medis, p.jk, p.tgl_lahir, p.alamat,
            pol.nm_poli, d.nm_dokter,
            pj.png_jawab as nm_pj
     FROM reg_periksa r
     JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
     JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
     LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
     LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
     WHERE r.no_rawat = ?"
);
$stmt->execute([$noRawat]);
$kunjungan = $stmt->fetch();
if (!$kunjungan) {
    echo "Data kunjungan tidak ditemukan.";
    exit;
}

// Cek Nota
$stmtNota = $pdo->prepare("SELECT * FROM nota_jalan WHERE no_rawat = ?");
$stmtNota->execute([$noRawat]);
$nota = $stmtNota->fetch();
if (!$nota) {
    echo "Nota belum dibuat untuk kunjungan ini. Silakan buat nota terlebih dahulu di menu Billing.";
    exit;
}

// ============================
// Ambil komponen tagihan
// ============================
$biayaRegistrasi = (float)($kunjungan['biaya_reg'] ?? 0);

$tindakanDr = $pdo->prepare("SELECT d.kd_jenis_prw, j.nm_perawatan, d.biaya_rawat FROM rawat_jl_dr d JOIN jns_perawatan j ON d.kd_jenis_prw = j.kd_jenis_prw WHERE d.no_rawat = ?");
$tindakanDr->execute([$noRawat]);
$tindakanDr = $tindakanDr->fetchAll();
$totalTindakanDr = array_sum(array_column($tindakanDr, 'biaya_rawat'));

$tindakanPr = $pdo->prepare("SELECT d.kd_jenis_prw, j.nm_perawatan, d.biaya_rawat FROM rawat_jl_pr d JOIN jns_perawatan j ON d.kd_jenis_prw = j.kd_jenis_prw WHERE d.no_rawat = ?");
$tindakanPr->execute([$noRawat]);
$tindakanPr = $tindakanPr->fetchAll();
$totalTindakanPr = array_sum(array_column($tindakanPr, 'biaya_rawat'));

$tindakanDrPr = $pdo->prepare("SELECT d.kd_jenis_prw, j.nm_perawatan, d.biaya_rawat FROM rawat_jl_drpr d JOIN jns_perawatan j ON d.kd_jenis_prw = j.kd_jenis_prw WHERE d.no_rawat = ?");
$tindakanDrPr->execute([$noRawat]);
$tindakanDrPr = $tindakanDrPr->fetchAll();
$totalTindakanDrPr = array_sum(array_column($tindakanDrPr, 'biaya_rawat'));

$resepItems = $pdo->prepare(
    "SELECT db.nama_brng, dpo.jml, dpo.total as subtotal
     FROM detail_pemberian_obat dpo
     LEFT JOIN databarang db ON dpo.kode_brng = db.kode_brng
     WHERE dpo.no_rawat = ?"
);
$resepItems->execute([$noRawat]);
$resepItems = $resepItems->fetchAll();
$totalObat = array_sum(array_column($resepItems, 'subtotal'));

$grandTotal = $biayaRegistrasi + $totalTindakanDr + $totalTindakanPr + $totalTindakanDrPr + $totalObat;

// Fetch riwayat pembayaran
$stmtDetail = $pdo->prepare("SELECT * FROM detail_nota_jalan WHERE no_rawat = ?");
$stmtDetail->execute([$noRawat]);
$pembayaran = $stmtDetail->fetchAll();

function rp(float $n): string {
    return 'Rp ' . number_format($n, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Nota Pembayaran - <?= htmlspecialchars($nota['no_nota']) ?></title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 13px;
            color: #333;
            margin: 20px;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px double #333;
            padding-bottom: 10px;
        }
        .header h2 {
            margin: 0 0 5px 0;
            font-size: 18px;
            text-transform: uppercase;
        }
        .header p {
            margin: 0;
            font-size: 11px;
            color: #666;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 4px 0;
            vertical-align: top;
        }
        .meta-table td.label {
            width: 15%;
            color: #666;
        }
        .meta-table td.value {
            width: 35%;
            font-weight: bold;
        }
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .detail-table th {
            border-top: 1px solid #333;
            border-bottom: 1px solid #333;
            padding: 8px;
            text-align: left;
            font-weight: bold;
        }
        .detail-table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .detail-table tr.total-row td {
            border-top: 1px solid #333;
            border-bottom: 2px double #333;
            font-weight: bold;
            font-size: 14px;
        }
        .signature-container {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            text-align: center;
            width: 200px;
        }
        .signature-space {
            height: 60px;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                margin: 0;
            }
        }
    </style>
</head>
<body>

<div class="no-print" style="background:#f4f4f5; padding: 12px; margin-bottom: 20px; border-radius: 6px; display: flex; gap:10px;">
    <button onclick="window.print()" style="padding: 6px 12px; background:#800020; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">🖨️ Cetak / Simpan PDF</button>
    <button onclick="window.close()" style="padding: 6px 12px; background:#ccc; color:#333; border:none; border-radius:4px; cursor:pointer;">Tutup</button>
</div>

<div class="header">
    <h2>RSU AL-ARIF</h2>
    <p>Jl. Raya Ciamis - Banjar No.12, Ciamis · Telp: (0265) 123456</p>
</div>

<table class="meta-table">
    <tr>
        <td class="label">No. Nota</td>
        <td class="value">: <?= htmlspecialchars($nota['no_nota']) ?></td>
        <td class="label">No. Rawat/RM</td>
        <td class="value">: <?= htmlspecialchars($kunjungan['no_rawat']) ?> / <code><?= htmlspecialchars($kunjungan['no_rkm_medis']) ?></code></td>
    </tr>
    <tr>
        <td class="label">Nama Pasien</td>
        <td class="value">: <?= htmlspecialchars($kunjungan['nm_pasien']) ?></td>
        <td class="label">Tanggal Kunjungan</td>
        <td class="value">: <?= date('d-m-Y', strtotime($kunjungan['tgl_registrasi'])) ?></td>
    </tr>
    <tr>
        <td class="label">Poliklinik/Dokter</td>
        <td class="value">: <?= htmlspecialchars($kunjungan['nm_poli']) ?> / <?= htmlspecialchars($kunjungan['nm_dokter']) ?></td>
        <td class="label">Tanggal Nota</td>
        <td class="value">: <?= date('d-m-Y', strtotime($nota['tanggal'])) ?> <?= htmlspecialchars(substr($nota['jam'], 0, 5)) ?></td>
    </tr>
    <tr>
        <td class="label">Cara Bayar</td>
        <td class="value">: <?= htmlspecialchars($kunjungan['nm_pj']) ?></td>
        <td class="label">Alamat Pasien</td>
        <td class="value">: <?= htmlspecialchars($kunjungan['alamat'] ?: '-') ?></td>
    </tr>
</table>

<table class="detail-table">
    <thead>
        <tr>
            <th>Rincian Layanan / Tagihan</th>
            <th style="text-align:right;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($biayaRegistrasi > 0): ?>
        <tr>
            <td>Administrasi / Registrasi</td>
            <td style="text-align:right; font-family: monospace;"><?= rp($biayaRegistrasi) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($totalTindakanDr > 0): ?>
        <tr>
            <td>Tindakan Dokter</td>
            <td style="text-align:right; font-family: monospace;"><?= rp($totalTindakanDr) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($totalTindakanPr > 0): ?>
        <tr>
            <td>Tindakan Perawat</td>
            <td style="text-align:right; font-family: monospace;"><?= rp($totalTindakanPr) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($totalTindakanDrPr > 0): ?>
        <tr>
            <td>Tindakan Dokter & Perawat</td>
            <td style="text-align:right; font-family: monospace;"><?= rp($totalTindakanDrPr) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($totalObat > 0): ?>
        <tr>
            <td>Obat & Resep (Tervalidasi)</td>
            <td style="text-align:right; font-family: monospace;"><?= rp($totalObat) ?></td>
        </tr>
        <?php endif; ?>
        
        <tr class="total-row">
            <td>TOTAL TAGIHAN KESELURUHAN</td>
            <td style="text-align:right; font-family: monospace;"><?= rp($grandTotal) ?></td>
        </tr>

        <!-- Riwayat Pembayaran -->
        <?php if (count($pembayaran) > 0): ?>
        <tr><td colspan="2" style="height:15px;"></td></tr>
        <tr>
            <th colspan="2" style="border-top:none; border-bottom:1px solid #ddd; padding-left:0;">Riwayat Pembayaran</th>
        </tr>
        <?php foreach ($pembayaran as $p): ?>
        <tr>
            <td>Metode: <?= htmlspecialchars($p['nama_bayar']) ?></td>
            <td style="text-align:right; font-family: monospace;"><?= rp((float)$p['besar_bayar']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<div class="signature-container">
    <div class="signature-box">
        <p>Pasien / Keluarga</p>
        <div class="signature-space"></div>
        <p>( _______________________ )</p>
    </div>
    <div class="signature-box">
        <p>Petugas Kasir</p>
        <div class="signature-space"></div>
        <p>( <?= htmlspecialchars($_SESSION['username'] ?? 'Kasir') ?> )</p>
    </div>
</div>

</body>
</html>
