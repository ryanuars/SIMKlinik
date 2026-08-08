<?php
/**
 * asesmen/obstetri-detail.php
 * -----------------------------------------------------------------
 * Form Pemeriksaan Obstetri Detail →
 * tabel: pemeriksaan_obstetri_ralan
 * PK komposit: (no_rawat, tgl_perawatan, jam_rawat)
 * Alur: Auto-load data terbaru per no_rawat → INSERT jika baru, UPDATE jika ada
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
    "SELECT r.no_rawat, r.tgl_registrasi,
            p.nm_pasien, p.no_rkm_medis
     FROM reg_periksa r JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
     WHERE r.no_rawat = ?"
);
$stmt->execute([$noRawat]);
$kunjungan = $stmt->fetch();
if (!$kunjungan) { header('Location: pilih.php'); exit; }

// Ambil seluruh riwayat obstetri untuk no_rawat ini (untuk tabel riwayat)
$stmtList = $pdo->prepare(
    "SELECT * FROM pemeriksaan_obstetri_ralan WHERE no_rawat=? ORDER BY tgl_perawatan DESC, jam_rawat DESC"
);
$stmtList->execute([$noRawat]);
$daftarObs = $stmtList->fetchAll();

// Auto-load: ambil data obstetri terbaru untuk kunjungan ini
$stmtExist = $pdo->prepare(
    "SELECT * FROM pemeriksaan_obstetri_ralan WHERE no_rawat=? ORDER BY tgl_perawatan DESC, jam_rawat DESC LIMIT 1"
);
$stmtExist->execute([$noRawat]);
$prefill = $stmtExist->fetch() ?: [];
$hasData = !empty($prefill);

// Mode edit eksplisit via ?edit=tgl|jam
$editKey = null;
if (isset($_GET['edit'])) {
    [$eT,$eJ] = explode('|', $_GET['edit'], 2);
    $stmtEd = $pdo->prepare(
        "SELECT * FROM pemeriksaan_obstetri_ralan WHERE no_rawat=? AND tgl_perawatan=? AND jam_rawat=?"
    );
    $stmtEd->execute([$noRawat,$eT,$eJ]);
    $prefill = $stmtEd->fetch() ?: [];
    $editKey = htmlspecialchars($_GET['edit']);
    $hasData = !empty($prefill);
}

$sudahBayar = isSudahBayar($noRawat, $pdo);

// Nilai default tanggal & jam untuk form
$valTgl = !empty($prefill['tgl_perawatan']) ? $prefill['tgl_perawatan'] : date('Y-m-d');
$valJam = !empty($prefill['jam_rawat']) ? substr($prefill['jam_rawat'], 0, 5) : date('H:i');

$error = ''; $sukses = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($sudahBayar) {
        $error = 'Peringatan: Data tidak dapat disimpan karena pasien sudah melakukan pembayaran (LUNAS).';
    } else {
        $tglP = $_POST['tgl_perawatan'] ?? date('Y-m-d');
        $jamP = $_POST['jam_rawat'] ?? date('H:i:s');
        if (strlen($jamP)===5) $jamP.=':00';
        $mode = $_POST['mode'] ?? ($hasData ? 'update' : 'insert');

        $fields = [
            'tinggi_uteri' => trim($_POST['tinggi_uteri'] ?? ''),
            'janin'        => $_POST['janin'] ?? '-',
            'letak'        => trim($_POST['letak'] ?? ''),
            'panggul'      => $_POST['panggul'] ?? '-',
            'denyut'       => trim($_POST['denyut'] ?? ''),
            'kontraksi'    => $_POST['kontraksi'] ?? '-',
            'kualitas_mnt' => trim($_POST['kualitas_mnt'] ?? ''),
            'kualitas_dtk' => trim($_POST['kualitas_dtk'] ?? ''),
            'fluksus'      => $_POST['fluksus'] ?? '-',
            'albus'        => $_POST['albus'] ?? '-',
            'vulva'        => trim($_POST['vulva'] ?? ''),
            'portio'       => trim($_POST['portio'] ?? ''),
            'dalam'        => $_POST['dalam'] ?? 'Kenyal',
            'tebal'        => trim($_POST['tebal'] ?? ''),
            'arah'         => $_POST['arah'] ?? 'depan',
            'pembukaan'    => trim($_POST['pembukaan'] ?? ''),
            'penurunan'    => trim($_POST['penurunan'] ?? ''),
            'denominator'  => trim($_POST['denominator'] ?? ''),
            'ketuban'      => $_POST['ketuban'] ?? '-',
            'feto'         => $_POST['feto'] ?? 'Normal',
        ];

        try {
            if ($mode === 'update') {
                // Update rekaman yang sedang di-edit (berdasarkan tgl/jam lama)
                $oldT = $_POST['old_tgl'] ?? $prefill['tgl_perawatan'] ?? $tglP;
                $oldJ = $_POST['old_jam'] ?? $prefill['jam_rawat'] ?? $jamP;
                $set = implode(', ', array_map(fn($k) => "`$k`=?", array_keys($fields)));
                $s = $pdo->prepare("UPDATE pemeriksaan_obstetri_ralan SET tgl_perawatan=?, jam_rawat=?, $set WHERE no_rawat=? AND tgl_perawatan=? AND jam_rawat=?");
                $s->execute([$tglP, $jamP, ...array_values($fields), $noRawat, $oldT, $oldJ]);
            } else {
                // INSERT baru — cek duplikat tgl+jam
                $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM pemeriksaan_obstetri_ralan WHERE no_rawat=? AND tgl_perawatan=? AND jam_rawat=?");
                $stmtCek->execute([$noRawat,$tglP,$jamP]);
                if ((int)$stmtCek->fetchColumn() > 0) {
                    $error = "Sudah ada data obstetri untuk waktu {$tglP} {$jamP}.";
                } else {
                    $cols = 'no_rawat, tgl_perawatan, jam_rawat, ' . implode(', ', array_map(fn($k)=>"`$k`", array_keys($fields)));
                    $ph = '?,?,?,' . implode(',', array_fill(0, count($fields), '?'));
                    $s = $pdo->prepare("INSERT INTO pemeriksaan_obstetri_ralan ($cols) VALUES ($ph)");
                    $s->execute([$noRawat,$tglP,$jamP,...array_values($fields)]);
                }
            }
            if ($error === '') {
                header('Location: obstetri-detail.php?no_rawat=' . urlencode($noRawat) . '&status=success');
                exit;
            }
        } catch (Throwable $e) {
            error_log('[obstetri-detail.php] ' . $e->getMessage());
            $error = 'Gagal menyimpan: ' . $e->getMessage();
        }
    }
}

$halamanAktif = 'asesmen';
$judulHalaman = 'Pemeriksaan Obstetri Detail';
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
.obs-tbl{width:100%;border-collapse:collapse;font-size:12.5px;}
.obs-tbl th{background:var(--color-primary);color:#fff;padding:7px 10px;text-align:left;}
.obs-tbl td{border-bottom:1px solid var(--color-border);padding:6px 10px;}
.obs-tbl tr:hover td{background:#FDF6F8;}
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
        <a href="cetak_asesmen.php?type=obstetri&no_rawat=<?= urlencode($noRawat) ?>" target="_blank"
           class="btn btn-outline" style="font-size:12.5px; padding:6px 14px; display:inline-flex; align-items:center; gap:6px; text-decoration:none;">
            🖨️ Cetak Semua Obstetri
        </a>
    </div>
</div>

<?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?><div class="alert alert-success no-print" id="alert-simpan-sukses">✔ Data obstetri berhasil disimpan.</div><?php endif; ?>
<?php if ($error):  ?><div class="alert alert-danger no-print"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($daftarObs): ?>
<div class="card card-riwayat-container">
    <p class="card-title">Riwayat Pemeriksaan Obstetri</p>
    <div style="overflow-x:auto;">
    <table class="obs-tbl">
        <thead><tr>
            <th>Tanggal</th><th>Jam</th><th>TFU</th><th>Janin</th><th>Letak</th>
            <th>Panggul</th><th>Denyut</th><th>Kontraksi</th><th>Pembukaan</th><th>Ketuban</th><th>Feto</th><th style="width:130px;" class="col-aksi">Aksi</th>
        </tr></thead>
        <tbody>
        <?php foreach ($daftarObs as $o): ?>
        <tr>
            <td><?= htmlspecialchars(date('d-m-Y',strtotime($o['tgl_perawatan']))) ?></td>
            <td><?= htmlspecialchars($o['jam_rawat']) ?></td>
            <td><?= htmlspecialchars($o['tinggi_uteri']??'') ?> cm</td>
            <td><?= htmlspecialchars($o['janin']??'') ?></td>
            <td><?= htmlspecialchars($o['letak']??'') ?></td>
            <td><?= htmlspecialchars($o['panggul']??'') ?></td>
            <td><?= htmlspecialchars($o['denyut']??'') ?></td>
            <td><?= htmlspecialchars($o['kontraksi']??'') ?></td>
            <td><?= htmlspecialchars($o['pembukaan']??'') ?></td>
            <td><?= htmlspecialchars($o['ketuban']??'') ?></td>
            <td><?= htmlspecialchars($o['feto']??'') ?></td>
            <td class="col-aksi">
                <?php if ($sudahBayar): ?>
                    <span class="text-muted" style="font-size:12.5px;">🔒 Terkunci</span>
                <?php else: ?>
                    <a href="?no_rawat=<?= urlencode($noRawat) ?>&edit=<?= urlencode($o['tgl_perawatan'].'|'.$o['jam_rawat']) ?>"
                       class="btn btn-outline" style="font-size:12px;padding:3px 8px;">Edit</a>
                <?php endif; ?>
                <a href="cetak_asesmen.php?type=obstetri&no_rawat=<?= urlencode($noRawat) ?>&tgl=<?= urlencode($o['tgl_perawatan']) ?>&jam=<?= urlencode($o['jam_rawat']) ?>" target="_blank"
                    class="btn btn-outline btn-print-act"
                    style="font-size:11.5px; padding:3px 8px; margin-left:4px; border-color:var(--color-primary); color:var(--color-primary); text-decoration:none;" title="Cetak Obstetri">🖨️ Cetak</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <p class="card-title"><?= $editKey ? 'Edit Data Obstetri' : ($hasData ? 'Edit Data Obstetri (Data Tersedia)' : 'Pemeriksaan Obstetri Baru') ?></p>
    <?php if ($hasData && !$editKey): ?>
        <div style="background:#EBF5FF; border:1px solid #B3D4F0; border-radius:6px; padding:9px 12px; margin-bottom:12px; font-size:12.5px;">
            ✔ <strong>Data obstetri ditemukan</strong> untuk kunjungan ini. Form diisi otomatis. Submit untuk menyimpan perubahan.
        </div>
    <?php endif; ?>
    <?php if ($sudahBayar): ?>
        <div class="alert alert-danger" style="margin-bottom:15px; font-size:12.5px;">
            🔒 <strong>Data dikunci:</strong> Pembayaran telah diselesaikan. Anda tidak dapat menambah atau mengubah data obstetri ini.
        </div>
    <?php endif; ?>

    <form method="post">
        <fieldset <?= $sudahBayar ? 'disabled' : '' ?> style="border:0; padding:0; margin:0;">
        <input type="hidden" name="no_rawat" value="<?= htmlspecialchars($noRawat) ?>">
        <input type="hidden" name="mode" value="<?= ($editKey || $hasData) ? 'update' : 'insert' ?>">
        <?php if ($editKey): [$oT,$oJ]=explode('|',urldecode($editKey),2); ?>
            <input type="hidden" name="old_tgl" value="<?= htmlspecialchars($oT) ?>">
            <input type="hidden" name="old_jam" value="<?= htmlspecialchars($oJ) ?>">
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
        </div>

        <p class="sec">Pemeriksaan Luar (Palpasi & DJJ)</p>
        <div class="fm4">
            <div>
                <label for="tinggi_uteri">Tinggi Fundus Uteri (cm)</label>
                <input type="text" id="tinggi_uteri" name="tinggi_uteri" placeholder="28"
                       value="<?= ov($prefill,'tinggi_uteri') ?>">
            </div>
            <div>
                <label for="janin">Janin</label>
                <select id="janin" name="janin">
                    <?= oSel(['Tunggal','Gemelli','-'], ov($prefill,'janin'), '-') ?>
                </select>
            </div>
            <div>
                <label for="letak">Letak Janin</label>
                <input type="text" id="letak" name="letak" placeholder="Membujur / Preskep"
                       value="<?= ov($prefill,'letak') ?>">
            </div>
            <div>
                <label for="panggul">Panggul</label>
                <select id="panggul" name="panggul">
                    <?= oSel(['-','5/5','4/5','3/5','2/5','1/5'], ov($prefill,'panggul'), '-') ?>
                </select>
            </div>
        </div>

        <div class="fm4" style="margin-top:10px;">
            <div>
                <label for="denyut">Denyut (DJJ)</label>
                <input type="text" id="denyut" name="denyut" placeholder="140"
                       value="<?= ov($prefill,'denyut') ?>">
            </div>
            <div>
                <label for="kontraksi">Kontraksi</label>
                <select id="kontraksi" name="kontraksi">
                    <?= oSel(['+','-'], ov($prefill,'kontraksi'), '+') ?>
                </select>
            </div>
            <div>
                <label for="kualitas_mnt">His (x / 10 mnt)</label>
                <input type="text" id="kualitas_mnt" name="kualitas_mnt" placeholder="3"
                       value="<?= ov($prefill,'kualitas_mnt') ?>">
            </div>
            <div>
                <label for="kualitas_dtk">Lama His (detik)</label>
                <input type="text" id="kualitas_dtk" name="kualitas_dtk" placeholder="40"
                       value="<?= ov($prefill,'kualitas_dtk') ?>">
            </div>
        </div>

        <p class="sec">Pemeriksaan Genitalia & VT</p>
        <div class="fm4">
            <div>
                <label for="fluksus">Fluksus</label>
                <select id="fluksus" name="fluksus">
                    <?= oSel(['+','-'], ov($prefill,'fluksus'), '-') ?>
                </select>
            </div>
            <div>
                <label for="albus">Fluor Albus</label>
                <select id="albus" name="albus">
                    <?= oSel(['+','-'], ov($prefill,'albus'), '-') ?>
                </select>
            </div>
            <div>
                <label for="vulva">Vulva</label>
                <input type="text" id="vulva" name="vulva" placeholder="Tak ada kelainan"
                       value="<?= ov($prefill,'vulva') ?>">
            </div>
            <div>
                <label for="portio">Portio</label>
                <input type="text" id="portio" name="portio" placeholder="Lunak / Tebal"
                       value="<?= ov($prefill,'portio') ?>">
            </div>
        </div>

        <div class="fm4" style="margin-top:10px;">
            <div>
                <label for="dalam">Konsistensi Dalam</label>
                <select id="dalam" name="dalam">
                    <?= oSel(['Kenyal','Lunak'], ov($prefill,'dalam'), 'Kenyal') ?>
                </select>
            </div>
            <div>
                <label for="tebal">Tebal Portio</label>
                <input type="text" id="tebal" name="tebal" placeholder="1 cm"
                       value="<?= ov($prefill,'tebal') ?>">
            </div>
            <div>
                <label for="arah">Arah Portio</label>
                <select id="arah" name="arah">
                    <?= oSel(['depan','axial','belakang'], ov($prefill,'arah'), 'depan') ?>
                </select>
            </div>
            <div>
                <label for="pembukaan">Pembukaan Serviks</label>
                <input type="text" id="pembukaan" name="pembukaan" placeholder="4 cm"
                       value="<?= ov($prefill,'pembukaan') ?>">
            </div>
        </div>

        <div class="fm4" style="margin-top:10px;">
            <div>
                <label for="penurunan">Penurunan Bagian Bawah</label>
                <input type="text" id="penurunan" name="penurunan" placeholder="Hodge II"
                       value="<?= ov($prefill,'penurunan') ?>">
            </div>
            <div>
                <label for="denominator">Denominator</label>
                <input type="text" id="denominator" name="denominator" placeholder="UUK Kiri Depan"
                       value="<?= ov($prefill,'denominator') ?>">
            </div>
            <div>
                <label for="ketuban">Ketuban</label>
                <select id="ketuban" name="ketuban">
                    <?= oSel(['+','-'], ov($prefill,'ketuban'), '+') ?>
                </select>
            </div>
            <div>
                <label for="feto">Feto-Pelvik (CPD)</label>
                <select id="feto" name="feto">
                    <?= oSel(['Normal','Susp.CPD-FPD','CPD-FPD'], ov($prefill,'feto'), 'Normal') ?>
                </select>
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:20px;">
            <button type="submit" class="btn btn-primary"><?= ($editKey || $hasData) ? 'Simpan Perubahan' : 'Simpan Data Obstetri' ?></button>
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
    const baseUrl = 'obstetri-detail.php?no_rawat=' + encodeURIComponent(noRawat);
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil Disimpan!',
            text: 'Data obstetri berhasil disimpan.',
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
