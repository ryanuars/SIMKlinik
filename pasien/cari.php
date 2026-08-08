<?php
/**
 * pasien/cari.php
 * -----------------------------------------------------------------
 * Titik masuk alur registrasi: cari pasien lama (nama/no_rkm_medis/
 * no_ktp), atau lanjut ke form pasien baru jika tidak ditemukan.
 * READ ONLY — tidak melakukan INSERT/UPDATE apapun.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/auth.php';

wajibLogin();

$pdo = getKoneksi();

$kataKunci = trim($_GET['q'] ?? '');
$mode = trim($_GET['mode'] ?? '');
$hasil = [];

if ($kataKunci !== '') {
    $stmt = $pdo->prepare(
        "SELECT no_rkm_medis, nm_pasien, no_ktp, jk, tgl_lahir, alamat, no_tlp
         FROM pasien
         WHERE no_rkm_medis LIKE ?
            OR nm_pasien    LIKE ?
            OR no_ktp       LIKE ?
         ORDER BY nm_pasien ASC
         LIMIT 30"
    );
    $cari = '%' . $kataKunci . '%';
    $stmt->execute([$cari, $cari, $cari]);
    $hasil = $stmt->fetchAll();
}

$halamanAktif = $mode === 'asesmen' ? 'asesmen' : 'pasien';
$judulHalaman = $mode === 'asesmen' ? 'Cari Pasien — Masuk Asesmen' : 'Cari Pasien';
$baseAsset = '../';
require __DIR__ . '/../lib/layout_header.php';
?>

<div class="card">
    <p class="card-title">Cari Pasien Lama</p>
    <p class="text-muted">Masukkan nama, nomor rekam medis, atau nomor KTP pasien.</p>

    <form method="get" style="display:flex;gap:10px;align-items:flex-start;">
        <div style="flex:1;">
            <input type="text" name="q" placeholder="Cari nama / No. RM / No. KTP..."
                   value="<?= htmlspecialchars($kataKunci) ?>" autofocus
                   style="margin-bottom:0;">
        </div>
        <button type="submit" class="btn btn-primary" style="white-space:nowrap;">Cari</button>
    </form>
</div>

<?php if ($kataKunci !== ''): ?>
<div class="card">
    <p class="card-title">Hasil Pencarian</p>

    <?php if (empty($hasil)): ?>
        <div class="alert alert-warning">
            Pasien tidak ditemukan untuk kata kunci "<?= htmlspecialchars($kataKunci) ?>".
        </div>
        <?php if ($mode !== 'asesmen'): ?>
        <a href="daftar-baru.php" class="btn btn-primary">+ Daftarkan Sebagai Pasien Baru</a>
        <?php endif; ?>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>No. RM</th>
                    <th>Nama Pasien</th>
                    <th>JK</th>
                    <th>Tgl Lahir</th>
                    <th>No. Telp</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hasil as $p): ?>
                <tr>
                    <td><code><?= htmlspecialchars($p['no_rkm_medis']) ?></code></td>
                    <td><?= htmlspecialchars($p['nm_pasien']) ?></td>
                    <td><?= htmlspecialchars($p['jk'] ?? '-') ?></td>
                    <td><?= $p['tgl_lahir'] ? htmlspecialchars(date('d-m-Y', strtotime($p['tgl_lahir']))) : '-' ?></td>
                    <td><?= htmlspecialchars($p['no_tlp'] ?? '-') ?></td>
                    <td>
                    <?php if ($mode === 'asesmen'): ?>
                        <?php
                        // Cari no_rawat terbaru pasien ini
                        $stmtRawat = $pdo->prepare(
                            "SELECT no_rawat FROM reg_periksa WHERE no_rkm_medis=? ORDER BY tgl_registrasi DESC, no_rawat DESC LIMIT 1"
                        );
                        $stmtRawat->execute([$p['no_rkm_medis']]);
                        $noRawatTerbaru = $stmtRawat->fetchColumn();
                        ?>
                        <?php if ($noRawatTerbaru): ?>
                            <a href="../asesmen/pilih.php?no_rawat=<?= urlencode($noRawatTerbaru) ?>"
                               class="btn btn-primary" style="padding:6px 14px;font-size:12.5px;">
                                Pilih Kunjungan
                            </a>
                        <?php else: ?>
                            <span class="text-muted" style="font-size:12px;">Belum ada kunjungan</span>
                            <a href="registrasi.php?no_rkm_medis=<?= urlencode($p['no_rkm_medis']) ?>"
                               class="btn btn-outline" style="padding:4px 10px;font-size:12px;margin-left:6px;">
                                Daftar Dulu
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="registrasi.php?no_rkm_medis=<?= urlencode($p['no_rkm_medis']) ?>"
                           class="btn btn-primary" style="padding:6px 14px;font-size:12.5px;">
                            Daftarkan Kunjungan
                        </a>
                    <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="text-muted" style="margin-top:14px;">
            Pasien yang dicari tidak ada di daftar?
            <a href="daftar-baru.php" style="color:var(--color-primary);font-weight:600;">Daftarkan sebagai pasien baru</a>.
        </p>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card" style="background:#FBF7F8;border-style:dashed;">
    <p class="text-muted" style="margin:0;">
        Belum tahu data pasien sama sekali?
        <a href="daftar-baru.php" style="color:var(--color-primary);font-weight:600;">Langsung daftarkan pasien baru</a>.
    </p>
</div>

<?php require __DIR__ . '/../lib/layout_footer.php'; ?>
