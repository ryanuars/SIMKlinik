<?php
/**
 * pasien/riwayat.php
 * -----------------------------------------------------------------
 * Riwayat Kunjungan Pasien (Level 1: Accordion Timeline, Level 2: Kategori Buttons, Level 3: AJAX Modal)
 *
 * Parameter:
 *   ?no_rkm_medis=...   — lihat riwayat seluruh kunjungan pasien
 *   ?no_rawat=...       — auto-resolve no_rkm_medis dari satu kunjungan
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';

wajibLogin();
$pdo = getKoneksi();

if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$noRkm   = trim($_GET['no_rkm_medis'] ?? '');
$noRawat = trim($_GET['no_rawat'] ?? '');

// Resolve no_rkm_medis dari no_rawat bila diperlukan
if ($noRkm === '' && $noRawat !== '') {
    $st = $pdo->prepare("SELECT no_rkm_medis FROM reg_periksa WHERE no_rawat = ? LIMIT 1");
    $st->execute([$noRawat]);
    $noRkm = (string)($st->fetchColumn() ?: '');
}

if ($noRkm === '') {
    header('Location: cari.php');
    exit;
}

// Info Pasien
$stPas = $pdo->prepare(
    "SELECT p.no_rkm_medis, p.nm_pasien, p.tgl_lahir, p.jk, p.alamat
     FROM pasien p WHERE p.no_rkm_medis = ?"
);
$stPas->execute([$noRkm]);
$pasien = $stPas->fetch();
if (!$pasien) {
    header('Location: cari.php');
    exit;
}

// Hanya Query Daftar Kunjungan Pasien (Sangat Ringan & Cepat!)
$stKunj = $pdo->prepare(
    "SELECT r.no_rawat, r.tgl_registrasi, r.jam_reg, r.kd_poli, pol.nm_poli,
            r.kd_dokter, dok.nm_dokter, r.status_bayar
     FROM reg_periksa r
     LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
     LEFT JOIN dokter dok ON r.kd_dokter = dok.kd_dokter
     WHERE r.no_rkm_medis = ?
     ORDER BY r.tgl_registrasi DESC, r.jam_reg DESC"
);
$stKunj->execute([$noRkm]);
$daftarKunjungan = $stKunj->fetchAll();

$halamanAktif = 'riwayat';
$judulHalaman = 'Riwayat Pasien';
$baseAsset    = '../';
require __DIR__ . '/../lib/layout_header.php';
?>

<style>
/* ── Container & Info Card ─────────────────────────────────────── */
.rw-patient-card {
    background: #FFF;
    border: 1px solid var(--color-border);
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
}
.rw-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-top: 10px;
}
.rw-info-item label {
    display: block; font-size: 11px; font-weight: 700; text-transform: uppercase;
    color: var(--color-text-mute); margin-bottom: 2px;
}
.rw-info-item span {
    font-size: 13.5px; font-weight: 600; color: var(--color-text);
}

/* ── Timeline / Accordion ─────────────────────────────────────── */
.rw-timeline {
    position: relative;
    padding-left: 20px;
    border-left: 2px solid #EAE0E5;
    margin-left: 8px;
}
.rw-acc-card {
    background: #FFF;
    border: 1.5px solid var(--color-border);
    border-radius: 10px;
    margin-bottom: 14px;
    position: relative;
    transition: all 0.2s ease;
    box-shadow: 0 1px 4px rgba(0,0,0,0.02);
}
.rw-acc-card::before {
    content: '';
    position: absolute;
    left: -27px;
    top: 18px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--color-primary);
    border: 3px solid #FFF;
    box-shadow: 0 0 0 2px var(--color-primary);
}
.rw-acc-card.current-visit {
    border-color: var(--color-primary);
    background: #FFFDFE;
}
.rw-acc-header {
    padding: 14px 18px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    user-select: none;
    flex-wrap: wrap;
    gap: 10px;
}
.rw-acc-header:hover {
    background: #FFF9FC;
}
.rw-acc-title {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.rw-date-badge {
    font-size: 13px; font-weight: 700; color: #222;
}
.rw-poli-badge {
    background: #EBF5FF; color: #0066CC; font-weight: 600;
    font-size: 11.5px; padding: 3px 10px; border-radius: 12px;
}
.rw-dok-name {
    font-size: 12.5px; color: #555; font-weight: 500;
}

.rw-acc-body {
    display: none;
    padding: 14px 18px 18px;
    border-top: 1px dashed var(--color-border);
    background: #FAFAFA;
    border-radius: 0 0 8px 8px;
}
.rw-acc-card.open .rw-acc-body {
    display: block;
}
.rw-acc-chevron {
    transition: transform 0.2s ease;
    font-size: 12px; color: #888;
}
.rw-acc-card.open .rw-acc-chevron {
    transform: rotate(180deg);
}

/* ── Level 2: Category Buttons / Chips Grid ───────────────────── */
.rw-cat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 10px;
    margin-top: 6px;
}
.rw-cat-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: #FFF;
    border: 1.5px solid var(--color-border);
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--color-text);
    cursor: pointer;
    transition: all 0.15s ease;
    text-align: left;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}
.rw-cat-btn:hover {
    border-color: var(--color-primary);
    color: var(--color-primary);
    background: #FFF8FB;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(160,57,106,0.12);
}
.rw-cat-btn span.icon {
    font-size: 16px; flex-shrink: 0;
}

/* ── Level 3: Dynamic AJAX Modal CSS ───────────────────────────── */
.rw-modal-backdrop {
    display: none;
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(15, 10, 12, 0.55);
    backdrop-filter: blur(3px);
    z-index: 9999;
    align-items: center; justify-content: center;
    padding: 16px;
    animation: fadeIn 0.15s ease-out;
}
.rw-modal-backdrop.active {
    display: flex;
}
.rw-modal-dialog {
    background: #FFF;
    width: 100%;
    max-width: 820px;
    max-height: 88vh;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: slideUp 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes slideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.rw-modal-header {
    padding: 16px 20px;
    background: var(--color-primary);
    color: #FFF;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.rw-modal-title {
    font-size: 15px; font-weight: 700; margin: 0;
}
.rw-modal-close {
    background: rgba(255,255,255,0.2);
    border: none; color: #FFF; font-size: 18px; line-height: 1;
    width: 30px; height: 30px; border-radius: 50%;
    cursor: pointer; transition: background 0.15s;
    display: flex; align-items: center; justify-content: center;
}
.rw-modal-close:hover { background: rgba(255,255,255,0.4); }

.rw-modal-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
}

.rw-modal-footer {
    padding: 12px 20px;
    background: #F8F8F8;
    border-top: 1px solid var(--color-border);
    display: flex; justify-content: flex-end;
}

/* Modal Content Components */
.rw-detail-card {
    background: #FFF; border: 1px solid var(--color-border);
    border-radius: 8px; padding: 14px 16px;
}
.rw-detail-header {
    display: flex; justify-content: space-between; align-items: center;
    border-bottom: 1px solid var(--color-border);
    padding-bottom: 8px; margin-bottom: 12px;
}
.rw-vit-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 8px; margin-bottom: 14px;
}
.rw-vit-box {
    background: #FFF8FB; border: 1px solid #F0D3E1;
    border-radius: 6px; padding: 6px 8px; text-align: center;
}
.rw-vit-box label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; color: #888; margin-bottom: 2px; }
.rw-vit-box span { font-size: 12.5px; font-weight: 700; color: var(--color-primary); }

.rw-sub-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12.5px; }
.rw-sub-grid p { margin: 2px 0 0; color: #444; }

.rw-info-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.rw-info-table th { text-align: left; width: 35%; padding: 6px 8px; background: #FAF5F7; border-bottom: 1px solid #EAE0E5; color: #555; }
.rw-info-table td { padding: 6px 8px; border-bottom: 1px solid #EAE0E5; }

.rw-modal-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.rw-modal-table th { background: var(--color-primary); color: #FFF; padding: 7px 10px; text-align: left; font-size: 11.5px; }
.rw-modal-table td { padding: 7px 10px; border-bottom: 1px solid var(--color-border); }

.rw-empty-modal {
    text-align: center; color: var(--color-text-mute); padding: 30px 10px; font-size: 13px;
}

/* Spinner Loader */
.rw-loader {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 40px 0; color: var(--color-primary);
}
.rw-spinner {
    width: 36px; height: 36px;
    border: 3.5px solid #F0D3E1; border-top-color: var(--color-primary);
    border-radius: 50%; animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<!-- ─── HEADER PASIEN ──────────────────────────────────────────── -->
<div class="rw-patient-card">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
        <div>
            <h2 style="margin:0 0 4px; font-size:17px; color:var(--color-primary);">
                Riwayat Medis Pasien: <strong><?= e($pasien['nm_pasien']) ?></strong>
            </h2>
            <div class="rw-info-grid">
                <div class="rw-info-item"><label>No. Rekam Medis</label><span><code><?= e($pasien['no_rkm_medis']) ?></code></span></div>
                <div class="rw-info-item"><label>Tanggal Lahir</label><span><?= $pasien['tgl_lahir'] ? date('d-m-Y', strtotime($pasien['tgl_lahir'])) : '-' ?></span></div>
                <div class="rw-info-item"><label>Jenis Kelamin</label><span><?= $pasien['jk'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></span></div>
                <div class="rw-info-item"><label>Total Kunjungan</label><span><strong style="color:var(--color-primary);"><?= count($daftarKunjungan) ?></strong> Kali</span></div>
            </div>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <?php if ($noRawat): ?>
            <a href="../asesmen/pilih.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-back">← Menu Asesmen</a>
            <?php endif; ?>
            <a href="cari.php" class="btn btn-outline" style="font-size:12.5px; padding:6px 12px;">Cari Pasien</a>
        </div>
    </div>
</div>

<!-- ─── LEVEL 1: TIMELINE ACCORDION KUNJUNGAN ──────────────────── -->
<?php if (empty($daftarKunjungan)): ?>
    <div class="card" style="text-align:center; padding:40px 20px; color:var(--color-text-mute);">
        Belum ada riwayat kunjungan medis untuk pasien ini.
    </div>
<?php else: ?>
    <div class="rw-timeline">
    <?php foreach ($daftarKunjungan as $idx => $k):
        $isCurrent = ($noRawat !== '' && $k['no_rawat'] === $noRawat);
        $tglFormat = date('d M Y', strtotime($k['tgl_registrasi'])) . ' (' . substr($k['jam_reg'], 0, 5) . ')';
    ?>
        <div class="rw-acc-card <?= $isCurrent || $idx === 0 ? 'open' : '' ?> <?= $isCurrent ? 'current-visit' : '' ?>" id="kunj-<?= e($k['no_rawat']) ?>">
            
            <!-- Header Accordion -->
            <div class="rw-acc-header" onclick="toggleAccordion('kunj-<?= e($k['no_rawat']) ?>')">
                <div class="rw-acc-title">
                    <span class="rw-date-badge">📅 <?= $tglFormat ?></span>
                    <span class="rw-poli-badge"><?= e($k['nm_poli'] ?: 'Poliklinik') ?></span>
                    <span class="rw-dok-name">👨‍⚕️ <?= e($k['nm_dokter'] ?: '-') ?></span>
                    <?php if ($isCurrent): ?>
                        <span style="font-size:10px; background:var(--color-primary); color:#fff; border-radius:4px; padding:2px 6px; font-weight:700;">Kunjungan Aktif</span>
                    <?php endif; ?>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <span class="badge <?= $k['status_bayar'] === 'Sudah Bayar' ? 'badge-success' : 'badge-warning' ?>" style="font-size:11px;">
                        <?= e($k['status_bayar']) ?>
                    </span>
                    <code style="font-size:11.5px;"><?= e($k['no_rawat']) ?></code>
                    <span class="rw-acc-chevron">▼</span>
                </div>
            </div>

            <!-- Body Accordion (Level 2: Category Chips / Buttons) -->
            <div class="rw-acc-body">
                <div style="font-size:11.5px; font-weight:700; text-transform:uppercase; color:#888; margin-bottom:8px;">
                    Pilih Kategori Pemeriksaan untuk Melihat Detail:
                </div>
                <div class="rw-cat-grid">
                    <button type="button" class="rw-cat-btn" onclick="openDetailModal('<?= e($k['no_rawat']) ?>', 'soap', 'Catatan SOAP &amp; Vital Signs', '<?= $tglFormat ?>')">
                        <span class="icon">📋</span> <span>SOAP &amp; Vital Signs</span>
                    </button>
                    <button type="button" class="rw-cat-btn" onclick="openDetailModal('<?= e($k['no_rawat']) ?>', 'medis', 'Asesmen Medis Kandungan', '<?= $tglFormat ?>')">
                        <span class="icon">🩺</span> <span>Asesmen Medis</span>
                    </button>
                    <button type="button" class="rw-cat-btn" onclick="openDetailModal('<?= e($k['no_rawat']) ?>', 'keperawatan', 'Asesmen Keperawatan Kebidanan', '<?= $tglFormat ?>')">
                        <span class="icon">👩‍⚕️</span> <span>Asesmen Keperawatan</span>
                    </button>
                    <button type="button" class="rw-cat-btn" onclick="openDetailModal('<?= e($k['no_rawat']) ?>', 'obstetri', 'Pemeriksaan Obstetri', '<?= $tglFormat ?>')">
                        <span class="icon">🤰</span> <span>Obstetri</span>
                    </button>
                    <button type="button" class="rw-cat-btn" onclick="openDetailModal('<?= e($k['no_rawat']) ?>', 'ginekologi', 'Pemeriksaan Ginekologi', '<?= $tglFormat ?>')">
                        <span class="icon">🔬</span> <span>Ginekologi</span>
                    </button>
                    <button type="button" class="rw-cat-btn" onclick="openDetailModal('<?= e($k['no_rawat']) ?>', 'usg-k', 'Hasil USG Kandungan', '<?= $tglFormat ?>')">
                        <span class="icon">📡</span> <span>USG Kandungan</span>
                    </button>
                    <button type="button" class="rw-cat-btn" onclick="openDetailModal('<?= e($k['no_rawat']) ?>', 'usg-g', 'Hasil USG Ginekologi', '<?= $tglFormat ?>')">
                        <span class="icon">🖥️</span> <span>USG Ginekologi</span>
                    </button>
                    <button type="button" class="rw-cat-btn" onclick="openDetailModal('<?= e($k['no_rawat']) ?>', 'kecantikan', 'Asesmen Kecantikan &amp; Face Massage', '<?= $tglFormat ?>')">
                        <span class="icon">✨</span> <span>Asesmen Kecantikan</span>
                    </button>
                    <button type="button" class="rw-cat-btn" onclick="openDetailModal('<?= e($k['no_rawat']) ?>', 'obat', 'Resep &amp; Pemberian Obat', '<?= $tglFormat ?>')">
                        <span class="icon">💊</span> <span>Resep &amp; Obat</span>
                    </button>
                    <button type="button" class="rw-cat-btn" onclick="openDetailModal('<?= e($k['no_rawat']) ?>', 'tindakan', 'Tindakan &amp; Prosedur Medis', '<?= $tglFormat ?>')">
                        <span class="icon">💉</span> <span>Tindakan Medis</span>
                    </button>
                </div>
            </div>

        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ─── LEVEL 3: DYNAMIC AJAX MODAL ────────────────────────────── -->
<div class="rw-modal-backdrop" id="rwModalBackdrop" onclick="closeDetailModal(event)">
    <div class="rw-modal-dialog" onclick="event.stopPropagation()">
        <div class="rw-modal-header">
            <div>
                <h3 class="rw-modal-title" id="rwModalTitle">Detail Pemeriksaan</h3>
                <small id="rwModalSubtitle" style="font-size:12px; opacity:0.85; display:block; margin-top:2px;"></small>
            </div>
            <button type="button" class="rw-modal-close" onclick="closeDetailModal()">&times;</button>
        </div>
        <div class="rw-modal-body" id="rwModalBody">
            <!-- Content Injected via AJAX -->
        </div>
        <div class="rw-modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeDetailModal()" style="padding:6px 16px;">Tutup</button>
        </div>
    </div>
</div>

<script>
// Toggle Accordion Collapse
function toggleAccordion(elementId) {
    var card = document.getElementById(elementId);
    if (card) {
        card.classList.toggle('open');
    }
}

// Open Dynamic AJAX Modal
function openDetailModal(noRawat, kategori, title, tglFormat) {
    var backdrop = document.getElementById('rwModalBackdrop');
    var modalTitle = document.getElementById('rwModalTitle');
    var modalSub = document.getElementById('rwModalSubtitle');
    var modalBody = document.getElementById('rwModalBody');

    modalTitle.innerHTML = title;
    modalSub.innerHTML = 'Kunjungan: <code>' + noRawat + '</code> &bull; ' + tglFormat;
    
    // Render Loading Spinner
    modalBody.innerHTML = `
        <div class="rw-loader">
            <div class="rw-spinner"></div>
            <p style="margin-top:12px; font-size:13px; font-weight:600;">Memuat detail pemeriksaan...</p>
        </div>
    `;

    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling

    // Fetch detail data async via AJAX
    fetch('get-detail-riwayat.php?no_rawat=' + encodeURIComponent(noRawat) + '&kategori=' + encodeURIComponent(kategori))
        .then(function(response) {
            if (!response.ok) throw new Error('HTTP error ' + response.status);
            return response.text();
        })
        .then(function(html) {
            modalBody.innerHTML = html;
        })
        .catch(function(err) {
            console.error(err);
            modalBody.innerHTML = `
                <div class="alert alert-danger" style="margin:20px 0; text-align:center;">
                    ⚠️ Gagal memuat data detail riwayat: ${err.message}.
                </div>
            `;
        });
}

// Close Dynamic Modal
function closeDetailModal(e) {
    if (e && e.target !== document.getElementById('rwModalBackdrop') && !e.target.classList.contains('rw-modal-close') && e.target.tagName !== 'BUTTON') {
        // Only close when clicking backdrop or close button
    }
    var backdrop = document.getElementById('rwModalBackdrop');
    backdrop.classList.remove('active');
    document.body.style.overflow = '';
}

// Close modal on Escape key press
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDetailModal();
    }
});
</script>

<?php require __DIR__ . '/../lib/layout_footer.php'; ?>
