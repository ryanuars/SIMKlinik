<?php
/**
 * config/koneksi.php
 * -----------------------------------------------------------------
 * Koneksi ke database `sik` (skema bawaan SIMRS Khanza).
 * TIDAK mengubah struktur tabel apapun — koneksi ini HANYA dipakai
 * untuk SELECT/INSERT/UPDATE pada tabel yang sudah ada.
 *
 * Memakai PDO + prepared statement sebagai standar wajib di seluruh
 * modul (lihat docs/PLAN... Bagian 9 - Catatan Teknis Tambahan).
 * -----------------------------------------------------------------
 *
 * PENTING SEBELUM DIPAKAI DI SERVER ASLI:
 * 1. Ganti nilai DB_HOST, DB_NAME, DB_USER, DB_PASS sesuai server RSU Al-Arif.
 * 2. Sebaiknya pisahkan kredensial ke file .env / luar git (lihat .gitignore).
 * 3. Pastikan user DB yang dipakai punya privilege SELECT/INSERT/UPDATE
 *    pada tabel-tabel yang dipetakan di docs/PLAN-..., TANPA privilege
 *    DROP/ALTER (sebagai pengaman ekstra prinsip "tidak mengubah skema").
 */

// --- Konfigurasi dasar (SESUAIKAN dengan server RSU Al-Arif) ---
define('DB_HOST', getenv('SIMRS_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('SIMRS_DB_NAME') ?: 'sik');
define('DB_USER', getenv('SIMRS_DB_USER') ?: 'root');
define('DB_PASS', getenv('SIMRS_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Mengembalikan instance PDO singleton untuk koneksi ke `sik`.
 * Dipakai di semua modul: $pdo = getKoneksi();
 */
function getKoneksi(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // pakai native prepared statement
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Jangan tampilkan detail koneksi ke user di production.
            error_log('[koneksi.php] Gagal konek DB: ' . $e->getMessage());
            http_response_code(500);
            die('Gagal terhubung ke database. Hubungi admin sistem.');
        }
    }

    return $pdo;
}

/**
 * Helper kecil untuk cek koneksi dari CLI / halaman tes (lihat test-koneksi.php).
 */
function cekKoneksi(): array
{
    try {
        $pdo = getKoneksi();
        $stmt = $pdo->query('SELECT COUNT(*) AS total FROM pasien');
        $row = $stmt->fetch();
        return [
            'status'      => 'ok',
            'total_pasien' => (int) $row['total'],
        ];
    } catch (Throwable $e) {
        return [
            'status'  => 'error',
            'message' => $e->getMessage(),
        ];
    }
}
