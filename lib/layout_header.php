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

// Ambil no_rawat terakhir dari session untuk link sidebar kontekstual
$_lastNoRawat = $_SESSION['last_no_rawat'] ?? '';
$_suffiksTindakan = $_lastNoRawat !== '' ? '?no_rawat=' . urlencode($_lastNoRawat) : '';
$_suffiksResep    = $_lastNoRawat !== '' ? '?no_rawat=' . urlencode($_lastNoRawat) : '';
$_suffiksUsg      = $_lastNoRawat !== '' ? '?no_rawat=' . urlencode($_lastNoRawat) : '';
$_linkBilling     = $_lastNoRawat !== '' ? 'billing/tagihan.php?no_rawat=' . urlencode($_lastNoRawat) : 'billing/index.php';

// Tentukan link riwayat pasien: kontekstual jika ada sesi pasien aktif
$_suffiksRiwayat = '';
if ($_lastNoRawat !== '') {
    $_suffiksRiwayat = '?no_rawat=' . urlencode($_lastNoRawat);
}

$menu = [
    'dashboard'   => ['label' => 'Dashboard',          'href' => 'dashboard.php'],
    'pasien'      => ['label' => 'Registrasi Pasien',  'href' => 'pasien/cari.php'],
    'asesmen'     => ['label' => 'Asesmen',            'href' => 'asesmen/index.php'],
    'riwayat'     => ['label' => 'Riwayat Pasien',     'href' => 'pasien/riwayat.php' . $_suffiksRiwayat],
    'usg'         => ['label' => 'USG',                'href' => 'usg/index.php' . $_suffiksUsg],
    'tindakan'    => ['label' => 'Tindakan',           'href' => 'tindakan/input.php' . $_suffiksTindakan],
    'resep'       => ['label' => 'Resep',              'href' => 'resep/tulis.php' . $_suffiksResep],
    'billing'     => ['label' => 'Billing',            'href' => $_linkBilling],
];

// Menu Laporan Keuangan & Stok Obat HANYA tampil untuk Admin Utama
if (($_SESSION['role'] ?? '') === ROLE_ADMIN || ($_SESSION['role'] ?? '') === 'admin') {
    $menu['laporan']      = ['label' => 'Laporan Keuangan', 'href' => 'laporan/index.php'];
    $menu['laporan_stok'] = ['label' => 'Laporan Stok Obat', 'href' => 'laporan/stok-obat.php'];
}

$menu['master'] = ['label' => 'Master Data', 'href' => '#'];

// Deteksi Akurat Menu Aktif menggunakan PHP_SELF & Directory Path
$_scriptName = strtolower(basename($_SERVER['PHP_SELF'] ?? ''));
$_dirName    = strtolower(trim(dirname($_SERVER['PHP_SELF'] ?? ''), '/\\'));

if (in_array($_scriptName, ['stok-obat.php', 'mutasi-obat.php'])) {
    $halamanAktif = 'laporan_stok';
} elseif (in_array($_scriptName, ['index.php', 'export-excel.php']) && strpos($_dirName, 'laporan') !== false) {
    $halamanAktif = 'laporan';
}
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($judulHalaman) ?> — <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= $baseAsset ?? '' ?>assets/css/theme.css">
</head>
<body>
<div class="app-shell" id="appShell">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="sidebar" id="mainSidebar">
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

    <main class="main-content" id="mainContent">
        <div class="topbar">
            <div style="display:flex; align-items:center; gap:12px;">
                <button type="button" id="btnSidebarToggle" class="btn-toggle-sidebar" title="Buka / Tutup Sidebar" aria-label="Toggle Sidebar Navigation">
                    <span class="hamburger-icon">☰</span>
                </button>
                <h1 style="margin:0; font-size:20px; color:var(--color-text);"><?= htmlspecialchars($judulHalaman) ?></h1>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <?php
                // Logika format nama & role user
                $userRoleRaw = sessionRole() ?? '';
                $idUserRaw   = strtolower(trim($_SESSION['id_user'] ?? ''));

                if ($userRoleRaw === ROLE_ADMIN || in_array($idUserRaw, ['admin', 'ryan', 'root', 'superadmin', 'administrator'])) {
                    $userDisplayName = 'Admin Utama';
                    $userRoleLabel   = 'Administrator';
                } else {
                    $userDisplayName = sessionNama() ?: $idUserRaw;
                    // Coba cari nama gelar di dokter/pegawai jika ada
                    $stName = $pdo->prepare("SELECT nm_dokter FROM dokter WHERE kd_dokter = ? LIMIT 1");
                    $stName->execute([$idUserRaw]);
                    $dokName = $stName->fetchColumn();
                    if ($dokName) {
                        $userDisplayName = $dokName;
                        $userRoleLabel   = 'Dokter';
                    } else {
                        $stPeg = $pdo->prepare("SELECT nama, jbtn FROM pegawai WHERE nik = ? LIMIT 1");
                        $stPeg->execute([$idUserRaw]);
                        $peg = $stPeg->fetch();
                        if ($peg && !empty($peg['nama'])) {
                            $userDisplayName = $peg['nama'];
                            $userRoleLabel   = !empty($peg['jbtn']) ? $peg['jbtn'] : ($userRoleRaw === ROLE_DOKTER ? 'Dokter' : 'Perawat');
                        } else {
                            $userRoleLabel = $userRoleRaw === ROLE_DOKTER ? 'Dokter' : ($userRoleRaw === ROLE_PERAWAT ? 'Perawat' : 'User');
                        }
                    }
                }
                ?>
                <span class="text-muted" style="font-size:13.5px;">
                    <strong><?= htmlspecialchars($userDisplayName) ?></strong>
                    <span class="badge badge-success" style="margin-left:6px; font-size:11px;">
                        <?= htmlspecialchars($userRoleLabel) ?>
                    </span>
                </span>
                <a href="<?= htmlspecialchars(($baseAsset ?? '') . 'logout.php') ?>" class="btn btn-outline" style="font-size:12.5px; padding:4px 10px;">Keluar</a>
            </div>
        </div>
