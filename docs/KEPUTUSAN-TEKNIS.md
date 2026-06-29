# Keputusan Teknis & Log Progress

> File ini adalah "kartu status" proyek. **Selalu upload file ini bersama folder proyek** saat melanjutkan pekerjaan di sesi/akun Claude yang berbeda, supaya konteks tidak hilang.

---

## Cara Pakai File Ini

1. Setiap kali sebuah keputusan teknis dibuat (lihat daftar pertanyaan terbuka di bawah), catat jawabannya di Bagian 1.
2. Setiap kali sebuah checklist fase di `docs/PLAN-SIMRS-KHANZA-WEB-KEBIDANAN.md` selesai, update juga ringkasan progress di Bagian 2 file ini.
3. Saat mulai sesi baru: upload folder project ini (terutama `docs/`) lalu katakan ke Claude, contoh:
   > "Lanjutkan proyek SIMRS Khanza Web Kebidanan. Baca docs/PLAN-SIMRS-KHANZA-WEB-KEBIDANAN.md dan docs/KEPUTUSAN-TEKNIS.md, kita baru selesai Fase 1."

---

## 1. Keputusan Teknis (Jawaban dari Bagian 8 Dokumen Rencana)

| # | Pertanyaan | Status | Jawaban / Keputusan |
|---|---|---|---|
| 1 | Format & generator `no_rawat` dan `no_rkm_medis` | ✅ Terkonfirmasi penuh | **no_rawat**: format `YYYY/MM/DD/NNNNNN`, reset harian — terverifikasi dari source Java DAN dari data riil RSU Al-Arif (`2026/06/30/000004`, dst, reset ke `000001` tiap ganti tanggal). **no_rkm_medis**: dikonfirmasi langsung oleh RSU Al-Arif (2026-06-29) — mode "Straight" bawaan Khanza (tahun=No, bulan=No), 6 digit urut polos tanpa reset, **maksimal 6 digit**. Data panjang 7 digit (`0435945`) & 5 digit (`05232`) adalah **data kotor hasil upload manual**, dan panjang 1 digit (`1`) **sengaja dibuat** — semua ini diabaikan oleh filter `REGEXP '^[0-9]{6}$'` di `generateNoRkmMedis()` supaya tidak mempengaruhi nilai MAX. Lihat `lib/nomor.php`. |
| 2 | Algoritma hash password tabel `user` | ✅ Terkonfirmasi | **Bukan hash, tapi MySQL `AES_ENCRYPT`/`AES_DECRYPT` dengan key literal `'windi'`** untuk kolom password, dan key `'nur'` untuk kolom `id_user` (id_user pun terenkripsi, bukan plaintext!). Sumber: `src/fungsi/akses.java:268-269`. Query asli Java: `SELECT * FROM user WHERE user.id_user=AES_ENCRYPT(?,'nur') AND user.password=AES_ENCRYPT(?,'windi')`. **Ada juga tabel `admin` terpisah** (kolom `usere`, `passworde`, pola AES sama) — khusus untuk login Admin Utama, terpisah dari `user` (dokter/perawat). Ini PAS dengan kebutuhan "login (admin utama, user (dokter-perawat))". |
| 3 | Kode poli & kode dokter khusus kebidanan/kecantikan di RSU Al-Arif | ⏳ Belum dikonfirmasi | — (akan diisi otomatis dari dropdown saat Fase 2, ambil dari tabel `poliklinik`/`dokter` yang sudah ada — 16 poli & 40 dokter sudah terverifikasi ada datanya) |
| 4 | Versi PHP & ekstensi server (mysqli/PDO, GD/Imagick) | ⏳ Belum dikonfirmasi | Koneksi PDO sudah terbukti jalan di Fase 0 (test-koneksi.php sukses) |
| 5 | Kebijakan validasi resep (berbasis tanggal vs SOP khusus) | ⏳ Belum dikonfirmasi | — |
| 6 | Opsi A vs B untuk modul kecantikan (tabel baru vs field generik) | ⏳ Belum dikonfirmasi | — |

> Update tabel di atas begitu Anda punya jawabannya (boleh dari cek source Java, atau dari SOP RSU Al-Arif).

---

## 2. Ringkasan Progress per Fase

| Fase | Status | Tanggal Selesai | Catatan |
|---|---|---|---|
| Fase 0 — Persiapan & Validasi Lingkungan | ✅ Selesai | 2026-06-29 | Koneksi DB terverifikasi sukses di server RSU Al-Arif: pasien (36.030), reg_periksa (73.822), poliklinik (16), dokter (40), user (128). |
| Fase 1 — Login, Layout, Generator no_rawat | ✅ Selesai | 2026-06-29 | Login Admin & User (dokter/perawat) **terkonfirmasi berhasil di server RSU Al-Arif** memakai AES_ENCRYPT/DECRYPT. `generateNoRawat()` & `generateNoRkmMedis()` keduanya final & terkonfirmasi dari data riil. Halaman uji `test-generator-nomor.php` dibuat (read-only, tanpa INSERT) untuk verifikasi visual sebelum Fase 2. |
| Fase 2 — Registrasi Pasien | ⬜ Belum mulai | — | |
| Fase 3 — Asesmen & SOAP | ⬜ Belum mulai | — | |
| Fase 4 — USG, Tindakan, Resep | ⬜ Belum mulai | — | |
| Fase 5 — Billing & Pembukuan | ⬜ Belum mulai | — | |
| Fase 6 — Modul Kecantikan | ⬜ Belum mulai (perlu keputusan #6) | — | |
| Fase 7 — Hardening & Deployment | ⬜ Belum mulai | — | |

Legenda: ⬜ belum mulai · 🟡 sedang berjalan · ✅ selesai · 🔴 terhambat (butuh keputusan)

---

## 3. File Penting yang Sudah Dibuat

```
simrs-kebidanan/
├── config/
│   ├── koneksi.php       → Koneksi PDO ke database `sik` (prepared statement wajib)
│   └── app.php           → Konstanta aplikasi (role, status enum, dll — tanpa secret)
├── lib/
│   ├── auth.php           → Login Admin (tabel admin) & User dokter/perawat (tabel user) —
│   │                         TERKONFIRMASI BERHASIL di server RSU Al-Arif
│   ├── nomor.php           → generateNoRawat() & generateNoRkmMedis() — KEDUANYA FINAL
│   │                         & terkonfirmasi dari data riil RSU Al-Arif
│   ├── layout_header.php  → Partial sidebar + topbar (warna merah maroon)
│   └── layout_footer.php  → Penutup partial layout
├── assets/css/theme.css   → Tema warna merah maroon profesional (DI-APPROVE)
├── login.php              → Halaman login (toggle Admin Utama / Dokter-Perawat) — TERUJI
├── dashboard.php           → Landing page, ringkasan kunjungan hari ini (read-only)
├── logout.php              → Hapus session
├── test-koneksi.php        → Halaman uji SELECT ke tabel inti (SUDAH SUKSES)
├── test-generator-nomor.php → Uji generateNoRawat()/generateNoRkmMedis() (read-only, no INSERT)
├── inspeksi-nomor.php       → Script investigasi awal (SUDAH DIPAKAI — boleh dihapus dari server)
├── inspeksi-nomor-2.php     → Script investigasi lanjutan (TIDAK JADI dipakai karena pengguna
│                               sudah konfirmasi langsung — boleh dihapus dari server)
├── preview-tema.html        → Preview visual komponen UI (SUDAH DI-APPROVE)
└── docs/
    ├── PLAN-SIMRS-KHANZA-WEB-KEBIDANAN.md   → Dokumen rencana utama
    └── KEPUTUSAN-TEKNIS.md                   → File ini
```

---

## 4. Langkah Selanjutnya (Next Action)

- [x] ~~Jalankan inspeksi-nomor.php~~ — selesai, hasil sudah dikonfirmasi pengguna.
- [ ] **Hapus** `inspeksi-nomor.php` dan `inspeksi-nomor-2.php` dari server produksi (sudah tidak diperlukan, jangan biarkan script diagnostik terbuka).
- [ ] Buka `test-generator-nomor.php` di browser untuk verifikasi visual nomor RM & no_rawat berikutnya sudah sesuai ekspektasi.
- [ ] Setelah itu, **lanjut ke Fase 2: Registrasi Pasien** — cari pasien lama, form pasien baru (pakai `generateNoRkmMedis()`), form registrasi kunjungan (pakai `generateNoRawat()`, pilih poli + dokter).
- [ ] Jawab pertanyaan #3 di Bagian 1 (kode poli & dokter spesifik kebidanan/kecantikan) saat mulai Fase 2 — bisa dijawab sambil jalan dari dropdown poli/dokter yang sudah ada di DB.
