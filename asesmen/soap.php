<?php
/**
 * asesmen/soap.php
 * -----------------------------------------------------------------
 * Form SOAP & Vital Sign → INSERT/UPDATE ke `pemeriksaan_ralan`.
 * PK komposit: (no_rawat, tgl_perawatan, jam_rawat)
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

// Ambil data kunjungan + pasien
$stmt = $pdo->prepare(
    "SELECT r.no_rawat, r.tgl_registrasi, r.kd_dokter,
            p.nm_pasien, p.no_rkm_medis, p.jk, p.tgl_lahir,
            dok.nm_dokter
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

// Ambil daftar SOAP yang sudah ada untuk kunjungan ini
$stmtList = $pdo->prepare(
    "SELECT tgl_perawatan, jam_rawat, suhu_tubuh, tensi, nadi, respirasi, tinggi, berat,
            spo2, gcs, kesadaran, keluhan, pemeriksaan, alergi, lingkar_perut, rtl, penilaian, instruksi, evaluasi, nip
     FROM pemeriksaan_ralan
     WHERE no_rawat = ?
     ORDER BY tgl_perawatan DESC, jam_rawat DESC"
);
$stmtList->execute([$noRawat]);
$daftarSoap = $stmtList->fetchAll();

// Ambil daftar pegawai untuk dropdown pencatat SOAP
$daftarPegawai = $pdo->query(
    "SELECT nik, nama, jbtn FROM pegawai WHERE nik != '-' ORDER BY nama ASC"
)->fetchAll();

$error  = '';
$sukses = false;
$editKey = null; // 'tgl|jam' jika mode edit
$prefill = [];

if (isset($_GET['edit'])) {
    [$eTgl, $eJam] = explode('|', $_GET['edit'], 2);
    $stmtEdit = $pdo->prepare(
        "SELECT * FROM pemeriksaan_ralan WHERE no_rawat = ? AND tgl_perawatan = ? AND jam_rawat = ?"
    );
    $stmtEdit->execute([$noRawat, $eTgl, $eJam]);
    $prefill = $stmtEdit->fetch() ?: [];
    $editKey = htmlspecialchars($_GET['edit']);
}

$sudahBayar = isSudahBayar($noRawat, $pdo);

// --- POST: INSERT atau UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($sudahBayar) {
        $error = 'Peringatan: Data tidak dapat disimpan karena pasien sudah melakukan pembayaran (LUNAS).';
    } else {
        $mode         = $_POST['mode'] ?? 'insert';
        $tglPerawatan = $_POST['tgl_perawatan'] ?? date('Y-m-d');
        $jamRawat     = $_POST['jam_rawat'] ?? date('H:i:s');

        if (strlen($jamRawat) === 5) $jamRawat .= ':00';

    $keluhan      = trim($_POST['keluhan'] ?? '');
    $pemeriksaan  = trim($_POST['pemeriksaan'] ?? '');
    $tensi        = trim($_POST['tensi'] ?? '');
    $nadi         = trim($_POST['nadi'] ?? '');
    $suhu_tubuh   = trim($_POST['suhu_tubuh'] ?? '');
    $respirasi    = trim($_POST['respirasi'] ?? '');
    $spo2         = trim($_POST['spo2'] ?? '');
    $gcs          = trim($_POST['gcs'] ?? '');
    $kesadaran    = trim($_POST['kesadaran'] ?? 'Composmentis');
    $berat        = trim($_POST['berat'] ?? '');
    $tinggi       = trim($_POST['tinggi'] ?? '');
    $alergi       = trim($_POST['alergi'] ?? '');
    $lingkar_perut= trim($_POST['lingkar_perut'] ?? '');
    $rtl          = trim($_POST['rtl'] ?? '');
    $penilaian    = trim($_POST['penilaian'] ?? '');
    $instruksi    = trim($_POST['instruksi'] ?? '');
    $evaluasi     = trim($_POST['evaluasi'] ?? '');

    // Resolusi NIP pencatat — prioritas: pilihan user di form → session → kd_dokter → '-'
    $nipPost = trim($_POST['nip'] ?? '');
    if ($nipPost !== '') {
        // Validasi NIP dari POST terhadap tabel pegawai
        $stmtNipChk = $pdo->prepare("SELECT nik FROM pegawai WHERE nik = ? LIMIT 1");
        $stmtNipChk->execute([$nipPost]);
        $nip = $stmtNipChk->fetchColumn() ? $nipPost : '-';
    } else {
        // Fallback dari session / kd_dokter
        $nipCandidates = array_filter([
            $_SESSION['nip']        ?? null,
            $_SESSION['id_user']    ?? null,
            $kunjungan['kd_dokter'] ?? null,
        ]);
        $nip = '-';
        if (!empty($nipCandidates)) {
            $stmtNip = $pdo->prepare("SELECT nik FROM pegawai WHERE nik = ? LIMIT 1");
            foreach ($nipCandidates as $cand) {
                $cand = trim((string)$cand);
                if ($cand === '') continue;
                $stmtNip->execute([$cand]);
                if ($stmtNip->fetchColumn()) { $nip = $cand; break; }
            }
        }
    }

    if ($keluhan === '') {
        $error = 'Keluhan tidak boleh kosong.';
    } else {
        try {
            if ($mode === 'update') {
                $oldTgl = $_POST['old_tgl'] ?? $tglPerawatan;
                $oldJam = $_POST['old_jam'] ?? $jamRawat;
                $stmt = $pdo->prepare(
                    "UPDATE pemeriksaan_ralan SET
                        suhu_tubuh=?, tensi=?, nadi=?, respirasi=?, tinggi=?, berat=?,
                        spo2=?, gcs=?, kesadaran=?, keluhan=?, pemeriksaan=?, alergi=?,
                        lingkar_perut=?, rtl=?, penilaian=?, instruksi=?, evaluasi=?, nip=?
                     WHERE no_rawat=? AND tgl_perawatan=? AND jam_rawat=?"
                );
                $stmt->execute([
                    $suhu_tubuh, $tensi, $nadi, $respirasi, $tinggi, $berat,
                    $spo2, $gcs, $kesadaran, $keluhan, $pemeriksaan, $alergi,
                    $lingkar_perut, $rtl, $penilaian, $instruksi, $evaluasi, $nip,
                    $noRawat, $oldTgl, $oldJam
                ]);
            } else {
                // Cek duplikat PK
                $stmtCek = $pdo->prepare(
                    "SELECT COUNT(*) FROM pemeriksaan_ralan WHERE no_rawat=? AND tgl_perawatan=? AND jam_rawat=?"
                );
                $stmtCek->execute([$noRawat, $tglPerawatan, $jamRawat]);
                if ((int)$stmtCek->fetchColumn() > 0) {
                    $error = "Sudah ada catatan SOAP untuk kunjungan ini pada {$tglPerawatan} jam {$jamRawat}. Silakan pilih waktu lain atau gunakan tombol Edit.";
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO pemeriksaan_ralan (
                            no_rawat, tgl_perawatan, jam_rawat,
                            suhu_tubuh, tensi, nadi, respirasi, tinggi, berat,
                            spo2, gcs, kesadaran, keluhan, pemeriksaan, alergi,
                            lingkar_perut, rtl, penilaian, instruksi, evaluasi, nip
                         ) VALUES (
                            ?,?,?,
                            ?,?,?,?,?,?,
                            ?,?,?,?,?,?,
                            ?,?,?,?,?,?
                         )"
                    );
                    $stmt->execute([
                        $noRawat, $tglPerawatan, $jamRawat,
                        $suhu_tubuh, $tensi, $nadi, $respirasi, $tinggi, $berat,
                        $spo2, $gcs, $kesadaran, $keluhan, $pemeriksaan, $alergi,
                        $lingkar_perut, $rtl, $penilaian, $instruksi, $evaluasi, $nip
                    ]);
                }
            }
            if ($error === '') {
                header('Location: soap.php?no_rawat=' . urlencode($noRawat) . '&status=success');
                exit;
            }
        } catch (Throwable $e) {
            error_log('[soap.php] ' . $e->getMessage());
            $error = 'Gagal menyimpan: ' . $e->getMessage();
        }
    }
}
}

$halamanAktif = 'asesmen';
$judulHalaman = 'SOAP & Vital Sign';
$baseAsset    = '../';
require __DIR__ . '/../lib/layout_header.php';
?>
<style>
.soap-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.soap-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
.soap-grid-4 { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:12px; }
.ttv-box {
    background: #FDF6F8;
    border: 1.5px solid var(--color-border);
    border-radius: 8px;
    padding: 14px 16px;
    margin-bottom: 12px;
}
.ttv-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--color-text-mute); margin-bottom:4px; }
.soap-history { border-collapse:collapse; width:100%; font-size:13px; }
.soap-history th { background:var(--color-primary); color:#fff; padding:8px 10px; text-align:left; }
.soap-history td { border-bottom:1px solid var(--color-border); padding:7px 10px; vertical-align:top; }
.soap-history tr:hover td { background:#FDF6F8; }
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
        <a href="cetak_asesmen.php?type=soap&no_rawat=<?= urlencode($noRawat) ?>" target="_blank"
           class="btn btn-outline" style="font-size:12.5px; padding:6px 14px; display:inline-flex; align-items:center; gap:6px; text-decoration:none;">
            🖨️ Cetak Semua SOAP
        </a>
    </div>
</div>

<?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
    <div class="alert alert-success no-print" id="alert-simpan-sukses">✔ Data SOAP berhasil disimpan.</div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger no-print"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Riwayat SOAP existing -->
<?php if ($daftarSoap): ?>
<div class="card card-riwayat-container">
    <p class="card-title">Riwayat SOAP Kunjungan Ini</p>
    <div style="overflow-x:auto;">
    <table class="soap-history">
        <thead>
            <tr>
                <th>Tanggal</th><th>Jam</th><th>Keluhan</th>
                <th>Tensi</th><th>Nadi</th><th>Suhu</th><th>Resp</th><th>BB/TB</th>
                <th>Pemeriksaan</th><th>Asesmen</th><th>Instruksi/RTL</th>
                <th style="width:130px;" class="col-aksi">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($daftarSoap as $s): ?>
            <tr>
                <td><?= htmlspecialchars(date('d-m-Y', strtotime($s['tgl_perawatan']))) ?></td>
                <td><?= htmlspecialchars($s['jam_rawat']) ?></td>
                <td><?= nl2br(htmlspecialchars(mb_substr($s['keluhan'],0,60) . (mb_strlen($s['keluhan'])>60?'…':''))) ?></td>
                <td><?= htmlspecialchars($s['tensi']) ?></td>
                <td><?= htmlspecialchars($s['nadi']) ?></td>
                <td><?= htmlspecialchars($s['suhu_tubuh']) ?></td>
                <td><?= htmlspecialchars($s['respirasi']) ?></td>
                <td><?= htmlspecialchars($s['berat']) ?>kg / <?= htmlspecialchars($s['tinggi']) ?>cm</td>
                <td><?= nl2br(htmlspecialchars(mb_substr($s['pemeriksaan']??'',0,50).(mb_strlen($s['pemeriksaan']??'')>50?'…':''))) ?></td>
                <td><?= nl2br(htmlspecialchars(mb_substr($s['penilaian']??'',0,50).(mb_strlen($s['penilaian']??'')>50?'…':''))) ?></td>
                <td><?= nl2br(htmlspecialchars(mb_substr(($s['rtl'] !== '' ? $s['rtl'] : $s['instruksi']??''),0,50))) ?></td>
                <td class="col-aksi">
                    <?php if ($sudahBayar): ?>
                        <span class="text-muted" style="font-size:12.5px;">🔒 Terkunci</span>
                    <?php else: ?>
                        <a href="?no_rawat=<?= urlencode($noRawat) ?>&edit=<?= urlencode($s['tgl_perawatan'].'|'.$s['jam_rawat']) ?>"
                           class="btn btn-outline" style="font-size:12px;padding:3px 8px;">Edit</a>
                    <?php endif; ?>
                    <a href="cetak_asesmen.php?type=soap&no_rawat=<?= urlencode($noRawat) ?>&tgl=<?= urlencode($s['tgl_perawatan']) ?>&jam=<?= urlencode($s['jam_rawat']) ?>" target="_blank"
                        class="btn btn-outline btn-print-act"
                        style="font-size:11.5px; padding:3px 8px; margin-left:4px; border-color:var(--color-primary); color:var(--color-primary); text-decoration:none;" title="Cetak SOAP">🖨️ Cetak</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<!-- Form SOAP -->
<div class="card">
    <p class="card-title"><?= $editKey ? 'Edit Catatan SOAP' : 'Tambah Catatan SOAP Baru' ?></p>
    
    <?php if ($sudahBayar): ?>
        <div class="alert alert-danger" style="margin-bottom:15px; font-size:12.5px;">
            🔒 <strong>Data dikunci:</strong> Pembayaran telah diselesaikan. Anda tidak dapat menambah atau mengubah catatan SOAP.
        </div>
    <?php endif; ?>

    <form method="post" id="formSoap">
        <fieldset <?= $sudahBayar ? 'disabled' : '' ?> style="border:0; padding:0; margin:0;">
            <input type="hidden" name="no_rawat" value="<?= htmlspecialchars($noRawat) ?>">
        <input type="hidden" name="mode" value="<?= $editKey ? 'update' : 'insert' ?>">
        <?php if ($editKey): ?>
            <?php [$oldTgl2, $oldJam2] = explode('|', urldecode($editKey), 2); ?>
            <input type="hidden" name="old_tgl" value="<?= htmlspecialchars($oldTgl2) ?>">
            <input type="hidden" name="old_jam" value="<?= htmlspecialchars($oldJam2) ?>">
        <?php endif; ?>

        <!-- Waktu Pemeriksaan -->
        <div class="soap-grid-2" style="max-width:400px;margin-bottom:14px;">
            <div>
                <label for="tgl_perawatan">Tanggal Pemeriksaan *</label>
                <input type="date" id="tgl_perawatan" name="tgl_perawatan"
                       value="<?= htmlspecialchars($prefill['tgl_perawatan'] ?? date('Y-m-d')) ?>"
                       <?= $editKey ? 'readonly style="background:#f5f5f5;"' : '' ?>>
            </div>
            <div>
                <label for="jam_rawat">Jam Pemeriksaan *</label>
                <input type="time" id="jam_rawat" name="jam_rawat"
                       value="<?= htmlspecialchars(substr($prefill['jam_rawat'] ?? date('H:i'), 0, 5)) ?>"
                       <?= $editKey ? 'readonly style="background:#f5f5f5;"' : '' ?>>
            </div>
        </div>

        <!-- TTV -->
        <p style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--color-primary);margin:16px 0 8px;">Tanda Vital (TTV)</p>
        <div class="ttv-box">
            <div class="soap-grid-4">
                <div>
                    <div class="ttv-label">Tekanan Darah (Tensi)</div>
                    <input type="text" id="tensi" name="tensi" placeholder="120/80"
                           value="<?= htmlspecialchars($prefill['tensi'] ?? '') ?>">
                    <small class="text-muted">mmHg</small>
                </div>
                <div>
                    <div class="ttv-label">Nadi</div>
                    <input type="text" id="nadi" name="nadi" placeholder="80"
                           value="<?= htmlspecialchars($prefill['nadi'] ?? '') ?>">
                    <small class="text-muted">x/menit</small>
                </div>
                <div>
                    <div class="ttv-label">Suhu Tubuh</div>
                    <input type="text" id="suhu_tubuh" name="suhu_tubuh" placeholder="36.5"
                           value="<?= htmlspecialchars($prefill['suhu_tubuh'] ?? '') ?>">
                    <small class="text-muted">°C</small>
                </div>
                <div>
                    <div class="ttv-label">Respirasi</div>
                    <input type="text" id="respirasi" name="respirasi" placeholder="20"
                           value="<?= htmlspecialchars($prefill['respirasi'] ?? '') ?>">
                    <small class="text-muted">x/menit</small>
                </div>
            </div>
            <div class="soap-grid-4" style="margin-top:10px;">
                <div>
                    <div class="ttv-label">SpO₂</div>
                    <input type="text" id="spo2" name="spo2" placeholder="98"
                           value="<?= htmlspecialchars($prefill['spo2'] ?? '') ?>">
                    <small class="text-muted">%</small>
                </div>
                <div>
                    <div class="ttv-label">GCS (E, V, M)</div>
                    <input type="text" id="gcs" name="gcs" placeholder="15"
                           value="<?= htmlspecialchars($prefill['gcs'] ?? '') ?>">
                </div>
                <div>
                    <div class="ttv-label">Berat Badan</div>
                    <input type="text" id="berat" name="berat" placeholder="55"
                           value="<?= htmlspecialchars($prefill['berat'] ?? '') ?>">
                    <small class="text-muted">kg</small>
                </div>
                <div>
                    <div class="ttv-label">Tinggi Badan</div>
                    <input type="text" id="tinggi" name="tinggi" placeholder="160"
                           value="<?= htmlspecialchars($prefill['tinggi'] ?? '') ?>">
                    <small class="text-muted">cm</small>
                </div>
            </div>
            <div class="soap-grid-3" style="margin-top:10px;">
                <div>
                    <div class="ttv-label">Kesadaran</div>
                    <select id="kesadaran" name="kesadaran">
                        <?php foreach (['Composmentis','Apatis','Somnolen','Sopor','Koma','Delirium'] as $k): ?>
                            <option value="<?= $k ?>" <?= ($prefill['kesadaran'] ?? 'Composmentis') === $k ? 'selected' : '' ?>><?= $k ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <div class="ttv-label">Alergi</div>
                    <input type="text" id="alergi" name="alergi" placeholder="Tidak ada / Sebutkan"
                           value="<?= htmlspecialchars($prefill['alergi'] ?? '') ?>">
                </div>
                <div>
                    <div class="ttv-label">Lingkar Perut</div>
                    <input type="text" id="lingkar_perut" name="lingkar_perut" placeholder="80"
                           value="<?= htmlspecialchars($prefill['lingkar_perut'] ?? '') ?>">
                    <small class="text-muted">cm</small>
                </div>
            </div>
        </div>

        <!-- SOAP -->
        <p style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--color-primary);margin:16px 0 8px;">Catatan SOAP</p>
        
        <label for="keluhan">S — Subjektif (Keluhan Utama) *</label>
        <textarea id="keluhan" name="keluhan" rows="3" required><?= htmlspecialchars($prefill['keluhan'] ?? '') ?></textarea>

        <label for="pemeriksaan">O — Objektif (Pemeriksaan Fisik)</label>
        <textarea id="pemeriksaan" name="pemeriksaan" rows="3"><?= htmlspecialchars($prefill['pemeriksaan'] ?? '') ?></textarea>

        <label for="penilaian">A — Asesmen / Diagnosis Kerja</label>
        <textarea id="penilaian" name="penilaian" rows="2"><?= htmlspecialchars($prefill['penilaian'] ?? '') ?></textarea>

        <div class="soap-grid-2">
            <div>
                <label for="rtl">P — Plan (Rencana Tindak Lanjut)</label>
                <textarea id="rtl" name="rtl" rows="3"><?= htmlspecialchars($prefill['rtl'] ?? '') ?></textarea>
            </div>
            <div>
                <label for="instruksi">Instruksi Medis / Sediaan</label>
                <textarea id="instruksi" name="instruksi" rows="3"><?= htmlspecialchars($prefill['instruksi'] ?? '') ?></textarea>
            </div>
        </div>

        <label for="evaluasi" style="margin-top: 10px;">Evaluasi</label>
        <textarea id="evaluasi" name="evaluasi" rows="2"><?= htmlspecialchars($prefill['evaluasi'] ?? '') ?></textarea>

        <?php
        // Tentukan NIP default untuk dropdown: dari prefill (mode edit) atau dari session/kd_dokter
        $nipDefault = $prefill['nip'] ?? '';
        if ($nipDefault === '') {
            $nipDefault = '-';
            $nipSessCands = array_filter([$_SESSION['nip'] ?? null, $_SESSION['id_user'] ?? null, $kunjungan['kd_dokter'] ?? null]);
            if (!empty($nipSessCands)) {
                $stNip2 = $pdo->prepare("SELECT nik FROM pegawai WHERE nik = ? LIMIT 1");
                foreach ($nipSessCands as $cNip) {
                    $cNip = trim((string)$cNip);
                    if ($cNip === '') continue;
                    $stNip2->execute([$cNip]);
                    if ($stNip2->fetchColumn()) { $nipDefault = $cNip; break; }
                }
            }
        }
        ?>
        <div style="margin-top:14px;">
            <label for="nip_pencatat">Dokter / Petugas Pencatat</label>
            <select id="nip_pencatat" name="nip">
                <option value="-" <?= $nipDefault === '-' ? 'selected' : '' ?>>— Tidak Ditentukan —</option>
                <?php foreach ($daftarPegawai as $pg): ?>
                <option value="<?= htmlspecialchars($pg['nik']) ?>"
                    <?= $nipDefault === $pg['nik'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($pg['nama']) ?><?= $pg['jbtn'] ? ' (' . htmlspecialchars($pg['jbtn']) . ')' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display:flex;gap:10px;margin-top:20px;">
            <button type="submit" class="btn btn-primary">
                <?= $editKey ? 'Simpan Perubahan' : 'Simpan SOAP' ?>
            </button>
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
    const baseUrl = 'soap.php?no_rawat=' + encodeURIComponent(noRawat);
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil Disimpan!',
            text: 'Data SOAP berhasil disimpan.',
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
