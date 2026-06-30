<?php
/**
 * pasien/daftar-baru.php
 * -----------------------------------------------------------------
 * Form pendaftaran pasien baru — mengikuti seluruh field form Java
 * SIMRS Khanza (DlgPasien.java), termasuk alamat lengkap, data
 * penanggungjawab, suku bangsa, bahasa, cacat fisik, instansi, NIP.
 * Checkbox "Alamat PJ = Alamat Pasien" untuk copy otomatis.
 * No. RM ditampilkan (preview nomor yang akan digenerate).
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/nomor.php';

wajibLogin();

$pdo = getKoneksi();

// Default aman (terverifikasi tidak melanggar FK — lihat KEPUTUSAN-TEKNIS.md Bagian 5)
$DEF_INT = 1;
$DEF_STR = '-';

// Ambil semua data referensi untuk dropdown
$refPropinsi  = $pdo->query("SELECT kd_prop, nm_prop FROM propinsi ORDER BY nm_prop ASC")->fetchAll();
$refKabupaten = $pdo->query("SELECT kd_kab, nm_kab FROM kabupaten ORDER BY nm_kab ASC")->fetchAll();
$refKecamatan = $pdo->query("SELECT kd_kec, nm_kec FROM kecamatan ORDER BY nm_kec ASC")->fetchAll();
$refKelurahan = $pdo->query("SELECT kd_kel, nm_kel FROM kelurahan ORDER BY nm_kel ASC")->fetchAll();
$refSuku      = $pdo->query("SELECT id, nama_suku_bangsa FROM suku_bangsa ORDER BY nama_suku_bangsa ASC")->fetchAll();
$refBahasa    = $pdo->query("SELECT id, nama_bahasa FROM bahasa_pasien ORDER BY nama_bahasa ASC")->fetchAll();
$refCacat     = $pdo->query("SELECT id, nama_cacat FROM cacat_fisik ORDER BY nama_cacat ASC")->fetchAll();
$refPenjab    = $pdo->query("SELECT kd_pj, png_jawab FROM penjab WHERE status='1' ORDER BY png_jawab ASC")->fetchAll();

// Preview No. RM berikutnya (read-only, untuk ditampilkan saja — generate ulang saat POST)
try {
    $previewNoRm = generateNoRkmMedis();
} catch (Throwable $e) {
    $previewNoRm = 'Error: ' . $e->getMessage();
}

$error  = '';
$sukses = false;
$noRkmMedisBaru = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- ambil semua field ---
    $nama       = trim($_POST['nm_pasien'] ?? '');
    $jk         = $_POST['jk'] ?? '';
    $tglLahir   = $_POST['tgl_lahir'] ?? '';
    $tmpLahir   = trim($_POST['tmp_lahir'] ?? '');
    $namaIbu    = trim($_POST['nm_ibu'] ?? '') ?: '-';
    $golDarah   = $_POST['gol_darah'] ?? '-';
    $sttsNikah  = $_POST['stts_nikah'] ?? 'BELUM MENIKAH';
    $agama      = $_POST['agama'] ?? '-';
    $pnd        = $_POST['pnd'] ?? '-';
    $pekerjaan  = trim($_POST['pekerjaan'] ?? '');
    $noKtp      = trim($_POST['no_ktp'] ?? '');
    $noTlp      = trim($_POST['no_tlp'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $nip        = trim($_POST['nip'] ?? '');
    $alamat     = trim($_POST['alamat'] ?? '');
    $kdProp     = (int)($_POST['kd_prop'] ?? $DEF_INT);
    $kdKab      = (int)($_POST['kd_kab']  ?? $DEF_INT);
    $kdKec      = (int)($_POST['kd_kec']  ?? $DEF_INT);
    $kdKel      = (int)($_POST['kd_kel']  ?? $DEF_INT);
    $kdSuku     = (int)($_POST['suku_bangsa'] ?? $DEF_INT);
    $kdBahasa   = (int)($_POST['bahasa_pasien'] ?? $DEF_INT);
    $kdCacat    = (int)($_POST['cacat_fisik'] ?? $DEF_INT);
    $instansi   = trim($_POST['perusahaan_pasien'] ?? $DEF_STR);

    // Penanggungjawab
    $kdPj       = $_POST['kd_pj'] ?? 'A09';
    $noPeserta  = trim($_POST['no_peserta'] ?? '');
    $keluarga   = $_POST['keluarga'] ?? 'DIRI SENDIRI';
    $namaKel    = trim($_POST['namakeluarga'] ?? '') ?: $nama;
    $pkjPj      = trim($_POST['pekerjaanpj'] ?? '');
    $alamatPj   = trim($_POST['alamatpj'] ?? '');
    $propinsiPj = trim($_POST['propinsipj'] ?? '');
    $kabPj      = trim($_POST['kabupatenpj'] ?? '');
    $kecPj      = trim($_POST['kecamatanpj'] ?? '');
    $kelPj      = trim($_POST['kelurahanpj'] ?? '');

    if ($nama === '' || $jk === '' || $tglLahir === '') {
        $error = 'Nama pasien, jenis kelamin, dan tanggal lahir wajib diisi.';
    } else {
        try {
            $lahir   = new DateTime($tglLahir);
            $now     = new DateTime();
            $diff    = $now->diff($lahir);
            $umurStr = "{$diff->y} Th {$diff->m} Bl {$diff->d} Hr";

            $noRkmMedisBaru = generateNoRkmMedis();
            $tglDaftar      = date('Y-m-d');

            $stmt = $pdo->prepare(
                "INSERT INTO pasien (
                    no_rkm_medis, nm_pasien, no_ktp, jk, tmp_lahir, tgl_lahir, nm_ibu,
                    alamat, gol_darah, pekerjaan, stts_nikah, agama, tgl_daftar, no_tlp,
                    umur, pnd, keluarga, namakeluarga, kd_pj, no_peserta,
                    kd_kel, kd_kec, kd_kab, pekerjaanpj, alamatpj, kelurahanpj, kecamatanpj,
                    kabupatenpj, perusahaan_pasien, suku_bangsa, bahasa_pasien, cacat_fisik,
                    email, nip, kd_prop, propinsipj
                ) VALUES (
                    ?,?,?,?,?,?,?,
                    ?,?,?,?,?,?,?,
                    ?,?,?,?,?,?,
                    ?,?,?,?,?,?,?,
                    ?,?,?,?,?,
                    ?,?,?,?
                )"
            );
            $stmt->execute([
                $noRkmMedisBaru, $nama, $noKtp, $jk, $tmpLahir, $tglLahir, $namaIbu,
                $alamat, $golDarah, $pekerjaan, $sttsNikah, $agama, $tglDaftar, $noTlp,
                $umurStr, $pnd, $keluarga, $namaKel, $kdPj, $noPeserta,
                $kdKel, $kdKec, $kdKab, $pkjPj, $alamatPj, $kelPj, $kecPj,
                $kabPj, $instansi, $kdSuku, $kdBahasa, $kdCacat,
                $email, $nip, $kdProp, $propinsiPj,
            ]);

            $sukses = true;
        } catch (Throwable $e) {
            error_log('[daftar-baru.php] Error: ' . $e->getMessage());
            $error = 'Gagal menyimpan: ' . $e->getMessage();
        }
    }
}

$halamanAktif = 'pasien';
$judulHalaman = 'Pendaftaran Pasien Baru';
$baseAsset    = '../';
require __DIR__ . '/../lib/layout_header.php';

// Helper: buat <option> dari array referensi, dengan selected jika nilai cocok
function opts(array $data, string $keyCol, string $labelCol, $selected, bool $isInt = false): string {
    $html = '';
    foreach ($data as $row) {
        $val  = htmlspecialchars((string)$row[$keyCol]);
        $lbl  = htmlspecialchars($row[$labelCol]);
        $sel  = $isInt ? ((int)$row[$keyCol] === (int)$selected) : ($row[$keyCol] === $selected);
        $html .= "<option value=\"{$val}\"" . ($sel ? ' selected' : '') . ">{$lbl}</option>";
    }
    return $html;
}

$v = $_POST; // untuk repopulate setelah error
?>
<style>
.section-title {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--color-primary);
    margin: 20px 0 10px;
    padding-bottom: 6px;
    border-bottom: 1.5px solid var(--color-border);
}
.form-grid-3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; }
.form-grid-2 { display: grid; grid-template-columns: repeat(2,1fr); gap: 12px; }
.form-grid-4 { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; }
.no-rm-badge {
    display: inline-block;
    background: #F4EBED;
    border: 1.5px solid var(--color-primary);
    color: var(--color-primary);
    font-weight: 700;
    font-size: 15px;
    padding: 7px 16px;
    border-radius: 8px;
    letter-spacing: 0.05em;
    margin-bottom: 16px;
}
</style>

<?php if ($sukses): ?>
<div class="card">
    <div class="alert alert-success">✔ Pasien berhasil didaftarkan.</div>
    <p>No. Rekam Medis yang diberikan: <span class="no-rm-badge"><?= htmlspecialchars($noRkmMedisBaru) ?></span></p>
    <a href="registrasi.php?no_rkm_medis=<?= urlencode($noRkmMedisBaru) ?>" class="btn btn-primary">Lanjut ke Registrasi Kunjungan →</a>
    <a href="daftar-baru.php" class="btn btn-outline" style="margin-left:8px;">Pasien Baru Lainnya</a>
</div>
<?php else: ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" id="formPasien">
<!-- ===== KARTU 1: IDENTITAS PASIEN ===== -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
        <p class="card-title" style="margin:0;">Identitas Pasien</p>
        <div>
            <span class="text-muted" style="font-size:12px;">No. Rekam Medis (otomatis):</span>
            <span class="no-rm-badge" style="margin-bottom:0;margin-left:6px;" id="previewNoRm">
                <?= htmlspecialchars($previewNoRm) ?>
            </span>
        </div>
    </div>

    <p class="section-title">Data Dasar</p>
    <div class="form-grid-2">
        <div>
            <label for="nm_pasien">Nama Pasien *</label>
            <input type="text" id="nm_pasien" name="nm_pasien" required
                   value="<?= htmlspecialchars($v['nm_pasien'] ?? '') ?>">
        </div>
        <div class="form-grid-2" style="gap:8px;">
            <div>
                <label for="jk">Jenis Kelamin *</label>
                <select id="jk" name="jk" required>
                    <option value="">-- Pilih --</option>
                    <option value="P" <?= ($v['jk']??'')=='P'?'selected':'' ?>>Perempuan</option>
                    <option value="L" <?= ($v['jk']??'')=='L'?'selected':'' ?>>Laki-laki</option>
                </select>
            </div>
            <div>
                <label for="gol_darah">Gol. Darah</label>
                <select id="gol_darah" name="gol_darah">
                    <?php foreach(['-','A','B','O','AB'] as $g): ?>
                        <option value="<?=$g?>" <?= ($v['gol_darah']??'-')===$g?'selected':'' ?>><?=$g?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="form-grid-3">
        <div>
            <label for="tmp_lahir">Tempat Lahir</label>
            <input type="text" id="tmp_lahir" name="tmp_lahir"
                   value="<?= htmlspecialchars($v['tmp_lahir'] ?? '') ?>">
        </div>
        <div>
            <label for="tgl_lahir">Tanggal Lahir *</label>
            <input type="date" id="tgl_lahir" name="tgl_lahir" required
                   value="<?= htmlspecialchars($v['tgl_lahir'] ?? '') ?>">
        </div>
        <div>
            <label for="stts_nikah">Status Nikah</label>
            <select id="stts_nikah" name="stts_nikah">
                <?php foreach(['BELUM MENIKAH','MENIKAH','JANDA','DUDHA','JOMBLO'] as $s): ?>
                    <option value="<?=$s?>" <?= ($v['stts_nikah']??'BELUM MENIKAH')===$s?'selected':'' ?>><?=$s?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-grid-3">
        <div>
            <label for="agama">Agama</label>
            <select id="agama" name="agama">
                <?php foreach(['Islam','Kristen','Katolik','Hindu','Budha','Konghucu','-'] as $a): ?>
                    <option value="<?=$a?>" <?= ($v['agama']??'Islam')===$a?'selected':'' ?>><?=$a?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="pnd">Pendidikan</label>
            <select id="pnd" name="pnd">
                <?php foreach(['-','TS','TK','SD','SMP','SMA','SLTA/SEDERAJAT','D1','D2','D3','D4','S1','S2','S3'] as $p): ?>
                    <option value="<?=$p?>" <?= ($v['pnd']??'-')===$p?'selected':'' ?>><?=$p?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="nm_ibu">Nama Ibu Kandung</label>
            <input type="text" id="nm_ibu" name="nm_ibu"
                   value="<?= htmlspecialchars($v['nm_ibu'] ?? '') ?>">
        </div>
    </div>

    <div class="form-grid-3">
        <div>
            <label for="pekerjaan">Pekerjaan</label>
            <input type="text" id="pekerjaan" name="pekerjaan"
                   value="<?= htmlspecialchars($v['pekerjaan'] ?? '') ?>">
        </div>
        <div>
            <label for="no_ktp">No. KTP / SIM</label>
            <input type="text" id="no_ktp" name="no_ktp" maxlength="20"
                   value="<?= htmlspecialchars($v['no_ktp'] ?? '') ?>">
        </div>
        <div>
            <label for="no_tlp">No. Telp / HP</label>
            <input type="text" id="no_tlp" name="no_tlp"
                   value="<?= htmlspecialchars($v['no_tlp'] ?? '') ?>">
        </div>
    </div>

    <div class="form-grid-2">
        <div>
            <label for="email">Email</label>
            <input type="email" id="email" name="email"
                   value="<?= htmlspecialchars($v['email'] ?? '') ?>">
        </div>
        <div>
            <label for="nip">NIP / NRP</label>
            <input type="text" id="nip" name="nip"
                   value="<?= htmlspecialchars($v['nip'] ?? '') ?>">
        </div>
    </div>

    <p class="section-title">Alamat Pasien</p>
    <div>
        <label for="alamat">Alamat</label>
        <textarea id="alamat" name="alamat" rows="2"><?= htmlspecialchars($v['alamat'] ?? '') ?></textarea>
    </div>
    <div class="form-grid-4">
        <div>
            <label for="kd_prop">Provinsi</label>
            <select id="kd_prop" name="kd_prop">
                <?= opts($refPropinsi,'kd_prop','nm_prop',$v['kd_prop']??$DEF_INT,true) ?>
            </select>
        </div>
        <div>
            <label for="kd_kab">Kabupaten / Kota</label>
            <select id="kd_kab" name="kd_kab">
                <?= opts($refKabupaten,'kd_kab','nm_kab',$v['kd_kab']??$DEF_INT,true) ?>
            </select>
        </div>
        <div>
            <label for="kd_kec">Kecamatan</label>
            <select id="kd_kec" name="kd_kec">
                <?= opts($refKecamatan,'kd_kec','nm_kec',$v['kd_kec']??$DEF_INT,true) ?>
            </select>
        </div>
        <div>
            <label for="kd_kel">Kelurahan / Desa</label>
            <select id="kd_kel" name="kd_kel">
                <?= opts($refKelurahan,'kd_kel','nm_kel',$v['kd_kel']??$DEF_INT,true) ?>
            </select>
        </div>
    </div>

    <p class="section-title">Atribut Tambahan</p>
    <div class="form-grid-3">
        <div>
            <label for="suku_bangsa">Suku Bangsa</label>
            <select id="suku_bangsa" name="suku_bangsa">
                <?= opts($refSuku,'id','nama_suku_bangsa',$v['suku_bangsa']??$DEF_INT,true) ?>
            </select>
        </div>
        <div>
            <label for="bahasa_pasien">Bahasa yang Dipakai</label>
            <select id="bahasa_pasien" name="bahasa_pasien">
                <?= opts($refBahasa,'id','nama_bahasa',$v['bahasa_pasien']??$DEF_INT,true) ?>
            </select>
        </div>
        <div>
            <label for="cacat_fisik">Cacat Fisik</label>
            <select id="cacat_fisik" name="cacat_fisik">
                <?= opts($refCacat,'id','nama_cacat',$v['cacat_fisik']??$DEF_INT,true) ?>
            </select>
        </div>
    </div>
    <div class="form-grid-2">
        <div>
            <label for="perusahaan_pasien">Instansi Pasien</label>
            <input type="text" id="perusahaan_pasien" name="perusahaan_pasien"
                   value="<?= htmlspecialchars($v['perusahaan_pasien'] ?? $DEF_STR) ?>">
        </div>
    </div>
</div>

<!-- ===== KARTU 2: DATA PENANGGUNGJAWAB & ASURANSI ===== -->
<div class="card">
    <p class="card-title">Penanggungjawab & Asuransi</p>

    <div class="form-grid-3">
        <div>
            <label for="keluarga">Hubungan dengan Pasien</label>
            <select id="keluarga" name="keluarga">
                <?php foreach(['DIRI SENDIRI','AYAH','IBU','SUAMI','ISTRI','ANAK','SAUDARA','LAIN-LAIN'] as $k): ?>
                    <option value="<?=$k?>" <?= ($v['keluarga']??'DIRI SENDIRI')===$k?'selected':'' ?>><?=$k?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="namakeluarga">Nama Penanggungjawab</label>
            <input type="text" id="namakeluarga" name="namakeluarga"
                   placeholder="Kosongkan = sama dengan nama pasien"
                   value="<?= htmlspecialchars($v['namakeluarga'] ?? '') ?>">
        </div>
        <div>
            <label for="pekerjaanpj">Pekerjaan P.J.</label>
            <input type="text" id="pekerjaanpj" name="pekerjaanpj"
                   value="<?= htmlspecialchars($v['pekerjaanpj'] ?? '') ?>">
        </div>
    </div>

    <div class="form-grid-2">
        <div>
            <label for="kd_pj">Askes / Asuransi</label>
            <select id="kd_pj" name="kd_pj">
                <?= opts($refPenjab,'kd_pj','png_jawab',$v['kd_pj']??'A09') ?>
            </select>
        </div>
        <div>
            <label for="no_peserta">No. Peserta</label>
            <input type="text" id="no_peserta" name="no_peserta"
                   value="<?= htmlspecialchars($v['no_peserta'] ?? '') ?>">
        </div>
    </div>

    <p class="section-title" style="margin-top:12px;">
        Alamat Penanggungjawab
        <label style="font-weight:400;font-size:12px;margin-left:12px;cursor:pointer;color:var(--color-text);">
            <input type="checkbox" id="samaDenganPasien" style="width:auto;margin:0 4px 0 0;">
            Sama dengan alamat pasien
        </label>
    </p>
    <div>
        <label for="alamatpj">Alamat P.J.</label>
        <textarea id="alamatpj" name="alamatpj" rows="2"><?= htmlspecialchars($v['alamatpj'] ?? '') ?></textarea>
    </div>
    <div class="form-grid-4">
        <div>
            <label for="propinsipj">Provinsi P.J.</label>
            <input type="text" id="propinsipj" name="propinsipj"
                   value="<?= htmlspecialchars($v['propinsipj'] ?? '') ?>">
        </div>
        <div>
            <label for="kabupatenpj">Kabupaten / Kota P.J.</label>
            <input type="text" id="kabupatenpj" name="kabupatenpj"
                   value="<?= htmlspecialchars($v['kabupatenpj'] ?? '') ?>">
        </div>
        <div>
            <label for="kecamatanpj">Kecamatan P.J.</label>
            <input type="text" id="kecamatanpj" name="kecamatanpj"
                   value="<?= htmlspecialchars($v['kecamatanpj'] ?? '') ?>">
        </div>
        <div>
            <label for="kelurahanpj">Kelurahan P.J.</label>
            <input type="text" id="kelurahanpj" name="kelurahanpj"
                   value="<?= htmlspecialchars($v['kelurahanpj'] ?? '') ?>">
        </div>
    </div>
</div>

<!-- ===== TOMBOL AKSI ===== -->
<div class="card" style="padding:16px 24px;display:flex;gap:10px;align-items:center;">
    <button type="submit" class="btn btn-primary">Simpan Pasien Baru</button>
    <a href="cari.php" class="btn btn-outline">Batal</a>
</div>
</form>

<script>
// Checkbox "Sama dengan alamat pasien" — copy alamat pasien ke kolom PJ
document.getElementById('samaDenganPasien').addEventListener('change', function() {
    if (this.checked) {
        // Ambil nilai dari kolom alamat pasien
        document.getElementById('alamatpj').value    = document.getElementById('alamat').value;
        document.getElementById('propinsipj').value  = document.getElementById('kd_prop').options[document.getElementById('kd_prop').selectedIndex]?.text ?? '';
        document.getElementById('kabupatenpj').value = document.getElementById('kd_kab').options[document.getElementById('kd_kab').selectedIndex]?.text ?? '';
        document.getElementById('kecamatanpj').value = document.getElementById('kd_kec').options[document.getElementById('kd_kec').selectedIndex]?.text ?? '';
        document.getElementById('kelurahanpj').value = document.getElementById('kd_kel').options[document.getElementById('kd_kel').selectedIndex]?.text ?? '';
    } else {
        document.getElementById('alamatpj').value    = '';
        document.getElementById('propinsipj').value  = '';
        document.getElementById('kabupatenpj').value = '';
        document.getElementById('kecamatanpj').value = '';
        document.getElementById('kelurahanpj').value = '';
    }
});

// Jika keluarga dipilih "DIRI SENDIRI", auto-isi nama PJ = nama pasien
document.getElementById('keluarga').addEventListener('change', function() {
    if (this.value === 'DIRI SENDIRI') {
        document.getElementById('namakeluarga').value = document.getElementById('nm_pasien').value;
    }
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/../lib/layout_footer.php'; ?>
