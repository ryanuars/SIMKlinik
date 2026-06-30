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

$halamanAktif = 'pasien';
$judulHalaman = 'Cari Pasien';
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
        <a href="daftar-baru.php" class="btn btn-primary">+ Daftarkan Sebagai Pasien Baru</a>
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
                        <a href="registrasi.php?no_rkm_medis=<?= urlencode($p['no_rkm_medis']) ?>"
                           class="btn btn-primary" style="padding:6px 14px;font-size:12.5px;">
                            Daftarkan Kunjungan
                        </a>
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
