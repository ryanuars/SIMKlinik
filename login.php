<?php
/**
 * login.php
 * -----------------------------------------------------------------
 * Halaman login — dua mode sesuai kebutuhan:
 *   1. Admin Utama  -> tabel `admin`
 *   2. User (dokter/perawat) -> tabel `user`
 * Memakai pola AES_ENCRYPT/DECRYPT native Khanza (lib/auth.php).
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/lib/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika sudah login, langsung ke dashboard
if (!empty($_SESSION['role']) && !empty($_SESSION['id_user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$modeAktif = $_POST['mode'] ?? 'user'; // 'admin' | 'user'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $hasil = ($modeAktif === 'admin')
            ? loginAdmin($username, $password)
            : loginUser($username, $password);

        if ($hasil->sukses) {
            mulaiSession($hasil);
            header('Location: dashboard.php');
            exit;
        } else {
            $error = $hasil->pesan;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <h1><?= htmlspecialchars(NAMA_RS) ?></h1>
            <p class="subtitle">Klinik Kebidanan &amp; Kecantikan — Masuk ke sistem</p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="login.php">
                <label>Masuk sebagai</label>
                <div class="form-row" style="margin-bottom:16px;">
                    <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;">
                        <input type="radio" name="mode" value="user" style="width:auto;margin:0;"
                               <?= $modeAktif === 'user' ? 'checked' : '' ?>>
                        Dokter / Perawat
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;">
                        <input type="radio" name="mode" value="admin" style="width:auto;margin:0;"
                               <?= $modeAktif === 'admin' ? 'checked' : '' ?>>
                        Admin Utama
                    </label>
                </div>

                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">

                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>

                <button type="submit" class="btn btn-primary">Masuk</button>
            </form>
        </div>
    </div>
</body>
</html>
