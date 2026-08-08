<?php
/**
 * usg/kandungan.php
 * -----------------------------------------------------------------
 * Form Pemeriksaan USG Kandungan / Kebidanan / Obstetri →
 * tabel: hasil_pemeriksaan_usg & hasil_pemeriksaan_usg_gambar
 * PK: no_rawat (satu kunjungan, satu hasil USG kandungan)
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';

wajibLogin();

$pdo = getKoneksi();

$noRawat = trim($_GET['no_rawat'] ?? $_POST['no_rawat'] ?? '');
if ($noRawat === '' && !empty($_GET['no_rkm_medis'])) {
    $stRm = $pdo->prepare("SELECT no_rawat FROM reg_periksa WHERE no_rkm_medis = ? ORDER BY tgl_registrasi DESC LIMIT 1");
    $stRm->execute([$_GET['no_rkm_medis']]);
    $noRawat = (string)($stRm->fetchColumn() ?: '');
}
if ($noRawat === '') {
    header('Location: index.php');
    exit;
}

// 1. Ambil data kunjungan + pasien
$stmt = $pdo->prepare(
    "SELECT r.no_rawat, r.tgl_registrasi, r.kd_dokter,
            p.nm_pasien, p.no_rkm_medis, p.jk, p.tgl_lahir,
            dok.nm_dokter, dok.kd_dokter as kd_dok
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

// Simpan no_rawat ke session agar sidebar (Tindakan/Resep/Billing) sinkron
$_SESSION['last_no_rawat'] = $noRawat;

// 2. Ambil data existing USG kandungan
$stmtGet = $pdo->prepare("SELECT * FROM hasil_pemeriksaan_usg WHERE no_rawat = ?");
$stmtGet->execute([$noRawat]);
$prefill = $stmtGet->fetch() ?: [];
$hasData = !empty($prefill);

// Jika belum ada data di no_rawat ini, copy/prefill dari hasil USG Kandungan sebelumnya untuk pasien ini
if (!$hasData && !empty($kunjungan['no_rkm_medis'])) {
    $stmtPrev = $pdo->prepare(
        "SELECT u.* FROM hasil_pemeriksaan_usg u
         JOIN reg_periksa r ON u.no_rawat = r.no_rawat
         WHERE r.no_rkm_medis = ? AND u.no_rawat != ?
         ORDER BY u.tanggal DESC LIMIT 1"
    );
    $stmtPrev->execute([$kunjungan['no_rkm_medis'], $noRawat]);
    $prevData = $stmtPrev->fetch();
    if ($prevData) {
        $prefill = $prevData;
    }
}

// 3. Ambil data existing gambar USG kandungan
$stmtImg = $pdo->prepare("SELECT * FROM hasil_pemeriksaan_usg_gambar WHERE no_rawat = ?");
$stmtImg->execute([$noRawat]);
$imgRecord = $stmtImg->fetch();
$existingPhoto = $imgRecord['photo'] ?? '';

// 4. Ambil daftar dokter untuk dropdown
$stmtDokters = $pdo->query("SELECT kd_dokter, nm_dokter FROM dokter ORDER BY nm_dokter ASC");
$dokters = $stmtDokters->fetchAll();

// 5. Ambil riwayat USG Kandungan seluruh kunjungan pasien ini (GROUP BY u.no_rawat untuk cegah duplikasi)
$stmtRiwayat = $pdo->prepare(
    "SELECT u.*, dok.nm_dokter, r.no_rawat AS nr
     FROM hasil_pemeriksaan_usg u
     INNER JOIN reg_periksa r ON u.no_rawat = r.no_rawat
     LEFT JOIN dokter dok ON u.kd_dokter = dok.kd_dokter
     WHERE r.no_rkm_medis = ?
     GROUP BY u.no_rawat, u.tanggal
     ORDER BY u.tanggal DESC"
);
$stmtRiwayat->execute([$kunjungan['no_rkm_medis']]);
$daftarRiwayat = $stmtRiwayat->fetchAll();

// Catatan: halaman ini hanya menampilkan riwayat USG Kandungan.
// Riwayat asesmen lain (medis, keperawatan, kecantikan) tersedia di usg/kandungan.php hanya lewat menu USG.

$error = '';
$sukses = false;

$sudahBayar = isSudahBayar($noRawat, $pdo);

// Tentukan direktori upload (folder berkasrawat di webapps)
$usgUploadDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/webapps/berkasrawat/pages/upload/';
$usgUploadUrl = '/webapps/berkasrawat/pages/upload/';

// Action Hapus Gambar secara independen (AJAX/GET)
if (isset($_GET['action']) && $_GET['action'] === 'delete_photo') {
    if ($sudahBayar) {
        $error = 'Peringatan: Tidak dapat menghapus gambar karena pasien sudah melakukan pembayaran (LUNAS).';
    } else {
        try {
            if ($existingPhoto !== '') {
                $physicalPath = $usgUploadDir . basename($existingPhoto);
                if (file_exists($physicalPath) && is_file($physicalPath)) {
                    unlink($physicalPath);
                }
                $stmtDelImg = $pdo->prepare("DELETE FROM hasil_pemeriksaan_usg_gambar WHERE no_rawat = ?");
                $stmtDelImg->execute([$noRawat]);
                header("Location: kandungan.php?no_rawat=" . urlencode($noRawat) . "&delete_success=1");
                exit;
            }
        } catch (Throwable $e) {
            $error = 'Gagal menghapus gambar: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($sudahBayar) {
        $error = 'Peringatan: Data tidak dapat disimpan karena pasien sudah melakukan pembayaran (LUNAS).';
    } else {
        // Tangkap data post
    $kdDokter = $_POST['kd_dokter'] ?? $kunjungan['kd_dok'] ?? '';
    $diagnosaKlinis = trim($_POST['diagnosa_klinis'] ?? '');
    $kirimanDari = trim($_POST['kiriman_dari'] ?? '');
    $hta = trim($_POST['hta'] ?? '');
    $kantongGestasi = trim($_POST['kantong_gestasi'] ?? '');
    $ukuranBokong = trim($_POST['ukuran_bokongkepala'] ?? '');
    $jenisPrestasi = trim($_POST['jenis_prestasi'] ?? '');
    $diameterBiparietal = trim($_POST['diameter_biparietal'] ?? '');
    $panjangFemur = trim($_POST['panjang_femur'] ?? '');
    $lingkarAbdomen = trim($_POST['lingkar_abdomen'] ?? '');
    $tafsiranBerat = trim($_POST['tafsiran_berat_janin'] ?? '');
    $usiaKehamilan = trim($_POST['usia_kehamilan'] ?? '');
    $plasenta = trim($_POST['plasenta_berimplatansi'] ?? '');
    $derajatMaturitas = $_POST['derajat_maturitas'] ?? null;
    $jumlahAir = $_POST['jumlah_air_ketuban'] ?? null;
    $indeksCairan = trim($_POST['indek_cairan_ketuban'] ?? '');
    $kelainan = trim($_POST['kelainan_kongenital'] ?? '');
    $peluangSex = $_POST['peluang_sex'] ?? '-';
    $kesimpulan = trim($_POST['kesimpulan'] ?? '');
    $tglRegistrasi = $kunjungan['tgl_registrasi'];
    
    // Tentukan waktu USG: jika baru, gunakan waktu sekarang; jika edit, gunakan waktu existing
    $waktuUSG = $hasData ? $prefill['tanggal'] : date('Y-m-d H:i:s');

    try {
        $pdo->beginTransaction();

        if ($hasData) {
            // Update table hasil_pemeriksaan_usg
            $stmtUp = $pdo->prepare(
                "UPDATE hasil_pemeriksaan_usg SET 
                    tanggal = ?, 
                    kd_dokter = ?, 
                    diagnosa_klinis = ?, 
                    kiriman_dari = ?, 
                    hta = ?, 
                    kantong_gestasi = ?, 
                    ukuran_bokongkepala = ?, 
                    jenis_prestasi = ?, 
                    diameter_biparietal = ?, 
                    panjang_femur = ?, 
                    lingkar_abdomen = ?, 
                    tafsiran_berat_janin = ?, 
                    usia_kehamilan = ?, 
                    plasenta_berimplatansi = ?, 
                    derajat_maturitas = ?, 
                    jumlah_air_ketuban = ?, 
                    indek_cairan_ketuban = ?, 
                    kelainan_kongenital = ?, 
                    peluang_sex = ?, 
                    kesimpulan = ?
                 WHERE no_rawat = ?"
            );
            $stmtUp->execute([
                $waktuUSG, $kdDokter, $diagnosaKlinis, $kirimanDari, $hta, 
                $kantongGestasi, $ukuranBokong, $jenisPrestasi, $diameterBiparietal, 
                $panjangFemur, $lingkarAbdomen, $tafsiranBerat, $usiaKehamilan, 
                $plasenta, $derajatMaturitas, $jumlahAir, $indeksCairan, 
                $kelainan, $peluangSex, $kesimpulan, $noRawat
            ]);
        } else {
            // Insert table hasil_pemeriksaan_usg
            $stmtIn = $pdo->prepare(
                "INSERT INTO hasil_pemeriksaan_usg (
                    no_rawat, tanggal, kd_dokter, diagnosa_klinis, kiriman_dari, 
                    hta, kantong_gestasi, ukuran_bokongkepala, jenis_prestasi, 
                    diameter_biparietal, panjang_femur, lingkar_abdomen, 
                    tafsiran_berat_janin, usia_kehamilan, plasenta_berimplatansi, 
                    derajat_maturitas, jumlah_air_ketuban, indek_cairan_ketuban, 
                    kelainan_kongenital, peluang_sex, kesimpulan
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmtIn->execute([
                $noRawat, $waktuUSG, $kdDokter, $diagnosaKlinis, $kirimanDari, 
                $hta, $kantongGestasi, $ukuranBokong, $jenisPrestasi, 
                $diameterBiparietal, $panjangFemur, $lingkarAbdomen, 
                $tafsiranBerat, $usiaKehamilan, $plasenta, 
                $derajatMaturitas, $jumlahAir, $indeksCairan, 
                $kelainan, $peluangSex, $kesimpulan
            ]);
        }

        // ---------------------------------------------------------------
        // Handle Image Upload – dengan validasi & error handling lengkap
        // ---------------------------------------------------------------
        $uploadError = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {

            $uploadErrCode = $_FILES['photo']['error'];

            // 1. Cek status error upload dari PHP
            if ($uploadErrCode !== UPLOAD_ERR_OK) {
                $phpUploadErrors = [
                    UPLOAD_ERR_INI_SIZE   => 'File melebihi batas upload_max_filesize di php.ini.',
                    UPLOAD_ERR_FORM_SIZE  => 'File melebihi batas MAX_FILE_SIZE di form HTML.',
                    UPLOAD_ERR_PARTIAL    => 'File hanya terupload sebagian.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan di server.',
                    UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk.',
                    UPLOAD_ERR_EXTENSION  => 'Upload dihentikan oleh ekstensi PHP.',
                ];
                $uploadError = $phpUploadErrors[$uploadErrCode] ?? "Error upload tidak dikenal (kode: {$uploadErrCode})";
            } else {
                $fileTmpPath   = $_FILES['photo']['tmp_name'];
                $fileOrigName  = $_FILES['photo']['name'];
                $fileExtension = strtolower(pathinfo($fileOrigName, PATHINFO_EXTENSION));

                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

                if (!in_array($fileExtension, $allowedExtensions)) {
                    $uploadError = 'Ekstensi file tidak diperbolehkan. Gunakan: jpg, jpeg, png, atau gif.';
                } elseif (!is_uploaded_file($fileTmpPath)) {
                    $uploadError = 'File tidak valid (bukan hasil upload yang sah).';
                } else {
                    // 2. Target direktori: /webapps/berkasrawat/pages/upload/
                    $target_dir = $usgUploadDir;

                    // 3. Auto-create folder jika belum ada
                    if (!is_dir($target_dir)) {
                        if (!mkdir($target_dir, 0777, true)) {
                            $uploadError = 'Gagal membuat folder upload: ' . $target_dir;
                            error_log('[kandungan.php] mkdir gagal: ' . $target_dir);
                        }
                    }

                    if ($uploadError === '') {
                        // 4. Nama file unik & konsisten antara disk dan DB
                        $cleanNoRawat = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $noRawat);
                        $newFileName  = 'usg_kandungan_' . $cleanNoRawat . '_' . time() . '.' . $fileExtension;
                        $destPath     = $usgUploadDir . $newFileName;

                        // Buat folder jika belum ada
                        if (!is_dir($usgUploadDir)) {
                            if (!mkdir($usgUploadDir, 0777, true)) {
                                $uploadError = 'Gagal membuat folder upload: ' . $usgUploadDir;
                                error_log('[kandungan.php] mkdir gagal: ' . $usgUploadDir);
                            }
                        }

                        // 5. Pindahkan file – nama yang sama persis yang disimpan ke DB
                        if ($uploadError === '' && move_uploaded_file($fileTmpPath, $destPath)) {
                            // Simpan path relatif ke DB (pages/upload/filename.ext)
                            $dbPhotoPath = 'pages/upload/' . $newFileName;

                            // Hapus gambar lama jika ada
                            if ($existingPhoto !== '') {
                                $oldPhysicalPath = $usgUploadDir . basename($existingPhoto);
                                if (file_exists($oldPhysicalPath) && is_file($oldPhysicalPath)) {
                                    @unlink($oldPhysicalPath);
                                }
                            }

                            // Simpan atau update ke tabel hasil_pemeriksaan_usg_gambar
                            $stmtImgCheck = $pdo->prepare("SELECT COUNT(*) FROM hasil_pemeriksaan_usg_gambar WHERE no_rawat = ?");
                            $stmtImgCheck->execute([$noRawat]);
                            $hasImg = (int)$stmtImgCheck->fetchColumn() > 0;

                            if ($hasImg) {
                                $pdo->prepare("UPDATE hasil_pemeriksaan_usg_gambar SET photo = ? WHERE no_rawat = ?")
                                   ->execute([$dbPhotoPath, $noRawat]);
                            } else {
                                $pdo->prepare("INSERT INTO hasil_pemeriksaan_usg_gambar (no_rawat, photo) VALUES (?, ?)")
                                   ->execute([$noRawat, $dbPhotoPath]);
                            }
                            $existingPhoto = $dbPhotoPath;

                        } else {
                            // 6. Error handling jika move_uploaded_file gagal
                            $errMsg = "move_uploaded_file gagal. tmp={$fileTmpPath} | dest={$destPath} | is_writable=" . (is_writable($target_dir) ? 'yes' : 'no');
                            error_log('[kandungan.php] UPLOAD ERROR: ' . $errMsg);
                            $uploadError = 'Gagal memindahkan file gambar ke direktori tujuan. Periksa izin folder upload.';
                        }
                    }
                }
            }

            if ($uploadError !== '') {
                throw new Exception($uploadError);
            }
        }

        $pdo->commit();

        // ---------------------------------------------------------------
        // UX Auto-Redirect setelah simpan berhasil – bawa parameter status
        // ---------------------------------------------------------------
        header('Location: kandungan.php?no_rawat=' . urlencode($noRawat) . '&status=success');
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[kandungan.php] USG Error: ' . $e->getMessage());
        $error = 'Gagal menyimpan data USG: ' . $e->getMessage();
    }
    }
}

$halamanAktif = 'usg';
$judulHalaman = 'USG Kandungan (Obstetri)';
$baseAsset    = '../';
require __DIR__ . '/../lib/layout_header.php';

function ov($arr, $key, $default = '') {
    return htmlspecialchars($arr[$key] ?? $default);
}
?>
<style>
.tab-container {
    margin-top: 15px;
}
.tabs {
    display: flex;
    border-bottom: 2px solid var(--color-border);
    margin-bottom: 20px;
    gap: 8px;
}
.tab-btn {
    padding: 10px 18px;
    background: none;
    border: none;
    font-weight: 600;
    font-size: 13.5px;
    color: var(--color-text-mute);
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
}
.tab-btn:hover {
    color: var(--color-primary);
}
.tab-btn.active {
    color: var(--color-primary);
    border-bottom-color: var(--color-primary);
}
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}
.input-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.input-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
}
.input-grid-4 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 1fr;
    gap: 12px;
}
.section-title {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--color-primary);
    margin: 20px 0 10px;
    padding-bottom: 6px;
    border-bottom: 1.5px solid var(--color-border);
}
</style>

<div style="margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; font-size:13px;">
    <div>
        <a href="index.php?tanggal=<?= urlencode($kunjungan['tgl_registrasi']) ?>" class="btn btn-back">← Daftar Pasien USG</a>
        <span class="text-muted" style="margin-left:8px;">&bull; Kunjungan: <code><?= htmlspecialchars($noRawat) ?></code> &bull; Pasien: <strong><?= htmlspecialchars($kunjungan['nm_pasien']) ?></strong></span>
    </div>
    <div style="display:flex; gap:6px;">
        <?php if ($hasData): ?>
        <a href="cetak_usg.php?type=kandungan&no_rawat=<?= urlencode($noRawat) ?>" target="_blank"
           class="btn btn-outline" style="font-size:12px; padding:4px 10px; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
            🖨️ Cetak Hasil USG
        </a>
        <?php endif; ?>
        <a href="../asesmen/pilih.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-outline" style="font-size:12px; padding:4px 10px; text-decoration:none;">Menu Asesmen</a>
        <a href="../asesmen/index.php" class="btn btn-outline" style="font-size:12px; padding:4px 10px; text-decoration:none;">Daftar Pasien</a>
        <a href="../dashboard.php" class="btn btn-outline" style="font-size:12px; padding:4px 10px; text-decoration:none;">Dashboard</a>
    </div>
</div>

<?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
    <div class="alert alert-success" id="alert-simpan-sukses">✔ Data pemeriksaan USG Kandungan berhasil disimpan.</div>
<?php endif; ?>
<?php if (isset($_GET['delete_success'])): ?>
    <div class="alert alert-success">✔ Gambar lampiran USG berhasil dihapus.</div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <span class="text-muted">No. Rekam Medis:</span> <code><?= htmlspecialchars($kunjungan['no_rkm_medis']) ?></code>
            <span style="margin: 0 8px; color: var(--color-border);">|</span>
            <span class="text-muted">Nama:</span> <strong><?= htmlspecialchars($kunjungan['nm_pasien']) ?></strong>
            <span style="margin: 0 8px; color: var(--color-border);">|</span>
            <span class="text-muted">Tgl Lahir:</span> <?= date('d-m-Y', strtotime($kunjungan['tgl_lahir'])) ?>
        </div>
        <div>
            <span class="badge <?= $hasData ? 'badge-success' : 'badge-warning' ?>" style="font-size:12px; padding: 4px 10px;">
                <?= $hasData ? '✔ Data USG Terisi' : ' belum diisi' ?>
            </span>
        </div>
    </div>
</div>

<?php if ($daftarRiwayat): ?>
<div class="card" style="margin-bottom:15px;">
    <p class="card-title">Riwayat Pemeriksaan USG Kandungan</p>
    <div style="overflow-x:auto;">
    <table style="width:100%; border-collapse:collapse; font-size:12.5px;">
        <thead>
            <tr style="background:var(--color-primary); color:#fff;">
                <th style="padding:7px 10px; text-align:left;">Tanggal</th>
                <th style="padding:7px 10px; text-align:left;">Dokter</th>
                <th style="padding:7px 10px; text-align:left;">Diagnosa Klinis</th>
                <th style="padding:7px 10px; text-align:left;">Usia Kehamilan</th>
                <th style="padding:7px 10px; text-align:left;">TBJ</th>
                <th style="padding:7px 10px; text-align:left;">Kesimpulan</th>
                <th style="padding:7px 10px; text-align:center; width:70px;">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($daftarRiwayat as $r): ?>
            <tr style="border-bottom:1px solid var(--color-border);">
                <td style="padding:6px 10px;"><?= htmlspecialchars(date('d-m-Y H:i', strtotime($r['tanggal']))) ?></td>
                <td style="padding:6px 10px;"><?= htmlspecialchars($r['nm_dokter'] ?? '-') ?></td>
                <td style="padding:6px 10px;"><?= htmlspecialchars($r['diagnosa_klinis'] ?? '-') ?></td>
                <td style="padding:6px 10px;"><?= htmlspecialchars($r['usia_kehamilan'] ?? '-') ?></td>
                <td style="padding:6px 10px;"><?= htmlspecialchars($r['tafsiran_berat_janin'] ?? '-') ?> g</td>
                <td style="padding:6px 10px;"><?= htmlspecialchars(mb_substr($r['kesimpulan'] ?? '-', 0, 50)) ?><?= mb_strlen($r['kesimpulan'] ?? '') > 50 ? '…' : '' ?></td>
                <td style="padding:6px 10px; text-align:center;">
                    <a href="kandungan.php?no_rawat=<?= urlencode($r['nr']) ?>"
                       class="btn btn-outline" style="font-size:12px; padding:3px 10px; text-decoration:none;">Edit</a>
                    <a href="cetak_usg.php?type=kandungan&no_rawat=<?= urlencode($r['nr']) ?>" target="_blank"
                       class="btn btn-outline" style="font-size:12px; padding:3px 8px; margin-left:4px; border-color:var(--color-primary); color:var(--color-primary); text-decoration:none;" title="Cetak USG">🖨️</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>




<?php if ($sudahBayar): ?>
    <div class="alert alert-danger" style="margin-bottom:15px; font-size:12.5px;">
        🔒 <strong>Data dikunci:</strong> Pembayaran telah diselesaikan. Anda tidak dapat menambah atau mengubah data USG ini.
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <fieldset <?= $sudahBayar ? 'disabled' : '' ?> style="border:0; padding:0; margin:0;">
    <input type="hidden" name="no_rawat" value="<?= htmlspecialchars($noRawat) ?>">
    
    <div class="tab-container">
        <!-- Tabs Header -->
        <div class="tabs">
            <button type="button" class="tab-btn active" onclick="switchTab(event, 'tab-klinis')">1. Informasi Klinis</button>
            <button type="button" class="tab-btn" onclick="switchTab(event, 'tab-biometri')">2. Biometri Janin</button>
            <button type="button" class="tab-btn" onclick="switchTab(event, 'tab-lingkungan')">3. Lingkungan & Anatomi</button>
            <button type="button" class="tab-btn" onclick="switchTab(event, 'tab-hasil')">4. Kesimpulan & Gambar</button>
        </div>

        <!-- TAB 1: KLINIS -->
        <div id="tab-klinis" class="tab-content active">
            <div class="card">
                <p class="card-title">Informasi Klinis & Rujukan</p>
                
                <div class="input-grid-2">
                    <div>
                        <label for="kd_dokter">Dokter Pemeriksa USG *</label>
                        <select id="kd_dokter" name="kd_dokter" required>
                            <option value="">-- Pilih Dokter --</option>
                            <?php foreach ($dokters as $dok): ?>
                                <?php $selected = ($hasData ? $prefill['kd_dokter'] : $kunjungan['kd_dok']) === $dok['kd_dokter'] ? 'selected' : ''; ?>
                                <option value="<?= htmlspecialchars($dok['kd_dokter']) ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($dok['nm_dokter']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="hta">HTA / HPHT (Hari Pertama Haid Terakhir)</label>
                        <input type="text" id="hta" name="hta" placeholder="Misal: 10-09-2025 atau -" value="<?= ov($prefill, 'hta') ?>">
                    </div>
                </div>

                <div class="input-grid-2" style="margin-top:10px;">
                    <div>
                        <label for="diagnosa_klinis">Diagnosa Klinis</label>
                        <input type="text" id="diagnosa_klinis" name="diagnosa_klinis" placeholder="G1P0A0, Hamil 12 Minggu..." value="<?= ov($prefill, 'diagnosa_klinis') ?>">
                    </div>
                    <div>
                        <label for="kiriman_dari">Kiriman Dari</label>
                        <input type="text" id="kiriman_dari" name="kiriman_dari" placeholder="Bidan Mandiri / Rujukan..." value="<?= ov($prefill, 'kiriman_dari') ?>">
                    </div>
                </div>

                <div style="margin-top:10px;">
                    <label for="jenis_prestasi">Jenis Prestasi / Presentasi Janin</label>
                    <input type="text" id="jenis_prestasi" name="jenis_prestasi" placeholder="Kepala / Bokong / Melintang / Oblique..." value="<?= ov($prefill, 'jenis_prestasi') ?>">
                </div>
            </div>
        </div>

        <!-- TAB 2: BIOMETRI -->
        <div id="tab-biometri" class="tab-content">
            <div class="card">
                <p class="card-title">Biometri & Pengukuran Janin (USG Biometry)</p>
                <p class="text-muted" style="margin-bottom:15px; font-size:12px;">
                    Masukkan pengukuran biometri janin (dalam millimeter atau unit standar lainnya).
                </p>

                <div class="input-grid-3">
                    <div>
                        <label for="kantong_gestasi">Kantong Gestasi / GS (mm)</label>
                        <input type="text" id="kantong_gestasi" name="kantong_gestasi" placeholder="misal: 12.5" value="<?= ov($prefill, 'kantong_gestasi') ?>">
                    </div>
                    <div>
                        <label for="ukuran_bokongkepala">Crown-Rump Length / CRL (mm)</label>
                        <input type="text" id="ukuran_bokongkepala" name="ukuran_bokongkepala" placeholder="misal: 45.2" value="<?= ov($prefill, 'ukuran_bokongkepala') ?>">
                    </div>
                    <div>
                        <label for="diameter_biparietal">Diameter Biparietal / BPD (mm)</label>
                        <input type="text" id="diameter_biparietal" name="diameter_biparietal" placeholder="misal: 72.1" value="<?= ov($prefill, 'diameter_biparietal') ?>">
                    </div>
                </div>

                <div class="input-grid-3" style="margin-top:10px;">
                    <div>
                        <label for="panjang_femur">Panjang Femur / FL (mm)</label>
                        <input type="text" id="panjang_femur" name="panjang_femur" placeholder="misal: 58.4" value="<?= ov($prefill, 'panjang_femur') ?>">
                    </div>
                    <div>
                        <label for="lingkar_abdomen">Lingkar Abdomen / AC (mm)</label>
                        <input type="text" id="lingkar_abdomen" name="lingkar_abdomen" placeholder="misal: 242.5" value="<?= ov($prefill, 'lingkar_abdomen') ?>">
                    </div>
                    <div>
                        <label for="tafsiran_berat_janin">Tafsiran Berat Janin / EFW (gram)</label>
                        <input type="text" id="tafsiran_berat_janin" name="tafsiran_berat_janin" placeholder="misal: 1540" value="<?= ov($prefill, 'tafsiran_berat_janin') ?>">
                    </div>
                </div>

                <div style="margin-top:10px; max-width:300px;">
                    <label for="usia_kehamilan">Usia Kehamilan (GA / Gestational Age)</label>
                    <input type="text" id="usia_kehamilan" name="usia_kehamilan" placeholder="misal: 12 W 4 D atau 28-30 Mgg" value="<?= ov($prefill, 'usia_kehamilan') ?>">
                </div>
            </div>
        </div>

        <!-- TAB 3: LINGKUNGAN -->
        <div id="tab-lingkungan" class="tab-content">
            <div class="card">
                <p class="card-title">Kondisi Lingkungan & Anatomi Janin</p>

                <div class="input-grid-3">
                    <div>
                        <label for="plasenta_berimplatansi">Implantasi Plasenta</label>
                        <input type="text" id="plasenta_berimplatansi" name="plasenta_berimplatansi" placeholder="Misal: Corpus Anterior / Fundus..." value="<?= ov($prefill, 'plasenta_berimplatansi') ?>">
                    </div>
                    <div>
                        <label for="derajat_maturitas">Maturitas Plasenta (Grade)</label>
                        <select id="derajat_maturitas" name="derajat_maturitas">
                            <option value="">-- Pilih Grade --</option>
                            <?php foreach (['0', '1', '2', '3'] as $grade): ?>
                                <?php $selected = ($prefill['derajat_maturitas'] ?? '') === $grade ? 'selected' : ''; ?>
                                <option value="<?= $grade ?>" <?= $selected ?>>Grade <?= $grade ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="jumlah_air_ketuban">Jumlah Air Ketuban</label>
                        <select id="jumlah_air_ketuban" name="jumlah_air_ketuban">
                            <option value="">-- Pilih Jumlah --</option>
                            <?php foreach (['Cukup', 'Berkurang'] as $jml): ?>
                                <?php $selected = ($prefill['jumlah_air_ketuban'] ?? '') === $jml ? 'selected' : ''; ?>
                                <option value="<?= $jml ?>" <?= $selected ?>><?= $jml ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="input-grid-3" style="margin-top:10px;">
                    <div>
                        <label for="indek_cairan_ketuban">Indeks Cairan Ketuban / AFI</label>
                        <input type="text" id="indek_cairan_ketuban" name="indek_cairan_ketuban" placeholder="misal: 14 cm" value="<?= ov($prefill, 'indek_cairan_ketuban') ?>">
                    </div>
                    <div>
                        <label for="kelainan_kongenital">Kelainan Kongenital</label>
                        <input type="text" id="kelainan_kongenital" name="kelainan_kongenital" placeholder="Tidak ditemukan / spesifik..." value="<?= ov($prefill, 'kelainan_kongenital') ?>">
                    </div>
                    <div>
                        <label for="peluang_sex">Peluang Jenis Kelamin (Peluang Sex)</label>
                        <select id="peluang_sex" name="peluang_sex">
                            <?php foreach (['-', 'Laki-laki', 'Perempuan'] as $sex): ?>
                                <?php $selected = ($prefill['peluang_sex'] ?? '-') === $sex ? 'selected' : ''; ?>
                                <option value="<?= $sex ?>" <?= $selected ?>><?= $sex ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 4: HASIL -->
        <div id="tab-hasil" class="tab-content">
            <div class="card">
                <p class="card-title">Kesimpulan & Lampiran Gambar</p>

                <div>
                    <label for="kesimpulan">Kesimpulan Hasil USG</label>
                    <textarea id="kesimpulan" name="kesimpulan" rows="4" placeholder="Tuliskan kesimpulan medis hasil pemeriksaan USG obstetric..."><?= ov($prefill, 'kesimpulan') ?></textarea>
                </div>

                <div class="section-title">Foto Lampiran USG</div>
                
                <?php if ($existingPhoto !== ''): ?>
                    <div style="margin-bottom:15px; border:1px solid var(--color-border); padding:10px; border-radius:8px; display:inline-block; background:#f9f9f9; text-align:center;">
                        <p style="font-size:12px; font-weight:600; margin-bottom:8px;">File Saat Ini: <code><?= htmlspecialchars(basename($existingPhoto)) ?></code></p>
                        <img src="<?= $usgUploadUrl . htmlspecialchars(basename($existingPhoto)) ?>" alt="USG Kandungan Photo" style="max-width:320px; max-height:240px; border-radius:6px; display:block; margin: 0 auto 10px; box-shadow:0 2px 6px rgba(0,0,0,0.15)">
                        <?php if (!$sudahBayar): ?>
                        <a href="kandungan.php?no_rawat=<?= urlencode($noRawat) ?>&action=delete_photo" class="btn btn-outline" style="color: #D62839; border-color: #D62839; font-size:12px; padding: 4px 10px;" onclick="return confirm('Apakah Anda yakin ingin menghapus gambar lampiran USG ini?')">
                            Hapus Gambar
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div style="margin-top:10px;">
                    <label for="photo">Unggah / Ganti Gambar USG (Format: JPG / PNG / GIF)</label>
                    <input type="file" id="photo" name="photo" accept="image/*">
                    <small class="text-muted" style="display:block; margin-top:4px;">Disarankan resolusi sedang untuk kecepatan load data.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit buttons -->
    <div style="display:flex; gap:12px; margin-top:20px; align-items:center;">
        <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">Simpan Hasil USG</button>
        <a href="index.php?tanggal=<?= urlencode($kunjungan['tgl_registrasi']) ?>" class="btn btn-outline">Kembali</a>
    </div>
    </fieldset>
</form>

<script>
// ---------------------------------------------------------------
// Tab switcher
// ---------------------------------------------------------------
function switchTab(evt, tabId) {
    const contents = document.getElementsByClassName('tab-content');
    for (let i = 0; i < contents.length; i++) {
        contents[i].classList.remove('active');
    }
    const buttons = document.getElementsByClassName('tab-btn');
    for (let i = 0; i < buttons.length; i++) {
        buttons[i].classList.remove('active');
    }
    document.getElementById(tabId).classList.add('active');
    evt.currentTarget.classList.add('active');
}

// ---------------------------------------------------------------
// UX: Setelah simpan berhasil (redirect dengan ?status=success)
// Tampilkan SweetAlert, lalu auto-close setelah 2 detik.
// Jika SweetAlert tidak tersedia, alert biasa dengan redirect.
// ---------------------------------------------------------------
(function () {
    const params = new URLSearchParams(window.location.search);
    if (params.get('status') !== 'success') return;

    const noRawat = params.get('no_rawat') || '';
    const baseUrl = 'kandungan.php?no_rawat=' + encodeURIComponent(noRawat);

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil Disimpan!',
            text: 'Data pemeriksaan USG Kandungan berhasil disimpan.',
            confirmButtonText: 'OK',
            confirmButtonColor: 'var(--color-primary, #7C3AED)',
            timer: 3000,
            timerProgressBar: true
        }).then(function () {
            // Setelah klik OK atau timer habis → reload halaman tanpa ?status
            window.location.href = baseUrl;
        });
    } else {
        // Fallback: hilangkan ?status dari URL agar alert tidak muncul lagi setelah refresh
        const alertEl = document.getElementById('alert-simpan-sukses');
        if (alertEl) {
            // Auto-fade setelah 4 detik
            setTimeout(function () {
                alertEl.style.transition = 'opacity 0.5s';
                alertEl.style.opacity   = '0';
                setTimeout(function () { alertEl.style.display = 'none'; }, 500);
            }, 4000);
        }
        // Bersihkan query string ?status dari browser history tanpa reload
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, '', baseUrl);
        }
    }
})();
</script>

<?php require __DIR__ . '/../lib/layout_footer.php'; ?>
