<?php
/**
 * billing/cetak-thermal.php
 * -----------------------------------------------------------------
 * Halaman Cetak Nota/Tagihan Thermal 58mm.
 * Bisa dicetak meskipun belum bayar di Java (sebagai Invoice/Draft Tagihan).
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
    "SELECT r.no_rawat, r.tgl_registrasi, r.jam_reg, r.biaya_reg, r.status_bayar,
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

// Cek Nota (Bisa NULL jika belum bayar di Java)
$stmtNota = $pdo->prepare("SELECT * FROM nota_jalan WHERE no_rawat = ?");
$stmtNota->execute([$noRawat]);
$nota = $stmtNota->fetch();

// Status
$sudahBayar = ($kunjungan['status_bayar'] === 'Sudah Bayar');

// ============================
// Ambil komponen tagihan
// ============================
$biayaRegistrasi = (float) ($kunjungan['biaya_reg'] ?? 0);

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

// Fetch riwayat pembayaran jika ada
$pembayaran = [];
if ($nota) {
    $stmtDetail = $pdo->prepare("SELECT * FROM detail_nota_jalan WHERE no_rawat = ?");
    $stmtDetail->execute([$noRawat]);
    $pembayaran = $stmtDetail->fetchAll();
}

function rp(float $n): string
{
    return number_format($n, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Cetak Nota - <?= htmlspecialchars($kunjungan['nm_pasien']) ?></title>
    <style>
        @page {
            size: 58mm auto;
            margin: 0;
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 9px;
            line-height: 1.2;
            color: #000;
            width: 54mm;
            margin: 0 auto;
            padding: 2mm 0;
            background-color: #fff;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .border-top {
            border-top: 1px dashed #000;
            margin-top: 4px;
            padding-top: 4px;
        }

        .border-bottom {
            border-bottom: 1px dashed #000;
            margin-bottom: 4px;
            padding-bottom: 4px;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }

        .double-divider {
            border-top: 3px double #000;
            margin: 5px 0;
        }

        .header {
            margin-bottom: 8px;
        }

        .clinic-name {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .clinic-address {
            font-size: 8px;
        }

        .meta-info {
            margin-bottom: 6px;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
        }

        .meta-label {
            flex-shrink: 0;
        }

        .meta-val {
            text-align: right;
            word-break: break-all;
        }

        .table-items {
            width: 100%;
            border-collapse: collapse;
        }

        .table-items td {
            padding: 2px 0;
            vertical-align: top;
        }

        .status-badge {
            display: block;
            border: 1px solid #000;
            padding: 3px;
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            margin: 6px 0;
        }

        .status-badge.lunas {
            background-color: #000;
            color: #fff;
        }

        .no-print-bar {
            background: #f4f4f5;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 6px;
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .no-print-bar button {
            padding: 6px 12px;
            font-size: 11px;
            border-radius: 4px;
            cursor: pointer;
            border: 1px solid #ccc;
        }

        .btn-print {
            background: #800020;
            color: #fff;
            border-color: #800020 !important;
            font-weight: bold;
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                width: 100%;
                padding: 0;
                margin: 0;
            }
        }
    </style>
</head>

<body>

    <div class="no-print no-print-bar">
        <button class="btn-print" onclick="window.print()">🖨️ Print</button>
        <button onclick="window.close()">Tutup</button>
    </div>

    <div class="header text-center">
        <div class="clinic-name">Klinik Zyrra Medica</div>
        <div class="clinic-address">Jl. Kapten Murod Idrus No.15, Ciamis</div>
        <div class="clinic-address">Telp: 0811 2123 311</div>
    </div>

    <div class="divider"></div>

    <!-- Status Pembayaran — hanya ditampilkan jika sudah LUNAS -->
    <?php if ($sudahBayar): ?>
        <div class="status-badge lunas">*** LUNAS / PAID ***</div>
    <?php endif; ?>

    <div class="meta-info">
        <div class="meta-row">
            <span class="meta-label">Tgl/Jam:</span>
            <span class="meta-val"><?= date('d/m/Y H:i') ?></span>
        </div>
        <div class="meta-row">
            <span class="meta-label">No.Rawat:</span>
            <span class="meta-val"><?= htmlspecialchars($kunjungan['no_rawat']) ?></span>
        </div>
        <div class="meta-row">
            <span class="meta-label">No.RM:</span>
            <span class="meta-val"><?= htmlspecialchars($kunjungan['no_rkm_medis']) ?></span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Pasien:</span>
            <span class="meta-val bold"><?= htmlspecialchars(substr($kunjungan['nm_pasien'], 0, 18)) ?></span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Poli/Dr:</span>
            <span
                class="meta-val"><?= htmlspecialchars(substr($kunjungan['nm_poli'], 0, 10)) ?>/<?= htmlspecialchars(substr($kunjungan['nm_dokter'], 0, 10)) ?></span>
        </div>
        <div class="meta-row">
            <span class="meta-label">CaraByr:</span>
            <span class="meta-val"><?= htmlspecialchars($kunjungan['nm_pj']) ?></span>
        </div>
        <?php if ($nota): ?>
            <div class="meta-row">
                <span class="meta-label">No.Nota:</span>
                <span class="meta-val bold"><?= htmlspecialchars($nota['no_nota']) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="double-divider"></div>

    <table class="table-items">
        <!-- Registrasi -->
        <?php if ($biayaRegistrasi > 0): ?>
            <tr>
                <td colspan="2">Registrasi / Admin</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td class="text-right"><?= rp($biayaRegistrasi) ?></td>
            </tr>
        <?php endif; ?>

        <!-- Tindakan Dokter -->
        <?php if ($tindakanDr): ?>
            <tr>
                <td colspan="2" class="bold">[Tindakan Dokter]</td>
            </tr>
            <?php foreach ($tindakanDr as $t): ?>
                <tr>
                    <td style="padding-left: 2px;">- <?= htmlspecialchars(substr($t['nm_perawatan'], 0, 22)) ?></td>
                    <td class="text-right"><?= rp((float) $t['biaya_rawat']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Tindakan Perawat -->
        <?php if ($tindakanPr): ?>
            <tr>
                <td colspan="2" class="bold">[Tindakan Perawat]</td>
            </tr>
            <?php foreach ($tindakanPr as $t): ?>
                <tr>
                    <td style="padding-left: 2px;">- <?= htmlspecialchars(substr($t['nm_perawatan'], 0, 22)) ?></td>
                    <td class="text-right"><?= rp((float) $t['biaya_rawat']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Tindakan Bersama -->
        <?php if ($tindakanDrPr): ?>
            <tr>
                <td colspan="2" class="bold">[Tindakan Bersama]</td>
            </tr>
            <?php foreach ($tindakanDrPr as $t): ?>
                <tr>
                    <td style="padding-left: 2px;">- <?= htmlspecialchars(substr($t['nm_perawatan'], 0, 22)) ?></td>
                    <td class="text-right"><?= rp((float) $t['biaya_rawat']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Obat / Resep -->
        <?php if ($resepItems): ?>
            <tr>
                <td colspan="2" class="bold">[Obat &amp; Alkes]</td>
            </tr>
            <?php foreach ($resepItems as $o): ?>
                <tr>
                    <td style="padding-left: 2px;">
                        - <?= htmlspecialchars(substr($o['nama_brng'], 0, 20)) ?><br>
                        &nbsp;&nbsp;<?= number_format((float) $o['jml'], 0, ',', '.') ?> pcs
                    </td>
                    <td class="text-right" style="vertical-align: bottom;"><?= rp((float) $o['subtotal']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <div class="divider"></div>

    <table class="table-items">
        <tr class="bold">
            <td>TOTAL:</td>
            <td class="text-right"><?= rp($grandTotal) ?></td>
        </tr>
        <?php if ($sudahBayar && count($pembayaran) > 0): ?>
            <?php foreach ($pembayaran as $p): ?>
                <tr>
                    <td>Bayar via <?= htmlspecialchars($p['nama_bayar']) ?>:</td>
                    <td class="text-right"><?= rp((float) $p['besar_bayar']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <div class="divider"></div>

    <div class="text-center" style="margin-top: 8px; font-size: 8px;">
        Terima Kasih atas Kunjungan Anda<br>
        Semoga Lekas Sembuh<br><br>
        Operator: <?= htmlspecialchars($_SESSION['username'] ?? 'Kasir') ?>
    </div>

    <script>
        // Auto print when page opens
        window.addEventListener('DOMContentLoaded', (event) => {
            // give a slight delay for rendering, then open print dialog
            setTimeout(() => {
                window.print();
            }, 300);
        });
    </script>

</body>

</html>