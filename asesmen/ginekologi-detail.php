<?php
/**
 * asesmen/ginekologi-detail.php
 * -----------------------------------------------------------------
 * Form & Riwayat Pemeriksaan Ginekologi Detail →
 * tabel: pemeriksaan_ginekologi_ralan
 * PK komposit: (no_rawat, tgl_perawatan, jam_rawat)
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';

wajibLogin();

$pdo = getKoneksi();

$noRawat = trim($_GET['no_rawat'] ?? $_POST['no_rawat'] ?? '');
if ($noRawat === '') { header('Location: pilih.php'); exit; }

$stmt = $pdo->prepare(
    "SELECT r.no_rawat, r.tgl_registrasi, p.nm_pasien, p.no_rkm_medis
     FROM reg_periksa r JOIN pasien p ON r.no_rkm_medis=p.no_rkm_medis
     WHERE r.no_rawat=?"
);
$stmt->execute([$noRawat]);
$kunjungan = $stmt->fetch();
if (!$kunjungan) { header('Location: pilih.php'); exit; }

$stmtList = $pdo->prepare(
    "SELECT * FROM pemeriksaan_ginekologi_ralan WHERE no_rawat=? ORDER BY tgl_perawatan DESC, jam_rawat DESC"
);
$stmtList->execute([$noRawat]);
$daftarGin = $stmtList->fetchAll();

// Auto-load: ambil data ginekologi terbaru untuk kunjungan ini
$stmtExist = $pdo->prepare(
    "SELECT * FROM pemeriksaan_ginekologi_ralan WHERE no_rawat=? ORDER BY tgl_perawatan DESC, jam_rawat DESC LIMIT 1"
);
$stmtExist->execute([$noRawat]);
$prefill = $stmtExist->fetch() ?: [];
$hasData = !empty($prefill);

// Mode edit eksplisit via ?edit=tgl|jam
$editKey = null;
if (isset($_GET['edit'])) {
    [$eT,$eJ] = explode('|',$_GET['edit'],2);
    $e = $pdo->prepare("SELECT * FROM pemeriksaan_ginekologi_ralan WHERE no_rawat=? AND tgl_perawatan=? AND jam_rawat=?");
    $e->execute([$noRawat,$eT,$eJ]);
    $prefill = $e->fetch() ?: [];
    $editKey = htmlspecialchars($_GET['edit']);
    $hasData = !empty($prefill);
}

$sudahBayar = isSudahBayar($noRawat, $pdo);

// Nilai default tanggal & jam untuk form
$valTgl = !empty($prefill['tgl_perawatan']) ? $prefill['tgl_perawatan'] : date('Y-m-d');
$valJam = !empty($prefill['jam_rawat']) ? substr($prefill['jam_rawat'], 0, 5) : date('H:i');

$error=''; $sukses=false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($sudahBayar) {
        $error = 'Peringatan: Data tidak dapat disimpan karena pasien sudah melakukan pembayaran (LUNAS).';
    } else {
        $mode = $_POST['mode'] ?? ($hasData ? 'update' : 'insert');
        $tglP = $_POST['tgl_perawatan'] ?? date('Y-m-d');
        $jamP = $_POST['jam_rawat'] ?? date('H:i:s');
        if (strlen($jamP)===5) $jamP.=':00';

        $fields = [
            'inspeksi'         => trim($_POST['inspeksi'] ?? ''),
            'inspeksi_vulva'   => trim($_POST['inspeksi_vulva'] ?? ''),
            'inspekulo_gine'   => trim($_POST['inspekulo_gine'] ?? ''),
            'fluxus_gine'      => $_POST['fluxus_gine'] ?? '-',
            'fluor_gine'       => $_POST['fluor_gine'] ?? '-',
            'vulva_inspekulo'  => trim($_POST['vulva_inspekulo'] ?? ''),
            'portio_inspekulo' => trim($_POST['portio_inspekulo'] ?? ''),
            'sondage'          => trim($_POST['sondage'] ?? ''),
            'portio_dalam'     => trim($_POST['portio_dalam'] ?? ''),
            'bentuk'           => trim($_POST['bentuk'] ?? ''),
            'cavum_uteri'      => trim($_POST['cavum_uteri'] ?? ''),
            'mobilitas'        => $_POST['mobilitas'] ?? '-',
            'ukuran'           => trim($_POST['ukuran'] ?? ''),
            'nyeri_tekan'      => $_POST['nyeri_tekan'] ?? '-',
            'adnexa_kanan'     => trim($_POST['adnexa_kanan'] ?? ''),
            'adnexa_kiri'      => trim($_POST['adnexa_kiri'] ?? ''),
            'cavum_douglas'    => trim($_POST['cavum_douglas'] ?? ''),
        ];

        try {
            if ($mode === 'update') {
                $oldT=$_POST['old_tgl'] ?? $prefill['tgl_perawatan'] ?? $tglP;
                $oldJ=$_POST['old_jam'] ?? $prefill['jam_rawat'] ?? $jamP;
                $set = implode(', ', array_map(fn($k)=>"`$k`=?", array_keys($fields)));
                $s=$pdo->prepare("UPDATE pemeriksaan_ginekologi_ralan SET tgl_perawatan=?, jam_rawat=?, $set WHERE no_rawat=? AND tgl_perawatan=? AND jam_rawat=?");
                $s->execute([$tglP, $jamP, ...array_values($fields),$noRawat,$oldT,$oldJ]);
            } else {
                $ck=$pdo->prepare("SELECT COUNT(*) FROM pemeriksaan_ginekologi_ralan WHERE no_rawat=? AND tgl_perawatan=? AND jam_rawat=?");
                $ck->execute([$noRawat,$tglP,$jamP]);
                if ((int)$ck->fetchColumn()>0) {
                    $error="Sudah ada data ginekologi untuk waktu {$tglP} {$jamP}.";
                } else {
                    $cols='no_rawat, tgl_perawatan, jam_rawat, '.implode(', ',array_map(fn($k)=>"`$k`",array_keys($fields)));
                    $ph='?,?,?,'.implode(',',array_fill(0,count($fields),'?'));
                    $s=$pdo->prepare("INSERT INTO pemeriksaan_ginekologi_ralan ($cols) VALUES ($ph)");
                    $s->execute([$noRawat,$tglP,$jamP,...array_values($fields)]);
                }
            }
            if ($error==='') {
                header('Location: ginekologi-detail.php?no_rawat=' . urlencode($noRawat) . '&status=success');
                exit;
            }
        } catch (Throwable $e) {
            error_log('[ginekologi-detail.php] ' . $e->getMessage());
            $error = 'Gagal menyimpan: ' . $e->getMessage();
        }
    }
}

$halamanAktif = 'asesmen';
$judulHalaman = 'Pemeriksaan Ginekologi Detail';
$baseAsset    = '../';
require __DIR__ . '/../lib/layout_header.php';
function ov(array $a, string $k, string $d=''): string { return htmlspecialchars($a[$k]??$d); }
function oSel(array $opts, string $cur, string $def=''): string {
    $h='';
    foreach ($opts as $o) {
        $sel = ($cur===''?$def:$cur)===$o?' selected':'';
        $h.="<option value=\"{$o}\"{$sel}>{$o}</option>";
    }
    return $h;
}
?>
<style>
.fm3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;}
.fm4{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;}
.sec{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--color-primary);margin:18px 0 10px;padding-bottom:5px;border-bottom:1.5px solid var(--color-border);}
.gin-tbl{width:100%;border-collapse:collapse;font-size:12.5px;}
.gin-tbl th{background:var(--color-primary);color:#fff;padding:7px 10px;text-align:left;}
.gin-tbl td{border-bottom:1px solid var(--color-border);padding:6px 10px;}
.gin-tbl tr:hover td{background:#FDF6F8;}
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
        .brand {
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
        <a href="cetak_asesmen.php?type=ginekologi&no_rawat=<?= urlencode($noRawat) ?>" target="_blank"
           class="btn btn-outline" style="font-size:12.5px; padding:6px 14px; display:inline-flex; align-items:center; gap:6px; text-decoration:none;">
            🖨️ Cetak Semua Ginekologi
        </a>
    </div>
</div>

<?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?><div class="alert alert-success no-print" id="alert-simpan-sukses">✔ Data ginekologi berhasil disimpan.</div><?php endif; ?>
<?php if ($error):  ?><div class="alert alert-danger no-print"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($daftarGin): ?>
<div class="card card-riwayat-container">
    <p class="card-title">Riwayat Pemeriksaan Ginekologi</p>
    <div style="overflow-x:auto;">
    <table class="gin-tbl">
        <thead><tr>
            <th>Tanggal</th><th>Jam</th><th>Inspeksi</th><th>Vulva</th>
            <th>Portio Dalam</th><th>Bentuk</th><th>Ukuran</th><th>Nyeri Tekan</th>
            <th>Adnexa Kanan</th><th>Adnexa Kiri</th><th>Cavum Douglas</th><th style="width:130px;" class="col-aksi">Aksi</th>
        </tr></thead>
        <tbody>
        <?php foreach ($daftarGin as $g): ?>
        <tr>
            <td><?= htmlspecialchars(date('d-m-Y',strtotime($g['tgl_perawatan']))) ?></td>
            <td><?= htmlspecialchars($g['jam_rawat']) ?></td>
            <td><?= htmlspecialchars($g['inspeksi']??'') ?></td>
            <td><?= htmlspecialchars($g['inspeksi_vulva']??'') ?></td>
            <td><?= htmlspecialchars($g['portio_dalam']??'') ?></td>
            <td><?= htmlspecialchars($g['bentuk']??'') ?></td>
            <td><?= htmlspecialchars($g['ukuran']??'') ?></td>
            <td><?= htmlspecialchars($g['nyeri_tekan']??'') ?></td>
            <td><?= htmlspecialchars($g['adnexa_kanan']??'') ?></td>
            <td><?= htmlspecialchars($g['adnexa_kiri']??'') ?></td>
            <td><?= htmlspecialchars($g['cavum_douglas']??'') ?></td>
            <td class="col-aksi">
                <?php if ($sudahBayar): ?>
                    <span class="text-muted" style="font-size:12.5px;">🔒 Terkunci</span>
                <?php else: ?>
                    <a href="?no_rawat=<?= urlencode($noRawat) ?>&edit=<?= urlencode($g['tgl_perawatan'].'|'.$g['jam_rawat']) ?>"
                       class="btn btn-outline" style="font-size:12px;padding:3px 8px;">Edit</a>
                <?php endif; ?>
                <a href="cetak_asesmen.php?type=ginekologi&no_rawat=<?= urlencode($noRawat) ?>&tgl=<?= urlencode($g['tgl_perawatan']) ?>&jam=<?= urlencode($g['jam_rawat']) ?>" target="_blank"
                    class="btn btn-outline btn-print-act"
                    style="font-size:11.5px; padding:3px 8px; margin-left:4px; border-color:var(--color-primary); color:var(--color-primary); text-decoration:none;" title="Cetak Ginekologi">🖨️ Cetak</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <p class="card-title"><?= $editKey ? 'Edit Data Ginekologi' : ($hasData ? 'Edit Data Ginekologi (Data Tersedia)' : 'Pemeriksaan Ginekologi Baru') ?></p>
    <?php if ($hasData && !$editKey): ?>
        <div style="background:#EBF5FF; border:1px solid #B3D4F0; border-radius:6px; padding:9px 12px; margin-bottom:12px; font-size:12.5px;">
            ✔ <strong>Data ginekologi ditemukan</strong> untuk kunjungan ini. Form diisi otomatis. Submit untuk menyimpan perubahan.
        </div>
    <?php endif; ?>
    <?php if ($sudahBayar): ?>
        <div class="alert alert-danger" style="margin-bottom:15px; font-size:12.5px;">
            🔒 <strong>Data dikunci:</strong> Pembayaran telah diselesaikan. Anda tidak dapat menambah atau mengubah data ginekologi ini.
        </div>
    <?php endif; ?>

    <form method="post">
        <fieldset <?= $sudahBayar ? 'disabled' : '' ?> style="border:0; padding:0; margin:0;">
        <input type="hidden" name="no_rawat" value="<?= htmlspecialchars($noRawat) ?>">
        <input type="hidden" name="mode" value="<?= ($editKey || $hasData) ? 'update' : 'insert' ?>">
        <?php if ($editKey): [$gT,$gJ]=explode('|',urldecode($editKey),2); ?>
            <input type="hidden" name="old_tgl" value="<?= htmlspecialchars($gT) ?>">
            <input type="hidden" name="old_jam" value="<?= htmlspecialchars($gJ) ?>">
        <?php elseif ($hasData && !empty($prefill['tgl_perawatan'])): ?>
            <input type="hidden" name="old_tgl" value="<?= htmlspecialchars($prefill['tgl_perawatan']) ?>">
            <input type="hidden" name="old_jam" value="<?= htmlspecialchars($prefill['jam_rawat']) ?>">
        <?php endif; ?>

        <div class="fm3" style="max-width:480px;margin-bottom:14px;">
            <div>
                <label for="tgl_perawatan">Tanggal *</label>
                <input type="date" id="tgl_perawatan" name="tgl_perawatan" value="<?= htmlspecialchars($valTgl) ?>">
            </div>
            <div>
                <label for="jam_rawat">Jam *</label>
                <input type="time" id="jam_rawat" name="jam_rawat" value="<?= htmlspecialchars($valJam) ?>">
            </div>
        </div>

        <p class="sec">Pemeriksaan Inspeksi & Inspekulo</p>
        <div class="fm3">
            <div>
                <label for="inspeksi">Inspeksi General</label>
                <input type="text" id="inspeksi" name="inspeksi" placeholder="Normal / DBN"
                       value="<?= ov($prefill,'inspeksi') ?>">
            </div>
            <div>
                <label for="inspeksi_vulva">Inspeksi Vulva</label>
                <input type="text" id="inspeksi_vulva" name="inspeksi_vulva" placeholder="Tak ada kelainan"
                       value="<?= ov($prefill,'inspeksi_vulva') ?>">
            </div>
            <div>
                <label for="inspekulo_gine">Inspekulo Ginekologi</label>
                <input type="text" id="inspekulo_gine" name="inspekulo_gine" placeholder="Portio licin..."
                       value="<?= ov($prefill,'inspekulo_gine') ?>">
            </div>
        </div>

        <div class="fm4" style="margin-top:10px;">
            <div>
                <label for="fluxus_gine">Fluxus</label>
                <select id="fluxus_gine" name="fluxus_gine">
                    <?= oSel(['+','-'], ov($prefill,'fluxus_gine'), '-') ?>
                </select>
            </div>
            <div>
                <label for="fluor_gine">Fluor</label>
                <select id="fluor_gine" name="fluor_gine">
                    <?= oSel(['+','-'], ov($prefill,'fluor_gine'), '-') ?>
                </select>
            </div>
            <div>
                <label for="vulva_inspekulo">Vulva Inspekulo</label>
                <input type="text" id="vulva_inspekulo" name="vulva_inspekulo" placeholder="Tenang"
                       value="<?= ov($prefill,'vulva_inspekulo') ?>">
            </div>
            <div>
                <label for="portio_inspekulo">Portio Inspekulo</label>
                <input type="text" id="portio_inspekulo" name="portio_inspekulo" placeholder="Licin"
                       value="<?= ov($prefill,'portio_inspekulo') ?>">
            </div>
        </div>

        <p class="sec">Pemeriksaan Dalam (VT / Bimanual)</p>
        <div class="fm4">
            <div>
                <label for="sondage">Sondage Uteri</label>
                <input type="text" id="sondage" name="sondage" placeholder="7 cm"
                       value="<?= ov($prefill,'sondage') ?>">
            </div>
            <div>
                <label for="portio_dalam">Portio Dalam</label>
                <input type="text" id="portio_dalam" name="portio_dalam" placeholder="Lunak"
                       value="<?= ov($prefill,'portio_dalam') ?>">
            </div>
            <div>
                <label for="bentuk">Bentuk Uterus</label>
                <input type="text" id="bentuk" name="bentuk" placeholder="Normal / Antefleksi"
                       value="<?= ov($prefill,'bentuk') ?>">
            </div>
            <div>
                <label for="cavum_uteri">Cavum Uteri</label>
                <input type="text" id="cavum_uteri" name="cavum_uteri" placeholder="Biasa"
                       value="<?= ov($prefill,'cavum_uteri') ?>">
            </div>
        </div>

        <div class="fm4" style="margin-top:10px;">
            <div>
                <label for="mobilitas">Mobilitas</label>
                <select id="mobilitas" name="mobilitas">
                    <?= oSel(['+','-'], ov($prefill,'mobilitas'), '+') ?>
                </select>
            </div>
            <div>
                <label for="ukuran">Ukuran Uterus</label>
                <input type="text" id="ukuran" name="ukuran" placeholder="Sebesar telur bebek"
                       value="<?= ov($prefill,'ukuran') ?>">
            </div>
            <div>
                <label for="nyeri_tekan">Nyeri Tekan</label>
                <select id="nyeri_tekan" name="nyeri_tekan">
                    <?= oSel(['+','-'], ov($prefill,'nyeri_tekan'), '-') ?>
                </select>
            </div>
            <div>
                <label for="cavum_douglas">Cavum Douglas</label>
                <input type="text" id="cavum_douglas" name="cavum_douglas" placeholder="Tidak menonjol"
                       value="<?= ov($prefill,'cavum_douglas') ?>">
            </div>
        </div>

        <div class="fm2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px;">
            <div>
                <label for="adnexa_kanan">Adnexa Kanan</label>
                <input type="text" id="adnexa_kanan" name="adnexa_kanan" placeholder="Biasa / Nyeri (-)"
                       value="<?= ov($prefill,'adnexa_kanan') ?>">
            </div>
            <div>
                <label for="adnexa_kiri">Adnexa Kiri</label>
                <input type="text" id="adnexa_kiri" name="adnexa_kiri" placeholder="Biasa / Nyeri (-)"
                       value="<?= ov($prefill,'adnexa_kiri') ?>">
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:20px;">
            <button type="submit" class="btn btn-primary"><?= ($editKey || $hasData) ? 'Simpan Perubahan' : 'Simpan Data Ginekologi' ?></button>
            <a href="pilih.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-outline">Batal</a>
        </div>
        </fieldset>
    </form>
</div>

<script>
// UX: Auto-SweetAlert + redirect setelah simpan berhasil
(function () {
    const params = new URLSearchParams(window.location.search);
    if (params.get('status') !== 'success') return;
    const noRawat = params.get('no_rawat') || '';
    const baseUrl = 'ginekologi-detail.php?no_rawat=' + encodeURIComponent(noRawat);
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil Disimpan!',
            text: 'Data ginekologi berhasil disimpan.',
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
