<?php
/**
 * asesmen/kebidanan-keperawatan.php
 * -----------------------------------------------------------------
 * Form Asesmen Awal Keperawatan Kebidanan (bidan/perawat) →
 * tabel: penilaian_awal_keperawatan_kebidanan
 * PK: no_rawat (satu rawat jalan satu asesmen awal keperawatan)
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
    "SELECT r.no_rawat, r.tgl_registrasi,
            p.nm_pasien, p.no_rkm_medis, p.jk, p.tgl_lahir
     FROM reg_periksa r
     JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
     WHERE r.no_rawat = ?"
);
$stmt->execute([$noRawat]);
$kunjungan = $stmt->fetch();
if (!$kunjungan) {
    header('Location: index.php');
    exit;
}

// Ambil data existing
$stmtGet = $pdo->prepare("SELECT * FROM penilaian_awal_keperawatan_kebidanan WHERE no_rawat = ?");
$stmtGet->execute([$noRawat]);
$prefill = $stmtGet->fetch() ?: [];
$hasData = !empty($prefill);

// Ambil daftar petugas (bidan/perawat) untuk dropdown pilihan evaluator
$stmtPetugas = $pdo->query("SELECT nip, nama FROM petugas WHERE status = '1' ORDER BY nama ASC");
$daftarPetugas = $stmtPetugas->fetchAll();

// Ambil riwayat asesmen keperawatan kebidanan pasien ini
$stmtRiwayat = $pdo->prepare(
    "SELECT k.no_rawat, k.tanggal, k.keluhan_utama, k.td, k.nadi, k.suhu, k.rr, p.nama as nm_petugas
     FROM penilaian_awal_keperawatan_kebidanan k
     INNER JOIN reg_periksa r ON k.no_rawat = r.no_rawat
     LEFT JOIN petugas p ON k.nip = p.nip
     WHERE r.no_rkm_medis = ?
     ORDER BY k.tanggal DESC"
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
        
        $validNips = array_column($daftarPetugas, 'nip');
        $nipInput  = trim($_POST['nip'] ?? $_SESSION['nip'] ?? '');
        if (in_array($nipInput, $validNips)) {
            $nip = $nipInput;
        } else if (!empty($daftarPetugas)) {
            $nip = $daftarPetugas[0]['nip'];
        } else {
            $nip = null;
        }

    // 118 columns mapping
    $data_fields = [
        'tanggal'              => $tanggal,
        'nip'                  => $nip,
        'informasi'            => $_POST['informasi'] ?? 'Keluarga',
        'td'                   => trim($_POST['td'] ?? ''),
        'nadi'                 => trim($_POST['nadi'] ?? ''),
        'rr'                   => trim($_POST['rr'] ?? ''),
        'suhu'                 => trim($_POST['suhu'] ?? ''),
        'gcs'                  => trim($_POST['gcs'] ?? ''),
        'bb'                   => trim($_POST['bb'] ?? ''),
        'tb'                   => trim($_POST['tb'] ?? ''),
        'lila'                 => trim($_POST['lila'] ?? ''),
        'bmi'                  => trim($_POST['bmi'] ?? ''),
        'tfu'                  => trim($_POST['tfu'] ?? ''),
        'tbj'                  => trim($_POST['tbj'] ?? ''),
        'letak'                => trim($_POST['letak'] ?? ''),
        'presentasi'           => trim($_POST['presentasi'] ?? ''),
        'penurunan'            => trim($_POST['penurunan'] ?? ''),
        'his'                  => trim($_POST['his'] ?? ''),
        'kekuatan'             => trim($_POST['kekuatan'] ?? ''),
        'lamanya'              => trim($_POST['lamanya'] ?? ''),
        'bjj'                  => trim($_POST['bjj'] ?? ''),
        'ket_bjj'              => $_POST['ket_bjj'] ?? 'Normal',
        'portio'               => trim($_POST['portio'] ?? ''),
        'serviks'              => trim($_POST['serviks'] ?? ''),
        'ketuban'              => trim($_POST['ketuban'] ?? ''),
        'hodge'                => trim($_POST['hodge'] ?? ''),
        'inspekulo'            => $_POST['inspekulo'] ?? 'Tidak',
        'ket_inspekulo'        => trim($_POST['ket_inspekulo'] ?? ''),
        'ctg'                  => $_POST['ctg'] ?? 'Tidak',
        'ket_ctg'              => trim($_POST['ket_ctg'] ?? ''),
        'usg'                  => $_POST['usg'] ?? 'Tidak',
        'ket_usg'              => trim($_POST['ket_usg'] ?? ''),
        'lab'                  => $_POST['lab'] ?? 'Tidak',
        'ket_lab'              => trim($_POST['ket_lab'] ?? ''),
        'lakmus'               => $_POST['lakmus'] ?? 'Tidak',
        'ket_lakmus'           => trim($_POST['ket_lakmus'] ?? ''),
        'panggul'              => $_POST['panggul'] ?? 'Normal',
        'keluhan_utama'        => trim($_POST['keluhan_utama'] ?? ''),
        'umur'                 => trim($_POST['umur'] ?? ''),
        'lama'                 => trim($_POST['lama'] ?? ''),
        'banyaknya'            => trim($_POST['banyaknya'] ?? ''),
        'haid'                 => trim($_POST['haid'] ?? ''),
        'siklus'               => trim($_POST['siklus'] ?? ''),
        'ket_siklus'           => $_POST['ket_siklus'] ?? 'Teratur',
        'ket_siklus1'          => $_POST['ket_siklus1'] ?? 'Biasa',
        'status'               => $_POST['status'] ?? 'Kawin',
        'kali'                 => trim($_POST['kali'] ?? ''),
        'usia1'                => trim($_POST['usia1'] ?? ''),
        'ket1'                 => $_POST['ket1'] ?? 'Ya',
        'usia2'                => trim($_POST['usia2'] ?? ''),
        'ket2'                 => $_POST['ket2'] ?? 'Tidak',
        'usia3'                => trim($_POST['usia3'] ?? ''),
        'ket3'                 => $_POST['ket3'] ?? 'Tidak',
        'hpht'                 => $_POST['hpht'] ?: date('Y-m-d'),
        'usia_kehamilan'       => trim($_POST['usia_kehamilan'] ?? ''),
        'tp'                   => $_POST['tp'] ?: date('Y-m-d'),
        'imunisasi'            => $_POST['imunisasi'] ?? 'Tidak',
        'ket_imunisasi'        => trim($_POST['ket_imunisasi'] ?? ''),
        'g'                    => trim($_POST['g'] ?? ''),
        'p'                    => trim($_POST['p'] ?? ''),
        'a'                    => trim($_POST['a'] ?? ''),
        'hidup'                => trim($_POST['hidup'] ?? ''),
        'ginekologi'           => $_POST['ginekologi'] ?? 'Tidak',
        'kebiasaan'            => $_POST['kebiasaan'] ?? 'Tidak',
        'ket_kebiasaan'        => trim($_POST['ket_kebiasaan'] ?? ''),
        'kebiasaan1'           => $_POST['kebiasaan1'] ?? 'Tidak',
        'ket_kebiasaan1'       => trim($_POST['ket_kebiasaan1'] ?? ''),
        'kebiasaan2'           => $_POST['kebiasaan2'] ?? 'Tidak',
        'ket_kebiasaan2'       => trim($_POST['ket_kebiasaan2'] ?? ''),
        'kebiasaan3'           => $_POST['kebiasaan3'] ?? 'Tidak',
        'kb'                   => $_POST['kb'] ?? 'Tidak',
        'ket_kb'               => trim($_POST['ket_kb'] ?? ''),
        'komplikasi'           => $_POST['komplikasi'] ?? 'Tidak',
        'ket_komplikasi'       => trim($_POST['ket_komplikasi'] ?? ''),
        'berhenti'             => trim($_POST['berhenti'] ?? ''),
        'alasan'               => trim($_POST['alasan'] ?? ''),
        'alat_bantu'           => $_POST['alat_bantu'] ?? 'Tidak',
        'ket_bantu'            => trim($_POST['ket_bantu'] ?? ''),
        'prothesa'             => $_POST['prothesa'] ?? 'Tidak',
        'ket_pro'              => trim($_POST['ket_pro'] ?? ''),
        'adl'                  => $_POST['adl'] ?? 'Mandiri',
        'status_psiko'         => $_POST['status_psiko'] ?? 'Tenang',
        'ket_psiko'            => trim($_POST['ket_psiko'] ?? ''),
        'hub_keluarga'         => $_POST['hub_keluarga'] ?? 'Harmonis',
        'tinggal_dengan'       => $_POST['tinggal_dengan'] ?? 'Keluarga',
        'ket_tinggal'          => trim($_POST['ket_tinggal'] ?? ''),
        'ekonomi'              => $_POST['ekonomi'] ?? 'Cukup',
        'budaya'               => $_POST['budaya'] ?? 'Tidak Ada',
        'ket_budaya'           => trim($_POST['ket_budaya'] ?? ''),
        'edukasi'              => $_POST['edukasi'] ?? 'Bisa dipahami',
        'ket_edukasi'          => trim($_POST['ket_edukasi'] ?? ''),
        'berjalan_a'           => $_POST['berjalan_a'] ?? 'Tidak',
        'berjalan_b'           => $_POST['berjalan_b'] ?? 'Tidak',
        'berjalan_c'           => $_POST['berjalan_c'] ?? 'Tidak',
        'hasil'                => $_POST['hasil'] ?? 'Tidak Berisiko',
        'lapor'                => $_POST['lapor'] ?? 'Tidak',
        'ket_lapor'            => trim($_POST['ket_lapor'] ?? ''),
        'sg1'                  => $_POST['sg1'] ?? 'Tidak',
        'nilai1'               => $_POST['nilai1'] ?? '0',
        'sg2'                  => $_POST['sg2'] ?? 'Tidak',
        'nilai2'               => $_POST['nilai2'] ?? '0',
        'total_hasil'          => trim($_POST['total_hasil'] ?? '0'),
        'nyeri'                => $_POST['nyeri'] ?? 'Tidak',
        'provokes'             => $_POST['provokes'] ?? 'Proses Penyakit',
        'ket_provokes'         => trim($_POST['ket_provokes'] ?? ''),
        'quality'              => $_POST['quality'] ?? 'Tumpul',
        'ket_quality'          => trim($_POST['ket_quality'] ?? ''),
        'lokasi'               => trim($_POST['lokasi'] ?? ''),
        'menyebar'             => $_POST['menyebar'] ?? 'Tidak',
        'skala_nyeri'          => $_POST['skala_nyeri'] ?? '0',
        'durasi'               => trim($_POST['durasi'] ?? ''),
        'nyeri_hilang'         => $_POST['nyeri_hilang'] ?? 'Istirahat',
        'ket_nyeri'            => trim($_POST['ket_nyeri'] ?? ''),
        'pada_dokter'          => $_POST['pada_dokter'] ?? 'Tidak',
        'ket_dokter'           => trim($_POST['ket_dokter'] ?? ''),
        'masalah'              => trim($_POST['masalah'] ?? ''),
        'tindakan'             => trim($_POST['tindakan'] ?? ''),
        'nip'                  => $nip
    ];

    if ($data_fields['keluhan_utama'] === '') {
        $error = 'Keluhan Utama wajib diisi.';
    } else {
        try {
            if ($hasData) {
                // Update
                $setSql = [];
                $params = [];
                foreach ($data_fields as $col => $val) {
                    $setSql[] = "`$col` = ?";
                    $params[] = $val;
                }
                $params[] = $noRawat;
                $stmtUp = $pdo->prepare("UPDATE penilaian_awal_keperawatan_kebidanan SET " . implode(', ', $setSql) . " WHERE no_rawat = ?");
                $stmtUp->execute($params);
            } else {
                // Insert
                $cols = array_keys($data_fields);
                $vals = array_values($data_fields);
                $placeholders = array_fill(0, count($cols), '?');

                $stmtIns = $pdo->prepare(
                    "INSERT INTO penilaian_awal_keperawatan_kebidanan (no_rawat, " . implode(', ', array_map(fn($c) => "`$c`", $cols)) . ") 
                     VALUES (?, " . implode(', ', $placeholders) . ")"
                );
                $stmtIns->execute([$noRawat, ...$vals]);
            }
            header('Location: kebidanan-keperawatan.php?no_rawat=' . urlencode($noRawat) . '&status=success');
            exit;
        } catch (Throwable $e) {
            error_log('[kebidanan-keperawatan.php] ' . $e->getMessage());
            $error = 'Gagal menyimpan: ' . $e->getMessage();
        }
        }
    }
}

$halamanAktif = 'asesmen';
$judulHalaman = 'Asesmen Keperawatan Kebidanan';
$baseAsset    = '../';
require __DIR__ . '/../lib/layout_header.php';
?>
<style>
.fm2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.fm3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
.fm4 { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:12px; }
.sec-title {
    font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.08em;
    color:var(--color-primary); margin:18px 0 10px; padding-bottom:5px;
    border-bottom:1.5px solid var(--color-border);
}
.tab-nav { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:18px; border-bottom:2px solid var(--color-border); }
.tab-btn {
    padding:8px 16px; background:none; border:none; border-bottom:3px solid transparent;
    font-size:13px; font-weight:600; cursor:pointer; color:var(--color-text-mute);
    transition: 0.15s;
}
.tab-btn.active { color:var(--color-primary); border-bottom-color:var(--color-primary); background:#FDF6F8; }
.tab-pane { display:none; }
.tab-pane.active { display:block; }
.form-section { background: #fafafa; border: 1px solid var(--color-border); border-radius: 8px; padding: 15px; margin-bottom: 12px; }
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
        .tab-nav {
            display: none !important;
        }

        .tab-pane {
            display: block !important;
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
        <a href="cetak_asesmen.php?type=kebidanan-keperawatan&no_rawat=<?= urlencode($noRawat) ?>" target="_blank"
           class="btn btn-outline" style="font-size:12.5px; padding:6px 14px; display:inline-flex; align-items:center; gap:6px; text-decoration:none;">
            🖨️ Cetak Hasil Asesmen
        </a>
    </div>
</div>

<?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?><div class="alert alert-success no-print" id="alert-simpan-sukses">✔ Data Asesmen Keperawatan Bidan berhasil disimpan.</div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger no-print"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($daftarRiwayat): ?>
<div class="card card-riwayat-container" style="margin-bottom:15px;">
    <p class="card-title">Riwayat Asesmen Keperawatan Kebidanan</p>
    <div style="overflow-x:auto;">
    <table style="width:100%; border-collapse:collapse; font-size:12.5px;">
        <thead>
            <tr style="background:var(--color-primary); color:#fff;">
                <th style="padding:7px 10px; text-align:left;">Tanggal</th>
                <th style="padding:7px 10px; text-align:left;">No. Rawat</th>
                <th style="padding:7px 10px; text-align:left;">Bidan / Petugas</th>
                <th style="padding:7px 10px; text-align:left;">Keluhan Utama</th>
                <th style="padding:7px 10px; text-align:left;">TTV (TD/Nadi/Suhu)</th>
                <th style="padding:7px 10px; text-align:center; width:130px;" class="col-aksi">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($daftarRiwayat as $r): ?>
            <tr style="border-bottom:1px solid var(--color-border);">
                <td style="padding:6px 10px;"><?= htmlspecialchars(date('d-m-Y H:i', strtotime($r['tanggal']))) ?></td>
                <td style="padding:6px 10px;"><code><?= htmlspecialchars($r['no_rawat']) ?></code></td>
                <td style="padding:6px 10px;"><?= htmlspecialchars($r['nm_petugas'] ?? '-') ?></td>
                <td style="padding:6px 10px;"><?= htmlspecialchars(mb_substr($r['keluhan_utama'] ?? '-', 0, 45)) ?><?= mb_strlen($r['keluhan_utama'] ?? '') > 45 ? '…' : '' ?></td>
                <td style="padding:6px 10px;"><?= htmlspecialchars($r['td'] ?: '-') ?> | <?= htmlspecialchars($r['nadi'] ?: '-') ?> | <?= htmlspecialchars($r['suhu'] ?: '-') ?>°C</td>
                <td style="padding:6px 10px; text-align:center;" class="col-aksi">
                    <a href="kebidanan-keperawatan.php?no_rawat=<?= urlencode($r['no_rawat']) ?>"
                       class="btn btn-outline" style="font-size:12px; padding:3px 8px; text-decoration:none;">Edit</a>
                    <a href="cetak_asesmen.php?type=kebidanan-keperawatan&no_rawat=<?= urlencode($r['no_rawat']) ?>" target="_blank"
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
    <p class="card-title"><?= $hasData ? 'Edit Asesmen Awal Keperawatan Bidan' : 'Isi Asesmen Awal Keperawatan Bidan' ?></p>

    <?php if ($sudahBayar): ?>
        <div class="alert alert-danger" style="margin-bottom:15px; font-size:12.5px;">
            🔒 <strong>Data dikunci:</strong> Pembayaran telah diselesaikan. Anda tidak dapat menambah atau mengubah asesmen keperawatan ini.
        </div>
    <?php endif; ?>

    <form method="post" id="formKep">
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

        <div class="tab-nav">
            <button type="button" class="tab-btn active" onclick="showTab('tab1', this)">1. Anamnesis & Haid</button>
            <button type="button" class="tab-btn" onclick="showTab('tab2', this)">2. Obstetris & KB</button>
            <button type="button" class="tab-btn" onclick="showTab('tab3', this)">3. Vital Sign & Fisik</button>
            <button type="button" class="tab-btn" onclick="showTab('tab4', this)">4. Kebidanan & USG</button>
            <button type="button" class="tab-btn" onclick="showTab('tab5', this)">5. Skrining & Masalah</button>
        </div>

        <!-- TAB 1 -->
        <div class="tab-pane active" id="tab1">
            <div class="form-section">
                <p class="sec-title" style="margin-top:0">Anamnesis</p>
                <div class="fm2">
                    <div>
                        <label for="nip">Petugas / Bidan Pemeriksa (NIP) *</label>
                        <select id="nip" name="nip" required>
                            <option value="">-- Pilih Petugas / Bidan --</option>
                            <?php foreach ($daftarPetugas as $pet): ?>
                                <option value="<?= htmlspecialchars($pet['nip']) ?>" <?= ($prefill['nip'] ?? $_SESSION['nip'] ?? '') === $pet['nip'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pet['nama']) ?> (NIP: <?= htmlspecialchars($pet['nip']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="informasi">Sumber Informasi</label>
                        <select id="informasi" name="informasi">
                            <option value="Keluaraga" <?= ($prefill['informasi'] ?? '') === 'Keluaraga' ? 'selected' : '' ?>>Keluarga / Pengantar</option>
                            <option value="Pasien" <?= ($prefill['informasi'] ?? 'Pasien') === 'Pasien' ? 'selected' : '' ?>>Pasien Sendiri (Autoanamnesis)</option>
                        </select>
                    </div>
                </div>
                    <div>
                        <label for="keluhan_utama">Keluhan Utama *</label>
                        <textarea id="keluhan_utama" name="keluhan_utama" rows="2" required><?= htmlspecialchars($prefill['keluhan_utama'] ?? '') ?></textarea>
                    </div>
                </div>

            <div class="form-section">
                <p class="sec-title" style="margin-top:0">Riwayat Menstruasi</p>
                <div class="fm4">
                    <div>
                        <label for="umur">Umur Menarche (Thn)</label>
                        <input type="number" id="umur" name="umur" value="<?= htmlspecialchars($prefill['umur'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="lama">Lama Haid (Hari)</label>
                        <input type="number" id="lama" name="lama" value="<?= htmlspecialchars($prefill['lama'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="banyaknya">Banyaknya pembalut</label>
                        <input type="text" id="banyaknya" name="banyaknya" value="<?= htmlspecialchars($prefill['banyaknya'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="haid">Nyeri Haid (Dysmenorrhea)</label>
                        <input type="text" id="haid" name="haid" value="<?= htmlspecialchars($prefill['haid'] ?? '') ?>">
                    </div>
                </div>

                <div class="fm3" style="margin-top:10px;">
                    <div>
                        <label for="siklus">Siklus Haid (Hari)</label>
                        <input type="number" id="siklus" name="siklus" value="<?= htmlspecialchars($prefill['siklus'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="ket_siklus">Siklus</label>
                        <select id="ket_siklus" name="ket_siklus">
                            <option value="Teratur" <?= ($prefill['ket_siklus'] ?? 'Teratur') === 'Teratur' ? 'selected' : '' ?>>Teratur</option>
                            <option value="Tidak Teratur" <?= ($prefill['ket_siklus'] ?? '') === 'Tidak Teratur' ? 'selected' : '' ?>>Tidak Teratur</option>
                        </select>
                    </div>
                    <div>
                        <label for="ket_siklus1">Karakteristik</label>
                        <select id="ket_siklus1" name="ket_siklus1">
                            <option value="Biasa" <?= ($prefill['ket_siklus1'] ?? 'Biasa') === 'Biasa' ? 'selected' : '' ?>>Biasa (Encer)</option>
                            <option value="Bergumpal" <?= ($prefill['ket_siklus1'] ?? '') === 'Bergumpal' ? 'selected' : '' ?>>Bergumpal</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2 -->
        <div class="tab-pane" id="tab2">
            <div class="form-section">
                <p class="sec-title" style="margin-top:0">Kehamilan Aktual & Status Pernikahan</p>
                <div class="fm4">
                    <div>
                        <label for="g">Gravida (G)</label>
                        <input type="number" id="g" name="g" value="<?= htmlspecialchars($prefill['g'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="p">Para (P)</label>
                        <input type="number" id="p" name="p" value="<?= htmlspecialchars($prefill['p'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="a">Abortus (A)</label>
                        <input type="number" id="a" name="a" value="<?= htmlspecialchars($prefill['a'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="hidup">Anak Hidup</label>
                        <input type="number" id="hidup" name="hidup" value="<?= htmlspecialchars($prefill['hidup'] ?? '') ?>">
                    </div>
                </div>

                <div class="fm3" style="margin-top:10px;">
                    <div>
                        <label for="hpht">HPHT</label>
                        <input type="date" id="hpht" name="hpht" value="<?= htmlspecialchars($prefill['hpht'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="tp">Taksiran Persalinan (HPL)</label>
                        <input type="date" id="tp" name="tp" value="<?= htmlspecialchars($prefill['tp'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="usia_kehamilan">Usia Kehamilan</label>
                        <input type="text" id="usia_kehamilan" name="usia_kehamilan" value="<?= htmlspecialchars($prefill['usia_kehamilan'] ?? '') ?>">
                    </div>
                </div>

                <div class="fm3" style="margin-top:10px;">
                    <div>
                        <label for="status">Pernikahan</label>
                        <select id="status" name="status">
                            <option value="Kawin" <?= ($prefill['status'] ?? 'Kawin') === 'Kawin' ? 'selected' : '' ?>>Menikah</option>
                            <option value="Belum Kawin" <?= ($prefill['status'] ?? '') === 'Belum Kawin' ? 'selected' : '' ?>>Belum Menikah</option>
                            <option value="Janda" <?= ($prefill['status'] ?? '') === 'Janda' ? 'selected' : '' ?>>Janda</option>
                        </select>
                    </div>
                    <div>
                        <label for="kali">Berapa Kali Pernikahan</label>
                        <input type="text" id="kali" name="kali" value="<?= htmlspecialchars($prefill['kali'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="imunisasi">Imunisasi TT</label>
                        <select id="imunisasi" name="imunisasi">
                            <option value="Ya" <?= ($prefill['imunisasi'] ?? '') === 'Ya' ? 'selected' : '' ?>>Ya</option>
                            <option value="Tidak" <?= ($prefill['imunisasi'] ?? 'Tidak') === 'Tidak' ? 'selected' : '' ?>>Tidak</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <p class="sec-title" style="margin-top:0">Riwayat KB & Ginekologi</p>
                <div class="fm3">
                    <div>
                        <label for="kb">Pernah Menggunakan KB?</label>
                        <select id="kb" name="kb">
                            <option value="Ya" <?= ($prefill['kb'] ?? '') === 'Ya' ? 'selected' : '' ?>>Ya</option>
                            <option value="Tidak" <?= ($prefill['kb'] ?? 'Tidak') === 'Tidak' ? 'selected' : '' ?>>Tidak</option>
                        </select>
                    </div>
                    <div>
                        <label for="ket_kb">Jenis KB Terakhir</label>
                        <input type="text" id="ket_kb" name="ket_kb" placeholder="Suntik, Pil, IUD, dll." value="<?= htmlspecialchars($prefill['ket_kb'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="komplikasi">Ada Komplikasi KB?</label>
                        <select id="komplikasi" name="komplikasi">
                            <option value="Ada" <?= ($prefill['komplikasi'] ?? '') === 'Ada' ? 'selected' : '' ?>>Ada</option>
                            <option value="Tidak" <?= ($prefill['komplikasi'] ?? 'Tidak') === 'Tidak' ? 'selected' : '' ?>>Tidak</option>
                        </select>
                    </div>
                </div>
                <div class="fm2" style="margin-top:10px;">
                    <div>
                        <label for="berhenti">Kapan Berhenti KB</label>
                        <input type="text" id="berhenti" name="berhenti" value="<?= htmlspecialchars($prefill['berhenti'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="alasan">Alasan Berhenti</label>
                        <input type="text" id="alasan" name="alasan" value="<?= htmlspecialchars($prefill['alasan'] ?? '') ?>">
                    </div>
                </div>
                <div style="margin-top: 10px;">
                    <label for="ginekologi">Riwayat Ginekologi / Penyakit Rahim / SC Lalu</label>
                    <select id="ginekologi" name="ginekologi">
                        <option value="Tidak Ada" <?= ($prefill['ginekologi'] ?? 'Tidak Ada') === 'Tidak Ada' ? 'selected' : '' ?>>Tidak Ada</option>
                        <option value="tumor kandungan" <?= ($prefill['ginekologi'] ?? '') === 'tumor kandungan' ? 'selected' : '' ?>>Tumor / Kista</option>
                        <option value="infeksi rahim" <?= ($prefill['ginekologi'] ?? '') === 'infeksi rahim' ? 'selected' : '' ?>>Infeksi Rahim</option>
                        <option value="pernah SC" <?= ($prefill['ginekologi'] ?? '') === 'pernah SC' ? 'selected' : '' ?>>Pernah Sectio Caesarea</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- TAB 3 -->
        <div class="tab-pane" id="tab3">
            <div class="form-section">
                <p class="sec-title" style="margin-top:0">Pemeriksaan Fisik Umum</p>
                <div class="fm3">
                    <div>
                        <label for="td">Tekanan Darah (mmHg)</label>
                        <input type="text" id="td" name="td" value="<?= htmlspecialchars($prefill['td'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="nadi">Nadi (x/menit)</label>
                        <input type="text" id="nadi" name="nadi" value="<?= htmlspecialchars($prefill['nadi'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="rr">Respirasi (x/menit)</label>
                        <input type="text" id="rr" name="rr" value="<?= htmlspecialchars($prefill['rr'] ?? '') ?>">
                    </div>
                </div>

                <div class="fm4" style="margin-top:10px;">
                    <div>
                        <label for="suhu">Suhu (°C)</label>
                        <input type="text" id="suhu" name="suhu" value="<?= htmlspecialchars($prefill['suhu'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="gcs">GCS</label>
                        <input type="text" id="gcs" name="gcs" value="<?= htmlspecialchars($prefill['gcs'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="bb">Berat Badan (kg)</label>
                        <input type="text" id="bb" name="bb" value="<?= htmlspecialchars($prefill['bb'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="tb">Tinggi Badan (cm)</label>
                        <input type="text" id="tb" name="tb" value="<?= htmlspecialchars($prefill['tb'] ?? '') ?>">
                    </div>
                </div>

                <div class="fm2" style="max-width:400px; margin-top:10px;">
                    <div>
                        <label for="lila">LILA (cm)</label>
                        <input type="text" id="lila" name="lila" value="<?= htmlspecialchars($prefill['lila'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="bmi">BMI</label>
                        <input type="text" id="bmi" name="bmi" value="<?= htmlspecialchars($prefill['bmi'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 4 -->
        <div class="tab-pane" id="tab4">
            <div class="form-section">
                <p class="sec-title" style="margin-top:0">Kondisi Kebidanan / Obstetrik</p>
                <div class="fm3">
                    <div>
                        <label for="tfu">TFU (cm)</label>
                        <input type="text" id="tfu" name="tfu" value="<?= htmlspecialchars($prefill['tfu'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="tbj">TBJ (gram)</label>
                        <input type="text" id="tbj" name="tbj" value="<?= htmlspecialchars($prefill['tbj'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="letak">Letak Janin</label>
                        <input type="text" id="letak" name="letak" value="<?= htmlspecialchars($prefill['letak'] ?? '') ?>">
                    </div>
                </div>

                <div class="fm4" style="margin-top:10px;">
                    <div>
                        <label for="presentasi">Presentasi</label>
                        <input type="text" id="presentasi" name="presentasi" value="<?= htmlspecialchars($prefill['presentasi'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="penurunan">Penurunan Kepala</label>
                        <input type="text" id="penurunan" name="penurunan" value="<?= htmlspecialchars($prefill['penurunan'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="his">His (x/10 menit)</label>
                        <input type="text" id="his" name="his" value="<?= htmlspecialchars($prefill['his'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="bjj">BJJ (djj)</label>
                        <input type="text" id="bjj" name="bjj" value="<?= htmlspecialchars($prefill['bjj'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <p class="sec-title" style="margin-top:0">Pemeriksaan VT & Penunjang</p>
                <div class="fm3">
                    <div>
                        <label for="portio">Portio</label>
                        <input type="text" id="portio" name="portio" value="<?= htmlspecialchars($prefill['portio'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="serviks">Serviks (Pembukaan)</label>
                        <input type="text" id="serviks" name="serviks" value="<?= htmlspecialchars($prefill['serviks'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="ketuban">Ketuban</label>
                        <input type="text" id="ketuban" name="ketuban" value="<?= htmlspecialchars($prefill['ketuban'] ?? '') ?>">
                    </div>
                </div>

                <div class="fm3" style="margin-top:10px;">
                    <div>
                        <label for="usg">USG</label>
                        <select id="usg" name="usg">
                            <option value="Tidak" <?= ($prefill['usg'] ?? 'Tidak') === 'Tidak' ? 'selected' : '' ?>>Tidak</option>
                            <option value="Ada" <?= ($prefill['usg'] ?? '') === 'Ada' ? 'selected' : '' ?>>Ada</option>
                        </select>
                    </div>
                    <div>
                        <label for="ket_usg">Keterangan USG</label>
                        <input type="text" id="ket_usg" name="ket_usg" value="<?= htmlspecialchars($prefill['ket_usg'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="panggul">Pemeriksaan Panggul</label>
                        <select id="panggul" name="panggul">
                            <option value="Normal" <?= ($prefill['panggul'] ?? 'Normal') === 'Normal' ? 'selected' : '' ?>>Normal (Adekuat)</option>
                            <option value="Abnormal" <?= ($prefill['panggul'] ?? '') === 'Abnormal' ? 'selected' : '' ?>>Abnormal / Sempit</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 5 -->
        <div class="tab-pane" id="tab5">
            <div class="form-section">
                <p class="sec-title" style="margin-top:0">Skrining Nyeri</p>
                <div class="fm3">
                    <div>
                        <label for="nyeri">Apakah ada Nyeri?</label>
                        <select id="nyeri" name="nyeri">
                            <option value="Tidak" <?= ($prefill['nyeri'] ?? 'Tidak') === 'Tidak' ? 'selected' : '' ?>>Tidak Ada Nyeri</option>
                            <option value="Ya" <?= ($prefill['nyeri'] ?? '') === 'Ya' ? 'selected' : '' ?>>Ada Nyeri</option>
                        </select>
                    </div>
                    <div>
                        <label for="skala_nyeri">Skala Nyeri (0-10)</label>
                        <select id="skala_nyeri" name="skala_nyeri">
                            <?php for($i=0; $i<=10; $i++): ?>
                                <option value="<?= $i ?>" <?= ($prefill['skala_nyeri'] ?? '0') == $i ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label for="lokasi">Lokasi Kandungan / Nyeri</label>
                        <input type="text" id="lokasi" name="lokasi" value="<?= htmlspecialchars($prefill['lokasi'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <p class="sec-title" style="margin-top:0">Penutup & Rencana Keperawatan</p>
                <label for="masalah">Masalah / Diagnosis Keperawatan Bidan</label>
                <textarea id="masalah" name="masalah" rows="2" placeholder="Gagal bersalin, cemas, risiko perdarahan..."><?= htmlspecialchars($prefill['masalah'] ?? '') ?></textarea>

                <label for="tindakan" style="margin-top:10px;">Intervensi / Tindakan Mandiri Bidan</label>
                <textarea id="tindakan" name="tindakan" rows="2" placeholder="Observasi djj, anjurkan berbaring miring kiri..."><?= htmlspecialchars($prefill['tindakan'] ?? '') ?></textarea>
            </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:24px; padding-top:16px; border-top:1px solid var(--color-border);">
            <button type="submit" class="btn btn-primary">
                <?= $hasData ? 'Simpan Perubahan Asesmen' : 'Simpan Asesmen Keperawatan' ?>
            </button>
            <a href="pilih.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-outline">Batal</a>
        </div>
        </fieldset>
    </form>
</div>


<script>
function showTab(id, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    btn.classList.add('active');
}

// Auto-hitung HPL & Usia Kehamilan dari HPHT
document.getElementById('hpht').addEventListener('change', function() {
    if (!this.value) return;
    const d = new Date(this.value);
    d.setDate(d.getDate() + 280);
    document.getElementById('tp').value = d.toISOString().split('T')[0];
    const today = new Date();
    const diff = Math.floor((today - new Date(this.value)) / 86400000);
    document.getElementById('usia_kehamilan').value = `${Math.floor(diff/7)} minggu ${diff%7} hari`;
});

// Auto hitung BMI
function hitungBmi() {
    const bb = parseFloat(document.getElementById('bb').value) || 0;
    const tb = parseFloat(document.getElementById('tb').value) || 0;
    if (bb > 0 && tb > 0) {
        const tbMeter = tb / 100;
        const bmiVal = (bb / (tbMeter * tbMeter)).toFixed(1);
        document.getElementById('bmi').value = bmiVal;
    }
}
document.getElementById('bb').addEventListener('input', hitungBmi);
document.getElementById('tb').addEventListener('input', hitungBmi);

// UX: Auto-SweetAlert + redirect setelah simpan berhasil
(function () {
    const params = new URLSearchParams(window.location.search);
    if (params.get('status') !== 'success') return;
    const noRawat = params.get('no_rawat') || '';
    const baseUrl = 'kebidanan-keperawatan.php?no_rawat=' + encodeURIComponent(noRawat);
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil Disimpan!',
            text: 'Data Asesmen Keperawatan Bidan berhasil disimpan.',
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
