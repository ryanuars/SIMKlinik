<?php
/**
 * lib/layout_header.php
 * -----------------------------------------------------------------
 * Partial layout (sidebar + topbar) — di-include di setiap halaman
 * setelah login. Pastikan variabel $halamanAktif & $judulHalaman
 * sudah diset SEBELUM include file ini.
 *
 * Wajib panggil wajibLogin() di halaman pemanggil SEBELUM include ini.
 * -----------------------------------------------------------------
 */

$halamanAktif = $halamanAktif ?? '';
$judulHalaman = $judulHalaman ?? APP_NAME;

$menu = [
    'dashboard'   => ['label' => 'Dashboard',          'href' => 'dashboard.php'],
    'pasien'      => ['label' => 'Registrasi Pasien',  'href' => 'pasien/cari.php'],
    'asesmen'     => ['label' => 'Asesmen',            'href' => '#'],
    'usg'         => ['label' => 'USG',                'href' => '#'],
    'tindakan'    => ['label' => 'Tindakan',           'href' => '#'],
    'resep'       => ['label' => 'Resep',              'href' => '#'],
    'billing'     => ['label' => 'Billing',            'href' => '#'],
    'master'      => ['label' => 'Master Data',        'href' => '#'],
];
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($judulHalaman) ?> — <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= $baseAsset ?? '' ?>assets/css/theme.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">
            <?= htmlspecialchars(NAMA_RS) ?>
            <small>Klinik Kebidanan &amp; Kecantikan</small>
        </div>
        <nav>
            <?php foreach ($menu as $key => $item): ?>
                <a href="<?= htmlspecialchars(($baseAsset ?? '') . $item['href']) ?>"
                   class="<?= $halamanAktif === $key ? 'active' : '' ?>">
                    <?= htmlspecialchars($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <h1><?= htmlspecialchars($judulHalaman) ?></h1>
            <div style="display:flex;align-items:center;gap:12px;">
                <span class="text-muted">
                    <?= htmlspecialchars(sessionNama() ?? '') ?>
                    <span class="badge badge-success" style="margin-left:6px;">
                        <?= htmlspecialchars(sessionRole() ?? '') ?>
                    </span>
                </span>
                <a href="<?= htmlspecialchars(($baseAsset ?? '') . 'logout.php') ?>" class="btn btn-outline">Keluar</a>
            </div>
        </div>
