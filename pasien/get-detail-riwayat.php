<?php
/**
 * pasien/get-detail-riwayat.php
 * Endpoint AJAX untuk mengambil detail pemeriksaan pasien berdasarkan no_rawat dan kategori.
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';

wajibLogin();
$pdo = getKoneksi();

$noRawat  = trim($_GET['no_rawat'] ?? '');
$kategori = trim($_GET['kategori'] ?? '');

if ($noRawat === '' || $kategori === '') {
    echo '<div class="alert alert-danger" style="margin:0;">Parameter tidak lengkap.</div>';
    exit;
}

if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

switch ($kategori) {

    // ─── 1. SOAP & VITAL SIGNS ────────────────────────────────────
    case 'soap':
        $st = $pdo->prepare(
            "SELECT pr.*, COALESCE(peg.nama, pet.nama, 'Petugas') AS nama_pencatat
             FROM pemeriksaan_ralan pr
             LEFT JOIN pegawai peg ON pr.nip = peg.nik
             LEFT JOIN petugas pet ON pr.nip = pet.nip
             WHERE pr.no_rawat = ?
             ORDER BY pr.tgl_perawatan DESC, pr.jam_rawat DESC"
        );
        $st->execute([$noRawat]);
        $rows = $st->fetchAll();

        if (empty($rows)) {
            echo '<div class="rw-empty-modal">Belum ada catatan SOAP / Vital Signs untuk kunjungan ini.</div>';
            break;
        }

        foreach ($rows as $i => $r) {
            ?>
            <div class="rw-detail-card" style="<?= $i > 0 ? 'margin-top:16px;' : '' ?>">
                <div class="rw-detail-header">
                    <div>
                        <strong>🗓️ <?= date('d M Y', strtotime($r['tgl_perawatan'])) ?> — <?= e($r['jam_rawat']) ?></strong>
                        <span class="text-muted" style="font-size:12px; margin-left:8px;">Oleh: <?= e($r['nama_pencatat']) ?></span>
                    </div>
                </div>

                <!-- Vital Signs Grid -->
                <div class="rw-vit-grid">
                    <div class="rw-vit-box"><label>Tensi (TD)</label><span><?= e($r['tensi'] ?: '-') ?> <small>mmHg</small></span></div>
                    <div class="rw-vit-box"><label>Nadi</label><span><?= e($r['nadi'] ?: '-') ?> <small>x/mnt</small></span></div>
                    <div class="rw-vit-box"><label>Suhu Tubuh</label><span><?= e($r['suhu_tubuh'] ?: '-') ?> <small>°C</small></span></div>
                    <div class="rw-vit-box"><label>Respirasi (RR)</label><span><?= e($r['respirasi'] ?: '-') ?> <small>x/mnt</small></span></div>
                    <div class="rw-vit-box"><label>SpO2</label><span><?= e($r['spo2'] ?: '-') ?> <small>%</small></span></div>
                    <div class="rw-vit-box"><label>Berat (BB)</label><span><?= e($r['berat'] ?: '-') ?> <small>kg</small></span></div>
                    <div class="rw-vit-box"><label>Tinggi (TB)</label><span><?= e($r['tinggi'] ?: '-') ?> <small>cm</small></span></div>
                    <div class="rw-vit-box"><label>Kesadaran</label><span><?= e($r['kesadaran'] ?: '-') ?></span></div>
                </div>

                <div class="rw-sub-grid">
                    <div><strong>Subjektif (S):</strong><p><?= nl2br(e($r['keluhan'] ?: '-')) ?></p></div>
                    <div><strong>Objektif (O):</strong><p><?= nl2br(e($r['pemeriksaan'] ?: '-')) ?></p></div>
                    <div><strong>Asesmen (A):</strong><p><?= nl2br(e($r['penilaian'] ?: '-')) ?></p></div>
                    <div><strong>Plan (P):</strong><p><?= nl2br(e($r['rtl'] ?: '-')) ?></p></div>
                </div>
                <?php if ($r['instruksi'] || $r['evaluasi']): ?>
                <div class="rw-sub-grid" style="margin-top:8px; border-top:1px dashed #eee; padding-top:8px;">
                    <?php if ($r['instruksi']): ?><div><strong>Instruksi:</strong><p><?= nl2br(e($r['instruksi'])) ?></p></div><?php endif; ?>
                    <?php if ($r['evaluasi']): ?><div><strong>Evaluasi:</strong><p><?= nl2br(e($r['evaluasi'])) ?></p></div><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php
        }
        break;

    // ─── 2. ASESMEN MEDIS ─────────────────────────────────────────
    case 'medis':
        $st = $pdo->prepare(
            "SELECT am.*, dok.nm_dokter
             FROM penilaian_medis_ralan_kandungan am
             LEFT JOIN dokter dok ON am.kd_dokter = dok.kd_dokter
             WHERE am.no_rawat = ?"
        );
        $st->execute([$noRawat]);
        $r = $st->fetch();

        if (!$r) {
            echo '<div class="rw-empty-modal">Belum ada data Asesmen Medis Kebidanan &amp; Kandungan.</div>';
            break;
        }
        ?>
        <div class="rw-detail-card">
            <div class="rw-detail-header">
                <div>
                    <strong>🩺 Asesmen Medis Kebidanan &amp; Kandungan</strong>
                    <span class="text-muted" style="font-size:12px; margin-left:8px;">Dokter: <?= e($r['nm_dokter'] ?? '-') ?></span>
                </div>
                <div class="text-muted" style="font-size:12px;"><?= date('d M Y H:i', strtotime($r['tanggal'])) ?></div>
            </div>

            <table class="rw-info-table">
                <tr><th>Keluhan Utama</th><td><?= nl2br(e($r['keluhan_utama'] ?: '-')) ?></td></tr>
                <tr><th>Riwayat Penyakit</th><td><?= nl2br(e($r['riwayat_penyakit'] ?: '-')) ?></td></tr>
                <tr><th>Diagnosis</th><td><strong style="color:var(--color-primary);"><?= e($r['diagnosis'] ?: '-') ?></strong></td></tr>
                <tr><th>Tata Laksana / Plan</th><td><?= nl2br(e($r['tata'] ?: '-')) ?></td></tr>
                <?php if (!empty($r['konsultasi'])): ?>
                <tr><th>Konsultasi</th><td><?= nl2br(e($r['konsultasi'])) ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
        <?php
        break;

    // ─── 3. ASESMEN KEPERAWATAN ──────────────────────────────────
    case 'keperawatan':
        $st = $pdo->prepare(
            "SELECT ak.*, COALESCE(peg.nama, pet.nama, 'Petugas') AS nama_petugas
             FROM penilaian_awal_keperawatan_kebidanan ak
             LEFT JOIN pegawai peg ON ak.nip = peg.nik
             LEFT JOIN petugas pet ON ak.nip = pet.nip
             WHERE ak.no_rawat = ?"
        );
        $st->execute([$noRawat]);
        $r = $st->fetch();

        if (!$r) {
            echo '<div class="rw-empty-modal">Belum ada data Asesmen Keperawatan Kebidanan.</div>';
            break;
        }
        ?>
        <div class="rw-detail-card">
            <div class="rw-detail-header">
                <div>
                    <strong>👩‍⚕️ Asesmen Keperawatan Kebidanan</strong>
                    <span class="text-muted" style="font-size:12px; margin-left:8px;">Petugas: <?= e($r['nama_petugas']) ?></span>
                </div>
                <div class="text-muted" style="font-size:12px;"><?= date('d M Y H:i', strtotime($r['tanggal'])) ?></div>
            </div>

            <div class="rw-vit-grid" style="margin-bottom:12px;">
                <div class="rw-vit-box"><label>TD</label><span><?= e($r['td'] ?: '-') ?></span></div>
                <div class="rw-vit-box"><label>Nadi</label><span><?= e($r['nadi'] ?: '-') ?></span></div>
                <div class="rw-vit-box"><label>RR</label><span><?= e($r['rr'] ?: '-') ?></span></div>
                <div class="rw-vit-box"><label>Suhu</label><span><?= e($r['suhu'] ?: '-') ?>°C</span></div>
                <div class="rw-vit-box"><label>BB</label><span><?= e($r['bb'] ?: '-') ?> kg</span></div>
                <div class="rw-vit-box"><label>TB</label><span><?= e($r['tb'] ?: '-') ?> cm</span></div>
            </div>

            <table class="rw-info-table">
                <tr><th>Keluhan Utama</th><td><?= nl2br(e($r['keluhan_utama'] ?: '-')) ?></td></tr>
                <tr><th>Masalah Keperawatan</th><td><?= nl2br(e($r['masalah'] ?: '-')) ?></td></tr>
                <tr><th>Rencana Tindakan</th><td><?= nl2br(e($r['tindakan'] ?: '-')) ?></td></tr>
            </table>
        </div>
        <?php
        break;

    // ─── 4. OBSTETRI ──────────────────────────────────────────────
    case 'obstetri':
        $st = $pdo->prepare("SELECT * FROM pemeriksaan_obstetri_ralan WHERE no_rawat = ? ORDER BY tgl_perawatan DESC, jam_rawat DESC");
        $st->execute([$noRawat]);
        $rows = $st->fetchAll();

        if (empty($rows)) {
            echo '<div class="rw-empty-modal">Belum ada pemeriksaan Obstetri untuk kunjungan ini.</div>';
            break;
        }

        foreach ($rows as $r) {
            ?>
            <div class="rw-detail-card" style="margin-bottom:12px;">
                <div class="rw-detail-header">
                    <strong>🤰 Pemeriksaan Obstetri</strong>
                    <span class="text-muted" style="font-size:12px;"><?= date('d M Y', strtotime($r['tgl_perawatan'])) ?> — <?= e($r['jam_rawat']) ?></span>
                </div>
                <div class="rw-vit-grid">
                    <div class="rw-vit-box"><label>Tinggi Uteri</label><span><?= e($r['tinggi_uteri'] ?: '-') ?></span></div>
                    <div class="rw-vit-box"><label>Janin</label><span><?= e($r['janin'] ?: '-') ?></span></div>
                    <div class="rw-vit-box"><label>Letak Janin</label><span><?= e($r['letak'] ?: '-') ?></span></div>
                    <div class="rw-vit-box"><label>DJJ (Denyut)</label><span><?= e($r['denyut'] ?: '-') ?></span></div>
                    <div class="rw-vit-box"><label>Kontraksi</label><span><?= e($r['kontraksi'] ?: '-') ?></span></div>
                    <div class="rw-vit-box"><label>Pembukaan</label><span><?= e($r['pembukaan'] ?: '-') ?></span></div>
                    <div class="rw-vit-box"><label>Penurunan</label><span><?= e($r['penurunan'] ?: '-') ?></span></div>
                    <div class="rw-vit-box"><label>Ketuban</label><span><?= e($r['ketuban'] ?: '-') ?></span></div>
                </div>
            </div>
            <?php
        }
        break;

    // ─── 5. GINEKOLOGI ───────────────────────────────────────────
    case 'ginekologi':
        $st = $pdo->prepare("SELECT * FROM pemeriksaan_ginekologi_ralan WHERE no_rawat = ? ORDER BY tgl_perawatan DESC, jam_rawat DESC");
        $st->execute([$noRawat]);
        $rows = $st->fetchAll();

        if (empty($rows)) {
            echo '<div class="rw-empty-modal">Belum ada pemeriksaan Ginekologi untuk kunjungan ini.</div>';
            break;
        }

        foreach ($rows as $r) {
            ?>
            <div class="rw-detail-card" style="margin-bottom:12px;">
                <div class="rw-detail-header">
                    <strong>🔬 Pemeriksaan Ginekologi</strong>
                    <span class="text-muted" style="font-size:12px;"><?= date('d M Y', strtotime($r['tgl_perawatan'])) ?> — <?= e($r['jam_rawat']) ?></span>
                </div>
                <table class="rw-info-table">
                    <tr><th>Inspeksi</th><td><?= e($r['inspeksi'] ?: '-') ?></td></tr>
                    <tr><th>Inspeksi Vulva</th><td><?= e($r['inspeksi_vulva'] ?: '-') ?></td></tr>
                    <tr><th>Inspekulo Ginekologi</th><td><?= e($r['inspekulo_gine'] ?: '-') ?></td></tr>
                    <tr><th>Portio Dalam</th><td><?= e($r['portio_dalam'] ?: '-') ?></td></tr>
                    <tr><th>Bentuk / Ukuran Uterus</th><td><?= e($r['bentuk'] ?: '-') ?> / <?= e($r['ukuran'] ?: '-') ?></td></tr>
                    <tr><th>Nyeri Tekan</th><td><?= e($r['nyeri_tekan'] ?: '-') ?></td></tr>
                    <tr><th>Adnexa Kanan / Kiri</th><td><?= e($r['adnexa_kanan'] ?: '-') ?> / <?= e($r['adnexa_kiri'] ?: '-') ?></td></tr>
                    <tr><th>Cavum Douglas</th><td><?= e($r['cavum_douglas'] ?: '-') ?></td></tr>
                </table>
            </div>
            <?php
        }
        break;

    // ─── 6. USG KANDUNGAN ────────────────────────────────────────
    case 'usg-k':
        $st = $pdo->prepare(
            "SELECT u.*, dok.nm_dokter
             FROM hasil_pemeriksaan_usg u
             LEFT JOIN dokter dok ON u.kd_dokter = dok.kd_dokter
             WHERE u.no_rawat = ?"
        );
        $st->execute([$noRawat]);
        $r = $st->fetch();

        if (!$r) {
            echo '<div class="rw-empty-modal">Belum ada data USG Kandungan untuk kunjungan ini.</div>';
            break;
        }
        ?>
        <div class="rw-detail-card">
            <div class="rw-detail-header">
                <div>
                    <strong>📡 Hasil Pemeriksaan USG Kandungan</strong>
                    <span class="text-muted" style="font-size:12px; margin-left:8px;">Dokter: <?= e($r['nm_dokter'] ?? '-') ?></span>
                </div>
                <div class="text-muted" style="font-size:12px;"><?= date('d M Y H:i', strtotime($r['tanggal'])) ?></div>
            </div>

            <div class="rw-vit-grid" style="margin-bottom:12px;">
                <div class="rw-vit-box"><label>Usia Kehamilan</label><span><?= e($r['usia_kehamilan'] ?: '-') ?></span></div>
                <div class="rw-vit-box"><label>Tafsiran BB Janin</label><span><?= e($r['tafsiran_berat_janin'] ?: '-') ?></span></div>
                <div class="rw-vit-box"><label>Prediksi Sex</label><span><?= e($r['peluang_sex'] ?: '-') ?></span></div>
                <div class="rw-vit-box"><label>Air Ketuban</label><span><?= e($r['jumlah_air_ketuban'] ?: '-') ?></span></div>
            </div>

            <table class="rw-info-table">
                <tr><th>Diagnosa Klinis</th><td><?= e($r['diagnosa_klinis'] ?: '-') ?></td></tr>
                <tr><th>Kesimpulan USG</th><td><strong style="color:var(--color-primary);"><?= nl2br(e($r['kesimpulan'] ?: '-')) ?></strong></td></tr>
            </table>
        </div>
        <?php
        break;

    // ─── 7. USG GINEKOLOGI ───────────────────────────────────────
    case 'usg-g':
        $st = $pdo->prepare(
            "SELECT ug.*, dok.nm_dokter
             FROM hasil_pemeriksaan_usg_gynecologi ug
             LEFT JOIN dokter dok ON ug.kd_dokter = dok.kd_dokter
             WHERE ug.no_rawat = ?"
        );
        $st->execute([$noRawat]);
        $r = $st->fetch();

        if (!$r) {
            echo '<div class="rw-empty-modal">Belum ada data USG Ginekologi untuk kunjungan ini.</div>';
            break;
        }
        ?>
        <div class="rw-detail-card">
            <div class="rw-detail-header">
                <div>
                    <strong>🖥️ Hasil Pemeriksaan USG Ginekologi</strong>
                    <span class="text-muted" style="font-size:12px; margin-left:8px;">Dokter: <?= e($r['nm_dokter'] ?? '-') ?></span>
                </div>
                <div class="text-muted" style="font-size:12px;"><?= date('d M Y H:i', strtotime($r['tanggal'])) ?></div>
            </div>

            <table class="rw-info-table">
                <tr><th>Diagnosa Klinis</th><td><?= e($r['diagnosa_klinis'] ?: '-') ?></td></tr>
                <tr><th>Uterus</th><td><?= e($r['uterus'] ?: '-') ?></td></tr>
                <tr><th>Ovarium</th><td><?= e($r['ovarium'] ?: '-') ?></td></tr>
                <tr><th>Kesimpulan USG</th><td><strong style="color:var(--color-primary);"><?= nl2br(e($r['kesimpulan'] ?: '-')) ?></strong></td></tr>
            </table>
        </div>
        <?php
        break;

    // ─── 8. ASESMEN KECANTIKAN ───────────────────────────────────
    case 'kecantikan':
        $st = $pdo->prepare(
            "SELECT kc.*, COALESCE(peg.nama, pet.nama, 'Petugas') AS nama_petugas
             FROM penilaian_treatment_wajah kc
             LEFT JOIN pegawai peg ON kc.nip = peg.nik
             LEFT JOIN petugas pet ON kc.nip = pet.nip
             WHERE kc.no_rawat = ?"
        );
        $st->execute([$noRawat]);
        $r = $st->fetch();

        if (!$r) {
            echo '<div class="rw-empty-modal">Belum ada data Asesmen Kecantikan &amp; Face Massage.</div>';
            break;
        }

        $stT = $pdo->prepare("SELECT pos_x, pos_y, keterangan FROM penilaian_treatment_wajah_titik WHERE no_rawat = ?");
        $stT->execute([$noRawat]);
        $titik = $stT->fetchAll();
        ?>
        <div class="rw-detail-card">
            <div class="rw-detail-header">
                <div>
                    <strong>✨ Asesmen Kecantikan &amp; Face Massage</strong>
                    <span class="text-muted" style="font-size:12px; margin-left:8px;">Petugas: <?= e($r['nama_petugas'] ?? '-') ?></span>
                </div>
                <div class="text-muted" style="font-size:12px;"><?= date('d M Y H:i', strtotime($r['tgl_perawatan'])) ?></div>
            </div>

            <div class="rw-vit-grid" style="margin-bottom:12px;">
                <div class="rw-vit-box"><label>Jenis Kulit</label><span style="color:var(--color-primary); font-weight:700;"><?= e($r['jenis_kulit']) ?></span></div>
                <div class="rw-vit-box"><label>Jerawat</label><span><?= e($r['jerawat']) ?><?= $r['jerawat_area'] ? ' ('.e($r['jerawat_area']).')' : '' ?></span></div>
                <div class="rw-vit-box"><label>Bekas Jerawat</label><span><?= e($r['cacat_bekas_jerawat']) ?></span></div>
                <div class="rw-vit-box"><label>Fleks Hitam/Cokelat</label><span><?= e($r['fleks_hitam_cokelat']) ?></span></div>
                <div class="rw-vit-box"><label>Keriput Wajah</label><span><?= e($r['keriput_wajah']) ?></span></div>
                <div class="rw-vit-box"><label>Area Sensitif</label><span><?= e($r['area_sensitif']) ?></span></div>
            </div>

            <table class="rw-info-table">
                <tr><th>Keluhan Utama</th><td><?= nl2br(e($r['keluhan'] ?: '-')) ?></td></tr>
                <tr><th>Riwayat Dahulu / Keluarga</th><td><?= e($r['riwayat_penyakit_dahulu'] ?: '-') ?> / <?= e($r['riwayat_penyakit_keluarga'] ?: '-') ?></td></tr>
                <tr><th>Kondisi Khusus</th><td>Hamil: <?= e($r['kondisi_hamil']) ?> | Menyusui: <?= e($r['kondisi_menyusui']) ?> | Kontrasepsi: <?= e($r['menggunakan_kontrasepsi']) ?> (<?= e($r['jenis_kontrasepsi'] ?: '-') ?>) | Alergi: <?= e($r['alergi']) ?></td></tr>
                <tr><th>Fokus Pijatan</th><td><?= nl2br(e($r['fokus_pijatan_area'] ?: '-')) ?> (<?= e($r['tingkat_pijatan'] ?: '-') ?>)</td></tr>
                <tr><th>Persetujuan Pasien</th><td>
                    <span class="badge <?= $r['persetujuan_pasien']==='Ya' ? 'badge-success' : 'badge-warning' ?>">
                        <?= e($r['persetujuan_pasien']) ?> — Penandatangan: <?= e($r['nama_ttd_pasien'] ?: '-') ?>
                    </span>
                </td></tr>
            </table>

            <?php if (!empty($titik)): ?>
            <div style="margin-top:12px; background:#FFF8FB; border:1px solid #F0D3E1; border-radius:8px; padding:10px;">
                <label style="font-size:11.5px; font-weight:700; color:var(--color-primary);">Titik Penanda Pijatan Wajah (<?= count($titik) ?> titik):</label>
                <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:6px;">
                    <?php foreach ($titik as $idx => $t): ?>
                    <span style="font-size:11px; background:#A0396A; color:#fff; border-radius:4px; padding:2px 8px;">
                        Titik <?= $idx+1 ?> (<?= round($t['pos_x']) ?>%, <?= round($t['pos_y']) ?>%)
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        break;

    // ─── 9. RESEP & OBAT ──────────────────────────────────────────
    case 'obat':
        $st = $pdo->prepare(
            "SELECT dpo.tgl_perawatan, dpo.jam, dpo.kode_brng, db.nama_brng AS nama_obat,
                    dpo.jml AS jumlah, db.kode_sat,
                    COALESCE(rd.aturan_pakai, '') AS aturan_pakai
             FROM detail_pemberian_obat dpo
             INNER JOIN databarang db ON dpo.kode_brng = db.kode_brng
             LEFT JOIN resep_obat ro ON dpo.no_rawat = ro.no_rawat AND dpo.tgl_perawatan = ro.tgl_perawatan AND dpo.jam = ro.jam
             LEFT JOIN resep_dokter rd ON ro.no_resep = rd.no_resep AND dpo.kode_brng = rd.kode_brng
             WHERE dpo.no_rawat = ?
             ORDER BY dpo.tgl_perawatan DESC, dpo.jam DESC"
        );
        $st->execute([$noRawat]);
        $rows = $st->fetchAll();

        if (empty($rows)) {
            echo '<div class="rw-empty-modal">Belum ada catatan pemberian obat / resep.</div>';
            break;
        }
        ?>
        <div class="rw-detail-card">
            <div class="rw-detail-header" style="margin-bottom:10px;">
                <strong>💊 Resep &amp; Pemberian Obat</strong>
            </div>
            <table class="rw-modal-table">
                <thead>
                    <tr>
                        <th>Tgl / Jam</th>
                        <th>Kode</th>
                        <th>Nama Obat</th>
                        <th style="text-align:center;">Jumlah</th>
                        <th>Aturan Pakai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= date('d-m-Y', strtotime($r['tgl_perawatan'])) ?> <small class="text-muted"><?= e($r['jam']) ?></small></td>
                        <td><code><?= e($r['kode_brng']) ?></code></td>
                        <td><strong><?= e($r['nama_obat']) ?></strong></td>
                        <td style="text-align:center;"><?= e($r['jumlah']) ?> <?= e($r['kode_sat']) ?></td>
                        <td><?= e($r['aturan_pakai'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        break;

    // ─── 10. TINDAKAN ──────────────────────────────────────────────
    case 'tindakan':
        $st = $pdo->prepare(
            "SELECT r.tgl_perawatan, r.jam_rawat, j.nm_perawatan, r.tarif_tindakan, 'Dokter' as jenis
             FROM rawat_jl_dr r JOIN jns_perawatan j ON r.kd_jenis_prw = j.kd_jenis_prw WHERE r.no_rawat = ?
             UNION ALL
             SELECT r.tgl_perawatan, r.jam_rawat, j.nm_perawatan, r.tarif_tindakan, 'Petugas' as jenis
             FROM rawat_jl_pr r JOIN jns_perawatan j ON r.kd_jenis_prw = j.kd_jenis_prw WHERE r.no_rawat = ?
             UNION ALL
             SELECT r.tgl_perawatan, r.jam_rawat, j.nm_perawatan, r.tarif_tindakan, 'Dokter & Petugas' as jenis
             FROM rawat_jl_drpr r JOIN jns_perawatan j ON r.kd_jenis_prw = j.kd_jenis_prw WHERE r.no_rawat = ?
             ORDER BY tgl_perawatan DESC, jam_rawat DESC"
        );
        $st->execute([$noRawat, $noRawat, $noRawat]);
        $rows = $st->fetchAll();

        if (empty($rows)) {
            echo '<div class="rw-empty-modal">Belum ada tindakan medis yang dicatat.</div>';
            break;
        }
        ?>
        <div class="rw-detail-card">
            <div class="rw-detail-header" style="margin-bottom:10px;">
                <strong>🩺 Tindakan &amp; Prosedur Medis</strong>
            </div>
            <table class="rw-modal-table">
                <thead>
                    <tr>
                        <th>Tgl / Jam</th>
                        <th>Nama Tindakan / Perawatan</th>
                        <th>Pelaksana</th>
                        <th style="text-align:right;">Tarif</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= date('d-m-Y', strtotime($r['tgl_perawatan'])) ?> <small class="text-muted"><?= e($r['jam_rawat']) ?></small></td>
                        <td><strong><?= e($r['nm_perawatan']) ?></strong></td>
                        <td><?= e($r['jenis']) ?></td>
                        <td style="text-align:right;">Rp <?= number_format($r['tarif_tindakan'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        break;

    default:
        echo '<div class="rw-empty-modal">Kategori pemeriksaan tidak dikenal.</div>';
        break;
}
