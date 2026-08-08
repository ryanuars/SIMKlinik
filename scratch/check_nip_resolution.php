<?php
require_once __DIR__ . '/../config/koneksi.php';
$pdo = getKoneksi();

function resolveNip(PDO $pdo, ?string $sessionNip, ?string $sessionIdUser, ?string $kdDokter): string {
    $candidates = array_filter([$sessionNip, $sessionIdUser, $kdDokter]);
    $stmt = $pdo->prepare("SELECT nik FROM pegawai WHERE nik = ?");

    foreach ($candidates as $cand) {
        $cand = trim($cand);
        if ($cand === '') continue;
        $stmt->execute([$cand]);
        if ($stmt->fetchColumn()) {
            return $cand;
        }
    }

    // Jika id_user / kd_dokter adalah kode dokter (misal PD01), coba cari nik pegawai dari dokter/petugas nama atau nik pegawai default
    foreach ($candidates as $cand) {
        $cand = trim($cand);
        // Cek tabel dokter
        $stmtDok = $pdo->prepare("SELECT nm_dokter FROM dokter WHERE kd_dokter = ?");
        $stmtDok->execute([$cand]);
        $nmDok = $stmtDok->fetchColumn();
        if ($nmDok) {
            $stmtPeg = $pdo->prepare("SELECT nik FROM pegawai WHERE nama LIKE ? LIMIT 1");
            $stmtPeg->execute(['%' . $nmDok . '%']);
            $nikFound = $stmtPeg->fetchColumn();
            if ($nikFound) return $nikFound;
        }

        // Cek tabel petugas
        $stmtPtg = $pdo->prepare("SELECT nama FROM petugas WHERE nip = ?");
        $stmtPtg->execute([$cand]);
        $nmPtg = $stmtPtg->fetchColumn();
        if ($nmPtg) {
            $stmtPeg = $pdo->prepare("SELECT nik FROM pegawai WHERE nama LIKE ? LIMIT 1");
            $stmtPeg->execute(['%' . $nmPtg . '%']);
            $nikFound = $stmtPeg->fetchColumn();
            if ($nikFound) return $nikFound;
        }
    }

    // Default aman yang ADA di pegawai.nik
    return '-';
}

echo "Testing NIP resolution:\n";
echo "PD01 -> " . resolveNip($pdo, null, 'PD01', 'PD01') . "\n";
echo "admin -> " . resolveNip($pdo, null, 'admin', null) . "\n";
echo "56001977072 -> " . resolveNip($pdo, '56001977072', null, null) . "\n";
