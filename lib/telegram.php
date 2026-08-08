<?php
/**
 * lib/telegram.php
 * -----------------------------------------------------------------
 * Helper Notifikasi Keamanan Telegram (Anti-Fraud System)
 * Mengirim notifikasi otomatis saat login atau aktivitas penting.
 * -----------------------------------------------------------------
 */

// Konstanta Token & Chat ID Bot Telegram
// (Sesuaikan dengan Bot Token & Chat ID Telegram yang valid)
if (!defined('TELEGRAM_BOT_TOKEN')) {
    define('TELEGRAM_BOT_TOKEN', 'YOUR_TELEGRAM_BOT_TOKEN_HERE');
}
if (!defined('TELEGRAM_CHAT_ID')) {
    define('TELEGRAM_CHAT_ID', 'YOUR_TELEGRAM_CHAT_ID_HERE');
}

/**
 * Kirim notifikasi pesan ke Telegram Bot via cURL.
 *
 * @param string $message Format pesan (Markdown / HTML)
 * @param string|null $botToken
 * @param string|null $chatId
 * @return bool True jika berhasil dikirim
 */
function sendTelegramAlert(string $message, ?string $botToken = null, ?string $chatId = null): bool
{
    $token = $botToken ?: TELEGRAM_BOT_TOKEN;
    $chat  = $chatId   ?: TELEGRAM_CHAT_ID;

    // Abaikan jika token/chat_id belum diset (masih placeholder)
    if ($token === '' || $token === 'YOUR_TELEGRAM_BOT_TOKEN_HERE' || $chat === '' || $chat === 'YOUR_TELEGRAM_CHAT_ID_HERE') {
        return false;
    }

    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $payload = [
        'chat_id'    => $chat,
        'text'       => $message,
        'parse_mode' => 'Markdown',
    ];

    if (!function_exists('curl_init')) {
        error_log('[telegram.php] Ekstensi cURL PHP tidak aktif di server.');
        return false;
    }

    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_TIMEOUT        => 5, // Timeout 5 detik agar tidak memperlambat login
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('[telegram.php] cURL Error: ' . $error);
            return false;
        }
        return true;
    } catch (Throwable $e) {
        error_log('[telegram.php] Telegram Exception: ' . $e->getMessage());
        return false;
    }
}

/**
 * Format & kirim notifikasi alert login pengguna.
 */
function sendLoginAlert(string $idUser, string $role, string $nama = ''): bool
{
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if ($ip === '::1') $ip = '127.0.0.1 (localhost)';
    $waktu    = date('d-m-Y H:i:s');
    $namaUser = $nama ?: $idUser;
    $roleText = strtoupper($role);

    $msg = "🚨 *LOGIN ALERT SIMKLINIK* 🚨\n"
         . "━━━━━━━━━━━━━━━━━━━━\n"
         . "👤 *User:* {$namaUser} (`{$idUser}`)\n"
         . "🔑 *Role:* {$roleText}\n"
         . "⏰ *Waktu:* {$waktu} WIB\n"
         . "🌐 *IP Address:* `{$ip}`";

    return sendTelegramAlert($msg);
}
