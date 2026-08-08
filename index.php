<?php
/**
 * index.php
 * -----------------------------------------------------------------
 * Router Awal / Gatekeeper SIMKlinik
 * Redirect ke dashboard.php jika user sudah login, atau ke login.php jika belum.
 * -----------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah session login valid (role & id_user terisi)
if (!empty($_SESSION['role']) && !empty($_SESSION['id_user'])) {
    header('Location: dashboard.php');
    exit;
} else {
    header('Location: login.php');
    exit;
}
