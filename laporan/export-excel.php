<?php
/**
 * laporan/export-excel.php
 * -----------------------------------------------------------------
 * Fitur Export Laporan Keuangan & Omset ke Microsoft Excel (.xls).
 * Mengunduh SELURUH data transaksi pada rentang tanggal tanpa LIMIT.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';

wajibLogin();

// AUTH GUARD: HANYA Admin Utama yang berhak mendownload laporan
if (($_SESSION['role'] ?? '') !== ROLE_ADMIN && ($_SESSION['role'] ?? '') !== 'admin') {
    die("Akses ditolak. Hanya Admin Utama yang berhak mendownload laporan.");
}

$pdo = getKoneksi();

// Filter Parameter (Mendukung parameter tgl_awal/tgl_akhir atau start_date/end_date)
$tglAwal     = $_GET['tgl_awal'] ?? $_GET['start_date'] ?? date('Y-m-01');
$tglAkhir    = $_GET['tgl_akhir'] ?? $_GET['end_date'] ?? date('Y-m-d');
$statusBayar = trim($_GET['status_bayar'] ?? '');

// Set Header PHP murni untuk mendownload file sebagai Excel (.xls)
header("Content-Type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Pendapatan_" . $tglAwal . "_sd_" . $tglAkhir . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// Query Utama (TANPA LIMIT & OFFSET untuk mengunduh seluruh data)
$sqlReg = "SELECT r.no_rawat, r.tgl_registrasi, r.jam_reg, r.kd_pj, r.kd_poli, r.biaya_reg,
                  r.status_bayar, r.stts,
                  p.nm_pasien, p.no_rkm_medis,
                  pol.nm_poli, dok.nm_dokter,
                  pj.png_jawab as nm_pj
           FROM reg_periksa r
           JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
           LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
           LEFT JOIN dokter dok ON r.kd_dokter = dok.kd_dokter
           LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
           WHERE r.tgl_registrasi BETWEEN ? AND ?";

$paramsReg = [$tglAwal, $tglAkhir];

if ($statusBayar !== '') {
    $sqlReg .= " AND r.status_bayar = ?";
    $paramsReg[] = $statusBayar;
}

$sqlReg .= " ORDER BY r.tgl_registrasi DESC, r.jam_reg DESC";

$stmtReg = $pdo->prepare($sqlReg);
$stmtReg->execute($paramsReg);
$daftarReg = $stmtReg->fetchAll();

// Prepared Statements untuk Rincian Komponen Biaya
$stmtDr = $pdo->prepare("SELECT j.nm_perawatan, d.biaya_rawat FROM rawat_jl_dr d JOIN jns_perawatan j ON d.kd_jenis_prw = j.kd_jenis_prw WHERE d.no_rawat = ?");
$stmtPr = $pdo->prepare("SELECT j.nm_perawatan, d.biaya_rawat FROM rawat_jl_pr d JOIN jns_perawatan j ON d.kd_jenis_prw = j.kd_jenis_prw WHERE d.no_rawat = ?");
$stmtDrPr = $pdo->prepare("SELECT j.nm_perawatan, d.biaya_rawat FROM rawat_jl_drpr d JOIN jns_perawatan j ON d.kd_jenis_prw = j.kd_jenis_prw WHERE d.no_rawat = ?");
$stmtObat = $pdo->prepare("SELECT db.nama_brng, dpo.jml, dpo.total as subtotal FROM detail_pemberian_obat dpo JOIN databarang db ON dpo.kode_brng = db.kode_brng WHERE dpo.no_rawat = ?");

$grandTotalLunas = 0;
$grandTotalBelum = 0;
?>
<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr style="background-color: #f2f2f2;">
            <th colspan="10" style="font-size: 16px; font-weight: bold; text-align: center;">
                LAPORAN KEUANGAN &amp; PENDAPATAN KLINIK (<?= htmlspecialchars(NAMA_RS) ?>)
            </th>
        </tr>
        <tr style="background-color: #f2f2f2;">
            <th colspan="10" style="text-align: center;">
                Periode: <?= htmlspecialchars($tglAwal) ?> s/d <?= htmlspecialchars($tglAkhir) ?>
            </th>
        </tr>
        <tr style="background-color: #4CAF50; color: white; font-weight: bold;">
            <th>No</th>
            <th>No. Rawat</th>
            <th>Tanggal &amp; Jam</th>
            <th>No. RM &amp; Nama Pasien</th>
            <th>Jenis Bayar</th>
            <th>Dokter Pemeriksa</th>
            <th>Rincian Tindakan (Rp)</th>
            <th>Rincian Obat (Rp)</th>
            <th>Total Biaya (Rp)</th>
            <th>Status Pembayaran</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($daftarReg)): ?>
        <tr>
            <td colspan="10" style="text-align: center;">Tidak ada data transaksi keuangan dalam periode ini.</td>
        </tr>
    <?php else: ?>
        <?php foreach ($daftarReg as $idx => $reg):
            $noRawat  = $reg['no_rawat'];
            $biayaReg = (float)($reg['biaya_reg'] ?? 0);

            $stmtDr->execute([$noRawat]);
            $tDr = $stmtDr->fetchAll();
            $stmtPr->execute([$noRawat]);
            $tPr = $stmtPr->fetchAll();
            $stmtDrPr->execute([$noRawat]);
            $tDrPr = $stmtDrPr->fetchAll();

            $rincianTindakan = [];
            $totalTindakan   = 0;
            foreach (array_merge($tDr, $tPr, $tDrPr) as $t) {
                $biaya = (float)$t['biaya_rawat'];
                $totalTindakan += $biaya;
                $rincianTindakan[] = $t['nm_perawatan'] . ' (Rp ' . number_format($biaya, 0, ',', '.') . ')';
            }

            $stmtObat->execute([$noRawat]);
            $itemsObat = $stmtObat->fetchAll();
            $rincianObat = [];
            $totalObat   = 0;
            foreach ($itemsObat as $ob) {
                $sub = (float)$ob['subtotal'];
                $totalObat += $sub;
                $rincianObat[] = $ob['nama_brng'] . ' x' . (float)$ob['jml'] . ' (Rp ' . number_format($sub, 0, ',', '.') . ')';
            }

            $totalBiaya = $biayaReg + $totalTindakan + $totalObat;
            $isLunas    = ($reg['status_bayar'] === 'Sudah Bayar');

            if ($isLunas) {
                $grandTotalLunas += $totalBiaya;
            } else {
                $grandTotalBelum += $totalBiaya;
            }
        ?>
        <tr>
            <td style="text-align: center;"><?= $idx + 1 ?></td>
            <td>'<?= htmlspecialchars($reg['no_rawat']) ?></td>
            <td><?= date('d-m-Y', strtotime($reg['tgl_registrasi'])) ?> <?= htmlspecialchars($reg['jam_reg']) ?></td>
            <td>'<?= htmlspecialchars($reg['no_rkm_medis']) ?> - <?= htmlspecialchars($reg['nm_pasien']) ?></td>
            <td><?= htmlspecialchars($reg['nm_pj'] ?: '-') ?></td>
            <td><?= htmlspecialchars($reg['nm_dokter'] ?: '-') ?></td>
            <td><?= !empty($rincianTindakan) ? htmlspecialchars(implode('; ', $rincianTindakan)) : '-' ?></td>
            <td><?= !empty($rincianObat) ? htmlspecialchars(implode('; ', $rincianObat)) : '-' ?></td>
            <td style="text-align: right; font-weight: bold;"><?= $totalBiaya ?></td>
            <td style="text-align: center; font-weight: bold; color: <?= $isLunas ? 'green' : 'orange' ?>;">
                <?= $isLunas ? 'LUNAS' : 'Belum Bayar' ?>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
    <tfoot>
        <tr style="font-weight: bold; background-color: #f2f2f2;">
            <td colspan="8" style="text-align: right;">TOTAL OMSET LUNAS:</td>
            <td style="text-align: right; color: green;"><?= $grandTotalLunas ?></td>
            <td></td>
        </tr>
        <tr style="font-weight: bold; background-color: #f2f2f2;">
            <td colspan="8" style="text-align: right;">TOTAL TAGIHAN BELUM LUNAS:</td>
            <td style="text-align: right; color: orange;"><?= $grandTotalBelum ?></td>
            <td></td>
        </tr>
        <tr style="font-weight: bold; background-color: #e2e2e2;">
            <td colspan="8" style="text-align: right;">GRAND TOTAL OMSET (GROSS):</td>
            <td style="text-align: right; color: blue;"><?= $grandTotalLunas + $grandTotalBelum ?></td>
            <td></td>
        </tr>
    </tfoot>
</table>
