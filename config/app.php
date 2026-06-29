<?php
/**
 * config/app.php
 * -----------------------------------------------------------------
 * Konstanta & konfigurasi umum aplikasi (bukan kredensial DB).
 * File ini AMAN untuk masuk git (tidak ada secret di sini).
 * -----------------------------------------------------------------
 */

define('APP_NAME', 'SIMRS Khanza Web — Kebidanan & Kecantikan');
define('APP_VERSION', '0.1.0-fase0');

// Nama instansi (dipakai di header/cetak nota)
define('NAMA_RS', 'RSU Al-Arif');

// Timezone wajib konsisten dengan server Khanza Java agar tgl_registrasi,
// jam_reg, dsb selaras antar-aplikasi.
date_default_timezone_set('Asia/Jakarta');

/**
 * DAFTAR ROLE APLIKASI (level aplikasi PHP, BUKAN kolom baru di tabel `user`)
 * Sesuai prinsip Fase 7: role ditentukan dari relasi user -> dokter/petugas,
 * bukan menambah kolom ke tabel bawaan Khanza.
 */
const ROLE_ADMIN   = 'admin';
const ROLE_DOKTER  = 'dokter';
const ROLE_PERAWAT = 'perawat'; // termasuk bidan

/**
 * PENTING (lihat docs/KEPUTUSAN-TEKNIS.md):
 * - ROLE_ADMIN ditentukan via whitelist id_user di bawah ini untuk sementara
 *   (Fase 0/1). Di Fase 7 bisa dipindah ke tabel mapping baru bila perlu.
 * - Whitelist ini HARUS diisi sesuai id_user admin yang sebenarnya di RSU Al-Arif
 *   sebelum dipakai di server production.
 */
const ADMIN_WHITELIST = [
    // 'admin', // contoh: isi id_user yang valid di tabel `user`
];

// Status enum reg_periksa.stts yang valid (disalin dari skema sik.sql,
// JANGAN diubah urutannya kecuali skema Khanza berubah)
const STATUS_REG_PERIKSA = [
    'Belum', 'Sudah', 'Batal', 'Berkas Diterima', 'Dirujuk', 'Meninggal', 'Dirawat', 'Pulang Paksa',
];

// Status billing yang relevan untuk modul ini (subset dari ENUM lengkap di tabel billing)
const STATUS_BILLING_RELEVAN = [
    'Registrasi',
    'Ralan Dokter',
    'Ralan Paramedis',
    'Obat',
    'Resep Pulang',
];
