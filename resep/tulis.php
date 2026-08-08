<?php
/**
 * resep/tulis.php
 * -----------------------------------------------------------------
 * Form penulisan resep dokter.
 * Header resep → tabel resep_obat (no_resep, tgl, jam, no_rawat,
 *                                  kd_dokter, status='ralan', ...)
 * Item resep   → tabel resep_dokter (no_resep, kode_brng, jml, aturan_pakai)
 * PK resep_obat: no_resep (format: YYMMDDHHmmXX, generated dari MAX+1)
 * Referensi Java: DlgRawatJalan.java (tab "Resep Dokter")
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';

wajibLogin();

$pdo = getKoneksi();

$noRawat = trim($_GET['no_rawat'] ?? $_POST['no_rawat'] ?? '');
// Jika tidak ada di URL, coba ambil dari session (untuk navigasi sidebar)
if ($noRawat === '') {
    $noRawat = $_SESSION['last_no_rawat'] ?? '';
}
if ($noRawat === '') {
    header('Location: index.php');
    exit;
}

// Simpan ke session agar sidebar bisa mempertahankan konteks pasien
$_SESSION['last_no_rawat'] = $noRawat;

// Ambil data kunjungan
$stmt = $pdo->prepare(
    "SELECT r.no_rawat, r.tgl_registrasi, r.kd_dokter,
            p.nm_pasien, p.no_rkm_medis,
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

/**
 * Helper untuk sinkronisasi draf resep yang telah divalidasi oleh Java Khanza.
 * Mencegah timbulnya no_resep ganda antara draf PHP dan validasi Java.
 */
function syncDraftResep(PDO $pdo, string $noRawat): void {
    try {
        $stmtDraft = $pdo->prepare(
            "SELECT no_resep FROM resep_obat
             WHERE no_rawat = ? AND (tgl_perawatan IS NULL OR tgl_perawatan = '0000-00-00')"
        );
        $stmtDraft->execute([$noRawat]);
        $drafts = $stmtDraft->fetchAll(PDO::FETCH_COLUMN);

        foreach ($drafts as $noResepDraft) {
            $stmtRd = $pdo->prepare("SELECT kode_brng FROM resep_dokter WHERE no_resep = ?");
            $stmtRd->execute([$noResepDraft]);
            $itemsDraft = $stmtRd->fetchAll(PDO::FETCH_COLUMN);

            if (empty($itemsDraft)) continue;

            $placeholders = implode(',', array_fill(0, count($itemsDraft), '?'));
            $params = array_merge([$noRawat], $itemsDraft);
            $stmtDpo = $pdo->prepare(
                "SELECT tgl_perawatan, jam FROM detail_pemberian_obat
                 WHERE no_rawat = ? AND kode_brng IN ($placeholders)
                 ORDER BY tgl_perawatan DESC, jam DESC LIMIT 1"
            );
            $stmtDpo->execute($params);
            $valMatch = $stmtDpo->fetch();

            if ($valMatch) {
                $stmtExists = $pdo->prepare(
                    "SELECT COUNT(*) FROM resep_obat
                     WHERE no_rawat = ? AND tgl_perawatan = ? AND jam = ? AND no_resep != ?"
                );
                $stmtExists->execute([$noRawat, $valMatch['tgl_perawatan'], $valMatch['jam'], $noResepDraft]);
                $hasOtherHeader = (int)$stmtExists->fetchColumn() > 0;

                if ($hasOtherHeader) {
                    $pdo->prepare("DELETE FROM resep_dokter WHERE no_resep = ?")->execute([$noResepDraft]);
                    $pdo->prepare("DELETE FROM resep_obat WHERE no_resep = ?")->execute([$noResepDraft]);
                } else {
                    $pdo->prepare(
                        "UPDATE resep_obat SET tgl_perawatan = ?, jam = ? WHERE no_resep = ?"
                    )->execute([$valMatch['tgl_perawatan'], $valMatch['jam'], $noResepDraft]);
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[syncDraftResep] ' . $e->getMessage());
    }
}

// Jalankan auto-sync sebelum menampilkan data
syncDraftResep($pdo, $noRawat);

// Ambil daftar resep yang sudah ada untuk kunjungan ini
$stmtResep = $pdo->prepare(
    "SELECT ro.no_resep, ro.tgl_perawatan, ro.jam, ro.kd_dokter, dok.nm_dokter,
            ro.tgl_peresepan, ro.jam_peresepan,
            ro.tgl_penyerahan, ro.jam_penyerahan,
            COUNT(rd.kode_brng) as jumlah_item
     FROM resep_obat ro
     LEFT JOIN dokter dok ON ro.kd_dokter = dok.kd_dokter
     LEFT JOIN resep_dokter rd ON ro.no_resep = rd.no_resep
     WHERE ro.no_rawat = ?
     GROUP BY ro.no_resep
     ORDER BY ro.tgl_perawatan DESC, ro.jam DESC"
);
$stmtResep->execute([$noRawat]);
$daftarResep = $stmtResep->fetchAll();

// Ambil daftar dokter
$stmtDok = $pdo->query("SELECT kd_dokter, nm_dokter FROM dokter ORDER BY nm_dokter ASC");
$dokters = $stmtDok->fetchAll();

// Ambil daftar obat/barang aktif untuk dropdown (limit untuk performa)
$stmtObat = $pdo->query(
    "SELECT d.kode_brng, d.nama_brng, d.ralan, d.kode_satbesar, SUM(g.stok) as total_stok
     FROM databarang d
     LEFT JOIN gudangbarang g ON d.kode_brng = g.kode_brng
     WHERE d.status = '1'
     GROUP BY d.kode_brng
     ORDER BY d.nama_brng ASC"
);
$daftarObat = $stmtObat->fetchAll();
$obatJson   = json_encode($daftarObat);

$error  = '';
$sukses = '';

/**
 * Generator no_resep — format IDENTIK dengan Java Khanza (autoNomer3):
 * Format: YYYYMMdd + 4-digit urut harian = 12 karakter
 */
function generateNoResep(PDO $pdo): string {
    $tglHariIni = date('Y-m-d');
    $prefixTgl  = date('Ymd');

    $stmt = $pdo->prepare(
        "SELECT IFNULL(MAX(CONVERT(RIGHT(no_resep,4), SIGNED)), 0)
         FROM resep_obat
         WHERE (tgl_peresepan = ? OR tgl_perawatan = ?)
           AND LEFT(no_resep, 8) = ?"
    );
    $stmt->execute([$tglHariIni, $tglHariIni, $prefixTgl]);
    $maxUrut = (int)$stmt->fetchColumn();

    return $prefixTgl . str_pad($maxUrut + 1, 4, '0', STR_PAD_LEFT);
}

$sudahBayar = isSudahBayar($noRawat, $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($sudahBayar) {
        $error = 'Peringatan: Data tidak dapat dimodifikasi karena pasien sudah melakukan pembayaran (LUNAS).';
    } else {
        $aksi = $_POST['aksi'] ?? 'simpan';

    if ($aksi === 'simpan') {
        $kdDokter = trim($_POST['kd_dokter'] ?? $kunjungan['kd_dok'] ?? '');
        $tglPrwt  = $_POST['tgl_perawatan'] ?? date('Y-m-d');
        $jam      = date('H:i:s');

        $kodeObat   = $_POST['kode_brng']    ?? [];
        $jumlah     = $_POST['jml']          ?? [];
        $aturan     = $_POST['aturan_pakai'] ?? [];

        $itemResep = [];
        for ($i = 0; $i < count($kodeObat); $i++) {
            $kb = trim($kodeObat[$i] ?? '');
            $jl = trim($jumlah[$i] ?? '');
            if ($kb !== '' && $jl !== '') {
                $itemResep[] = [
                    'kode_brng'   => $kb,
                    'jml'         => (float)$jl,
                    'aturan_pakai'=> trim($aturan[$i] ?? ''),
                ];
            }
        }

        if ($kdDokter === '') {
            $error = 'Dokter wajib dipilih.';
        } elseif (empty($itemResep)) {
            $error = 'Minimal satu item obat harus ditambahkan.';
        } else {
            $stokCukup = true;
            $pesanStok = '';
            foreach ($itemResep as $item) {
                $stmtCek = $pdo->prepare("SELECT SUM(stok) as total_stok FROM gudangbarang WHERE kode_brng = ?");
                $stmtCek->execute([$item['kode_brng']]);
                $stokGudang = (float)$stmtCek->fetchColumn();
                if ($stokGudang < $item['jml']) {
                    $stokCukup = false;
                    $pesanStok .= "Stok obat " . htmlspecialchars($item['kode_brng']) . " tidak cukup (Sisa: {$stokGudang}, Diminta: {$item['jml']}). ";
                }
            }

            if (!$stokCukup) {
                $error = $pesanStok;
            } else {
                try {
                $pdo->beginTransaction();
                $noResep = generateNoResep($pdo);

                $pdo->prepare(
                    "INSERT INTO resep_obat
                     (no_resep, tgl_perawatan, jam, no_rawat, kd_dokter,
                      tgl_peresepan, jam_peresepan, status, tgl_penyerahan, jam_penyerahan)
                     VALUES (?,'0000-00-00','00:00:00',?,?,?,?,'ralan','0000-00-00','00:00:00')"
                )->execute([
                    $noResep, $noRawat, $kdDokter,
                    $tglPrwt, $jam
                ]);

                $stmtItem = $pdo->prepare(
                    "INSERT INTO resep_dokter (no_resep, kode_brng, jml, aturan_pakai) VALUES (?,?,?,?)"
                );
                foreach ($itemResep as $item) {
                    $stmtItem->execute([
                        $noResep,
                        $item['kode_brng'],
                        $item['jml'],
                        $item['aturan_pakai'],
                    ]);
                }

                $pdo->commit();
                $sukses = "Resep No. <strong>{$noResep}</strong> berhasil disimpan dengan " . count($itemResep) . " item.";

                syncDraftResep($pdo, $noRawat);
                $stmtResep->execute([$noRawat]);
                $daftarResep = $stmtResep->fetchAll();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('[resep/tulis.php] ' . $e->getMessage());
                $error = 'Gagal menyimpan resep: ' . $e->getMessage();
            }
            }
        }
    } elseif ($aksi === 'hapus') {
        $noResepHapus = trim($_POST['no_resep_hapus'] ?? '');
        if ($noResepHapus) {
            try {
                $pdo->beginTransaction();

                // 1. Ambil info resep_obat
                $stmtCheck = $pdo->prepare("SELECT no_rawat, tgl_perawatan, jam FROM resep_obat WHERE no_resep = ?");
                $stmtCheck->execute([$noResepHapus]);
                $roInfo = $stmtCheck->fetch();

                if ($roInfo) {
                    $tglPrw = $roInfo['tgl_perawatan'];
                    $jamPrw = $roInfo['jam'];
                    $noRw   = $roInfo['no_rawat'];

                    // 2. Jika sudah divalidasi, kembalikan stok & hapus dari detail_pemberian_obat
                    if ($tglPrw && $tglPrw !== '0000-00-00') {
                        $stmtDpo = $pdo->prepare(
                            "SELECT kode_brng, jml, kd_bangsal FROM detail_pemberian_obat
                             WHERE no_rawat = ? AND tgl_perawatan = ? AND jam = ?"
                        );
                        $stmtDpo->execute([$noRw, $tglPrw, $jamPrw]);
                        $dpoItems = $stmtDpo->fetchAll();

                        $updStok = $pdo->prepare(
                            "UPDATE gudangbarang SET stok = stok + ? WHERE kode_brng = ? AND kd_bangsal = ?"
                        );
                        foreach ($dpoItems as $di) {
                            $bangsal = !empty($di['kd_bangsal']) ? $di['kd_bangsal'] : 'FARM';
                            $updStok->execute([(float)$di['jml'], $di['kode_brng'], $bangsal]);
                        }

                        $pdo->prepare(
                            "DELETE FROM detail_pemberian_obat WHERE no_rawat = ? AND tgl_perawatan = ? AND jam = ?"
                        )->execute([$noRw, $tglPrw, $jamPrw]);
                    }
                }

                // Hapus item juga dari detail_pemberian_obat yang cocok dengan kode obat resep ini jika ada entry gantung
                $stmtRdItems = $pdo->prepare("SELECT kode_brng, jml FROM resep_dokter WHERE no_resep = ?");
                $stmtRdItems->execute([$noResepHapus]);
                $rdItems = $stmtRdItems->fetchAll();
                if ($rdItems) {
                    $updStok = $pdo->prepare("UPDATE gudangbarang SET stok = stok + ? WHERE kode_brng = ? AND kd_bangsal = 'FARM'");
                    $delDpoItem = $pdo->prepare("DELETE FROM detail_pemberian_obat WHERE no_rawat = ? AND kode_brng = ?");
                    foreach ($rdItems as $rdi) {
                        $delDpoItem->execute([$noRawat, $rdi['kode_brng']]);
                    }
                }

                // 3. Hapus dari resep_dokter dan resep_obat
                $pdo->prepare("DELETE FROM resep_dokter WHERE no_resep = ?")->execute([$noResepHapus]);
                $pdo->prepare("DELETE FROM resep_obat WHERE no_resep = ? AND no_rawat = ?")->execute([$noResepHapus, $noRawat]);

                $pdo->commit();
                $sukses = "Resep No. <strong>{$noResepHapus}</strong> berhasil dihapus dan stok obat telah dikembalikan.";
                header('Location: tulis.php?no_rawat=' . urlencode($noRawat) . '&sukses=' . urlencode(strip_tags($sukses)));
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Gagal menghapus resep: ' . $e->getMessage();
            }
        }
    } elseif ($aksi === 'validasi') {
        $noResepAksi = trim($_POST['no_resep_aksi'] ?? '');
        if ($noResepAksi) {
            try {
                $pdo->beginTransaction();

                $tglVal = date('Y-m-d');
                $jamVal = date('H:i:s');

                $pdo->prepare(
                    "UPDATE resep_obat SET tgl_perawatan = ?, jam = ?
                     WHERE no_resep = ? AND no_rawat = ?"
                )->execute([$tglVal, $jamVal, $noResepAksi, $noRawat]);

                $stmtItems = $pdo->prepare(
                    "SELECT rd.kode_brng, rd.jml, db.h_beli, db.ralan,
                            g.kd_bangsal, g.no_batch, g.no_faktur
                     FROM resep_dokter rd
                     JOIN databarang db ON rd.kode_brng = db.kode_brng
                     LEFT JOIN gudangbarang g ON rd.kode_brng = g.kode_brng AND g.kd_bangsal = 'FARM'
                     WHERE rd.no_resep = ?"
                );
                $stmtItems->execute([$noResepAksi]);
                $items = $stmtItems->fetchAll();

                $insDpo = $pdo->prepare(
                    "INSERT INTO detail_pemberian_obat
                     (tgl_perawatan, jam, no_rawat, kode_brng, h_beli, biaya_obat, jml, embalase, tuslah, total, status, kd_bangsal, no_batch, no_faktur)
                     VALUES (?,?,?,?,?,?,?,0,0,?,?,?,?,?)"
                );

                $updStok = $pdo->prepare(
                    "UPDATE gudangbarang SET stok = stok - ?
                     WHERE kode_brng = ? AND kd_bangsal = 'FARM'"
                );

                $chkDpo = $pdo->prepare(
                    "SELECT COUNT(*) FROM detail_pemberian_obat WHERE no_rawat = ? AND tgl_perawatan = ? AND jam = ? AND kode_brng = ?"
                );

                foreach ($items as $item) {
                    $chkDpo->execute([$noRawat, $tglVal, $jamVal, $item['kode_brng']]);
                    if ((int)$chkDpo->fetchColumn() > 0) {
                        continue;
                    }

                    $hBeli    = (float)($item['h_beli'] ?? 0);
                    $hJual    = (float)($item['ralan'] ?? 0);
                    $jml      = (float)($item['jml']);
                    $total    = $hJual * $jml;
                    $kdBangsal = $item['kd_bangsal'] ?? 'FARM';
                    $noBatch   = $item['no_batch'] ?? '';
                    $noFaktur  = $item['no_faktur'] ?? '';

                    $insDpo->execute([
                        $tglVal, $jamVal, $noRawat,
                        $item['kode_brng'],
                        $hBeli, $hJual, $jml,
                        $total,
                        'Ralan', $kdBangsal, $noBatch, $noFaktur
                    ]);

                    $updStok->execute([$jml, $item['kode_brng']]);
                }

                $pdo->commit();
                $sukses = "Resep <strong>{$noResepAksi}</strong> telah divalidasi dan dicatat ke billing.";
                header('Location: tulis.php?no_rawat=' . urlencode($noRawat) . '&sukses=' . urlencode(strip_tags($sukses)));
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Gagal memvalidasi resep: ' . $e->getMessage();
            }
        }
    } elseif ($aksi === 'serahkan') {
        // Petugas farmasi menyerahkan obat ke pasien (setelah divalidasi)
        $noResepAksi = trim($_POST['no_resep_aksi'] ?? '');
        if ($noResepAksi) {
            try {
                // Tinggal update status tgl_penyerahan saja
                $pdo->prepare(
                    "UPDATE resep_obat SET tgl_penyerahan = CURDATE(), jam_penyerahan = CURTIME()
                     WHERE no_resep = ? AND no_rawat = ?"
                )->execute([$noResepAksi, $noRawat]);

                $sukses = "Resep <strong>{$noResepAksi}</strong> telah diserahkan ke pasien.";
                header('Location: tulis.php?no_rawat=' . urlencode($noRawat) . '&sukses=' . urlencode(strip_tags($sukses)));
                exit;
            } catch (Throwable $e) {
                $error = 'Gagal mencatat penyerahan: ' . $e->getMessage();
            }
        }
        }
    }
}

$halamanAktif = 'resep';
$judulHalaman = 'Tulis Resep Dokter';
$baseAsset    = '../';
require __DIR__ . '/../lib/layout_header.php';

// encode obat data untuk JS
$obatJson = json_encode(array_column($daftarObat, null, 'kode_brng'));
?>
<style>
.resep-table th { background:var(--color-primary); color:#fff; }
.resep-table td, .resep-table th { padding:7px 10px; font-size:13px; }
.resep-table tr:hover td { background:#fdf6f8; }
.item-row td { padding:5px 6px; vertical-align:middle; }
.item-row input, .item-row select { margin-bottom:0; padding:5px 8px; font-size:12.5px; }
#tbl-item tfoot td { padding:8px 6px; }
</style>

<div style="margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; font-size:13px;">
    <div>
        <a href="../asesmen/pilih.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-back">← Kembali ke Menu Asesmen</a>
        <span class="text-muted" style="margin-left:8px;">&bull; <code><?= htmlspecialchars($noRawat) ?></code> &bull; <strong><?= htmlspecialchars($kunjungan['nm_pasien']) ?></strong></span>
    </div>
    <div style="display:flex; gap:6px;">
        <a href="../asesmen/index.php" class="btn btn-outline" style="font-size:12px; padding:4px 10px; text-decoration:none;">Daftar Pasien</a>
        <a href="../dashboard.php" class="btn btn-outline" style="font-size:12px; padding:4px 10px; text-decoration:none;">Dashboard</a>
    </div>
</div>

<?php if ($sukses): ?>
    <div class="alert alert-success" id="alert-simpan-sukses">✔ <?= $sukses ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php $sukses = $sukses ?: ($_GET['sukses'] ?? ''); ?>
<!-- Daftar Resep yang Sudah Ada -->
<?php if ($daftarResep): ?>
<div class="card">
    <p class="card-title">Resep Kunjungan Ini (<?= count($daftarResep) ?>)</p>
    <small class="text-muted" style="display:block;margin-bottom:12px;font-size:11px;">
        Alur: <strong>Tulis Resep</strong> → <strong style="color:#b36b00;">Validasi</strong> (farmasi cek kelengkapan) → <strong style="color:#2F6B4F;">Serahkan</strong> (obat diserahkan ke pasien)
    </small>
    <div style="overflow-x:auto;">
    <table class="table resep-table">
        <thead><tr>
            <th>No. Resep</th>
            <th>Tgl &amp; Jam Peresepan</th>
            <th>Dokter</th>
            <th>Item</th>
            <th>Tgl &amp; Jam Validasi</th>
            <th>Tgl &amp; Jam Penyerahan</th>
            <th>Aksi</th>
        </tr></thead>
        <tbody>
        <?php foreach ($daftarResep as $r): ?>
        <?php
            $sudahValidasi   = !empty($r['tgl_perawatan'])   && $r['tgl_perawatan']   !== '0000-00-00';
            $sudahDiserahkan = !empty($r['tgl_penyerahan']) && $r['tgl_penyerahan'] !== '0000-00-00';
        ?>
        <tr>
            <td><code style="font-size:11px;"><?= htmlspecialchars($r['no_resep']) ?></code></td>
            <td>
                <?php
                    $tglP = $r['tgl_peresepan'] ?: $r['tgl_perawatan'];
                    $jamP = $r['jam_peresepan'] ?: $r['jam'];
                    echo $tglP ? date('d-m-Y', strtotime($tglP)) : '-';
                ?>
                <br><small class="text-muted"><?= htmlspecialchars(substr($jamP ?? '-', 0, 5)) ?></small>
            </td>
            <td><?= htmlspecialchars($r['nm_dokter'] ?? '-') ?></td>
            <td><?= (int)$r['jumlah_item'] ?> item</td>
            <td>
                <?php if ($sudahValidasi): ?>
                    <span class="badge badge-success">✔ Tervalidasi</span><br>
                    <small class="text-muted"><?= date('d-m-Y', strtotime($r['tgl_perawatan'])) ?> <?= htmlspecialchars(substr($r['jam'] ?? '', 0, 5)) ?></small>
                <?php else: ?>
                    <span class="badge badge-warning">Belum Validasi</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($sudahDiserahkan): ?>
                    <span class="badge badge-success">✔ Diserahkan</span><br>
                    <small class="text-muted"><?= date('d-m-Y', strtotime($r['tgl_penyerahan'])) ?> <?= htmlspecialchars(substr($r['jam_penyerahan'] ?? '', 0, 5)) ?></small>
                <?php else: ?>
                    <span class="badge" style="background:#eee;color:#666;">Belum Diserahkan</span>
                <?php endif; ?>
            </td>
            <td style="white-space:nowrap;">
                <a href="detail.php?no_resep=<?= urlencode($r['no_resep']) ?>&no_rawat=<?= urlencode($noRawat) ?>"
                   class="btn btn-outline" style="font-size:12px;padding:3px 8px;">Detail</a>
                <?php if (!$sudahBayar && !$sudahValidasi && !$sudahDiserahkan): ?>
                <form method="post" style="display:inline;"
                      onsubmit="return confirm('Validasi resep <?= htmlspecialchars($r['no_resep']) ?>?')">
                    <input type="hidden" name="no_rawat" value="<?= htmlspecialchars($noRawat) ?>">
                    <input type="hidden" name="aksi" value="validasi">
                    <input type="hidden" name="no_resep_aksi" value="<?= htmlspecialchars($r['no_resep']) ?>">
                    <button type="submit" class="btn btn-outline" style="font-size:12px;padding:3px 8px;color:#b36b00;border-color:#b36b00;">Validasi</button>
                </form>
                <?php elseif (!$sudahBayar && $sudahValidasi && !$sudahDiserahkan): ?>
                <form method="post" style="display:inline;"
                      onsubmit="return confirm('Serahkan obat resep <?= htmlspecialchars($r['no_resep']) ?> ke pasien?')">
                    <input type="hidden" name="no_rawat" value="<?= htmlspecialchars($noRawat) ?>">
                    <input type="hidden" name="aksi" value="serahkan">
                    <input type="hidden" name="no_resep_aksi" value="<?= htmlspecialchars($r['no_resep']) ?>">
                    <button type="submit" class="btn btn-success" style="font-size:12px;padding:3px 8px;">Serahkan Obat</button>
                </form>
                <?php endif; ?>
                <?php if (!$sudahBayar && !$sudahDiserahkan): ?>
                <form method="post" style="display:inline;"
                      onsubmit="return confirm('Hapus resep <?= htmlspecialchars($r['no_resep']) ?>?')">
                    <input type="hidden" name="no_rawat" value="<?= htmlspecialchars($noRawat) ?>">
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="no_resep_hapus" value="<?= htmlspecialchars($r['no_resep']) ?>">
                    <button type="submit" class="btn btn-outline"
                            style="font-size:12px;padding:3px 8px;color:#D62839;border-color:#D62839;">Hapus</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<!-- Form Tulis Resep Baru -->
<div class="card">
    <p class="card-title">Tulis Resep Baru</p>

    <?php if ($sudahBayar): ?>
        <div class="alert alert-danger" style="margin-bottom:15px; font-size:12.5px;">
            🔒 <strong>Data dikunci:</strong> Pembayaran telah diselesaikan. Anda tidak dapat membuat, mengubah, menghapus, atau memvalidasi resep.
        </div>
    <?php endif; ?>

    <form method="post" id="formResep">
        <fieldset <?= $sudahBayar ? 'disabled' : '' ?> style="border:0; padding:0; margin:0;">
        <input type="hidden" name="no_rawat" value="<?= htmlspecialchars($noRawat) ?>">
        <input type="hidden" name="aksi" value="simpan">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
            <div>
                <label for="kd_dokter">Dokter Penulis Resep *</label>
                <select id="kd_dokter" name="kd_dokter" required>
                    <option value="">-- Pilih Dokter --</option>
                    <?php foreach ($dokters as $d): ?>
                    <option value="<?= htmlspecialchars($d['kd_dokter']) ?>"
                        <?= ($kunjungan['kd_dok'] ?? '') === $d['kd_dokter'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['nm_dokter']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="tgl_perawatan">Tanggal Peresepan</label>
                <input type="date" id="tgl_perawatan" name="tgl_perawatan" value="<?= date('Y-m-d') ?>">
            </div>
        </div>

        <!-- Tabel Item Resep -->
        <p style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--color-primary);margin:12px 0 8px;">
            Item Obat / Barang
        </p>
        <div style="overflow-x:auto;">
        <table id="tbl-item" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:var(--color-primary);color:#fff;">
                    <th style="padding:7px 10px;font-size:12.5px;text-align:left;width:40%;">Obat / Barang</th>
                    <th style="padding:7px 10px;font-size:12.5px;text-align:left;width:8%;">Jumlah</th>
                    <th style="padding:7px 10px;font-size:12.5px;text-align:left;">Aturan Pakai</th>
                    <th style="padding:7px 10px;font-size:12.5px;text-align:right;width:10%;">Harga/Unit</th>
                    <th style="width:36px;"></th>
                </tr>
            </thead>
            <tbody id="item-body">
                <tr class="item-row" id="row-0">
                    <td>
                        <select name="kode_brng[]" onchange="updateHarga(this)" style="width:100%;">
                            <option value="">-- Pilih Obat --</option>
                            <?php foreach ($daftarObat as $o): ?>
                            <?php $stok = (float)$o['total_stok']; ?>
                            <option value="<?= htmlspecialchars($o['kode_brng']) ?>" <?= $stok <= 0 ? 'disabled' : '' ?> data-harga="<?= (float)($o['ralan'] ?? 0) ?>">
                                <?= htmlspecialchars($o['nama_brng']) ?> — Rp <?= number_format((float)$o['ralan'],0,',','.') ?>
                                [Stok: <?= $stok > 0 ? $stok : 'Kosong' ?> <?= htmlspecialchars($o['kode_satbesar']) ?>]
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="number" name="jml[]" min="0.01" step="any" placeholder="1" value="1" style="width:100%;"></td>
                    <td><input type="text" name="aturan_pakai[]" placeholder="3x1, sesudah makan..." style="width:100%;"></td>
                    <td style="text-align:right;">
                        <span class="harga-display" style="font-family:monospace;font-size:12px;white-space:nowrap;">-</span>
                    </td>
                    <td style="text-align:center;">
                        <button type="button" onclick="removeRow(this)" style="background:none;border:none;color:#D62839;font-size:18px;cursor:pointer;line-height:1;">×</button>
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5">
                        <button type="button" id="btnTambah" onclick="addRow()"
                                class="btn btn-outline" style="font-size:12.5px;padding:5px 12px;">
                            + Tambah Baris Obat
                        </button>
                    </td>
                </tr>
            </tfoot>
        </table>
        </div>

        <div style="display:flex;gap:10px;margin-top:18px;">
            <button type="submit" class="btn btn-primary">Simpan Resep</button>
            <a href="../asesmen/pilih.php?no_rawat=<?= urlencode($noRawat) ?>" class="btn btn-outline">Kembali</a>
        </div>
        </fieldset>
    </form>
</div>

<script>
const obatData = <?= $obatJson ?>;
let rowCount = 1;

function getObatSelect() {
    // Return HTML options for obat select
    let opts = '<option value="">-- Pilih Obat --</option>';
    Object.values(obatData).forEach(ob => {
        const stok = parseFloat(ob.total_stok || 0);
        const harga = parseFloat(ob.ralan || 0);
        const formatHarga = harga.toLocaleString('id-ID');
        const labelStok = stok > 0 ? stok : 'Kosong';
        const disabled = stok <= 0 ? 'disabled' : '';
        opts += `<option value="${ob.kode_brng}" ${disabled} data-harga="${harga}">
            ${ob.nama_brng} — Rp ${formatHarga} [Stok: ${labelStok} ${ob.kode_satbesar || ''}]
        </option>`;
    });
    return opts;
}

function addRow() {
    const tbody = document.getElementById('item-body');
    const tr = document.createElement('tr');
    tr.className = 'item-row';
    tr.id = 'row-' + rowCount;
    tr.innerHTML = `
        <td><select name="kode_brng[]" onchange="updateHarga(this)" style="width:100%;">${getObatSelect()}</select></td>
        <td><input type="number" name="jml[]" min="0.01" step="any" value="1" style="width:100%;"></td>
        <td><input type="text" name="aturan_pakai[]" placeholder="3x1, sesudah makan..." style="width:100%;"></td>
        <td style="text-align:right;"><span class="harga-display" style="font-family:monospace;font-size:12px;white-space:nowrap;">-</span></td>
        <td style="text-align:center;"><button type="button" onclick="removeRow(this)" style="background:none;border:none;color:#D62839;font-size:18px;cursor:pointer;line-height:1;">×</button></td>
    `;
    tbody.appendChild(tr);
    rowCount++;
}

function removeRow(btn) {
    const rows = document.querySelectorAll('#item-body .item-row');
    if (rows.length <= 1) { alert('Minimal satu item harus ada.'); return; }
    btn.closest('tr').remove();
}

function updateHarga(select) {
    const opt = select.selectedOptions[0];
    const harga = parseFloat(opt?.dataset?.harga || 0);
    const display = select.closest('tr').querySelector('.harga-display');
    display.textContent = harga > 0 ? 'Rp ' + harga.toLocaleString('id-ID') : '-';
}

// UX: Auto-SweetAlert + clean URL setelah simpan/validasi/serahkan berhasil
(function () {
    const params = new URLSearchParams(window.location.search);
    if (!params.get('sukses')) return;
    const pesan = params.get('sukses');
    const noRawat = params.get('no_rawat') || '';
    const baseUrl = 'tulis.php?no_rawat=' + encodeURIComponent(noRawat);
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: pesan,
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
