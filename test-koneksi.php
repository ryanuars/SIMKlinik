<?php
/**
 * test-koneksi.php
 * -----------------------------------------------------------------
 * Halaman bantu untuk Fase 0 — Definition of Done:
 * "koneksi PHP ke `sik` berhasil, bisa SELECT dari tabel `pasien`
 *  dan `reg_periksa` tanpa error."
 *
 * HAPUS atau lindungi file ini sebelum go-live (jangan biarkan
 * halaman diagnostik terbuka di production tanpa autentikasi).
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/app.php';

header('Content-Type: text/html; charset=utf-8');

$hasil = [];

try {
    $pdo = getKoneksi();

    // 1. Cek tabel pasien
    $stmt = $pdo->query('SELECT COUNT(*) AS total FROM pasien');
    $hasil['pasien'] = $stmt->fetch()['total'];

    // 2. Cek tabel reg_periksa
    $stmt = $pdo->query('SELECT COUNT(*) AS total FROM reg_periksa');
    $hasil['reg_periksa'] = $stmt->fetch()['total'];

    // 3. Cek tabel poliklinik
    $stmt = $pdo->query('SELECT COUNT(*) AS total FROM poliklinik');
    $hasil['poliklinik'] = $stmt->fetch()['total'];

    // 4. Cek tabel dokter
    $stmt = $pdo->query('SELECT COUNT(*) AS total FROM dokter');
    $hasil['dokter'] = $stmt->fetch()['total'];

    // 5. Cek tabel user (login)
    $stmt = $pdo->query('SELECT COUNT(*) AS total FROM user');
    $hasil['user'] = $stmt->fetch()['total'];

    $status = 'ok';
    $pesan  = 'Koneksi berhasil. Semua tabel inti terbaca.';
} catch (Throwable $e) {
    $status = 'error';
    $pesan  = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Uji Koneksi — <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body>
    <div class="container" style="max-width:640px;margin:60px auto;">
        <div class="card">
            <h1 class="card-title">Uji Koneksi Database</h1>
            <p class="text-muted"><?= htmlspecialchars(NAMA_RS) ?> — <?= htmlspecialchars(APP_NAME) ?> (v<?= APP_VERSION ?>)</p>

            <?php if ($status === 'ok'): ?>
                <div class="alert alert-success">✔ <?= htmlspecialchars($pesan) ?></div>
                <table class="table">
                    <thead><tr><th>Tabel</th><th>Jumlah Baris</th></tr></thead>
                    <tbody>
                        <?php foreach ($hasil as $tabel => $jumlah): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($tabel) ?></code></td>
                            <td><?= htmlspecialchars((string) $jumlah) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-danger">
                    ✘ Gagal terhubung / query error.<br>
                    <small><?= htmlspecialchars($pesan) ?></small>
                </div>
                <p class="text-muted">
                    Cek kembali nilai <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code>,
                    <code>DB_PASS</code> di <code>config/koneksi.php</code> (atau environment variable
                    <code>SIMRS_DB_*</code>).
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
