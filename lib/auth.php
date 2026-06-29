<?php
/**
 * lib/auth.php
 * -----------------------------------------------------------------
 * Helper autentikasi yang KOMPATIBEL dengan pola native SIMRS Khanza.
 *
 * SUMBER VERIFIKASI (lihat docs/KEPUTUSAN-TEKNIS.md #2):
 *   src/fungsi/akses.java baris 268-269 (repo resmi mas-elkhanza/SIMRS-Khanza):
 *     - Admin Utama : SELECT * FROM admin
 *                     WHERE admin.usere = AES_ENCRYPT(?, 'nur')
 *                       AND admin.passworde = AES_ENCRYPT(?, 'windi')
 *     - User (dokter/perawat) : SELECT * FROM user
 *                     WHERE user.id_user = AES_ENCRYPT(?, 'nur')
 *                       AND user.password = AES_ENCRYPT(?, 'windi')
 *
 * PENTING:
 * - Kita TIDAK mengganti algoritma ini dengan bcrypt/password_hash,
 *   karena tabel `admin` dan `user` adalah tabel BAWAAN Khanza yang
 *   juga dipakai aplikasi Java — mengganti algoritma akan merusak
 *   login dari sisi Java. Kita WAJIB memakai AES_ENCRYPT/DECRYPT
 *   yang sama persis, termasuk key literalnya ('nur' dan 'windi').
 * - Key 'nur' dan 'windi' adalah string literal hardcoded di Khanza
 *   sendiri (bukan secret yang kita pilih) — disimpan sebagai konstanta
 *   di bawah, BUKAN sebagai rahasia yang perlu disembunyikan ekstra,
 *   karena memang begitu adanya di seluruh instalasi Khanza.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/app.php';

// Key AES literal bawaan Khanza (JANGAN diubah — harus identik dengan Java)
const AES_KEY_USER = 'nur';   // dipakai untuk enkripsi id_user
const AES_KEY_PASS = 'windi'; // dipakai untuk enkripsi password

/**
 * Hasil login terstandar.
 */
final class HasilLogin
{
    public bool $sukses;
    public string $role = '';      // ROLE_ADMIN | ROLE_DOKTER | ROLE_PERAWAT
    public ?string $idUser = null; // id_user (admin: usere) dalam bentuk plain
    public ?string $nama = null;
    public ?string $kdDokter = null; // terisi jika role dokter
    public ?string $nip = null;      // terisi jika role dokter/perawat (dari tabel petugas)
    public string $pesan = '';

    public static function gagal(string $pesan): self
    {
        $h = new self();
        $h->sukses = false;
        $h->pesan = $pesan;
        return $h;
    }
}

/**
 * Coba login sebagai Admin Utama (tabel `admin`).
 */
function loginAdmin(string $username, string $password): HasilLogin
{
    $pdo = getKoneksi();

    $stmt = $pdo->prepare(
        'SELECT usere
         FROM admin
         WHERE usere = AES_ENCRYPT(?, ?)
           AND passworde = AES_ENCRYPT(?, ?)
         LIMIT 1'
    );
    $stmt->execute([
        $username,
        AES_KEY_USER,
        $password,
        AES_KEY_PASS,
    ]);
    $row = $stmt->fetch();

    if (!$row) {
        return HasilLogin::gagal('Username atau password Admin salah.');
    }

    $hasil = new HasilLogin();
    $hasil->sukses = true;
    $hasil->role = ROLE_ADMIN;
    $hasil->idUser = $username;
    $hasil->nama = 'Administrator';
    return $hasil;
}

/**
 * Coba login sebagai User (dokter/perawat) — tabel `user`.
 * Role ditentukan dari relasi ke tabel `dokter` (via kolom user.dokter
 * atau pencocokan nama, sesuai konvensi Khanza) — TIDAK menambah kolom
 * baru ke tabel `user`.
 */
function loginUser(string $username, string $password): HasilLogin
{
    $pdo = getKoneksi();

    // NOTE: pakai positional placeholder (?), bukan named parameter,
    // karena AES_KEY_USER dipakai 2x dalam satu query (kolom SELECT +
    // kondisi WHERE) — native prepared statement MySQL (PDO::ATTR_EMULATE_PREPARES
    // = false) tidak selalu mengizinkan named parameter dipakai berulang
    // dalam satu statement yang sama.
    $stmt = $pdo->prepare(
        'SELECT AES_DECRYPT(user.id_user, ?) AS id_user_plain,
                user.dokter
         FROM user
         WHERE user.id_user = AES_ENCRYPT(?, ?)
           AND user.password = AES_ENCRYPT(?, ?)
         LIMIT 1'
    );
    $stmt->execute([
        AES_KEY_USER,
        $username,
        AES_KEY_USER,
        $password,
        AES_KEY_PASS,
    ]);
    $row = $stmt->fetch();

    if (!$row) {
        return HasilLogin::gagal('Username atau password salah.');
    }

    $hasil = new HasilLogin();
    $hasil->sukses = true;
    $hasil->idUser = $row['id_user_plain'];

    // Tentukan role: jika user ini terhubung ke seorang dokter
    // (kolom boolean `user.dokter` = hak akses modul dokter di Khanza),
    // anggap role-nya dokter; selain itu perawat/bidan.
    // NOTE: ini heuristik level-aplikasi, BUKAN kolom baru di DB.
    if (!empty($row['dokter']) && (int) $row['dokter'] === 1) {
        $hasil->role = ROLE_DOKTER;
    } else {
        $hasil->role = ROLE_PERAWAT;
    }

    // Coba cocokkan ke data petugas/dokter by nama user untuk dapat nip/kd_dokter.
    // (Konvensi Khanza: nama petugas/dokter sering disamakan dengan id_user,
    // tapi ini TIDAK selalu pasti — perlu diverifikasi per instalasi di Fase 1
    // lanjutan. Untuk sekarang nip/kd_dokter boleh null dan diisi manual di UI.)
    $hasil->nama = $row['id_user_plain'];

    return $hasil;
}

/**
 * Mulai session login (dipanggil setelah loginAdmin/loginUser sukses).
 */
function mulaiSession(HasilLogin $hasil): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['role'] = $hasil->role;
    $_SESSION['id_user'] = $hasil->idUser;
    $_SESSION['nama'] = $hasil->nama;
    $_SESSION['kd_dokter'] = $hasil->kdDokter;
    $_SESSION['nip'] = $hasil->nip;
    $_SESSION['login_at'] = time();
}

/**
 * Cek apakah ada session aktif; redirect ke login.php jika belum.
 * Panggil di awal setiap halaman yang butuh login.
 */
function wajibLogin(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['role']) || empty($_SESSION['id_user'])) {
        header('Location: /login.php');
        exit;
    }
}

function sessionRole(): ?string
{
    return $_SESSION['role'] ?? null;
}

function sessionNama(): ?string
{
    return $_SESSION['nama'] ?? null;
}

function logout(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    session_destroy();
}
