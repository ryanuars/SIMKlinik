<?php
/**
 * asesmen/pilih.php
 * -----------------------------------------------------------------
 * Halaman menu asesmen per kunjungan.
 * Menampilkan status tiap sub-modul (sudah/belum diisi) dan link
 * masuk ke masing-masing form asesmen.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';

wajibLogin();

$pdo = getKoneksi();

$noRawat = trim($_GET['no_rawat'] ?? '');
if ($noRawat === '') {
    header('Location: ../pasien/cari.php');
    exit;
}

// Simpan no_rawat ke session agar sidebar (Tindakan/Resep/USG) bisa langsung menuju pasien ini
$_SESSION['last_no_rawat'] = $noRawat;

// Ambil data kunjungan + pasien
$stmt = $pdo->prepare(
    "SELECT r.no_rawat, r.tgl_registrasi, r.kd_poli, r.kd_dokter, r.stts, r.status_bayar,
            p.nm_pasien, p.no_rkm_medis, p.jk, p.tgl_lahir,
            pol.nm_poli, dok.nm_dokter
     FROM reg_periksa r
     JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
     LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
     LEFT JOIN dokter dok ON r.kd_dokter = dok.kd_dokter
     WHERE r.no_rawat = ?"
);
$stmt->execute([$noRawat]);
$kunjungan = $stmt->fetch();

if (!$kunjungan) {
    header('Location: ../pasien/cari.php');
    exit;
}

$sudahBayar = ($kunjungan['status_bayar'] === 'Sudah Bayar');

// Cek status tiap sub-modul (sudah diisi atau belum)
// SOAP — cek di pemeriksaan_ralan
$stmtSoap = $pdo->prepare("SELECT COUNT(*) FROM pemeriksaan_ralan WHERE no_rawat = ?");
$stmtSoap->execute([$noRawat]);
$adaSoap = (int)$stmtSoap->fetchColumn() > 0;

// Asesmen medis kebidanan
$stmtMedis = $pdo->prepare("SELECT COUNT(*) FROM penilaian_medis_ralan_kandungan WHERE no_rawat = ?");
$stmtMedis->execute([$noRawat]);
$adaMedis = (int)$stmtMedis->fetchColumn() > 0;

// Asesmen keperawatan kebidanan
$stmtKep = $pdo->prepare("SELECT COUNT(*) FROM penilaian_awal_keperawatan_kebidanan WHERE no_rawat = ?");
$stmtKep->execute([$noRawat]);
$adaKep = (int)$stmtKep->fetchColumn() > 0;

// Obstetri detail
$stmtObs = $pdo->prepare("SELECT COUNT(*) FROM pemeriksaan_obstetri_ralan WHERE no_rawat = ?");
$stmtObs->execute([$noRawat]);
$adaObs = (int)$stmtObs->fetchColumn() > 0;

// Ginekologi detail
$stmtGin = $pdo->prepare("SELECT COUNT(*) FROM pemeriksaan_ginekologi_ralan WHERE no_rawat = ?");
$stmtGin->execute([$noRawat]);
$adaGin = (int)$stmtGin->fetchColumn() > 0;

// Tindakan dokter
$stmtTind = $pdo->prepare("SELECT COUNT(*) FROM rawat_jl_dr WHERE no_rawat = ?");
$stmtTind->execute([$noRawat]);
$adaTindakan = (int)$stmtTind->fetchColumn() > 0;

// Resep dokter
$stmtRes = $pdo->prepare("SELECT COUNT(*) FROM resep_obat WHERE no_rawat = ?");
$stmtRes->execute([$noRawat]);
$adaResep = (int)$stmtRes->fetchColumn() > 0;

// USG Kandungan (Obstetri)
$stmtUsgKand = $pdo->prepare("SELECT COUNT(*) FROM hasil_pemeriksaan_usg WHERE no_rawat = ?");
$stmtUsgKand->execute([$noRawat]);
$adaUsgKandungan = (int)$stmtUsgKand->fetchColumn() > 0;

// USG Ginekologi
$stmtUsgGin = $pdo->prepare("SELECT COUNT(*) FROM hasil_pemeriksaan_usg_gynecologi WHERE no_rawat = ?");
$stmtUsgGin->execute([$noRawat]);
$adaUsgGinekologi = (int)$stmtUsgGin->fetchColumn() > 0;

// Asesmen Kecantikan (Treatment Wajah)
$stmtCantik = $pdo->prepare("SELECT COUNT(*) FROM penilaian_treatment_wajah WHERE no_rawat = ?");
$stmtCantik->execute([$noRawat]);
$adaKecantikan = (int)$stmtCantik->fetchColumn() > 0;

$halamanAktif = 'asesmen';
$judulHalaman = 'Menu Asesmen';
$baseAsset    = '../';
require __DIR__ . '/../lib/layout_header.php';
?>
<style>
.asesmen-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
    margin-top: 8px;
}
.asesmen-card {
    background: #fff;
    border: 1.5px solid var(--color-border);
    border-radius: 10px;
    padding: 20px 22px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    transition: border-color .18s, box-shadow .18s;
}
.asesmen-card:hover { border-color: var(--color-primary); box-shadow: 0 4px 16px rgba(139,21,56,0.10); }
.asesmen-card.done { border-color: #2F6B4F; }
.asesmen-card.coming-soon { opacity: 0.62; }
.asesmen-card-title {
    font-weight: 700;
    font-size: 14.5px;
    color: var(--color-text);
}
.asesmen-card-desc {
    font-size: 12.5px;
    color: var(--color-text-mute);
    flex: 1;
}
.badge-status {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 11.5px;
    font-weight: 600;
    letter-spacing: .03em;
}
.badge-done   { background:#E6F4EE; color:#2F6B4F; }
.badge-empty  { background:#FDF6E3; color:#B8762E; }
.badge-soon   { background:#F0F0F0; color:#888; }
</style>

<!-- Info Kunjungan -->
<div class="card" style="margin-bottom:15px;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
        <div>
            <p class="card-title" style="margin:0 0 6px;">Kunjungan: <code><?= htmlspecialchars($kunjungan['no_rawat']) ?></code></p>
            <p style="margin:0;font-size:13px;">
                <strong><?= htmlspecialchars($kunjungan['nm_pasien']) ?></strong>
                &nbsp;·&nbsp; No.RM: <code><?= htmlspecialchars($kunjungan['no_rkm_medis']) ?></code>
                &nbsp;·&nbsp; <?= date('d-m-Y', strtotime($kunjungan['tgl_registrasi'])) ?>
            </p>
            <p style="margin:4px 0 0;font-size:12.5px;color:var(--color-text-mute);">
                <?= htmlspecialchars($kunjungan['nm_poli'] ?? '-') ?> — <?= htmlspecialchars($kunjungan['nm_dokter'] ?? '-') ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <a href="index.php" class="btn btn-outline" style="font-size:12.5px;padding:6px 12px;text-decoration:none;">Daftar Pasien Asesmen</a>
            <a href="../pasien/riwayat.php?no_rawat=<?= urlencode($noRawat) ?>"
               class="btn btn-outline" style="font-size:12.5px;padding:6px 12px;text-decoration:none;">Riwayat Pasien</a>
            <a href="../dashboard.php" class="btn btn-outline" style="font-size:12.5px;padding:6px 12px;text-decoration:none;">Dashboard</a>
            <a href="../billing/tagihan.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-primary" style="font-size:12.5px;padding:6px 12px;text-decoration:none;">Billing</a>
        </div>
    </div>
</div>

<?php if ($sudahBayar): ?>
<div class="alert alert-danger" style="margin-bottom:20px; border-left: 5px solid #d32f2f;">
    <p style="margin:0 0 4px 0; font-weight:bold; font-size:14px;">⚠️ PASIEN SUDAH MELAKUKAN PEMBAYARAN</p>
    <p style="margin:0; font-size:12.5px; line-height:1.4;">
        Data rekam medis, tindakan, dan resep pasien ini telah <strong>dikunci (read-only)</strong> karena transaksi pembayaran telah selesai diproses di Kasir.
        Untuk melakukan perubahan data, hubungi petugas!
    </p>
</div>
<?php endif; ?>

<!-- Grid Menu Asesmen -->
<div class="asesmen-grid">

    <!-- SOAP / Vital Sign -->
    <div class="asesmen-card <?= $adaSoap ? 'done' : '' ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span class="asesmen-card-title">SOAP / Vital Sign</span>
            <span class="badge-status <?= $adaSoap ? 'badge-done' : 'badge-empty' ?>">
                <?= $adaSoap ? '✔ Terisi' : 'Belum' ?>
            </span>
        </div>
        <p class="asesmen-card-desc">Keluhan utama, TTV (TD, Nadi, Suhu, SpO₂), Subjektif, Objektif, Asesmen, Plan — tabel <code>pemeriksaan_ralan</code>.</p>
        <a href="soap.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-primary" style="font-size:13px;text-align:center;">
            <?= $adaSoap ? 'Lihat / Edit' : 'Isi SOAP' ?>
        </a>
    </div>

    <!-- Asesmen Medis Kebidanan -->
    <div class="asesmen-card <?= $adaMedis ? 'done' : '' ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span class="asesmen-card-title">Asesmen Medis Kebidanan</span>
            <span class="badge-status <?= $adaMedis ? 'badge-done' : 'badge-empty' ?>">
                <?= $adaMedis ? '✔ Terisi' : 'Belum' ?>
            </span>
        </div>
        <p class="asesmen-card-desc">Anamnesis obstetri-ginekologi: TFU, TBJ, HIS, DJJ, VT, diagnosis — tabel <code>penilaian_medis_ralan_kandungan</code>.</p>
        <a href="kebidanan-medis.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-primary" style="font-size:13px;text-align:center;">
            <?= $adaMedis ? 'Lihat / Edit' : 'Isi Asesmen Medis' ?>
        </a>
    </div>

    <!-- Asesmen Keperawatan Kebidanan -->
    <div class="asesmen-card <?= $adaKep ? 'done' : '' ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span class="asesmen-card-title">Asesmen Keperawatan</span>
            <span class="badge-status <?= $adaKep ? 'badge-done' : 'badge-empty' ?>">
                <?= $adaKep ? '✔ Terisi' : 'Belum' ?>
            </span>
        </div>
        <p class="asesmen-card-desc">Riwayat haid, HPHT, GPA, KB, skrining risiko, psikososial — tabel <code>penilaian_awal_keperawatan_kebidanan</code>.</p>
        <a href="kebidanan-keperawatan.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-primary" style="font-size:13px;text-align:center;">
            <?= $adaKep ? 'Lihat / Edit' : 'Isi Asesmen Keperawatan' ?>
        </a>
    </div>

    <!-- Obstetri Detail -->
    <div class="asesmen-card <?= $adaObs ? 'done' : '' ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span class="asesmen-card-title">Obstetri Detail</span>
            <span class="badge-status <?= $adaObs ? 'badge-done' : 'badge-empty' ?>">
                <?= $adaObs ? '✔ Terisi' : 'Belum' ?>
            </span>
        </div>
        <p class="asesmen-card-desc">Tinggi uteri, letak janin, DJJ, pembukaan, ketuban — tabel <code>pemeriksaan_obstetri_ralan</code>.</p>
        <a href="obstetri-detail.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-primary" style="font-size:13px;text-align:center;">
            <?= $adaObs ? 'Lihat / Edit' : 'Isi Obstetri Detail' ?>
        </a>
    </div>

    <!-- Ginekologi Detail -->
    <div class="asesmen-card <?= $adaGin ? 'done' : '' ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span class="asesmen-card-title">Ginekologi Detail</span>
            <span class="badge-status <?= $adaGin ? 'badge-done' : 'badge-empty' ?>">
                <?= $adaGin ? '✔ Terisi' : 'Belum' ?>
            </span>
        </div>
        <p class="asesmen-card-desc">Inspeksi, inspekulo, adnexa, cavum douglas — tabel <code>pemeriksaan_ginekologi_ralan</code>.</p>
        <a href="ginekologi-detail.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-primary" style="font-size:13px;text-align:center;">
            <?= $adaGin ? 'Lihat / Edit' : 'Isi Ginekologi Detail' ?>
        </a>
    </div>

    <!-- Tindakan Medis -->
    <div class="asesmen-card <?= $adaTindakan ? 'done' : '' ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span class="asesmen-card-title">Tindakan Medis</span>
            <span class="badge-status <?= $adaTindakan ? 'badge-done' : 'badge-empty' ?>">
                <?= $adaTindakan ? '✔ Ada Tindakan' : 'Belum' ?>
            </span>
        </div>
        <p class="asesmen-card-desc">Input tindakan dokter &amp; tindakan bersama perawat — tabel <code>rawat_jl_dr</code> &amp; <code>rawat_jl_drpr</code>.</p>
        <a href="../tindakan/input.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-primary" style="font-size:13px;text-align:center;">
            <?= $adaTindakan ? 'Lihat / Tambah' : 'Input Tindakan' ?>
        </a>
    </div>

    <!-- Resep Dokter -->
    <div class="asesmen-card <?= $adaResep ? 'done' : '' ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span class="asesmen-card-title">Resep Dokter</span>
            <span class="badge-status <?= $adaResep ? 'badge-done' : 'badge-empty' ?>">
                <?= $adaResep ? '✔ Ada Resep' : 'Belum' ?>
            </span>
        </div>
        <p class="asesmen-card-desc">Penulisan resep obat — tabel <code>resep_obat</code> + <code>resep_dokter</code>.</p>
        <a href="../resep/tulis.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-primary" style="font-size:13px;text-align:center;">
            <?= $adaResep ? 'Lihat / Tambah' : 'Tulis Resep' ?>
        </a>
    </div>

    <!-- USG Kandungan -->
    <div class="asesmen-card <?= $adaUsgKandungan ? 'done' : '' ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span class="asesmen-card-title">USG Kandungan</span>
            <span class="badge-status <?= $adaUsgKandungan ? 'badge-done' : 'badge-empty' ?>">
                <?= $adaUsgKandungan ? '✔ Terisi' : 'Belum' ?>
            </span>
        </div>
        <p class="asesmen-card-desc">Hasil pemeriksaan USG Obstetri / Kandungan — tabel <code>hasil_pemeriksaan_usg</code>.</p>
        <a href="../usg/kandungan.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-primary" style="font-size:13px;text-align:center;">
            <?= $adaUsgKandungan ? 'Lihat / Edit' : 'Isi USG Kandungan' ?>
        </a>
    </div>

    <!-- USG Ginekologi -->
    <div class="asesmen-card <?= $adaUsgGinekologi ? 'done' : '' ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span class="asesmen-card-title">USG Ginekologi</span>
            <span class="badge-status <?= $adaUsgGinekologi ? 'badge-done' : 'badge-empty' ?>">
                <?= $adaUsgGinekologi ? '✔ Terisi' : 'Belum' ?>
            </span>
        </div>
        <p class="asesmen-card-desc">Hasil pemeriksaan USG Ginekologi — tabel <code>hasil_pemeriksaan_usg_gynecologi</code>.</p>
        <a href="../usg/ginekologi.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-primary" style="font-size:13px;text-align:center;">
            <?= $adaUsgGinekologi ? 'Lihat / Edit' : 'Isi USG Ginekologi' ?>
        </a>
    </div>

    <!-- Asesmen Kecantikan -->
    <div class="asesmen-card <?= $adaKecantikan ? 'done' : '' ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span class="asesmen-card-title">Asesmen Kecantikan</span>
            <span class="badge-status <?= $adaKecantikan ? 'badge-done' : 'badge-empty' ?>">
                <?= $adaKecantikan ? '✔ Terisi' : 'Belum' ?>
            </span>
        </div>
        <p class="asesmen-card-desc">Penilaian awal wajah, analisis kulit (jenis, jerawat, keriput, sensitif, dll) &amp; titik rencana treatment.</p>
        <a href="kecantikan.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-primary" style="font-size:13px;text-align:center;">
            <?= $adaKecantikan ? 'Lihat / Edit' : 'Isi Asesmen' ?>
        </a>
    </div>

</div>

<?php require __DIR__ . '/../lib/layout_footer.php'; ?>
