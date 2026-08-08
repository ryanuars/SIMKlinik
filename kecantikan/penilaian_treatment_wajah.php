<?php
/**
 * Penilaian Awal & Rencana Treatment Face Massage
 * -------------------------------------------------
 * Kontribusi mengikuti pola SIMRS Khanza (reg_periksa -> pasien).
 * Menggunakan PDO (getKoneksi()) agar konsisten dengan SIMKlinik.
 *
 * Cara pakai:
 *   penilaian_treatment_wajah.php?no_rawat=XXXXXX/XX/XX/XXX
 */

session_start();
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../config/app.php";

$pdo = getKoneksi();

// Auto-migration: ubah no_rawat dari varchar(15) ke varchar(17) di database jika masih 15
try {
    $stCol = $pdo->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'penilaian_treatment_wajah' AND COLUMN_NAME = 'no_rawat'");
    $colLen = (int)($stCol->fetchColumn() ?: 0);
    if ($colLen > 0 && $colLen < 17) {
        @$pdo->exec("ALTER TABLE `penilaian_treatment_wajah_titik` DROP FOREIGN KEY `fk_ptwt_no_rawat`");
        @$pdo->exec("ALTER TABLE `penilaian_treatment_wajah` DROP FOREIGN KEY `fk_ptw_no_rawat`");
        $pdo->exec("ALTER TABLE `penilaian_treatment_wajah` MODIFY COLUMN `no_rawat` varchar(17) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL");
        $pdo->exec("ALTER TABLE `penilaian_treatment_wajah_titik` MODIFY COLUMN `no_rawat` varchar(17) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL");
        @$pdo->exec("ALTER TABLE `penilaian_treatment_wajah` ADD CONSTRAINT `fk_ptw_no_rawat` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE");
        @$pdo->exec("ALTER TABLE `penilaian_treatment_wajah_titik` ADD CONSTRAINT `fk_ptwt_no_rawat` FOREIGN KEY (`no_rawat`) REFERENCES `penilaian_treatment_wajah` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE");
    }
} catch (Throwable $t) {
    error_log('[ptw_migration] ' . $t->getMessage());
}
// Auto-migration: tambah kolom ttd_pasien jika belum ada
try {
    $stTtd = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='penilaian_treatment_wajah' AND COLUMN_NAME='ttd_pasien'");
    if (!$stTtd->fetchColumn()) {
        $pdo->exec("ALTER TABLE penilaian_treatment_wajah ADD COLUMN `ttd_pasien` MEDIUMTEXT DEFAULT NULL COMMENT 'TTD digital pasien (base64)' AFTER `nama_ttd_pasien`");
    }
} catch (Throwable $t) {
    error_log('[ptw_ttd_migration] ' . $t->getMessage());
}

$no_rawat = isset($_POST['no_rawat']) && $_POST['no_rawat'] !== '' 
            ? trim($_POST['no_rawat']) 
            : (isset($_GET['no_rawat']) ? trim($_GET['no_rawat']) : '');
$pesan    = '';
$error    = '';

/* ==========================================================
   1. PROSES SIMPAN (POST)
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($no_rawat === '') {
        $error = "No. Rawat tidak boleh kosong.";
    } else {
        // Validasi no_rawat ada di reg_periksa sebelum insert/update
        $stmtChk = $pdo->prepare("SELECT no_rawat FROM reg_periksa WHERE no_rawat = ? LIMIT 1");
        $stmtChk->execute([$no_rawat]);
        $matchedNoRawat = $stmtChk->fetchColumn();

        if (!$matchedNoRawat) {
            $error = "No. Rawat \"" . htmlspecialchars($no_rawat) . "\" tidak ditemukan di sistem. Pastikan pasien sudah terdaftar kunjungan.";
        } else {
            // Gunakan no_rawat persis dari reg_periksa
            $no_rawat = $matchedNoRawat;
            // --- kondisi_pasien (checkbox multi) ---
            $hamil    = isset($_POST['kondisi_hamil']) ? 'Ya' : 'Tidak';
            $menyusui = isset($_POST['kondisi_menyusui']) ? 'Ya' : 'Tidak';

            $data = [
                'no_rawat'                     => $no_rawat,
                'tgl_perawatan'                => date('Y-m-d H:i:s'),
                'bb'                           => (isset($_POST['bb']) && $_POST['bb'] !== '') ? $_POST['bb'] : null,
                'tb'                           => (isset($_POST['tb']) && $_POST['tb'] !== '') ? $_POST['tb'] : null,
                'email'                        => $_POST['email'] ?? '',
                'jenis_kulit'                  => $_POST['jenis_kulit'] ?? 'Normal',
                'jerawat'                      => $_POST['jerawat'] ?? 'Tidak Ada',
                'jerawat_area'                 => $_POST['jerawat_area'] ?? '',
                'jerawat_derajat'              => (isset($_POST['jerawat_derajat']) && $_POST['jerawat_derajat'] !== '') ? $_POST['jerawat_derajat'] : null,
                'cacat_bekas_jerawat'          => $_POST['cacat_bekas_jerawat'] ?? 'Tidak Ada',
                'cacat_bekas_jerawat_area'     => $_POST['cacat_bekas_jerawat_area'] ?? '',
                'cacat_bekas_jerawat_derajat'  => (isset($_POST['cacat_bekas_jerawat_derajat']) && $_POST['cacat_bekas_jerawat_derajat'] !== '') ? $_POST['cacat_bekas_jerawat_derajat'] : null,
                'fleks_hitam_cokelat'          => $_POST['fleks_hitam_cokelat'] ?? 'Tidak Ada',
                'fleks_area'                   => $_POST['fleks_area'] ?? '',
                'fleks_derajat'                => (isset($_POST['fleks_derajat']) && $_POST['fleks_derajat'] !== '') ? $_POST['fleks_derajat'] : null,
                'keriput_wajah'                => $_POST['keriput_wajah'] ?? 'Tidak Ada',
                'keriput_area'                 => $_POST['keriput_area'] ?? '',
                'area_sensitif'                => $_POST['area_sensitif'] ?? 'Tidak Ada',
                'area_sensitif_ket'            => $_POST['area_sensitif_ket'] ?? '',
                'kondisi_hamil'                => $hamil,
                'kondisi_menyusui'             => $menyusui,
                'menggunakan_kontrasepsi'      => $_POST['menggunakan_kontrasepsi'] ?? 'Tidak',
                'jenis_kontrasepsi'            => $_POST['jenis_kontrasepsi'] ?? '',
                'diet_khusus'                  => $_POST['diet_khusus'] ?? 'Tidak',
                'jenis_diet'                   => $_POST['jenis_diet'] ?? '',
                'alergi'                       => $_POST['alergi'] ?? 'Tidak',
                'alergi_ket'                   => $_POST['alergi_ket'] ?? '',
                'produk_perawatan_terakhir'    => $_POST['produk_perawatan_terakhir'] ?? '',
                'keluhan'                      => $_POST['keluhan'] ?? '',
                'riwayat_penyakit_dahulu'      => $_POST['riwayat_penyakit_dahulu'] ?? '',
                'riwayat_penyakit_keluarga'    => $_POST['riwayat_penyakit_keluarga'] ?? '',
                'fokus_pijatan_area'           => $_POST['fokus_pijatan_area'] ?? '',
                'tingkat_pijatan'              => (isset($_POST['tingkat_pijatan']) && $_POST['tingkat_pijatan'] !== '') ? $_POST['tingkat_pijatan'] : null,
                'persetujuan_pasien'           => isset($_POST['persetujuan_pasien']) ? 'Ya' : 'Tidak',
                'nama_ttd_pasien'              => $_POST['nama_ttd_pasien'] ?? '',
                'ttd_pasien'                   => (isset($_POST['ttd_pasien']) && $_POST['ttd_pasien'] !== '' && $_POST['ttd_pasien'] !== 'data:,') ? $_POST['ttd_pasien'] : null,
                'nip'                          => $_SESSION['user_id'] ?? ($_POST['nip'] ?? null),
            ];

            try {
                // Cek apakah sudah ada record untuk no_rawat ini
                $stmtExist = $pdo->prepare("SELECT COUNT(*) FROM penilaian_treatment_wajah WHERE no_rawat = ?");
                $stmtExist->execute([$no_rawat]);
                $exists = (int)$stmtExist->fetchColumn() > 0;

                if ($exists) {
                    // UPDATE — tidak include no_rawat di kolom update
                    $setCols = array_filter(array_keys($data), fn($k) => $k !== 'no_rawat');
                    $setSql = implode(', ', array_map(fn($k) => "`$k` = ?", $setCols));
                    $params = array_map(fn($k) => $data[$k], $setCols);
                    $params[] = $no_rawat;
                    $pdo->prepare("UPDATE penilaian_treatment_wajah SET $setSql WHERE no_rawat = ?")->execute($params);
                } else {
                    // INSERT
                    $kolom = array_keys($data);
                    $placeholder = implode(', ', array_fill(0, count($kolom), '?'));
                    $sql = "INSERT INTO penilaian_treatment_wajah (`" . implode('`, `', $kolom) . "`) VALUES ($placeholder)";
                    $pdo->prepare($sql)->execute(array_values($data));
                }

                // --- simpan ulang titik pijatan: hapus lalu insert kembali ---
                $pdo->prepare("DELETE FROM penilaian_treatment_wajah_titik WHERE no_rawat = ?")->execute([$no_rawat]);

                if (!empty($_POST['titik_x']) && is_array($_POST['titik_x'])) {
                    $stmtT = $pdo->prepare(
                        "INSERT INTO penilaian_treatment_wajah_titik (no_rawat, pos_x, pos_y, keterangan) VALUES (?, ?, ?, ?)"
                    );
                    foreach ($_POST['titik_x'] as $i => $x) {
                        $y   = $_POST['titik_y'][$i]   ?? 0;
                        $ket = $_POST['titik_ket'][$i] ?? '';
                        $stmtT->execute([$no_rawat, $x, $y, $ket]);
                    }
                }
                $pesan = "Data berhasil disimpan.";

            } catch (Throwable $e) {
                error_log('[penilaian_treatment_wajah.php] ' . $e->getMessage());
                $error = "Gagal menyimpan data: " . $e->getMessage();
            }
        }
    }
}

/* ==========================================================
   2. AMBIL IDENTITAS PASIEN (read-only, tidak perlu input ulang)
   ========================================================== */
$identitas = null;
if ($no_rawat !== '') {
    $q = $pdo->prepare(
        "SELECT reg_periksa.no_rawat, pasien.no_rkm_medis, pasien.nm_pasien,
                IF(pasien.jk='L','Laki-Laki','Perempuan') AS jk,
                pasien.tgl_lahir, pasien.alamat, pasien.agama,
                bp.nama_bahasa, cf.nama_cacat,
                reg_periksa.status_lanjut, pasien.no_tlp,
                TIMESTAMPDIFF(YEAR, pasien.tgl_lahir, CURDATE()) AS umur
         FROM reg_periksa
         INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
         LEFT JOIN bahasa_pasien bp ON bp.id = pasien.bahasa_pasien
         LEFT JOIN cacat_fisik cf  ON cf.id  = pasien.cacat_fisik
         WHERE reg_periksa.no_rawat = ?"
    );
    $q->execute([$no_rawat]);
    $identitas = $q->fetch();
}

/* ==========================================================
   3. AMBIL DATA TREATMENT (jika sudah pernah diisi -> mode edit)
   ========================================================== */
$row = null;
$titikTersimpan = [];
if ($no_rawat !== '') {
    $q2 = $pdo->prepare("SELECT * FROM penilaian_treatment_wajah WHERE no_rawat = ?");
    $q2->execute([$no_rawat]);
    $row = $q2->fetch();

    $q3 = $pdo->prepare("SELECT pos_x, pos_y, keterangan FROM penilaian_treatment_wajah_titik WHERE no_rawat = ?");
    $q3->execute([$no_rawat]);
    $titikTersimpan = $q3->fetchAll();
}

/** helper kecil untuk checked/selected pada radio & checkbox */
function chk($val, $target) { return ($val == $target) ? 'checked' : ''; }
function esc($v) { return htmlspecialchars($v ?? '', ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Penilaian Awal & Rencana Treatment Face Massage</title>
<style>
    body{font-family:Arial,Helvetica,sans-serif;font-size:13px;background:#f2f2f2;margin:0;padding:20px;}
    .sheet{max-width:900px;margin:auto;background:#fff;border:1px solid #333;padding:0;}
    .judul{text-align:center;font-weight:bold;padding:8px;border-bottom:2px solid #333;background:#e9e9e9;}
    .section-title{background:#dcdcdc;font-weight:bold;padding:6px 10px;border-top:2px solid #333;border-bottom:1px solid #333;}
    table{width:100%;border-collapse:collapse;}
    td{padding:5px 10px;vertical-align:top;}
    .lbl{width:230px;white-space:nowrap;}
    .colon{width:15px;}
    input[type=text], input[type=number], textarea, select{
        width:100%;box-sizing:border-box;padding:4px;border:1px solid #999;border-radius:3px;font-size:13px;
    }
    textarea{resize:vertical;}
    .inline label{margin-right:14px;font-weight:normal;white-space:nowrap;}
    .readonly-box{background:#f7f7f7;border:1px solid #ccc;padding:10px;margin:10px 15px;border-radius:4px;}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 20px;}
    .sub{margin-left:20px;color:#555;}
    .btn-simpan{display:block;margin:20px auto;padding:10px 30px;background:#2e7d32;color:#fff;border:none;
        border-radius:5px;font-size:15px;cursor:pointer;}
    .btn-simpan:hover{background:#256428;}
    .pesan-sukses{background:#dff0d8;color:#3c763d;padding:10px;margin:10px 15px;border-radius:4px;}
    .pesan-error{background:#f2dede;color:#a94442;padding:10px;margin:10px 15px;border-radius:4px;}
    .face-wrap{text-align:center;padding:10px;}
    #faceSvg{border:1px solid #ccc;cursor:crosshair;background:#fff;}
    .marker{fill:#e53935;stroke:#fff;stroke-width:1;cursor:pointer;}
    .hint{font-size:11px;color:#777;margin-top:5px;}
    .titik-list{font-size:12px;margin-top:8px;text-align:left;padding:0 15px;}
</style>
</head>
<body>
<div class="sheet">
    <div class="judul">FORMULIR PENILAIAN AWAL &amp; RENCANA TREATMENT FACE MASSAGE</div>

    <?php if ($pesan): ?><div class="pesan-sukses"><?= esc($pesan) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="pesan-error"><?= esc($error) ?></div><?php endif; ?>

    <?php if (!$identitas): ?>
        <div class="pesan-error">
            No. Rawat "<?= esc($no_rawat) ?>" tidak ditemukan. Buka halaman ini dengan parameter
            <code>?no_rawat=NOMOR_RAWAT</code> dari menu pendaftaran/kunjungan pasien.
        </div>
    <?php else: ?>

    <div class="section-title">IDENTITAS (otomatis, tidak perlu diisi ulang)</div>
    <div class="readonly-box">
        <div class="grid2">
            <div><b>No. Rawat</b> : <?= esc($identitas['no_rawat']) ?></div>
            <div><b>No. RM</b> : <?= esc($identitas['no_rkm_medis']) ?></div>
            <div><b>Nama Pasien</b> : <?= esc($identitas['nm_pasien']) ?></div>
            <div><b>Jenis Kelamin</b> : <?= esc($identitas['jk']) ?></div>
            <div><b>Tgl. Lahir / Umur</b> : <?= esc($identitas['tgl_lahir']) ?> (<?= esc($identitas['umur']) ?> th)</div>
            <div><b>Agama</b> : <?= esc($identitas['agama']) ?></div>
            <div><b>Bahasa</b> : <?= esc($identitas['nama_bahasa']) ?></div>
            <div><b>Cacat Fisik</b> : <?= esc($identitas['nama_cacat']) ?></div>
            <div><b>No. HP</b> : <?= esc($identitas['no_tlp']) ?></div>
            <div style="grid-column:1/3;"><b>Alamat</b> : <?= esc($identitas['alamat']) ?></div>
        </div>
    </div>

    <form method="post" id="formTreatment" action="penilaian_treatment_wajah.php?no_rawat=<?= urlencode($no_rawat) ?>">
    <input type="hidden" name="no_rawat" value="<?= esc($no_rawat) ?>">

    <div class="section-title">DATA TAMBAHAN KUNJUNGAN</div>
    <table>
        <tr>
            <td class="lbl">Email</td><td class="colon">:</td>
            <td><input type="text" name="email" value="<?= esc($row['email'] ?? '') ?>"></td>
            <td class="lbl">BB (kg) / TB (cm)</td><td class="colon">:</td>
            <td>
                <div style="display:flex;gap:8px;">
                    <input type="number" step="0.1" name="bb" value="<?= esc($row['bb'] ?? '') ?>" placeholder="BB">
                    <input type="number" step="0.1" name="tb" value="<?= esc($row['tb'] ?? '') ?>" placeholder="TB">
                </div>
            </td>
        </tr>
    </table>

    <div class="section-title">ANALISIS KONDISI PASIEN DAN KULIT WAJAH</div>
    <table>
        <tr>
            <td class="lbl">Jenis Kulit</td><td class="colon">:</td>
            <td colspan="3">
                <div class="inline">
                <?php foreach (['Normal','Kering','Berminyak','Kombinasi','Sensitif'] as $opt): ?>
                    <label><input type="radio" name="jenis_kulit" value="<?= $opt ?>" <?= chk($row['jenis_kulit'] ?? 'Normal', $opt) ?>> <?= $opt ?></label>
                <?php endforeach; ?>
                </div>
            </td>
        </tr>

        <?php
        // Blok berulang: Jerawat / Cacat-Bekas Jerawat / Fleksi Hitam-Cokelat (punya "derajat")
        $blokDerajat = [
            ['jerawat', 'Jerawat di Wajah'],
            ['cacat_bekas_jerawat', 'Cacat/Bekas Jerawat'],
            ['fleks_hitam_cokelat', 'Fleks Hitam/Cokelat'],
        ];
        foreach ($blokDerajat as [$f, $label]):
        ?>
        <tr>
            <td class="lbl"><?= $label ?></td><td class="colon">:</td>
            <td colspan="3">
                <div class="inline">
                    <label><input type="radio" name="<?= $f ?>" value="Ada" onclick="toggleArea('<?= $f ?>')" <?= chk($row[$f] ?? 'Tidak Ada','Ada') ?>> Ada, di area</label>
                    <label><input type="radio" name="<?= $f ?>" value="Tidak Ada" onclick="toggleArea('<?= $f ?>')" <?= chk($row[$f] ?? 'Tidak Ada','Tidak Ada') ?>> Tidak Ada</label>
                </div>
                <div id="wrap_<?= $f ?>_area" class="sub" style="margin-top:4px;">
                    <input type="text" name="<?= $f ?>_area" placeholder="Sebutkan area..." value="<?= esc($row[$f.'_area'] ?? '') ?>">
                </div>
                <div class="sub inline" style="margin-top:4px;">
                    Derajat Keparahan:
                    <?php foreach (['Ringan','Sedang','Berat'] as $d): ?>
                        <label><input type="radio" name="<?= $f ?>_derajat" value="<?= $d ?>" <?= chk($row[$f.'_derajat'] ?? '', $d) ?>> <?= $d ?></label>
                    <?php endforeach; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>

        <tr>
            <td class="lbl">Keriput di Wajah</td><td class="colon">:</td>
            <td colspan="3">
                <div class="inline">
                    <label><input type="radio" name="keriput_wajah" value="Ada" onclick="toggleArea('keriput_wajah')" <?= chk($row['keriput_wajah'] ?? 'Tidak Ada','Ada') ?>> Ada, di area</label>
                    <label><input type="radio" name="keriput_wajah" value="Tidak Ada" onclick="toggleArea('keriput_wajah')" <?= chk($row['keriput_wajah'] ?? 'Tidak Ada','Tidak Ada') ?>> Tidak Ada</label>
                </div>
                <div id="wrap_keriput_wajah_area" class="sub" style="margin-top:4px;">
                    <input type="text" name="keriput_area" placeholder="Sebutkan area..." value="<?= esc($row['keriput_area'] ?? '') ?>">
                </div>
            </td>
        </tr>

        <tr>
            <td class="lbl">Area Sensitif</td><td class="colon">:</td>
            <td colspan="3">
                <div class="inline">
                    <label><input type="radio" name="area_sensitif" value="Ada" onclick="toggleArea('area_sensitif')" <?= chk($row['area_sensitif'] ?? 'Tidak Ada','Ada') ?>> Ada, di area</label>
                    <label><input type="radio" name="area_sensitif" value="Tidak Ada" onclick="toggleArea('area_sensitif')" <?= chk($row['area_sensitif'] ?? 'Tidak Ada','Tidak Ada') ?>> Tidak Ada</label>
                </div>
                <div id="wrap_area_sensitif_area" class="sub" style="margin-top:4px;">
                    <input type="text" name="area_sensitif_ket" placeholder="Sebutkan area..." value="<?= esc($row['area_sensitif_ket'] ?? '') ?>">
                </div>
            </td>
        </tr>

        <tr>
            <td class="lbl">Kondisi Pasien</td><td class="colon">:</td>
            <td colspan="3">
                <div class="inline">
                    <label><input type="checkbox" name="kondisi_hamil" <?= (($row['kondisi_hamil'] ?? 'Tidak')=='Ya')?'checked':'' ?>> Hamil</label>
                    <label><input type="checkbox" name="kondisi_menyusui" <?= (($row['kondisi_menyusui'] ?? 'Tidak')=='Ya')?'checked':'' ?>> Menyusui</label>
                </div>
            </td>
        </tr>

        <tr>
            <td class="lbl">Menggunakan Kontrasepsi</td><td class="colon">:</td>
            <td colspan="3">
                <div class="inline">
                    <label><input type="radio" name="menggunakan_kontrasepsi" value="Tidak" onclick="toggleArea('kontrasepsi')" <?= chk($row['menggunakan_kontrasepsi'] ?? 'Tidak','Tidak') ?>> Tidak</label>
                    <label><input type="radio" name="menggunakan_kontrasepsi" value="Ya" onclick="toggleArea('kontrasepsi')" <?= chk($row['menggunakan_kontrasepsi'] ?? 'Tidak','Ya') ?>> Ya, Jenis Kontrasepsi</label>
                </div>
                <div id="wrap_kontrasepsi_area" class="sub" style="margin-top:4px;">
                    <input type="text" name="jenis_kontrasepsi" value="<?= esc($row['jenis_kontrasepsi'] ?? '') ?>">
                </div>
            </td>
        </tr>

        <tr>
            <td class="lbl">Diet Khusus yang Dijalani</td><td class="colon">:</td>
            <td colspan="3">
                <div class="inline">
                    <label><input type="radio" name="diet_khusus" value="Tidak" onclick="toggleArea('diet')" <?= chk($row['diet_khusus'] ?? 'Tidak','Tidak') ?>> Tidak</label>
                    <label><input type="radio" name="diet_khusus" value="Ya" onclick="toggleArea('diet')" <?= chk($row['diet_khusus'] ?? 'Tidak','Ya') ?>> Ya, Jenis Diet</label>
                </div>
                <div id="wrap_diet_area" class="sub" style="margin-top:4px;">
                    <input type="text" name="jenis_diet" value="<?= esc($row['jenis_diet'] ?? '') ?>">
                </div>
            </td>
        </tr>

        <tr>
            <td class="lbl">Alergi</td><td class="colon">:</td>
            <td colspan="3">
                <div class="inline">
                    <label><input type="radio" name="alergi" value="Tidak" onclick="toggleArea('alergi')" <?= chk($row['alergi'] ?? 'Tidak','Tidak') ?>> Tidak</label>
                    <label><input type="radio" name="alergi" value="Ya" onclick="toggleArea('alergi')" <?= chk($row['alergi'] ?? 'Tidak','Ya') ?>> Ya, Sebutkan</label>
                </div>
                <div id="wrap_alergi_area" class="sub" style="margin-top:4px;">
                    <input type="text" name="alergi_ket" value="<?= esc($row['alergi_ket'] ?? '') ?>">
                </div>
            </td>
        </tr>

        <tr>
            <td class="lbl">Produk Perawatan Terakhir Digunakan</td><td class="colon">:</td>
            <td colspan="3"><input type="text" name="produk_perawatan_terakhir" value="<?= esc($row['produk_perawatan_terakhir'] ?? '') ?>"></td>
        </tr>
        <tr>
            <td class="lbl">Keluhan</td><td class="colon">:</td>
            <td colspan="3"><textarea name="keluhan" rows="2"><?= esc($row['keluhan'] ?? '') ?></textarea></td>
        </tr>
        <tr>
            <td class="lbl">Riwayat Penyakit Dahulu</td><td class="colon">:</td>
            <td colspan="3"><textarea name="riwayat_penyakit_dahulu" rows="2"><?= esc($row['riwayat_penyakit_dahulu'] ?? '') ?></textarea></td>
        </tr>
        <tr>
            <td class="lbl">Riwayat Penyakit Keluarga</td><td class="colon">:</td>
            <td colspan="3"><textarea name="riwayat_penyakit_keluarga" rows="2"><?= esc($row['riwayat_penyakit_keluarga'] ?? '') ?></textarea></td>
        </tr>
    </table>

    <div class="section-title">RENCANA TREATMENT FACE MASSAGE</div>
    <table>
        <tr>
            <td class="lbl">Fokus Pijatan Pada Area</td><td class="colon">:</td>
            <td colspan="3"><textarea name="fokus_pijatan_area" rows="2"><?= esc($row['fokus_pijatan_area'] ?? '') ?></textarea></td>
        </tr>
        <tr>
            <td class="lbl">Tingkat Pijatan</td><td class="colon">:</td>
            <td colspan="3">
                <div class="inline">
                <?php foreach (['Tekanan Ringan','Tekanan Sedang','Tekanan Kuat'] as $t): ?>
                    <label><input type="radio" name="tingkat_pijatan" value="<?= $t ?>" <?= chk($row['tingkat_pijatan'] ?? '', $t) ?>> <?= $t ?></label>
                <?php endforeach; ?>
                </div>
            </td>
        </tr>
    </table>

    <div class="face-wrap">
        <b>Area Pijatan &mdash; klik langsung pada gambar untuk menandai titik</b>
        <div>
            <svg id="faceSvg" width="260" height="320" viewBox="0 0 260 320" xmlns="http://www.w3.org/2000/svg" onclick="tandaiTitik(event)">
                <!-- diagram wajah sederhana (skematik, bukan foto) -->
                <ellipse cx="130" cy="165" rx="95" ry="130" fill="#fff" stroke="#333" stroke-width="1.5"/>
                <path d="M40 150 Q30 100 60 70" fill="none" stroke="#333" stroke-width="1.2"/>
                <path d="M220 150 Q230 100 200 70" fill="none" stroke="#333" stroke-width="1.2"/>
                <ellipse cx="90" cy="140" rx="14" ry="7" fill="none" stroke="#333"/>
                <ellipse cx="170" cy="140" rx="14" ry="7" fill="none" stroke="#333"/>
                <circle cx="90" cy="140" r="3" fill="#333"/>
                <circle cx="170" cy="140" r="3" fill="#333"/>
                <path d="M85 115 Q90 110 100 113" fill="none" stroke="#333"/>
                <path d="M160 113 Q170 110 175 115" fill="none" stroke="#333"/>
                <path d="M130 145 L122 195 Q130 200 138 195 Z" fill="none" stroke="#333"/>
                <path d="M105 225 Q130 240 155 225" fill="none" stroke="#333" stroke-width="1.5"/>
                <g id="markerLayer"></g>
            </svg>
        </div>
        <div class="hint">Klik pada area wajah untuk menambah titik pijatan &bull; klik titik merah untuk menghapusnya.</div>
        <div id="titikListView" class="titik-list"></div>
        <div id="titikInputs"></div>
    </div>

    <div class="section-title">PERSETUJUAN &amp; TANDA TANGAN DIGITAL</div>
    <table>
        <tr>
            <td colspan="4">
                <label><input type="checkbox" name="persetujuan_pasien" <?= (($row['persetujuan_pasien'] ?? 'Tidak')=='Ya')?'checked':'' ?> required>
                Dengan ini saya telah mengisi formulir dengan benar dan dapat dipertanggungjawabkan.</label>
            </td>
        </tr>
        <tr>
            <td class="lbl">Nama Penandatangan</td><td class="colon">:</td>
            <td colspan="2"><input type="text" name="nama_ttd_pasien" value="<?= esc($row['nama_ttd_pasien'] ?? '') ?>" required></td>
        </tr>
    </table>

    <!-- SIGNATURE PAD -->
    <div style="margin-top:12px;">
        <div style="font-weight:700;color:var(--color-primary,#8B1538);margin-bottom:6px;">Tanda Tangan Digital Pasien / Wali:</div>
        <div style="position:relative;display:inline-block;border:2px solid #ccc;border-radius:6px;background:#fafafa;cursor:crosshair;touch-action:none;">
            <canvas id="ttdCanvas" width="400" height="150"
                    style="display:block;border-radius:4px;"></canvas>
        </div>
        <?php $ttdSaved = $row['ttd_pasien'] ?? ''; ?>
        <?php if ($ttdSaved): ?>
        <div style="margin-top:6px;">
            <div style="font-size:12px;color:#666;margin-bottom:4px;">TTD tersimpan sebelumnya:</div>
            <img id="ttdPreview" src="<?= htmlspecialchars($ttdSaved) ?>" alt="TTD Tersimpan"
                 style="border:1px dashed #ccc;border-radius:4px;max-width:400px;max-height:150px;background:#fff;display:block;">
        </div>
        <?php endif; ?>
        <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" id="btnHapusTtd" onclick="hapusTtd()" style="padding:5px 14px;border:1px solid #ccc;border-radius:4px;background:#fff;cursor:pointer;font-size:13px;">🗑 Hapus &amp; Gambar Ulang</button>
            <button type="button" onclick="simpanTtd()" style="padding:5px 14px;border:1px solid #8B1538;border-radius:4px;background:#8B1538;color:#fff;cursor:pointer;font-size:13px;">✔ Gunakan TTD Ini</button>
        </div>
        <input type="hidden" name="ttd_pasien" id="ttdInput" value="<?= htmlspecialchars($ttdSaved) ?>">
    </div>

    <button type="submit" class="btn-simpan">Simpan Penilaian</button>
    </form>
    <?php endif; ?>
</div>

<script>
// tampilkan/sembunyikan kotak "area" ketika radio "Ada"/"Ya" dipilih
function toggleArea(key){
    // fungsi generik dipanggil dari onclick tiap radio, cukup no-op di sini
    // (tampil selalu, agar user tetap bisa mengisi keterangan meski sudah ada nilainya)
}

// ---- penanda titik pijatan langsung di gambar wajah ----
var svg = document.getElementById('faceSvg');
var layer = document.getElementById('markerLayer');
var titik = <?= json_encode($titikTersimpan) ?>; // preload data lama saat mode edit

function renderTitik(){
    layer.innerHTML = '';
    var listHtml = '';
    titik.forEach(function(t, idx){
        var cx = t.pos_x / 100 * 260;
        var cy = t.pos_y / 100 * 320;
        var c = document.createElementNS('http://www.w3.org/2000/svg','circle');
        c.setAttribute('cx', cx); c.setAttribute('cy', cy); c.setAttribute('r', 6);
        c.setAttribute('class','marker');
        c.onclick = function(e){ e.stopPropagation(); hapusTitik(idx); };
        layer.appendChild(c);
        listHtml += '<div>Titik ' + (idx+1) + ': x=' + parseFloat(t.pos_x).toFixed(1) + '%, y=' + parseFloat(t.pos_y).toFixed(1) + '%</div>';
    });
    document.getElementById('titikListView').innerHTML = listHtml;
    renderHiddenInputs();
}

function renderHiddenInputs(){
    var wrap = document.getElementById('titikInputs');
    wrap.innerHTML = '';
    titik.forEach(function(t){
        wrap.innerHTML += '<input type="hidden" name="titik_x[]" value="' + t.pos_x + '">' +
                           '<input type="hidden" name="titik_y[]" value="' + t.pos_y + '">' +
                           '<input type="hidden" name="titik_ket[]" value="' + (t.keterangan||'') + '">';
    });
}

function tandaiTitik(e){
    var rect = svg.getBoundingClientRect();
    var x = (e.clientX - rect.left) / rect.width * 100;
    var y = (e.clientY - rect.top) / rect.height * 100;
    titik.push({pos_x: x.toFixed(2), pos_y: y.toFixed(2), keterangan: ''});
    renderTitik();
}

function hapusTitik(idx){
    titik.splice(idx,1);
    renderTitik();
}

renderTitik();

// ======================================================
// SIGNATURE PAD — tanda tangan digital pasien
// ======================================================
(function(){
    var canvas = document.getElementById('ttdCanvas');
    if (!canvas) return;

    var ctx    = canvas.getContext('2d');
    var input  = document.getElementById('ttdInput');
    var drawing = false;

    // Isi canvas dengan background putih
    function fillBg(){
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        // Garis panduan
        ctx.strokeStyle = '#e0e0e0';
        ctx.lineWidth = 1;
        ctx.setLineDash([4,4]);
        ctx.beginPath();
        ctx.moveTo(20, canvas.height - 30);
        ctx.lineTo(canvas.width - 20, canvas.height - 30);
        ctx.stroke();
        ctx.setLineDash([]);
        ctx.fillStyle = '#bbb';
        ctx.font = '11px sans-serif';
        ctx.fillText('Tanda Tangan', 20, canvas.height - 14);
    }

    fillBg();

    // Preload TTD tersimpan ke canvas jika ada
    var savedVal = input ? input.value : '';
    if (savedVal && savedVal.indexOf('data:image') === 0) {
        var imgLoad = new Image();
        imgLoad.onload = function(){ ctx.drawImage(imgLoad, 0, 0); };
        imgLoad.src = savedVal;
    }

    function getPos(e){
        var r = canvas.getBoundingClientRect();
        var src = e.touches ? e.touches[0] : e;
        return { x: src.clientX - r.left, y: src.clientY - r.top };
    }

    function startDraw(e){ e.preventDefault(); drawing = true; var p=getPos(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); }
    function moveDraw(e){ if(!drawing)return; e.preventDefault(); var p=getPos(e); ctx.lineWidth=2; ctx.lineCap='round'; ctx.strokeStyle='#111'; ctx.lineTo(p.x,p.y); ctx.stroke(); ctx.beginPath(); ctx.moveTo(p.x,p.y); }
    function endDraw(e){ drawing=false; }

    canvas.addEventListener('mousedown',  startDraw);
    canvas.addEventListener('mousemove',  moveDraw);
    canvas.addEventListener('mouseup',    endDraw);
    canvas.addEventListener('mouseleave', endDraw);
    canvas.addEventListener('touchstart', startDraw, {passive:false});
    canvas.addEventListener('touchmove',  moveDraw,  {passive:false});
    canvas.addEventListener('touchend',   endDraw);

    // Fungsi global yang dipanggil tombol
    window.simpanTtd = function(){
        if (input) {
            input.value = canvas.toDataURL('image/png');
            // Update preview jika ada
            var prev = document.getElementById('ttdPreview');
            if (prev) { prev.src = input.value; }
            alert('Tanda tangan telah dikunci. Klik "Simpan Penilaian" untuk menyimpan ke database.');
        }
    };

    window.hapusTtd = function(){
        fillBg();
        if (input) input.value = '';
        var prev = document.getElementById('ttdPreview');
        if (prev) { prev.style.display = 'none'; }
    };
})();
</script>
</body>
</html>
