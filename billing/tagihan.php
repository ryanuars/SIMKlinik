<?php
/**
 * billing/tagihan.php
 * -----------------------------------------------------------------
 * Halaman detail tagihan per kunjungan (no_rawat).
 * Menampilkan breakdown biaya lengkap:
 *   1. Registrasi (tarif admin dari reg_periksa)
 *   2. Tindakan Dokter (rawat_jl_dr)
 *   3. Tindakan Perawat (rawat_jl_pr)
 *   4. Tindakan Bersama (rawat_jl_drpr)
 *   5. Obat/Resep (resep_dokter × harga databarang.ralan)
 * 
 * Juga bisa membuat / update nota_jalan (cetak/konfirmasi nota).
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
$_SESSION['last_no_rawat'] = $noRawat;

// Data kunjungan — sertakan status_bayar & stts untuk deteksi apakah Java sudah proses bayar
$stmt = $pdo->prepare(
    "SELECT r.no_rawat, r.tgl_registrasi, r.jam_reg, r.kd_pj, r.kd_poli, r.biaya_reg,
            r.status_bayar, r.stts,
            p.nm_pasien, p.no_rkm_medis, p.jk, p.tgl_lahir,
            pol.nm_poli, d.nm_dokter, d.kd_dokter as kd_dok,
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
    header('Location: index.php');
    exit;
}

// Status pembayaran dari reg_periksa (di-update oleh Java saat kasir bayar)
$sudahBayar = ($kunjungan['status_bayar'] === 'Sudah Bayar');

// ============================
// Ambil komponen tagihan
// ============================

// 1. Registrasi
$biayaRegistrasi = (float)($kunjungan['biaya_reg'] ?? 0);

// 2. Tindakan Dokter
$tindakanDr = $pdo->prepare(
    "SELECT d.kd_jenis_prw, j.nm_perawatan, d.kd_dokter, dok.nm_dokter,
            d.tgl_perawatan, d.jam_rawat, d.biaya_rawat, d.stts_bayar
     FROM rawat_jl_dr d
     JOIN jns_perawatan j ON d.kd_jenis_prw = j.kd_jenis_prw
     JOIN dokter dok ON d.kd_dokter = dok.kd_dokter
     WHERE d.no_rawat = ? ORDER BY d.tgl_perawatan, d.jam_rawat"
);
$tindakanDr->execute([$noRawat]);
$tindakanDr = $tindakanDr->fetchAll();
$totalTindakanDr = array_sum(array_column($tindakanDr, 'biaya_rawat'));

// 3. Tindakan Perawat
$tindakanPr = $pdo->prepare(
    "SELECT d.kd_jenis_prw, j.nm_perawatan, d.nip, ptg.nama as nm_petugas,
            d.tgl_perawatan, d.jam_rawat, d.biaya_rawat, d.stts_bayar
     FROM rawat_jl_pr d
     JOIN jns_perawatan j ON d.kd_jenis_prw = j.kd_jenis_prw
     JOIN petugas ptg ON d.nip = ptg.nip
     WHERE d.no_rawat = ? ORDER BY d.tgl_perawatan, d.jam_rawat"
);
$tindakanPr->execute([$noRawat]);
$tindakanPr = $tindakanPr->fetchAll();
$totalTindakanPr = array_sum(array_column($tindakanPr, 'biaya_rawat'));

// 4. Tindakan Bersama
$tindakanDrPr = $pdo->prepare(
    "SELECT d.kd_jenis_prw, j.nm_perawatan, d.kd_dokter, dok.nm_dokter,
            d.nip, ptg.nama as nm_petugas, d.tgl_perawatan, d.jam_rawat, d.biaya_rawat, d.stts_bayar
     FROM rawat_jl_drpr d
     JOIN jns_perawatan j ON d.kd_jenis_prw = j.kd_jenis_prw
     JOIN dokter dok ON d.kd_dokter = dok.kd_dokter
     JOIN petugas ptg ON d.nip = ptg.nip
     WHERE d.no_rawat = ? ORDER BY d.tgl_perawatan, d.jam_rawat"
);
$tindakanDrPr->execute([$noRawat]);
$tindakanDrPr = $tindakanDrPr->fetchAll();
$totalTindakanDrPr = array_sum(array_column($tindakanDrPr, 'biaya_rawat'));

// 5. Obat/Resep
$resepItems = $pdo->prepare(
    "SELECT dpo.tgl_perawatan, dpo.kode_brng, db.nama_brng, dpo.biaya_obat as harga_satuan,
            dpo.jml, dpo.total as subtotal, ro.no_resep, ro.tgl_penyerahan, rd.aturan_pakai
     FROM detail_pemberian_obat dpo
     LEFT JOIN databarang db ON dpo.kode_brng = db.kode_brng
     LEFT JOIN resep_obat ro ON dpo.no_rawat = ro.no_rawat AND dpo.tgl_perawatan = ro.tgl_perawatan AND dpo.jam = ro.jam
     LEFT JOIN resep_dokter rd ON ro.no_resep = rd.no_resep AND dpo.kode_brng = rd.kode_brng
     WHERE dpo.no_rawat = ?
     ORDER BY dpo.tgl_perawatan, db.nama_brng"
);
$resepItems->execute([$noRawat]);
$resepItems = $resepItems->fetchAll();
$totalObat = array_sum(array_column($resepItems, 'subtotal'));

// Total keseluruhan
$grandTotal = $biayaRegistrasi + $totalTindakanDr + $totalTindakanPr + $totalTindakanDrPr + $totalObat;

// Cek nota yang sudah ada (bisa dibuat oleh Java maupun sesi PHP sebelumnya)
$notaExist = $pdo->prepare("SELECT * FROM nota_jalan WHERE no_rawat = ?");
$notaExist->execute([$noRawat]);
$nota = $notaExist->fetch();

// Cek riwayat pembayaran (detail_nota_jalan diisi Java saat kasir bayar)
$pembayaranExist = null;
if ($nota) {
    $stmtPbyr = $pdo->prepare("SELECT * FROM detail_nota_jalan WHERE no_rawat = ?");
    $stmtPbyr->execute([$noRawat]);
    $pembayaranExist = $stmtPbyr->fetch();
}

$halamanAktif = 'billing';
$judulHalaman = 'Detail Tagihan';
$baseAsset    = '../';
require __DIR__ . '/../lib/layout_header.php';

function rp(float $n): string {
    return 'Rp ' . number_format($n, 0, ',', '.');
}

/**
 * PHP HANYA READ-ONLY untuk billing.
 * Proses pembayaran (tutup billing) dilakukan di aplikasi Java Khanza.
 * PHP bertugas: tampilkan tagihan + cetak nota thermal 58mm.
 */
?>
<style>
.billing-section { margin-bottom: 20px; }
.billing-table th { background: var(--color-primary); color: #fff; }
.billing-table td, .billing-table th { padding: 7px 10px; font-size: 13px; }
.billing-table tr:hover td { background: #fdf6f8; }
.total-row td { font-weight: 700; background: #f5f0f2; }
.summary-box { background: linear-gradient(135deg, var(--color-primary), #8b1a2e); color: #fff; border-radius: 10px; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.summary-box .label { font-size: 13px; opacity: .85; margin-bottom: 4px; }
.summary-box .amount { font-size: 26px; font-weight: 700; letter-spacing: .02em; }

/* Status pembayaran banner */
.status-bayar-banner {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 20px; border-radius: 10px; margin-bottom: 20px;
    font-size: 14px; font-weight: 600;
}
.status-bayar-banner.sudah {
    background: #d1fae5; border: 1.5px solid #10b981; color: #065f46;
}
.status-bayar-banner.belum {
    background: #fef3c7; border: 1.5px solid #f59e0b; color: #92400e;
}
.status-bayar-banner .status-icon { font-size: 24px; }
.status-bayar-banner .status-detail { font-size: 12px; font-weight: 400; opacity: .8; margin-top: 2px; }

/* Kotak info Java */
.java-info-box {
    background: #eff6ff; border: 1px solid #93c5fd; border-radius: 8px;
    padding: 12px 16px; font-size: 12.5px; color: #1e40af; display: flex;
    align-items: flex-start; gap: 10px; margin-bottom: 16px;
}
.java-info-box .info-icon { font-size: 18px; flex-shrink: 0; margin-top: 1px; }
</style>

<div style="margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; font-size:13px;">
    <div>
        <a href="index.php?tanggal=<?= urlencode($kunjungan['tgl_registrasi']) ?>" class="btn btn-back">← Daftar Billing</a>
        <span class="text-muted" style="margin-left:8px;">&bull; <code><?= htmlspecialchars($noRawat) ?></code> &bull; <strong><?= htmlspecialchars($kunjungan['nm_pasien']) ?></strong></span>
    </div>
    <div style="display:flex; gap:6px;">
        <a href="../asesmen/pilih.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-outline" style="font-size:12px; padding:4px 10px; text-decoration:none;">Menu Asesmen</a>
        <a href="../asesmen/index.php" class="btn btn-outline" style="font-size:12px; padding:4px 10px; text-decoration:none;">Daftar Pasien</a>
        <a href="../dashboard.php" class="btn btn-outline" style="font-size:12px; padding:4px 10px; text-decoration:none;">Dashboard</a>
    </div>
</div>

<!-- ===== STATUS PEMBAYARAN BANNER ===== -->
<?php if ($sudahBayar): ?>
<div class="status-bayar-banner sudah">
    <span class="status-icon">✅</span>
    <div>
        <div>Sudah Bayar</div>
        <div class="status-detail">
            <?php if ($pembayaranExist): ?>
                Via <strong><?= htmlspecialchars($pembayaranExist['nama_bayar']) ?></strong>
                · Dibayar <strong><?= rp((float)$pembayaranExist['besar_bayar']) ?></strong>
                <?php if ($nota): ?> · No. Nota: <code><?= htmlspecialchars($nota['no_nota']) ?></code><?php endif; ?>
            <?php else: ?>
                Tercatat lunas di sistem — detail pembayaran tersimpan di Java Khanza.
            <?php endif; ?>
        </div>
    </div>
</div>
<?php else: ?>
<div class="status-bayar-banner belum">
    <span class="status-icon">⏳</span>
    <div>
        <div>Belum Lunas — Menunggu Proses Pembayaran di Kasir</div>
        <div class="status-detail">Tagihan di bawah ini sudah final. Silakan tutup billing melalui aplikasi Java Khanza di kasir.</div>
    </div>
</div>
<?php endif; ?>

<!-- Info: proses bayar di Java -->
<div class="java-info-box">
    <span class="info-icon">ℹ️</span>
    <div>
        <strong>Alur Billing:</strong> Input &amp; proses pembayaran dilakukan di <strong>Aplikasi Java Khanza</strong> (kasir).
        Halaman ini menampilkan rincian tagihan dan memungkinkan <strong>cetak nota thermal</strong> kapan saja — baik sebelum maupun sesudah bayar.
        Status akan otomatis ter-update mengikuti Java.
    </div>
</div>

<!-- Info Kunjungan -->
<div class="card">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
        <div><span class="text-muted" style="font-size:11px;">PASIEN</span><br><strong><?= htmlspecialchars($kunjungan['nm_pasien']) ?></strong></div>
        <div><span class="text-muted" style="font-size:11px;">NO. RM</span><br><code><?= htmlspecialchars($kunjungan['no_rkm_medis']) ?></code></div>
        <div><span class="text-muted" style="font-size:11px;">POLIKLINIK</span><br><?= htmlspecialchars($kunjungan['nm_poli']) ?></div>
        <div><span class="text-muted" style="font-size:11px;">DOKTER</span><br><?= htmlspecialchars($kunjungan['nm_dokter'] ?? '-') ?></div>
        <div><span class="text-muted" style="font-size:11px;">JAMINAN</span><br><?= htmlspecialchars($kunjungan['nm_pj'] ?? $kunjungan['kd_pj'] ?? '-') ?></div>
        <div><span class="text-muted" style="font-size:11px;">TGL REGISTRASI</span><br><?= date('d-m-Y', strtotime($kunjungan['tgl_registrasi'])) ?> <?= htmlspecialchars(substr($kunjungan['jam_reg'] ?? '', 0, 5)) ?></div>
    </div>
</div>

<!-- Grand Total Box -->
<div class="summary-box">
    <div>
        <div class="label">TOTAL TAGIHAN KESELURUHAN</div>
        <div class="amount"><?= rp($grandTotal) ?></div>
        <div style="font-size:11px;opacity:.75;margin-top:6px;">
            Registrasi <?= rp($biayaRegistrasi) ?> + Tindakan <?= rp($totalTindakanDr + $totalTindakanPr + $totalTindakanDrPr) ?> + Obat <?= rp($totalObat) ?>
        </div>
    </div>
    <?php if ($nota): ?>
    <div style="text-align:right;">
        <div class="label">No. Nota</div>
        <code style="font-size:16px;background:rgba(255,255,255,0.2);padding:6px 12px;border-radius:6px;"><?= htmlspecialchars($nota['no_nota']) ?></code>
        <div style="font-size:11px;opacity:.75;margin-top:6px;"><?= date('d-m-Y H:i', strtotime($nota['tanggal'] . ' ' . $nota['jam'])) ?></div>
    </div>
    <?php endif; ?>
</div>

<!-- Detail: Tindakan Dokter -->
<?php if ($tindakanDr): ?>
<div class="card billing-section">
    <p class="card-title">Tindakan Dokter — <?= rp($totalTindakanDr) ?></p>
    <table class="table billing-table">
        <thead><tr><th>Tindakan</th><th>Dokter</th><th>Tanggal</th><th style="text-align:right;">Biaya</th><th>Status Bayar</th></tr></thead>
        <tbody>
        <?php foreach ($tindakanDr as $t): ?>
        <tr>
            <td><?= htmlspecialchars($t['nm_perawatan']) ?></td>
            <td><?= htmlspecialchars($t['nm_dokter']) ?></td>
            <td><?= date('d-m-Y', strtotime($t['tgl_perawatan'])) ?> <?= htmlspecialchars(substr($t['jam_rawat'], 0, 5)) ?></td>
            <td style="text-align:right;font-family:monospace;"><?= rp((float)$t['biaya_rawat']) ?></td>
            <td><span class="badge <?= $t['stts_bayar']==='Sudah'?'badge-success':'badge-warning' ?>"><?= htmlspecialchars($t['stts_bayar']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row"><td colspan="3">Subtotal Tindakan Dokter</td><td style="text-align:right;font-family:monospace;"><?= rp($totalTindakanDr) ?></td><td></td></tr>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Detail: Tindakan Perawat -->
<?php if ($tindakanPr): ?>
<div class="card billing-section">
    <p class="card-title">Tindakan Perawat — <?= rp($totalTindakanPr) ?></p>
    <table class="table billing-table">
        <thead><tr><th>Tindakan</th><th>Perawat</th><th>Tanggal</th><th style="text-align:right;">Biaya</th><th>Status Bayar</th></tr></thead>
        <tbody>
        <?php foreach ($tindakanPr as $t): ?>
        <tr>
            <td><?= htmlspecialchars($t['nm_perawatan']) ?></td>
            <td><?= htmlspecialchars($t['nm_petugas']) ?></td>
            <td><?= date('d-m-Y', strtotime($t['tgl_perawatan'])) ?> <?= htmlspecialchars(substr($t['jam_rawat'], 0, 5)) ?></td>
            <td style="text-align:right;font-family:monospace;"><?= rp((float)$t['biaya_rawat']) ?></td>
            <td><span class="badge <?= $t['stts_bayar']==='Sudah'?'badge-success':'badge-warning' ?>"><?= htmlspecialchars($t['stts_bayar']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row"><td colspan="3">Subtotal Tindakan Perawat</td><td style="text-align:right;font-family:monospace;"><?= rp($totalTindakanPr) ?></td><td></td></tr>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Detail: Tindakan Bersama -->
<?php if ($tindakanDrPr): ?>
<div class="card billing-section">
    <p class="card-title">Tindakan Bersama (Dr + Pr) — <?= rp($totalTindakanDrPr) ?></p>
    <table class="table billing-table">
        <thead><tr><th>Tindakan</th><th>Petugas</th><th>Tanggal</th><th style="text-align:right;">Biaya</th><th>Status Bayar</th></tr></thead>
        <tbody>
        <?php foreach ($tindakanDrPr as $t): ?>
        <tr>
            <td><?= htmlspecialchars($t['nm_perawatan']) ?></td>
            <td><small>Dr: <?= htmlspecialchars($t['nm_dokter']) ?><br>Pr: <?= htmlspecialchars($t['nm_petugas']) ?></small></td>
            <td><?= date('d-m-Y', strtotime($t['tgl_perawatan'])) ?> <?= htmlspecialchars(substr($t['jam_rawat'], 0, 5)) ?></td>
            <td style="text-align:right;font-family:monospace;"><?= rp((float)$t['biaya_rawat']) ?></td>
            <td><span class="badge <?= $t['stts_bayar']==='Sudah'?'badge-success':'badge-warning' ?>"><?= htmlspecialchars($t['stts_bayar']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row"><td colspan="3">Subtotal Tindakan Bersama</td><td style="text-align:right;font-family:monospace;"><?= rp($totalTindakanDrPr) ?></td><td></td></tr>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Detail: Obat/Resep -->
<?php if ($resepItems): ?>
<div class="card billing-section">
    <p class="card-title">Obat / Resep — <?= rp($totalObat) ?></p>
    <table class="table billing-table">
        <thead><tr><th>Nama Obat</th><th>No. Resep</th><th>Aturan Pakai</th><th style="text-align:right;">Jml</th><th style="text-align:right;">Harga/Sat</th><th style="text-align:right;">Subtotal</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($resepItems as $o): ?>
        <tr>
            <td><?= htmlspecialchars($o['nama_brng'] ?? $o['kode_brng']) ?></td>
            <td><code style="font-size:11px;"><?= htmlspecialchars($o['no_resep']) ?></code></td>
            <td><?= htmlspecialchars($o['aturan_pakai'] ?? '-') ?></td>
            <td style="text-align:right;"><?= number_format((float)$o['jml'], 2, ',', '.') ?></td>
            <td style="text-align:right;font-family:monospace;"><?= rp((float)$o['harga_satuan']) ?></td>
            <td style="text-align:right;font-family:monospace;"><?= rp((float)$o['subtotal']) ?></td>
            <td>
                <?php if (!empty($o['tgl_penyerahan']) && $o['tgl_penyerahan'] !== '0000-00-00'): ?>
                    <span class="badge badge-success">Diserahkan</span>
                <?php elseif (!empty($o['tgl_perawatan']) && $o['tgl_perawatan'] !== '0000-00-00'): ?>
                    <span class="badge badge-warning">Tervalidasi</span>
                <?php else: ?>
                    <span class="badge" style="background:#eee;color:#888;">Menunggu</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row"><td colspan="5">Subtotal Obat</td><td style="text-align:right;font-family:monospace;"><?= rp($totalObat) ?></td><td></td></tr>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Bila tidak ada tagihan -->
<?php if (!$tindakanDr && !$tindakanPr && !$tindakanDrPr && !$resepItems && $biayaRegistrasi == 0): ?>
<div class="alert alert-warning">Belum ada komponen tagihan yang tercatat untuk kunjungan ini.</div>
<?php endif; ?>

<!-- ===== Ringkasan Total & Aksi Cetak ===== -->
<div class="card">
    <p class="card-title">Ringkasan Tagihan</p>
    <table style="width:100%;max-width:420px;border-collapse:collapse;font-size:14px;">
        <?php if ($biayaRegistrasi > 0): ?>
        <tr><td style="padding:6px 0;">Administrasi / Registrasi</td><td style="text-align:right;font-family:monospace;"><?= rp($biayaRegistrasi) ?></td></tr>
        <?php endif; ?>
        <?php if ($totalTindakanDr > 0): ?>
        <tr><td style="padding:6px 0;">Tindakan Dokter</td><td style="text-align:right;font-family:monospace;"><?= rp($totalTindakanDr) ?></td></tr>
        <?php endif; ?>
        <?php if ($totalTindakanPr > 0): ?>
        <tr><td style="padding:6px 0;">Tindakan Perawat</td><td style="text-align:right;font-family:monospace;"><?= rp($totalTindakanPr) ?></td></tr>
        <?php endif; ?>
        <?php if ($totalTindakanDrPr > 0): ?>
        <tr><td style="padding:6px 0;">Tindakan Bersama</td><td style="text-align:right;font-family:monospace;"><?= rp($totalTindakanDrPr) ?></td></tr>
        <?php endif; ?>
        <?php if ($totalObat > 0): ?>
        <tr><td style="padding:6px 0;">Obat / Resep</td><td style="text-align:right;font-family:monospace;"><?= rp($totalObat) ?></td></tr>
        <?php endif; ?>
        <tr style="border-top:2px solid var(--color-primary);">
            <td style="padding:10px 0;font-weight:700;font-size:16px;">TOTAL</td>
            <td style="text-align:right;font-family:monospace;font-weight:700;font-size:16px;color:var(--color-primary);"><?= rp($grandTotal) ?></td>
        </tr>
    </table>

    <!-- Tombol Aksi -->
    <div style="margin-top:20px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
        <!-- Cetak Thermal 58mm — selalu tersedia -->
        <a href="cetak-thermal.php?no_rawat=<?= urlencode($noRawat) ?>"
           class="btn btn-primary" target="_blank"
           title="Cetak ke printer thermal kasir 58mm">
            🖨️ Cetak Nota Thermal
        </a>

        <!-- Cetak A4/PDF — hanya kalau nota sudah ada di system -->
        <?php if ($nota): ?>
        <a href="cetak.php?no_rawat=<?= urlencode($noRawat) ?>"
           class="btn btn-outline" target="_blank"
           title="Cetak nota A4 / simpan PDF">
            📄 Cetak A4 / PDF
        </a>
        <?php endif; ?>

        <a href="index.php?tanggal=<?= urlencode($kunjungan['tgl_registrasi']) ?>" class="btn btn-outline">← Kembali</a>
    </div>

    <!-- Catatan penggunaan -->
    <p style="margin-top:14px;font-size:11.5px;color:#888;border-top:1px solid #eee;padding-top:10px;">
        💡 <strong>Catatan:</strong> Tombol <em>Cetak Nota Thermal</em> dapat ditekan kapan saja — sebelum maupun sesudah pembayaran.
        Nota akan menampilkan status terkini secara otomatis.
        Untuk menutup billing (input pembayaran &amp; jurnal), gunakan <strong>Aplikasi Java Khanza</strong> di kasir.
    </p>
</div>

<?php require __DIR__ . '/../lib/layout_footer.php'; ?>
